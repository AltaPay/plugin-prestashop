<?php

/**
 * Class AltapayTerminal
 */
class AltapayTerminal
{
    private $title;
    private $country;
    private $natures = array();
    private $currencies = array();

    /**
     * @param $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }

    /**
     * @param $nature
     */
    public function addNature($nature)
    {
        $this->natures[] = $nature;
    }

    /**
     * @return array
     */
    public function getNature()
    {
        return $this->natures;
    }

    /**
     * @param $currency
     */
    public function addCurrency($currency)
    {
        $this->currencies[] = $currency;
    }

    /**
     * @param $currency
     * @return bool
     */
    public function hasCurrency($currency)
    {
        if (!empty($this->currencies)) {
            return in_array('XXX', $this->currencies) || in_array($currency, $this->currencies);
        }
        else {
            return true;
        }
    }
}