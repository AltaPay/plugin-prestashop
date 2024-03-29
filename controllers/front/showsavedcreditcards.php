<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class ALTAPAYshowsavedcreditcardsModuleFrontController extends ModuleFrontController
{
    /**
     * @var bool
     */
    public $display_column_left;

    /**
     * Method for displaying saved credit cards in user account page
     *
     * @return void
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function initContent()
    {
        $this->display_column_left = false;
        parent::initContent();

        $savedCreditCard = [];

        if ($this->context->customer->isLogged()) {
            $customerID = $this->context->customer->id;
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_saved_credit_card` WHERE userID =' . pSQL($customerID);
            $results = Db::getInstance()->executeS($sql);
            if ($results) {
                foreach ($results as $result) {
                    $savedCreditCard[] = [
                        'userID' => $result['userID'],
                        'creditCard' => $result['creditCardNumber'],
                        'cardBrand' => $result['cardBrand'],
                        'cardExpiryDate' => $result['cardExpiryDate'],
                    ];
                }
                $this->context->smarty->assign('savedCreditCard', $savedCreditCard);
            }
        }

        $this->setTemplate('module:altapay/views/templates/front/showCreditCards.tpl');
    }
}
