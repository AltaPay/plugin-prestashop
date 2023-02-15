<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2023 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCronModuleFrontController extends ModuleFrontController
{
    private $log_file;

    public function __construct()
    {
        parent::__construct();
        $this->log_file = dirname(__FILE__) . '/../../altapay_cron.log';
    }

    /**
     * Initialize cron controller.
     *
     * @see ModuleFrontController::init()
     */
    public function init()
    {
        parent::init();
        if (Module::isEnabled('wkproductsubscription')) {
            include_once _PS_MODULE_DIR_ . 'wkproductsubscription/classes/WkSubscriptionRequired.php';

            $pending_cron_jobs = $this->getPendingAltaPayCronJobs();
            if (!empty($pending_cron_jobs)) {
                foreach ($pending_cron_jobs as $pending_cron_job) {
                    $order = new Order((int) $pending_cron_job['id_order']);
                    $parent_order_id = $this->getParentOrder($pending_cron_job['id_order']);
                    if (!empty($parent_order_id)) {
                        $agreement = $this->getAgreementByOrderId($parent_order_id[0]['first_order_id']);
                        if (!empty($agreement)) {
                            $reconciliation_identifier = sha1($agreement[0]['agreement_id'] . time());
                            $amount = (float) json_decode($pending_cron_job['payload'], true)['order_total'];
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
                                    createAltapayOrder($response, $order, 'subscription_payment');
                                    $this->saveAltaPayTransaction($uniqueId, $transaction->CapturedAmount, $transaction->Terminal);
                                }
                                saveOrderReconciliationIdentifier($pending_cron_job['id_order'], $reconciliation_identifier);
                                $this->markAltaPayCronjobAsCompleted($pending_cron_job['id']);
                                $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
                            } catch (Exception $e) {
                                $order->setCurrentState((int) Configuration::get('ALTAPAY_OS_PENDING'));
                                error_log('===========AltaPay cron error============' . PHP_EOL, 3, $this->log_file);
                                error_log($e->getMessage() . PHP_EOL, 3, $this->log_file);
                                error_log(json_encode([$pending_cron_job, $parent_order_id, $agreement]) . PHP_EOL, 3, $this->log_file);
                                error_log('===========AltaPay cron error============' . PHP_EOL, 3, $this->log_file);
                            }
                        }
                    }
                }
            }
        } else {
            exit('wkproductsubscription module not enabled.');
        }
    }

    /**
     * @return array
     */
    private function getPendingAltaPayCronJobs()
    {
        $sql = 'SELECT `id`, `id_order`, `payload` FROM `' . _DB_PREFIX_ . "altapay_crons` WHERE `status` ='pending'";

        return Db::getInstance()->executeS($sql);
    }

    /**
     * @param int $id_order
     *
     * @return array
     */
    private function getParentOrder($id_order)
    {
        // @phpstan-ignore-next-line
        $subscription_order_table = WkSubscriberOrderModel::$definition['table'];
        // @phpstan-ignore-next-line
        $subscription_table = WkSubscriberProductModal::$definition['table'];
        // @phpstan-ignore-next-line
        $subscription_table_pk = WkSubscriberProductModal::$definition['primary'];

        $sql = 'SELECT s.first_order_id FROM ' . _DB_PREFIX_ . "$subscription_table s 
                INNER JOIN " . _DB_PREFIX_ . "$subscription_order_table so ON so.id_subscription=s.$subscription_table_pk 
                WHERE so.id_order='" . pSQL($id_order) . "'";

        return Db::getInstance()->executeS($sql);
    }

    /**
     * @param int $id_order
     *
     * @return array
     */
    private function getAgreementByOrderId($id_order)
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
    private function saveAltaPayTransaction($unique_id, $amount, $terminal)
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'altapay_transaction` 
				(id_cart, payment_form_url, token, unique_id, amount, terminal_name, date_add) VALUES ' .
            "('', '', '', '" . pSQL($unique_id) . "', '" . pSQL($amount) . "', '" . pSQL($terminal) . "' ,
             '" . pSQL(time()) . "')" . ' ON DUPLICATE KEY UPDATE `amount` = ' . pSQL($amount);

        Db::getInstance()->Execute($sql);
    }

    /**
     * @param int $cron_job_id
     *
     * @return void
     */
    private function markAltaPayCronjobAsCompleted($cron_job_id)
    {
        $sql = 'UPDATE `' . _DB_PREFIX_ . "altapay_crons` SET status ='completed' WHERE `id` ='" . pSQL($cron_job_id) . "' LIMIT 1";

        Db::getInstance()->executeS($sql);
    }
}
