<?php

/**
 * Class AltapayHttpRequest
 */
class AltapayHttpRequest
{
    private $url;
    private $method = 'GET';
    private $parameters = array();
    private $postContent;
    private $user;
    private $pass;
    private $logPaymentId;
    private $logPaymentRequestId;
    private $cookie;
    private $headers = array();

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url; 
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method; 
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters; 
    }

    /**
     * @return mixed
     */
    public function getPostContent()
    {
        return $this->postContent; 
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user; 
    }

    /**
     * @return mixed
     */
    public function getPass()
    {
        return $this->pass; 
    }

    /**
     * @return mixed
     */
    public function getLogPaymentId()
    {
        return $this->logPaymentId; 
    }

    /**
     * @return mixed
     */
    public function getLogPaymentRequestId()
    {
        return $this->logPaymentRequestId; 
    }

    /**
     * @return mixed
     */
    public function getCookie()
    {
        return $this->cookie; 
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers; 
    }

    /**
     * @param $x
     */
    public function setUrl($x)
    {
        $this->url = $x; 
    }

    /**
     * @param $x
     */
    public function setMethod($x)
    {
        $this->method = $x; 
    }

    /**
     * @param $x
     */
    public function setParameters($x)
    {
        $this->parameters = $x; 
    }

    /**
     * @param $x
     */
    public function setPostContent($x)
    {
        $this->postContent = $x; 
    }

    /**
     * @param $x
     */
    public function setUser($x)
    {
        $this->user = $x; 
    }

    /**
     * @param $x
     */
    public function setPass($x)
    {
        $this->pass = $x; 
    }

    /**
     * @param $x
     */
    public function setLogPaymentId($x)
    {
        $this->logPaymentId = $x; 
    }

    /**
     * @param $x
     */
    public function setLogPaymentRequestId($x)
    {
        $this->logPaymentRequestId = $x; 
    }

    /**
     * @param $x
     */
    public function setCookie($x)
    {
        $this->cookie = $x; 
    }

    /**
     * @param $header
     */
    public function addHeader($header)
    {
        $this->headers[] = $header;
    }
}