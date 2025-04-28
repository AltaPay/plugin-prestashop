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
        $message = '';
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
        try {
            $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
            $response = $callback->call();
            $transaction = getTransaction($response);
            $transactionId = $transaction->TransactionId;
            $shopOrderId = $response->shopOrderId;
            $isChildOrder = isChildOrder($shopOrderId);
            $tableName = $isChildOrder ? 'altapay_child_order' : 'altapay_order';
            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                unlockCallback($lockFileName, $lockFileHandle);
                exit('Could not load cart - exiting');
            }
            if (!empty($shopOrderId)) {
                $condition = "unique_id = '" . pSQL($shopOrderId) . "' AND paymentStatus = 'succeeded'";
                $query = 'SELECT id_order, payment_id FROM `' . _DB_PREFIX_ . $tableName . '` WHERE ' . $condition;
                $result = Db::getInstance()->executeS($query);

                // Check if the order already saved with the success status
                if (!empty($result)) {
                    if ($postData['status'] === 'succeeded' and $postData['payment_status'] === 'bank_payment_refunded'
                        and $transactionId == $result[0]['payment_id']) {
                        if ($isChildOrder) {
                            $order = getChildOrderFromUniqueId($shopOrderId);
                        } else {
                            $order = getOrderFromUniqueId($shopOrderId);
                            if (Validate::isLoadedObject($order)) {
                                $refundStatus = Configuration::get('manual_refund_payments_status');
                                if ($refundStatus !== $this->module::ALTAPAY_MANUAL_CAPTURE_REFUND_STATUS) {
                                    $order->setCurrentState((int) Configuration::get('PS_OS_REFUND'));
                                }
                            }
                        }

                        saveReconciliationDetails($response, $order);
                        unlockCallback($lockFileName, $lockFileHandle);
                        exit('Order refund status updated.');
                    } else {
                        unlockCallback($lockFileName, $lockFileHandle);
                        exit('Order already Processed!!');
                    }
                }
            }

            // Check if this is a duplicate payment
            $order_id = Order::getOrderByCartId((int) ($cart->id));
            if (!empty($order_id)) {
                $altapay_order_details = $isChildOrder ? getAltapayChildOrderDetails($shopOrderId) : getAltapayOrderDetails($order_id);
                if (!empty($altapay_order_details)
                    and $altapay_order_details[0]['paymentStatus'] === 'succeeded'
                    and $altapay_order_details[0]['payment_id'] != $transactionId
                    and $postData['status'] === 'succeeded') {
                    // Refund or Release incoming payment request
                    refundOrReleaseTransactionByStatus($transaction);
                    unlockCallback($lockFileName, $lockFileHandle);
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

            if (!$isChildOrder) {
                if (empty($order_state)) {
                    $order_state = (int) Configuration::get('PS_OS_PAYMENT');
                }
                if (in_array($transactionStatus, $captured_statuses, true)) {
                    $order_state = (int) Configuration::get('PS_OS_PAYMENT');
                }
            }
            $resultStatus = strtolower($response->Result);
            updateTransactionStatus($shopOrderId, $resultStatus);

            // Check if an order exist
            $order = getOrderFromUniqueId($shopOrderId);
            $fraudPayment = handleFraudPayment($response, $transaction);
            $errorStatus = ['cancelled', 'declined', 'error', 'failed', 'incomplete', 'open'];
            if (!in_array($resultStatus, $errorStatus, true) && !$fraudPayment['payment_status']) {
                if ($isChildOrder) {
                    $this->processChildOrder($cart, $transactionId, $response, $transactionStatus, $auth_statuses, $captured_statuses, $lockFileName, $lockFileHandle);
                }
                // NO ORDER FOUND, CREATE?
                if (!Validate::isLoadedObject($order)) {
                    $transaction = getTransaction($response);
                    // Payment successful - create order
                    if ($response && is_array($response->Transactions)) {
                        $amount = $response->amount ?? $cart->getOrderTotal(true, Cart::BOTH);
                        $currencyId = Currency::getIdByIsoCode($transaction->MerchantCurrencyAlpha) ?? $cart->id_currency;
                        $currency_iso_code = $transaction->MerchantCurrencyAlpha;
                        if (empty($currency_iso_code)) {
                            $currency = new Currency($currencyId);
                            $currency_iso_code = $currency->iso_code;
                        }
                        $remoteId = getTerminalIdByRemoteName($transaction->Terminal, $cart->id_shop);
                        $terminal = getTerminal($remoteId, $currency_iso_code);
                        $paymentMethod = !empty($terminal) ? $terminal->display_name : $transaction->PaymentNature;

                        // Create an order with 'payment accepted' status
                        $this->module->validateOrder(
                            $cart->id,
                            $order_state,
                            $amount,
                            $paymentMethod,
                            null,
                            null,
                            (int) $currencyId,
                            false,
                            $customer->secure_key
                        );
                        // Log order
                        $currentOrder = new Order((int) $this->module->currentOrder);

                        createAltapayOrder($response, $currentOrder);

                        if (!empty($response->Transactions[0]->ReconciliationIdentifiers)) {
                            $reconciliation_identifier = $response->Transactions[0]->ReconciliationIdentifiers[0]->Id;
                            $reconciliation_type = $response->Transactions[0]->ReconciliationIdentifiers[0]->Type;

                            saveOrderReconciliationIdentifierIfNotExists($currentOrder->id, $reconciliation_identifier, $reconciliation_type, $shopOrderId);
                        }
                        unlockCallback($lockFileName, $lockFileHandle);
                        exit('Order created');
                    } else {
                        unlockCallback($lockFileName, $lockFileHandle);
                        exit('Only handling Success state');
                    }
                } elseif ($order->getCurrentState() != Configuration::get('ALTAPAY_OS_PENDING')) { //Order found, but not pending
                    unlockCallback($lockFileName, $lockFileHandle);
                    exit('Order found but is not currently pending - ignoring');
                } elseif (Validate::isLoadedObject($order)) { // Pending order found, update
                    if (in_array($transactionStatus, $auth_statuses, true) or in_array($transactionStatus, $captured_statuses, true)) {
                        /*
                         * preauth occurs for wallet transactions where payment type is 'payment'.
                         * Funds are still waiting to be captured.
                         * For this scenario we change the order status to 'payment accepted'.
                         * bank_payment_finalized is for ePayments.
                         */
                        setOrderStateIfNotExistInHistory($order, $order_state);
                        // Update payment status to 'succeeded'
                        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
                    SET `paymentStatus` = \'succeeded\' WHERE `id_order` = ' . (int) $order->id;
                        Db::getInstance()->Execute($sql);

                        if (!empty($response->Transactions[0]->ReconciliationIdentifiers)) {
                            $reconciliation_identifier = $response->Transactions[0]->ReconciliationIdentifiers[0]->Id;
                            $reconciliation_type = $response->Transactions[0]->ReconciliationIdentifiers[0]->Type;

                            saveOrderReconciliationIdentifierIfNotExists($order->id, $reconciliation_identifier, $reconciliation_type, $shopOrderId);
                        }
                        unlockCallback($lockFileName, $lockFileHandle);
                        exit('Order status updated to Accepted');
                    } elseif ($transactionStatus === 'epayment_declined') {
                        // Update payment status to 'declined'
                        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
                        SET `paymentStatus` = \'declined\' WHERE `id_order` = ' . (int) $order->id;
                        Db::getInstance()->Execute($sql);
                        unlockCallback($lockFileName, $lockFileHandle);
                        exit('Order status updated to Error');
                    } else {
                        // Unexpected scenario
                        $mNa = $this->module->name;
                        PrestaShopLogger::addLog('Unexpected scenario: Callback notification was received for Transaction '
                            . $shopOrderId . ' with payment status ' . $transactionStatus, 3, '1005', $mNa,
                            $this->module->id, true);
                        unlockCallback($lockFileName, $lockFileHandle);
                        exit('Unrecognized status received ' . $transactionStatus);
                    }
                }
            } elseif (Validate::isLoadedObject($order) and $transactionStatus === 'epayment_declined') {
                // Update payment status to 'declined'
                $sql = 'UPDATE `' . _DB_PREFIX_ . $tableName . '`
                        SET `paymentStatus` = \'declined\' WHERE `id_order` = ' . (int) $order->id;
                Db::getInstance()->Execute($sql);
                unlockCallback($lockFileName, $lockFileHandle);
                exit('Order status updated to Error');
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
                unlockCallback($lockFileName, $lockFileHandle);
                exit('Unrecognized status received ' . $transactionStatus);
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
        $message = 'Callback notification issue: ' . $message;
        PrestaShopLogger::addLog($message,
            3,
            '1005',
            $this->module->name,
            $this->module->id,
            true
        );
        unlockCallback($lockFileName, $lockFileHandle);
        exit($message);
    }

    public function processChildOrder($cart, $transactionId, $response, $transactionStatus, $auth_statuses, $captured_statuses, $lockFileName, $lockFileHandle)
    {
        $shopOrderId = $response->shopOrderId;
        // Check if an order exist
        $order = getChildOrderFromUniqueId($shopOrderId);
        if (!Validate::isLoadedObject($order)) {
            $order_id = Order::getOrderByCartId((int) ($cart->id));
            $order = new Order((int) $order_id);
            createAltapayOrder($response, $order, 'succeeded', true);
            if (!empty($response->Transactions[0]->ReconciliationIdentifiers)) {
                $reconciliation_identifier = $response->Transactions[0]->ReconciliationIdentifiers[0]->Id;
                $reconciliation_type = $response->Transactions[0]->ReconciliationIdentifiers[0]->Type;

                saveOrderReconciliationIdentifierIfNotExists($order->id, $reconciliation_identifier, $reconciliation_type, $shopOrderId);
            }
            unlockCallback($lockFileName, $lockFileHandle);
            exit('Store transaction details for the Item Added from Back order');
        } elseif (Validate::isLoadedObject($order)) {
            if (in_array($transactionStatus, $auth_statuses, true) or in_array($transactionStatus, $captured_statuses, true)) {
                // Update payment status to 'succeeded'
                $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_child_order` 
                    SET `paymentStatus` = \'succeeded\' WHERE `unique_id` = \'' . pSQL($shopOrderId) . "'";
                Db::getInstance()->Execute($sql);

                if (!empty($response->Transactions[0]->ReconciliationIdentifiers)) {
                    $reconciliation_identifier = $response->Transactions[0]->ReconciliationIdentifiers[0]->Id;
                    $reconciliation_type = $response->Transactions[0]->ReconciliationIdentifiers[0]->Type;

                    saveOrderReconciliationIdentifierIfNotExists($order->id, $reconciliation_identifier, $reconciliation_type, $shopOrderId);
                }
                if (in_array($transactionStatus, $captured_statuses, true)) {
                    markChildOrderAsCaptured($transactionId);
                }
                unlockCallback($lockFileName, $lockFileHandle);
                exit('Child Order status updated');
            }
        }
    }
}
