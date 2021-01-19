<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
require_once _PS_MODULE_DIR_ . 'altapay/lib/altapay/altapay-php-sdk/lib/AltapayMerchantAPI.class.php';

class AltapayPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @var bool
     */
    public $display_column_left;

    /**
     * Method to follow when payment is being initiated with payment method
     *
     * @return void
     */
    public function initContent()
    {
        $this->display_column_left = false;
        parent::initContent();
        $savedCreditCard = null;

        $cart = $this->context->cart;
        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        /* Redirect user back to the checkout payment step,
        * assume a failure occurred creating the URL until a payment URL is received
        */
        $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
        $payment_form_url = $this->context->link->getPageLink($controller, true, null,
                'step=3&altapay_unavailable=1') . '#altapay_unavailable';

        $payment_method = Tools::getValue('method', false);

        if (isset($_COOKIE['selectedCreditCard'])) {
            $savedCreditCard = $_COOKIE['selectedCreditCard'];
            unset($_COOKIE['selectedCreditCard']);
            setcookie('selectedCreditCard', null, -1, '/');
        } else {
            unset($_COOKIE['selectedCreditCard']);
            setcookie('selectedCreditCard', null, -1, '/');
        }

        $result = $this->module->createTransaction($savedCreditCard, $payment_method);

        if ($result['success']) {
            $payment_form_url = $result['payment_form_url'];

            // Insert into transaction log
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_transaction` 
				(id_cart, payment_form_url, unique_id, amount, date_add) VALUES ' .
                   "('" . $cart->id . "', '" . $payment_form_url . "', '" . $result['uniqueid'] . "', '"
                   . $result['amount'] . "', '" . time() . "')" .
                   ' ON DUPLICATE KEY UPDATE `amount` = ' . $result['amount'];
            Db::getInstance()->Execute($sql);

            // Redirect user to payment form url
            Tools::redirect($payment_form_url);
        } else {
            // Redirect user back to checkout with a generic error
            Tools::redirect($payment_form_url);
        }
    }
}
