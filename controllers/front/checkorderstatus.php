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

        if (!empty($shopOrderId)) {
            $isChildOrder = isChildOrder($shopOrderId);
            $tableName = $isChildOrder ? 'altapay_child_order' : 'altapay_order';
            $paymentRecord = Db::getInstance()->getRow(
                'SELECT id_order, paymentStatus FROM `' . _DB_PREFIX_ . $tableName
                . '` WHERE unique_id = \'' . pSQL($shopOrderId) . '\''
            );

            if (!empty($paymentRecord)) {
                $order = new Order((int) $paymentRecord['id_order']);
                $paymentSucceeded = strtolower((string) $paymentRecord['paymentStatus']) === 'succeeded';

                if (Validate::isLoadedObject($order) && $paymentSucceeded && $this->isCompletedOrder($order)) {
                    $context = Context::getContext();
                    $currentCustomerId = isset($context->customer) ? (int) $context->customer->id : 0;

                    if ($currentCustomerId > 0 && (int) $order->id_customer === $currentCustomerId) {
                        $customer = new Customer((int) $order->id_customer);
                        $thankYouUrl = $context->link->getPageLink(
                            'order-confirmation',
                            true,
                            null,
                            [
                                'id_cart' => (int) $order->id_cart,
                                'id_module' => (int) $this->module->id,
                                'id_order' => (int) $order->id,
                                'key' => $customer->secure_key,
                            ]
                        );

                        $this->ajaxDie(json_encode(['success' => true, 'url' => $thankYouUrl]));
                    }

                    // The payment is complete, but the current browser does not own the order session.
                    // Do not expose the confirmation URL, secure key, or any order/customer information.
                    $this->ajaxDie(json_encode(['success' => true, 'completed' => true]));
                }
            }

            $data = Db::getInstance()->getRow(
                'SELECT transaction_status FROM `' . _DB_PREFIX_ . 'altapay_transaction` WHERE unique_id = "'
                . pSQL($shopOrderId) . '"'
            );

            if (!empty($data)) {
                $transactionStatus = isset($data['transaction_status']) ? $data['transaction_status'] : '';
                $errorStatus = ['cancelled', 'declined', 'error', 'failed', 'incomplete', 'open'];

                if (in_array($transactionStatus, $errorStatus, true)) {
                    $this->redirectBackToCheckout('altapay_cancel=1&isPaymentStep=true&step=3#altapay_cancel');
                }
            }
        }

        if ($timeout) {
            $this->redirectBackToCheckout('altapay_cancel=1&isPaymentStep=true&step=3#altapay_cancel');
        }

        $this->ajaxDie(json_encode(['success' => false]));
    }

    /**
     * A successful payment record is complete only after the PrestaShop order has
     * left states that represent an unfinished or unsuccessful payment.
     *
     * @param Order $order
     *
     * @return bool
     */
    private function isCompletedOrder($order)
    {
        $incompleteOrderStates = [
            (int) Configuration::get('ALTAPAY_OS_PENDING'),
            (int) Configuration::get('PS_OS_CANCELED'),
            (int) Configuration::get('PS_OS_ERROR'),
        ];
        $incompleteOrderStates = array_filter($incompleteOrderStates);

        return !in_array((int) $order->getCurrentState(), $incompleteOrderStates, true);
    }

    public function redirectBackToCheckout($query)
    {
        $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
        $pLink = $this->context->link->getPageLink($controller);
        $location = $pLink . (strpos($controller, '?') !== false ? '&' : '?') . $query;

        $this->ajaxDie(json_encode(['success' => false, 'url' => $location]));
    }
}
