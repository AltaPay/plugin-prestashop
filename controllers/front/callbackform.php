<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbackformModuleFrontController extends ModuleFrontController
{
    /**
     * Method to add external assets
     *
     * @return void
     */
    public function setMedia()
    {
        parent::setMedia();
        $this->addCSS($this->module->getPathUri() . 'css/altapay.css', 'all');
        $this->addCSS($this->module->getPathUri() . 'css/custom_css.css', 'all');
    }

    /**
     * Method to follow when callback form is being requested
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        $css_dir = null;
        $postData = Tools::getAllValues();
        $cart = getCartFromUniqueId($postData['shop_orderid']);
        $checksum = !empty($postData['checksum']) ? $postData['checksum'] : '';
        $terminalRemoteName = getCvvLess($cart->id, $postData['shop_orderid']);
        $terminal_name = getTransactionTerminalByUniqueId($postData['shop_orderid']);
        $secret = Altapay_Models_Terminal::getTerminalSecretByRemoteName($terminal_name);

        if (!empty($checksum) and !empty($secret) and calculateChecksum($postData, $secret) !== $checksum) {
            exit();
        }

        $this->context->smarty->assign('cssClass', $terminalRemoteName);
        $payment_style = unserialize(Configuration::get('enable_cc_style'));
        if (empty($payment_style)) {
            $payment_style = 'legacy-cc';
        }
        $this->context->smarty->assign('stylingclass', $payment_style);
        $this->context->smarty->assign('summarydetails', $cart->getSummaryDetails());
        // Different conventions of assigning details for Version 1.6 and 1.7 respectively
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->context->smarty->assign('pathUri', $this->module->getPathUri());
            $this->context->smarty->assign('products', $cart->getProducts());
            $this->context->smarty->assign('css_dir', $css_dir);
            $this->setTemplate('module:altapay/views/templates/front/payment_form17.tpl');
        } else {
            $this->context->smarty->assign('pathUri', $this->module->getPathUri());
            $this->setTemplate('payment_form.tpl');
        }
    }
}
