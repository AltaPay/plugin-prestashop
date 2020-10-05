<?php


/**
SimpleXMLElement Object
(
    [Filename] => fundingDownloadTest
    [ContractIdentifier] => FunctionalTestContractID
    [Shops] => SimpleXMLElement Object
        (
            [Shop] => Altapay Functional Test Shop
        )

    [Acquirer] => TestAcquirer
    [FundingDate] => 2010-12-24
    [Amount] => 0.00 EUR
    [CreatedDate] => 2013-01-19
    [DownloadLink] => http://gateway.dev.altapay.com/merchant.php/API/fundingDownload?id=1
)
 *
 * @author emanuel
 */
class AltapayAPIFunding
{
    private $filename;
    private $contractIdentifier;
    private $shops = array();
    private $acquirer;
    private $fundingDate;
    private $amount;
    private $currency;
    private $createdDate;
    private $downloadLink;
    private $referenceText;

    /**
     * AltapayAPIFunding constructor.
     * @param SimpleXmlElement $xml
     */
    public function __construct(SimpleXmlElement $xml)
    {
        $this->filename = (string)$xml->Filename;
        $this->contractIdentifier = (string)$xml->ContractIdentifier;
        foreach($xml->Shops->Shop as $shop)
        {
            $this->shops[] = (string)$shop;
        }
        $this->acquirer = (string)$xml->Acquirer;
        $this->fundingDate = (string)$xml->FundingDate;
        list($this->amount, $this->currency) = explode(" ", (string)$xml->Amount, 2);
        $this->createdDate = (string)$xml->CreatedDate;
        $this->downloadLink = (string)$xml->DownloadLink;
        $this->referenceText = (string)$xml->ReferenceText;
    }

    /**
     * @return string
     */
    public function getFundingDate()
    {
        return $this->fundingDate;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return mixed
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return string
     */
    public function getDownloadLink()
    {
        return $this->downloadLink;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function getContractIdentifier()
    {
        return $this->contractIdentifier;
    }

    /**
     * @return array
     */
    public function getShops()
    {
        return $this->shops;
    }

    /**
     * @return string
     */
    public function getAcquirer()
    {
        return $this->acquirer;
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
    public function getReferenceText()
    {
        return $this->referenceText;
    }
}