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
        $savedCreditCard = $_COOKIE['selectedCreditCard'] ?? null;
        $saveCard = $_COOKIE['savecard'] ?? null;
        $payment_method = Tools::getValue('method', false);
        $providerData = Tools::getValue('providerData');
        $is_apple_pay = (bool) Tools::getValue('is_apple_pay', false);
        $terminal = getTerminal($payment_method, $this->context->currency->iso_code);

        $cart = $this->context->cart;
        if (!$this->module->checkCurrency($cart)) {
            if ($is_apple_pay === true) {
                echo json_encode(['status' => 'error']);
                exit();
            }

            Tools::redirect('index.php?controller=order');
        }

        /* Redirect user back to the checkout payment step,
        * assume a failure occurred creating the URL until a payment URL is received
        */
        $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
        $payment_form_url = $this->context->link->getPageLink($controller, true, null,
                'step=3&altapay_unavailable=1') . '#altapay_unavailable';

        unset($_COOKIE['savecard']);
        unset($_COOKIE['selectedCreditCard']);
        setcookie('savecard', null, -1, '/');
        setcookie('selectedCreditCard', null, -1, '/');

        $result = $this->module->createTransaction($saveCard, $savedCreditCard, $payment_method, $providerData, $is_apple_pay);
        // Load the customer
        $customer = new Customer((int) $cart->id_customer);
        $currency_paid = new Currency($cart->id_currency);

        if ($result['success'] && !empty($result['payment_form_url'])) {
            $payment_form_url = $result['payment_form_url'];
            // Insert into transaction log
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_transaction` 
				(id_cart, payment_form_url, unique_id, amount, terminal_name, date_add, token) VALUES ' .
                   "('" . (int) $cart->id . "', '" . pSQL($payment_form_url) . "', '" . pSQL($result['uniqueid']) . "', '"
                   . pSQL($result['amount']) . "', '" . pSQL($terminal->remote_name) . "' , '" . pSQL(time()) . "', '')" .
                   ' ON DUPLICATE KEY UPDATE `amount` = ' . pSQL($result['amount']);

            Db::getInstance()->Execute($sql);

            if ($payment_form_url === 'reservation' || $payment_form_url === 'cardwallet') {
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

                $currentOrder = new Order((int) $this->module->currentOrder);
                createAltapayOrder($result['response'], $currentOrder, 'succeeded');
                $customer = new Customer($cart->id_customer);
                if ($payment_form_url === 'reservation') {
                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $currentOrder->id . '&key=' . $customer->secure_key);
                } else {
                    saveReconciliationDetails($result['response'], $currentOrder);
                    $response = [
                        'status' => $result['response']->Result,
                        'redirectUrl' => $this->context->link->getPageLink(
                            'order-confirmation',
                            true,
                            null,
                            [
                                'id_cart' => (int) $cart->id,
                                'id_module' => (int) $this->module->id,
                                'id_order' => (int) $currentOrder->id,
                                'key' => $customer->secure_key,
                            ]
                        ),
                    ];
                    echo json_encode($response);
                    exit();
                }
            } elseif ($result['apple_pay_terminal'] === true) {
                echo json_encode(['status' => 'error']);
                exit();
            } else {
                Tools::redirect($payment_form_url);
            }
        } elseif ($result['apple_pay_terminal'] === true || $is_apple_pay === true) {
            echo json_encode(['status' => 'error']);
            exit();
        } else {
            // Redirect user back to checkout with a generic error
            Tools::redirect($payment_form_url);
        }
    }
}
