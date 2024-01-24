<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbackredirectModuleFrontController extends ModuleFrontController
{
    /**
     * Method to follow when callback redirect is being triggered
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        // PrestaShop 1.6 and PrestaShop 1.7 have different declarations of $this->setTemplate()
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $themeName = Context::getContext()->shop->theme_name;
            if ($themeName === 'at_movic') {
                $this->setTemplate('module:altapay/views/templates/front/paymentredirect_atmovic.tpl');
            } else {
                $this->setTemplate('module:altapay/views/templates/front/payment_redirect17.tpl');
            }
        } else {
            $this->setTemplate('payment_redirect.tpl');
        }
    }
}
