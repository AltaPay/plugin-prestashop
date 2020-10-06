<?php

/**
 * Class AltapayAPICountryOfOrigin
 */
class AltapayAPICountryOfOrigin
{

    const NotSet = 'NotSet';
    const CardNumber = 'CardNumber';
    const BankAccount = 'BankAccount';
    const BillingAddress = 'BillingAddress';
    const RegisteredAddress = 'RegisteredAddress';
    const ShippingAddress = 'ShippingAddress';
    const PayPal = 'PayPal';

    private $country;
    private $source;

    /**
     * AltapayAPICountryOfOrigin constructor.
     * @param SimpleXmlElement $xml
     */
    public function __construct(SimpleXmlElement $xml)
    {
        $this->country = (string)$xml->Country;
        $this->source = (string)$xml->Source;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }
}