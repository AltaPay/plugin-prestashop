<?php
if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}
require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'response'. DIRECTORY_SEPARATOR .'AltapayAbstractPaymentResponse.class.php';

class AltapayPreauthRecurringResponse extends AltapayAbstractPaymentResponse
{
    public function __construct(SimpleXmlElement $xml)
    {
        parent::__construct($xml);
    }

    /**
     * @param SimpleXmlElement $body
     * @return mixed|void
     */
    protected function parseBody(SimpleXmlElement $body)
    {
        
    }

    /**
     * This payment represent the subscription, it is returned as the subscription it 
     * self might have changed since last time it was used.
     * 
     * @return AltapayAPIPayment
     */
    public function getSubscriptionPayment()
    {
        return isset($this->payments[0]) ? $this->payments[0] : null;
    }
    
    /**
     * This is the payment which was pre-authed.
     * 
     * @return AltapayAPIPayment
     */
    public function getPrimaryPayment()
    {
        return isset($this->payments[1]) ? $this->payments[1] : null;
    }
}