<?php

class AltapayXmlException extends Exception
{
    /**
     * @var SimpleXMLElement
     */
    private $xml;

    /**
     * AltapayXmlException constructor.
     * @param $message
     * @param SimpleXMLElement $xml
     */
    public function __construct($message, SimpleXMLElement $xml)
    {
        parent::__construct($message ."\n\n".$xml->asXML());
        $this->xml = $xml;
    }

    /**
     * @return SimpleXMLElement
     */
    public function getXml()
    {
        return $this->xml;
    }
}
