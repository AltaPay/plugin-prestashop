<?php

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


    public function ajaxProcessGetUrl()
    {
        $paymentLink = Tools::getValue('payment_link');

        if (Tools::isSubmit('send_email')) {
            $customerId = (int)Tools::getValue('customer_id');
            $this->sendCustomEmail($customerId, $paymentLink);
        }
    }
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

    protected function ajaxProcessRefundRemaining() {
        try {
            $orderID = Tools::getValue('orderid');
            $paymentID = Tools::getValue('payment_id');
            $refundAmount = (float) Tools::getValue('remaining_amount');

            $reconciliation_identifier = sha1($paymentID . time());
            $api = new API\PHP\Altapay\Api\Payments\RefundCapturedReservation(getAuth());
            $api->setAmount($refundAmount);
            $api->setTransaction($paymentID);
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
    protected function sendCustomEmail($customerId, $paymentUrl)
    {
        $customer = new Customer($customerId);
        if (Validate::isLoadedObject($customer)) {
            $to = $customer->email;
            $toName = $customer->firstname . ' ' . $customer->lastname;
            $subject = $this->l('Action Required: Payment Link for Outstanding Amount');
            $template = 'addition_item_email';
            $templateVars = array(
                '{customer_name}' => $customer->firstname . ' ' . $customer->lastname,
                '{payment_link}' => $paymentUrl
            );
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
                    dirname(__file__) . '/mails/'
                );

                echo json_encode(['status' => 'success', 'message' => 'Email sent successfully!']);

            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to send email.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid customer ID.']);
        }
    }

}