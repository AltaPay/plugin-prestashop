<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbackopenModuleFrontController extends ModuleFrontController
{
    /**
     * If the payment state is "open", the order will not be created and plugin will
     * wait for the order created through notification callback.
     * Plugin will shows the loader and redirects to success/failure page.
     */

    /**
     * Method for open callback
     *
     * @return void
     *
     * @throws AltapayXmlException
     */
    public function postProcess()
    {
        $postData = Tools::getAllValues();
        $checksum = !empty($postData['checksum']) ? $postData['checksum'] : '';
        $terminal_name = getTransactionTerminalByUniqueId($postData['shop_orderid']);
        $secret = Altapay_Models_Terminal::getTerminalSecretByRemoteName($terminal_name);

        if (!empty($checksum) and !empty($secret) and calculateChecksum($postData, $secret) !== $checksum) {
            exit();
        }

        $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
        $response = $callback->call();
        $shopOrderId = $response->shopOrderId;
        // Load the cart
        $cart = getCartFromUniqueId($shopOrderId);
        if (!Validate::isLoadedObject($cart)) {
            exit('Could not load cart - exiting');
        }

        $orderId = isset($postData['shop_orderid']) ? $postData['shop_orderid'] : '';

        $redirectUrl = $this->context->link->getModuleLink('altapay', 'callbackopenvalidate', ['order_id' => $orderId]);
        
        Tools::redirect($redirectUrl);
    }
}
