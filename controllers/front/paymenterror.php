<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2026 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayPaymenterrorModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $error = Tools::safeOutput(Tools::getValue('error'));

        $this->context->smarty->assign(['errorText' => $error]);

        if (version_compare(_PS_VERSION_, $this->module::PS_17_MIN_VERSION, '>=')) {
            $this->setTemplate('module:altapay/views/templates/front/payment_error17.tpl');
        } else {
            $this->setTemplate('payment_error.tpl');
        }
    }
}
