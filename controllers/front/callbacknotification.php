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
            $fp = fopen(_PS_MODULE_DIR_ . '/altapay/controllers/front/lock.txt', 'r');
            flock($fp, LOCK_EX);
            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                $this->unlock($fp);
                exit('Could not load cart - exiting');
            }
            // Load the customer
            $customer = new Customer((int) $cart->id_customer);
            $transactionStatus = $response->paymentStatus;
            // Check if an order exist
            $order = getOrderFromUniqueId($shopOrderId);
            $errorStatus = ['cancelled', 'epayment_cancelled'];
            if (!in_array($transactionStatus, $errorStatus, true)) {
                // NO ORDER FOUND, CREATE?
                if (!Validate::isLoadedObject($order)) {
                    // Payment successful - create order
                    if ($response && is_array($response->Transactions)) {
                        $order_status = (int) Configuration::get('PS_OS_PAYMENT');
                        $currency_paid = Currency::getIdByIsoCode($response->Currency);
                        $amount_paid = $response->amount;
                        $paymentType = $response->Transactions[0]->AuthType;
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
                        $this->unlock($fp);
                        exit('Order created');
                    } else {
                        $this->unlock($fp);
                        exit('Only handling Success state');
                    }
                } // Order found, but not pending
                elseif ($order->getCurrentState() != Configuration::get('ALTAPAY_OS_PENDING')) { //pending
                    $this->unlock($fp);
                    exit('Order found but is not currently pending - ignoring');
                } // Pending order found, update
                elseif (Validate::isLoadedObject($order)) {
                    if ($transactionStatus === 'preauth' || $transactionStatus === 'bank_payment_finalized' || $transactionStatus === 'captured') {
                        /*
                         * preauth occurs for wallet transactions where payment type is 'payment'.
                         * Funds are still waiting to be captured.
                         * For this scenario we change the order status to 'payment accepted'.
                         * bank_payment_finalized is for ePayments.
                         */
                        $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
                        // Update payment status to 'succeeded'
                        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
                    SET `paymentStatus` = \'succeeded\' WHERE `id_order` = ' . $order->id;
                        Db::getInstance()->Execute($sql);
                        $payment = $order->getOrderPaymentCollection();
                        if (isset($payment[0])) {
                            $payment[0]->transaction_id = pSQL($shopOrderId);
                            $payment[0]->save();
                        }
                        $this->unlock($fp);
                        exit('Order status updated to Accepted');
                    } elseif ($transactionStatus === 'epayment_declined') {
                        // Update payment status to 'declined'
                        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
                        SET `paymentStatus` = \'declined\' WHERE `id_order` = ' . $order->id;
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
                // Unexpected scenario
                $mNa = $this->module->name;
                PrestaShopLogger::addLog('Callback notification was received for Transaction ' . $shopOrderId . ' with payment status ' . $transactionStatus,
                3,
                '1005',
                $mNa,
                $this->module->id,
                true
            );
                $this->unlock($fp);
                exit('Unrecognized status received ' . $transactionStatus);
            }
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
