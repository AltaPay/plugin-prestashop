<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Function library for ALTAPAY payment module
 *
 * @since 04-2016
 */

/**
 * Determine which payment method to display in PrestaShop backoffice
 * i.e. MobilePay Visa, CreditCard, etc.
 *
 * @param array $transactionInfo
 *
 * @return array
 */
function transactionInfo($transactionInfo = [])
{
    $pluginName = 'altapay';
    $pluginVersion = '3.8.5';

    // Transaction info
    $transactionInfo['ecomPlatform'] = 'PrestaShop';
    $transactionInfo['ecomVersion'] = _PS_VERSION_;
    $transactionInfo['ecomPluginName'] = $pluginName;
    $transactionInfo['ecomPluginVersion'] = $pluginVersion;
    $transactionInfo['otherInfo'] = 'storeName-' . Configuration::get('PS_SHOP_NAME');

    return $transactionInfo;
}

/**
 * @param AltapayCallbackHandler $response
 *
 * @return string
 */
function determinePaymentMethodForDisplay($response)
{
    $paymentNature = $response->nature;

    if ($paymentNature === 'Wallet') {
        return $response->Transactions[0]->PaymentSchemeName;
    }
    if ($paymentNature === 'CreditCard') {
        return $paymentNature;
    }
    if ($paymentNature === 'CreditCardWallet') {
        return $response->Transactions[0]->PaymentSchemeName;
    }

    return $paymentNature;
}

/**
 * Use the unique ID to determine cart
 *
 * @param int $uniqueId
 *
 * @return bool|Cart
 */
function getCartFromUniqueId($uniqueId)
{
    $results = Db::getInstance()->getRow('SELECT id_cart 
    FROM `' . _DB_PREFIX_ . 'altapay_transaction` 
    WHERE unique_id=\'' . pSQL($uniqueId) . '\'');
    if (!$results) {
        return false;
    }

    return new Cart((int) $results['id_cart']);
}

/**
 * Use the unique ID to fetch the created from it (if any)
 *
 * @param int $uniqueId
 *
 * @return bool|Order
 */
function getOrderFromUniqueId($uniqueId)
{
    $results = Db::getInstance()->getRow('SELECT id_order 
    FROM `' . _DB_PREFIX_ . 'altapay_order` 
    WHERE unique_id = \'' . $uniqueId . '\'');
    if (!$results) {
        return false;
    }

    return new Order((int) $results['id_order']);
}

/**
 * @param $uniqueId
 *
 * @return false|Order
 */
function getChildOrderFromUniqueId($uniqueId)
{
    $results = Db::getInstance()->getRow('SELECT id_order
    FROM `' . _DB_PREFIX_ . 'altapay_child_order` 
    WHERE unique_id = \'' . pSQL($uniqueId) . '\'');
    if (!$results) {
        return false;
    }

    return new Order((int) $results['id_order']);
}

/**
 * Method for marking and updating status of order as captured in case of a captured action in database
 *
 * @param int $paymentId
 * @param array $orderlines
 *
 * @return bool
 */
function markAsCaptured($paymentId, $orderlines = [])
{
    $sql = 'UPDATE ' . _DB_PREFIX_ . 'altapay_order SET requireCapture = 0 WHERE payment_id = ' . (int) $paymentId
           . ' LIMIT 1';
    Db::getInstance()->Execute($sql);

    foreach ($orderlines as $productId => $quantity) {
        if ($quantity == 0) {
            continue;
        }

        $result = Db::getInstance()->getRow('SELECT captured 
            FROM ' . _DB_PREFIX_ . 'altapay_orderlines WHERE altapay_payment_id = "'
                                            . pSQL($paymentId) . '" AND product_id = \'' . pSQL($productId) . "'");

        if (isset($result['captured'])) {
            $quantity += $result['captured'];
            $sqlUpdateCapture = 'UPDATE ' . _DB_PREFIX_ .
                                'altapay_orderlines SET captured = ' . (int) $quantity .
                                ' WHERE altapay_payment_id = ' . pSQL($paymentId);
            Db::getInstance()->Execute($sqlUpdateCapture);
        } else {
            $sqlOrderLine = 'INSERT INTO ' . _DB_PREFIX_ .
                            'altapay_orderlines (altapay_payment_id, product_id, captured) 
                VALUES("' . pSQL($paymentId) . '", "' . pSQL($productId) . '", ' . (int) $quantity . ')';
            Db::getInstance()->Execute($sqlOrderLine);
        }
    }

    return true;
}

/**
 * @param $paymentId
 *
 * @return bool
 */
function markChildOrderAsCaptured($paymentId)
{
    $sql = 'UPDATE ' . _DB_PREFIX_ . 'altapay_child_order SET requireCapture = 0 WHERE payment_id = \'' . pSQL($paymentId)
        . '\' LIMIT 1';
    Db::getInstance()->Execute($sql);

    $result = getTransactionStatus($paymentId);

    if (!isset($result['captured'])) {
        $sqlOrderLine = 'INSERT INTO ' . _DB_PREFIX_ . 'altapay_orderlines (altapay_payment_id, product_id, captured,refunded)  VALUES("' . pSQL($paymentId) . '", "1", "1", "0")';
        Db::getInstance()->Execute($sqlOrderLine);
    }

    return true;
}

/**
 * Method for marking and updating status of order as refund in case of a refund action in database
 *
 * @param int $paymentId
 * @param array $orderlines
 *
 * @return bool
 */
function markAsRefund($paymentId, $orderlines = [])
{
    $sqlRequireCapture = 'SELECT requireCapture 
    FROM ' . _DB_PREFIX_ . 'altapay_order WHERE payment_id = ' . pSQL($paymentId);
    $result = Db::getInstance()->getRow($sqlRequireCapture);
    // Only payments which have been captured/partial captured will be considered
    if (!isset($result['requireCapture']) || $result['requireCapture'] != 0) {
        return false;
    }
    foreach ($orderlines as $productId => $quantity) {
        if ($quantity == 0) {
            continue;
        }
        $sqlGetRefundedFieldValue = 'SELECT captured, refunded 
                FROM ' . _DB_PREFIX_ . 'altapay_orderlines WHERE altapay_payment_id = "'
                                    . pSQL($paymentId) . '" AND product_id = \'' . pSQL($productId) . "'";
        $result = Db::getInstance()->getRow($sqlGetRefundedFieldValue);
        if (isset($result['refunded'])) {
            $quantity += $result['refunded'];
            // If the amount of refunded items is bigger than the actual captured amount than set the max amount
            if ($quantity > $result['captured']) {
                $quantity = $result['captured'];
            }

            // Update only of there is a capture for this product
            $sql = 'UPDATE ' . _DB_PREFIX_ . 'altapay_orderlines SET refunded = '
                   . $quantity . " WHERE altapay_payment_id = '" . pSQL($paymentId) . "' AND product_id = '" . pSQL($productId) . "'";
            Db::getInstance()->Execute($sql);
        }
    }

    return true;
}

/**
 * @param $paymentId
 *
 * @return bool
 */
function markChildOrderAsRefund($paymentId)
{
    $sqlRequireCapture = 'SELECT requireCapture  FROM ' . _DB_PREFIX_ . 'altapay_child_order WHERE payment_id = \'' . pSQL($paymentId) . "'";
    $result = Db::getInstance()->getRow($sqlRequireCapture);
    // Only payments which have been captured/partial captured will be considered
    if (!isset($result['requireCapture']) || $result['requireCapture'] != 0) {
        return false;
    }

    $transactionData = getTransactionStatus($paymentId);
    if (isset($transactionData['refunded'])) {
        // Update only of there is a capture for this product
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'altapay_orderlines 
        SET refunded = 1 
        WHERE altapay_payment_id = "' . pSQL($paymentId) . '"';

        Db::getInstance()->Execute($sql);
    }

    return true;
}

/**
 * Method to update latest error message from gateway response in database
 *
 * @param int $paymentId
 * @param string $latestError
 *
 * @return void
 */
function saveLastErrorMessage($paymentId, $latestError)
{
    $sql = 'UPDATE 
    ' . _DB_PREFIX_ . 'altapay_order SET latestError = \'' . pSQL($latestError) . '\' WHERE payment_id='
           . pSQL($paymentId) . ' LIMIT 1';
    Db::getInstance()->Execute($sql);
}

/**
 * @param $paymentId
 * @param $latestError
 *
 * @return void
 */
function saveLastErrorMessageForChildOrder($paymentId, $latestError)
{
    $sql = 'UPDATE 
    ' . _DB_PREFIX_ . 'altapay_child_order SET latestError = \'' . pSQL($latestError) . '\' WHERE payment_id= \''
        . pSQL($paymentId) . '\' LIMIT 1';
    Db::getInstance()->Execute($sql);
}

/**
 * Method for updating payment status in database
 *
 * @param int $paymentId
 * @param string $paymentStatus
 *
 * @return void
 */
function updatePaymentStatus($paymentId, $paymentStatus)
{
    $sql = 'UPDATE 
    ' . _DB_PREFIX_ . 'altapay_order SET paymentStatus = \'' . pSQL($paymentStatus) . '\' WHERE payment_id='
           . pSQL($paymentId) . ' LIMIT 1';
    Db::getInstance()->Execute($sql);
}

/**
 * @param $paymentId
 * @param $paymentStatus
 *
 * @return void
 */
function updatePaymentStatusForChildOrder($paymentId, $paymentStatus)
{
    $sql = 'UPDATE 
    ' . _DB_PREFIX_ . 'altapay_child_order SET paymentStatus = \'' . pSQL($paymentStatus) . '\' WHERE payment_id= \''
        . pSQL($paymentId) . '\' LIMIT 1';
    Db::getInstance()->Execute($sql);
}

/**
 * Method for creating orders at prestashop backend
 *
 * @param AltapayCallbackHandler $response
 * @param Order $current_order
 * @param string $payment_status
 *
 * @return void
 */
function createAltapayOrder($response, $current_order, $payment_status = 'succeeded', $ischildOrder = false)
{
    $latestTransKey = 0;
    if (isset($response) && isset($response->Transactions)) {
        foreach ($response->Transactions as $key => $transaction) {
            if ($transaction->AuthType === 'subscription_payment' && $transaction->CreatedDate > $max_date) {
                $max_date = $transaction->CreatedDate;
                $latestTransKey = $key;
            }
        }
        $transaction = $response->Transactions[$latestTransKey];
        $uniqueId = ($payment_status === 'subscription_payment_succeeded' ? "$transaction->ShopOrderId ($transaction->TransactionId)" : $transaction->ShopOrderId);
        $paymentId = $transaction->TransactionId;
        $cardMask = $transaction->CreditCardMaskedPan;
        $cardToken = $transaction->CreditCardToken;
        $cardExpiryMonth = isset($transaction->CreditCardExpiry->Month) ? $transaction->CreditCardExpiry->Month : null;
        $cardExpiryYear = isset($transaction->CreditCardExpiry->Year) ? $transaction->CreditCardExpiry->Year : null;
        $cardBrand = $transaction->PaymentSchemeName;
        $paymentType = $transaction->AuthType;
        $paymentTerminal = $transaction->Terminal;
        $paymentNature = $transaction->PaymentNature;
        $paymentStatus = $payment_status;
        $requireCapture = 0;
        if ($paymentType === 'payment' or ($paymentType === 'subscription_payment' and $transaction->TransactionStatus !== 'captured')) {
            $requireCapture = 1;
        }
        $cardExpiryDate = 0;
        if ($cardExpiryMonth && $cardExpiryYear) {
            $cardExpiryDate = $cardExpiryMonth . '/' . $cardExpiryYear;
        }
    }
    $customerInfo = $transaction->CustomerInfo;
    $cardCountry = $customerInfo->CountryOfOrigin->Country;

    if ($ischildOrder) {
        // Remove the underscore and everything after it
        $parentShopId = explode('_', $uniqueId)[0];
        //insert into order log
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_child_order`
        (id_order, unique_id, parent_unique_id, payment_id, cardMask, cardToken, cardBrand, cardExpiryDate, cardCountry, 
        paymentType, paymentTerminal, paymentStatus, paymentNature, requireCapture, date_add) 
        VALUES ' .
            "('" . $current_order->id . "', '" . pSQL($uniqueId) . "', '" . pSQL($parentShopId) . "', '"
            . pSQL($paymentId) . "', '" . pSQL($cardMask) . "', '"
            . pSQL($cardToken) . "', '" . pSQL($cardBrand) . "', '"
            . pSQL($cardExpiryDate) . "', '"
            . pSQL($cardCountry) . "', '" . pSQL($paymentType) . "', '"
            . pSQL($paymentTerminal) . "', '"
            . pSQL($paymentStatus) . "', '" . pSQL($paymentNature) . "', '"
            . pSQL($requireCapture) . "', '" . time()
            . "')" . ' ON DUPLICATE KEY UPDATE `paymentStatus` = ' . "'" . pSQL($paymentStatus) . "'";

        Db::getInstance()->Execute($sql);

        if (Validate::isLoadedObject($current_order)) {
            try {
                $current_order->addOrderPayment($transaction->ReservedAmount, $paymentTerminal, $uniqueId);
            } catch (Exception $e) {
                PrestaShopLogger::addLog("Child shop_orderid $uniqueId, Parent ID : $current_order->id, {$e->getMessage()}", 4);
            }
        }
    } else {
        //insert into order log
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_order`
        (id_order, unique_id, payment_id, cardMask, cardToken, cardBrand, cardExpiryDate, cardCountry, 
        paymentType, paymentTerminal, paymentStatus, paymentNature, requireCapture, date_add) 
        VALUES ' .
            "('" . $current_order->id . "', '" . pSQL($uniqueId) . "', '"
            . pSQL($paymentId) . "', '" . pSQL($cardMask) . "', '"
            . pSQL($cardToken) . "', '" . pSQL($cardBrand) . "', '"
            . pSQL($cardExpiryDate) . "', '"
            . pSQL($cardCountry) . "', '" . pSQL($paymentType) . "', '"
            . pSQL($paymentTerminal) . "', '"
            . pSQL($paymentStatus) . "', '" . pSQL($paymentNature) . "', '"
            . pSQL($requireCapture) . "', '" . time()
            . "')" . ' ON DUPLICATE KEY UPDATE `paymentStatus` = ' . "'" . pSQL($paymentStatus) . "'";
        Db::getInstance()->Execute($sql);

        if (Validate::isLoadedObject($current_order)) {
            try {
                $payment = $current_order->getOrderPaymentCollection();
                if (isset($payment[0])) {
                    $payment[0]->transaction_id = pSQL($uniqueId);
                    $payment[0]->card_number = pSQL($cardMask);
                    $payment[0]->card_brand = pSQL($cardBrand);
                    $payment[0]->save();
                }
            } catch (Exception $e) {
                PrestaShopLogger::addLog("Order ID : $current_order->id, {$e->getMessage()}", 4);
            }
        }
    }
}

/**
 * Method for getting order details created using plugin
 *
 * @param int $orderID
 *
 * @return mixed
 */
function getAltapayOrderDetails($orderID)
{
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_order` WHERE id_order =' . (int) $orderID;

    return Db::getInstance()->executeS($sql);
}

/**
 * @param $orderID
 *
 * @return array
 */
function getAltapayChildOrderDetails($orderID)
{
    if (filter_var($orderID, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        return false;
    }

    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_child_order` WHERE id_order =' . $orderID;

    return Db::getInstance()->executeS($sql);
}

/**
 * Get terminal remote name based on terminal id and shop id
 *
 * @param int $terminalId
 * @param int $shop_id [optional] The shop id, defaults to 1 if not provided
 *
 * @return array|null An array containing the `remote_name` field, or null if no terminal is found
 *
 * @throws Exception If the provided `shop_id` is not a valid integer
 */
function getTerminalById($terminalId, $shop_id = 1)
{
    try {
        if (filter_var($shop_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new Exception('Invalid shop id');
        }
        $query = 'SELECT `remote_name` FROM `' . _DB_PREFIX_ . 'altapay_terminals` WHERE `id_terminal` = ' . (int) $terminalId . ' AND `shop_id` = ' . $shop_id;
        $result = Db::getInstance()->executeS($query);

        return $result;
    } catch (Exception $e) {
        $context = Context::getContext();
        if (isset($context->controller->errors)) {
            $context->controller->errors[] = $e->getMessage();
        }
        PrestaShopLogger::addLog($e->getMessage(), 4);
    }
}

/**
 * Get terminal control status based on terminal remote name
 *
 * @param string $terminalRemoteName
 *
 * @return array
 */
function getTerminalTokenControlStatus($terminalRemoteName)
{
    $sql = 'SELECT ccTokenControl_ FROM `' . _DB_PREFIX_ . 'altapay_terminals` WHERE `remote_name`='
           . "'$terminalRemoteName'";

    return Db::getInstance()->executeS($sql);
}

/**
 * @return \API\PHP\Altapay\Authentication
 */
function getAuth()
{
    $config = Configuration::getMultiple([
        'ALTAPAY_USERNAME',
        'ALTAPAY_PASSWORD',
        'ALTAPAY_URL',
    ]);

    return new API\PHP\Altapay\Authentication($config['ALTAPAY_USERNAME'], $config['ALTAPAY_PASSWORD'],
        $config['ALTAPAY_URL']);
}
/**
 * @param int $cartId
 * @param string $shopOrderId
 *
 * @return array
 */
function getCvvLess($cartId, $shopOrderId)
{
    $sql = 'SELECT term.`cvvLess`
    FROM `' . _DB_PREFIX_ . 'altapay_transaction` trans
    INNER JOIN `' . _DB_PREFIX_ . 'altapay_terminals` term ON trans.`terminal_name` = term.`remote_name`
    WHERE trans.`id_cart` = ' . (int) $cartId . '
        AND trans.`unique_id` = ' . "'$shopOrderId'";

    return Db::getInstance()->getValue($sql);
}

/**
 * @param int $orderID
 * @param string $reconciliation_identifier
 * @param string $type
 *
 * @return bool
 */
function saveOrderReconciliationIdentifier($orderID, $reconciliation_identifier, $shopOrderId, $type = 'captured')
{
    if (filter_var($orderID, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        return false;
    }

    return Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'altapay_order_reconciliation`
		(id_order, unique_id, reconciliation_identifier, transaction_type) 
        VALUES ' . "('" . (int) $orderID . "','" . pSQL($shopOrderId) . "','" . pSQL($reconciliation_identifier) . "', '" . pSQL($type) . "')");
}

/**
 * @param int $orderID
 *
 * @return array
 */
function getOrderReconciliationIdentifiers($orderID)
{
    $sql = 'SELECT reconciliation_identifier, transaction_type FROM `' . _DB_PREFIX_ . 'altapay_order_reconciliation` WHERE id_order =' . (int) $orderID;

    return Db::getInstance()->executeS($sql);
}

/**
 * @param int $orderID
 * @param string $reconciliation_identifier
 * @param string $type
 *
 * @return void
 */
function saveOrderReconciliationIdentifierIfNotExists($orderID, $reconciliation_identifier, $type, $shopOrderId)
{
    $sql = 'SELECT id FROM `' . _DB_PREFIX_ . 'altapay_order_reconciliation` WHERE id_order =' . (int) $orderID .
        " AND reconciliation_identifier ='" . pSQL($reconciliation_identifier) .
        "' AND transaction_type ='" . pSQL($type) . "'";

    if (!Db::getInstance()->getRow($sql)) {
        saveOrderReconciliationIdentifier($orderID, $reconciliation_identifier, $shopOrderId, $type);
    }
}

/**
 * @param $orderID
 * @param $reconciliation_identifier
 * @param $type
 * @param $shopOrderId
 *
 * @return void
 */
function saveChildOrderIdentifier($orderID, $reconciliation_identifier, $type, $shopOrderId)
{
    $sql = 'SELECT id FROM `' . _DB_PREFIX_ . 'altapay_order_reconciliation` WHERE unique_id =\'' . pSQL($shopOrderId) .
        "' AND reconciliation_identifier ='" . pSQL($reconciliation_identifier) .
        "' AND transaction_type ='" . pSQL($type) . "'";

    if (!Db::getInstance()->getRow($sql)) {
        saveOrderReconciliationIdentifier($orderID, $reconciliation_identifier, $shopOrderId, $type);
    }
}

/**
 * @param $cart
 *
 * @return bool
 */
function cartHasSubscriptionProduct($cart)
{
    $subscription_product_exists = false;
    if (Module::isEnabled('wkproductsubscription')) {
        include_once _PS_MODULE_DIR_ . 'wkproductsubscription/classes/WkSubscriptionRequired.php';
        if ($cartProducts = $cart->getProducts()) {
            foreach ($cartProducts as $productData) {
                $idProduct = $productData['id_product'];
                $idAttr = $productData['id_product_attribute'];
                $idCart = $cart->id;
                // @phpstan-ignore-next-line
                if (WkProductSubscriptionModel::checkIfSubscriptionProduct($idProduct) && WkSubscriptionCartProducts::getByIdProductByIdCart($idCart, $idProduct, $idAttr, true)) {
                    $subscription_product_exists = true;
                    break;
                }
            }
        }
    }

    return $subscription_product_exists;
}

/**
 * @param int $order_id
 * @param int $parent_order_id
 *
 * @return void
 */
function chargeAltaPayAgreement($order_id, $parent_order_id)
{
    $order = new Order((int) $order_id);
    $agreement = getAgreementByOrderId($parent_order_id);
    if (!empty($agreement)) {
        $reconciliation_identifier = sha1($agreement[0]['agreement_id'] . time());
        $amount = (float) $order->total_paid;
        try {
            $api = new API\PHP\Altapay\Api\Subscription\ChargeSubscription(getAuth());
            $api->setTransaction($agreement[0]['agreement_id']);
            $api->setAgreement(['id' => $agreement[0]['agreement_id']]);
            $api->setReconciliationIdentifier($reconciliation_identifier);
            if ($amount > 0) {
                $api->setAmount($amount);
            }
            $response = $api->call();
            $latestTransKey = 0;
            if (isset($response) && isset($response->Transactions)) {
                foreach ($response->Transactions as $key => $transaction) {
                    if ($transaction->AuthType === 'subscription_payment' && $transaction->CreatedDate > $max_date) {
                        $max_date = $transaction->CreatedDate;
                        $latestTransKey = $key;
                    }
                }
                $transaction = $response->Transactions[$latestTransKey];
                $uniqueId = (($transaction->AuthType === 'subscription_payment') ? "$transaction->ShopOrderId ($transaction->TransactionId)" : $transaction->ShopOrderId);
                createAltapayOrder($response, $order, 'subscription_payment_succeeded');
                saveAltaPayTransaction($uniqueId, $transaction->CapturedAmount, $transaction->Terminal, $response->Result);
                saveOrderReconciliationIdentifier($order_id, $reconciliation_identifier, $uniqueId);
                $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
            }
        } catch (Exception $e) {
            $file = fopen(dirname(__FILE__) . '/cron_logs.log', 'a');
            $msg = "\r\n\n";
            $msg .= '[' . date('d-m-Y H:i:s') . ']  ----  ===========AltaPay cron error============ ' . "\n";
            $msg .= $e->getMessage() . "\n";
            $msg .= json_encode([$order, $agreement]) . "\n";
            $msg .= "===========AltaPay cron error============\n";
            fwrite($file, $msg);
            fclose($file);
        }
    }
}

/**
 * @param int $id_order
 *
 * @return array
 */
function getAgreementByOrderId($id_order)
{
    $sql = 'SELECT `agreement_id`, `agreement_type`, `agreement_unscheduled_type` 
                FROM `' . _DB_PREFIX_ . "altapay_saved_credit_card` WHERE `id_order` ='" . pSQL($id_order) . "'";

    return Db::getInstance()->executeS($sql);
}

 /**
  * @param string $unique_id
  * @param string $amount
  * @param string $terminal
  * @param string|null $terminal
  *
  * @return void
  */
 function saveAltaPayTransaction($unique_id, $amount, $terminal, $transactionStatus = null)
 {
     $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_transaction` 
				(id_cart, payment_form_url, token, transaction_status, unique_id, amount, terminal_name, date_add) VALUES ' .
        "('', '', '', '" . pSQL($transactionStatus) . "', '" . pSQL($unique_id) . "', '" . pSQL($amount) . "', '" . pSQL($terminal) . "' ,
             '" . pSQL(time()) . "')" . ' ON DUPLICATE KEY UPDATE `amount` = ' . pSQL($amount);

     Db::getInstance()->Execute($sql);
 }

 /**
  * @param $shopOrderId
  * @param $transactionStatus
  *
  * @return void
  */
 function updateTransactionStatus($shopOrderId, $transactionStatus)
 {
     $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_transaction` SET `transaction_status` = "' . $transactionStatus . '" WHERE `unique_id` = \'' . $shopOrderId . '\'';

     Db::getInstance()->Execute($sql);
 }
/**
 * Method for updating payment status in database
 *
 * @param int $id_order
 * @param int $paymentId
 *
 * @return void
 */
function updateTransactionIdForParentSubscription($id_order, $paymentId)
{
    $sql = 'UPDATE 
    ' . _DB_PREFIX_ . 'altapay_order SET payment_id = \'' . pSQL($paymentId) . '\' WHERE id_order='
        . (int) $id_order . ' LIMIT 1';
    Db::getInstance()->Execute($sql);
}

/**
 * @param $id_order
 * @param $paymentId
 *
 * @return bool
 */
function updateParentTransIdChildOrder($id_order, $paymentId)
{
    if (filter_var($id_order, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        return false;
    }

    $sql = 'UPDATE 
    ' . _DB_PREFIX_ . 'altapay_child_order SET payment_id = \'' . pSQL($paymentId) . '\' WHERE id_order='
        . $id_order . ' LIMIT 1';

    return Db::getInstance()->Execute($sql);
}

/**
 * Retrieve the latest transaction from a given response object
 *
 * @param object $response
 *
 * @return object
 */
function getTransaction($response)
{
    $max_date = '';
    $latestTransKey = 0;
    foreach ($response->Transactions as $key => $transaction) {
        if ($transaction->CreatedDate > $max_date) {
            $max_date = $transaction->CreatedDate;
            $latestTransKey = $key;
        }
    }

    return $response->Transactions[$latestTransKey];
}

/**
 * @param $response
 * @param $transaction
 *
 * @return array|void
 */
function handleFraudPayment($response, $transaction)
{
    $message = ' ';
    $paymentProcessed = false;
    $paymentStatus = strtolower($response->paymentStatus);
    $transactionID = $transaction->TransactionId;
    $fraudStatus = $transaction->FraudRecommendation;
    $fraudMsg = $transaction->FraudExplanation;
    if ($paymentStatus === 'released' || (isset($fraudStatus) && isset($fraudMsg) && strtolower($fraudStatus) === 'deny')) {
        $message = 'Payment released!';
        $fraudConfig = Tools::getValue('enable_fraud', Configuration::get('enable_fraud'));
        $enableReleaseRefund = Tools::getValue('enable_release_refund', Configuration::get('enable_release_refund'));
        if ($fraudConfig && $enableReleaseRefund && strtolower($fraudStatus) === 'deny') {
            $message = $fraudMsg;
            $paymentProcessed = true;
            try {
                refundOrReleaseTransactionByStatus($transaction);
                saveLastErrorMessage($transactionID, $fraudMsg);
                updatePaymentStatus($transactionID, $fraudStatus);
            } catch (Exception $e) {
                saveLastErrorMessage($transactionID, $e->getMessage());
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Could not release reservation. ' . $e->getMessage(),
                ]);
                exit();
            }
        }
    }

    if ($response->type === 'subscriptionAndCharge' && strtolower($response->status) === 'succeeded') {
        $authType = $transaction->AuthType;
        $transStatus = $transaction->TransactionStatus;

        if (isset($transaction) && $authType === 'subscription_payment' && $transStatus !== 'captured') {
            $paymentProcessed = true;
        }
    }

    return ['msg' => $message, 'payment_status' => $paymentProcessed];
}

/**
 * Get terminal for AltaPay transaction by shop_orderid (unique ID)
 *
 * @param string $uniqueId
 *
 * @return string
 */
function getTransactionTerminalByUniqueId($uniqueId)
{
    $results = Db::getInstance()->getRow('SELECT terminal_name FROM `' . _DB_PREFIX_ . 'altapay_transaction` 
    WHERE unique_id=\'' . pSQL($uniqueId) . '\'');

    return $results['terminal_name'];
}

/**
 * Calculate checksum for AltaPay request
 *
 * @param array $input_data
 * @param string $shared_secret
 *
 * @return string
 */
function calculateChecksum($input_data, $shared_secret)
{
    $checksum_data = [
        'amount' => $input_data['amount'],
        'currency' => $input_data['currency'],
        'shop_orderid' => $input_data['shop_orderid'],
        'secret' => $shared_secret,
    ];

    ksort($checksum_data);
    $data = [];
    foreach ($checksum_data as $name => $value) {
        $data[] = $name . '=' . $value;
    }

    return md5(join(',', $data));
}

/**
 * Saves the reconciliation details for a given order
 *
 * @param object $response
 * @param object $order
 *
 * @return void
 */
function saveReconciliationDetails($response, $order)
{
    if (!empty($response) && !empty($response->Transactions)) {
        $latestTransKey = $max_date = 0;
        foreach ($response->Transactions as $key => $transaction) {
            if ($transaction->AuthType === 'subscription_payment' && $transaction->CreatedDate > $max_date) {
                $max_date = $transaction->CreatedDate;
                $latestTransKey = $key;
            }
        }
        $transaction = $response->Transactions[$latestTransKey];
        if (!empty($transaction->ReconciliationIdentifiers)) {
            foreach ($transaction->ReconciliationIdentifiers as $reconciliationIdentifier) {
                $reconciliation_identifier = $reconciliationIdentifier->Id;
                $reconciliation_type = $reconciliationIdentifier->Type;
                saveOrderReconciliationIdentifierIfNotExists($order->id, $reconciliation_identifier, $reconciliation_type, $response->shopOrderId);
            }
        }
    }
}

/**
 * Get AltaPay callback data
 *
 * @return array
 */
function getAltaPayCallbackData()
{
    if (version_compare(_PS_VERSION_, '1.6.1.24', '>=')) {
        return Tools::getAllValues();
    }

    $postData = [];

    $postData['shop_orderid'] = Tools::getValue('shop_orderid');
    $postData['currency'] = Tools::getValue('currency');
    $postData['type'] = Tools::getValue('type');
    $postData['embedded_window'] = Tools::getValue('embedded_window');
    $postData['amount'] = Tools::getValue('amount');
    $postData['transaction_id'] = Tools::getValue('transaction_id');
    $postData['payment_id'] = Tools::getValue('payment_id');
    $postData['nature'] = Tools::getValue('nature');
    $postData['require_capture'] = Tools::getValue('require_capture');
    $postData['payment_status'] = Tools::getValue('payment_status');
    $postData['masked_credit_card'] = Tools::getValue('masked_credit_card');
    $postData['credit_card_masked_pan'] = Tools::getValue('credit_card_masked_pan');
    $postData['blacklist_token'] = Tools::getValue('blacklist_token');
    $postData['credit_card_token'] = Tools::getValue('credit_card_token');
    $postData['status'] = Tools::getValue('status');
    $postData['checksum'] = Tools::getValue('checksum');
    $postData['cardholder_message_must_be_shown'] = Tools::getValue('cardholder_message_must_be_shown');
    $postData['merchant_error_message'] = Tools::getValue('merchant_error_message');
    $postData['error_message'] = Tools::getValue('error_message');
    $postData['xml'] = Tools::getValue('xml');

    return $postData;
}

/**
 * @param $lockFileName
 *
 * @return false|mixed|resource|void
 */
function lockCallback($lockFileName)
{
    $maxRetries = 10; // Maximum number of retry attempts
    $retryDelay = 1000000; // 1-second delay between retries (in microseconds)

    // Attempt to acquire the lock with retry mechanism
    $lockAcquired = false;
    $retryCount = 0;

    while (!$lockAcquired && $retryCount < $maxRetries) {
        // Attempt to acquire an exclusive lock on the lock file
        $fileHandle = @fopen($lockFileName, 'w');

        if ($fileHandle !== false) {
            // Attempt to acquire an exclusive lock on the file
            $lockAcquired = flock($fileHandle, LOCK_EX | LOCK_NB);

            if (!$lockAcquired) {
                // Failed to acquire lock, release the file handle and retry
                fclose($fileHandle);
                usleep($retryDelay);
                ++$retryCount;
            }
        } else {
            // Lock file creation failed, wait and retry
            usleep($retryDelay);
            ++$retryCount;
        }
    }

    if (!$lockAcquired) {
        // Lock acquisition failed after maximum retries, handle appropriately
        $message = 'Unable to acquire lock after maximum retries';
        $module = Module::getInstanceByName('altapay');
        PrestaShopLogger::addLog($message, 3, '1004', $module->name, $module->id, true);
        exit($message);
    }

    // Return the lock filename along with the file handle
    return $fileHandle;
}

/**
 * @param $lockFileName
 * @param $fileHandle
 *
 * @return void
 */
function unlockCallback($lockFileName, $fileHandle)
{
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);

    // Delete the lock file
    if (file_exists($lockFileName)) {
        unlink($lockFileName);
    }
}

/**
 * @param $order_id
 * @param $transaction_id
 * @param $amount
 * @param $shopOrderId
 *
 * @return \API\PHP\Altapay\Response\AbstractResponse|\API\PHP\Altapay\Response\Embeds\Transaction[]|string
 */
function capturePayment($order_id, $transaction_id, $amount, $shopOrderId)
{
    $reconciliation_identifier = sha1($transaction_id . time());
    $api = new API\PHP\Altapay\Api\Payments\CaptureReservation(getAuth());
    $api->setTransaction($transaction_id);
    $api->setAmount($amount);
    $api->setReconciliationIdentifier($reconciliation_identifier);
    $response = $api->call();
    saveOrderReconciliationIdentifier($order_id, $reconciliation_identifier, $shopOrderId);

    return $response;
}

/**
 * @return void
 */
function redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle)
{
    /* Redirect user back to the checkout payment step,
    * assume a failure occurred creating the URL until a payment url is received
    */
    $context = Context::getContext();
    $controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
    $as = $context->link;
    $con = $controller;
    $redirect = $as->getPageLink($con, true, null, 'step=3&altapay_unavailable=1') . '#altapay_unavailable';
    unlockCallback($lockFileName, $lockFileHandle);
    Tools::redirect($redirect);
}

/**
 * @param $response
 * @param $currencyPaid
 * @param $cart
 * @param $orderStatus
 *
 * @return mixed
 */
function createOrder($response, $currencyPaid, $cart, $orderStatus)
{
    $module = Module::getInstanceByName('altapay');
    // Determine payment method for display
    $paymentMethod = determinePaymentMethodForDisplay($response);
    // Create an order with 'payment accepted' status
    $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
    $cartID = $cart->id;

    // Load the customer
    $customer = new Customer((int) $cart->id_customer);
    $customerSecureKey = $customer->secure_key;

    $module->validateOrder($cartID, $orderStatus, $amountPaid,
        $paymentMethod, null, null,
        (int) $currencyPaid, false, $customerSecureKey);

    return $module;
}

/**
 * @param $message
 *
 * @return void
 */
function saveLogs($message)
{
    // Log message and return payment status
    $module = Module::getInstanceByName('altapay');
    PrestaShopLogger::addLog($message, 3, '1004', $module->name, $module->id, true);
    $responseMessage = ($message !== '') ? $message : $module->l('This payment method is not available 1004.', 'callbackok');
    echo $module->l($responseMessage, 'callbackOk');
}

/**
 * @param $cart
 * @param $order
 * @param $response
 * @param $shopOrderId
 * @param $lockFileName
 * @param $lockFileHandle
 *
 * @return void
 */
function updateOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle)
{
    $module = Module::getInstanceByName('altapay');
    if ($response && is_array($response->Transactions)) {
        $transactionStatus = $response->Transactions[0]->TransactionStatus;
    }
    $auth_statuses = ['preauth', 'invoice_initialized', 'recurring_confirmed'];
    $captured_statuses = ['bank_payment_finalized', 'captured'];
    if (in_array($transactionStatus, $auth_statuses, true) or in_array($transactionStatus, $captured_statuses, true)) {
        /*
         * preauth occurs for wallet transactions where payment type is 'payment'.
         * Funds are still waiting to be captured.
         * For this scenario we change the order status to 'payment accepted'.
         * bank_payment_finalized is for ePayments.
         */
        $order_state = (int) Configuration::get('authorized_payments_status');
        if (empty($order_state)) {
            $order_state = (int) Configuration::get('PS_OS_PAYMENT');
        }
        if (in_array($transactionStatus, $captured_statuses, true)) {
            $order_state = (int) Configuration::get('PS_OS_PAYMENT');
        }
        setOrderStateIfNotExistInHistory($order, $order_state);
        // Update payment status to 'succeeded'
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
        SET `paymentStatus` = \'succeeded\' WHERE `id_order` = ' . (int) $order->id;
        Db::getInstance()->Execute($sql);

        if (!empty($response->Transactions[0]->ReconciliationIdentifiers)) {
            $reconciliation_identifier = $response->Transactions[0]->ReconciliationIdentifiers[0]->Id;
            $reconciliation_type = $response->Transactions[0]->ReconciliationIdentifiers[0]->Type;

            saveOrderReconciliationIdentifierIfNotExists($order->id, $reconciliation_identifier, $reconciliation_type, $shopOrderId);
        }
        $customer = new Customer($cart->id_customer);
        unlockCallback($lockFileName, $lockFileHandle);
        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key);
    } elseif ($transactionStatus === 'epayment_declined') {
        // Update payment status to 'declined'
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_order` 
            SET `paymentStatus` = \'declined\' WHERE `id_order` = ' . (int) $order->id;
        Db::getInstance()->Execute($sql);
        unlockCallback($lockFileName, $lockFileHandle);
        exit('Order status updated to Error');
    } else {
        // Unexpected scenario
        $mNa = $module->name;
        PrestaShopLogger::addLog('Unexpected scenario: Callback notification was received for Transaction '
            . $shopOrderId . ' with payment status ' . $transactionStatus, 3, '1005', $mNa,
            $module->id, true);
        unlockCallback($lockFileName, $lockFileHandle);
        exit('Unrecognized status received ' . $transactionStatus);
    }
}

/**
 * @param $cart
 * @param $order
 * @param $response
 * @param $shopOrderId
 * @param $lockFileName
 * @param $lockFileHandle
 *
 * @return void
 */
function updateChildOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle)
{
    $module = Module::getInstanceByName('altapay');
    if ($response && is_array($response->Transactions)) {
        $transactionStatus = $response->Transactions[0]->TransactionStatus;
    }
    $auth_statuses = ['preauth', 'invoice_initialized', 'recurring_confirmed'];
    $captured_statuses = ['bank_payment_finalized', 'captured'];
    if (in_array($transactionStatus, $auth_statuses, true) or in_array($transactionStatus, $captured_statuses, true)) {
        // Update payment status to 'succeeded'
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_child_order` 
        SET `paymentStatus` = \'succeeded\' WHERE `unique_id` = \'' . pSQL($shopOrderId) . "'";
        Db::getInstance()->Execute($sql);

        if (!empty($response->Transactions[0]->ReconciliationIdentifiers)) {
            $reconciliation_identifier = $response->Transactions[0]->ReconciliationIdentifiers[0]->Id;
            $reconciliation_type = $response->Transactions[0]->ReconciliationIdentifiers[0]->Type;

            saveChildOrderIdentifier($order->id, $reconciliation_identifier, $reconciliation_type, $shopOrderId);
        }
        unlockCallback($lockFileName, $lockFileHandle);
        // Check if an order exist, update it and redirect to success
        $redirectUrl = Context::getContext()->link->getModuleLink('altapay', 'orderconfirmation', ['id_order' => $order->id]);

        Tools::redirect($redirectUrl);
    } elseif ($transactionStatus === 'epayment_declined') {
        // Update payment status to 'declined'
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_child_order` 
            SET `paymentStatus` = \'declined\' WHERE `unique_id` = \'' . pSQL($shopOrderId) . "'";
        Db::getInstance()->Execute($sql);
        unlockCallback($lockFileName, $lockFileHandle);
        exit('Order status updated to Error');
    } else {
        // Unexpected scenario
        $mNa = $module->name;
        PrestaShopLogger::addLog('Unexpected scenario: Callback notification was received for Transaction '
            . $shopOrderId . ' with payment status ' . $transactionStatus, 3, '1005', $mNa,
            $module->id, true);
        unlockCallback($lockFileName, $lockFileHandle);
        exit('Unrecognized status received ' . $transactionStatus);
    }
}

/**
 * @param $shopOrderId
 * @param $transaction
 * @param $ccToken
 * @param $maskedPan
 * @param $customerID
 * @param $cart
 * @param $agreementType
 *
 * @return void
 */
function handleVerifyCard(
    $shopOrderId,
    $transaction,
    $ccToken,
    $maskedPan,
    $customerID,
    $cart,
    $agreementType
) {
    $module = Module::getInstanceByName('altapay');
    $message = '';
    $expires = '';
    $cardType = '';
    $transactionID = $transaction->TransactionId;
    $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
    if (isset($transaction->CapturedAmount)) {
        $amountPaid = $transaction->CapturedAmount;
    }
    if (isset($transaction->CreditCardExpiry->Month)
        && isset($transaction->CreditCardExpiry->Year)
    ) {
        $expires = $transaction->CreditCardExpiry->Month . '/'
            . $transaction->CreditCardExpiry->Year;
    }
    if (isset($transaction->PaymentSchemeName)) {
        $cardType = $transaction->PaymentSchemeName;
    }
    $currencyPaid = new Currency($cart->id_currency);
    $sql = 'INSERT INTO `' . _DB_PREFIX_
        . 'altapay_saved_credit_card` (time,userID,agreement_id,agreement_type,cardBrand,creditCardNumber,cardExpiryDate,ccToken) VALUES (Now(),'
        . pSQL($customerID) . ',"' . pSQL($transactionID) . '","'
        . pSQL($agreementType) . '","' . pSQL($cardType) . '","'
        . pSQL($maskedPan) . '","' . pSQL($expires) . '","' . pSQL($ccToken)
        . '")';
    Db::getInstance()->executeS($sql);

    $request = new API\PHP\Altapay\Api\Payments\ReservationOfFixedAmount(getAuth());
    $request->setCreditCardToken($transaction->CreditCardToken)
        ->setTerminal($transaction->Terminal)
        ->setShopOrderId($shopOrderId)
        ->setAmount($amountPaid)
        ->setCurrency($currencyPaid->iso_code)
        ->setAgreement([
            'id' => $transactionID,
            'type' => 'unscheduled',
            'unscheduled_type' => 'incremental',
        ]);
    try {
        $response = $request->call();
    } catch (API\PHP\Altapay\Exceptions\ClientException $e) {
        $message = $e->getResponse()->getBody();
    } catch (API\PHP\Altapay\Exceptions\ResponseHeaderException $e) {
        $message = $e->getHeader()->ErrorMessage;
    } catch (API\PHP\Altapay\Exceptions\ResponseMessageException $e) {
        $message = $e->getMessage();
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
    PrestaShopLogger::addLog('Callback OK issue, Message ' . $message,
        3,
        '1005',
        $module->name,
        $module->id,
        true
    );
}

/**
 * Use the cart ID to determine unique ID / shop_orderid
 *
 * @param int $id_cart
 *
 * @return mixed
 */
function getLatestUniqueIdFromCartId($id_cart)
{
    $sql = 'SELECT unique_id 
    FROM `' . _DB_PREFIX_ . 'altapay_transaction` 
    WHERE id_cart=\'' . pSQL($id_cart) . '\' 
    ORDER BY CAST(date_add AS UNSIGNED) DESC';

    return Db::getInstance()->getValue($sql);
}

/**
 * @param $id_cart
 * @param $unique_id
 *
 * @return mixed
 */
function getPaymentFormUrl($id_cart, $unique_id)
{
    $sql = 'SELECT unique_id, payment_form_url, amount
            FROM `' . _DB_PREFIX_ . 'altapay_transaction` 
            WHERE id_cart = \'' . pSQL($id_cart) . '\' 
            AND unique_id LIKE \'' . pSQL($unique_id) . "%\_%' 
            ORDER BY CAST(date_add AS UNSIGNED) DESC";

    return Db::getInstance()->getRow($sql);
}

/**
 * Updates order state only if it does not exist in order history
 *
 * @param $order
 * @param $order_state
 *
 * @return void
 */
function setOrderStateIfNotExistInHistory($order, $order_state)
{
    if (empty($order->getHistory($order->id_lang, $order_state))) {
        $order->setCurrentState($order_state);
    }
}

/**
 * Refunds or Releases a transaction on gateway, based on its status
 *
 * @param $transaction
 *
 * @return void
 */
function refundOrReleaseTransactionByStatus($transaction)
{
    if (in_array($transaction->TransactionStatus, ['captured', 'bank_payment_finalized'], true)) {
        $api = new API\PHP\Altapay\Api\Payments\RefundCapturedReservation(getAuth());
    } else {
        $api = new API\PHP\Altapay\Api\Payments\ReleaseReservation(getAuth());
    }
    $api->setTransaction($transaction->TransactionId);
    $api->call();
}

/**
 * Get the terminal ID based on the remote name and shop ID.
 *
 * @param string $remote_name
 * @param int $shop_id
 *
 * @return int|string
 */
function getTerminalIdByRemoteName($remote_name, $shop_id = 1)
{
    $terminal_id = '';
    try {
        if (filter_var($shop_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new Exception('Invalid shop id');
        }
        $query = 'SELECT id_terminal FROM `' . _DB_PREFIX_ . "altapay_terminals` WHERE active = 1 AND remote_name = '" . pSQL($remote_name) . "' AND shop_id = '" . $shop_id . "' ";

        $result = Db::getInstance()->getRow($query);
        $terminal_id = $result['id_terminal'];
    } catch (Exception $e) {
        $context = Context::getContext();
        if (isset($context->controller) && isset($context->controller->errors)) {
            $context->controller->errors[] = $e->getMessage();
        }
        PrestaShopLogger::addLog($e->getMessage(), 4);
    }

    return $terminal_id;
}

/**
 * Save transaction data to the database.
 *
 * @param array $result
 * @param string $payment_form_url
 * @param int $cartId
 * @param string $terminalName
 *
 * @return void
 */
function saveTransactionData($result, $payment_form_url, $cartId, $terminalName)
{
    if (filter_var($cartId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        return false;
    }

    $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_transaction`
				(id_cart, payment_form_url, unique_id, amount, terminal_name, date_add, token) VALUES ' .
        "('" . (int) $cartId . "', '" . pSQL($payment_form_url) . "', '" . pSQL($result['uniqueid']) . "', '"
        . pSQL($result['amount']) . "', '" . pSQL($terminalName) . "' , '" . pSQL(time()) . "', '')" .
        ' ON DUPLICATE KEY UPDATE `amount` = ' . pSQL($result['amount']);

    Db::getInstance()->Execute($sql);
}

/**
 * Get the transaction status (captured and refunded amounts) for the given payment ID.
 *
 * @param string $paymentId
 *
 * @return array|null
 */
function getTransactionStatus($paymentId)
{
    $sql = 'SELECT captured, refunded 
            FROM ' . _DB_PREFIX_ . 'altapay_orderlines 
            WHERE altapay_payment_id = "' . pSQL($paymentId) . '"';

    return Db::getInstance()->getRow($sql);
}

/**
 * Check if the given shop order ID represents a child order.
 *
 * @param string $shopOrderId
 *
 * @return bool
 */
function isChildOrder($shopOrderId)
{
    return strpos($shopOrderId, '_') !== false;
}

/**
 * @param $postData
 * @param $record_id
 *
 * @return void
 */
function createOrderOkCallback($postData, $record_id = null)
{
    // Create lock file name based on transaction_id so that it locks creation of current order only.
    // Locking prevents attempt to create order in PrestaShop if notification & ok callbacks get processed simultaneously.

    $lockFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'callback_lock_' . md5($postData['transaction_id']) . '.lock';
    $lockFileHandle = lockCallback($lockFileName);
    $message = '';
    $module = Module::getInstanceByName('altapay');
    try {
        // Load the cart
        $cart = getCartFromUniqueId($postData['shop_orderid']);
        if (!Validate::isLoadedObject($cart)) {
            markAltaPayCallbackRecord($record_id, 2);
            exit('Could not load cart - exiting');
        }
        $agreementType = 'unscheduled';
        $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
        $response = $callback->call();
        $shopOrderId = $response->shopOrderId;
        $isChildOrder = isChildOrder($shopOrderId);
        $paymentType = $response->type;
        $transaction = getTransaction($response);
        $orderStatus = (int) Configuration::get('authorized_payments_status');
        if (empty($orderStatus) or in_array($transaction->TransactionStatus, ['bank_payment_finalized', 'captured'], true)) {
            $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
        }

        $currencyPaid = Currency::getIdByIsoCode($transaction->MerchantCurrencyAlpha);
        $amountPaid = $isChildOrder ? $postData['amount'] : $cart->getOrderTotal(true, Cart::BOTH);
        $customer = new Customer($cart->id_customer);
        $transactionID = $transaction->TransactionId;
        $ccToken = $response->creditCardToken;
        $maskedPan = $response->maskedCreditCard;
        if (!$isChildOrder) {
            $payment_module = createOrder($response, $currencyPaid, $cart, $orderStatus);
            // Load order
            $order = new Order((int) $payment_module->currentOrder);
        } else {
            $order_id = Order::getOrderByCartId((int) ($cart->id));
            $order = new Order((int) $order_id);
            if (in_array($transaction->TransactionStatus, ['bank_payment_finalized', 'captured'], true)) {
                markChildOrderAsCaptured($transactionID);
            }
        }

        if (!Validate::isLoadedObject($order)) {
            markAltaPayCallbackRecord($record_id, 2);
            saveLogs('Something went wrong');
            redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle);
        }

        if (!empty($transaction->ReconciliationIdentifiers)) {
            $reconciliation_identifier = $transaction->ReconciliationIdentifiers[0]->Id;
            $reconciliation_type = $transaction->ReconciliationIdentifiers[0]->Type;
            saveOrderReconciliationIdentifier($order->id, $reconciliation_identifier, $shopOrderId, $reconciliation_type);
        }
        if ($paymentType === 'paymentAndCapture' && $response->requireCapture === true) {
            $response = capturePayment($order->id, $transactionID, $amountPaid, $shopOrderId);
            $orderStatusCaptured = (int) Configuration::get('PS_OS_PAYMENT');
            if ($orderStatusCaptured != $orderStatus && !$isChildOrder) {
                setOrderStateIfNotExistInHistory($order, $orderStatusCaptured);
            }
        }

        if ($paymentType === 'verifyCard') {
            handleVerifyCard($shopOrderId, $transaction, $ccToken, $maskedPan, $cart->id_customer, $cart, $agreementType);
        }
        if (in_array($paymentType, ['subscription', 'subscriptionAndCharge'])) {
            $sql = 'INSERT INTO `' . _DB_PREFIX_
                . 'altapay_saved_credit_card` (time,userID,agreement_id,agreement_type,id_order) VALUES (Now(),'
                . pSQL($cart->id_customer) . ',"' . pSQL($transactionID) . '","'
                . pSQL('recurring') . '","' . pSQL($order->id)
                . '")';
            Db::getInstance()->executeS($sql);
        }

        // Log order
        createAltapayOrder($response, $order, 'succeeded', $isChildOrder);
        unlockCallback($lockFileName, $lockFileHandle);
        markAltaPayCallbackRecord($record_id);
        if ($isChildOrder) {
            $redirectUrl = Context::getContext()->link->getModuleLink('altapay', 'orderconfirmation', ['id_order' => $order->id]);
        } else {
            $redirectUrl = Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key);
        }

        Tools::redirect($redirectUrl);
    } catch (API\PHP\Altapay\Exceptions\ClientException $e) {
        $message = $e->getResponse()->getBody();
    } catch (API\PHP\Altapay\Exceptions\ResponseHeaderException $e) {
        $message = $e->getHeader()->ErrorMessage;
    } catch (API\PHP\Altapay\Exceptions\ResponseMessageException $e) {
        $message = $e->getMessage();
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
    markAltaPayCallbackRecord($record_id, 2);
    saveLogs($message);
    redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle);
}

/**
 * @param array $postData
 * @param string $callback_type
 *
 * @return false|int
 */
function saveAltaPayCallbackRequest($postData, $callback_type = 'ok')
{
    $xml = $postData['xml'];
    // Encode the XML data
    $xmlEncoded = base64_encode($xml);
    // Replace with encoded xml in POST array
    $postData['xml'] = $xmlEncoded;
    $jsonPostData = json_encode($postData);

    $sql = 'INSERT INTO `' . _DB_PREFIX_
        . "altapay_callback_requests` (shop_orderid, transaction_id, request_data, callback_type) VALUES (
        '" . pSQL($postData['shop_orderid']) . "', '" . pSQL($postData['transaction_id']) . "', '" . pSQL($jsonPostData) . "', '" . pSQL($callback_type) . "')";
    if (Db::getInstance()->execute($sql)) {
        return (int) Db::getInstance()->Insert_ID();
    }

    return false;
}

/**
 * @param $record_id
 * @param int $status
 *
 * @return bool
 */
function markAltaPayCallbackRecord($record_id, $status = 1)
{
    if (!empty($record_id)) {
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'altapay_callback_requests` SET `processing_status` = ' . (int) $status . ' WHERE `id` = ' . (int) $record_id . ' LIMIT 1';

        return Db::getInstance()->execute($sql);
    }

    return false;
}

/** Sends a non-blocking POST request to a specified URL.
 *
 * This function sends a POST request to the given URL with the provided data.
 * It ensures the request is sent and received by the remote server without
 * waiting for a response.
 *
 * @param string $url the URL to send the POST request to
 * @param array $data The data to be sent in the POST request. This should be an associative array.
 */
function sendAsyncPostRequest($url, $data)
{
    // Initialize cURL session
    $ch = curl_init();

    // Configure cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);  // Connection timeout in seconds
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);  // Request timeout in seconds

    // Execute the cURL request
    curl_exec($ch);

    // Close the cURL session
    curl_close($ch);
}

/**
 * Get Terminal logo based on payment method identifier
 *
 * @param string $identifier
 *
 * @return string
 */
function getPaymentMethodIcon($identifier = '')
{
    $defaultValue = ' ';

    $paymentMethodIcons = [
        'ApplePay' => 'apple_pay.png',
        'Bancontact' => 'bancontact.png',
        'BankPayment' => 'bank.png',
        'CreditCard' => 'creditcard.png',
        'iDeal' => 'ideal.png',
        'Invoice' => 'invoice.png',
        'Klarna' => 'klarna_pink.png',
        'MobilePay' => 'mobilepay.png',
        'OpenBanking' => 'bank.png',
        'Payconiq' => 'payconiq.png',
        'PayPal' => 'paypal.png',
        'Przelewy24' => 'przelewy24.png',
        'Sepa' => 'sepa.png',
        'SwishSweden' => 'swish.png',
        'Trustly' => 'trustly_primary.png',
        'Twint' => 'twint.png',
        'ViaBill' => 'viabill.png',
        'Vipps' => 'vipps.png',
    ];

    if (isset($paymentMethodIcons[$identifier])) {
        return $paymentMethodIcons[$identifier];
    }

    return $defaultValue;
}
