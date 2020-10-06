<?php
/**
 * Altapay module for Prestashop
 *
 * Copyright © 2020 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once(_PS_MODULE_DIR_.'/altapay/lib/altapay/altapay-php-sdk/lib/AltapayCallbackHandler.class.php');
require_once(_PS_MODULE_DIR_.'/altapay/helpers.php');
require_once(_PS_MODULE_DIR_.'/altapay/lib/altapay/altapay-php-sdk/lib/AltapayMerchantAPI.class.php');

class AltapayCallbackokModuleFrontController extends ModuleFrontController
{
    /**
     * Method to follow when callback ok is being triggered
     * @throws AltapayMerchantAPIException
     */
    public function postProcess()
    {
        try {
            $xml = Tools::getValue('xml');
            $callbackHandler = new AltapayCallbackHandler();
            $response = $callbackHandler->parseXmlResponse($xml);


            $shopOrderId = $response->getPrimaryPayment()->getShopOrderId();

            
            //this lock prevents orders to be created twice.
            $fp = fopen(_PS_MODULE_DIR_.'/altapay/controllers/front/lock.txt', 'r');
            flock($fp, LOCK_EX);

            // load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                $this->unlock($fp);
                die('Could not load cart - exiting');
            }

            // load the customer
            $customer = new Customer((int)$cart->id_customer);

            // check if an order already exists
            $order = getOrderFromUniqueId($shopOrderId);
            if (Validate::isLoadedObject($order)) {
                // an order has already been created from this cart - redirect
                Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='
                .$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
            }

            // handle success
            if ($response->wasSuccessful()) {
                $orderStatus=(int)Configuration::get('PS_OS_PAYMENT');
                $paymentType=$response->getPrimaryPayment()->getAuthType();
                $amountPaid=$response->getPrimaryPayment()->getCapturedAmount();
                $captureStatus = Tools::getValue('require_capture');
                $currencyPaid=Currency::getIdByIsoCode($response->getPrimaryPayment()->getCurrency());
                $transactionID = Tools::getValue('transaction_id');
                /*if payment type is 'payment' funds have not yet been captured,
                so Altapay returns zero as the captured amount. Therefore we assume full payment has been authorized.*/
                if ($paymentType=='payment') {
                    $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
                    $currencyPaid = new Currency($cart->id_currency);
                } else if ($paymentType=='paymentAndCapture' && $captureStatus == 'true') {
                    $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
                    $currencyPaid = new Currency($cart->id_currency);
                    $api = apiLogin();
                    $api->captureReservation($transactionID, $amountPaid,array(), null);
                }

                //determine payment method for display
                $paymentMethod = determinePaymentMethodForDisplay($response);
                //create an order with 'payment accepted' status
                $currencyPaidID = (int)$currencyPaid->id;
                $customerSecureKey = $customer->secure_key;
                $cartID = $cart->id;
                $paymentMethod = $paymentMethod;
                $this->module->validateOrder($cartID, $orderStatus, $amountPaid, $paymentMethod, null, null, $currencyPaidID, false, $customerSecureKey);

                // log order
                $currentOrder = new Order((int)$this->module->currentOrder);
                createAltapayOrder($response, $currentOrder);
                $this->unlock($fp);
                if (_PS_VERSION_ >= '1.7.0.0') {
                    Tools::redirect('index.php?fc=module&module=altapay&controller=orderconfirmation&id_order=' . $this->module->currentOrder);
                } else {
                    Tools::redirect('index.php?controller=order-detail&id_order=' . $this->module->currentOrder);
                }
            } else {
                //unexpected scenario
                $moduleName = $this->module->name;
                $moduleID = $this->module->id;
                Logger::addLog('Callback ok received but payment was unsuccessful', 3, '1004', $moduleName, $moduleID, true);
                echo $this->module->l('This payment method is not available 1004.', 'callbackok');

                /*redirect user back to checkout payment step,
                assume a failure occured creating the URL until a payment url is received*/
                $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
                $as = $this->context->link;
                $con = $controller;
                $redirect = $as->getPageLink($con, true, null, "step=3&altapay_unavailable=1").'#altapay_unavailable';
                $this->unlock($fp);
                Tools::redirect($redirect);
            }
        } finally {
            $this->unlock($fp);
        }
    }

    public function unlock($fileOpen)
    {
        flock($fileOpen, LOCK_UN);
        fclose($fileOpen);
    }
}
