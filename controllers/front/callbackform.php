<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once _PS_MODULE_DIR_.'/altapay/lib/altapay/altapay-php-sdk/lib/AltapayCallbackHandler.class.php';
require_once _PS_MODULE_DIR_.'/altapay/helpers.php';

class AltapayCallbackformModuleFrontController extends ModuleFrontController
{
    /**
     * Method to add external assets
     * @return void
     */
    public function setMedia()
    {
        parent::setMedia();
        $this->addCSS($this->module->getPathUri().'css/altapay.css', 'all');
        $this->addCSS($this->module->getPathUri().'css/custom_css.css', 'all');
    }


    /**
     * Method to follow when callback form is being requested
     * @return void
     */
    public function postProcess()
    {
        $css_dir = null;
        // Different conventions of assigning details for Version 1.6 and 1.7 respectively
        if (_PS_VERSION_ >= '1.7.0.0') {
            $cart = $this->context->cart;
            $this->context->smarty->assign('pathUri', $this->module->getPathUri());
            $this->context->smarty->assign('summarydetails', $cart->getSummaryDetails());
            $this->context->smarty->assign('products', $cart->getProducts());
            $this->context->smarty->assign('css_dir', $css_dir);
            $this->setTemplate('module:altapay/views/templates/front/payment_form17.tpl');
        } else {
            $this->context->smarty->assign('pathUri', $this->module->getPathUri());
            $this->setTemplate('payment_form.tpl');
        }
    }
}
