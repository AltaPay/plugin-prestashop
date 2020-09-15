<?php
/**
 * Altapay module for Prestashop
 *
 * Copyright © 2020 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Terminal extends ObjectModel
{
    public $id_terminal;
    public $display_name;
    public $remote_name;
    public $payment_type;
    public $currency;
    public $ccTokenControl;
    public $icon_filename;

    /** @var boolean Enabled or disabled */
    public $active;
    public $position;

    public static $definition = array(
        'table' => 'altapay_terminals',
        'primary' => 'id_terminal',
        'fields' => array(
            'id_terminal' => array('type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId', 'copy_post' => false),
            'display_name' => array('type' => self::TYPE_STRING, 'required' => true, 'size' => 255),
            'remote_name' => array('type' => self::TYPE_STRING, 'required' => true, 'size' => 255),
            'payment_type' => array('type' => self::TYPE_STRING, 'required' => true, 'size' => 32),
            'currency' => array('type' => self::TYPE_STRING, 'required' => true, 'size' => 100),
            'ccTokenControl_' => array('type' => self::TYPE_INT,'required' => true, 'size' => 255),
            'icon_filename' => array('type' => self::TYPE_STRING, 'required' => true, 'size' => 100),
            'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'position' => array('type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId'),
        ),
    );

    public function __construct($id_terminal = null)//, $id_lang = null)
    {
        parent::__construct($id_terminal);
    }

    /**
     * Method to get saved terminals from database
     * @return mixed
     */
    public static function getTerminals()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS("
			SELECT * FROM `"._DB_PREFIX_."altapay_terminals` ORDER BY `id_terminal` ASC
		");
    }

    /**
     * Method to get active terminals from database
     * @return mixed
     */
    public static function getActiveTerminals()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS("
			SELECT * FROM `"._DB_PREFIX_."altapay_terminals` WHERE active = 1 ORDER BY `display_name` ASC
		");
    }

    /**
     * Method to get terminals against a given currency from database
     * @param bool $currency
     * @return mixed
     */
    public static function getActiveTerminalsForCurrency($currency = false)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS("
            SELECT * FROM `"
            ._DB_PREFIX_."altapay_terminals` WHERE active = 1 AND currency = '".$currency."' ORDER BY `display_name` ASC
		");
    }
}
