<?php
/**
 * Altapay module for Prestashop
 *
 * Copyright © 2020 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once(_PS_MODULE_DIR_.'/altapay/lib/altapay/altapay-php-sdk/lib/AltapayMerchantAPI.class.php');

/**
 * Wrapper for interacting with ALTAPAY merchant API
 *
 * @author Ulrik Andersen ulrik.and@gmail.com
 */
class MerchantAPI
{
    /**
     * @var AltapayMerchantAPI
     */
    private $api;
    private $api_url;
    private $api_username;
    private $api_password;
    
    public function __construct()
    {
    }

    /**
     * Method for validation of credentials provided for api connection
     * @param $api_url
     * @param $api_username
     * @param $api_password
     * @throws Exception
     */
    public function init($api_url, $api_username, $api_password)
    {
        $this->api_url = $api_url;
        $this->api_username = $api_username;
        $this->api_password = $api_password;
        $this->validateConfiguration();
    }

    /**
     * Method to get payment details against payment Id
     * @param $paymentId
     * @return AltapayAPIPayment
     * @throws AltapayConnectionFailedException
     * @throws AltapayInvalidResponseException
     * @throws AltapayRequestTimeoutException
     * @throws AltapayUnauthorizedAccessException
     * @throws AltapayUnknownMerchantAPIException
     */
    public function getPaymentDetails($paymentId)
    {
        $response = $this->api->getPayment($paymentId);

        if (is_null($response)) {
            throw new Exception("Could not get payment details of payment ".$paymentId);
        }

        return $response->getPrimaryPayment();
    }

    /**
     * Method to trigger capture action
     * @param $paymentId
     * @param array $orderLines
     * @param int $amount
     * @return AltapayCaptureResponse
     * @throws AltapayMerchantAPIException
     */
    public function captureAmount($paymentId, $orderLines = array(), $amount = 0)
    {
        $response = $this->api->captureReservation($paymentId, $amount, $orderLines);

        if (!$response->wasSuccessful()) {
            $xmlResponse = $this->xmlParser($response->getXml());
            throw new Exception($this->errorMsg($xmlResponse, 'Capture'));
        }

        return $response;
    }

    /**
     * Method to trigger refund action
     * @param $paymentId
     * @param array $orderLines
     * @param int $amount
     * @return AltapayRefundResponse
     * @throws AltapayMerchantAPIException
     */
    public function refundAmount($paymentId, $orderLines = array(), $amount = 0)
    {
        $response = $this->api->refundCapturedReservation($paymentId, $amount, $orderLines);

        if (!$response->wasSuccessful()) {
            $xmlResponse = $this->xmlParser($response->getXml());
            throw new Exception($this->errorMsg($xmlResponse, 'Refund'));
        }

        return $response;
    }

    /**
     * Method to trigger release action
     * @param $paymentId
     * @param $transactionAction
     * @return AltapayReleaseResponse
     * @throws AltapayMerchantAPIException
     */
    public function release($paymentId, $transactionAction)
    {
        $response = $this->api->releaseReservation($paymentId);

        if (!$response->wasSuccessful()) {
            $xmlResponse = $this->xmlParser($response->getXml());
            throw new Exception($this->errorMsg($xmlResponse, $transactionAction));
        }

        return $response;
    }

    /**
     * Method for validation of merchant details
     * @throws AltapayMerchantAPIException
     */
    private function validateConfiguration()
    {
        if (empty($this->api_url) ||
            empty($this->api_username) ||
            empty($this->api_password)) {
            throw new Exception('Url, username or password missing');
        }

        $this->api = new AltapayMerchantAPI(
            $this->api_url,
            $this->api_username,
            $this->api_password,
            null
        );
        $response = $this->api->login();
        if (!$response->wasSuccessful()) {
            throw new Exception("Could not login to the Merchant API: ".
                $response->getErrorMessage(), $response->getErrorCode());
        }
    }

    /**
     * @param $xml
     * @return mixed
     */
    public function xmlParser($xml)
    {
        //get the response XML and convert into array
        $xml = simplexml_load_string($xml);
        $json = json_encode($xml);

        return json_decode($json, true);
    }

    /**
     * Method for getting error message response
     * @param $xmlResponse
     * @param $action
     * @return false|string
     */
    public function errorMsg($xmlResponse, $action)
    {
        $response = array();

        $response['responseResult'] = $xmlResponse['Body'][$action . ' Result'];
        $response['responseMsg'] = $xmlResponse['Body']['MerchantErrorMessage'];

        return json_encode($response);
    }
}
