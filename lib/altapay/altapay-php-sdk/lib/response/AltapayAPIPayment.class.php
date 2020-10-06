<?php

if(!defined('ALTAPAY_API_ROOT')) {
    define('ALTAPAY_API_ROOT', dirname(__DIR__));
}

require_once ALTAPAY_API_ROOT. DIRECTORY_SEPARATOR .'response'. DIRECTORY_SEPARATOR .'AltapayAPIReconciliationIdentifier.class.php';

/**
   [Transaction] =&gt; SimpleXMLElement Object
       (
           [TransactionId] =&gt; 5
           [AuthType] =&gt; payment
           [CardStatus] =&gt; Valid
           [CreditCardToken] =&gt; ce657182528301c19032840ba6682bdeb5b342d8
           [CreditCardMaskedPan] =&gt; 555555*****5444
           [IsTokenized] =&gt; true
           [ThreeDSecureResult] =&gt; Not_Attempted
           [BlacklistToken] =&gt; 9484bac14dfd5dbb27329f81dcb12ceb8ed7703e
           [ShopOrderId] =&gt; qoute_247
           [Shop] =&gt; Altapay Functional Test Shop
           [Terminal] =&gt; Altapay Dev Terminal
           [TransactionStatus] =&gt; preauth
           [MerchantCurrency] =&gt; 978
           [CardHolderCurrency] =&gt; 978
           [ReservedAmount] =&gt; 14.10
           [CapturedAmount] =&gt; 0
           [RefundedAmount] =&gt; 0
           [RecurringMaxAmount] =&gt; 0
           [CreatedDate] =&gt; 2012-01-06 15:23:12
           [UpdatedDate] =&gt; 2012-01-06 15:23:12
           [PaymentNature] =&gt; CreditCard
           [PaymentSource] = &gt; eCommerce
           [PaymentNatureService] =&gt; SimpleXMLElement Object
               (
                   [@attributes] =&gt; Array
                       (
                           [name] =&gt; TestAcquirer
                       )

                   [SupportsRefunds] =&gt; true
                   [SupportsRelease] =&gt; true
                   [SupportsMultipleCaptures] =&gt; true
                   [SupportsMultipleRefunds] =&gt; true
               )

           [FraudRiskScore] =&gt; 14
           [FraudExplanation] =&gt; For the test fraud service the risk score is always equal mod 101 of the created amount for the payment
           [TransactionInfo] =&gt; SimpleXMLElement Object
               (
               )

           [CustomerInfo] =&gt; SimpleXMLElement Object
               (
                   [UserAgent] =&gt; SimpleXMLElement Object
                       (
                       )

                   [IpAddress] =&gt; 127.0.0.1
               )

           [ReconciliationIdentifiers] =&gt; SimpleXMLElement Object
               (
               )
 *
 * @author emanuel
 */


/**
 * Class AltapayAPIPayment
 */
class AltapayAPIPayment
{
    private $simpleXmlElement;

    // Remember to reflect additions within this->getCurrentXml()
    private $transactionId;
    private $uuid;
    private $authType;
    private $creditCardMaskedPan;
    private $creditCardExpiryMonth;
    private $creditCardExpiryYear;
    private $creditCardToken;
    private $isTokenized;
    private $cardStatus;
    private $shopOrderId;
    private $shop;
    private $terminal;
    private $transactionStatus;
    private $reasonCode;
    private $currency;
    private $addressVerification;
    private $addressVerificationDescription;
    
    private $reservedAmount;
    private $capturedAmount;
    private $refundedAmount;
    private $recurringMaxAmount;
    private $surchargeAmount;

    private $paymentSchemeName;
    private $paymentNature;
    private $paymentSource;
    private $paymentNatureService;

    private $fraudRiskScore;
    private $fraudExplanation;
    private $fraudRecommendation;

    private $createdDate;
    private $updatedDate;

    // Remember to reflect additions within this->getCurrentXml()
    /**
     * @var AltapayAPICustomerInfo
     */
    private $customerInfo;
    
    /**
     * @var AltapayAPIPaymentInfos
     */
    private $paymentInfos;
    
    private $reconciliationIdentifiers = array();

    /**
     * @var AltapayAPIChargebackEvents
     */
    private $chargebackEvents;
    // Remember to reflect additions within this->getCurrentXml()

    /**
     * AltapayAPIPayment constructor.
     * @param SimpleXmlElement $xml
     * @throws Exception
     */
    public function __construct(SimpleXmlElement $xml)
    {
        $this->simpleXmlElement = $xml->saveXML();
        $this->transactionId = (string)$xml->TransactionId;
        $this->uuid = (string)$xml->PaymentId;
        $this->authType = (string)$xml->AuthType;
        $this->creditCardMaskedPan = (string)$xml->CreditCardMaskedPan;
        $this->creditCardExpiryMonth = (string)$xml->CreditCardExpiry->Month;
        $this->creditCardExpiryYear = (string)$xml->CreditCardExpiry->Year;
        $this->creditCardToken = (string)$xml->CreditCardToken;
        $this->isTokenized = (boolean)$xml->IsTokenized;
        $this->cardStatus = (string)$xml->CardStatus;
        $this->shopOrderId = (string)$xml->ShopOrderId;
        $this->shop = (string)$xml->Shop;
        $this->terminal = (string)$xml->Terminal;
        $this->transactionStatus = (string)$xml->TransactionStatus;
        $this->reasonCode = (string)$xml->ReasonCode;
        $this->currency = (string)$xml->MerchantCurrency;
        $this->addressVerification = (string)$xml->AddressVerification;
        $this->addressVerificationDescription = (string)$xml->AddressVerificationDescription;

        $this->reservedAmount = (string)$xml->ReservedAmount;
        $this->capturedAmount = (string)$xml->CapturedAmount;
        $this->refundedAmount = (string)$xml->RefundedAmount;
        $this->recurringMaxAmount = (string)$xml->RecurringMaxAmount;
        $this->surchargeAmount = (String)$xml->SurchargeAmount;

        $this->createdDate = (string)$xml->CreatedDate;
        $this->updatedDate = (string)$xml->UpdatedDate;

        $this->paymentSchemeName = (string)$xml->PaymentSchemeName;
        $this->paymentNature = (string)$xml->PaymentNature;
        $this->paymentSource = (string)$xml->PaymentSource;
        $this->paymentNatureService = new AltapayAPIPaymentNatureService($xml->PaymentNatureService);

        $this->fraudRiskScore = (string)$xml->FraudRiskScore;
        $this->fraudExplanation = (string)$xml->FraudExplanation;
        $this->fraudRecommendation = (string)$xml->FraudRecommendation;

        $this->customerInfo = new AltapayAPICustomerInfo($xml->CustomerInfo);
        $this->paymentInfos = new AltapayAPIPaymentInfos($xml->PaymentInfos);
        $this->chargebackEvents = new AltapayAPIChargebackEvents($xml->ChargebackEvents);

        if(isset($xml->ReconciliationIdentifiers->ReconciliationIdentifier)) {
            foreach($xml->ReconciliationIdentifiers->ReconciliationIdentifier as $reconXml)
            {
                $this->reconciliationIdentifiers[] = new AltapayAPIReconciliationIdentifier($reconXml);
            }
        }
    }

    /**
     * @return bool
     */
    public function mustBeCaptured()
    {
        return $this->capturedAmount == '0';
    }

    /**
     * @return string
     */
    public function getCurrentStatus()
    {
        return $this->transactionStatus;
    }

    /**
     * @return bool
     */
    public function isReleased()
    {
        return $this->getCurrentStatus() == 'released';
    }
    
    /**
     * @return AltapayAPIReconciliationIdentifier
     */
    public function getLastReconciliationIdentifier()
    {
        return $this->reconciliationIdentifiers[count($this->reconciliationIdentifiers) - 1];
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->transactionId;
    }

    /**
     * @return string
     */
    public function getPaymentId()
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getAuthType()
    {
        return $this->authType;
    }

    /**
     * @return string
     */
    public function getShopOrderId()
    {
        return $this->shopOrderId;
    }

    /**
     * @return string
     */
    public function getMaskedPan()
    {
        return $this->creditCardMaskedPan;
    }

    /**
     * @return string
     */
    public function getCreditCardExpiryMonth()
    {
        return $this->creditCardExpiryMonth;
    }

    /**
     * @return string
     */
    public function getCreditCardExpiryYear()
    {
        return $this->creditCardExpiryYear;
    }

    /**
     * @return string
     */
    public function getCreditCardToken()
    {
        return $this->creditCardToken;
    }

    /**
     * @return bool
     */
    public function isTokenized()
    {
        return $this->isTokenized;
    }

    /**
     * @return string
     */
    public function getCardStatus()
    {
        return $this->cardStatus;
    }

    /**
     * @return string
     */
    public function getPaymentNature()
    {
        return $this->paymentNature;
    }

    /**
     * @return string
     */
    public function getPaymentSource()
    {
        return $this->paymentSource;
    }

    /**
     * @return string
     */
    public function getPaymentSchemeName()
    {
        return $this->paymentSchemeName;
    }
    
    /**
     * @return AltapayAPIPaymentNatureService
     */
    public function getPaymentNatureService()
    {
        return $this->paymentNatureService;
    }

    /**
     * @return string
     */
    public function getFraudRiskScore()
    {
        return $this->fraudRiskScore;
    }

    /**
     * @return string
     */
    public function getFraudExplanation()
    {
        return $this->fraudExplanation;
    }

    /**
     * @return string
     */
    public function getFraudRecommendation()
    {
        return $this->fraudRecommendation;
    }

    /**
     * @return AltapayAPICustomerInfo
     */
    public function getCustomerInfo()
    {
        return $this->customerInfo;
    }

    /**
     * @param $keyName
     * @return mixed
     */
    public function getPaymentInfo($keyName)
    {
        return $this->paymentInfos->getInfo($keyName);
    }

    /**
     * @return string
     */
    public function getReasonCode()
    {
        return $this->reasonCode;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return string
     */
    public function getReservedAmount()
    {
        return $this->reservedAmount;
    }

    /**
     * @return string
     */
    public function getCapturedAmount()
    {
        return $this->capturedAmount; 
    }

    /**
     * @return string
     */
    public function getRefundedAmount()
    {
        return $this->refundedAmount;
    }

    /**
     * @return string
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @return string
     */
    public function getUpdatedDate()
    {
        return $this->updatedDate;
    }

    /**
     * @return AltapayAPIChargebackEvents
     */
    public function getChargebackEvents()
    {
        return $this->chargebackEvents;
    }

    /**
     * Returns an XML representation of the payment as used to instantiate the object. It does not reflect any subsequent changes.
     *
     * @see    AltapayAPIPayment::getCurrentXml() for an up-to-date XML representation of the payment
     * @return SimpleXMLElement an XML representation of the object as it was instantiated
     */
    public function getXml()
    {
        return $this->simpleXmlElement;
    }
    
    /**
     * Returns an up-to-date XML representation of the payment
     *
     * @see    AltapayAPIPayment::getXml() for an XML representation of the payment as used to instantiate the object
     * @return SimpleXMLElement an up-to-date XML representation of the payment
     */
    public function getCurrentXml()
    {
        $simpleXmlElement = new SimpleXMLElement('<AltapayAPIPayment></AltapayAPIPayment>');
        
        $simpleXmlElement->addChild('TransactionId', $this->transactionId);
        $simpleXmlElement->addChild('PaymentId', $this->uuid);
        $simpleXmlElement->addChild('AuthType', $this->authType);
        $simpleXmlElement->addChild('CreditCardMaskedPan', $this->creditCardMaskedPan);
        $creditCardExpiryXml = $simpleXmlElement->addChild('CreditCardExpiry');
        $creditCardExpiryXml->addChild('CreditCardExpiryMonth', $this->creditCardExpiryMonth);
        $creditCardExpiryXml->addChild('CreditCardExpiryYear', $this->creditCardExpiryYear);
        $simpleXmlElement->addChild('CreditCardToken', $this->creditCardToken);
        $simpleXmlElement->addChild('CardStatus', $this->cardStatus);
        $simpleXmlElement->addChild('ShopOrderId', $this->shopOrderId);
        $simpleXmlElement->addChild('Shop', $this->shop);
        $simpleXmlElement->addChild('Terminal', $this->terminal);
        $simpleXmlElement->addChild('TransactionStatus', $this->transactionStatus);
        $simpleXmlElement->addChild('ReasonCode', $this->reasonCode);
        $simpleXmlElement->addChild('MerchantCurrency', $this->currency);
        $simpleXmlElement->addChild('AddressVerification', $this->addressVerification);
        $simpleXmlElement->addChild('AddressVerificationDescription', $this->addressVerificationDescription);
        
        $simpleXmlElement->addChild('ReservedAmount', $this->reservedAmount);
        $simpleXmlElement->addChild('CapturedAmount', $this->capturedAmount);
        $simpleXmlElement->addChild('RefundedAmount', $this->refundedAmount);
        $simpleXmlElement->addChild('RecurringMaxAmount', $this->recurringMaxAmount);
        $simpleXmlElement->addChild('SurchargeAmount', $this->surchargeAmount);
        
        $simpleXmlElement->addChild('PaymentSchemeName', $this->paymentSchemeName);
        $simpleXmlElement->addChild('PaymentNature', $this->paymentNature);
        $simpleXmlElement->addChild('PaymentSource', $this->paymentSource);
        $simpleXmlElement->addChild('PaymentNatureService', $this->paymentNatureService->getXmlElement());
        
        $simpleXmlElement->addChild('FraudRiskScore', $this->fraudRiskScore);
        $simpleXmlElement->addChild('FraudExplanation', $this->fraudExplanation);
        $simpleXmlElement->addChild('FraudRecommendation', $this->fraudRecommendation);
        
        $simpleXmlElement->addChild('AltapayAPICustomerInfo', $this->customerInfo->getXmlElement());
        $simpleXmlElement->addChild('AltapayAPIPaymentInfos', $this->paymentInfos->getXmlElement());
        $simpleXmlElement->addChild('AltapayAPIChargebackEvents', $this->chargebackEvents->getXmlElement());
        $simpleXmlElement->addChild("CreatedDate", $this->getCreatedDate());
        $simpleXmlElement->addChild("UpdatedDate", $this->getUpdatedDate());
        
        return $simpleXmlElement;
    }

    /**
     * @return string
     */
    public function getSurchargeAmount()
    {
        return $this->surchargeAmount;
    }

    /**
     * @return string
     */
    public function getTerminal()
    {
        return $this->terminal;
    }

    /**
     * Gives the amount of the good(s) without surcharge.
     */
    public function getInitiallyAmount()
    {
        return bcsub($this->reservedAmount, $this->surchargeAmount, 2);
    }

    /**
     * @return string
     */
    public function getAddressVerification()
    {
        return $this->addressVerification;
    }

    /**
     * @return string
     */
    public function getAddressVerificationDescription()
    {
        return $this->addressVerificationDescription;
    }
}