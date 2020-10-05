<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}

require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'http'. DIRECTORY_SEPARATOR .'IAltapayHttpUtils.class.php';

/**
 * Class AltapayCurlBasedHttpUtils
 */
class AltapayCurlBasedHttpUtils implements IAltapayHttpUtils
{
    /**
     * @var int
     */
    private $timeout;
    private $connectionTimeout;
    private $sslVerifyPeer;

    /**
     * AltapayCurlBasedHttpUtils constructor.
     * @param int $timeoutSeconds
     * @param int $connectionTimeout
     * @param bool $sslVerifyPeer
     */
    public function __construct($timeoutSeconds=60, $connectionTimeout=30, $sslVerifyPeer=true)
    {
        $this->timeout = $timeoutSeconds;
        $this->connectionTimeout = $connectionTimeout;
        $this->sslVerifyPeer = $sslVerifyPeer;
    }

    /**
     * @param AltapayHttpRequest $request
     * @return AltapayHttpResponse
     */
    public function requestURL(AltapayHttpRequest $request)
    {
        $curl = curl_init();
        if(!is_null($request->getUser()) && !is_null($request->getPass())) {
            curl_setopt($curl, CURLOPT_USERPWD, $request->getUser().":".$request->getPass());
        }
        
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);
        
        if(!is_null($request->getCookie())) {
            curl_setopt($curl, CURLOPT_COOKIE, $request->getCookie());
        }

        if(!is_null($request->getHeaders()) && count($request->getHeaders()) > 0) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $request->getHeaders());
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->sslVerifyPeer);

        // Container for the header/content
        $httpResponse = new AltapayHttpResponse();
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, array($httpResponse,'curlReadHeader'));
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, array($httpResponse,'curlReadContent'));

        $url = $request->getUrl();
        switch($request->getMethod())
        {
        case 'POST':
            if(!is_null($request->getPostContent())) {
                $data = $request->getPostContent();
            }
            else
            {
                $data = http_build_query($request->getParameters());
            }
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case 'GET':
            $url = $this->appendToUrl($url, $request->getParameters());
            curl_setopt($curl, CURLOPT_HTTPGET, true);
            break;
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);

        curl_exec($curl);

        $requestHeaders = curl_getinfo($curl, CURLINFO_HEADER_OUT);
        $curl_info = curl_getinfo($curl);
        $charsetAndMime = $this->getCharsetAndMime($curl);
        $errorMessage = curl_error($curl);
        $errorNumber = curl_errno($curl);
        curl_close($curl);

        $httpResponse->setRequestHeader($requestHeaders);
        $httpResponse->setInfo($curl_info);
        $httpResponse->setErrorMessage($errorMessage);
        $httpResponse->setErrorNumber($errorNumber);

        // Fix encoding
        if(isset($charsetAndMime['charset'])) {
            // Actually convert the bytes
            if(strtolower($charsetAndMime['charset']) != 'utf-8') {
                $httpResponse->setContent(iconv($charsetAndMime['charset'], 'utf-8', $httpResponse->getContent()));

                // Replace in header
                if($charsetAndMime['mime'] == 'text/html') {
                    $httpResponse->setContent(
                        str_ireplace(
                            'charset='.$charsetAndMime['charset'].'',
                            'charset=utf-8',
                            $httpResponse->getContent()
                        )
                    );
                }
            }
        }

        if($httpResponse->getErrorMessage() == 'connect() timed out!'
            || preg_match('/Connection timed out/i', $httpResponse->getErrorMessage())
        ) {
            $httpResponse->setConnectionResult(AltapayHttpResponse::CONNECTION_TIMEOUT);
        }
        else if($httpResponse->getErrorMessage() == 'couldn\'t connect to host'
            || preg_match('/Connection refused/i', $httpResponse->getErrorMessage())
        ) {
            $httpResponse->setConnectionResult(AltapayHttpResponse::CONNECTION_REFUSED);
        }
        else if(preg_match('/Operation timed out/i', $httpResponse->getErrorMessage())) {
            $httpResponse->setConnectionResult(AltapayHttpResponse::CONNECTION_READ_TIMEOUT);
        }
        else
        {
            $httpResponse->setConnectionResult(AltapayHttpResponse::CONNECTION_OKAY);
        }

        return $httpResponse;
    }

    /**
     * @param $curl
     * @return array
     */
    private function getCharsetAndMime(&$curl)
    {
        /* Get the content type from CURL */
        $content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

        /* Get the MIME type and character set */
        preg_match('@([\w/+]+)(;\s+charset=(\S+))?@i', $content_type, $matches);
        $result = array();
        if (isset($matches[1]) ) {
            $result['mime'] = $matches[1];
        }
        if (isset($matches[3]) ) {
            $result['charset'] = $matches[3];
        }
        return $result;
    }

    /**
     * This method will append the given parameters to the URL. Using a ? or a & depending on the url
     *
     * @param  string $url
     * @param  array  $parameters
     * @return string - the URL with the new parameters appended
     */
    public function appendToUrl($url, array $parameters)
    {
        if(count($parameters) > 0) {
            $append = http_build_query($parameters);
            return $url.(strstr($url, "?") ? "&" : "?").$append;
        }
        return $url;
    }
}