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
        $transactionId = Tools::getValue('transaction_id');

        if(!empty($transactionId)){
            $condition = "payment_id = '" . pSQL($transactionId) . "'";
            $query = 'SELECT id_order, unique_id FROM `' . _DB_PREFIX_ . 'altapay_order` WHERE ' . $condition;
            $data = Db::getInstance()->executeS($query);

            if(!empty($data)){
                $orderId = $data[0]['id_order'];
                $shopOrderId = $data[0]['unique_id'];
                $cart = getCartFromUniqueId($shopOrderId);
                $context = Context::getContext();

                // Load the order object
                $order = new Order($orderId);
                $customer = new Customer($cart->id_customer);

                // Check if the order exists and is associated with the current customer
                if (Validate::isLoadedObject($order) && $order->id_customer == $context->customer->id) {
                    // Get the thank you page URL for the order
                    $thank_you_url = $context->link->getPageLink('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $orderId . '&key=' . $customer->secure_key);
            

                    $this->ajaxDie(Tools::jsonEncode(['success' => true, 'url' => $thank_you_url]));
                }
            }
        }
        
        $this->ajaxDie(Tools::jsonEncode(['success' => false]));
    }
}