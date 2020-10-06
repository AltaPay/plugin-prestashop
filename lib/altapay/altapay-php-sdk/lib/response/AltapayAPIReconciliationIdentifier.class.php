<?php

/**    
    [ReconciliationIdentifier] => SimpleXMLElement Object
        (
            [Id] => 5a9d09b7-4784-4d47-aebc-c0ac63b56722
            [Amount] => 105
            [Type] => captured
            [Date] => 2011-08-31T23:36:14+02:00
        )

 * @author emanuel
 */
class AltapayAPIReconciliationIdentifier
{
    private $id, $amount, $type, $date;

    /**
     * AltapayAPIReconciliationIdentifier constructor.
     * @param SimpleXmlElement $xml
     */
    public function __construct(SimpleXmlElement $xml)
    {
        $this->id = (string)$xml->Id;
        $this->amount = (string)$xml->Amount;
        $this->type = (string)$xml->Type;
        $this->date = (string)$xml->Date;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
}