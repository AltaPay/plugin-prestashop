<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCheckorderstatusModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $shopOrderId = Tools::getValue('order_id');
        $timeout = filter_var(Tools::getValue('timeout'), FILTER_VALIDATE_BOOLEAN);

        if ($timeout) {
            $this->redirectBackToCheckout('altapay_cancel=1&isPaymentStep=true&step=3#altapay_cancel');
        }

        if (!empty($shopOrderId)) {
            $data = Db::getInstance()->getRow('SELECT transaction_status FROM `' . _DB_PREFIX_ . 'altapay_transaction` WHERE unique_id = "' . pSQL($shopOrderId) . '"');

            if (!empty($data)) {
                $transactionStatus = isset($data['transaction_status']) ? $data['transaction_status'] : '';
                $errorStatus = ['cancelled', 'declined', 'error', 'failed', 'incomplete', 'open'];

                if (!in_array($transactionStatus, $errorStatus, true)) {
                    // Load the order object
                    $cart = getCartFromUniqueId($shopOrderId);
                    $orderId = Order::getOrderByCartId((int) ($cart->id));
                    $order = new Order($orderId);
                    $customer = new Customer($cart->id_customer);

                    // Check if the order exists and is associated with the current customer
                    $context = Context::getContext();
                    if (Validate::isLoadedObject($order) && $order->id_customer == $context->customer->id) {
                        // Get the thank you page URL for the order
                        $thank_you_url = $context->link->getPageLink(
                            'order-confirmation',
                            true,
                            null,
                            [
                                'id_cart' => (int) $cart->id,
                                'id_module' => (int) $this->module->id,
                                'id_order' => (int) $orderId,
                                'key' => $customer->secure_key,
                            ]
                        );

                        $this->ajaxDie(json_encode(['success' => true, 'url' => $thank_you_url]));
                    }
                } else {
                    $this->redirectBackToCheckout('altapay_cancel=1&isPaymentStep=true&step=3#altapay_cancel');
                }
            }
        }

        $this->ajaxDie(json_encode(['success' => false]));
    }

    public function redirectBackToCheckout($query)
    {
        $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
        $pLink = $this->context->link->getPageLink($controller);
        $location = $pLink . (strpos($controller, '?') !== false ? '&' : '?') . $query;

        $this->ajaxDie(json_encode(['success' => false, 'url' => $location]));
    }
}
