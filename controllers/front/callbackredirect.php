<?php
/**
 * Altapay module for Prestashop
 *
 * Copyright © 2020 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class AltapayCallbackredirectModuleFrontController extends ModuleFrontController
{
    /**
     * Method to follow when callback redirect is being triggered
     */
    public function postProcess()
    {
        $this->setTemplate('payment_redirect.tpl');
    }
}
