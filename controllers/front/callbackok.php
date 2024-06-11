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
            $fraudPayment = handleFraudPayment($response, $transaction);

            if (strpos($shopOrderId, '_') !== false) { // Save transaction details for products created from back orders
                // Handle duplicate callback
                if (!empty($shopOrderId)) {
                    $this->handleDuplicateCallback($response, $transaction, $amountPaid, $cart, $customer, $lockFileName, $lockFileHandle, true);
                }

                // Handle duplicate payment
                $order_id = Order::getOrderByCartId((int) $cart->id);
                if (!empty($order_id)) {
                    $this->handleDuplicatePayment($order_id, $cart, $customer, $transactionID, $postData, $lockFileName, $lockFileHandle, true);
                }

                $this->itemAddedFromBackOrder($cart, $response, $transaction, $fraudPayment, $customer, $customerID, $amountPaid, $lockFileName, $lockFileHandle);
            } else {
                // Handle duplicate callback
                if (!empty($shopOrderId)) {
                    $this->handleDuplicateCallback($response, $transaction, $amountPaid, $cart, $customer, $lockFileName, $lockFileHandle);
                }

                // Handle duplicate payment
                $order_id = Order::getOrderByCartId((int) $cart->id);
                if (!empty($order_id)) {
                    $this->handleDuplicatePayment($order_id, $cart, $customer, $transactionID, $postData, $lockFileName, $lockFileHandle);
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

                if ($paymentType === 'paymentAndCapture' && $response->requireCapture === true) {
                    $response = capturePayment($order->id, $transactionID, $amountPaid, $shopOrderId);
                    $orderStatusCaptured = (int) Configuration::get('PS_OS_PAYMENT');
                    if ($orderStatusCaptured != $orderStatus) {
                        setOrderStateIfNotExistInHistory($order, $orderStatusCaptured);
                    }
                }

                $this->processTransaction($response, $order, $transaction, $cart, $customerID, $paymentType);

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


    /**
     * @param $shopOrderId
     * @return null|array
     */
    public function itemAddedFromBackOrder($cart, $response, $transaction, $fraudPayment, $customer, $customerID, $amountPaid, $lockFileName, $lockFileHandle)
    {
        $shopOrderId = $response->shopOrderId;
        $paymentType = $response->type;
        $transactionID = $transaction->TransactionId;
        // Redirect to payment selection page
        if ($fraudPayment['payment_status']) {
            saveLogs($transaction->FraudExplanation);
            exit('Error in the Payment!');
        } else {
            // Check if an order exist
            $order = getChildOrderFromUniqueId($shopOrderId);
            if (Validate::isLoadedObject($order)) {
                updateChildOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
            } else {
                $order_id = Order::getOrderByCartId((int) ($cart->id));
                $orderDetail = new Order((int) $order_id);
                if ($paymentType === 'paymentAndCapture' && $response->requireCapture === true) {
                    $response = capturePayment($order->id, $transactionID, $amountPaid, $shopOrderId);
                }
                $this->processTransaction($response, $order, $transaction, $cart, $customerID, $paymentType);
                createAltapayOrder($response, $orderDetail, 'succeeded', true);
                unlockCallback($lockFileName, $lockFileHandle);
                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $order_id . '&key=' . $customer->secure_key);
            }
        }
    }

    public function handleDuplicateCallback($response, $transaction, $amountPaid, $cart, $customer, $lockFileName, $lockFileHandle, $isChildOrder = false)
    {
        $shopOrderId = $response->shopOrderId;
        $paymentType = $response->type;
        $requireCapture = $response->requireCapture;
        $transactionID = $transaction->TransactionId;
        // Determine the table name based on whether it's a child order
        $tableName = $isChildOrder ? 'altapay_child_order' : 'altapay_order';

        $condition = "unique_id = '" . pSQL($shopOrderId) . "' AND paymentStatus = 'succeeded'";
        $query = 'SELECT id_order FROM `' . _DB_PREFIX_ . $tableName . '` WHERE ' . $condition;
        $result = Db::getInstance()->executeS($query);

        // Check if the order is already saved with the success status
        if (!empty($result)) {
            // Check if an order exists
            $order = new Order((int)$result[0]['id_order']);
            if (Validate::isLoadedObject($order) && $paymentType === 'paymentAndCapture' && $requireCapture === true) {
                // Capture payment
                $response = capturePayment($order->id, $transactionID, $amountPaid, $shopOrderId);
                if ($isChildOrder) {
                    updateChildOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                } else {
                    updateOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                }
            }
            unlockCallback($lockFileName, $lockFileHandle);
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id . '&id_module=' . (int)$this->module->id . '&id_order=' . (int)$order->id . '&key=' . $customer->secure_key);
        }
    }

    public function handleDuplicatePayment($order_id, $cart, $customer, $transactionID, $postData, $lockFileName, $lockFileHandle, $isChildOrder = false)
    {
        $altapay_order_details = getAltapayOrderDetails($order_id);
        if($isChildOrder) {
            $altapay_order_details = getAltapayChildOrderDetails($order_id);
        }

        if (!empty($altapay_order_details)
            && $altapay_order_details[0]['paymentStatus'] === 'succeeded'
            && $altapay_order_details[0]['payment_id'] != $transactionID
            && $postData['status'] === 'succeeded') {

            // Refund or Release incoming payment request
            refundOrReleaseTransactionByStatus($transactionID);
            unlockCallback($lockFileName, $lockFileHandle);
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id . '&id_module=' . (int)$this->module->id . '&id_order=' . (int)$order_id . '&key=' . $customer->secure_key);
        }
    }

    public function processTransaction($response, $order, $transaction, $cart, $customerID, $paymentType)
    {
        $shopOrderId = $response->shopOrderId;
        $transactionID = $transaction->TransactionId;
        $ccToken = $response->creditCardToken;
        $maskedPan = $response->maskedCreditCard;
        $agreementType = 'unscheduled';

        if (!empty($transaction->ReconciliationIdentifiers)) {
            $reconciliation_identifier = $transaction->ReconciliationIdentifiers[0]->Id;
            $reconciliation_type = $transaction->ReconciliationIdentifiers[0]->Type;
            saveOrderReconciliationIdentifier($order->id, $reconciliation_identifier, $shopOrderId, $reconciliation_type);
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
    }
}
