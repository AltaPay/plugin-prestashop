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

class AltapayCallbackopenModuleFrontController extends ModuleFrontController
{

    /**
     * If the payment state is "open", the module will convert the shopping cart to an
     * order using a the defined "Awaiting Payment Processing" order status. The module
     * will display a message to the customer stating that an order has been created but
     * is awaiting payment processing.
     * ALTAPAY will send a notification to the "open" callback URL when the payment moves
     * to "success" or "failure". The module will then update the order status to either
     * "Payment Accepted" or "Payment Error".
     */
    public function postProcess()
    {
        $xml = Tools::getValue('xml');
        $callbackHandler = new AltapayCallbackHandler();
        $response = $callbackHandler->parseXmlResponse($xml);

        $shopOrderId = $response->getPrimaryPayment()->getShopOrderId();

        // load the cart
        $cart = getCartFromUniqueId($shopOrderId);
        if (!Validate::isLoadedObject($cart)) {
            die('Could not load cart - exiting');
        }

        // load the customer
        $customer = new Customer((int)$cart->id_customer);

        // amount paid is returned as 0, so we use cart amount instead
        $amount_paid = $cart->getOrderTotal(true, Cart::BOTH);
        $currency_paid = new Currency($cart->id_currency);
                
        // determine payment method for display
        $paymentMethod = determinePaymentMethodForDisplay($response);

        // create order
        $confOs = Configuration::get('ALTAPAY_OS_PENDING');
        $curPaid = (int)$currency_paid->id;
        $curSk = $customer->secure_key;
        $cId = $cart->id;
        $this->module->validateOrder($cId, $confOs, $amount_paid, $paymentMethod, null, null, $curPaid, false, $curSk);

        // log order
        $current_order = new Order((int)$this->module->currentOrder);
        createAltapayOrder($response, $current_order, 'open');

        $curOr = $this->module->currentOrder;
        $mId = $this->module->id;
        $confOr = 'index.php?controller=order-confirmation&id_cart=';
        Tools::redirect($confOr.$cId.'&id_module='.$mId.'&id_order='.$curOr.'&key='.$curSk);
    }
}
