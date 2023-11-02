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
        $orderId = Tools::getValue('order_id');
        // If response is 1 that means we have the record we need to show message and remove the loader
        $this->context->smarty->assign([
            'order_id' => $orderId,
        ]);

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->setTemplate('module:altapay/views/templates/front/paymentopen_status17.tpl');
        } else {
            $this->setTemplate('paymentopen_status.tpl');
        }
    }

    public function setMedia()
    {
        parent::setMedia();
    }
}
