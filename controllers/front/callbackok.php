<?php

/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbackokModuleFrontController extends ModuleFrontController
{
    /**
     * Method to follow when callback ok is being triggered
     *
     * @return void
     *
     * @throws AltapayXmlException
     * @throws AltapayMerchantAPIException
     */
    public function postProcess()
    {
        $postData = getAltaPayCallbackData();
        $checksum = !empty($postData['checksum']) ? $postData['checksum'] : '';
        $terminal_name = getTransactionTerminalByUniqueId($postData['shop_orderid']);
        $secret = Altapay_Models_Terminal::getTerminalSecretByRemoteName($terminal_name);

        if (!empty($checksum) and !empty($secret) and calculateChecksum($postData, $secret) !== $checksum) {
            exit('Invalid request');
        }

        // Create lock file name based on transaction_id so that it locks creation of current order only.
        // Locking prevents attempt to create order in PrestaShop if notification & ok callbacks get processed simultaneously.

        $lockFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'callback_lock_' . md5($postData['transaction_id']) . '.lock';
        $lockFileHandle = lockCallback($lockFileName);

        $message = '';
        $orderStatus = (int) Configuration::get('authorized_payments_status');
        if (empty($orderStatus)) {
            $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
        }
        $customerID = $this->context->customer->id;
        $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
        try {
            $response = $callback->call();
            $shopOrderId = $response->shopOrderId;
            $paymentType = $response->type;
            $transaction = getTransaction($response);
            if (in_array($transaction->TransactionStatus, ['bank_payment_finalized', 'captured'], true)) {
                $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
            }

            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                unlockCallback($lockFileName, $lockFileHandle);
                exit('Could not load cart - exiting');
            }
            $currencyPaid = Currency::getIdByIsoCode($transaction->MerchantCurrencyAlpha);
            $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
            $customer = new Customer($cart->id_customer);
            $transactionID = $transaction->TransactionId;
            $ccToken = $response->creditCardToken;
            $maskedPan = $response->maskedCreditCard;
            $agreementType = 'unscheduled';
            $fraudPayment = handleFraudPayment($response, $transaction);
            //Check if this is a duplicate callback
            if (!empty($shopOrderId)) {
                $condition = "unique_id = '" . pSQL($shopOrderId) . "' AND paymentStatus = 'succeeded'";
                $query = 'SELECT id_order FROM `' . _DB_PREFIX_ . 'altapay_order` WHERE ' . $condition;
                $result = Db::getInstance()->executeS($query);
                // Check if the order already saved with the success status
                if (!empty($result)) {
                    // Check if an order exist
                    $order = new Order((int) $result[0]['id_order']);
                    if (Validate::isLoadedObject($order) and $paymentType === 'paymentAndCapture' and $response->requireCapture === true) {
                        $response = capturePayment($order->id, $transactionID, $amountPaid);
                        updateOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                    }
                    unlockCallback($lockFileName, $lockFileHandle);
                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $order->id . '&key=' . $customer->secure_key);
                }
            }

            // Check if this is a duplicate payment
            $order_id = Order::getOrderByCartId((int) ($cart->id));
            if (!empty($order_id)) {
                $altapay_order_details = getAltapayOrderDetails($order_id);
                if (!empty($altapay_order_details)
                    and $altapay_order_details[0]['paymentStatus'] === 'succeeded'
                    and $altapay_order_details[0]['payment_id'] != $transactionID
                    and $postData['status'] === 'succeeded') {
                    //refund or release incoming payment request
                    if (in_array($transaction->TransactionStatus, ['captured', 'bank_payment_finalized'], true)) {
                        $api = new API\PHP\Altapay\Api\Payments\RefundCapturedReservation(getAuth());
                    } else {
                        $api = new API\PHP\Altapay\Api\Payments\ReleaseReservation(getAuth());
                    }
                    $api->setTransaction($transactionID);
                    $api->call();
                    unlockCallback($lockFileName, $lockFileHandle);
                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $order_id . '&key=' . $customer->secure_key);
                }
            }

            // Redirect to payment selection page
            if ($fraudPayment['payment_status']) {
                saveLogs($transaction->FraudExplanation);
                redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle);
            } else {
                // Check if an order exist
                $order = getOrderFromUniqueId($shopOrderId);
                if (Validate::isLoadedObject($order)) {
                    updateOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                } else {
                    createOrder($response, $currencyPaid, $cart, $orderStatus);
                }
            }
            // Load order
            $order = new Order((int) $this->module->currentOrder);

            if (Validate::isLoadedObject($order)) {
                if (!empty($transaction->ReconciliationIdentifiers)) {
                    $reconciliation_identifier = $transaction->ReconciliationIdentifiers[0]->Id;
                    $reconciliation_type = $transaction->ReconciliationIdentifiers[0]->Type;
                    saveOrderReconciliationIdentifier($order->id, $reconciliation_identifier, $reconciliation_type);
                }
                if ($paymentType === 'paymentAndCapture' && $response->requireCapture === true) {
                    $response = capturePayment($order->id, $transactionID, $amountPaid);
                    $orderStatusCaptured = (int) Configuration::get('PS_OS_PAYMENT');
                    if ($orderStatusCaptured != $orderStatus) {
                        $order->setCurrentState($orderStatusCaptured);
                    }
                }

                if ($paymentType === 'verifyCard') {
                    handleVerifyCard($shopOrderId, $transaction, $ccToken, $maskedPan, $customerID, $cart, $agreementType);
                }
                if (in_array($paymentType, ['subscription', 'subscriptionAndCharge'])) {
                    $sql = 'INSERT INTO `' . _DB_PREFIX_
                        . 'altapay_saved_credit_card` (time,userID,agreement_id,agreement_type,id_order) VALUES (Now(),'
                        . pSQL($customerID) . ',"' . pSQL($transactionID) . '","'
                        . pSQL('recurring') . '","' . pSQL($order->id)
                        . '")';
                    Db::getInstance()->executeS($sql);
                }

                // Log order
                createAltapayOrder($response, $order);
                unlockCallback($lockFileName, $lockFileHandle);
                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key);
            } else {
                saveLogs('Something went wrong');
                redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle);
            }
        } catch (API\PHP\Altapay\Exceptions\ClientException $e) {
            $message = $e->getResponse()->getBody();
        } catch (API\PHP\Altapay\Exceptions\ResponseHeaderException $e) {
            $message = $e->getHeader()->ErrorMessage;
        } catch (API\PHP\Altapay\Exceptions\ResponseMessageException $e) {
            $message = $e->getMessage();
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        saveLogs($message);
        redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle);
    }
}
