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
    $pluginVersion = '3.3.1';

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
 * @return array<string>
 */
function determinePaymentMethodForDisplay($response)
{
    $paymentNature = $response->getPrimaryPayment()->getPaymentNature();

    if ($paymentNature === 'Wallet') {
        return $response->getPrimaryPayment()->getPaymentSchemeName();
    }
    if ($paymentNature === 'CreditCard') {
        return $paymentNature;
    }
    if ($paymentNature === 'CreditCardWallet') {
        return $response->getPrimaryPayment()->getPaymentSchemeName();
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
    WHERE unique_id=\'' . $uniqueId . '\'');
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
    $sql = 'UPDATE ' . _DB_PREFIX_ . 'altapay_order SET requireCapture = 0 WHERE payment_id = ' . $paymentId
           . ' LIMIT 1';
    Db::getInstance()->Execute($sql);

    foreach ($orderlines as $productId => $quantity) {
        if ($quantity == 0) {
            continue;
        }

        $result = Db::getInstance()->getRow('SELECT captured 
            FROM ' . _DB_PREFIX_ . 'altapay_orderlines WHERE altapay_payment_id = "'
                                            . $paymentId . '" AND product_id = ' . $productId);

        if (isset($result['captured'])) {
            $quantity += $result['captured'];
            $sqlUpdateCapture = 'UPDATE ' . _DB_PREFIX_ .
                                'altapay_orderlines SET captured = ' . $quantity .
                                ' WHERE altapay_payment_id = ' . $paymentId;
            Db::getInstance()->Execute($sqlUpdateCapture);
        } else {
            $sqlOrderLine = 'INSERT INTO ' . _DB_PREFIX_ .
                            'altapay_orderlines (altapay_payment_id, product_id, captured) 
                VALUES("' . $paymentId . '", "' . $productId . '", ' . $quantity . ')';
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
    FROM ' . _DB_PREFIX_ . 'altapay_order WHERE payment_id = ' . $paymentId;
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
                                    . $paymentId . '" AND product_id = ' . $productId;
        $result = Db::getInstance()->getRow($sqlGetRefundedFieldValue);
        if (isset($result['refunded'])) {
            $quantity += $result['refunded'];
            // If the amount of refunded items is bigger than the actual captured amount than set the max amount
            if ($quantity > $result['captured']) {
                $quantity = $result['captured'];
            }

            // Update only of there is a capture for this product
            $sql = 'UPDATE ' . _DB_PREFIX_ . 'altapay_orderlines SET refunded = '
                   . $quantity . " WHERE altapay_payment_id = '" . $paymentId . "' AND product_id = " . $productId;
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
    ' . _DB_PREFIX_ . 'altapay_order SET latestError = \'' . $latestError . '\' WHERE payment_id='
           . $paymentId . ' LIMIT 1';
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
    ' . _DB_PREFIX_ . 'altapay_order SET paymentStatus = \'' . $paymentStatus . '\' WHERE payment_id='
           . $paymentId . ' LIMIT 1';
    Db::getInstance()->Execute($sql);
}

/**
 * Method for creating orders at prestashop backend
 *
 * @param AltapayCallbackHandler $response
 * @param array $current_order
 * @param string $payment_status
 *
 * @return void
 */
function createAltapayOrder($response, $current_order, $payment_status = 'succeeded')
{
    $uniqueId = $response->getPrimaryPayment()->getShopOrderId();
    $paymentId = $response->getPrimaryPayment()->getId();
    $cardMask = $response->getPrimaryPayment()->getMaskedPan();
    $cardToken = $response->getPrimaryPayment()->getCreditCardToken();
    $cardExpiryMonth = $response->getPrimaryPayment()->getCreditCardExpiryMonth();
    $cardExpiryYear = $response->getPrimaryPayment()->getCreditCardExpiryYear();
    $cardBrand = $response->getPrimaryPayment()->getPaymentSchemeName();
    $paymentType = $response->getPrimaryPayment()->getAuthType();
    $paymentTerminal = $response->getPrimaryPayment()->getTerminal();
    $paymentNature = $response->getPrimaryPayment()->getPaymentNature();
    $paymentStatus = $payment_status;
    $requireCapture = 0;
    if ($paymentType === 'payment') {
        $requireCapture = 1;
    }
    $cardExpiryDate = 0;
    if ($cardExpiryMonth && $cardExpiryYear) {
        $cardExpiryDate = $cardExpiryMonth . '/' . $cardExpiryYear;
    }

    $errorCode = null;
    $errorText = null;
    $customerInfo = $response->getPrimaryPayment()->getCustomerInfo();
    $cardCountry = $customerInfo->getCountryOfOrigin()->getCountry();
    //insert into order log
    $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_order`
		(id_order, unique_id, payment_id, cardMask, cardToken, cardBrand, cardExpiryDate, cardCountry, 
        paymentType, paymentTerminal, paymentStatus, paymentNature, requireCapture, errorCode, errorText, date_add) 
        VALUES ' .
           "('" . $current_order->id . "', '" . pSQL($uniqueId) . "', '"
           . pSQL($paymentId) . "', '" . pSQL($cardMask) . "', '"
           . pSQL($cardToken) . "', '" . pSQL($cardBrand) . "', '"
           . pSQL($cardExpiryDate) . "', '"
           . pSQL($cardCountry) . "', '" . pSQL($paymentType) . "', '"
           . pSQL($paymentTerminal) . "', '"
           . pSQL($paymentStatus) . "', '" . pSQL($paymentNature) . "', '"
           . pSQL($requireCapture) . "', '" . pSQL($errorCode) . "', '"
           . pSQL($errorText) . "', '" . time() . "')";
    Db::getInstance()->Execute($sql);

    if (Validate::isLoadedObject($current_order)) {
        $payment = $current_order->getOrderPaymentCollection();
        if (isset($payment[0])) {
            $payment[0]->transaction_id = pSQL($uniqueId);
            $payment[0]->card_number = pSQL($cardMask);
            $payment[0]->card_brand = pSQL($cardBrand);
            // $payment[0]->card_expiration = pSQL($cardExp);    //not provided
            $payment[0]->save();
        }
    }
}

/**
 * Method for conversion of date format
 *
 * @param string $date
 *
 * @return string
 *
 * @throws Exception
 */
function convertDateTimeFormat($date)
{
    $dateTime = new DateTime($date);

    return $dateTime->format('Y-m-d');
}

/**
 * Method for AltaPay api login
 *
 * @return string|AltapayMerchantAPI
 *
 * @throws Exception
 */
function apiLogin()
{
    $config = Configuration::getMultiple([
        'ALTAPAY_USERNAME',
        'ALTAPAY_PASSWORD',
        'ALTAPAY_URL',
    ]);

    $api = new AltapayMerchantAPI(
        $config['ALTAPAY_URL'],
        $config['ALTAPAY_USERNAME'],
        $config['ALTAPAY_PASSWORD'],
        null
    );
    try {
        $api->login();
    } catch (Exception $e) {
        return $e->getMessage();
    }

    return $api;
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
    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_order` WHERE id_order =' . $orderID;

    return Db::getInstance()->executeS($sql);
}

/**
 * Get terminal id based on terminal remote name
 *
 * @param string $terminalRemoteName
 *
 * @return array
 */
function getTerminalId($terminalRemoteName)
{
    $sql = 'SELECT id_terminal FROM `' . _DB_PREFIX_ . 'altapay_terminals` WHERE `remote_name`='
           . "'$terminalRemoteName'";

    return Db::getInstance()->executeS($sql);
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
