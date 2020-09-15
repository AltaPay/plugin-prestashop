<?php

class AltapayUnknownMerchantAPIException extends AltapayMerchantAPIException
{
    /**
     * @var Exception
     */
    private $cause;

    /**
     * AltapayUnknownMerchantAPIException constructor.
     * @param Exception|null $cause
     */
    public function __construct(Exception $cause = null)
    {
        parent::__construct("Unknown error".(!is_null($cause) ? ' caused by: '.$cause->getMessage() : ''));
        $this->cause = $cause;
    }

    /**
     * @return Exception|null
     */
    public function getCause()
    {
        return $this->cause;
    }
}