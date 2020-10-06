<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}

require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'response'. DIRECTORY_SEPARATOR .'AltapayAbstractResponse.class.php';

/**
 * Class AltapayCreateInvoiceReservationResponse
 */
class AltapayCreateInvoiceReservationResponse extends AltapayAbstractResponse
{
    private $result;

    /**
     * AltapayCreateInvoiceReservationResponse constructor.
     * @param SimpleXmlElement $xml
     */
    public function __construct(SimpleXmlElement $xml)
    {
        parent::__construct($xml);
        
        if($this->getErrorCode() === '0') {
            $this->result = (string)$xml->Body->Result;
        }
    }

    /**
     * @return bool
     */
    public function wasSuccessful()
    {
        return $this->getErrorCode() === '0' && $this->result == 'Success';
    }
}