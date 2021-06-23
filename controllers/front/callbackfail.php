<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbackfailModuleFrontController extends ModuleFrontController
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
    }

    /**
     * Method to follow when callback fail is being triggered
     *
     * @throws Exception
     *
     * @return void
     */
    public function postProcess()
    {
        $postData = Tools::getAllValues();
        $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
        $response = $callback->call();
        $shopOrderId = $response->shopOrderId;
        // Load the cart
        $cart = getCartFromUniqueId($shopOrderId);
        if (!Validate::isLoadedObject($cart)) {
            exit('Could not load cart - exiting');
        }

        $status = Tools::getValue('status');
        if ($status === 'cancelled') {
            $unique_id = Tools::getValue('shop_orderid');
            // Updated transaction record to cancel
            $pI = pSQL($unique_id);
            $q = 'UPDATE `' . _DB_PREFIX_ . 'altapay_transaction` set `is_cancelled`=1 WHERE `unique_id`=\'' . $pI . '\'';
            Db::getInstance()->Execute($q);

            // Redirect back to either standard or quick checkout process
            $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
            $pLink = $this->context->link->getPageLink($controller);
            $vCan = 'altapay_cancel=1&isPaymentStep=true&step=3#altapay_cancel';
            $location = $pLink . (strpos($controller, '?') !== false ? '&' : '?') . $vCan;
            Tools::redirectLink($location);
        } else {
            $mErM = $response->MerchantErrorMessage;
            $cId = $cart->id;
            $mNa = $this->module->name;
            $mId = $this->module->id;
            PrestaShopLogger::addLog('Payment failure for cart ' . $cId . '. Error Message: ' . $mErM, 3, 2001, $mNa, $mId, true);
            $this->context->smarty->assign([
                'errorText' => $response->CardHolderErrorMessage,
                'unique_id' => $shopOrderId,
                'payment_id' => $response->transactionId,
                'this_path' => $this->module->getPathUri(),
                'this_path_altapay' => $this->module->getPathUri(),
                'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $mNa . '/',
                'css_dir' => null,
            ]);
            // PrestaShop 1.6 and PrestaShop 1.7 have different declarations of $this->setTemplate()
            if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                $this->setTemplate('module:altapay/views/templates/front/payment_error17.tpl');
            } else {
                $this->setTemplate('payment_error.tpl');
            }
        }
    }
}
