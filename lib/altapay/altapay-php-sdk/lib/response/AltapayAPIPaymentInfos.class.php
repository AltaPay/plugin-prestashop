<?php

class AltapayAPIPaymentInfos
{
    /*
    <PaymentInfos>
    <PaymentInfo name="auxkey">aux data (&lt;&#xE6;&#xF8;&#xE5;&gt;)</PaymentInfo>
    </PaymentInfos>
    */
    private $simpleXmlElement;
    private $infos = array();

    /**
     * AltapayAPIPaymentInfos constructor.
     * @param SimpleXmlElement $xml
     */
    public function __construct(SimpleXmlElement $xml)
    {
        $this->simpleXmlElement = $xml;
        if(isset($xml->PaymentInfo)) {
            foreach($xml->PaymentInfo as $paymentInfo)
            {
                $attrs = $paymentInfo->attributes();
                $this->infos[(string)$attrs['name']] = (string)$paymentInfo;
            }
        }
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->infos;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getInfo($key)
    {
        return @$this->infos[$key];
    }

    /**
     * @return SimpleXMLElement an XML representation of the object as it was instantiated
     */
    public function getXmlElement()
    {
        return $this->simpleXmlElement;
    }
}