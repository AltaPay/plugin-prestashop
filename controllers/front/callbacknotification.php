<?php

/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbacknotificationModuleFrontController extends ModuleFrontController
{
    /**
     * Method to follow when callback notification is being triggered
     *
     * @return void
     *
     * @throws Exception
     */
    public function postProcess()
    {
        $postData = Tools::getAllValues();
        $checksum = !empty($postData['checksum']) ? $postData['checksum'] : '';
        $terminal_name = getTransactionTerminalByUniqueId($postData['shop_orderid']);
        $secret = Altapay_Models_Terminal::getTerminalSecretByRemoteName($terminal_name);

        if (!empty($checksum) and !empty($secret) and calculateChecksum($postData, $secret) !== $checksum) {
            exit();
        }

        $fp = fopen(_PS_MODULE_DIR_ . '/altapay/controllers/front/lock.txt', 'r');
        try {
            $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
            $response = $callback->call();
            $transaction = getTransaction($response);
            $transactionId = $transaction->TransactionId;
            $shopOrderId = $response->shopOrderId;
            flock($fp, LOCK_EX);
            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                $this->unlock($fp);
                exit('Could not load cart - exiting');
            }
            if (!empty($shopOrderId)) {
                $condition = "unique_id = '" . pSQL($shopOrderId) . "' AND paymentStatus = 'succeeded'";
                $query = 'SELECT id_order, payment_id FROM `' . _DB_PREFIX_ . 'altapay_order` WHERE ' . $condition;
                $result = Db::getInstance()->executeS($query);
                // Check if the order already saved with the success status
                if (!empty($result)) {
                    if ($postData['status'] === 'succeeded' and $postData['payment_status'] === 'bank_payment_refunded'
                        and $transactionId == $result[0]['payment_id']) {
                        $order = getOrderFromUniqueId($shopOrderId);
                        if (Validate::isLoadedObject($order)) {
                            $order->setCurrentState((int) Configuration::get('PS_OS_REFUND'));
                            saveReconciliationDetails($response, $order);
                            $this->unlock($fp);
                            exit('Order refund status updated.');
                        }
                    } else {
                        $this->unlock($fp);
                        exit('Order already Processed!!');
                    }
                }
            }

            // Check if this is a duplicate payment
            $order_id = Order::getOrderByCartId((int) ($cart->id));
            if (!empty($order_id)) {
                $altapay_order_details = getAltapayOrderDetails($order_id);
                if (!empty($altapay_order_details)
                    and $altapay_order_details[0]['paymentStatus'] === 'succeeded'
                    and $altapay_order_details[0]['payment_id'] != $transactionId
                    and $postData['status'] === 'succeeded') {
                    //refund or release incoming payment request
                    if (in_array($transaction->TransactionStatus, ['captured', 'bank_payment_finalized'], true)) {
                        $api = new API\PHP\Altapay\Api\Payments\RefundCapturedReservation(getAuth());
                    } else {
                        $api = new API\PHP\Altapay\Api\Payments\ReleaseReservation(getAuth());
                    }
                    $api->setTransaction($transactionId);
                    $api->call();
                    $this->unlock($fp);
                    exit('Order already Processed!');
                }
            }

            // Load the customer
            $customer = new Customer((int) $cart->id_customer);
            $transactionStatus = $response->paymentStatus;

            if ($response && is_array($response->Transactions)) {
                $transactionStatus = $response->Transactions[0]->TransactionStatus;
            }

            $auth_statuses = ['preauth', 'invoice_initialized', 'recurring_confirmed'];
            $captured_statuses = ['bank_payment_finalized', 'captured'];
            $order_state = (int) Configuration::get('authorized_payments_status');
            if (empty($order_state)) {
                $order_state = (int) Configuration::get('PS_OS_PAYMENT');
            }
            if (in_array($transactionStatus, $captured_statuses, true)) {
                $order_state = (int) Configuration::get('PS_OS_PAYMENT');
            }

            $resultStatus = strtolower($response->Result);
            updateTransactionStatus($shopOrderId, $resultStatus);
            // Check if an order exist
            $order = getOrderFromUniqueId($shopOrderId);
            $fraudPayment = handleFraudPayment($response, $transaction);
            $errorStatus = ['cancelled', 'declined', 'error', 'failed', 'incomplete', 'open'];
            if (!in_array($resultStatus, $errorStatus, true) && !$fraudPayment['payment_status']) {
                // NO ORDER FOUND, CREATE?
                if (!Validate::isLoadedObject($order)) {
                    // Payment successful - create order
                    if ($response && is_array($response->Transactions)) {
                        $currency = Currency::getIdByIsoCode($response->currency);
                        $amount = $response->amount;
                        $paymentType = $response->Transactions[0]->AuthType;
                        /*
                        If payment type is 'payment' funds have not yet been captured,
                        * so AltaPay returns 0 as the captured amount.Therefore, we assume full payment has been authorized.
                        */
                        if ($paymentType === 'payment') {
                            $amount = $cart->getOrderTotal(true, Cart::BOTH);
                            $currency = new Currency($cart->id_currency);
                        }
                        // Determine payment method for display
                        $paymentMethod = determinePaymentMethodForDisplay($response);

                        // Create an order with 'payment accepted' status
                        $this->module->validateOrder(
                            $cart->id,
                            $order_state,
                            $amount,
                            $paymentMethod,
                            null,
                            null,
                            (int) $currency->id,
                            false,
                            $customer->secure_key
                        );
                        // Log order
                        $currentOrder = new Order((int) $this->module->currentOrder);

                        createAltapayOrder($response, $currentOrder);

                        if (!empty($response->Transactions[0]->ReconciliationIdentifiers)) {
                            $reconciliation_identifier = $response->Transactions[0]->ReconciliationIdentifiers[0]->Id;
                            $reconciliation_type = $response->Transactions[0]->ReconciliationIdentifiers[0]->Type;

                            saveOrderReconciliationIdentifierIfNotExists($currentOrder->id, $reconciliation_identifier, $reconciliation_type);
                        }
                        $this->unlock($fp);
                        exit('Order created');
                    } else {
                        $this->unlock($fp);
                        exit('Only handling Success state');
                    }
                } elseif ($order->getCurrentState() != Configuration::get('ALTAPAY_OS_PENDING')) { //Order found, but not pending
                    $this->unlock($fp);
                    exit('Order found but is not currently pending - ignoring');
                } elseif (Validate::isLoadedObject($order)) { // Pending order found, update
                    if (in_array($transactionStatus, $auth_statuses, true) or in_array($transactionStatus, $captured_statuses, true)) {
                        /*
                         * preauth occurs for wallet transactions where payment type is 'payment'.
                         * Funds are still waiting to be captured.
                         * For this scenario we change the order status to 'payment accepted'.
                         * bank_payment_finalized is for ePayments.
                         */
                        $order->setCurrentState($order_state);
                        // Update payment status to 'succeeded'
                        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
                    SET `paymentStatus` = \'succeeded\' WHERE `id_order` = ' . (int) $order->id;
                        Db::getInstance()->Execute($sql);
                        $payment = $order->getOrderPaymentCollection();
                        if (isset($payment[0])) {
                            $payment[0]->transaction_id = pSQL($shopOrderId);
                            $payment[0]->save();
                        }

                        if (!empty($response->Transactions[0]->ReconciliationIdentifiers)) {
                            $reconciliation_identifier = $response->Transactions[0]->ReconciliationIdentifiers[0]->Id;
                            $reconciliation_type = $response->Transactions[0]->ReconciliationIdentifiers[0]->Type;

                            saveOrderReconciliationIdentifierIfNotExists($order->id, $reconciliation_identifier, $reconciliation_type);
                        }
                        $this->unlock($fp);
                        exit('Order status updated to Accepted');
                    } elseif ($transactionStatus === 'epayment_declined') {
                        // Update payment status to 'declined'
                        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
                        SET `paymentStatus` = \'declined\' WHERE `id_order` = ' . (int) $order->id;
                        Db::getInstance()->Execute($sql);
                        $this->unlock($fp);
                        exit('Order status updated to Error');
                    } else {
                        // Unexpected scenario
                        $mNa = $this->module->name;
                        PrestaShopLogger::addLog('Unexpected scenario: Callback notification was received for Transaction '
                            . $shopOrderId . ' with payment status ' . $transactionStatus, 3, '1005', $mNa,
                            $this->module->id, true);
                        $this->unlock($fp);
                        exit('Unrecognized status received ' . $transactionStatus);
                    }
                }
            } else {
                updateTransactionStatus($shopOrderId, $resultStatus);
                // Unexpected scenario
                PrestaShopLogger::addLog('Callback notification was received for Transaction ' . $shopOrderId . ' with payment status ' . $transactionStatus,
                    3,
                    '1005',
                    $this->module->name,
                    $this->module->id,
                    true
                );
                $this->unlock($fp);
                exit('Unrecognized status received ' . $transactionStatus);
            }
        } catch (PrestaShopException $e) {
            PrestaShopLogger::addLog('Callback notification issue, Message ' . $e->displayMessage(),
                3,
                '1005',
                $this->module->name,
                $this->module->id,
                true
            );

            $this->unlock($fp);
        } finally {
            $this->unlock($fp);
        }
    }

    /**
     * @param string $fileOpen
     *
     * @return void
     */
    public function unlock($fileOpen)
    {
        flock($fileOpen, LOCK_UN);
        fclose($fileOpen);
    }
}
