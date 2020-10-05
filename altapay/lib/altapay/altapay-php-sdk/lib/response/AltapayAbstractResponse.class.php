<?php

/**
 * <APIResponse version="20110831">
 *     <Header>
 *         <Date>2011-08-29T23:48:32+02:00</Date>
 *         <Path>API/xxx</Path>
 *         <ErrorCode>0</ErrorCode>
 *         <ErrorMessage/>
 *     </Header>
 *     <Body>
 *         [.....]
 *     </Body>
 * </APIResponse>
 * Enter description here ...
 *
 * @author emanuel
 */
abstract class AltapayAbstractResponse
{
    protected $xml;
    private $version;
    private $date;
    private $path;
    private $errorCode;
    private $errorMessage;

    /**
     * AltapayAbstractResponse constructor.
     * @param SimpleXmlElement $xml
     */
    public function __construct(SimpleXmlElement $xml)
    {
        $this->xml = $xml->saveXml();
        $this->version = (string)$xml['version'];
        $this->date = (string)$xml->Header->Date;
        $this->path = (string)$xml->Header->Path;
        $this->errorCode = (string)$xml->Header->ErrorCode;
        $this->errorMessage = (string)$xml->Header->ErrorMessage;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @return mixed
     */
    public function getXml()
    {
        return $this->xml;
    }

    /**
     * @return mixed
     */
    public abstract function wasSuccessful();
}