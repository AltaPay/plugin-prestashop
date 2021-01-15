<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class ALTAPAYsavecreditcardModuleFrontController extends ModuleFrontController
{
    /**
     * Method for saving credit card in database
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     */
    public function postProcess()
    {
        $cardMask = Tools::getValue('cardMask', false);
        $cardToken = Tools::getValue('cardToken', false);
        $cardBrand = Tools::getValue('cardBrand', false);
        $cardExpiryDate = Tools::getValue('cardExpiryDate', false);

        if ($this->context->customer->isLogged()) {
            $customerID = $this->context->customer->id;
            $sql = 'REPLACE  into `' . _DB_PREFIX_ . 'altapay_saved_credit_card` (time,userID,cardBrand,creditCardNumber,cardExpiryDate,ccToken) VALUES (Now(),' . $customerID . ',"' . $cardBrand . '","' . $cardMask . '","' . $cardExpiryDate . '","' . $cardToken . '")';
            Db::getInstance()->executeS($sql);
        }
        Tools::redirect('index.php?fc=module&module=altapay&controller=showsavedcreditcards');
    }
}
