<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once _PS_MODULE_DIR_ . '/altapay/lib/altapay/altapay-php-sdk/lib/helpers.php';

/**
 * Wrapper for interacting with AltaPay merchant API
 */
class MerchantAPI
{
    /**
     * @var AltapayMerchantAPI
     */
    private $api;
    /**
     * @var string
     */
    private $api_url;
    /**
     * @var string
     */
    private $api_username;
    /**
     * @var string
     */
    private $api_password;

    const ALTAPAY = " {AltaPay} ";

    /**
     * Method for validation of credentials provided for api connection
     * @param string $api_url
     * @param string $api_username
     * @param string $api_password
     * @throws Exception
     * @return void
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
     * @param int $paymentId
     * @return AltapayAPIPayment
     * @throws AltapayConnectionFailedException
     * @throws AltapayInvalidResponseException
     * @throws AltapayRequestTimeoutException
     * @throws AltapayUnauthorizedAccessException
     * @throws AltapayUnknownMerchantAPIException
     * @return AltapayAPIPayment
     */
    public function getPaymentDetails($paymentId)
    {
        $response = $this->api->getPayment($paymentId);

        if (is_null($response)) {
            throw new AltapayMerchantAPIException(self::ALTAPAY . 'Could not get payment details of payment '.$paymentId);
        }

        return $response->getPrimaryPayment();
    }

    /**
     * Method to trigger capture action
     * @param int $paymentId
     * @param array $orderLines
     * @param int $amount
     * @return AltapayCaptureResponse
     * @throws AltapayMerchantAPIException
     */
    public function captureAmount($paymentId, $orderLines = [], $amount = 0)
    {
        $response = $this->api->captureReservation($paymentId, $amount, $orderLines);

        if (!$response->wasSuccessful()) {
            $xmlResponse = $this->xmlParser($response->getXml());
            throw new AltapayMerchantAPIException($this->errorMsg($xmlResponse, self::ALTAPAY . 'Error occurred while payment capture'));
        }

        return $response;
    }

    /**
     * Method to trigger refund action
     * @param int $paymentId
     * @param array $orderLines
     * @param int $amount
     * @return AltapayRefundResponse
     * @throws AltapayMerchantAPIException
     */
    public function refundAmount($paymentId, $orderLines =[], $amount = 0)
    {
        $response = $this->api->refundCapturedReservation($paymentId, $amount, $orderLines);

        if (!$response->wasSuccessful()) {
            $xmlResponse = $this->xmlParser($response->getXml());
            throw new AltapayMerchantAPIException($this->errorMsg($xmlResponse, self::ALTAPAY . 'Error occurred while payment refund'));
        }

        return $response;
    }

    /**
     * Method to trigger release action
     * @param int $paymentId
     * @param string $transactionAction
     * @return AltapayReleaseResponse
     * @throws AltapayMerchantAPIException
     */
    public function release($paymentId, $transactionAction)
    {
        $response = $this->api->releaseReservation($paymentId);

        if (!$response->wasSuccessful()) {
            $xmlResponse = $this->xmlParser($response->getXml());
            throw new AltapayMerchantAPIException($this->errorMsg($xmlResponse, self::ALTAPAY . $transactionAction));
        }

        return $response;
    }

    /**
     * Method for validation of merchant details
     * @throws AltapayMerchantAPIException
     * @return void
     */
    private function validateConfiguration()
    {
        if (empty($this->api_url) ||
            empty($this->api_username) ||
            empty($this->api_password)) {
            throw new AltapayMerchantAPIException(self::ALTAPAY . 'Url, username or password missing');
        }

        $this->api = new AltapayMerchantAPI(
            $this->api_url,
            $this->api_username,
            $this->api_password,
            null
        );
        $response = $this->api->login();
        if (!$response->wasSuccessful()) {
            throw new AltapayMerchantAPIException(self::ALTAPAY . 'Could not login to the Merchant API: '.
                $response->getErrorMessage(), $response->getErrorCode());
        }
    }

    /**
     * @param /SimpleXMLElement $xml
     * @return array
     */
    public function xmlParser($xml)
    {
        // Get the response XML and convert into array
        $xml = new SimpleXMLElement($xml);
        $json = json_encode($xml);

        return json_decode($json, true);
    }

    /**
     * Method for getting error message response
     * @param array $xmlResponse
     * @param string $action
     * @return false|string
     */
    public function errorMsg($xmlResponse, $action)
    {
        $response = [];

        $response['responseResult'] = $xmlResponse['Body'][$action . ' Result'];
        $response['responseMsg'] = $xmlResponse['Body']['MerchantErrorMessage'];

        return json_encode($response);
    }
}
