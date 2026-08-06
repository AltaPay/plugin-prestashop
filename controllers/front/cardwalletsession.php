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
        $currentShopId = $this->context->shop->id;
        $validationUrl = Tools::getValue('validationUrl');
        $terminalId = (int) Tools::getValue('termminalid');
        $currentUrl = $this->context->shop->getBaseURL();
        $domain = parse_url($currentUrl, PHP_URL_HOST);

        $terminal = new Altapay_Models_Terminal($terminalId);
        if (!Validate::isLoadedObject($terminal) || (int) $terminal->shop_id !== (int) $currentShopId) {
            $this->ajaxDie(json_encode(['success' => false, 'error' => 'Something went wrong']));
        }

        $cart = $this->context->cart;
        $request = new API\PHP\Altapay\Api\Payments\CardWalletSession(getAuth());
        $request->setTerminal($terminal->remote_name)
                ->setValidationUrl($validationUrl)
                ->setDomain($domain);

        if (!$terminal->applepay_legacy_flow) {
            $requestedAmount = (float) Tools::getValue('amount');
            $amount = $requestedAmount > 0 ? $requestedAmount : $cart->getOrderTotal(true, Cart::BOTH);

            $requestedCurrency = Tools::strtoupper((string) Tools::getValue('currency'));
            $currency = preg_match('/^[A-Z]{3}$/', $requestedCurrency) ? $requestedCurrency : $this->context->currency->iso_code;
            $uniqueId = $this->getTransactionUniqueId($cart->id);
            $shopOrderId = !empty($uniqueId) ? $uniqueId : uniqid('PS');

            if($shopOrderId){
                $request->setShopOrderId($shopOrderId)
                    ->setAmount($amount)
                    ->setCurrency($currency)
                    ->setApplePayRequestData([
                        'validationUrl' => $validationUrl,
                        'domain' => $domain,
                    ]);
            }
        }

        try {
            $response = $request->call();
            $this->sendValidateMerchantResponse($response, $cart);
        } catch (Exception $e) {
            $this->ajaxDie(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    /**
     * Send the AJAX response for a CardWalletSession result, storing the
     * PaymentId for the cart when the MarketPay flow returns one.
     *
     * @param object $response
     * @param Cart $cart
     *
     * @param string|null $sessionShopOrderId
     *
     * @return void
     */
    private function sendValidateMerchantResponse($response, $cart)
    {
        if ($response->Result !== 'Success') {
            $this->ajaxDie(json_encode(['success' => false, 'error' => 'Something went wrong']));
        }

        if (isset($response->ApplePaySession)) {
            $this->ajaxDie(json_encode(['success' => true, 'applePaySession' => $response->ApplePaySession]));
        }

        if (isset($response->WalletData->Session)) {
            $transaction = !empty($response->Transactions) ? reset($response->Transactions) : null;
            $paymentId = (isset($transaction->PaymentId) && !empty($transaction->PaymentId)) ? (string) $transaction->PaymentId : null;
            $shopOrderId = (isset($transaction->ShopOrderId) && !empty($transaction->ShopOrderId)) ? (string) $transaction->ShopOrderId : '';

            if (empty($shopOrderId)) {
                PrestaShopLogger::addLog(
                    'Apple Pay session could not determine valid shop_order_id for cart id '
                    . (int) $cart->id
                    . '. shopOrderId="' . pSQL($shopOrderId) . '"',
                    3,
                    null,
                    'altapay',
                    0,
                    true
                );
            }

            if (!empty($paymentId)) {
                $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_cartInfo` (id_cart, date_add, payment_id, shop_order_id) VALUES ('
                    . (int) $cart->id . ", '" . pSQL(time()) . "', '" . pSQL($paymentId) . "', '" . pSQL($shopOrderId) . "')"
                    . ' ON DUPLICATE KEY UPDATE `payment_id` = \'' . pSQL($paymentId) . '\'';
                $sql .= ', `shop_order_id` = \'' . pSQL($shopOrderId) . '\'';

                Db::getInstance()->Execute($sql);
            }

            $this->ajaxDie(json_encode(['success' => true, 'applePaySession' => $response->WalletData->Session]));
        }

        $this->ajaxDie(json_encode(['success' => false, 'error' => 'Something went wrong']));
    }

    private function getTransactionUniqueId($cartId)
    {
        $db = Db::getInstance();

        $uniqueId = $db->getValue('SELECT unique_id FROM `' . _DB_PREFIX_ . 'altapay_transaction` WHERE id_cart = ' . $cartId);

        if ($uniqueId) {
            return strpos($uniqueId, '_') !== false ? strstr($uniqueId, '_', true) : $uniqueId;
        }

        return $uniqueId;
    }
}
