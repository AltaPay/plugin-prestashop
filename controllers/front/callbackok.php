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
        $postData = Tools::getAllValues();
        $checksum = !empty($postData['checksum']) ? $postData['checksum'] : '';
        $terminal_name = getTransactionTerminalByUniqueId($postData['shop_orderid']);
        $secret = Altapay_Models_Terminal::getTerminalSecretByRemoteName($terminal_name);

        if (!empty($checksum) and !empty($secret) and calculateChecksum($postData, $secret) !== $checksum) {
            exit('Invalid request');
        }

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
            $currencyPaid = Currency::getIdByIsoCode($response->currency);
            $paymentType = $response->type;
            $transaction = getTransaction($response);
            if (in_array($transaction->TransactionStatus, ['bank_payment_finalized', 'captured'], true)) {
                $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
            }

            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                exit('Could not load cart - exiting');
            }
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
                        $response = $this->capturePayment($order->id, $transactionID, $amountPaid);
                        $this->updateOrder($cart, $order, $response, $shopOrderId);
                    }
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
                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $order_id . '&key=' . $customer->secure_key);
                }
            }

            // Redirect to payment selection page
            if ($fraudPayment['payment_status']) {
                $this->saveLogs($transaction->FraudExplanation);
                $this->redirectUserToCheckoutPaymentStep();
            } else {
                // Check if an order exist
                $order = getOrderFromUniqueId($shopOrderId);
                if (Validate::isLoadedObject($order)) {
                    $this->updateOrder($cart, $order, $response, $shopOrderId);
                } else {
                    $this->createOrder($response, $currencyPaid, $cart, $orderStatus);
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
                    $response = $this->capturePayment($order->id, $transactionID, $amountPaid);
                    $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
                }
                $order->setCurrentState($orderStatus);

                if ($paymentType === 'verifyCard') {
                    $this->handleVerifyCard($shopOrderId, $transaction, $ccToken, $maskedPan, $customerID, $cart, $agreementType);
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
                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key);
            } else {
                $this->saveLogs('Something went wrong');
                $this->redirectUserToCheckoutPaymentStep();
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
        $this->saveLogs($message);
        $this->redirectUserToCheckoutPaymentStep();
    }

    /**
     * @param $order_id
     * @param $transaction_id
     * @param $amount
     *
     * @return \API\PHP\Altapay\Response\AbstractResponse|\API\PHP\Altapay\Response\Embeds\Transaction[]|string
     */
    public function capturePayment($order_id, $transaction_id, $amount)
    {
        $reconciliation_identifier = sha1($transaction_id . time());
        $api = new API\PHP\Altapay\Api\Payments\CaptureReservation(getAuth());
        $api->setTransaction($transaction_id);
        $api->setAmount($amount);
        $api->setReconciliationIdentifier($reconciliation_identifier);
        $response = $api->call();
        saveOrderReconciliationIdentifier($order_id, $reconciliation_identifier);

        return $response;
    }

    /**
     * @return void
     */
    public function redirectUserToCheckoutPaymentStep()
    {
        /* Redirect user back to the checkout payment step,
        * assume a failure occurred creating the URL until a payment url is received
        */
        $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
        $as = $this->context->link;
        $con = $controller;
        $redirect = $as->getPageLink($con, true, null, 'step=3&altapay_unavailable=1') . '#altapay_unavailable';
        Tools::redirect($redirect);
    }

    /**
     * @param $response
     * @param $currencyPaid
     * @param $cart
     * @param $orderStatus
     *
     * @return void
     */
    private function createOrder($response, $currencyPaid, $cart, $orderStatus)
    {
        // Determine payment method for display
        $paymentMethod = determinePaymentMethodForDisplay($response);
        // Create an order with 'payment accepted' status
        $currencyPaidID = (int) $currencyPaid->id;
        $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
        $cartID = $cart->id;

        // Load the customer
        $customer = new Customer((int) $cart->id_customer);
        $customerSecureKey = $customer->secure_key;
        $this->module->validateOrder($cartID, $orderStatus, $amountPaid,
            $paymentMethod, null, null,
            $currencyPaidID, false, $customerSecureKey);
    }

    /**
     * @param $message
     *
     * @return void
     */
    protected function saveLogs($message)
    {
        // Log message and return payment status
        $module = $this->module;
        PrestaShopLogger::addLog($message, 3, '1004', $module->name, $module->id, true);
        $responseMessage = ($message !== '') ? $message : $this->module->l('This payment method is not available 1004.', 'callbackok');
        echo $this->module->l($responseMessage, 'callbackOk');
    }

    /**
     * @param $shopOrderId
     * @param $transaction
     * @param $ccToken
     * @param $maskedPan
     * @param $customerID
     * @param $cart
     * @param $agreementType
     *
     * @return void
     */
    protected function handleVerifyCard(
        $shopOrderId,
        $transaction,
        $ccToken,
        $maskedPan,
        $customerID,
        $cart,
        $agreementType
    ) {
        $message = '';
        $expires = '';
        $cardType = '';
        $transactionID = $transaction->TransactionId;
        $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
        if (isset($transaction->CapturedAmount)) {
            $amountPaid = $transaction->CapturedAmount;
        }
        if (isset($transaction->CreditCardExpiry->Month)
            && isset($transaction->CreditCardExpiry->Year)
        ) {
            $expires = $transaction->CreditCardExpiry->Month . '/'
                . $transaction->CreditCardExpiry->Year;
        }
        if (isset($transaction->PaymentSchemeName)) {
            $cardType = $transaction->PaymentSchemeName;
        }
        $currencyPaid = new Currency($cart->id_currency);
        $sql = 'INSERT INTO `' . _DB_PREFIX_
            . 'altapay_saved_credit_card` (time,userID,agreement_id,agreement_type,cardBrand,creditCardNumber,cardExpiryDate,ccToken) VALUES (Now(),'
            . pSQL($customerID) . ',"' . pSQL($transactionID) . '","'
            . pSQL($agreementType) . '","' . pSQL($cardType) . '","'
            . pSQL($maskedPan) . '","' . pSQL($expires) . '","' . pSQL($ccToken)
            . '")';
        Db::getInstance()->executeS($sql);

        $request = new API\PHP\Altapay\Api\Payments\ReservationOfFixedAmount(getAuth());
        $request->setCreditCardToken($transaction->CreditCardToken)
            ->setTerminal($transaction->Terminal)
            ->setShopOrderId($shopOrderId)
            ->setAmount($amountPaid)
            ->setCurrency($currencyPaid->iso_code)
            ->setAgreement([
                'id' => $transactionID,
                'type' => 'unscheduled',
                'unscheduled_type' => 'incremental',
            ]);
        try {
            $response = $request->call();
        } catch (API\PHP\Altapay\Exceptions\ClientException $e) {
            $message = $e->getResponse()->getBody();
        } catch (API\PHP\Altapay\Exceptions\ResponseHeaderException $e) {
            $message = $e->getHeader()->ErrorMessage;
        } catch (API\PHP\Altapay\Exceptions\ResponseMessageException $e) {
            $message = $e->getMessage();
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        PrestaShopLogger::addLog('Callback OK issue, Message ' . $message,
            3,
            '1005',
            $this->module->name,
            $this->module->id,
            true
        );
    }

    /**
     * @param $cart
     * @param $order
     * @param $response
     * @param $shopOrderId
     *
     * @return void
     */
    protected function updateOrder($cart, $order, $response, $shopOrderId)
    {
        if ($response && is_array($response->Transactions)) {
            $transactionStatus = $response->Transactions[0]->TransactionStatus;
        }
        $auth_statuses = ['preauth', 'invoice_initialized', 'recurring_confirmed'];
        $captured_statuses = ['bank_payment_finalized', 'captured'];
        if (in_array($transactionStatus, $auth_statuses, true) or in_array($transactionStatus, $captured_statuses, true)) {
            /*
             * preauth occurs for wallet transactions where payment type is 'payment'.
             * Funds are still waiting to be captured.
             * For this scenario we change the order status to 'payment accepted'.
             * bank_payment_finalized is for ePayments.
             */
            $order_state = (int) Configuration::get('authorized_payments_status');
            if (empty($order_state)) {
                $order_state = (int) Configuration::get('PS_OS_PAYMENT');
            }
            if (in_array($transactionStatus, $captured_statuses, true)) {
                $order_state = (int) Configuration::get('PS_OS_PAYMENT');
            }
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
            $customer = new Customer($cart->id_customer);
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key);
        } elseif ($transactionStatus === 'epayment_declined') {
            // Update payment status to 'declined'
            $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
            SET `paymentStatus` = \'declined\' WHERE `id_order` = ' . (int) $order->id;
            Db::getInstance()->Execute($sql);
            exit('Order status updated to Error');
        } else {
            // Unexpected scenario
            $mNa = $this->module->name;
            PrestaShopLogger::addLog('Unexpected scenario: Callback notification was received for Transaction '
                . $shopOrderId . ' with payment status ' . $transactionStatus, 3, '1005', $mNa,
                $this->module->id, true);
            exit('Unrecognized status received ' . $transactionStatus);
        }
    }
}
