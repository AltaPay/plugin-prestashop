<?php

/**
 * Class AltapayAPIChargebackEvents
 */
class AltapayAPIChargebackEvents
{
    private $simpleXmlElement;
    private $chargebackEvents = array();

    /**
     * AltapayAPIChargebackEvents constructor.
     * @param SimpleXmlElement $xml
     * @throws Exception
     */
    public function __construct(SimpleXmlElement $xml)
    {
        $this->simpleXmlElement = $xml;
        if(isset($xml->ChargebackEvent)) {
            foreach($xml->ChargebackEvent as $chargebackEvent)
            {
                $this->chargebackEvents[] = new AltapayAPIChargebackEvent($chargebackEvent);
            }
        }
    }

    /**
     * @return AltapayAPIChargebackEvent
     */
    public function getNewest()
    {
        $newest = null; /* @var $newest AltapayAPIChargebackEvent */
        foreach($this->chargebackEvents as $chargebackEvent) /* @var $chargebackEvent AltapayAPIChargebackEvent */
        {
            if(is_null($newest) || $newest->getDate()->getTimestamp() < $chargebackEvent->getDate()->getTimestamp()) {
                $newest = $chargebackEvent;
            }
        }

        return $newest;
    }

    /**
     * @return SimpleXMLElement an XML representation of the object as it was instantiated
     */
    public function getXmlElement()
    {
        return $this->simpleXmlElement;
    }
}