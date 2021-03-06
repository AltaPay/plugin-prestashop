<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCallbackokModuleFrontController extends ModuleFrontController
{
    /**
     * Method to follow when callback ok is being triggered
     *
     * @return void
     *
     * @throws AltapayXmlException
     * @throws AltapayMerchantAPIException
     */
    public function postProcess()
    {
        try {
            $postData = Tools::getAllValues();
            $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
            $response = $callback->call();
            $shopOrderId = $response->shopOrderId;

            // This lock prevents orders to be created twice.
            $fp = fopen(_PS_MODULE_DIR_ . '/altapay/controllers/front/lock.txt', 'r');
            flock($fp, LOCK_EX);

            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                $this->unlock($fp);
                exit('Could not load cart - exiting');
            }

            // Load the customer
            $customer = new Customer((int) $cart->id_customer);

            // Check if an order already exists
            $order = getOrderFromUniqueId($shopOrderId);
            if (Validate::isLoadedObject($order)) {
                // An order has already been created from this cart - redirect
                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module='
                                . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key='
                                . $customer->secure_key);
            }

            // Handle success
            if ($response && is_array($response->Transactions)) {
                $amountPaid = 0;
                $transactionID = null;
                $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
                $paymentType = $response->type;
                $captureStatus = $response->requireCapture;
                $currencyPaid = Currency::getIdByIsoCode($response->currency);
                $amountPaid = $response->Transactions[0]->CapturedAmount;
                $transactionID = $response->Transactions[0]->TransactionId;
                /*
                 * If payment type is 'payment' funds have not yet been captured,
                 * so AltaPay returns zero as the captured amount.
                 * Therefore we assume full payment has been authorized.
                 */
                if ($paymentType === 'payment') {
                    $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
                    $currencyPaid = new Currency($cart->id_currency);
                } elseif ($paymentType === 'paymentAndCapture' && $captureStatus === true) {
                    $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
                    $currencyPaid = new Currency($cart->id_currency);
                    $api = new API\PHP\Altapay\Api\Payments\CaptureReservation(getAuth());
                    $api->setAmount($amountPaid);
                    $api->setTransaction($transactionID);
                    $api->call();
                }

                // Determine payment method for display
                $paymentMethod = determinePaymentMethodForDisplay($response);
                // Create an order with 'payment accepted' status
                $currencyPaidID = (int) $currencyPaid->id;
                $customerSecureKey = $customer->secure_key;
                $cartID = $cart->id;
                $this->module->validateOrder($cartID, $orderStatus, $amountPaid, $paymentMethod, null, null,
                    $currencyPaidID, false, $customerSecureKey);

                // Log order
                $currentOrder = new Order((int) $this->module->currentOrder);
                createAltapayOrder($response, $currentOrder);
                $this->unlock($fp);
                if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                    Tools::redirect('index.php?fc=module&module=altapay&controller=orderconfirmation&id_order='
                                    . $this->module->currentOrder);
                } else {
                    Tools::redirect('index.php?controller=order-detail&id_order=' . $this->module->currentOrder);
                }
            } else {
                // Unexpected scenario
                $moduleName = $this->module->name;
                $moduleID = $this->module->id;
                PrestaShopLogger::addLog('Callback ok received but payment was unsuccessful', 3, '1004', $moduleName, $moduleID,
                    true);
                echo $this->module->l('This payment method is not available 1004.', 'callbackok');

                /* Redirect user back to the checkout payment step,
                * assume a failure occured creating the URL until a payment url is received
                */
                $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
                $as = $this->context->link;
                $con = $controller;
                $redirect = $as->getPageLink($con, true, null, 'step=3&altapay_unavailable=1')
                              . '#altapay_unavailable';
                $this->unlock($fp);
                Tools::redirect($redirect);
            }
        } finally {
            $this->unlock($fp);
        }
    }

    /**
     * @param string $fileOpen
     *
     * @return void
     */
    public function unlock($fileOpen)
    {
        flock($fileOpen, LOCK_UN);
        fclose($fileOpen);
    }
}
