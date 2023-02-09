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
        $terminalId = Tools::getValue('termminalid');
        $currentUrl = $this->context->shop->getBaseURL();
        $domain = parse_url($currentUrl, PHP_URL_HOST);
        $terminalName = getTerminalById($terminalId, $currentShopId)[0]['remote_name'];
        $request = new API\PHP\Altapay\Api\Payments\CardWalletSession(getAuth());
        $request->setTerminal($terminalName)
                ->setValidationUrl($validationUrl)
                ->setDomain($domain);
        try {
            $response = $request->call();
            if ($response->Result === 'Success') {
                $this->ajaxDie(Tools::jsonEncode(['success' => true, 'applePaySession' => $response->ApplePaySession]));
            } else {
                $this->ajaxDie(Tools::jsonEncode(['success' => false, 'error' => "Something went wrong"]));
            }
        } catch (Exception $e) {
            $this->ajaxDie(Tools::jsonEncode(['success' => false, 'error' => $e->getMessage()]));
        }
    }
}
