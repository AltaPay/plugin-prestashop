<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}

require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'response'. DIRECTORY_SEPARATOR .'AltapayAbstractResponse.class.php';
require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'response'. DIRECTORY_SEPARATOR .'AltapayAPIPayment.class.php';

/**
 * Class AltapayAbstractPaymentResponse
 */
abstract class AltapayAbstractPaymentResponse extends AltapayAbstractResponse
{
    private $result;
    private $merchantErrorMessage, $cardHolderErrorMessage, $cardHolderMessageMustBeShown;
    protected $payments = array();

    /**
     * AltapayAbstractPaymentResponse constructor.
     * @param SimpleXmlElement $xml
     * @throws Exception
     */
    public function __construct(SimpleXmlElement $xml)
    {
        parent::__construct($xml);
        $this->initFromXml($xml);
    }

    /**
     *
     */
    public function __wakeup()
    {
        $this->initFromXml(new SimpleXmlElement($this->xml));
    }

    /**
     * @param SimpleXmlElement $xml
     * @throws Exception
     */
    private function initFromXml(SimpleXmlElement $xml)
    {
        $this->payments = array();
        if($this->getErrorCode() === '0') {
            $this->result = strval($xml->Body->Result);
            $this->merchantErrorMessage = (string)$xml->Body->MerchantErrorMessage;
            $this->cardHolderErrorMessage = (string)$xml->Body->CardHolderErrorMessage;
            $this->cardHolderMessageMustBeShown = (string)$xml->Body->CardHolderMessageMustBeShown;
            
            $this->parseBody($xml->Body);

            if(isset($xml->Body->Transactions->Transaction)) {
                foreach($xml->Body->Transactions->Transaction as $transactionXml)
                {
                    $this->addPayment(new AltapayAPIPayment($transactionXml));
                }
            }
        }
    }

    /**
     * @param AltapayAPIPayment $payment
     */
    private function addPayment(AltapayAPIPayment $payment)
    {
        $this->payments[] = $payment;
    }
    
    /**
     * @return AltapayAPIPayment[]
     */
    public function getPayments()
    {
        return $this->payments;
    }
    
    /**
     * @return AltapayAPIPayment
     */
    public function getPrimaryPayment()
    {
        return isset($this->payments[0]) ? $this->payments[0] : null;
    }

    /**
     * @return bool
     */
    public function wasSuccessful()
    {
        return $this->getErrorCode() === '0' && $this->result == 'Success';
    }

    /**
     * @return bool
     */
    public function wasDeclined()
    {
        return $this->getErrorCode() === '0' && $this->result == 'Failed';
    }

    /**
     * @return bool
     */
    public function wasErroneous()
    {
        return $this->getErrorCode() !== '0' || $this->result == 'Error';
    }

    /**
     * @return mixed
     */
    public function getMerchantErrorMessage()
    {
        return $this->merchantErrorMessage;
    }

    /**
     * @return mixed
     */
    public function getCardHolderErrorMessage()
    {
        return $this->cardHolderErrorMessage;
    }

    /**
     * @return mixed
     */
    public function getCardHolderMessageMustBeShown()
    {
        return $this->cardHolderMessageMustBeShown;
    }

    /**
     * @param SimpleXmlElement $body
     * @return mixed
     */
    abstract protected function parseBody(SimpleXmlElement $body);
}