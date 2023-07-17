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
        $checksum = $postData['checksum'];
        $terminal_name = getTransactionTerminalByUniqueId($postData['shop_orderid']);
        $secret = Altapay_Models_Terminal::getTerminalSecretByRemoteName($terminal_name);

        if (!empty($checksum) and !empty($secret) and calculateChecksum($postData, $secret) !== $checksum) {
            exit();
        }
        // This lock prevents orders to be created twice.
        $fp = fopen(_PS_MODULE_DIR_ . '/altapay/controllers/front/lock.txt', 'r');
        flock($fp, LOCK_EX);

        $message = '';
        $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
        $customerID = $this->context->customer->id;
        $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
        try {
            $response = $callback->call();
            $shopOrderId = $response->shopOrderId;
            $currencyPaid = Currency::getIdByIsoCode($response->currency);
            $paymentType = $response->type;
            $transaction = getTransaction($response);
            $transactionID = $transaction->TransactionId;
            $ccToken = $response->creditCardToken;
            $maskedPan = $response->maskedCreditCard;
            $agreementType = 'unscheduled';
            $fraudPayment = handleFraudPayment($response, $transaction);
            
            if (isset($shopOrderId) && !empty($shopOrderId)) {
                $condition = "unique_id = '" . pSQL($shopOrderId) . "' AND paymentStatus = 'succeeded'";
                $query = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_order` WHERE ' . $condition;
                $result = Db::getInstance()->executeS($query);
                // Check if the order already saved with the success status
                if (!empty($result)) {
                    exit('Order already Processed!!');
                }
            }

            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                $this->unlock($fp);
                exit('Could not load cart - exiting');
            }
            // Redirect to payment selection page
            if ($fraudPayment['payment_status']) {
                $this->saveLogs($transaction->FraudExplanation);
                $this->redirectUserToCheckoutPaymentStep($fp);
            } else {
                // Check if an order exist
                $order = getOrderFromUniqueId($shopOrderId);
                if (Validate::isLoadedObject($order)) {
                    $this->updateOrder($cart, $order, $response, $fp);
                } else {
                    $this->createOrder($response, $currencyPaid, $cart, $orderStatus);
                }
            }
            // Load order
            $order = new Order((int) $this->module->currentOrder);

            if (Validate::isLoadedObject($order)) {
                $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
                if (!empty($transaction->ReconciliationIdentifiers)) {
                    $reconciliation_identifier = $transaction->ReconciliationIdentifiers[0]->Id;
                    $reconciliation_type = $transaction->ReconciliationIdentifiers[0]->Type;
                    saveOrderReconciliationIdentifier($order->id, $reconciliation_identifier, $reconciliation_type);
                }
                if ($paymentType === 'paymentAndCapture' && $response->requireCapture === true) {
                    $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
                    $reconciliation_identifier = sha1($transactionID . time());
                    $api = new API\PHP\Altapay\Api\Payments\CaptureReservation(getAuth());
                    $api->setAmount($amountPaid);
                    $api->setTransaction($transactionID);
                    $api->setReconciliationIdentifier($reconciliation_identifier);
                    $api->call();
                    saveOrderReconciliationIdentifier($order->id, $reconciliation_identifier);
                }
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
                $this->unlock($fp);
                $customer = new Customer($cart->id_customer);
                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key);
            } else {
                $this->saveLogs('Something went wrong');
                $this->redirectUserToCheckoutPaymentStep($fp);
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
        $this->redirectUserToCheckoutPaymentStep($fp);
        $this->unlock($fp);
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

    /**
     * @param $fp
     *
     * @return void
     */
    public function redirectUserToCheckoutPaymentStep($fp)
    {
        /* Redirect user back to the checkout payment step,
        * assume a failure occurred creating the URL until a payment url is received
        */
        $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
        $as = $this->context->link;
        $con = $controller;
        $redirect = $as->getPageLink($con, true, null, 'step=3&altapay_unavailable=1') . '#altapay_unavailable';
        $this->unlock($fp);
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
     * @param $fp
     *
     * @return void
     */
    protected function updateOrder($cart, $order, $response, $fp)
    {
        $shopOrderId = $response->shopOrderId;
        $transactionStatus = $response->paymentStatus;
        $statuses = ['preauth', 'bank_payment_finalized', 'captured', 'recurring_confirmed'];
        if (in_array($transactionStatus, $statuses, true)) {
            /*
             * preauth occurs for wallet transactions where payment type is 'payment'.
             * Funds are still waiting to be captured.
             * For this scenario we change the order status to 'payment accepted'.
             * bank_payment_finalized is for ePayments.
             */
            $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
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
}
