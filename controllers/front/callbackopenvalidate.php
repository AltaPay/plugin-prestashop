<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbackopenvalidateModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        // We have transaction ID coming from another controller
        $transactionId = Tools::getValue('transaction_id');
        // If response is 1 that means we have the record we need to show message and remove the loader
        $this->context->smarty->assign([
            'transaction_id' => $transactionId
        ]);
        $this->setTemplate('module:altapay/views/templates/front/paymentopen_status.tpl');

    }

    public function setMedia()
    {
        parent::setMedia();
    }
}