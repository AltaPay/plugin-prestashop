<?php

/**
 * Class AltapayAPITransactionsRequest
 */
class AltapayAPITransactionsRequest
{
    private $shop;
    private $terminal;
    private $transaction;
    private $transactionId;
    private $shopOrderId;
    private $paymentStatus;
    private $reconciliationIdentifier;
    private $acquirerReconciliationIdentifier;

    /**
     * @return mixed
     */
    public function getShop()
    {
        return $this->shop;
    }

    /**
     * @param $shop
     */
    public function setShop($shop)
    {
        $this->shop = $shop;
    }

    /**
     * @return mixed
     */
    public function getTerminal()
    {
        return $this->terminal;
    }

    /**
     * @param $terminal
     */
    public function setTerminal($terminal)
    {
        $this->terminal = $terminal;
    }

    /**
     * @return mixed
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @param $transaction
     */
    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * @return mixed
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @param $transactionId
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
    }

    /**
     * @return mixed
     */
    public function getShopOrderId()
    {
        return $this->shopOrderId;
    }

    /**
     * @param $shopOrderId
     */
    public function setShopOrderId($shopOrderId)
    {
        $this->shopOrderId = $shopOrderId;
    }

    /**
     * @return mixed
     */
    public function getPaymentStatus()
    {
        return $this->paymentStatus;
    }

    /**
     * @param $paymentStatus
     */
    public function setPaymentStatus($paymentStatus)
    {
        $this->paymentStatus = $paymentStatus;
    }

    /**
     * @return mixed
     */
    public function getReconciliationIdentifier()
    {
        return $this->reconciliationIdentifier;
    }

    /**
     * @param $reconciliationIdentifier
     */
    public function setReconciliationIdentifier($reconciliationIdentifier)
    {
        $this->reconciliationIdentifier = $reconciliationIdentifier;
    }

    /**
     * @return mixed
     */
    public function getAcquirerReconciliationIdentifier()
    {
        return $this->acquirerReconciliationIdentifier;
    }

    /**
     * @param $acquirerReconciliationIdentifier
     */
    public function setAcquirerReconciliationIdentifier($acquirerReconciliationIdentifier)
    {
        $this->acquirerReconciliationIdentifier = $acquirerReconciliationIdentifier;
    }

    /**
     * @return array
     */
    public function asArray()
    {
        $array = array();
        if (!is_null($this->shop)) {
            $array['shop'] = $this->shop;
        }
        if (!is_null($this->terminal)) {
            $array['terminal'] = $this->terminal;
        }
        if (!is_null($this->transaction)) {
            $array['transaction'] = $this->transaction;
        }
        if (!is_null($this->transactionId)) {
            $array['transaction_id'] = $this->transactionId;
        }
        if (!is_null($this->shopOrderId)) {
            $array['shop_orderid'] = $this->shopOrderId;
        }
        if (!is_null($this->paymentStatus)) {
            $array['payment_status'] = $this->paymentStatus;
        }
        if (!is_null($this->reconciliationIdentifier)) {
            $array['reconciliation_identifier'] = $this->reconciliationIdentifier;
        }
        if (!is_null($this->acquirerReconciliationIdentifier)) {
            $array['acquirer_reconciliation_identifier'] = $this->acquirerReconciliationIdentifier;
        }

        return $array;
    }
}
