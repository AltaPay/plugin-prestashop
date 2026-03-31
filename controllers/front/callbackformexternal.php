<?php

/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2026 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbackformexternalModuleFrontController extends ModuleFrontController
{
    protected $display_header = false;
    protected $display_footer = false;
    public $content_only = true;

    /**
     * Method to follow when callback form is being requested
     *
     * @return void
     *
     * @throws Exception
     */
    public function postProcess()
    {
        $postData = getAltaPayCallbackData();
        $shopOrderId = $postData['shop_orderid'];
        $checksum = !empty($postData['checksum']) ? $postData['checksum'] : '';
        $terminal_name = getTransactionTerminalByUniqueId($shopOrderId);
        $secret = Altapay_Models_Terminal::getTerminalSecretByRemoteName($terminal_name);

        if (!empty($secret)) {
            if (empty($checksum) || calculateChecksum($postData, $secret) !== $checksum) {
                exit('Invalid request');
            }
        }

        $payment_style = Configuration::get('enable_cc_style');

        if ($payment_style == 'checkout-cc') {
            $payment_style = 'checkout-style';
        } elseif ($payment_style == 'checkout-v2') {
            $payment_style .= ' checkout-style';
        }
        $this->context->smarty->assign([
            'stylingclass' => $payment_style,
            'amount' => $postData['amount'],
            'shop_logo' => _PS_IMG_ . Configuration::get('PS_LOGO'),
        ]);
        if (version_compare(_PS_VERSION_, $this->module::PS_17_MIN_VERSION, '>=')) {
            $this->setTemplate('module:altapay/views/templates/front/payment_form_independent.tpl');
        } else {
            $this->setTemplate('payment_form_independent.tpl');
        }
    }
}
