<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', __DIR__);
}

require_once ALTAPAY_API_ROOT.'/AltapayMerchantAPI.class.php';

/**
 * The purpose of this class is to parse the callback parameters and return
 * a usefull response object from which your business logic can get information
 * for the decisions it needs to make.
 * 
 * @author "Emanuel Holm Greisen" <phpclientapi@altapay.com>
 */
class AltapayCallbackHandler
{
    /**
     * @param $xml
     * @return AltapayAbstractPaymentResponse
     * @throws AltapayXmlException
     */
    public function parseXmlResponse($xml)
    {
        if(!($xml instanceof SimpleXMLElement)) {
            $xml = new SimpleXMLElement($xml);
        }
        $this->verifyXml($xml);
        
        // This is not a perfect way of figuring out what kind of response would be appropriate
        // At some point we should have a more direct link between something in the header
        // and the way the result should be interpreted. 
        $authType = $xml->Body[0]->Transactions[0]->Transaction[0]->AuthType;
        switch($authType)
        {
        case 'payment':
        case 'paymentAndCapture':
        case 'recurring':
        case 'subscription':
        case 'verifyCard':
            return new AltapayOmniReservationResponse($xml);
        case 'subscriptionAndCharge':
        case 'recurringAndCapture':
            return new AltapayCaptureRecurringResponse($xml);
        default:
            throw new Exception("Unsupported 'authType': (".$authType.")");                
        }
    }

    /**
     * @param SimpleXMLElement $xml
     * @throws AltapayXmlException
     */
    private function verifyXml(SimpleXMLElement $xml)
    {
        if($xml->getName() != 'APIResponse') {
            throw new AltapayXmlException("Unknown root-tag <".$xml->getName()."> in XML, should have been <APIResponse>", $xml);
        }
        if(!isset($xml->Header)) {
            throw new AltapayXmlException("No <Header> in response", $xml);
        }
        if(!isset($xml->Header->ErrorCode)) {
            throw new AltapayXmlException("No <ErrorCode> in Header of response", $xml);
        }
        if((string)$xml->Header->ErrorCode !== '0') {
            throw new Exception($xml->Header->ErrorMessage.' (Error code: '.$xml->Header->ErrorCode.')');
        }
        if(!isset($xml->Body)) {
            throw new AltapayXmlException("No <Body> in response", $xml);
        }

        if($this->getBodyResult($xml) != 'Error' && !isset($xml->Body[0]->Transactions)) {
            $error = $this->getBodyMerchantErrorMessage($xml);
            throw new AltapayXmlException("No <Transactions> in <Body> of response".($error ? ' ('.$error.')' : ''), $xml);
        }
        if($this->getBodyResult($xml) != 'Error' &&  !isset($xml->Body[0]->Transactions[0]->Transaction)) {
            $error = $this->getBodyMerchantErrorMessage($xml);
            throw new AltapayXmlException("No <Transaction> in <Transactions> of response".($error ? ' ('.$error.')' : ''), $xml);
        }
        if ($this->getBodyResult($xml) == 'Error' && !isset($xml->Body[0]->Transactions[0]->Transaction)) {
            $error = $this->getBodyMerchantErrorMessage($xml);
            throw new AltapayXmlException(($error ? ' ('.$error.')' : ''), $xml);
        }
    }

    /**
     * @param SimpleXMLElement $xml
     * @return bool|string
     */
    private function getBodyMerchantErrorMessage(SimpleXMLElement $xml)
    {
        if(isset($xml->Body[0]->MerchantErrorMessage)) {
            return (string)$xml->Body[0]->MerchantErrorMessage;
        }
        return false;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return bool|string
     */
    private function getBodyResult(SimpleXMLElement $xml)
    {
        if(isset($xml->Body[0]->Result)) {
            return (string)$xml->Body[0]->Result;
        }
        return false;
    }
}