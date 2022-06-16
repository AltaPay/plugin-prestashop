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
        try {
            $postData = Tools::getAllValues();
            $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
            $response = $callback->call();
            $shopOrderId = $response->shopOrderId;
            $paymentId = $response->transactionId;
            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                exit('Could not load cart - exiting');
            }
            // Load the customer
            $customer = new Customer((int) $cart->id_customer);
            $transactionStatus = $response->paymentStatus;
            $resultStatus = strtolower($response->Result);
            $order = getOrderFromUniqueId($shopOrderId);

            //Set order status, if available from the payment gateway
            $cardHolderMessageMustBeShown = false;
            if (isset($postData['cardholder_message_must_be_shown'])) {
                $cardHolderMessageMustBeShown = $postData['cardholder_message_must_be_shown'];
            }
            if (isset($postData['error_message']) && $cardHolderMessageMustBeShown == 'true') {
                $msg = $postData['error_message'];
            } else {
                $msg = 'Error with the Payment.';
            }

            if ($resultStatus == 'cancelled') {
                $msg = 'Payment canceled';
            }

            switch ($resultStatus) {
                case 'succeeded':
                case 'success':
                    $this->handleNotificationAction($cart, $order, $response, $customer, $transactionStatus, $shopOrderId);
                    break;
                case 'cancelled':
                    $this->handleCancelledStatusAction($shopOrderId, $transactionStatus);
                    break;
                case 'error':
                case 'failed':
                    $this->handleFailedStatusAction($msg, $paymentId, $order, $transactionStatus);
                    break;
                default:
                    $this->handleUnExpectedStatusAction($shopOrderId, $transactionStatus);
            }
        } catch (PrestaShopException $e) {
            $e->displayMessage();
        }
    }

    public function handleCancelledStatusAction($shopOrderId, $transactionStatus)
    {
        // Payment canceled
        $mNa = $this->module->name;
        PrestaShopLogger::addLog('Callback notification was received for Transaction '
                                    . $shopOrderId . ' with payment status ' . $transactionStatus, 3, '1005', $mNa,
            $this->module->id, true);

        exit('Payment canceled');
    }

    public function handleUnExpectedStatusAction($shopOrderId, $transactionStatus)
    {
        // Unexpected scenario
        $mNa = $this->module->name;
        PrestaShopLogger::addLog('Unexpected scenario: Callback notification was received for Transaction '
                                    . $shopOrderId . ' with payment status ' . $transactionStatus, 3, '1005', $mNa,
            $this->module->id, true);

        exit('Unrecognized status received ' . $transactionStatus);
    }

    public function handleFailedStatusAction($msg, $paymentId, $order, $transactionStatus)
    {
        saveLastErrorMessage($paymentId, $msg);
        $order->setCurrentState((int) Configuration::get('PS_OS_CANCELED'));
        // Update payment status to 'declined'
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
        SET `paymentStatus` = ' . $transactionStatus . ' WHERE `id_order` = ' . $order->id;
        Db::getInstance()->Execute($sql);

        exit('Order status updated to canceled');
    }

    public function handleNotificationAction($cart, $order, $response, $customer, $transactionStatus, $shopOrderId)
    {
        $log_file = _PS_MODULE_DIR_.'altapay/logs/logOrderId-'.uniqid().'.txt';  
        // NO ORDER FOUND, CREATE?
        if (!Validate::isLoadedObject($order)) {
            // Payment successful - create order
            if ($response && is_array($response->Transactions)) {
                $order_status = (int) Configuration::get('PS_OS_PAYMENT');
                $currency_paid = Currency::getIdByIsoCode($response->Currency);
                $amount_paid = $response->amount;
                $paymentType = $response->Transactions[0]->AuthType;
                file_put_contents($log_file, print_r("Create Order - ".$shopOrderId, true));
                /* If payment type is 'payment' funds have not yet been captured,
                * so AltaPay returns 0 as the captured amount.Therefore we assume full payment has been authorized.
                */
                if ($paymentType === 'payment') {
                    $amount_paid = $cart->getOrderTotal(true, Cart::BOTH);
                    $currency_paid = new Currency($cart->id_currency);
                }
                // Determine payment method for display
                $paymentMethod = determinePaymentMethodForDisplay($response);

                // Create an order with 'payment accepted' status
                $cSk = $customer->secure_key;
                $cpId = (int) $currency_paid->id;
                $cId = $cart->id;
                $oSt = $order_status;
                $pMeth = $paymentMethod;
                $this->module->validateOrder($cId, $oSt, $amount_paid, $pMeth, null, null, $cpId, false, $cSk);
                // Log order
                $current_order = new Order((int) $this->module->currentOrder);

                createAltapayOrder($response, $current_order, $transactionStatus);
                exit('Order created');
            } else {
                exit('Only handling Success state');
            }
        }
        elseif ($order->getCurrentState() != Configuration::get('ALTAPAY_OS_PENDING')) { //pending     
            file_put_contents($log_file, print_r("Ignore Order - ".$order->id, true));
            exit('Order found but is not currently pending - ignoring');
        }
        else {   
            file_put_contents($log_file, print_r("Already created Order - ".$order->id, true));
            $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
            // Update payment status to 'succeeded'
            $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
        SET `paymentStatus` = ' . $transactionStatus . ' WHERE `id_order` = ' . $order->id;
            Db::getInstance()->Execute($sql);
            $payment = $order->getOrderPaymentCollection();
            if (isset($payment[0])) {
                $payment[0]->transaction_id = pSQL($shopOrderId);
                $payment[0]->save();
            }

            exit('Order status updated to Accepted');
        }
    }
}
