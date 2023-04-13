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
    $pluginVersion = '3.4.9';

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
    $cart = new Cart((int) $results['id_cart']);

    return $cart;
}

/**
 * Use the unique ID to fetch the created from it (if any)
 *
 * @param int $uniqueId
 *
 * @return Order
 */
function getOrderFromUniqueId($uniqueId)
{
    $results = Db::getInstance()->getRow('SELECT id_order 
    FROM `' . _DB_PREFIX_ . 'altapay_order` 
    WHERE unique_id = \'' . $uniqueId . '\'');
    $order = new Order((int) $results['id_order']);

    return $order;
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
                                            . pSQL($paymentId) . '" AND product_id = ' . pSQL($productId));

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
                                    . pSQL($paymentId) . '" AND product_id = ' . pSQL($productId);
        $result = Db::getInstance()->getRow($sqlGetRefundedFieldValue);
        if (isset($result['refunded'])) {
            $quantity += $result['refunded'];
            // If the amount of refunded items is bigger than the actual captured amount than set the max amount
            if ($quantity > $result['captured']) {
                $quantity = $result['captured'];
            }

            // Update only of there is a capture for this product
            $sql = 'UPDATE ' . _DB_PREFIX_ . 'altapay_orderlines SET refunded = '
                   . $quantity . " WHERE altapay_payment_id = '" . pSQL($paymentId) . "' AND product_id = " . pSQL($productId);
            Db::getInstance()->Execute($sql);
        } else {
            // Product which have not been captured cannot be refunded
            continue;
        }
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
 * Method for creating orders at prestashop backend
 *
 * @param AltapayCallbackHandler $response
 * @param Order $current_order
 * @param string $payment_status
 *
 * @return void
 */
function createAltapayOrder($response, $current_order, $payment_status = 'succeeded')
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
        $cardExpiryMonth = $transaction->CreditCardExpiry->Month;
        $cardExpiryYear = $transaction->CreditCardExpiry->Year;
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
        $payment = $current_order->getOrderPaymentCollection();
        if (isset($payment[0])) {
            $payment[0]->transaction_id = pSQL($uniqueId);
            $payment[0]->card_number = pSQL($cardMask);
            $payment[0]->card_brand = pSQL($cardBrand);
            $payment[0]->save();
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
 * Retrieve the ID of a terminal based on its remote name and shop ID.
 *
 * @param string $terminalRemoteName
 * @param int $shop_id
 *
 * @return array|void
 */
function getTerminalId($terminalRemoteName, $shop_id = 1)
{
    try {
        if (filter_var($shop_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new Exception('Invalid shop id');
        }
        $query = 'SELECT id_terminal, shop_id FROM `' . _DB_PREFIX_ . 'altapay_terminals` WHERE `remote_name`="' . pSQL($terminalRemoteName) . '" AND `shop_id` = "' . (int) $shop_id . '"';
        $result = Db::getInstance()->executeS($query);

        return $result;
    } catch (Exception $e) {
        $context = Context::getContext();
        if (isset($context->controller) && isset($context->controller->errors)) {
            $context->controller->errors[] = $e->getMessage();
        }
        PrestaShopLogger::addLog($e->getMessage(), 4);
    }
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
        if (isset($context->controller) && isset($context->controller->errors)) {
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
 * @return void
 */
function saveOrderReconciliationIdentifier($orderID, $reconciliation_identifier, $type = 'captured')
{
    Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'altapay_order_reconciliation`
		(id_order, reconciliation_identifier, transaction_type) 
        VALUES ' . "('" . (int) $orderID . "', '" . pSQL($reconciliation_identifier) . "', '" . pSQL($type) . "')");
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
function saveOrderReconciliationIdentifierIfNotExists($orderID, $reconciliation_identifier, $type)
{
    $sql = 'SELECT id FROM `' . _DB_PREFIX_ . 'altapay_order_reconciliation` WHERE id_order =' . (int) $orderID .
        " AND reconciliation_identifier ='" . pSQL($reconciliation_identifier) .
        "' AND transaction_type ='" . pSQL($type) . "'";

    if (!Db::getInstance()->getRow($sql)) {
        saveOrderReconciliationIdentifier($orderID, $reconciliation_identifier, $type);
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
                saveAltaPayTransaction($uniqueId, $transaction->CapturedAmount, $transaction->Terminal);
                saveOrderReconciliationIdentifier($order_id, $reconciliation_identifier);
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
  *
  * @return void
  */
 function saveAltaPayTransaction($unique_id, $amount, $terminal)
 {
     $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_transaction` 
				(id_cart, payment_form_url, token, unique_id, amount, terminal_name, date_add) VALUES ' .
        "('', '', '', '" . pSQL($unique_id) . "', '" . pSQL($amount) . "', '" . pSQL($terminal) . "' ,
             '" . pSQL(time()) . "')" . ' ON DUPLICATE KEY UPDATE `amount` = ' . pSQL($amount);

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
 * @param array $order
 * @param string $fraudStatus
 * @param string $fraudMsg
 * @param int $transactionId
 *
 * @return void
 */
function fraudPayment(
    $order,
    $fraudStatus,
    $fraudMsg,
    $transactionId,
    $transactionStatus
) {
    $fraudConfig = Tools::getValue('enable_fraud', Configuration::get('enable_fraud'));
    $enableReleaseRefund = Tools::getValue('enable_release_refund', Configuration::get('enable_release_refund'));
    // Create a new order state object for the "Canceled" state
    $canceled_state = new OrderState((int)Configuration::get('PS_OS_CANCELED'));
    // Update the order state to the "Canceled" state
    $order->setCurrentState($canceled_state->id);
    // Save the changes to the order
    $order->save();
    
    try {
        if ($transactionStatus === "captured") {
            $api = new API\PHP\Altapay\Api\Payments\RefundCapturedReservation(getAuth());
        } else {
            $api = new API\PHP\Altapay\Api\Payments\ReleaseReservation(getAuth());
        }
        $api->setTransaction($transactionId);
        $api->call();
        saveLastErrorMessage($transactionId, $fraudMsg);
        updatePaymentStatus($transactionId, $fraudStatus);
    } catch (Exception $e) {
        saveLastErrorMessage($transactionId, $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Could not release reservation. ' . $e->getMessage(),
        ]);
        exit();
    }

}

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