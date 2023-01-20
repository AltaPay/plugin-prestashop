<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapaycardwalletsessionModuleFrontController extends ModuleFrontController
{
    /**
     * Method to follow when card wallet session initiate
     *
     * @return void
     */
    public function postProcess()
    {
        $validationUrl = Tools::getValue('validationUrl');
        $terminalId = Tools::getValue('termminalid');
        $domain = $this->context->shop->getBaseURL(true);
    }
}
