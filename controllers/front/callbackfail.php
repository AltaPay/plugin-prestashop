<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbackfailModuleFrontController extends ModuleFrontController
{
    /**
     * Method to add external assets
     *
     * @return void
     */
    public function setMedia()
    {
        parent::setMedia();
        $this->addCSS($this->module->getPathUri() . 'css/altapay.css', 'all');
    }

    /**
     * Method to follow when callback fail is being triggered
     *
     * @throws Exception
     *
     * @return void
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

        $customerID = $this->context->customer->id;
        $orderStatus = (int) Configuration::get('authorized_payments_status');
        if (empty($orderStatus)) {
            $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
        }

        $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
        $transaction = null;
        try {
            $response = $callback->call();
            $shopOrderId = $response->shopOrderId;
            $isChildOrder = isChildOrder($shopOrderId);
            $cardHolderMessageMustBeShown = false;
            $merchantError = '';

            if (isset($postData['cardholder_message_must_be_shown'])) {
                $cardHolderMessageMustBeShown = $postData['cardholder_message_must_be_shown'];
            }
            if (isset($postData['error_message']) && isset($postData['merchant_error_message'])) {
                if ($postData['error_message'] != $postData['merchant_error_message']) {
                    $merchantError = $postData['merchant_error_message'];
                }
            }
            if (isset($postData['error_message']) && $cardHolderMessageMustBeShown == 'true') {
                $errorMessage = $postData['error_message'];
            } else {
                $errorMessage = 'Error with the Payment.';
            }

            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                exit('Could not load cart - exiting');
            }

            $paymentType = $response->type;
            $transaction = getTransaction($response);
            if (in_array($transaction->TransactionStatus, ['bank_payment_finalized', 'captured'], true)) {
                $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
            }
            if ($transaction->ReservedAmount > 0) {
                $currencyPaid = Currency::getIdByIsoCode($transaction->MerchantCurrencyAlpha);
                $amountPaid = $isChildOrder ? $response->amount : $cart->getOrderTotal(true, Cart::BOTH);
                $customer = new Customer($cart->id_customer);
                $transactionID = $transaction->TransactionId;
                $ccToken = $response->creditCardToken;
                $maskedPan = $response->maskedCreditCard;
                $agreementType = 'unscheduled';
                $fraudPayment = handleFraudPayment($response, $transaction);

                //Check if this is a duplicate callback
                if (!empty($shopOrderId)) {
                    $tableName = $isChildOrder ? 'altapay_child_order' : 'altapay_order';

                    $condition = "unique_id = '" . pSQL($shopOrderId) . "' AND paymentStatus = 'succeeded'";
                    $query = 'SELECT id_order FROM `' . _DB_PREFIX_ . $tableName . '` WHERE ' . $condition;

                    $result = Db::getInstance()->executeS($query);
                    // Check if the order already saved with the success status
                    if (!empty($result)) {
                        // Check if an order exist
                        $order = new Order((int) $result[0]['id_order']);
                        if (Validate::isLoadedObject($order) and $paymentType === 'paymentAndCapture' and $response->requireCapture === true) {
                            $response = capturePayment($order->id, $transactionID, $amountPaid, $shopOrderId);
                            updateOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                        }
                        unlockCallback($lockFileName, $lockFileHandle);
                        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $order->id . '&key=' . $customer->secure_key);
                    }
                }

                // Check if this is a duplicate payment
                $order_id = Order::getOrderByCartId((int) ($cart->id));
                if (!empty($order_id)) {
                    $altapay_order_details = $isChildOrder ? getAltapayChildOrderDetails($order_id) : getAltapayOrderDetails($order_id);
                    if (!empty($altapay_order_details)
                        and $altapay_order_details[0]['paymentStatus'] === 'succeeded'
                        and $altapay_order_details[0]['payment_id'] != $transactionID
                        and ($postData['status'] === 'succeeded' or $transaction->ReservedAmount > 0)) {
                        // Refund or Release incoming payment request
                        refundOrReleaseTransactionByStatus($transaction);
                        unlockCallback($lockFileName, $lockFileHandle);
                        if ($isChildOrder) {
                            $redirectUrl = Context::getContext()->link->getModuleLink('altapay', 'orderconfirmation', ['id_order' => $order_id]);
                        } else {
                            $redirectUrl = 'index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $order_id . '&key=' . $customer->secure_key;
                        }

                        Tools::redirect($redirectUrl);
                    }
                }

                // Redirect to payment selection page
                if ($fraudPayment['payment_status']) {
                    saveLogs($transaction->FraudExplanation);
                    !$isChildOrder ? redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle) : exit('Error in the Payment!');
                } else {
                    // Check if an order exist
                    $order = $isChildOrder ? getChildOrderFromUniqueId($shopOrderId) : getOrderFromUniqueId($shopOrderId);

                    if (Validate::isLoadedObject($order)) {
                        if ($isChildOrder) {
                            updateChildOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                        } else {
                            updateOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                        }
                    } elseif (!$isChildOrder) {
                        createOrder($transaction, $amountPaid, $currencyPaid, $cart, $orderStatus);
                    }
                }
                // Load order
                if ($isChildOrder) {
                    $order_id = Order::getOrderByCartId((int) ($cart->id));
                    $order = new Order((int) $order_id);
                } else {
                    $order = new Order((int) $this->module->currentOrder);
                }

                if (Validate::isLoadedObject($order)) {
                    if (!empty($transaction->ReconciliationIdentifiers)) {
                        $reconciliation_identifier = $transaction->ReconciliationIdentifiers[0]->Id;
                        $reconciliation_type = $transaction->ReconciliationIdentifiers[0]->Type;
                        saveOrderReconciliationIdentifier($order->id, $reconciliation_identifier, $shopOrderId, $reconciliation_type);
                    }
                    if ($paymentType === 'paymentAndCapture' && $response->requireCapture === true) {
                        $response = capturePayment($order->id, $transactionID, $amountPaid, $shopOrderId);
                        $orderStatusCaptured = (int) Configuration::get('PS_OS_PAYMENT');
                        if (($orderStatusCaptured != $orderStatus) && !$isChildOrder) {
                            setOrderStateIfNotExistInHistory($order, $orderStatusCaptured);
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
                    createAltapayOrder($response, $order, 'succeeded', $isChildOrder);
                    unlockCallback($lockFileName, $lockFileHandle);
                    if ($isChildOrder) {
                        $redirectUrl = Context::getContext()->link->getModuleLink('altapay', 'orderconfirmation', ['id_order' => $order->id]);
                    } else {
                        $redirectUrl = 'index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $order->id . '&key=' . $customer->secure_key;
                    }

                    Tools::redirect($redirectUrl);
                } else {
                    saveLogs('Something went wrong');
                    redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle);
                }
            }
        } catch (API\PHP\Altapay\Exceptions\ClientException $e) {
            $errorMessage = $e->getResponse()->getBody();
        } catch (API\PHP\Altapay\Exceptions\ResponseHeaderException $e) {
            $errorMessage = $e->getHeader()->ErrorMessage;
        } catch (API\PHP\Altapay\Exceptions\ResponseMessageException $e) {
            $errorMessage = $e->getMessage();
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }

        // Successful order exists, set its status to cancel
        $order = getOrderFromUniqueId($postData['shop_orderid']);

        if (Validate::isLoadedObject($order) and !empty($transaction) && $transaction->ReservedAmount == 0) {
            if ($isChildOrder) {
                updatePaymentStatusForChildOrder($postData['transaction_id'], $postData['status']);
                saveLastErrorMessageForChildOrder($postData['transaction_id'], $errorMessage);
            } else {
                updatePaymentStatus($postData['transaction_id'], $postData['status']);
                saveLastErrorMessage($postData['transaction_id'], $errorMessage);
                $orderStatusCancelled = (int) Configuration::get('PS_OS_CANCELED');
                setOrderStateIfNotExistInHistory($order, $orderStatusCancelled);
            }
        }

        $status = isset($response) ? strtolower($response->Result) : '';
        $unique_id = $postData['shop_orderid'];
        if ($status === 'cancelled') {
            $unique_id = $postData['shop_orderid'];
            // Updated transaction record to cancel
            $pI = pSQL($unique_id);
            $q = 'UPDATE `' . _DB_PREFIX_ . 'altapay_transaction` set `is_cancelled`=1, `transaction_status` = "' . $status . '" WHERE `unique_id`=\'' . $pI . '\'';
            Db::getInstance()->Execute($q);

            // Redirect back to either standard or quick checkout process
            $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
            $pLink = $this->context->link->getPageLink($controller);
            $vCan = 'altapay_cancel=1&isPaymentStep=true&step=3#altapay_cancel';
            $location = $pLink . (strpos($controller, '?') !== false ? '&' : '?') . $vCan;
            Tools::redirectLink($location);
        } else {
            if (!empty($merchantError)) {
                $errorMessage = $errorMessage . '|' . $merchantError;
            }

            $mNa = $this->module->name;
            $mId = $this->module->id;
            PrestaShopLogger::addLog('Error Message: ' . $errorMessage, 3, 2001, $mNa, $mId, true);
            $this->context->smarty->assign([
                'errorText' => $postData['error_message'],
                'unique_id' => $unique_id,
                'payment_id' => $postData['transaction_id'],
                'this_path' => $this->module->getPathUri(),
                'this_path_altapay' => $this->module->getPathUri(),
                'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $mNa . '/',
                'css_dir' => null,
            ]);
            // PrestaShop 1.6 and PrestaShop 1.7 have different declarations of $this->setTemplate()
            if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                $this->setTemplate('module:altapay/views/templates/front/payment_error17.tpl');
            } else {
                $this->setTemplate('payment_error.tpl');
            }
        }
    }
}
