<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}

require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'response'. DIRECTORY_SEPARATOR .'AltapayAbstractResponse.class.php';
require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'response'. DIRECTORY_SEPARATOR .'AltapayTerminal.class.php';

/**
 * Class AltapayCalculateSurchargeResponse
 */
class AltapayCalculateSurchargeResponse extends AltapayAbstractResponse
{
    private $result;
    private $surchargeAmount = array();

    /**
     * AltapayCalculateSurchargeResponse constructor.
     * @param SimpleXmlElement $xml
     */
    public function __construct(SimpleXmlElement $xml)
    {
        parent::__construct($xml);
        
        if($this->getErrorCode() === '0') {
            $this->result = (string)$xml->Body->Result;
            $this->surchargeAmount = (string)$xml->Body->SurchageAmount;
        }
    }

    /**
     * @return array|string
     */
    public function getSurchargeAmount()
    {
        return $this->surchargeAmount;
    }

    /**
     * @return bool
     */
    public function wasSuccessful()
    {
        return $this->result === 'Success';
    }
}