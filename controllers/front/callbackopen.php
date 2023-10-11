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
     * If the payment state is "open", the module will convert the shopping cart to an
     * order using a the defined "Awaiting Payment Processing" order status. The module
     * will display a message to the customer stating that an order has been created but
     * is awaiting payment processing.
     * ALTAPAY will send a notification to the "open" callback URL when the payment moves
     * to "success" or "failure". The module will then update the order status to either
     * "Payment Accepted" or "Payment Error".
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
        $transactionId = $postData['transaction_id'];

        $redirectUrl = $this->context->link->getModuleLink('altapay', 'callbackopenvalidate',
            array('transaction_id' => $transactionId));

        Tools::redirect($redirectUrl);

    }
}
