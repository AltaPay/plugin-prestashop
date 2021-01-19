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
        $orderPaymentNature = '';
        $productDetails = $order->getProducts();
        $deliveryAddress = new Address((int) ($order->id_address_delivery));
        $invoiceAddress = new Address((int) ($order->id_address_invoice));
        $altapayOrderDetails = getAltapayOrderDetails($orderID);
        $terminalTokenControlStatus = 0;

        // Loop through the order details to check and assign payment nature
        foreach ($altapayOrderDetails as $altapayOrderDetail) {
            $orderPaymentNature = $altapayOrderDetail['paymentNature'];
            if ($orderPaymentNature === 'CreditCard') {
                $card = $altapayOrderDetail['cardMask'];
                $cardToken = $altapayOrderDetail['cardToken'];
                $cardBrand = $altapayOrderDetail['cardBrand'];
                $cardExpiryDate = $altapayOrderDetail['cardExpiryDate'];
                $userID = $this->context->customer->id;

                $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_saved_credit_card` WHERE userID = "' . $userID . '" and creditcardNumber ="' . $card . '" ';
                $results = Db::getInstance()->executeS($sql);

                $orderTerminalRemoteName = getAltapayOrderDetails($orderID)[0]['paymentTerminal'];

                $terminalTokenControlStatus = getTerminalTokenControlStatus($orderTerminalRemoteName)[0]['ccTokenControl_'];

                $this->context->smarty->assign('creditCardStatus', $results ? 1 : 0);
                $this->context->smarty->assign('cardMask', $card);
                $this->context->smarty->assign('cardToken', $cardToken);
                $this->context->smarty->assign('cardBrand', $cardBrand);
                $this->context->smarty->assign('cardExpiryDate', $cardExpiryDate);
            } else {
                $this->context->smarty->assign('cardMask', 0);
                $this->context->smarty->assign('cardToken', 0);
            }
        }

        // Assigment of necessary variables to render in template
        $this->context->smarty->assign('deliveryAddress', $deliveryAddress);
        $this->context->smarty->assign('invoiceAddress', $invoiceAddress);
        $this->context->smarty->assign('productDetails', $productDetails);
        $this->context->smarty->assign('orderID', $orderID);
        $this->context->smarty->assign('orderDetails', $order);

        // Display appropriate template according to user logged in statys
        if ($this->context->customer->isLogged() && $terminalTokenControlStatus) {
            $this->context->smarty->assign('paymentNature', $orderPaymentNature);
            $this->setTemplate('module:altapay/views/templates/front/orderConfirmationRegisteredUser.tpl');
        } else {
            $this->setTemplate('module:altapay/views/templates/front/orderConfirmationGuestUser.tpl');
        }
    }
}
