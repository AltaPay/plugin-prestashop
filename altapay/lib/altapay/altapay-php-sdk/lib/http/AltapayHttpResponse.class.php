<?php

/**
 * Class AltapayHttpResponse
 */
class AltapayHttpResponse
{
    const CONNECTION_REFUSED = 'CONNECTION_REFUSED';
    const CONNECTION_TIMEOUT = 'CONNECTION_TIMEOUT';
    const CONNECTION_READ_TIMEOUT = 'CONNECTION_READ_TIMEOUT';
    const CONNECTION_OKAY = 'CONNECTION_OKAY';
    
    private $requestHeader = '';
    private $header = '';
    private $content = '';
    private $info;
    private $errorMessage;
    private $errorNumber;
    private $connectionResult;

    /**
     * @return string
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        if(preg_match('/^Content-Type: (.+)$/m', $this->header, $matches)) {
            return trim($matches[1]);
        }
    }

    /**
     * @return string
     */
    public function getLocationHeader()
    {
        if(preg_match('/^Location: (.+)$/m', $this->header, $matches)) {
            return trim($matches[1]);
        }
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param $header
     */
    public function setHeader($header)
    {
        if(is_array($header)) {
            $header = implode("\r\n", $header);
        }        
        $this->header = $header;
    }

    /**
     * @param $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return string
     */
    public function getRequestHeader()
    {
        return $this->requestHeader;
    }

    /**
     * @param $requestHeader
     */
    public function setRequestHeader($requestHeader)
    {
        $this->requestHeader = $requestHeader;
    }

    /**
     * @return mixed
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * @param $info
     */
    public function setInfo($info)
    {
        $this->info = $info;
    }

    /**
     * @return mixed
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @param $errorMessage
     */
    public function setErrorMessage($errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return mixed
     */
    public function getErrorNumber()
    {
        return $this->errorNumber;
    }

    /**
     * @param $errorNumber
     */
    public function setErrorNumber($errorNumber)
    {
        $this->errorNumber = $errorNumber;
    }

    /**
     * @return mixed
     */
    public function getConnectionResult()
    {
        return $this->connectionResult;
    }

    /**
     * @param $connectionResult
     */
    public function setConnectionResult($connectionResult)
    {
        $this->connectionResult = $connectionResult;
    }

    /**
     * @return mixed
     */
    public function getHttpCode()
    {
        return $this->info['http_code'];
    }

    /**
     * @param $curl
     * @param $header
     * @return int
     */
    public function curlReadHeader(&$curl, $header)
    {
        $this->header .= $header;
        
        return strlen($header);
    }

    /**
     * @param $curl
     * @param $content
     * @return int
     */
    public function curlReadContent(&$curl, $content)
    {
        $this->content .= $content;
        
        return strlen($content);
    }
}