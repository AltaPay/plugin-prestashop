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

            // Load order if it exist
            $orderId = Order::getOrderByCartId((int) ($cart->id));
            $order = new Order((int) ($orderId));

            // Handle success
            if ($response && is_array($response->Transactions) && Validate::isLoadedObject($order)) {
                $cardType = '';
                $expires = '';
                $amountPaid = 0;
                $transactionId = $response->transactionId;
                $paymentType = $response->type;
                $captureStatus = $response->requireCapture;
                $currencyPaid = Currency::getIdByIsoCode($response->currency);
                $transaction = $this->getTransaction($response);
                $customerID = $this->context->customer->id;
                $ccToken = $response->creditCardToken;
                $maskedPan = $response->maskedCreditCard;
                $agreementType = 'unscheduled';
                $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
                $message = '';
                if (isset($transaction->CapturedAmount)) {
                    $amountPaid = $transaction->CapturedAmount;
                }
                if (isset($transaction->CreditCardExpiry->Month) && isset($transaction->CreditCardExpiry->Year)) {
                    $expires = $transaction->CreditCardExpiry->Month . '/' . $transaction->CreditCardExpiry->Year;
                }
                if (isset($transaction->PaymentSchemeName)) {
                    $cardType = $transaction->PaymentSchemeName;
                }
                if ($paymentType === 'paymentAndCapture' && $captureStatus === true) {
                    $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
                    $currencyPaid = new Currency($cart->id_currency);
                    $api = new API\PHP\Altapay\Api\Payments\CaptureReservation(getAuth());
                    $api->setAmount($amountPaid);
                    $api->setTransaction($transactionId);
                    $api->call();
                }
                if ($paymentType === 'verifyCard') {
                    $amountPaid = $cart->getOrderTotal(true, Cart::BOTH);
                    $currencyPaid = new Currency($cart->id_currency);
                    $sql = 'INSERT INTO `' . _DB_PREFIX_
                        . 'altapay_saved_credit_card` (time,userID,agreement_id,agreement_type,cardBrand,creditCardNumber,cardExpiryDate,ccToken) VALUES (Now(),'
                        . pSQL($customerID) . ',"' . pSQL($transactionId) . '","'
                        . pSQL($agreementType) . '","' . pSQL($cardType) . '","'
                        . pSQL($maskedPan) . '","' . pSQL($expires) . '","' . pSQL($ccToken)
                        . '")';
                    Db::getInstance()->executeS($sql);

                    $agreementData = [
                        'id' => $transactionId,
                        'type' => 'unscheduled',
                        'unscheduled_type' => 'incremental',
                    ];
                    $request = new API\PHP\Altapay\Api\Payments\ReservationOfFixedAmount(getAuth());
                    $request->setCreditCardToken($response->creditCardToken)
                            ->setTerminal($transaction->Terminal)
                            ->setShopOrderId($response->shopOrderId)
                            ->setAmount($amountPaid)
                            ->setCurrency($currencyPaid->iso_code)
                            ->setAgreement([
                                'id' => $transactionId,
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
                    $this->module->name,
                    $this->module->id,
                    true
                    );
                }

                // Log order
                createAltapayOrder($response, $order);
                $this->unlock($fp);
                Tools::redirect('index.php?controller=order-detail&id_order=' . $order->id);
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

    public function getTransaction($response)
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
}
