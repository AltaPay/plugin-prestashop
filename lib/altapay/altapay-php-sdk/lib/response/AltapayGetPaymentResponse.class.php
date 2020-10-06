<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}

require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'response'. DIRECTORY_SEPARATOR .'AltapayAbstractPaymentResponse.class.php';

/**
 * Class AltapayGetPaymentResponse
 */
class AltapayGetPaymentResponse extends AltapayAbstractPaymentResponse
{
    /**
     * AltapayGetPaymentResponse constructor.
     * @param SimpleXmlElement $xml
     */
    public function __construct(SimpleXmlElement $xml)
    {
        parent::__construct($xml);
    }

    /**
     * @param SimpleXmlElement $body
     */
    protected function parseBody(SimpleXmlElement $body)
    {
        
    }

    /**
     * @return bool
     */
    public function wasSuccessful()
    {
        return $this->getErrorCode() === '0' && !is_null($this->getPrimaryPayment());
    }


}