<?php
/**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/Terminal.php';
require_once __DIR__ . '/classes/MerchantAPI.php';
require_once __DIR__ . '/helpers.php';
require_once _PS_MODULE_DIR_ . '/altapay/lib/altapay/altapay-php-sdk/lib/AltapayMerchantAPI.class.php';

class ALTAPAY extends PaymentModule
{
    public $url;
    public $captureStatus;
    public $username;
    public $password;
    private $Mhtml = '';
    private $postErrors = [];
    private $paymentMethodIconDir = 'views/img/payment_icons';
    const ALTAPAY = " {AltaPay} ";

    public function __construct()
    {
        $this->name                   = 'altapay';
        $this->tab                    = 'payments_gateways';
        $this->version                = '3.3.0';
        $this->v16                    = _PS_VERSION_ >= '1.6.1.24';
        $this->v17                    = _PS_VERSION_ >= '1.7.6.9';
        $this->author                 = 'AltaPay A/S';
        $this->is_eu_compatible       = 1;
        $this->ps_versions_compliancy = ['min' => '1.6.1.24', 'max' => '1.7.6.8'];
        $this->currencies             = true;
        $this->currencies_mode        = 'checkbox';
        $this->bootstrap              = true;

        $config = Configuration::getMultiple([
            'ALTAPAY_USERNAME',
            'ALTAPAY_PASSWORD',
            'ALTAPAY_URL',
            'AUTOCAPTURE_STATUSES',
            'ALTAPAY_TERMINAL'
        ]);
        if (isset($config['ALTAPAY_USERNAME'])) {
            $this->username = $config['ALTAPAY_USERNAME'];
        }
        if (isset($config['ALTAPAY_PASSWORD'])) {
            $this->password = $config['ALTAPAY_PASSWORD'];
        }
        if (isset($config['ALTAPAY_URL'])) {
            $this->url = $config['ALTAPAY_URL'];
        }
        if (isset($config['AUTOCAPTURE_STATUSES'])) {
            $this->captureStatus = $config['AUTOCAPTURE_STATUSES'];
        }

        parent::__construct();
        $this->displayName      = $this->l('AltaPay for PrestaShop');
        $this->description      = $this->l('AltaPay: Payments less complicated');
        $this->confirmUninstall = $this->l('Are you sure about removing these details?');

        // Make sure currencies are configured for this payment module
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    /**
     * Called on install
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('payment')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('adminOrder')
            || !$this->registerHook('actionOrderStatusUpdate')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('displayCustomerAccount')
            || !$this->registerHook('actionFrontControllerSetMedia')
            || !$this->registerHook('actionOrderStatusPostUpdate')
        ) {
            return false;
        }

        // This table captures the payment information
        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_order`")) {
            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_order`  TO `" . _DB_PREFIX_ . "altapay_order`  ";
            Db::getInstance()->Execute($sql);

            $sql1 = "ALTER TABLE  `" . _DB_PREFIX_ . "altapay_order`  add column cardExpiryDate varchar(255) NOT NULL AFTER cardBrand";
            Db::getInstance()->Execute($sql1);
            $sql2 = "ALTER TABLE  `" . _DB_PREFIX_ . "altapay_order`  add column paymentTerminal varchar(255) NOT NULL AFTER paymentType";
            Db::getInstance()->Execute($sql2);
        } else {
            Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'altapay_order` (
            `id_order` int(10) unsigned NOT NULL,
            `unique_id` varchar(255) NOT NULL,
            `payment_id` varchar(255) NULL,
            `cardMask` varchar(20) NULL,
            `cardToken` varchar(255) NULL,
            `cardBrand` varchar(255) NULL,
            `cardExpiryDate` varchar(255) NULL,
            `cardCountry` varchar(255) NULL,
            `paymentType` varchar(255) NULL,
            `paymentTerminal` varchar(255) NULL,
            `paymentStatus` varchar(255) NULL,
            `paymentNature` varchar(255) NULL,
            `orderDetails` varchar(255) Null,
            `requireCapture` tinyint(1) NOT NULL DEFAULT \'0\',
            `errorCode` varchar(255) NULL,
            `errorText` varchar(255) NULL,
            `latestError` varchar(255) NULL,
            `date_add` varchar(50) NOT NULL,
            PRIMARY KEY (`id_order`),
            UNIQUE KEY `unique_id` (`unique_id`),
            KEY `cardToken` (`cardToken`)
        ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8');
        }

        /* Will add a new column if it doesn't exist.
        That way we keep the backwards compatibility while adding a new column.*/
        if (!Db::getInstance()->getRow('SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME = \'' . _DB_PREFIX_ . 'altapay_order\' AND COLUMN_NAME = \'latestError\'')) {
            if (!Db::getInstance()->Execute('ALTER TABLE ' . _DB_PREFIX_ .
                                            'altapay_order ADD COLUMN latestError varchar(256) NULL')) {
                $this->context->controller->errors[] = Db::getInstance()->getMsgError();

                return false;
            }
        }

        /* This table captures each of the transaction details.  An order may or may not exist, and a transaction
       can exist multiple times for each cart */
        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_transaction`")) {
            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_transaction`  TO `" . _DB_PREFIX_ . "altapay_transaction`  ";
            Db::getInstance()->Execute($sql);
        } else {
            Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'altapay_transaction` (
            `id` int(255) NOT NULL AUTO_INCREMENT,
            `id_cart` int(255) unsigned NOT NULL,
            `unique_id` varchar(255) NOT NULL,
            `amount` varchar(255) NOT NULL,
            `token` varchar(255) NOT NULL,
            `payment_form_url` TEXT NOT NULL,
            `is_cancelled` tinyint(1) NULL DEFAULT \'0\',
            `date_add` varchar(50) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_id` (`unique_id`),
            KEY `id_cart` (`id_cart`),
            KEY `token` (`token`)
        ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1');
        }


        // This table contains the payment methods / terminals
        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_terminals`")) {
            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_terminals`  TO `" . _DB_PREFIX_ . "altapay_terminals`  ";
            Db::getInstance()->Execute($sql);

            $sql1 = "ALTER TABLE  `" . _DB_PREFIX_ . "altapay_terminals`  add column ccTokenControl_ int(255) NOT NULL AFTER currency";
            Db::getInstance()->Execute($sql1);
        } else {
            Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "altapay_terminals` (
            `id_terminal` int(11) NOT NULL AUTO_INCREMENT,
            `display_name` varchar(255) DEFAULT NULL,
            `icon_filename` varchar(100) DEFAULT NULL,
            `remote_name` varchar(255) DEFAULT NULL,
            `payment_type` varchar(32) DEFAULT NULL,
            `currency` varchar(100) DEFAULT NULL,
            `ccTokenControl_` int(255) NOT NULL DEFAULT '0',
            `position` int(11) NOT NULL DEFAULT '0',
            `active` int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id_terminal`)
        ) ENGINE=" . _MYSQL_ENGINE_ . "  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
        }

        // This table contains count of captured/refunded order lines
        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_orderlines`")) {
            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_orderlines`  TO `" . _DB_PREFIX_ . "altapay_orderlines`  ";
            Db::getInstance()->Execute($sql);
        } else {
            Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "altapay_orderlines` (
		`altapay_payment_id` varchar(36) NOT NULL,
		`product_id` varchar(36) NOT NULL,
		`captured` int(10) NOT NULL DEFAULT 0,
		`refunded` int(10) NOT NULL DEFAULT 0,
		PRIMARY KEY (`altapay_payment_id`,`product_id`)
		) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8");
        }

        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_saved_credit_card`")) {
            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_saved_credit_card`  TO `" . _DB_PREFIX_ . "altapay_saved_credit_card`  ";
            Db::getInstance()->Execute($sql);
        } else {
            Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "altapay_saved_credit_card` (
		`id` mediumint(9) NOT NULL AUTO_INCREMENT,
		`time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		`userID` varchar(200) DEFAULT '' NOT NULL,
		`cardBrand` varchar(200) DEFAULT '' NOT NULL,
		`creditCardNumber` varchar(200) DEFAULT '' NOT NULL,
		`cardExpiryDate` varchar(200) DEFAULT '' NOT NULL,
		`ccToken` varchar(200) DEFAULT '' NOT NULL,
		PRIMARY KEY  (`id`)
		) ENGINE=" . _MYSQL_ENGINE_ . "  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1");
        }

        if (!Db::getInstance()->Execute("ALTER TABLE `" . _DB_PREFIX_ . "altapay_orderlines`
            MODIFY `product_id` varchar(36) NOT NULL")
        ) {
            $this->context->controller->errors[] = Db::getInstance()->getMsgError();

            return false;
        }

        // This table captures the payment information
        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_cartInfo`")) {
            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_cartInfo`  TO `" . _DB_PREFIX_ . "altapay_cartInfo`  ";
            Db::getInstance()->Execute($sql);
        } else {
            Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'altapay_cartInfo` (
			`id_cart` int(10) unsigned NOT NULL,
			`productDetails` varchar(255) NOT NULL,
			`date_add` varchar(50) NOT NULL,
			PRIMARY KEY (`id_cart`)
		) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8');
        }
        $this->createOrderState();

        return true;
    }

    /**
     * Create a new order state
     *
     * @return void
     */
    public function createOrderState()
    {
        if (!Configuration::get('ALTAPAY_OS_PENDING')) {
            $orderState       = new OrderState();
            $orderState->name = [];
            foreach (Language::getLanguages() as $language) {
                $orderState->name[$language['id_lang']] = 'Awaiting payment processing';
            }
            $orderState->color      = '#ffff5a';
            $orderState->logable    = false;
            $orderState->invoice    = false;
            $orderState->hidden     = false;
            $orderState->send_email = false;
            $orderState->shipped    = false;
            $orderState->paid       = false;
            $orderState->delivery   = false;
            if ($orderState->add()) {
                $source      = __DIR__ . '/views/img/os_pending.gif';
                $destination = __DIR__ . '/../../img/os/' . (int)$orderState->id . '.gif';
                copy($source, $destination);
            }
            Configuration::updateValue('ALTAPAY_OS_PENDING', (int)$orderState->id);
        }
    }

    /**
     * Called on uninstall
     * Leaves tables in place in order to not loose history.
     *
     * @return bool
     */
    public function uninstall()
    {
        if (!Configuration::deleteByName('ALTAPAY_USERNAME')
            || !Configuration::deleteByName('ALTAPAY_PASSWORD')
            || !Configuration::deleteByName('ALTAPAY_URL')
            || !parent::uninstall()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Return content for the configuration in back office
     *
     * @return string HTML for display
     */
    public function getContent()
    {
        /* Display: add/edit terminal form */
        if (Tools::isSubmit('updatealtapay_terminals') || Tools::isSubmit('addaltapay')) {
            $this->Mhtml .= $this->renderAddForm();

            return $this->Mhtml;
        } elseif (Tools::isSubmit('payment_actions')) { /* Process: capture, refund, release */
            $this->processPaymentActions();
        } elseif (Tools::isSubmit('savealtapay_terminals')) { /* Process: save terminal */
            if (!$this->postProcessTerminal()) {
                return $this->Mhtml . $this->renderAddForm();
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false) . '&configure='
                                     . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'));
            }
        } elseif (Tools::isSubmit('activealtapay_terminals')) { /* Process: enable/disable */
            $this->postProcessActive();

            return $this->displayAltapay();
        } elseif (Tools::isSubmit('btnSubmit')) { /* Process: save merchant details */
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();

                return $this->displayAltapay();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->Mhtml .= '<div class="alert alert-danger">' . $err . '</div>';
                    $this->Mhtml .= $this->displayAltapay();

                    return $this->Mhtml;
                }
            }
        } else {  /* Default display */
            $this->Mhtml .= $this->displayAltapay();

            return $this->Mhtml;
        }
    }
    /* ******************************** */

    /**
     * Form for adding and editing terminals
     *
     * @return string HTML for display
     */
    public function renderAddForm()
    {
        $currencyOptions = [];
        $terminalNature  = [];
        foreach (Currency::getCurrencies((int)Context::getContext()->language->id) as $currency) {
            $currencyOptions[] = [
                'id'   => $currency->iso_code,
                'name' => $currency->name . ' (' . $currency->iso_code . ')'
            ];
        }
        $iconOptions       = [];
        $fieldsForm        = [];
        $tokenControl      = [];
        $directory         = _PS_MODULE_DIR_ . '/' . $this->name . '/' . $this->paymentMethodIconDir;
        $scanned_directory = array_diff(scandir($directory), ['..', '.', '.DS_Store']);
        foreach ($scanned_directory as $filename) {
            $iconOptions[] = [
                'id'   => $filename,
                'name' => $filename
            ];
        }
        $ccTokenControlOptions = [
            [
                'name' => 'Enable',
                'val'  => 1
            ]
        ];
        $terminals             = $this->getAltapayTerminals();
        foreach ($terminals as $terminal) {
            $terminalNature[] = [
                'id'   => $terminal['nature'],
                'name' => $terminal['nature'],
            ];
        }

        if (_PS_VERSION_ >= '1.7.0.0') {
            $tokenControl = [
                'type'     => 'checkbox',
                'label'    => $this->l('Credit Card Token Control'),
                'desc'     => $this->l('Check this box to enable Credit Card Control for this terminal'),
                'name'     => 'ccTokenControl',
                'id'       => 'ccTokenControl',
                'required' => false,
                'lang'     => false,
                'values'   => [
                    'query' => $ccTokenControlOptions,
                    'id'    => 'id',
                    'name'  => 'name',
                ]
            ];
        }

        $fieldsForm[0]['form']         = [
            'legend'  => [
                'title' => $this->l('Terminal details'),
                'icon'  => 'icon-cog'
            ],
            'input'   => [
                [
                    'type' => 'hidden',
                    'name' => 'id_terminal'
                ],
                [
                    'type'     => 'text',
                    'label'    => $this->l('Display name'),
                    'desc'     => $this->l('What the customer sees'),
                    'name'     => 'display_name',
                    'required' => true
                ],
                [
                    'type'     => 'select',
                    'label'    => $this->l('Icon'),
                    'desc'     => $this->l('Upload icons in size 20x20 pixels to ')
                                  . $this->_path . $this->paymentMethodIconDir,
                    'name'     => 'icon_filename',
                    'required' => true,
                    'options'  => [
                        'query' => $iconOptions,
                        'id'    => 'id',
                        'name'  => 'name'
                    ]
                ],
                [
                    'type'     => 'select',
                    'label'    => $this->l('Altapay terminal'),
                    'desc'     => $this->l('Name of the terminal in the Altapay merchant information interface'),
                    'name'     => 'remote_name',
                    'id'       => 'terminalName',
                    'required' => true,
                    'options'  => [
                        'query' => $this->getAltapayTerminals(),
                        'id'    => 'id',
                        'name'  => 'name',
                    ]
                ],

                [
                    'type'     => 'select',
                    'name'     => 'terminal_nature',
                    'id'       => 'terminalNature',
                    'required' => false,
                    'options'  => [
                        'query' => $terminalNature,
                        'id'    => 'id',
                        'name'  => 'name',
                    ]
                ],

                $tokenControl,

                [
                    'type'     => 'select',
                    'label'    => $this->l('Currency'),
                    'name'     => 'currency',
                    'required' => true,
                    'options'  => [
                        'query' => $currencyOptions,
                        'id'    => 'id',
                        'name'  => 'name'
                    ]
                ],
                [
                    'type'     => 'select',
                    'label'    => $this->l('Payment type'),
                    'desc'     => $this->l('How the payment is handled'),
                    'name'     => 'payment_type',
                    'required' => true,
                    'options'  => [
                        'query' => [
                            [
                                'id_option' => 'payment',
                                'name'      => 'Authorize only'
                            ],
                            [
                                'id_option' => 'paymentAndCapture',
                                'name'      => 'Authorize and capture'
                            ],
                        ],
                        'id'    => 'id_option',
                        'name'  => 'name'
                    ]
                ],
                [
                    'type'     => 'radio',
                    'label'    => $this->l('Status'),
                    'name'     => 'active',
                    'required' => true,
                    'is_bool'  => true,
                    'values'   => [
                        [
                            'id'    => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ],
                        [
                            'id'    => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        ],
                    ],
                ],
            ],
            'submit'  => [
                'title' => $this->l('Save'),
            ],
            'buttons' => [
                [
                    'href'  => AdminController::$currentIndex .
                               '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                    'title' => $this->l('Back to list'),
                    'icon'  => 'process-icon-back'
                ]
            ],
        ];
        $helper                        = new HelperForm();
        $helper->module                = $this;
        $helper->name_controller       = 'altapay';
        $helper->identifier            = $this->identifier;
        $helper->token                 = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex          = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->show_toolbar          = false;
        $helper->table                 = 'altapay_terminals';
        $lang                          = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->id                    = (int)Tools::getValue('id_terminal');
        $helper->submit_action         = 'savealtapay_terminals';
        $helper->tpl_vars              = [
            'fields_value' => (array)$this->getFormValues(),
            'languages'    => (array)$this->context->controller->getLanguages(),
            'id_language'  => (array)$this->context->language->id
        ];

        return $helper->generateForm($fieldsForm);
    }

    /**
     * Query the AltaPay API for available terminals
     *
     * @param bool $objects
     *
     * @return array<int, Terminal>
     */
    private function getAltapayTerminals($objects = false)
    {
        $cgConf                = [];
        $terminalArray         = [];
        $termNature            = '';
        $cgConf['user']        = $this->getAPIUsername();
        $cgConf['password']    = $this->getAPIPassword();
        $cgConf['altapay_url'] = $this->getAltapayUrl();
        $api                   = null;
        try {
            $api               = new AltapayMerchantAPI($cgConf['altapay_url'], $cgConf['user'], $cgConf['password'],
                null);
            $response          = $api->login();
            $responseTerminals = $api->getTerminals();
            $terminals         = $responseTerminals->getTerminals();
            if (!$response->wasSuccessful()) {
                $resErrMsg  = $response->getErrorMessage();
                $resErrCode = $response->getErrorCode();
                throw new AltapayMerchantAPIException(self::ALTAPAY . 'Could not login to the Merchant API: ' . $resErrMsg, $resErrCode);
            }
        } catch (Exception $e) {
            Logger::addLog($e->getMessage(), 3, $e->getCode(), $this->name, $this->id, true);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false) . '&configure='
                                 . $this->name . '&errorMessage&token=' . Tools::getAdminTokenLite('AdminModules'));
            exit();
        }
        foreach ($terminals as $terminal) {
            if (!$objects) {
                $terminalNature = $terminal->getNature();
                if (in_array('CreditCard', $terminalNature, true)) {
                    $termNature = 'CreditCard';
                } elseif (in_array('Invoice', $terminalNature, true)) {
                    $termNature = 'Invoice';
                }
                $terminalArray[$terminal->getTitle()] = [
                    'id'     => $terminal->getTitle(),
                    'name'   => $terminal->getTitle(),
                    'nature' => $termNature
                ];
            } else {
                $terminalArray[$terminal->getTitle()] = $terminal;
            }
        }

        return $terminalArray;
    }


    /* ******************************** */

    /**
     * @return string
     */
    private function getAPIUsername()
    {
        return $this->username;
    }

    /* ******************************** */

    /**
     * @return string
     */
    private function getAPIPassword()
    {
        return $this->password;
    }

    /* ******************************** */

    /**
     * @return string
     */
    public function getAltapayUrl()
    {
        return $this->url;
    } /* ******************************** */

    /**
     * Get field values for add/edit terminal form
     *
     * @return array<string, mixed>
     */
    public function getFormValues()
    {
        $data       = [];
        $idTerminal = (int)Tools::getValue('id_terminal');
        if ($idTerminal > 0) {
            $data = new Terminal($idTerminal);
        } else {
            $def = Terminal::$definition;
            foreach ($def['fields'] as $fieldName => $stuff) {
                $data[$fieldName] = Tools::getValue($fieldName);
            }
        }

        return $data;
    }

    /**
     * Handle payment processing
     * capture, refund, release
     *
     * @return void
     */
    private function processPaymentActions()
    {
        $paymentID         = (int)Tools::getValue('payment_id');
        $action            = Tools::ucfirst(Tools::getValue('action'));
        $goodWillRefund    = false;
        $orderID           = Tools::getValue('ap_order_id');
        $orderLines        = Tools::getValue('ap_order_qty');
        $orderLineGiftWrap = Tools::getValue('ap_order_wrap');

        header('Content-Type: application/json');
        if (!(Tools::getValue('action') && Tools::getValue('payment_id'))) {
            return;
        }
        // Merchant API
        $api = new MerchantAPI();
        try {
            $api->init($this->getAltapayUrl(), $this->getAPIUsername(), $this->getAPIPassword());
        } catch (Exception $e) {
            saveLastErrorMessage($paymentID, $e->getMessage());
            echo json_encode(
                [
                    'status'  => 'error',
                    'message' => 'Connection error: ' . $e->getMessage()
                ]
            );
            exit();
        }
        if ($action === 'Capture') { // CAPTURE
            try {
                $finalOrderLines = $this->populateOrderLinesFromPost($orderLines, $orderLineGiftWrap, $orderID);
                $api->captureAmount($paymentID, $finalOrderLines, Tools::getValue('amount'));
                markAsCaptured($paymentID, $this->getItemCaptureRefundQuantityCount($finalOrderLines));
            } catch (Exception $e) {
                // Save the latest error message in db
                $response = json_decode($e->getMessage(), true);
                saveLastErrorMessage($paymentID, $response['responseMsg']);
                echo json_encode(
                    [
                        'status'  => $response['responseResult'],
                        'message' => 'Could not capture reservation. ' . $response['responseMsg']
                    ]
                );
                exit();
            }
            echo json_encode(
                [
                    'status'  => 'success',
                    'message' => 'Reservation captured successfully'
                ]
            );
            exit();
        } elseif ($action === 'Refund') { // REFUND
            try {
                $refundAmount = Tools::getValue('amount');
                if (Tools::getValue('goodwillrefund') === 'yes') {
                    $goodWillRefund = true;
                }
                $finalOrderLines = $this->populateOrderLinesFromPost(
                    $orderLines,
                    $orderLineGiftWrap,
                    $orderID,
                    $goodWillRefund
                );

                // Add a dummy orderLine array in case no orderLines are parsed in the refund
                if ($finalOrderLines === [] && $goodWillRefund) {
                    $finalOrderLines = $this->createDummyOrderLinesArr($refundAmount);
                }
                $api->refundAmount($paymentID, $finalOrderLines, $refundAmount);
                $refundUpdate = markAsRefund($paymentID, $this->getItemCaptureRefundQuantityCount($finalOrderLines));
                if (!$refundUpdate) {
                    throw new AltapayMerchantAPIException(self::ALTAPAY . 'The refund could not be updated in database');
                }
            } catch (Exception $e) {
                $response = json_decode($e->getMessage(), true);
                saveLastErrorMessage($paymentID, $response['responseMsg']);

                echo json_encode(
                    [
                        'status'  => $response['responseResult'],
                        'message' => 'Could not refund payment. ' . $response['responseMsg']
                    ]
                );
                exit();
            }

            echo json_encode(
                [
                    'status'  => 'success',
                    'message' => 'Payment refunded successfully'
                ]
            );
            exit();
        } elseif ($action === 'Release') { // RELEASE
            try {
                $api->release($paymentID, $action);
                updatePaymentStatus($paymentID, 'Payment Released');
            } catch (Exception $e) {
                $response = json_decode($e->getMessage(), true);
                saveLastErrorMessage($paymentID, $response['responseMsg']);

                echo json_encode(
                    [
                        'status'  => $response['responseResult'],
                        'message' => 'Could not release reservation. ' . $response['responseResult'] . ': '
                                     . $response['responseMsg']
                    ]
                );
                exit();
            }
            echo json_encode(
                [
                    'status'  => 'success',
                    'message' => 'Reservation released successfully'
                ]
            );
            exit();
        }
    }

    /* ******************************** */

    /**
     * Method for generating order lines from order backend
     *
     * @param array      $orderLines
     * @param array|null $orderLineGiftWrap
     * @param string     $orderID
     * @param bool       $goodWillRefund
     *
     * @return array
     */
    private function populateOrderLinesFromPost(
        $orderLines,
        $orderLineGiftWrap = null,
        $orderID,
        $goodWillRefund = false
    ) {
        $i                             = 0;
        $priceAfterDiscountRounded     = 0;
        $priceAfterDiscount            = 0;
        $totalQuantity                 = 0;
        $compensationAmountPerQuantity = 0;
        $altapayOrderLines             = [];
        $discountPercentage            = 0;
        $orderDetail                   = new Order((int)$orderID);
        $productDetailObject           = new OrderDetail;
        $productDetail                 = $productDetailObject->getList($orderID);
        $compensationQuantity          = 0;
        $cartRuleDiscounts             = $this->getCartRuleDiscounts($orderDetail);

        foreach ($orderLines as $key => $orderedQuantity) {
            if ($orderedQuantity > 0) {
                $productDetails = $productDetail[$key];
                if ($productDetails) {
                    $productName                  = $productDetails['product_name'];
                    $reductionPercent             = $productDetails['reduction_percent'];
                    $priceWithoutReductionTaxIncl = $productDetails['unit_price_tax_incl'] / (1 - ($reductionPercent
                                                                                                   / 100));
                    $basePrice                    = $productDetails['original_product_price'];
                    $productQuantity              = $orderedQuantity;
                    $productTax                   = $priceWithoutReductionTaxIncl - $basePrice;
                    $goodsType                    = 'item';
                    // Calculation of base price
                    if ($reductionPercent > 0) {
                        $discountPercentage = $reductionPercent;
                    } else {
                        foreach ($cartRuleDiscounts as $cartRuleDiscount) {
                            if ($productDetails['product_id'] == $cartRuleDiscount['productID']) {
                                $discountPercentage = $cartRuleDiscount['discountPercent'];
                                break;
                            } else {
                                $discountPercentage = 0;
                            }
                        }
                    }
                    if (isset($productDetails['product_attribute_id'])) {
                        $itemID = $productDetails['product_reference'] . '-' . $productDetails['product_attribute_id'];
                    } else {
                        $itemID = $productDetails['product_reference'];
                    }
                    if ($goodWillRefund) {
                        $goodsType = 'refund';
                    }
                    // Looping into the product array to get the difference regarding compensation amount
                    foreach ($productDetail as $proKeys) {
                        $productPriceTaxIncl       = $proKeys['total_price_tax_incl'];
                        $priceAfterDiscountRounded += round($productPriceTaxIncl - ($productPriceTaxIncl
                                                                                    * ($discountPercentage / 100)), 2);
                        $priceAfterDiscount        += $productPriceTaxIncl - ($productPriceTaxIncl
                                                                              * ($discountPercentage / 100));
                        $totalQuantity             += $proKeys['product_quantity'];
                    }
                    // Calculation of Total Compensation Amount
                    $compensationAmount            = round($priceAfterDiscountRounded - $priceAfterDiscount, 2);
                    $compensationAmountPerQuantity = $compensationAmount / $totalQuantity;
                    $totalProductsTaxAmount        = number_format($productTax * $productQuantity, 2, '.', '');
                    // Mandatory keys for orderLines:
                    $altapayOrderLines[$i]['description'] = $productName; // Description of item.
                    $altapayOrderLines[$i]['itemId']      = $itemID; // Item number (SKU)
                    $altapayOrderLines[$i]['quantity']    = $productQuantity;
                    // Unit price excluding sales tax, only two digits.
                    $altapayOrderLines[$i]['unitPrice'] = number_format($basePrice, 2, '.', '');

                    /* Optional keys for orderLines:
                       TaxAmount should be the total tax amount for order line.
                    */
                    $altapayOrderLines[$i]['taxAmount'] = $totalProductsTaxAmount;
                    // The type of order line it is. Should be one of the following: shipment|handling|item|refund
                    $altapayOrderLines[$i]['goodsType'] = $goodsType;
                    $altapayOrderLines[$i]['discount']  = $discountPercentage;
                    $compensationQuantity               += $productQuantity;
                } else {
                    $shippingDiscount = 0;
                    foreach ($cartRuleDiscounts as $cartRuleDiscount) {
                        if ($cartRuleDiscount['shipping']) {
                            $shippingDiscount = 100;
                        }
                    }
                    $orderDetail    = new Order((int)$orderID);
                    $shippingDetail = reset($orderDetail->getShipping());              
                    // Mandatory keys for orderLines:
                    $altapayOrderLines[$i]['description'] = $shippingDetail['carrier_name']; // Description of item.
                    $altapayOrderLines[$i]['itemId']      = $shippingDetail['carrier_name']; // Item number (SKU)
                    $altapayOrderLines[$i]['quantity']    = 1;
                    // Unit price excluding sales tax, only two digits.
                    $altapayOrderLines[$i]['unitPrice'] = $shippingDetail['shipping_cost_tax_excl'];
                    $altapayOrderLines[$i]['discount']  = $shippingDiscount;

                    /* Optional keys for orderLines
                       Taxamount should be the total tax amount for order line.
                    */
                    $altapayOrderLines[$i]['taxAmount'] = $shippingDetail['shipping_cost_tax_incl']
                                                          - $shippingDetail['shipping_cost_tax_excl'];
                    // The type of order line it is. Should be one of the following: shipment|handling|item|refund
                    $altapayOrderLines[$i]['goodsType'] = 'shipment';
                }
            } else {
                continue;
            }
            $i++;
        }
        if ($orderLineGiftWrap && isset($orderLineGiftWrap[0]) && $orderLineGiftWrap[0] == 1) {
            $orderDetail     = new Order((int)$orderID);
            $giftWrappingFee = $orderDetail->total_wrapping;
            // Mandatory keys for orderLines:
            $altapayOrderLines[$i]['description'] = 'Gift Wrap'; // Description of item.
            $altapayOrderLines[$i]['itemId']      = 'giftwrap'; // Item number (SKU)
            $altapayOrderLines[$i]['quantity']    = 1;
            // Unit price excluding sales tax, only two digits.
            $altapayOrderLines[$i]['unitPrice'] = $giftWrappingFee;

            // The type of order line it is. Should be one of the following: shipment|handling|item|refund
            $altapayOrderLines[$i]['goodsType'] = 'item';
            $i++;
        }
        if ($compensationAmountPerQuantity > 0) {
            $altapayOrderLines[$i]['description'] = 'compensation'; // Description of item.
            $altapayOrderLines[$i]['itemId']      = 'comp-1'; // Item number (SKU)
            $altapayOrderLines[$i]['quantity']    = 1;
            // Unit price excluding sales tax, only two digits.
            $altapayOrderLines[$i]['unitPrice'] = $compensationQuantity * $compensationAmountPerQuantity;

            // Optional keys for orderLines:
            $altapayOrderLines[$i]['taxAmount'] = 0; // Taxamount should be the total tax amount for order line.
            // The type of order line it is. Should be one of the following: shipment|handling|item|refund
            $altapayOrderLines[$i]['goodsType'] = 'item';
        }

        return $altapayOrderLines;
    }

    /**
     * Method to get quantity count of captured or refunded items from order backend
     *
     * @param array $orderLines
     *
     * @return array|false
     */
    public function getItemCaptureRefundQuantityCount($orderLines)
    {
        // Get the array of the itemIDs to be captured or refund of each orderline
        $itemIDs = array_column($orderLines, 'itemId');
        // Get the array of quantities of the Items to be captured or refund of each orderline
        $quantities = array_column($orderLines, 'quantity');

        return array_combine($itemIDs, $quantities);
    }
    /* ******************************** */

    /* Handle merchant details form */

    /**
     * Method for creating dummy order lines array in case no order lines selected for refund action
     *
     * @param float $totalAmount
     *
     * @return array
     */
    private function createDummyOrderLinesArr($totalAmount)
    {
        $dummyItemOrderLine = [];
        // Mandatory keys for orderLines:
        $dummyItemOrderLine['description'] = 'Good-will refund';
        $dummyItemOrderLine['itemId']      = '100200';
        $dummyItemOrderLine['quantity']    = 1;
        $dummyItemOrderLine['unitPrice']   = $totalAmount;
        // Optional keys for orderLines:
        $dummyItemOrderLine['taxAmount']  = '0.00';
        $dummyItemOrderLine['taxPercent'] = '0.00';
        $dummyItemOrderLine['goodsType']  = 'refund';

        return $dummyItemOrderLine;
    }
    /* ******************************** */

    /**
     * Handle submission of terminal form
     *
     * @return bool
     */
    private function postProcessTerminal()
    {
        $terminalRemoteName = $_POST['remote_name'];
        $terminalId         = getTerminalId($terminalRemoteName)[0]['id_terminal'];
        // Update existing
        if ($idTerminal = Tools::getValue('id_terminal')) {
            $terminal = new Terminal((int)$idTerminal);
        } // New
        elseif (!($idTerminal = Tools::getValue('id_terminal')) && $terminalId) {
            $idTerminal = $terminalId;
            $terminal   = new Terminal((int)$idTerminal);
        } else {
            $terminal = new Terminal;
        }
        $altapayTerminal = new AltapayTerminal();
        // Currency supported?
        if (!$altapayTerminal->hasCurrency(Tools::getValue('currency'))) {
            $getVal      = Tools::getValue('currency');
            $this->Mhtml .= sprintf('<div class="alert alert-danger">Selected terminal does not support currency %s</div>',
                $getVal);

            return false;
        }

        // Fields
        $fields = [
            'display_name',
            'remote_name',
            'icon_filename',
            'currency',
            'ccTokenControl_',
            'payment_type',
            'active'
        ];
        foreach ($fields as $fieldName) {
            $terminal->{$fieldName} = Tools::getValue($fieldName);
        }

        // Validate
        $result = $terminal->validateFields(false, true);

        if ($result) {
            $terminal->save();

            return true;
        }

        $this->Mhtml .= '<div class="alert alert-danger">' . $result . '</div>';

        return false;
    }

    /**
     * Method for getting terminal status after creation
     *
     * @return void
     */
    private function postProcessActive()
    {
        $idTerminal = Tools::getValue('id_terminal');
        if (!$idTerminal) {
            return null;
        }
        $terminal         = new Terminal((int)$idTerminal);
        $terminal->active = !(bool)$terminal->active;
        $terminal->save();
    }

    /**
     * Info displayed at the top on the module config page
     *
     * @return string HTML for display
     */
    protected function displayAltapay()
    {
        $html = $this->display(__FILE__, 'config.tpl');
        $html .= $this->renderForm();
        $html .= $this->renderTerminalList();

        return $html;
    }

    // hookPayment is utilized in prestashop 1.6

    /**
     * Merchant details form
     *
     * @return string HTML for display
     */
    public function renderForm()
    {
        $statuses            = OrderState::getOrderStates($this->context->language->id);
        $selectCaptureStatus = [];
        foreach ($statuses as $status) {
            $selectCaptureStatus[] = ['key' => $status['id_order_state'], 'name' => $status['name']];
        }

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Merchant details'),
                    'icon'  => 'icon-cog'
                ],
                'input'  => [
                    [
                        'type'     => 'text',
                        'label'    => $this->l('API username'),
                        'name'     => 'ALTAPAY_USERNAME',
                        'required' => true
                    ],
                    [
                        'type'  => 'password',
                        'label' => $this->l('API password'),
                        'desc'  => 'Fill this to change the password',
                        'name'  => 'ALTAPAY_PASSWORD'
                    ],
                    [
                        'type'     => 'text',
                        'label'    => $this->l('API URL'),
                        'desc'     => 'Typically your installation for testing will be 
                        "https://testgateway.altapaysecure.com/" and for production it will be 
                        "https://yourdomain.altapaysecure.com/". 
                        Your Username and Password may be different for testing and live.',
                        'name'     => 'ALTAPAY_URL',
                        'required' => true
                    ],
                    [
                        'type'     => 'select',
                        'label'    => 'Capture on status changed to',
                        'name'     => 'AUTOCAPTURE_STATUSES[]',
                        'class'    => 'chosen',
                        'required' => false,
                        'multiple' => true,
                        'options'  => [
                            'query' => $selectCaptureStatus,
                            'id'    => 'key',
                            'name'  => 'name'
                        ]
                    ],

                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ]
            ],
        ];
        if (isset($_GET['errorMessage'])) {
            $this->Mhtml .= '<div class="alert alert-danger">Incorrect payment gateway account details</div>';
        }
        $helper                           = new HelperForm();
        $helper->show_toolbar             = false;
        $helper->table                    = $this->table;
        $lang                             = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language    = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form                = [];
        $helper->id                       = (int)Tools::getValue('id_carrier');
        $helper->identifier               = $this->identifier;
        $helper->submit_action            = 'btnSubmit';
        $helper->currentIndex             = $this->context->link->getAdminLink('AdminModules', false) . '&configure='
                                            . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token                    = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars                 = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    /**
     * Field values for the merchant details form
     *
     * @return array Array of curent configuration values
     */
    public function getConfigFieldsValues()
    {
        return [
            'ALTAPAY_USERNAME'       => Tools::getValue('ALTAPAY_USERNAME', Configuration::get('ALTAPAY_USERNAME')),
            'ALTAPAY_PASSWORD'       => Tools::getValue('ALTAPAY_PASSWORD', Configuration::get('ALTAPAY_PASSWORD')),
            'ALTAPAY_URL'            => Tools::getValue('ALTAPAY_URL', Configuration::get('ALTAPAY_URL')),
            'AUTOCAPTURE_STATUSES[]' => Tools::getValue('AUTOCAPTURE_STATUSES',
                unserialize(Configuration::get('AUTOCAPTURE_STATUSES'))),
        ];
    }

    /**
     * List of terminals
     *
     * @return string HTML for display
     */
    public function renderTerminalList()
    {
        $fields_list = [
            'id_terminal'     => [
                'title' => $this->l('ID'),
                'width' => 100,
                'type'  => 'text',
            ],
            'display_name'    => [
                'title' => $this->l('Name'),
                'width' => 140,
                'type'  => 'text',
            ],
            'currency'        => [
                'title' => $this->l('Currency'),
                'width' => 50,
                'type'  => 'text',
            ],
            'remote_name'     => [
                'title' => $this->l('Terminal'),
                'width' => 140,
                'type'  => 'text',
            ],
            'ccTokenControl_' => [
                'title'   => $this->l('Token control'),
                'type'    => 'bool',
                'width'   => 'auto',
                'orderby' => false,
                'search'  => false,
            ],
            'payment_type'    => [
                'title' => $this->l('Payment type'),
                'width' => 140,
                'type'  => 'text',
            ],
            'active'          => [
                'title'   => $this->l('Status'),
                'active'  => 'active',
                'type'    => 'bool',
                'width'   => 'auto',
                'orderby' => false,
                'search'  => false,
            ],
        ];

        $helper                      = new HelperList();
        $helper->shopLinkType        = '';
        $helper->simple_header       = false;
        $helper->actions             = ['edit'];
        $helper->identifier          = 'id_terminal';
        $helper->position_identifier = 'position';
        $helper->show_toolbar        = true;
        $helper->toolbar_btn         = [
            'new' => [
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&add' . $this->name
                          . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Add new')
            ]
        ];
        $helper->title               = 'Terminals';
        $helper->table               = 'altapay_terminals';
        $helper->token               = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex        = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->orderBy             = 'id_terminal';
        $helper->orderWay            = 'ASC';
        $content                     = Terminal::getTerminals();

        return $helper->generateList($content, $fields_list);
    }

    /**
     * Validate merchant details form
     *
     * @return void
     */
    private function postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('ALTAPAY_USERNAME')) {
                $this->postErrors[] = $this->l('API username is required');
            } elseif (!Tools::getValue('ALTAPAY_URL')) {
                $this->postErrors[] = $this->l('API URL is required');
            }
            if (!filter_var(Tools::getValue('ALTAPAY_URL'), FILTER_VALIDATE_URL)) {
                $this->postErrors[] = $this->l('Incorrect format for the API URL - Use "https://paymentURL"');
            }
        }
    }

    /**
     * Method for saving gateway configuration details in plugin settings
     *
     * @return void
     */
    private function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('ALTAPAY_USERNAME', Tools::getValue('ALTAPAY_USERNAME'));
            $urlPath = preg_replace('/\s+/', '', Tools::getValue('ALTAPAY_URL'));
            if (Tools::substr($urlPath, -1) !== '/') {
                Configuration::updateValue('ALTAPAY_URL', $urlPath .= '/');
            } elseif (Tools::substr($urlPath, -1) === '/') {
                Configuration::updateValue('ALTAPAY_URL', $urlPath);
            }
            if (Tools::getValue('ALTAPAY_PASSWORD') !== '') {
                Configuration::updateValue('ALTAPAY_PASSWORD', Tools::getValue('ALTAPAY_PASSWORD'));
            }
            if (Tools::getValue('AUTOCAPTURE_STATUSES') !== '') {
                Configuration::updateValue('AUTOCAPTURE_STATUSES', serialize(Tools::getValue('AUTOCAPTURE_STATUSES')));
            }
        }
        $this->Mhtml .= '<div class="alert alert-success"> ' . $this->l('Settings updated') . '</div>';
    }

    /**
     * Displays the error that occurred in the hookActionOrderStatusUpdate method, if any.
     *
     * @return bool
     */
    public function hookBackOfficeHeader()
    {
        $cookie = $this->context->cookie;

        $this->context->controller->addJquery();
        $this->context->controller->addJS($this->_path . 'views/js/form.js', 'all');

        if ($cookie->altapayError) {
            $this->context->controller->errors[] = $cookie->altapayError;

            // Unset the variable:
            $cookie->altapayError = null;
        }

        // Always returns false because there is no template to display
        return false;
    }

    /**
     * Captures a payment when the status is changed to Shipped
     *
     * @param array $params
     *
     * @return string
     */
    public function hookActionOrderStatusUpdate($params)
    {
        $results = $this->selectOrder($params);

        if (!$results) {
            return null;
        }

        $paymentID = $results['payment_id'];

        /** @var OrderStateCore */
        $newStatus = $params['newOrderStatus'];

        $shippedStatus = Configuration::get('PS_OS_SHIPPING');

        if (!$newStatus) {
            return null;
        }
        if ($newStatus->id == $shippedStatus) { // A capture will be made if necessary
            $this->performCapture($paymentID, $params);
        }

        return $results;
    }

    /**
     * @param array $params
     *
     * @return array
     */
    private function selectOrder($params)
    {
        return Db::getInstance()->getRow('SELECT ' . _DB_PREFIX_ . 'altapay_order.*, '
                                         . _DB_PREFIX_ . 'altapay_transaction.amount FROM `'
                                         . _DB_PREFIX_ . 'altapay_order` INNER JOIN ' . _DB_PREFIX_
                                         . 'altapay_transaction ON '
                                         . _DB_PREFIX_ . 'altapay_transaction.unique_id = '
                                         . _DB_PREFIX_ . 'altapay_order.unique_id WHERE id_order='
                                         . $params['id_order']);
    }

    /**
     * Method is being triggered whenever capture action is performed
     *
     * @param string $paymentID
     * @param array  $params
     * @param bool   $captureRemainedAmount
     *
     * @return string
     */
    public function performCapture($paymentID, $params, $captureRemainedAmount = true)
    {
        try {
            $api            = new MerchantAPI();
            $productDetails = new OrderDetail;
            $api->init($this->getAltapayUrl(), $this->getAPIUsername(), $this->getAPIPassword());
            $paymentDetails      = $api->getPaymentDetails($paymentID);
            $orderReservedAmount = $paymentDetails->getReservedAmount();
            $orderCapturedAmount = $paymentDetails->getCapturedAmount();
            $amountToCapture     = $orderReservedAmount - $orderCapturedAmount;
            $giftWrappingFee     = null;
            if ($productDetails->gift) {
                $giftWrappingFee = $productDetails->total_wrapping;
            }
            if ($amountToCapture == 0) {
                return null;
            }
            if ($amountToCapture > 0 && $orderCapturedAmount == 0) {
                $orderLines = $this->populateOrderLinesFromPost(array_column(
                    $productDetails->getList($params['id_order']),
                    'product_quantity'),
                    $giftWrappingFee,
                    $params['id_order'],
                    false
                );
                $api->captureAmount($paymentID, $orderLines, $amountToCapture);
                markAsCaptured($paymentID, $this->getItemCaptureRefundQuantityCount($orderLines));
            } elseif ($amountToCapture > 0 && $orderCapturedAmount > 0 && $captureRemainedAmount) {
                $orderLines = $this->createOrderStatusOrderLines($amountToCapture);
                $api->captureAmount($paymentID, $orderLines, $amountToCapture);
            }
        } catch (Exception $e) {
            $this->returnError($paymentID, $e);
        }
    }

    /**
     * Method being triggered when complete capture is being performed without selecting orderlines
     *
     * @param float $amountToCapture
     *
     * @return array
     */
    public function createOrderStatusOrderLines($amountToCapture)
    {
        $orderLines   = [];
        $orderLines[] = [
            'description' => 'Complete amount Capture',
            'itemId'      => 'Capture-1',
            'quantity'    => 1,
            'unitPrice'   => (float)number_format($amountToCapture, 2, '.', ''),
            'taxAmount'   => 0,
            'goodsType'   => 'handling'
        ];

        return $orderLines;
    }

    /**
     * Captures a payment when the status is changed to Delivered.
     *
     * @param array $params
     *
     * @return mixed
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $results = $this->selectOrder($params);
        if (!$results) {
            return null;
        }
        $orderStatus          = new OrderState($this->context->language->id);
        $configuredStatus     = $orderStatus->getOrderStates($this->context->language->id);
        $allowedOrderStatuses = unserialize(Configuration::get('AUTOCAPTURE_STATUSES'));

        foreach ($allowedOrderStatuses as $orderStatusID) {
            $objectID = array_search($orderStatusID, array_column($configuredStatus, 'id_order_state'));
            if ($objectID) {
                $orderstatusName[] = $configuredStatus[$objectID]['name'];
            }
        }

        $currentOrderStatus = $params['newOrderStatus'];
        if ($currentOrderStatus) {
            $currentOrderStatus = $params['newOrderStatus']->name;
            foreach ($orderstatusName as $captureOrderStatus) {
                if ($currentOrderStatus == $captureOrderStatus) {
                    $paymentID = $results['payment_id'];
                    $this->performCapture($paymentID, $params, false);
                }
            }
        } else {
            return null;
        }

        return $results;
    }

    /**
     * Displays payment info on order detail pages in back office
     *
     * @param $params
     *
     * @return string
     */
    public function hookAdminOrder($params)
    {
        $results = $this->selectOrder($params);

        if (!$results) {
            return false;
        }

        # collect order info
        $orderDetail    = new Order((int)$params['id_order']);
        $productDetail  = $orderDetail->getProducts();
        $shippingDetail = $orderDetail->getShipping();
        if ($orderDetail->gift) {
            $giftWrappingFee = $orderDetail->total_wrapping;
            $this->smarty->assign('ap_gift_wrapping', $giftWrappingFee);
        }
        $orderId   = $params['id_order'];
        $discounts = $this->getCartRuleDiscounts($orderDetail);

        $this->smarty->assign('ap_order_id', $orderId);
        $this->smarty->assign('ap_product_details', $productDetail);
        $this->smarty->assign('ap_shipping_details', $shippingDetail);
        $this->smarty->assign('ap_coupon_discount', $discounts);
        $this->smarty->assign('ap_order_detail', $orderDetail->total_discounts);
        $apOrders     = [];
        $apOrderlines = $this->getOrderActions($results['payment_id']);
        foreach ($productDetail as $product) {
            $apOrders[$product['product_id']] = [
                'captured' => '0',
                'refunded' => '0',
            ];

            foreach ($apOrderlines as $orderline) {
                if ($orderline['product_id'] == $product['product_id']) {
                    $apOrders[$product['product_id']]['captured'] = $orderline['captured'];
                    $apOrders[$product['product_id']]['refunded'] = $orderline['refunded'];
                }
            }
        }
        $this->smarty->assign('ap_orders', $apOrders);

        # collect info from AltaPay - fail gracefully
        $api = new MerchantAPI();
        try {
            $api->init($this->getAltapayUrl(), $this->getAPIUsername(), $this->getAPIPassword());
            $ap_payment = $api->getPaymentDetails($results['payment_id']);
            $this->smarty->assign('ap_paymentinfo', $ap_payment);
        } catch (Exception $e) {
            $this->smarty->assign('ap_error', 'Error: ' . $e->getMessage());
        }

        # prepare for view
        $paymentinfo = [
            'Transaction Date' => Tools::htmlentitiesUTF8(date('F j, Y, g:i a', $results['date_add'])),
            'Transaction ID'   => Tools::htmlentitiesUTF8($results['unique_id']),
            'Payment ID'       => Tools::htmlentitiesUTF8($results['payment_id']),
            'Card Brand'       => Tools::htmlentitiesUTF8($results['cardBrand']),
            'Card Number'      => Tools::htmlentitiesUTF8($results['cardMask']),
            'Card Country'     => Tools::htmlentitiesUTF8($results['cardCountry']),
            'Payment Type'     => Tools::htmlentitiesUTF8($results['paymentType']),
            'Payment Status'   => Tools::htmlentitiesUTF8($results['paymentStatus']),
            'Payment Nature'   => Tools::htmlentitiesUTF8($results['paymentNature']),
            'Latest Error'     => Tools::htmlentitiesUTF8($results['latestError']),
        ];
        $fet         = $this->context->link;
        $tname       = $this->name;
        $this->smarty->assign('paymentinfo', $paymentinfo);
        $this->smarty->assign('payment_id', $results['payment_id']);
        $this->smarty->assign('payment_amount', $results['amount']);
        $this->smarty->assign('payment_captured', !$results['requireCapture']);
        $this->smarty->assign('this_path', $this->_path);
        $this->smarty->assign('ajax_url', $fet->getAdminLink('AdminModules') . '&configure=' . $tname . '&payment_actions');
        $this->smarty->assign('token', Tools::getAdminTokenLite('AdminModules'));

        $this->context->controller->addCSS($this->_path . 'views/css/admin_order.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/admin_order.js');

        return $this->display(__FILE__, '/views/templates/hook/admin_order.tpl');
    }

    /**
     * Method to get order actions from db against payment id
     *
     * @param string $paymentId
     *
     * @return array
     */
    private function getOrderActions($paymentId)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_orderlines` WHERE altapay_payment_id = "' . $paymentId . '"';

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Hook payment is being triggered for prestashop 1.6 for payment processing from checkout page
     *
     * @param array $params
     *
     * @return void
     */
    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        // Check that we can accept this currency (currency restrictions)
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->context->controller->addJquery();
        $this->context->controller->addJS($this->_path . 'js/creditCardFront.js', 'all');
        $this->context->controller->addCSS($this->_path . 'css/payment.css', 'all');

        // Fetch payment methods
        $currency       = $this->getCurrencyForCart($params['cart']);
        $paymentMethods = Terminal::getActiveTerminalsForCurrency($currency->iso_code);

        $this->smarty->assign([
            'this_path'           => $this->_path,
            'this_path_altapay'   => $this->_path,
            'this_path_ssl'       => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'methods'             => $paymentMethods,
            'PS_STOCK_MANAGEMENT' => Configuration::get('PS_STOCK_MANAGEMENT'),
        ]);

        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * Method for checking the current currency in cart with module selected currency
     *
     * @param $cart
     *
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currency_order    = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Method to get currency from cart
     *
     * @param $cart
     *
     * @return Currency
     */
    private function getCurrencyForCart($cart)
    {
        return new Currency($cart->id_currency);
    }

    /**
     * Hook for displaying custom section in  user account page in prestashop
     *
     * @return void
     */
    public function hookDisplayCustomerAccount()
    {
        if (_PS_VERSION_ >= '1.7.0.0') {
            return $this->display(__FILE__, 'savedCreditCards.tpl');
        }
    }

    /**
     * Hook payment is being triggered for prestashop 1.7 for payment processing from checkout page
     *
     * @param array $params
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        $savedCreditCard = [];

        if (!$this->active) {
            return null;
        }
        // Check that we can accept this currency (currency restrictions)
        if (!$this->checkCurrency($params['cart'])) {
            return null;
        }

        if ($this->context->customer->isLogged()) {
            $customerID = $this->context->customer->id;
            $sql        = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_saved_credit_card` WHERE userID =' . $customerID;
            $results    = Db::getInstance()->executeS($sql);

            if ($results) {
                foreach ($results as $result) {
                    $savedCreditCard[] = [
                        'creditCard'     => $result['creditCardNumber'],
                        'cardName'       => $result['cardName'],
                        'cardExpiryDate' => $result['cardExpiryDate']
                    ];
                }
                $this->context->smarty->assign('savedCreditCard', $savedCreditCard);
            }
        }

        $this->context->controller->addCSS($this->_path . 'css/payment.css', 'all');
        // Fetch payment methods
        $currency       = $this->getCurrencyForCart($params['cart']);
        $paymentMethods = Terminal::getActiveTerminalsForCurrency($currency->iso_code);

        $this->smarty->assign(
            $this->getTemplateVarInfos()
        );

        $paymentsOptions = [];
        foreach ($paymentMethods as $paymentMethod) {
            $this->context->smarty->assign('ccTokenControl', $paymentMethod['ccTokenControl_']);
            if ($customerID) {
                $this->context->smarty->assign('customerID', $customerID);
            }
            $actionText     = $this->l('Pay with') . ' ' . $paymentMethod['display_name'];
            $paymentOptions = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $terminal_id    = $paymentMethod['id_terminal'];
            $terminal       = ['method' => $terminal_id];
            $template       = $this->fetch('module:altapay/views/templates/hook/payment17.tpl');

            $paymentOptions->setCallToActionText($actionText)
                           ->setAction($this->context->link->getModuleLink('altapay', 'payment', $terminal))
                           ->setModuleName($this->name)
                           ->setAdditionalInformation($template)
                           ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment_icons/'
                                                         . $paymentMethod['icon_filename']));
            $paymentsOptions[] = $paymentOptions;
        }
        echo '<script src="https://cdn.jsdelivr.net/npm/js-cookie@beta/dist/js.cookie.min.js"></script>';

        return $paymentsOptions;
    }


    /**
     * Hook for binding custom JS files on prestashop front end
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        $this->context->controller->addJquery();
        $this->context->controller->addJS($this->_path . '/views/js/creditCardFront.js', 'all');
    }

    /**
     * Method to get template variable information like path, ssl path, methods
     *
     * @return array
     */
    public function getTemplateVarInfos()
    {
        $cart           = $this->context->cart;
        $currency       = $this->getCurrencyForCart($cart);
        $paymentMethods = Terminal::getActiveTerminalsForCurrency($currency->iso_code);

        return [
            'this_path'           => $this->_path,
            'this_path_altapay'   => $this->_path,
            'this_path_ssl'       => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name
                                     . '/',
            'methods'             => $paymentMethods,
            'PS_STOCK_MANAGEMENT' => Configuration::get('PS_STOCK_MANAGEMENT'),
        ];
    }

    /**
     * Hook triggered at the time of payment returns
     *
     * @param array $params
     *
     * @return void
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        // PrestaShop 1.7 doesn't have the $params['objOrder']
        if (!isset($params['objOrder']) || !is_object($params['objOrder'])) {
            $params['objOrder'] = $params['order'];
        }

        $state   = $params['objOrder']->getCurrentState();
        $results = Db::getInstance()->getRow('SELECT * 
        FROM `' . _DB_PREFIX_ . 'altapay_order` WHERE id_order=' . $params['objOrder']->id);
        if ($state == Configuration::get('PS_OS_PAYMENT') || $state == Configuration::get('PS_OS_OUTOFSTOCK')) {
            $this->smarty->assign([
                'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'status'       => 'ok',
                'unique_id'    => $results['unique_id'],
                'payment_id'   => $results['payment_id'],
                'id_order'     => $params['objOrder']->id
            ]);
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference)) {
                $this->smarty->assign('reference', $params['objOrder']->reference);
            }
        } else {
            $this->smarty->assign([
                'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'status'       => 'open',
                'unique_id'    => $results['unique_id'],
                'payment_id'   => $results['payment_id'],
                'id_order'     => $params['objOrder']->id
            ]);
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference)) {
                $this->smarty->assign('reference', $params['objOrder']->reference);
            }
        }

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    /**
     * Creates the transaction to ALTAPAY which should result in the payment form page URL.
     *
     * @param bool   $payment_method
     * @param string $savedCreditCard
     *
     * @return array If the transaction failed, the array contains information about the failure
     * @throws Exception
     */
    public function createTransaction($payment_method = false, $savedCreditCard)
    {
        // $userType = 'private';
        $customerCreatedDate = null;
        $cart                = $this->context->cart;
        $ccToken             = null;

        // Terminal
        $terminal = $this->getTerminal($payment_method, $this->context->currency->iso_code);
        if (!is_object($terminal)) {
            $message = 'Could not determine remote terminal - possibly currency mismatch';
            Logger::addLog($message, 3, 0, $this->name, $this->id, true);

            return [
                'success'          => false,
                'result'           => 'failure',
                'message'          => $message,
                'additionalInfo'   => $message,
                'payment_form_url' => false,
            ];
        }
        $cgConf = [];
        // Config
        $cgConf['user']         = $this->getAPIUsername();
        $cgConf['password']     = $this->getAPIPassword();
        $cgConf['payment_type'] = $terminal->payment_type;
        $cgConf['altapay_url']  = $this->getAltapayUrl();
        $cgConf['currency']     = $this->context->currency->iso_code;
        $cgConf['language']     = $this->context->language->iso_code;
        $cgConf['uniqueid']     = $cart->id;
        $cgConf['terminal']     = $terminal->remote_name;
        $cgConf['cookie']       = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : null;

        $callback = [];
        // Callbacks
        $callback['callback_form']         = $this->context->link->getModuleLink(
            $this->name,
            'callbackform',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_ok']           = $this->context->link->getModuleLink(
            $this->name,
            'callbackok',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_fail']         = $this->context->link->getModuleLink(
            $this->name,
            'callbackfail',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_open']         = $this->context->link->getModuleLink(
            $this->name,
            'callbackopen',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_notification'] = $this->context->link->getModuleLink(
            $this->name,
            'callbacknotification',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_redirect']     = $this->context->link->getModuleLink(
            $this->name,
            'callbackredirect',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $customer                          = [];
        // Customer info
        $customer['billing_firstname'] = $this->context->customer->firstname;
        $customer['billing_lastname']  = $this->context->customer->lastname;
        $customer['email']             = $this->context->customer->email;

        // Billing address
        $invoice_address = new Address($this->context->cart->id_address_invoice);
        $country         = new Country($invoice_address->id_country);
        $state           = new State($invoice_address->id_state);

        $customer['billing_address'] = $invoice_address->address1;
        $customer['billing_city']    = $invoice_address->city;
        $customer['billing_postal']  = $invoice_address->postcode;
        $customer['billing_region']  = $state->iso_code;
        $customer['billing_country'] = $country->iso_code;

        // Phone
        $invoiceAph                 = $invoice_address->phone;
        $customer['customer_phone'] = $invoice_address->phone ?: $invoice_address->phone_mobile;

        // Shipping address
        $sp_address                     = new Address($this->context->cart->id_address_delivery);
        $sp_country                     = new Country($sp_address->id_country);
        $sp_state                       = new State($sp_address->id_state);
        $customer['shipping_address']   = $sp_address->address1;
        $customer['shipping_city']      = $sp_address->city;
        $customer['shipping_postal']    = $sp_address->postcode;
        $customer['shipping_region']    = $sp_state->iso_code;
        $customer['shipping_country']   = $sp_country->iso_code;
        $customer['shipping_firstname'] = $sp_address->firstname;
        $customer['shipping_lastname']  = $sp_address->lastname;

        //Calling transactionInfo method from helpers file
        $transactionInfo = transactionInfo();

        // Decode the HTML entities from the address data
        $customer = $this->decodeHtmlEntitiesArrayValues($customer);

        $amount = $cart->getOrderTotal(true, Cart::BOTH);

        if ($this->context->customer->isLogged()) {
            $customerCreatedDate = convertDateTimeFormat($this->context->customer->date_add);
        }

        if (!is_null($savedCreditCard)) {
            $sql = "SELECT ccToken FROM `" . _DB_PREFIX_ . 'altapay_saved_credit_card` WHERE creditcardNumber ="' . $savedCreditCard . '"';
            $results = Db::getInstance()->executeS($sql);
             foreach ($results as $result) {
                $ccToken = $result['ccToken'];
            }
        }

        $api = null;
        try {
            $api      = new AltapayMerchantAPI($cgConf['altapay_url'], $cgConf['user'], $cgConf['password'], null);
            $response = $api->login();

            if (!$response->wasSuccessful()) {
                $resErrMsg  = $response->getErrorMessage();
                $resErrCode = $response->getErrorCode();
                throw new AltapayMerchantAPIException(self::ALTAPAY . 'Could not login to the Merchant API: ' . $resErrMsg, $resErrCode);
            }
        } catch (Exception $e) {
            Logger::addLog($e->getMessage(), 3, $e->getCode(), $this->name, $this->id, true);

            return [
                'success'          => false,
                'result'           => 'failure',
                'message'          => 'unable to connect to gateway',
                'additionalInfo'   => $e->getMessage(),
                'payment_form_url' => false,
            ];
        }
        try {
            $response = $api->createPaymentRequest(
                $cgConf['terminal'],
                $cgConf['uniqueid'],
                $amount,
                $cgConf['currency'],
                $cgConf['payment_type'],
                $customer,
                $cgConf['cookie'],
                $cgConf['language'],
                $callback,
                $transactionInfo,
                $this->getOrderLines($cart),
                null,
                $ccToken,
                null,
                null,
                null,
                null,
                null,
                $customerCreatedDate
            );

            if (!$response->wasSuccessful()) {
                $resErrMsg  = $response->getErrorMessage();
                $resErrCode = $response->getErrorCode();
                throw new AltapayMerchantAPIException(self::ALTAPAY . 'Could not create the payment request: ' . $resErrMsg, $resErrCode);
            }

            return [
                'success'          => true,
                'uniqueid'         => $cgConf['uniqueid'],
                'amount'           => $amount,
                'result'           => 'Success',
                'payment_form_url' => $response->getRedirectURL(),
            ];
        } catch (Exception $e) {
            Logger::addLog($e->getMessage(), 3, $e->getCode(), $this->name, $this->id, true);

            return [
                'success'          => false,
                'result'           => 'failure',
                'message'          => 'unable to obtain payment form url',
                'additionalInfo'   => $e->getMessage(),
                'payment_form_url' => false,
            ];
        }
    }

    /**
     * Get the remote name of the terminal associated with
     * this payment method. Will check if currency matches the remote terminal.
     *
     * @param bool $terminal_id
     * @param bool $currency
     *
     * @return Terminal|null
     */
    private function getTerminal($terminal_id = false, $currency = false)
    {
        if ($terminal_id === false || $currency === false) {
            return null;
        }

        $terminal     = new Terminal($terminal_id);
        $terminalId   = $terminal->id_terminal;
        $terminalCurr = $terminal->currency;
        if ($terminalId === null || Tools::strtolower($terminalCurr) !== Tools::strtolower($currency)) {
            return null;
        }

        return $terminal;
    }

    /**
     * @param array $arr
     *
     * @return array
     */
    private function decodeHtmlEntitiesArrayValues($arr)
    {
        if (is_array($arr)) {
            foreach ($arr as $key => $value) {
                $arr[$key] = html_entity_decode($value, ENT_NOQUOTES);
            }
        }

        return $arr;
    }

    /**
     * Used to create the capture or refund quantity count in order to store in the db
     *
     * @param CartCore $cart
     *
     * @return array
     */
    private function getOrderLines($cart)
    {
        $i             = 0;
        $orderSummary  = $cart->getSummaryDetails();
        $orderSubtotal = $orderSummary['total_products_wt'];

        $orderLines              = [];
        $products                = $cart->getProducts();
        $shippingDiscountPercent = 0;
        $freeGiftVoucher         = $this->getCartRuleProperties($cart);
        $vouchers                = $this->getVoucherDetails();
        $cartID                  = $cart->id;
        $orderDetails            = [];

        if (in_array('1', $freeGiftVoucher['freeShippingStatus'], true)) {
            $cartRuleFreeShipping = true;
        } else {
            $cartRuleFreeShipping = false;
        }
        foreach ($products as $p) {
            $rateBasePrice = 1 + ($p['rate'] / 100);
            //Calculation of base price
            $basePrice              = $p['price_without_reduction'] / $rateBasePrice;

            $singleProductTaxAmount = $p['price_without_reduction'] - $basePrice;
            $productID              = $p['id_product'];
            $discountPercent        = 0;

           if ($vouchers) {
                $discountPercent = $this->getVoucherDiscounts(
                    $vouchers,
                    $productID,
                    $discountPercent,
                    $basePrice,
                    $orderSubtotal,
                    $freeGiftVoucher
                );
            } else {
                $discountAmount  = $p['price_without_reduction'] - $p['price_with_reduction'];
                $discountPercent = ($discountAmount / $p['price_without_reduction']) * 100;
            }
            $unitCode = 'unit';
            if ($p['cart_quantity'] > 1) {
                $unitCode = 'units';
            }
            if ($p['id_product_attribute']) {
                $itemID = $p['reference'] . '-' . $p['id_product_attribute'];
            } else {
                $itemID = $p['reference'];
            }

            $productUrl = $this->context->link->getProductLink($p['id_product']);

            $productImageUrl                     = $this->context->link->getImageLink($p['link_rewrite'], $p['id_image'], 'home_default');
            $orderDetails[$i]['productID']       = $productID;
            $orderDetails[$i]['discountPercent'] = $discountPercent;
            if ($cartRuleFreeShipping) {
                $orderDetails[$i]['shipping'] = 'free';
            }

            $orderLines[$i] = $this->createOrderlines(
                $p['name'],
                $itemID,
                $p['cart_quantity'],
                $discountPercent,
                $basePrice,
                $singleProductTaxAmount,
                'item',
                $unitCode,
                $productImageUrl,
                $productUrl
            );
            $i++;
        }

        if ($cart->gift) {
            $orderLines[$i] = $this->createOrderlines('Gift Wrap', 'giftwrap', 1, 0, $cart->getGiftWrappingPrice(), 0, 'item');
            $i++;
        }

        $carrier               = $cart->getSummaryDetails()['carrier'];
        $carrierCostWithTax    = $cart->getTotalShippingCost();
        $carrierCostWithoutTax = $cart->getTotalShippingCost(null, false);
        $carrierTax            = $carrierCostWithTax - $carrierCostWithoutTax;
        if ($cartRuleFreeShipping) {
            $shippingDiscountPercent = 100;
        }
        $orderLines[$i] = $this->createOrderlines(
            $carrier->delay,
            $carrier->name,
            1,
            $shippingDiscountPercent,
            $carrierCostWithoutTax,
            $carrierTax,
            'shipment'
        );

        if ($orderDetails) {
            $orderDetails = json_encode($orderDetails);
            $sql          = 'INSERT INTO ' . _DB_PREFIX_ . 'altapay_cartInfo (id_cart, productDetails, date_add) VALUES ' . "('" . $cartID . "', '"
                            . $orderDetails . "', '" . time() . "')";
            Db::getInstance()->Execute($sql);
        }

        return $orderLines;
    }


    /**
     * Returns the order lines using provided params
     *
     * @param string $productName
     * @param string $itemID
     * @param int    $quantity
     * @param float  $discount
     * @param float  $unitPrice
     * @param float  $taxAmount
     * @param string $goodsType
     * @param string $unitCode
     * @param string $imageUrl
     * @param string $productUrl
     *
     * @return array
     */
    private function createOrderlines(
        $productName,
        $itemID,
        $quantity,
        $discount,
        $unitPrice,
        $taxAmount,
        $goodsType,
        $unitCode,
        $imageUrl,
        $productUrl
    ) {
        // Mandatory keys for orderLines:
        $orderLines['description'] = $productName; // Description of item.
        $orderLines['itemId']      = $itemID; // Item number (SKU)
        $orderLines['quantity']    = $quantity;
        $orderLines['discount']    = $discount;
        // Unit price excluding sales tax, only two digits.
        $orderLines['unitPrice'] = number_format((100 * $unitPrice) / 100, 2, '.', '');

        /**
         * Optional keys for orderLines:
         * Tax amount should be the total tax amount.
         */
        $orderLines['taxAmount']  = number_format($quantity * $taxAmount, 4, '.', '');
        $orderLines['taxPercent'] = number_format(($taxAmount / $unitPrice) * 100, 2, '.', '');
        $orderLines['goodsType']  = $goodsType; // Order line Type - one of the following shipment|handling|item
        if ($unitCode && $imageUrl && $productUrl) {
            $orderLines['unitCode']   = $unitCode;
            $orderLines['imageUrl']   = $imageUrl;
            $orderLines['productUrl'] = $productUrl;
        }

        return $orderLines;
    }

    /**
     * Returns the voucher discounts for each product in the order lines
     *
     * @param array  $vouchers
     * @param string $productID
     * @param float  $discountPercent
     * @param float  $basePrice
     * @param float  $orderSubtotal
     * @param array  $freeGiftVoucher
     *
     * @return float
     */
    private function getVoucherDiscounts(
        $vouchers,
        $productID,
        $discountPercent,
        $basePrice,
        $orderSubtotal,
        $freeGiftVoucher
    ) {
        $discountedAmount          = 0;
        $productPriceAfterDiscount = 0;
        foreach ($vouchers as $key => $voucher) {
            if (in_array($productID, $voucher['products']) || $voucher['products'] === 'all') {
                if (!$discountPercent && $voucher['reductionPercent'] !== '0.00') {
                    $discountPercent           += $voucher['reductionPercent'];
                    $discountedAmount          = $basePrice * ($discountPercent / 100);
                    $productPriceAfterDiscount = $basePrice - $discountedAmount;
                } elseif ($voucher['reductionPercent'] === '0.00' && (empty($freeGiftVoucher['free_gift']) || $freeGiftVoucher['free_gift'])&& $freeGiftVoucher['free_gift'] != $productID) {
                    if ($freeGiftVoucher['free_gift']) {
                        $discountPercent += (($freeGiftVoucher['reductionAmount'] + $freeGiftVoucher[$key]) / ($orderSubtotal + $freeGiftVoucher[$key]) * 100);
                    } else {
                        $discountPercent += ($freeGiftVoucher[$key] / ($orderSubtotal)) * 100;
                    }
                } elseif ($voucher['reductionPercent'] === '0.00' && empty($freeGiftVoucher['free_gift']) || $freeGiftVoucher['free_gift'] == $productID) {
                    $discountPercent += (($freeGiftVoucher['reductionAmount'] + $freeGiftVoucher[$key]) / ($orderSubtotal+$freeGiftVoucher[$key]) * 100);
                } else {
                    $totalDiscountedAmount = $discountedAmount + ($productPriceAfterDiscount * ($voucher['reductionPercent'] / 100));
                    $discountPercent       = ($totalDiscountedAmount / $basePrice) * 100;
                }
            }
        }

        return $discountPercent;
    }


    /**
     * Returns the array of Cart Rule properties like discount percentage, free shipping status and free gift conditions
     *
     * @param CartCore $cart
     *
     * @return array
     */
    private function getCartRuleProperties($cart)
    {
        $voucherProperties = [];
        $freeShipping      = [];
        $cartRules         = $cart->getCartRules();

        foreach ($cartRules as $key => $cartRule) {
            if ($cartRule['gift_product']) {
                $voucherProperties['free_gift'] = $cartRule['gift_product'];
            }
            $cartRuleID                           = $cartRule['id_cart_rule'];
            $freeShipping[]                       = $cartRule['free_shipping'];
            $voucherProperties[$cartRuleID]       = $cartRule['value_real'];
            $voucherProperties['reductionAmount'] = $cartRule['reduction_amount'];
        }
        $voucherProperties['freeShippingStatus'] = $freeShipping;

        return $voucherProperties;
    }

    /**
     * Returns the array with products in cart rule group along with the reduction percentage
     *
     * @param int $couponID
     * @param int $reductionPercent
     *
     * @return array
     */
    private function getCartRuleGroupProducts($couponID, $reductionPercent)
    {
        $cartRuleGroupProducts = [];
        $cartRuleGroups        = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'cart_rule_product_rule_group WHERE id_cart_rule = ' . $couponID);
        foreach ($cartRuleGroups as $cartRuleGroup) {
            $cartRuleGroupProducts['reductionPercent'] = $reductionPercent;
            $cartRuleGroupProducts['products'] = $this->getCartRuleGroupProductIDs($cartRuleGroup['id_product_rule_group']);
        }

        return $cartRuleGroupProducts;
    }

    /**
     * Return IDs of the products in cart rule group
     *
     * @param int $cartRuleGroupID
     *
     * @return array
     */
    private function getCartRuleGroupProductIDs($cartRuleGroupID)
    {
        $productIDs     = [];
        $cartRuleGroups = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'cart_rule_product_rule_value WHERE id_product_rule = ' . $cartRuleGroupID);
        foreach ($cartRuleGroups as $cartRuleGroup) {
            $productIDs[] = $cartRuleGroup['id_item'];
        }

        return $productIDs;
    }

    /**
     * Returns array of applied voucher details from cart
     *
     * @return array
     */
    private function getVoucherDetails()
    {
        $voucherDetails   = [];
        $appliedCartRules = $this->context->cart->getCartRules();
        foreach ($appliedCartRules as $cartRule) {
            $reductionPercent = $cartRule['reduction_percent'];
            if (!empty($cartRule['reduction_product'])) {
                $voucherDetails[$cartRule['id_cart_rule']] = $this->getCartRuleGroupProducts($cartRule['id_cart_rule'], $reductionPercent);
            } else {
                $voucherDetails[$cartRule['id_cart_rule']] = [
                    'reductionPercent' => $reductionPercent,
                    'products'         => 'all'
                ];
            }
        }

        return $voucherDetails;
    }

    /**
     * Returns array of cart rule discounts applied on each product from created order
     *
     * @param array $order
     *
     * @return array
     */
        private function getCartRuleDiscounts($order)
    {
        $cartRuleDiscounts = [];
        $discountPercent = reset(Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'altapay_cartInfo WHERE id_cart = ' . $order->id_cart));
        
        if (isset($discountPercent['productDetails'])) {
            $cartRuleDiscounts = json_decode($discountPercent['productDetails'], true) ?: [];
        }

        return $cartRuleDiscounts;
    }

    /**
     * @param string    $paymentID
     * @param Exception $exception
     *
     * @return string
     */
    public function returnError($paymentID, $exception)
    {
        $cookie = $this->context->cookie;
        // Saves the error in a cookie, to display it if a HTTP redirect occurs:
        $msg                  = json_decode($exception->getMessage());
        $cookie->altapayError = Tools::displayError('Error trying to change the order status: ' . $msg->responseMsg);
        // Saves the error in errors[], to display it if there is no HTTP redirect:
        $this->context->controller->errors[] = $cookie->altapayError;
        // Saves the error in the database. The function is loaded from helpers file.
        saveLastErrorMessage($paymentID, $cookie->altapayError);

        return $cookie->altapayError;
    }
}
