<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AdminPayByLinkController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function init()
    {
        parent::init();
        $this->ajax = true;
    }

    public function initContent()
    {
        parent::initContent();
    }

    /**
     * Process an AJAX request to get the payment link and optionally send a custom email.
     *
     * @return void
     */
    public function ajaxProcessGetUrl()
    {
        $amount = Tools::getValue('amount');
        $order_id = Tools::getValue('order_id');
        $send_email = Tools::getValue('send_email');
        $paymentLink = $this->module->altaPayOrderEdited($order_id, $amount);

        if ($send_email && $paymentLink) {
            $customerId = (int) Tools::getValue('customer_id');
            $this->sendCustomEmail($customerId, $paymentLink, $amount, $order_id);
        }

        echo json_encode(['status' => 'success', 'message' => 'Success!']);
        exit();
    }

    /**
     * Process an AJAX request to capture the remaining amount of a payment.
     *
     * @return void
     */
    public function ajaxProcessCaptureRemaining()
    {
        $orderID = Tools::getValue('orderid');
        $paymentID = Tools::getValue('payment_id');
        $amount = (float) Tools::getValue('remaining_amount');

        try {
            $reconciliation_identifier = sha1($paymentID . time());
            $payment_type = getAltapayChildOrderDetails($orderID)[0]['paymentType'];
            if (in_array($payment_type, ['subscription', 'subscription_payment'])) {
                $api = new API\PHP\Altapay\Api\Subscription\ChargeSubscription(getAuth());
                $api->setAgreement(['id' => $paymentID]);
            } else {
                $api = new API\PHP\Altapay\Api\Payments\CaptureReservation(getAuth());
                $api->setAmount($amount);
            }
            $api->setOrderLines($this->OrderlineForBackorderItems($amount));
            $api->setTransaction($paymentID);
            $api->setReconciliationIdentifier($reconciliation_identifier);
            $response = $api->call();
            if ($payment_type == 'subscription' and isset($response) and isset($response->Transactions)) {
                $latestTransKey = 0;
                foreach ($response->Transactions as $key => $transaction) {
                    if ($transaction->AuthType === 'subscription_payment' && $transaction->CreatedDate > $max_date) {
                        $max_date = $transaction->CreatedDate;
                        $latestTransKey = $key;
                    }
                }
                $transaction = $response->Transactions[$latestTransKey];
                updateParentTransIdChildOrder($orderID, $transaction->TransactionId);
            }
            markChildOrderAsCaptured($paymentID);
            $transaction = getTransaction($response);
            saveOrderReconciliationIdentifier($orderID, $reconciliation_identifier, $transaction->ShopOrderId);

            echo json_encode(
                [
                    'status' => 'success',
                    'message' => 'Remaining amount for the reservation was captured successfully',
                ]
            );
        } catch (Exception $e) {
            // Save the latest error message in db
            saveLastErrorMessage($paymentID, $e->getMessage());
            echo json_encode(
                [
                    'status' => 'error',
                    'message' => 'Could not capture remaining reserved amount. ' . $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Process an AJAX request to refund the remaining amount of a payment.
     *
     * @return void
     */
    protected function ajaxProcessRefundRemaining()
    {
        try {
            $orderID = Tools::getValue('orderid');
            $paymentID = Tools::getValue('payment_id');
            $refundAmount = (float) Tools::getValue('remaining_amount');

            $reconciliation_identifier = sha1($paymentID . time());
            $api = new API\PHP\Altapay\Api\Payments\RefundCapturedReservation(getAuth());
            $api->setAmount($refundAmount);
            $api->setTransaction($paymentID);
            $api->setOrderLines($this->OrderlineForBackorderItems($refundAmount));
            $api->setReconciliationIdentifier($reconciliation_identifier);
            $response = $api->call();

            markChildOrderAsRefund($paymentID);

            if (strtolower($response->Result) === 'open') {
                $order_message = new Message();
                $order_message->id_order = $orderID;
                $order_message->message = 'Payment refund is in progress.';
                $order_message->private = true;
                $order_message->save();

                echo json_encode(
                    [
                        'status' => 'success',
                        'message' => 'Payment refund is in progress.',
                    ]
                );
            }

            $transaction = getTransaction($response);
            saveOrderReconciliationIdentifier($orderID, $reconciliation_identifier, $transaction->ShopOrderId, 'refunded');
            echo json_encode(
                [
                    'status' => 'success',
                    'message' => 'Payment refunded successfully',
                ]
            );
        } catch (Exception $e) {
            $message = $e->getMessage();
            saveLastErrorMessage($paymentID, $message);
            echo json_encode(
                [
                    'status' => 'error',
                    'message' => 'Could not refund payment. ' . $message,
                ]
            );
            exit();
        }
    }

    /**
     * Sends a custom email containing a payment link to the specified customer.
     *
     * @param $customerId
     * @param $paymentUrl
     * @param $amount
     * @param $order_id
     *
     * @return void
     */
    protected function sendCustomEmail($customerId, $paymentUrl, $amount, $order_id)
    {
        $customer = new Customer($customerId);
        if (Validate::isLoadedObject($customer)) {
            $to = $customer->email;
            $toName = $customer->firstname . ' ' . $customer->lastname;
            $subject = $this->l('Action Required: Payment Link for Outstanding Amount');
            $order = new Order($order_id);
            // Get currency object
            $currency = new Currency($order->id_currency);
            // Get currency symbol
            $currencySymbol = $currency->sign;

            if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                $template = 'addition_item_email';
            } else {
                $template = 'addition_item_email16';
            }
            $templateVars = [
                '{customer_name}' => $customer->firstname . ' ' . $customer->lastname,
                '{payment_link}' => $paymentUrl,
                '{amount}' => $amount,
                '{id_order}' => $order_id,
            ];
            $from = Configuration::get('PS_SHOP_EMAIL');
            $fromName = Configuration::get('PS_SHOP_NAME');

            try {
                Mail::Send(
                    $this->context->language->id,
                    $template,
                    $subject,
                    $templateVars,
                    $to,
                    $toName,
                    $from,
                    $fromName,
                    null,
                    null,
                    dirname(__FILE__, 3) . '/mails/',
                    false,
                    null
                );

                echo json_encode(['status' => 'success', 'message' => 'Payment link of ' . $currencySymbol . ''. $amount . ' sent to ' . $customer->email]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to send email.']);
                exit();
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid customer ID.']);
            exit();
        }
    }

    /**
     *  Create an OrderLine object for backorder items with the remaining amount.
     *
     * @param $remainingAmount
     *
     * @return \API\PHP\Altapay\Request\OrderLine
     */
    public function OrderlineForBackorderItems($remainingAmount)
    {
        $orderLine = new API\PHP\Altapay\Request\OrderLine(
            'Total',
            'additional-amount',
            1,
            $remainingAmount
        );
        $orderLine->setGoodsType('item');

        return $orderLine;
    }
}
