<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayCronSyncOrderFromGatewayModuleFrontController extends ModuleFrontController
{
    /** @var float The start time of the script. */
    private $start_time;
    private $cron_msg_prefix = 'Order sync cron';

    /**
     * Start the timer.
     */
    public function __construct()
    {
        parent::__construct();
        $this->start_time = microtime(true);
    }

    /**
     * Initialize cron controller.
     *
     * @see ModuleFrontController::init()
     */
    public function init()
    {
        parent::init();

        if (Module::isEnabled('altapay')) {
            $payment_module = Module::getInstanceByName('altapay');

            if (!$this->checkCronPrerequisites()) {
                $error = "$this->cron_msg_prefix not run, error: column 'is_processed_by_cron' does not exist in the 'altapay_transaction' table.";
                PrestaShopLogger::addLog($error, 3, null, $payment_module->name, $payment_module->id, true);
                exit($error);
            }

            $total_orders_synced = $total_orders_created = $total_orders_missing_on_gateway = 0;
            $records_to_mark = $records = [];
            try {
                $records = $this->findMissingAltaPayTransactionRecords($payment_module);
                if (!empty($records)) {
                    foreach ($records as $record) {
                        $response = $this->getTransactionFromAltaPayByShopOrderId($record['unique_id'], $payment_module);

                        // Proceed if transaction is found on AltaPay after fraud configuration check
                        if (empty($response)) {
                            PrestaShopLogger::addLog("$this->cron_msg_prefix No transaction data on gateway for shoporder_id: {$record['unique_id']}" . json_encode($record), 3, null, $payment_module->name, $payment_module->id, true);
                            $records_to_mark[] = $record['id'];
                            ++$total_orders_missing_on_gateway;
                            continue;
                        }
                        $cart = new Cart((int) $record['id_cart']);

                        if (!Validate::isLoadedObject($cart)) {
                            PrestaShopLogger::addLog("$this->cron_msg_prefix error could not load cart: " . json_encode($record), 3, null, $payment_module->name, $payment_module->id, true);
                            $records_to_mark[] = $record['id'];
                            continue;
                        }

                        // Check if order exists in PrestaShop
                        $id_order = Order::getOrderByCartId((int) ($cart->id));

                        if (!empty($id_order)) {
                            $orderDetail = new Order((int) $id_order);

                            if (!Validate::isLoadedObject($orderDetail)) {
                                PrestaShopLogger::addLog("$this->cron_msg_prefix error: could not load order ID : $id_order, " . json_encode($record), 3, null, $payment_module->name, $payment_module->id, true);
                                $records_to_mark[] = $record['id'];
                                continue;
                            }
                        } else {
                            $transaction = getTransaction($response);
                            $orderStatus = $this->getNewOrderStatus($transaction);
                            $currencyPaid = Currency::getIdByIsoCode($transaction->MerchantCurrencyAlpha);

                            //Create order in PrestaShop
                            createOrder($response, $currencyPaid, $cart, $orderStatus);

                            // Get newly created order ID
                            if ($payment_module->currentOrder) {
                                $orderDetail = new Order((int) $payment_module->currentOrder);

                                if (!Validate::isLoadedObject($orderDetail)) {
                                    PrestaShopLogger::addLog("$this->cron_msg_prefix error: could not load cron created order ID: $payment_module->currentOrder, " . json_encode($record), 3, null, $payment_module->name, $payment_module->id, true);
                                    // Mark in altapay_transaction, order creation in PrestaShop was attempted but loading failed.
                                    $records_to_mark[] = $record['id'];
                                    continue;
                                }
                                ++$total_orders_created;
                                PrestaShopLogger::addLog("$this->cron_msg_prefix created an order ID: $payment_module->currentOrder, " . json_encode($record), 1, null, $payment_module->name, $payment_module->id, true);
                            } else {
                                PrestaShopLogger::addLog("$this->cron_msg_prefix error: could not create order " . json_encode($record), 3, null, $payment_module->name, $payment_module->id, true);
                                // Mark in altapay_transaction, order creation in PrestaShop was attempted but failed.
                                $records_to_mark[] = $record['id'];
                                continue;
                            }
                        }

                        if (!Validate::isLoadedObject($orderDetail)) {
                            // Mark in altapay_transaction, transaction data sync failed because order was not loaded.
                            PrestaShopLogger::addLog("$this->cron_msg_prefix error: could not load order for syncing, " . json_encode($record), 3, null, $payment_module->name, $payment_module->id, true);
                            $records_to_mark[] = $record['id'];
                        } else {
                            // Sync AltaPay transaction data
                            createAltapayOrder($response, $orderDetail);

                            if (!empty($this->recordExistsInAltapayOrder($record['unique_id'], $payment_module))) {
                                ++$total_orders_synced;
                            } else {
                                // Mark in altapay_transaction, transaction data sync was attempted but failed.
                                PrestaShopLogger::addLog("$this->cron_msg_prefix error: could not sync order data, " . json_encode($record), 3, null, $payment_module->name, $payment_module->id, true);
                            }
                            $records_to_mark[] = $record['id'];
                        }
                    }
                }
            } catch (Exception $e) {
                PrestaShopLogger::addLog("$this->cron_msg_prefix exception: {$e->getMessage()}", 3, null, $payment_module->name, $payment_module->id, true);
            }

            // Mark records as processed by cron in altapay_transaction
            if (!empty($records_to_mark)) {
                $this->markAsProcessed($records_to_mark);
            }

            $msg = "\r\n\n";
            $timing_info = $this->stopTimer();
            $msg .= "$this->cron_msg_prefix started at: {$timing_info['start']}\n";
            $msg .= "Ended at: {$timing_info['end']}\n";
            $msg .= "Execution time: {$timing_info['execution_time']} seconds\n";
            $msg .= 'Total Records fetched: ' . count($records) . "\n";
            $msg .= 'Total Records Processed: ' . count($records_to_mark) . "\n";
            $msg .= "Total Order Synced: $total_orders_synced\n";
            $msg .= "Total Order Created: $total_orders_created\n";
            $msg .= "Total Unique Ids Missing on Gateway: $total_orders_missing_on_gateway\n";
            PrestaShopLogger::addLog($msg, 1, null, $payment_module->name, $payment_module->id, true);
            exit($msg);
        } else {
            $msg = "$this->cron_msg_prefix not run, AltaPay's Payment module not enabled.";
            PrestaShopLogger::addLog($msg, 3);
            exit($msg);
        }
    }

    /**
     * Retrieve unique_id values from altapay_transaction
     * that do not exist in altapay_order.
     *
     * @return array|false
     */
    private function findMissingAltaPayTransactionRecords($payment_module)
    {
        try {
            // Define the query
            $query = 'SELECT id, unique_id, id_cart FROM ' . _DB_PREFIX_ . 'altapay_transaction AS t 
        WHERE is_processed_by_cron = 0 AND is_cancelled != 1 AND NOT EXISTS ( SELECT 1 FROM ' . _DB_PREFIX_ . 'altapay_order AS o WHERE o.unique_id = t.unique_id )
        LIMIT 10';
            // Get a reference to the database object
            $db = Db::getInstance();
            // Execute the query, fetch & return the results
            return $db->executeS($query);
        } catch (Exception $e) {
            PrestaShopLogger::addLog("Cron error: could not find missing transaction records, DB error: {$e->getMessage()}", 3, null, $payment_module->name, $payment_module->id, true);

            return false;
        }
    }

    /**
     * Get transaction data from AltaPay by shop_orderid
     *
     * @param $shop_orderid
     * @param $payment_module
     *
     * @return false|object
     */
    private function getTransactionFromAltaPayByShopOrderId($shop_orderid, $payment_module)
    {
        try {
            $api = new API\PHP\Altapay\Api\Others\Payments(getAuth());
            $api->setShopOrderId($shop_orderid);
            $paymentDetails = $api->call();

            if (empty($paymentDetails)) {
                return false;
            }

            $fraudConfig = Tools::getValue('enable_fraud', Configuration::get('enable_fraud'));
            $enableReleaseRefund = Tools::getValue('enable_release_refund', Configuration::get('enable_release_refund'));
            if ($fraudConfig && $enableReleaseRefund && strtolower($paymentDetails[0]->FraudRecommendation) === 'deny') {
                PrestaShopLogger::addLog("Cron error: fraud payment shop_orderid: $shop_orderid", 3, null, $payment_module->name, $payment_module->id, true);

                return false;
            }

            $response['nature'] = $paymentDetails[0]->PaymentNature ?? '';
            $response['Transactions'] = $paymentDetails;

            return json_decode(json_encode($response));
        } catch (Exception $e) {
            PrestaShopLogger::addLog("Payments API Error shop_orderid: $shop_orderid, Cron error: " . $e->getMessage(), 3, null, $payment_module->name, $payment_module->id, true);

            return false;
        }
    }

    /**
     * Returns newly created order status to be set based on config
     *
     * @param $transaction
     *
     * @return int
     */
    private function getNewOrderStatus($transaction)
    {
        $orderStatus = (int) Configuration::get('authorized_payments_status');
        if (empty($orderStatus)) {
            $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
        }

        if (in_array($transaction->TransactionStatus, ['bank_payment_finalized', 'captured'], true)) {
            $orderStatus = (int) Configuration::get('PS_OS_PAYMENT');
        }

        return $orderStatus;
    }

    /**
     * Check if a record exists in altapay_order table based on shop_orderid.
     *
     * @param string $uniqueId the unique_id value to check
     *
     * @return bool true if the record exists, false otherwise
     */
    private function recordExistsInAltapayOrder($uniqueId, $payment_module)
    {
        try {
            // Get a reference to the database object
            $db = Db::getInstance();

            // Prepare the query
            $query = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'altapay_order WHERE unique_id = :uniqueId';

            // Prepare the parameters
            $params = [':uniqueId' => pSQL($uniqueId)];

            // Execute the query with parameters
            $result = $db->getValue($query, $params);

            // Check if a record exists
            return $result;
        } catch (Exception $e) {
            PrestaShopLogger::addLog("Cron error: could not validate if transaction data was synced, DB error: {$e->getMessage()}", 3, null, $payment_module->name, $payment_module->id, true);

            return false;
        }
    }

    /**
     * Mark the records as processed by cron in altapay_transaction
     *
     * @param array $ids array of IDs to update
     *
     * @return bool true on success, false on failure
     */
    private function markAsProcessed($ids)
    {
        $db = Db::getInstance();

        $query = 'UPDATE `' . _DB_PREFIX_ . 'altapay_transaction` 
        SET `is_processed_by_cron` = 1 WHERE `id` IN (' . implode(',', $ids) . ')';

        return $db->execute($query);
    }

    /**
     * Check if the column 'is_processed_by_cron' exists in the 'altapay_transaction' table.
     *
     * @return bool true if the column exists, false otherwise
     */
    private function checkCronPrerequisites()
    {
        $db = Db::getInstance();

        $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = \'' . pSQL(_DB_PREFIX_ . 'altapay_transaction') . '\' 
            AND COLUMN_NAME = \'is_processed_by_cron\'';

        $count = (int) $db->getValue($sql);

        return $count > 0;
    }

    /**
     * Stops the timer and returns the timing information.
     *
     * @return array timing information containing start time, end time, and execution time
     */
    private function stopTimer()
    {
        $endTime = microtime(true);
        $executionTime = round($endTime - $this->start_time, 2);
        $startDateTime = date('Y-m-d H:i:s', (int) $this->start_time);
        $endDateTime = date('Y-m-d H:i:s', (int) $endTime);

        return [
                'start' => $startDateTime,
                'end' => $endDateTime,
                'execution_time' => $executionTime,
            ];
    }
}
