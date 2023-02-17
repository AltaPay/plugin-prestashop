<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Altapay_Models_Terminal extends ObjectModel
{
    public $id_terminal;
    public $display_name;
    public $remote_name;
    public $payment_type;
    public $currency;
    public $ccTokenControl_;
    public $icon_filename;
    /** @var bool Enabled or disabled */
    public $active;
    public $position;
    public $cvvLess;
    public $shop_id;
    public $custom_message;

    public static $definition = [
        'table' => 'altapay_terminals',
        'primary' => 'id_terminal',
        'fields' => [
            'id_terminal' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId', 'copy_post' => false],
            'display_name' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 255],
            'remote_name' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 255],
            'payment_type' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 32],
            'currency' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 100],
            'ccTokenControl_' => ['type' => self::TYPE_INT, 'required' => true, 'size' => 255],
            'icon_filename' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 100],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId'],
            'cvvLess' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'shop_id' => ['type' => self::TYPE_INT],
            'custom_message' => ['type' => self::TYPE_STRING, 'required' => false, 'size' => 255],
        ],
    ];

    /**
     * Method to get saved terminals from database
     *
     * @param int $shop_id
     *
     * @return array|false|PDOStatement|resource|null
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getTerminals($shop_id = 1)
    {
        try {
            if (filter_var($shop_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new Exception('Invalid shop id');
            }

            $query = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_terminals` WHERE shop_id = ' . (int) $shop_id . ' ORDER BY `id_terminal` ASC';
            $result = Db::getInstance()->executeS($query);

            return $result;
        } catch (Exception $e) {
            $context = Context::getContext();
            if (isset($context->controller) && isset($context->controller->errors)) {
                $context->controller->errors[] = $e->getMessage();
            }
            PrestaShopLogger::addLog($e->getMessage(), 4);
        }
    }

    /**
     * Method to get active terminals from database
     *
     * @param int $shop_id
     *
     * @return array|false|PDOStatement|resource|null
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getActiveTerminals($shop_id = 1)
    {
        try {
            if (filter_var($shop_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new Exception('Invalid shop id');
            }
            $query = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_terminals` WHERE active = 1 AND shop_id = ' . (int) $shop_id . ' ORDER BY `display_name` ASC';
            $result = Db::getInstance()->executeS($query);

            return $result;
        } catch (Exception $e) {
            $context = Context::getContext();
            if (isset($context->controller) && isset($context->controller->errors)) {
                $context->controller->errors[] = $e->getMessage();
            }
            PrestaShopLogger::addLog($e->getMessage(), 4);
        }
    }

    /**
     * Method to get terminals against a given currency and shop id from database
     *
     * @param bool $currency
     * @param int $shop_id
     *
     * @return array|false|PDOStatement|resource|null
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getActiveTerminalsForCurrency($currency = false, $shop_id = 1)
    {
        try {
            if (filter_var($shop_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new Exception('Invalid shop id');
            }
            $query = 'SELECT * FROM `' . _DB_PREFIX_ . "altapay_terminals` WHERE active = 1 AND currency = '" . pSQL($currency) . "' AND shop_id = '" . $shop_id . "' ORDER BY IF(ISNULL(position), \"\", position) ASC, display_name DESC";
            $result = Db::getInstance()->executeS($query);

            return $result;
        } catch (Exception $e) {
            $context = Context::getContext();
            if (isset($context->controller) && isset($context->controller->errors)) {
                $context->controller->errors[] = $e->getMessage();
            }
            PrestaShopLogger::addLog($e->getMessage(), 4);
        }
    }
}
