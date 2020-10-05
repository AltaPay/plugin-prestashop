<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}

require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'http'. DIRECTORY_SEPARATOR .'IAltapayHttpUtils.class.php';

/**
 * Class AltapayFOpenBasedHttpUtils
 */
class AltapayFOpenBasedHttpUtils implements IAltapayHttpUtils
{
    private $streamState;

    /**
     * AltapayFOpenBasedHttpUtils constructor.
     * @param int $timeoutSeconds
     * @param int $connectionTimeout
     */
    public function __construct($timeoutSeconds=60, $connectionTimeout=30)
    {
        $this->timeout = $timeoutSeconds;
        $this->connectionTimeout = $connectionTimeout;
    }

    /**
     * @param AltapayHttpRequest $request
     * @return AltapayHttpResponse
     */
    public function requestURL(AltapayHttpRequest $request)
    {
        $this->streamState = 'NOT_CONNECTED';
        
        global $http_response_header;
        $context = $this->createContext($request);
        
        $url = ($request->getMethod() == 'GET') ? $this->appendToUrl($request->getUrl(), $request->getParameters()) : $request->getUrl(); 
        $content = @file_get_contents($url, false, $context);
        $response = new AltapayHttpResponse();
        $response->setInfo(array('http_code'=>$this->getHttpCodeFromHeader($http_response_header)));
        if($content !== false) {
            $response->setHeader($http_response_header);
            $response->setContent($content);
            $response->setConnectionResult(AltapayHttpResponse::CONNECTION_OKAY);
        }
        else
        {
            if($this->streamState == 'NOT_CONNECTED') {
                $response->setConnectionResult(AltapayHttpResponse::CONNECTION_REFUSED);
            }
            else
            {
                $response->setConnectionResult(AltapayHttpResponse::CONNECTION_READ_TIMEOUT);
            }
        }
        
        return $response;
    }

    /**
     * @param AltapayHttpRequest $request
     * @return resource
     */
    private function createContext(AltapayHttpRequest $request)
    {
        $args = array(
        'http' => array(
        'method'  => $request->getMethod(),
        'header'  => sprintf("Authorization: Basic %s\r\n", base64_encode($request->getUser().':'.$request->getPass())).
        "Content-type: application/x-www-form-urlencoded\r\n",
        'timeout' => $this->timeout,
        'ignore_errors' => true,
        ),
        );
        if($request->getMethod() == 'POST') {
            $args['http']['content'] = http_build_query($request->getParameters());
        }
        $context = stream_context_create($args);
        stream_context_set_params($context, array('notification' => array($this, 'stream_notification_callback')));
        return $context;
    }

    /**
     * @param $notification_code
     * @param $severity
     * @param $message
     * @param $message_code
     * @param $bytes_transferred
     * @param $bytes_max
     */
    public function stream_notification_callback($notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max)
    {
        switch($notification_code) {
        case STREAM_NOTIFY_FAILURE:
            if(strpos($message, '401 Unauthorized')) {
                $this->streamState = 'AUTH_FAILED';
            }
            break;
        case STREAM_NOTIFY_CONNECT:
            $this->streamState = 'CONNECTED';
            break;
        default:
            //echo "Notification: ".$notification_code."\n";
            break;
        }
    }

    /**
     * @param $http_response_header
     * @return int|mixed
     */
    private function getHttpCodeFromHeader($http_response_header)
    {
        if(is_array($http_response_header) && isset($http_response_header[0])) {
            if(preg_match('/HTTP\/[0-9\.]+ ([0-9]{3}) .*/', $http_response_header[0], $matches)) {
                return $matches[1];
            }
        }
        return 0;
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