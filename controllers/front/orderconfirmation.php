<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class ALTAPAYorderconfirmationModuleFrontController extends ModuleFrontController
{
    /**
     * @var bool
     */
    public $display_column_left;

    /**
     * Method to follow when order is being successfully processed
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

        // Assignment of order detail variables
        $orderID = (int) $_REQUEST['id_order'];
        $order = new Order($orderID);
        $productDetails = $order->getProducts();
        $deliveryAddress = new Address((int) ($order->id_address_delivery));
        $invoiceAddress = new Address((int) ($order->id_address_invoice));

        // Assigment of necessary variables to render in template
        $this->context->smarty->assign('deliveryAddress', $deliveryAddress);
        $this->context->smarty->assign('invoiceAddress', $invoiceAddress);
        $this->context->smarty->assign('productDetails', $productDetails);
        $this->context->smarty->assign('orderID', $orderID);
        $this->context->smarty->assign('orderDetails', $order);

        // Display appropriate template according to user logged in statys

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->setTemplate('module:altapay/views/templates/front/orderConfirmationGuestUser17.tpl');
        } else {
            $this->setTemplate('orderConfirmationGuestUser.tpl');
        }
    }
}
