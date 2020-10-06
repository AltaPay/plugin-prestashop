<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}

require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'http'. DIRECTORY_SEPARATOR .'AltapayHttpRequest.class.php';
require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'http'. DIRECTORY_SEPARATOR .'AltapayHttpResponse.class.php';

interface IAltapayHttpUtils
{
    /**
     * @param AltapayHttpRequest $request
     * @return AltapayHttpResponse
     */
    public function requestURL(AltapayHttpRequest $request);
}