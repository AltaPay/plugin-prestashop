<?php

/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class AltapayAsyncProcessCallbacksFromGatewayModuleFrontController extends ModuleFrontController
{
    /** @var float The start time of the script. */
    private $start_time;
    private $cron_msg_prefix = 'Async callback processing script';

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

        if (!Module::isEnabled('altapay')) {
            $msg = "$this->cron_msg_prefix not run, AltaPay's Payment module not enabled.";
            PrestaShopLogger::addLog($msg, 3);
            exit($msg);
        }

        $payment_module = Module::getInstanceByName('altapay');

        $record_id = Tools::getValue('id');

        if (empty($record_id)) {
            $error = "$this->cron_msg_prefix not run, error: param 'id' is missing.";
            PrestaShopLogger::addLog($error, 3, null, $payment_module->name, $payment_module->id, true);
            exit($error);
        }

        if (!$this->checkPrerequisites()) {
            $error = "$this->cron_msg_prefix not run, error: table 'altapay_callback_requests' does not exist.";
            PrestaShopLogger::addLog($error, 3, null, $payment_module->name, $payment_module->id, true);
            exit($error);
        }

        try {
            $records = $this->findCallbackRecord($record_id, $payment_module);
            if (!empty($records)) {
                $record = $records[0];
                $postData = json_decode($record['request_data'], true);
                $xmlEncoded = $postData['xml'];
                // Decode the XML data
                $xml = base64_decode($xmlEncoded);
                // Replace with xml in POST array
                $postData['xml'] = $xml;

                if ($record['callback_type'] == 'ok') {
                    createOrderOkCallback($postData, $record['id']);
                }
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog("$this->cron_msg_prefix exception: {$e->getMessage()}", 3, null, $payment_module->name, $payment_module->id, true);
        }

        $msg = "\r\n\n";
        $timing_info = $this->stopTimer();
        $msg .= "$this->cron_msg_prefix started at: {$timing_info['start']}\n";
        $msg .= "Ended at: {$timing_info['end']}\n";
        $msg .= "Execution time: {$timing_info['execution_time']} seconds\n";
        PrestaShopLogger::addLog($msg, 1, null, $payment_module->name, $payment_module->id, true);
    }

    /**
     * @param $id
     * @param $payment_module
     *
     * @return false
     */
    private function findCallbackRecord($id, $payment_module)
    {
        try {
            // Define the query
            $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'altapay_callback_requests WHERE processing_status = 0 AND id = ' . (int) $id;
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
     * Check if the 'altapay_callback_requests' table exists.
     *
     * @return bool true if the column exists, false otherwise
     */
    private function checkPrerequisites()
    {
        $db = Db::getInstance();

        $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = \'' . pSQL(_DB_PREFIX_ . 'altapay_callback_requests') . '\'';

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
