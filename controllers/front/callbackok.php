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
        $postData = getAltaPayCallbackData();
        $checksum = !empty($postData['checksum']) ? $postData['checksum'] : '';
        $terminal_name = getTransactionTerminalByUniqueId($postData['shop_orderid']);
        $secret = Altapay_Models_Terminal::getTerminalSecretByRemoteName($terminal_name);

        if (!empty($checksum) and !empty($secret) and calculateChecksum($postData, $secret) !== $checksum) {
            exit('Invalid request');
        }

        // Create lock file name based on transaction_id so that it locks creation of current order only.
        // Locking prevents attempt to create order in PrestaShop if notification & ok callbacks get processed simultaneously.

        $lockFileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'callback_lock_' . md5($postData['transaction_id']) . '.lock';
        $lockFileHandle = lockCallback($lockFileName);

        $message = '';
        $callback = new API\PHP\Altapay\Api\Ecommerce\Callback($postData);
        try {
            $response = $callback->call();
            $shopOrderId = $response->shopOrderId;
            $isChildOrder = isChildOrder($shopOrderId);
            $paymentType = $response->type;
            $transaction = getTransaction($response);

            // Load the cart
            $cart = getCartFromUniqueId($shopOrderId);
            if (!Validate::isLoadedObject($cart)) {
                unlockCallback($lockFileName, $lockFileHandle);
                exit('Could not load cart - exiting');
            }
            $amountPaid = $isChildOrder ? $response->amount : $cart->getOrderTotal(true, Cart::BOTH);
            $customer = new Customer($cart->id_customer);
            $transactionID = $transaction->TransactionId;
            $fraudPayment = handleFraudPayment($response, $transaction);
            //Check if this is a duplicate callback
            if (!empty($shopOrderId)) {
                $tableName = $isChildOrder ? 'altapay_child_order' : 'altapay_order';

                $condition = "unique_id = '" . pSQL($shopOrderId) . "' AND paymentStatus = 'succeeded'";
                $query = 'SELECT id_order FROM `' . _DB_PREFIX_ . $tableName . '` WHERE ' . $condition;
                $result = Db::getInstance()->executeS($query);
                // Check if the order already saved with the success status
                if (!empty($result)) {
                    // Check if an order exist
                    $order = new Order((int) $result[0]['id_order']);
                    if (Validate::isLoadedObject($order) and $paymentType === 'paymentAndCapture' and $response->requireCapture === true) {
                        $response = capturePayment($order->id, $transactionID, $amountPaid, $shopOrderId);
                        if ($isChildOrder) {
                            updateChildOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                        } else {
                            updateOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                        }
                    }
                    unlockCallback($lockFileName, $lockFileHandle);
                    if ($isChildOrder) {
                        $redirectUrl = Context::getContext()->link->getModuleLink('altapay', 'orderconfirmation', ['id_order' => $order->id]);
                    } else {
                        $redirectUrl = 'index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $order->id . '&key=' . $customer->secure_key;
                    }

                    Tools::redirect($redirectUrl);
                }
            }

            // Check if this is a duplicate payment
            $order_id = Order::getOrderByCartId((int) ($cart->id));
            if (!empty($order_id)) {
                $altapay_order_details = $isChildOrder ? getAltapayChildOrderDetails($order_id) : getAltapayOrderDetails($order_id);
                if (!empty($altapay_order_details)
                    and $altapay_order_details[0]['paymentStatus'] === 'succeeded'
                    and $altapay_order_details[0]['payment_id'] != $transactionID
                    and $postData['status'] === 'succeeded') {
                    // Refund or Release incoming payment request
                    refundOrReleaseTransactionByStatus($transaction);
                    unlockCallback($lockFileName, $lockFileHandle);
                    if ($isChildOrder) {
                        $redirectUrl = Context::getContext()->link->getModuleLink('altapay', 'orderconfirmation', ['id_order' => $order_id]);
                    } else {
                        $redirectUrl = 'index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $order_id . '&key=' . $customer->secure_key;
                    }

                    Tools::redirect($redirectUrl);
                }
            }

            // Redirect to payment selection page
            if ($fraudPayment['payment_status']) {
                saveLogs($transaction->FraudExplanation);
                redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle);
            }

            // Check if an order exist, update it and redirect to success
            $order = $isChildOrder ? getChildOrderFromUniqueId($shopOrderId) : getOrderFromUniqueId($shopOrderId);

            if (Validate::isLoadedObject($order)) {
                if ($isChildOrder) {
                    updateChildOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                } else {
                    updateOrder($cart, $order, $response, $shopOrderId, $lockFileName, $lockFileHandle);
                }
            }
            $record_id = false;
            if (!empty(Configuration::get('process_callbacks_async')) && !$isChildOrder) {
                $record_id = saveAltaPayCallbackRequest($postData);
            }
            unlockCallback($lockFileName, $lockFileHandle);
            if (!empty($record_id) && !$isChildOrder) {
                sendAsyncPostRequest($this->context->link->getModuleLink($this->module->name, 'asyncprocesscallbacksfromgateway'), ['id' => $record_id, 'shop_orderid' => $postData['shop_orderid'], 'transaction_id' => $postData['transaction_id']]);
                $redirectUrl = $this->context->link->getModuleLink('altapay', 'callbackopenvalidate', ['order_id' => $postData['shop_orderid']]);
                Tools::redirect($redirectUrl);
            }
            createOrderOkCallback($postData);
        } catch (API\PHP\Altapay\Exceptions\ClientException $e) {
            $message = $e->getResponse()->getBody();
        } catch (API\PHP\Altapay\Exceptions\ResponseHeaderException $e) {
            $message = $e->getHeader()->ErrorMessage;
        } catch (API\PHP\Altapay\Exceptions\ResponseMessageException $e) {
            $message = $e->getMessage();
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        saveLogs($message);
        redirectUserToCheckoutPaymentStep($lockFileName, $lockFileHandle);
    }
}
