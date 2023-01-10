<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class ALTAPAYcardwalletsessionModuleFrontController extends ModuleFrontController
{
    /**
     * Method to follow when saved credit card is being deleted from user account page
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     */
    public function postProcess()
    {
        $validationUrl = Tools::getValue('validationUrl');
        $terminalId = Tools::getValue('termminalid');
        $domain = $this->context->shop->getBaseURL(true);
    }
}
