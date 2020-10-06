<?php

/**
 * Class AltapayAPIPaymentNatureService
 */
class AltapayAPIPaymentNatureService
{
    private $name;
    private $supportsRefunds;
    private $supportsRelease;
    private $supportsMultipleCaptures;
    private $supportsMultipleRefunds;
    private $simpleXmlElement;

    /**
     * AltapayAPIPaymentNatureService constructor.
     * @param SimpleXmlElement $xml
     */
    public function __construct(SimpleXmlElement $xml)
    {
        $this->simpleXmlElement = $xml;
        
        $attrs = $xml->attributes();

        $this->name = strval(@$attrs['name']);
        $this->supportsRefunds = (string)$xml->SupportsRefunds;
        $this->supportsRelease = (string)$xml->SupportsRelease;
        $this->supportsMultipleCaptures = (string)$xml->SupportsMultipleCaptures;
        $this->supportsMultipleRefunds = (string)$xml->SupportsMultipleRefunds;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getSupportsRefunds()
    {
        return $this->supportsRefunds;
    }

    /**
     * @return string
     */
    public function getSupportsRelease()
    {
        return $this->supportsRelease;
    }

    /**
     * @return string
     */
    public function getSupportsMultipleCaptures()
    {
        return $this->supportsMultipleCaptures;
    }

    /**
     * @return string
     */
    public function getSupportsMultipleRefunds()
    {
        return $this->supportsMultipleRefunds;
    }

    /**
     * @return SimpleXmlElement
     */
    public function getXmlElement()
    {
        return $this->simpleXmlElement;
    }
}