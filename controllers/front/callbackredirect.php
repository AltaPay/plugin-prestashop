<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class AltapayCallbackredirectModuleFrontController extends ModuleFrontController
{
    /**
     * Method to follow when callback redirect is being triggered
     * @return void
     */
    public function postProcess()
    {
        $this->setTemplate('payment_redirect.tpl');
    }
}
