<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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
        $saveCard = null;
        $payment_method = Tools::getValue('method', false);

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

        if (isset($_COOKIE['selectedCreditCard'])) {
            $savedCreditCard = $_COOKIE['selectedCreditCard'];
            unset($_COOKIE['selectedCreditCard']);
            setcookie('selectedCreditCard', null, -1, '/');
        } else {
            unset($_COOKIE['selectedCreditCard']);
            setcookie('selectedCreditCard', null, -1, '/');
        }

        if (isset($_COOKIE['savecard'])) {
            $saveCard = $_COOKIE['savecard'];
            unset($_COOKIE['savecard']);
            setcookie('savecard', null, -1, '/');
        } else {
            unset($_COOKIE['savecard']);
            setcookie('savecard', null, -1, '/');
        }

        $result = $this->module->createTransaction($saveCard, $savedCreditCard, $payment_method);
        // Load the customer
        $customer = new Customer((int) $cart->id_customer);
        $currency_paid = new Currency($cart->id_currency);

        if ($result['success']) {
            $payment_form_url = $result['payment_form_url'];
            $terminal = $this->getTerminal($payment_method, $this->context->currency->iso_code);
            // Create Order with pending status
            $this->module->validateOrder(
                $cart->id,
                $result['status'],
                $result['amount'],
                $result['terminal'],
                null,
                null,
                (int) $currency_paid->id,
                false,
                $customer->secure_key
            );

            // Insert into transaction log
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_transaction` 
				(id_cart, payment_form_url, unique_id, amount, terminal_name, date_add) VALUES ' .
                   "('" . $cart->id . "', '" . $result['payment_form_url'] . "', '" . $result['uniqueid'] . "', '"
                   . $result['amount'] . "', '" . $terminal->remote_name . "' , '" . time() . "')" .
                   ' ON DUPLICATE KEY UPDATE `amount` = ' . $result['amount'];

            Db::getInstance()->Execute($sql);

            if ($payment_form_url === "reservation") {
                $currentOrder = new Order((int) $this->module->currentOrder);
                createAltapayOrder($result['response'], $currentOrder, "succeeded");
                Tools::redirect('index.php?controller=order-detail&id_order=' . $this->module->currentOrder);
            } else {
                Tools::redirect($payment_form_url);
            }
        } else {
            // Redirect user back to checkout with a generic error
            Tools::redirect($payment_form_url);
        }
    }

    /**
     * Get the remote name of the terminal associated with
     * this payment method. Will check if currency matches the remote terminal.
     *
     * @param bool $terminal_id
     * @param bool $currency
     *
     * @return Altapay_Models_Terminal|null
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getTerminal($terminal_id = false, $currency = false)
    {
        if ($terminal_id === false || $currency === false) {
            return null;
        }

        $terminal = new Altapay_Models_Terminal($terminal_id);
        $terminalId = $terminal->id_terminal;
        $terminalCurr = $terminal->currency;
        if ($terminalId === null || Tools::strtolower($terminalCurr) !== Tools::strtolower($currency)) {
            return null;
        }

        return $terminal;
    }
}
