<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}

require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'response'. DIRECTORY_SEPARATOR .'AltapayAbstractPaymentResponse.class.php';

class AltapayCaptureResponse extends AltapayAbstractPaymentResponse
{
    public function __construct(SimpleXmlElement $xml)
    {
        parent::__construct($xml);
    }
    
    protected function parseBody(SimpleXmlElement $body)
    {
        
    }
    
}