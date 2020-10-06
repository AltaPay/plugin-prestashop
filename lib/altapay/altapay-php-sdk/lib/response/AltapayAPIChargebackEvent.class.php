<?php

/**
 * Class AltapayAPIChargebackEvent
 */
class AltapayAPIChargebackEvent
{
    private $date;
    private $type;
    private $reasonCode;
    private $reason;
    private $amount;
    private $currency;
    private $additionalInfo = array();

    /**
     * AltapayAPIChargebackEvent constructor.
     * @param SimpleXmlElement $xml
     * @throws Exception
     */
    public function __construct(SimpleXmlElement $xml)
    {
        $this->date =  new DateTime((string)$xml->Date);
        $this->type = (string)$xml->Type;
        $this->reasonCode = (string)$xml->ReasonCode;
        $this->reason = (string)$xml->Reason;
        $this->amount = (string)$xml->Amount;
        $this->currency = (string)$xml->Currency;

        $additionalInfoXml = @simplexml_load_string((string)$xml->AdditionalInfo);
        foreach($additionalInfoXml->info_element as $infoElement)
        {
            $this->additionalInfo[(string)$infoElement->key] = (string)$infoElement->value;
        }
    }

    /**
     * @return DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param $date
     * @return mixed
     */
    public function setDate($date)
    {
        return $this->date = $date;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param $type
     * @return mixed
     */
    public function setType($type)
    {
        return $this->type = $type;
    }

    /**
     * @return string
     */
    public function getReasonCode()
    {
        return $this->reasonCode;
    }

    /**
     * @param $reasonCode
     * @return mixed
     */
    public function setReasonCode($reasonCode)
    {
        return $this->reasonCode = $reasonCode;
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @param $reason
     * @return mixed
     */
    public function setReason($reason)
    {
        return $this->reason = $reason;
    }

    /**
     * @return string
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param $amount
     * @return mixed
     */
    public function setAmount($amount)
    {
        return $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param $currency
     * @return mixed
     */
    public function setCurrency($currency)
    {
        return $this->currency = $currency;
    }

    /**
     * @return array
     */
    public function getAdditionalInfo()
    {
        return $this->additionalInfo;
    }

    /**
     * @param array $additionalInfo
     * @return array
     */
    public function setAdditionalInfo(array $additionalInfo)
    {
        return $this->additionalInfo = $additionalInfo;
    }
}

