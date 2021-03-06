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

require_once __DIR__ . '/vendor/autoload.php';

class ALTAPAY extends PaymentModule
{
    public $url;
    public $captureStatus;
    public $username;
    public $password;
    private $Mhtml = '';
    private $postErrors = [];
    private $paymentMethodIconDir = 'views/img/payment_icons';
    public $is_eu_compatible;
    public $fields_form;
    private $api_error = '';

    public function __construct()
    {
        $this->name = 'altapay';
        $this->tab = 'payments_gateways';
        $this->version = '3.3.6';
        $this->author = 'AltaPay A/S';
        $this->is_eu_compatible = 1;
        $this->ps_versions_compliancy = ['min' => '1.6.1.24', 'max' => '1.7.6.4'];
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        $config = Configuration::getMultiple([
            'AUTOCAPTURE_STATUSES',
            'ALTAPAY_TERMINAL',
        ]);
        if (isset($config['AUTOCAPTURE_STATUSES'])) {
            $this->captureStatus = $config['AUTOCAPTURE_STATUSES'];
        }

        parent::__construct();
        $this->displayName = $this->l('AltaPay for PrestaShop');
        $this->description = $this->l('AltaPay: Payments less complicated');
        $this->confirmUninstall = $this->l('Are you sure about removing these details?');

        // Make sure currencies are configured for this payment module
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    /**
     * Called on install
     *
     * @return bool|string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
        if (Db::getInstance()->Execute('SELECT 1 FROM `' . _DB_PREFIX_ . 'valitor_order`')) {
            $sql = 'RENAME TABLE  `' . _DB_PREFIX_ . 'valitor_order`  TO `' . _DB_PREFIX_ . 'altapay_order`  ';
            Db::getInstance()->Execute($sql);

            $sql1 = 'ALTER TABLE  `' . _DB_PREFIX_ . 'altapay_order`  add column cardExpiryDate varchar(255) NOT NULL AFTER cardBrand';
            Db::getInstance()->Execute($sql1);
            $sql2 = 'ALTER TABLE  `' . _DB_PREFIX_ . 'altapay_order`  add column paymentTerminal varchar(255) NOT NULL AFTER paymentType';
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
        if (Db::getInstance()->Execute('SELECT 1 FROM `' . _DB_PREFIX_ . 'valitor_transaction`')) {
            $sql = 'RENAME TABLE  `' . _DB_PREFIX_ . 'valitor_transaction`  TO `' . _DB_PREFIX_ . 'altapay_transaction`  ';
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

        if (!Db::getInstance()->Execute('SELECT terminal_name from `' . _DB_PREFIX_ . 'altapay_transaction`')) {
            if (!Db::getInstance()->Execute('ALTER TABLE `' . _DB_PREFIX_ .
                                            'altapay_transaction` ADD COLUMN terminal_name varchar(255) NULL AFTER amount')) {
                $this->context->controller->errors[] = Db::getInstance()->getMsgError();

                return false;
            }
        }
        // This table contains the payment methods / terminals
        if (Db::getInstance()->Execute('SELECT 1 FROM `' . _DB_PREFIX_ . 'valitor_terminals`')) {
            $sql = 'RENAME TABLE  `' . _DB_PREFIX_ . 'valitor_terminals`  TO `' . _DB_PREFIX_ . 'altapay_terminals`  ';
            Db::getInstance()->Execute($sql);

            $sql1 = 'ALTER TABLE  `' . _DB_PREFIX_ . 'altapay_terminals`  add column ccTokenControl_ int(255) NOT NULL AFTER currency';
            Db::getInstance()->Execute($sql1);
        } else {
            Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'altapay_terminals` (
            `id_terminal` int(11) NOT NULL AUTO_INCREMENT,
            `display_name` varchar(255) DEFAULT NULL,
            `icon_filename` varchar(100) DEFAULT NULL,
            `remote_name` varchar(255) DEFAULT NULL,
            `payment_type` varchar(32) DEFAULT NULL,
            `currency` varchar(100) DEFAULT NULL,
            `ccTokenControl_` int(255) NOT NULL DEFAULT \'0\',
            `position` int(11) NOT NULL DEFAULT \'0\',
            `active` int(11) NOT NULL DEFAULT \'0\',
            PRIMARY KEY (`id_terminal`)
        ) ENGINE=' . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1');
        }

        if (!Db::getInstance()->Execute('SELECT cvvLess from `' . _DB_PREFIX_ . 'altapay_terminals`')) {
            if (!Db::getInstance()->Execute('ALTER TABLE `' . _DB_PREFIX_ . 'altapay_terminals` ADD COLUMN cvvLess BOOLEAN NOT NULL DEFAULT 0')) {
                $this->context->controller->errors[] = Db::getInstance()->getMsgError();

                return false;
            }
        }
        // This table contains count of captured/refunded order lines
        if (Db::getInstance()->Execute('SELECT 1 FROM `' . _DB_PREFIX_ . 'valitor_orderlines`')) {
            $sql = 'RENAME TABLE  `' . _DB_PREFIX_ . 'valitor_orderlines`  TO `' . _DB_PREFIX_ . 'altapay_orderlines`  ';
            Db::getInstance()->Execute($sql);
        } else {
            Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'altapay_orderlines` (
		`altapay_payment_id` varchar(36) NOT NULL,
		`product_id` varchar(36) NOT NULL,
		`captured` int(10) NOT NULL DEFAULT 0,
		`refunded` int(10) NOT NULL DEFAULT 0,
		PRIMARY KEY (`altapay_payment_id`,`product_id`)
		) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8');
        }

        if (Db::getInstance()->Execute('SELECT 1 FROM `' . _DB_PREFIX_ . 'valitor_saved_credit_card`')) {
            $sql = 'RENAME TABLE  `' . _DB_PREFIX_ . 'valitor_saved_credit_card`  TO `' . _DB_PREFIX_ . 'altapay_saved_credit_card`  ';
            Db::getInstance()->Execute($sql);
        } else {
            Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . "altapay_saved_credit_card` (
		`id` mediumint(9) NOT NULL AUTO_INCREMENT,
		`time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		`userID` varchar(200) DEFAULT '' NOT NULL,
		`cardBrand` varchar(200) DEFAULT '' NOT NULL,
		`creditCardNumber` varchar(200) DEFAULT '' NOT NULL,
		`cardExpiryDate` varchar(200) DEFAULT '' NOT NULL,
		`ccToken` varchar(200) DEFAULT '' NOT NULL,
		PRIMARY KEY  (`id`)
		) ENGINE=" . _MYSQL_ENGINE_ . '  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1');
        }

        if (!Db::getInstance()->Execute('ALTER TABLE `' . _DB_PREFIX_ . 'altapay_orderlines`
            MODIFY `product_id` varchar(36) NOT NULL')
        ) {
            $this->context->controller->errors[] = Db::getInstance()->getMsgError();

            return false;
        }

        // This table captures the payment information
        if (Db::getInstance()->Execute('SELECT 1 FROM `' . _DB_PREFIX_ . 'valitor_cartInfo`')) {
            $sql = 'RENAME TABLE  `' . _DB_PREFIX_ . 'valitor_cartInfo`  TO `' . _DB_PREFIX_ . 'altapay_cartInfo`  ';
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
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function createOrderState()
    {
        if (!Configuration::get('ALTAPAY_OS_PENDING')) {
            $orderState = new OrderState();
            $orderState->name = [];
            foreach (Language::getLanguages() as $language) {
                $orderState->name[$language['id_lang']] = 'Awaiting payment processing';
            }
            $orderState->color = '#ffff5a';
            $orderState->logable = false;
            $orderState->invoice = false;
            $orderState->hidden = false;
            $orderState->send_email = false;
            $orderState->shipped = false;
            $orderState->paid = false;
            $orderState->delivery = false;
            if ($orderState->add()) {
                $source = __DIR__ . '/views/img/os_pending.gif';
                $destination = __DIR__ . '/../../img/os/' . (int) $orderState->id . '.gif';
                copy($source, $destination);
            }
            Configuration::updateValue('ALTAPAY_OS_PENDING', (int) $orderState->id);
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
     *
     * @throws PrestaShopException
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

    /**
     * Form for adding and editing terminals
     *
     * @return string HTML for display
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function renderAddForm()
    {
        $currencyOptions = [];
        $terminalNature = [];
        foreach (Currency::getCurrencies((int) Context::getContext()->language->id) as $currency) {
            $currencyOptions[] = [
                'id' => $currency->iso_code,
                'name' => $currency->name . ' (' . $currency->iso_code . ')',
            ];
        }
        $iconOptions = [];
        $fieldsForm = [];
        $tokenControl = [];
        $directory = _PS_MODULE_DIR_ . '/' . $this->name . '/' . $this->paymentMethodIconDir;
        $scanned_directory = array_diff(scandir($directory), ['..', '.', '.DS_Store']);
        foreach ($scanned_directory as $filename) {
            $iconOptions[] = [
                'id' => $filename,
                'name' => $filename,
            ];
        }

        $allTerminal = [];
        $totalTerminals = count(Altapay_Models_Terminal::getTerminals());
        foreach (range(1, $totalTerminals) as $priority) {
            $allTerminal[] = [
                'id' => $priority,
                'name' => $priority,
            ];
        }

        $ccTokenControlOptions = [
            [
                'name' => 'Enable',
                'val' => 1,
            ],
        ];
        $terminals = $this->getAltapayTerminals();
        foreach ($terminals as $terminal) {
            $terminalNature[] = [
                'id' => $terminal['nature'],
                'name' => $terminal['nature'],
            ];
        }

        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $tokenControl = [
                'type' => 'checkbox',
                'label' => $this->l('Credit Card Token Control'),
                'desc' => $this->l('Check this box to enable Credit Card Control for this terminal'),
                'name' => 'ccTokenControl',
                'id' => 'ccTokenControl',
                'required' => false,
                'lang' => false,
                'values' => [
                    'query' => $ccTokenControlOptions,
                    'id' => 'id',
                    'name' => 'name',
                ],
            ];
        }

        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Terminal details'),
                'icon' => 'icon-cog',
            ],
            'input' => [
                [
                    'type' => 'hidden',
                    'name' => 'id_terminal',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Display name'),
                    'desc' => $this->l('What the customer sees'),
                    'name' => 'display_name',
                    'required' => true,
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Icon'),
                    'desc' => $this->l('Upload icons in size 20x20 pixels to ')
                              . $this->_path . $this->paymentMethodIconDir,
                    'name' => 'icon_filename',
                    'required' => true,
                    'options' => [
                        'query' => $iconOptions,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Altapay terminal'),
                    'desc' => $this->l('Name of the terminal in the Altapay merchant information interface'),
                    'name' => 'remote_name',
                    'id' => 'terminalName',
                    'required' => true,
                    'options' => [
                        'query' => $this->getAltapayTerminals(),
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('CVV Less'),
                    'name' => 'cvvLess',
                    'required' => true,
                    'options' => [
                        'query' => [
                            [
                                'id_option' => '0',
                                'name' => 'No',
                            ],
                            [
                                'id_option' => '1',
                                'name' => 'Yes',
                            ],
                        ],
                        'id' => 'id_option',
                        'name' => 'name',
                    ],
                ],

                [
                    'type' => 'select',
                    'label' => $this->l('Display priority'),
                    'desc' => $this->l('A terminal with display priority 1 will be shown at the top of the list.'),
                    'name' => 'position',
                    'id' => 'terminalPosition',
                    'required' => true,
                    'options' => [
                        'query' => $allTerminal,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],

                [
                    'type' => 'select',
                    'name' => 'terminal_nature',
                    'id' => 'terminalNature',
                    'required' => false,
                    'options' => [
                        'query' => $terminalNature,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],

                $tokenControl,

                [
                    'type' => 'select',
                    'label' => $this->l('Currency'),
                    'name' => 'currency',
                    'required' => true,
                    'options' => [
                        'query' => $currencyOptions,
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Payment type'),
                    'desc' => $this->l('How the payment is handled'),
                    'name' => 'payment_type',
                    'required' => true,
                    'options' => [
                        'query' => [
                            [
                                'id_option' => 'payment',
                                'name' => 'Authorize only',
                            ],
                            [
                                'id_option' => 'paymentAndCapture',
                                'name' => 'Authorize and capture',
                            ],
                        ],
                        'id' => 'id_option',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Status'),
                    'name' => 'active',
                    'required' => true,
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        ],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
            'buttons' => [
                [
                    'href' => AdminController::$currentIndex .
                              '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                    'title' => $this->l('Back to list'),
                    'icon' => 'process-icon-back',
                ],
            ],
        ];
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = 'altapay';
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->show_toolbar = false;
        $helper->table = 'altapay_terminals';
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->id = (int) Tools::getValue('id_terminal');
        $helper->submit_action = 'savealtapay_terminals';
        $helper->tpl_vars = [
            'fields_value' => (array) $this->getFormValues(),
            'languages' => (array) $this->context->controller->getLanguages(),
            'id_language' => (array) $this->context->language->id,
        ];

        return $helper->generateForm($fieldsForm);
    }

    /**
     * Query the AltaPay API for available terminals
     *
     * @param bool $objects
     *
     * @return array<int, Terminal>
     *
     * @throws PrestaShopException
     */
    private function getAltapayTerminals($objects = false)
    {
        $terminalArray = [];
        try {
            $api = new API\PHP\Altapay\Api\Others\Terminals(getAuth());
            $response = $api->call();
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 3, $e->getCode(), $this->name, $this->id, true);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false) . '&configure='
                                 . $this->name . '&errorMessage&token=' . Tools::getAdminTokenLite('AdminModules'));
            exit();
        }

        foreach ($response->Terminals as $terminal) {
            if (!$objects) {
                $terminalNature = $terminal->Natures;
                $termNature = '';
                foreach ($terminalNature as $nature) {
                    if ($nature->Nature === 'CreditCard') {
                        $termNature = 'CreditCard';
                    }
                }
                $terminalArray[$terminal->Title] = [
                    'id' => $terminal->Title,
                    'name' => $terminal->Title,
                    'nature' => $termNature,
                ];
            } else {
                $terminalArray[$terminal->Title] = $terminal;
            }
        }

        return $terminalArray;
    }

    /**
     * @return string
     */
    private function getAPIUsername()
    {
        return $this->username;
    }

    /**
     * @return string
     */
    private function getAPIPassword()
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getAltapayUrl()
    {
        return $this->url;
    }

    /**
     * Get field values for add/edit terminal form
     *
     * @return Altapay/Models/Terminal|array
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getFormValues()
    {
        $data = [];
        $idTerminal = (int) Tools::getValue('id_terminal');
        if ($idTerminal > 0) {
            $data = new Altapay_Models_Terminal($idTerminal);
        } else {
            $def = Altapay_Models_Terminal::$definition;
            foreach ($def['fields'] as $fieldName => $stuff) {
                $data[$fieldName] = Tools::getValue($fieldName);
            }
        }

        return $data;
    }

    /**
     * Method for AltaPay api login using credentials provided in AltaPay settings page
     *
     * @return bool
     */
    public function altapayApiLogin()
    {
        try {
            $api = new API\PHP\Altapay\Api\Test\TestAuthentication(getAuth());
            $response = $api->call();
            if (!$response) {
                return false;
            }
        } catch (API\PHP\Altapay\Exceptions\ClientException $e) {
            $this->api_error = $e->getMessage();

            return false;
        } catch (Exception $e) {
            $this->api_error = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Handle payment processing
     * capture, refund, release
     *
     * @return void
     */
    private function processPaymentActions()
    {
        $paymentID = (int) Tools::getValue('payment_id');
        $action = Tools::ucfirst(Tools::getValue('action'));
        $goodWillRefund = false;
        $orderID = Tools::getValue('ap_order_id');
        $orderLines = Tools::getValue('ap_order_qty');
        $orderLineGiftWrap = Tools::getValue('ap_order_wrap');

        header('Content-Type: application/json');
        if (!(Tools::getValue('action') && Tools::getValue('payment_id'))) {
            return;
        }

        if (!$this->altapayApiLogin()) {
            saveLastErrorMessage($paymentID, $this->api_error);
            echo json_encode(
                [
                    'status' => 'error',
                    'message' => 'Connection error: ' . $this->api_error,
                ]
            );
            exit();
        }

        if ($action === 'Capture') { // CAPTURE
            try {
                $finalOrderLines = $this->populateOrderLinesFromPost($orderLines, $orderID, 0, $orderLineGiftWrap);

                $api = new API\PHP\Altapay\Api\Payments\CaptureReservation(getAuth());
                $api->setAmount((float) Tools::getValue('amount'));
                $api->setOrderLines($finalOrderLines);
                $api->setTransaction($paymentID);
                $api->call();
                markAsCaptured($paymentID, $this->getItemCaptureRefundQuantityCount($finalOrderLines));
            } catch (Exception $e) {
                // Save the latest error message in db
                saveLastErrorMessage($paymentID, $e->getMessage());
                echo json_encode(
                    [
                        'status' => 'error',
                        'message' => 'Could not capture reservation. ' . $e->getMessage(),
                    ]
                );
                exit();
            }
            echo json_encode(
                [
                    'status' => 'success',
                    'message' => 'Reservation captured successfully',
                ]
            );
            exit();
        } elseif ($action === 'Refund') { // REFUND
            try {
                $refundAmount = (float) Tools::getValue('amount');
                if (Tools::getValue('goodwillrefund') === 'yes') {
                    $goodWillRefund = true;
                }
                $finalOrderLines = $this->populateOrderLinesFromPost(
                    $orderLines,
                    $orderID,
                    0,
                    $orderLineGiftWrap,
                    $goodWillRefund
                );

                // Add a dummy orderLine array in case no orderLines are parsed in the refund
                if ($finalOrderLines === [] && $goodWillRefund) {
                    $finalOrderLines = $this->createDummyOrderLinesArr($refundAmount);
                }
                $api = new API\PHP\Altapay\Api\Payments\RefundCapturedReservation(getAuth());
                $api->setAmount($refundAmount);
                $api->setOrderLines($finalOrderLines);
                $api->setTransaction($paymentID);
                $api->call();
                markAsRefund($paymentID, $this->getItemCaptureRefundQuantityCount($finalOrderLines));
            } catch (Exception $e) {
                $message = $e->getMessage();
                saveLastErrorMessage($paymentID, $message);
                echo json_encode(
                    [
                        'status' => 'error',
                        'message' => 'Could not refund payment. ' . $message,
                    ]
                );
                exit();
            }

            echo json_encode(
                [
                    'status' => 'success',
                    'message' => 'Payment refunded successfully',
                ]
            );
            exit();
        } elseif ($action === 'Release') { // RELEASE
            try {
                $api = new API\PHP\Altapay\Api\Payments\ReleaseReservation(getAuth());
                $api->setTransaction($paymentID);
                $api->call();
                updatePaymentStatus($paymentID, 'Payment Released');
            } catch (Exception $e) {
                saveLastErrorMessage($paymentID, $e->getMessage());

                echo json_encode(
                    [
                        'status' => 'error',
                        'message' => 'Could not release reservation. ' . $e->getMessage(),
                    ]
                );
                exit();
            }
            echo json_encode(
                [
                    'status' => 'success',
                    'message' => 'Reservation released successfully',
                ]
            );
            exit();
        }
    }

    /**
     * Method for generating order lines from order backend
     *
     * @param array $orderLines
     * @param null $orderLineGiftWrap
     * @param string $orderID
     * @param bool $goodWillRefund
     * @param bool $isSetBackendDiscount
     * @param int|float $backendDiscount
     * @param bool $fullCapture
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    private function populateOrderLinesFromPost(
        $orderLines,
        $orderID,
        $backendDiscount = 0,
        $orderLineGiftWrap = null,
        $goodWillRefund = false,
        $isSetBackendDiscount = false,
        $fullCapture = false
    ) {
        $i = 0;
        $altapayOrderLines = [];
        $orderDetail = new Order((int) $orderID);
        $productDetailObject = new OrderDetail();
        $productDetail = $productDetailObject->getList($orderID);
        $cartRuleDiscounts = $this->getCartRuleDiscounts($orderDetail);
        $cart = new Cart($orderDetail->id_cart);

        foreach ($orderLines as $key => $orderedQuantity) {
            if ($orderedQuantity > 0) {
                $productDetails = $productDetail[$key];
                $cartDetails = $cart->getProducts()[$key];
                if ($productDetails) {
                    $rateBasePrice = 1 + ($cartDetails['rate'] / 100);
                    //Calculation of base price
                    $basePrice = $cartDetails['price_without_reduction'] / $rateBasePrice;
                    $productTax = $cartDetails['price_without_reduction'] - $basePrice;
                    $productName = $cartDetails['name'];
                    $productQuantity = $orderedQuantity;
                    $reductionPercent = $productDetails['reduction_percent'];
                    $goodsType = 'item';
                    $totalProductsTaxAmount = round($productTax * $productQuantity, 2);
                    $unitPrice = round($basePrice, 2);
                    // Calculation of base price
                    if ($reductionPercent > 0) {
                        $discountPercentage = $reductionPercent;
                    } else {
                        $discountPercentage = 0;
                        foreach ($cartRuleDiscounts as $cartRuleDiscount) {
                            if ($productDetails['product_id'] == $cartRuleDiscount['productID']) {
                                $discountPercentage = $cartRuleDiscount['discountPercent'];
                                break;
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

                    // Compensation calculation
                    $gatewaySubTotal = ($unitPrice * $productQuantity) + $totalProductsTaxAmount;
                    $gatewayTotal = $gatewaySubTotal - ($gatewaySubTotal * ($discountPercentage / 100));
                    $gatewayTotal = round($gatewayTotal, 2);
                    $cmsSubTotal = ($basePrice * $productQuantity) + ($productTax * $productQuantity);
                    $cmsTotal = $cmsSubTotal - ($cmsSubTotal * ($discountPercentage / 100));
                    $compensationAmount = $cmsTotal - $gatewayTotal;
                    $orderLine = new API\PHP\Altapay\Request\OrderLine(
                        $productName,
                        $itemID,
                        $productQuantity,
                        number_format($basePrice, 2, '.', '')
                    );
                    $orderLine->taxAmount = $totalProductsTaxAmount;
                    $orderLine->discount = round($discountPercentage, 2);
                    $orderLine->setGoodsType($goodsType);
                    $altapayOrderLines[$i] = $orderLine;
                    // Send compensation amount if Gateway total is not equal to cms total
                    if (($compensationAmount > 0 || $compensationAmount < 0)) {
                        ++$i;
                        $altapayOrderLines[$i] = $this->compensationOrderlines($itemID, $compensationAmount);
                    }
                } else {
                    $altapayOrderLines[$i] = $this->getShippingInfo($orderID, $cartRuleDiscounts);
                }
            } else {
                continue;
            }
            ++$i;
        }
        if ($orderLineGiftWrap && isset($orderLineGiftWrap[0]) && $orderLineGiftWrap[0] == 1) {
            $orderDetail = new Order((int) $orderID);
            $orderLine = new API\PHP\Altapay\Request\OrderLine(
                'Gift Wrap',
                'giftwrap',
                1,
                $orderDetail->total_wrapping
            );
            $orderLine->setGoodsType('item');
            $altapayOrderLines[$i] = $orderLine;
            ++$i;
        }
        if ($isSetBackendDiscount && $backendDiscount > 0) {
            $orderLine = new API\PHP\Altapay\Request\OrderLine(
                'Backend Discount',
                'bk-dsc',
                1,
                '-' . $backendDiscount
            );

            $orderLine->taxAmount = 0;
            $orderLine->setGoodsType('item');
            $altapayOrderLines[$i] = $orderLine;
            ++$i;
        }
        if ($fullCapture) {
            $altapayOrderLines[$i] = $this->getShippingInfo($orderID, $cartRuleDiscounts);
        }

        return $altapayOrderLines;
    }

    /**
     * @param string $orderID
     * @param array $cartRuleDiscounts
     *
     * @return array
     */
    public function getShippingInfo($orderID, $cartRuleDiscounts)
    {
        $shippingDiscount = 0;
        $orderDetail = new Order((int) $orderID);
        $shippingDetail = reset($orderDetail->getShipping());
        foreach ($cartRuleDiscounts as $cartRuleDiscount) {
            if ($cartRuleDiscount['shipping']) {
                $shippingDiscount = 100;
            }
        }
        $orderLine = new API\PHP\Altapay\Request\OrderLine(
            $shippingDetail['carrier_name'],
            $shippingDetail['carrier_name'],
            1,
            $shippingDetail['shipping_cost_tax_excl']
        );
        $orderLine->taxAmount = $shippingDetail['shipping_cost_tax_incl'] - $shippingDetail['shipping_cost_tax_excl'];
        $orderLine->discount = $shippingDiscount;
        $orderLine->setGoodsType('shipment');

        return $orderLine;
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
        $dummyItemOrderLine['itemId'] = '100200';
        $dummyItemOrderLine['quantity'] = 1;
        $dummyItemOrderLine['unitPrice'] = number_format($totalAmount, 2, '.', '');
        // Optional keys for orderLines:
        $dummyItemOrderLine['taxAmount'] = '0.00';
        $dummyItemOrderLine['taxPercent'] = '0.00';
        $dummyItemOrderLine['goodsType'] = 'refund';

        return $dummyItemOrderLine;
    }

    /**
     * Handle submission of terminal form
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function postProcessTerminal()
    {
        $terminalRemoteName = $_POST['remote_name'];
        $terminalId = getTerminalId($terminalRemoteName)[0]['id_terminal'];
        // Update existing
        if ($idTerminal = Tools::getValue('id_terminal')) {
            $terminal = new Altapay_Models_Terminal((int) $idTerminal);
        } // New
        elseif (!($idTerminal = Tools::getValue('id_terminal')) && $terminalId) {
            $idTerminal = $terminalId;
            $terminal = new Altapay_Models_Terminal((int) $idTerminal);
        } else {
            $terminal = new Altapay_Models_Terminal();
        }

        $api = new API\PHP\Altapay\Api\Others\Terminals(getAuth());
        $response = $api->call();
        $allowedCurrencies = [];

        foreach ($response->Terminals as $term) {
            if ($term->Title === $terminalRemoteName) {
                foreach ($term->Currencies as $currency) {
                    $allowedCurrencies[] = $currency->Currency;
                }
            }
        }

        $getVal = Tools::getValue('currency');
        // Currency supported?
        if (!in_array($getVal, $allowedCurrencies, true)) {
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
            'active',
            'position',
            'cvvLess',
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
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function postProcessActive()
    {
        $idTerminal = Tools::getValue('id_terminal');
        if (!$idTerminal) {
            return null;
        }
        $terminal = new Altapay_Models_Terminal((int) $idTerminal);
        $terminal->active = !(bool) $terminal->active;
        $terminal->save();
    }

    /**
     * Info displayed at the top on the module config page
     *
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
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
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function renderForm()
    {
        $statuses = OrderState::getOrderStates($this->context->language->id);
        $selectCaptureStatus = [];
        foreach ($statuses as $status) {
            $selectCaptureStatus[] = ['key' => $status['id_order_state'], 'name' => $status['name']];
        }

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Merchant details'),
                    'icon' => 'icon-cog',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('API username'),
                        'name' => 'ALTAPAY_USERNAME',
                        'required' => true,
                    ],
                    [
                        'type' => 'password',
                        'label' => $this->l('API password'),
                        'desc' => 'Fill this to change the password',
                        'name' => 'ALTAPAY_PASSWORD',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('API URL'),
                        'desc' => 'Typically your installation for testing will be 
                        "https://testgateway.altapaysecure.com/" and for production it will be 
                        "https://yourdomain.altapaysecure.com/". 
                        Your Username and Password may be different for testing and live.',
                        'name' => 'ALTAPAY_URL',
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => 'Capture on status changed to',
                        'name' => 'AUTOCAPTURE_STATUSES[]',
                        'class' => 'chosen',
                        'required' => false,
                        'multiple' => true,
                        'options' => [
                            'query' => $selectCaptureStatus,
                            'id' => 'key',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
        if (isset($_GET['errorMessage'])) {
            $this->Mhtml .= '<div class="alert alert-danger">Incorrect payment gateway account details</div>';
        }
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = [];
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure='
                                . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
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
            'ALTAPAY_USERNAME' => Tools::getValue('ALTAPAY_USERNAME', Configuration::get('ALTAPAY_USERNAME')),
            'ALTAPAY_PASSWORD' => Tools::getValue('ALTAPAY_PASSWORD', Configuration::get('ALTAPAY_PASSWORD')),
            'ALTAPAY_URL' => Tools::getValue('ALTAPAY_URL', Configuration::get('ALTAPAY_URL')),
            'AUTOCAPTURE_STATUSES[]' => Tools::getValue('AUTOCAPTURE_STATUSES',
                unserialize(Configuration::get('AUTOCAPTURE_STATUSES'))),
        ];
    }

    /**
     * List of terminals
     *
     * @return string
     *
     * @throws PrestaShopDatabaseException
     */
    public function renderTerminalList()
    {
        $fields_list = [
            'id_terminal' => [
                'title' => $this->l('ID'),
                'width' => 100,
                'type' => 'text',
            ],
            'display_name' => [
                'title' => $this->l('Name'),
                'width' => 140,
                'type' => 'text',
            ],
            'currency' => [
                'title' => $this->l('Currency'),
                'width' => 50,
                'type' => 'text',
            ],
            'remote_name' => [
                'title' => $this->l('Terminal'),
                'width' => 140,
                'type' => 'text',
            ],
            'ccTokenControl_' => [
                'title' => $this->l('Token control'),
                'type' => 'bool',
                'width' => 'auto',
                'orderby' => false,
                'search' => false,
            ],
            'position' => [
                'title' => $this->l('Position'),
                'width' => 'auto',
                'type' => 'text',
            ],
            'payment_type' => [
                'title' => $this->l('Payment type'),
                'width' => 140,
                'type' => 'text',
            ],
            'active' => [
                'title' => $this->l('Status'),
                'active' => 'active',
                'type' => 'bool',
                'width' => 'auto',
                'orderby' => false,
                'search' => false,
            ],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = ['edit'];
        $helper->identifier = 'id_terminal';
        $helper->position_identifier = 'position';
        $helper->show_toolbar = true;
        $helper->toolbar_btn = [
            'new' => [
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&add' . $this->name
                          . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Add new'),
            ],
        ];
        $helper->title = 'Terminals';
        $helper->table = 'altapay_terminals';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->orderBy = 'id_terminal';
        $helper->orderWay = 'ASC';
        $content = Altapay_Models_Terminal::getTerminals();

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
     * @return array|null
     *
     * @throws PrestaShopException
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
            $this->performCapture($paymentID, $params, true, true);
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
     * @param array $params
     * @param bool $captureRemainedAmount
     * @param bool $statusCapture
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    public function performCapture($paymentID, $params, $captureRemainedAmount = true, $statusCapture = false)
    {
        try {
            $productDetails = new OrderDetail();
            $cart = $this->context->cart;
            $orderSummary = $cart->getSummaryDetails();
            $api = new API\PHP\Altapay\Api\Others\Payments(getAuth());
            $api->setTransaction($paymentID);
            $paymentDetails = $api->call();

            $reserved = 0;
            $captured = 0;
            $refunded = 0;

            foreach ($paymentDetails as $pay) {
                $reserved += (float) $pay->ReservedAmount;
                $captured += (float) $pay->CapturedAmount;
                $refunded += (float) $pay->RefundedAmount;
            }

            $orderDetail = new Order((int) $params['id_order']);
            $discountData = $this->getorderCartRule($params['id_order']);
            $backendDiscount = 0;
            foreach ($discountData as $key => $discount) {
                $idCartRule = $discount['id_cart_rule'];
                if ($this->enableBackendDiscount($orderSummary['discounts'], $idCartRule)) {
                    $backendDiscount += $discountData[$key]['value'];
                }
            }

            $amountToCapture = $reserved - $captured;
            $giftWrappingFee = null;
            if ($productDetails->gift) {
                $giftWrappingFee = $productDetails->total_wrapping;
            }
            if ($amountToCapture == 0) {
                return null;
            }

            $api = new API\PHP\Altapay\Api\Payments\CaptureReservation(getAuth());
            $api->setTransaction($paymentID);

            if ($amountToCapture > 0 && $captured == 0) {
                $orderLines = $this->populateOrderLinesFromPost(array_column(
                    $productDetails->getList($params['id_order']),
                    'product_quantity'),
                    $params['id_order'],
                    $backendDiscount,
                    $giftWrappingFee,
                    false,
                    true,
                    true
                );
                $api->setOrderLines($orderLines);
                if ($statusCapture) {
                    $api->setAmount((float) $orderDetail->total_paid);
                } else {
                    $api->setAmount($amountToCapture);
                }
                $api->call();
                markAsCaptured($paymentID, $this->getItemCaptureRefundQuantityCount($orderLines));
            } elseif ($amountToCapture > 0 && $captured > 0 && $captureRemainedAmount) {
                $orderLines = $this->createOrderStatusOrderLines($amountToCapture);
                $api->setOrderLines($orderLines);
                $api->setAmount($amountToCapture);
                $api->call();
            }
        } catch (Exception $e) {
            $this->returnError($paymentID, $e);
        }
    }

    /**
     * @param array $appliedDiscount
     * @param int $idCartRule
     *
     * @return bool
     */
    public function enableBackendDiscount($appliedDiscount, $idCartRule)
    {
        foreach ($appliedDiscount as $key => $discountData) {
            if ($discountData['id_cart_rule'] == $idCartRule) {
                return false;
            }
        }

        return true;
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
        $orderLines = [];
        $orderLines[] = [
            'description' => 'Complete amount Capture',
            'itemId' => 'Capture-1',
            'quantity' => 1,
            'unitPrice' => round($amountToCapture, 2),
            'taxAmount' => 0,
            'goodsType' => 'handling',
        ];

        return $orderLines;
    }

    /**
     * Captures a payment when the status is changed to Delivered.
     *
     * @param array $params
     *
     * @return array|null
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $results = $this->selectOrder($params);
        if (!$results) {
            return null;
        }
        $orderStatus = new OrderState($this->context->language->id);
        $configuredStatus = $orderStatus->getOrderStates($this->context->language->id);
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
                if ($currentOrderStatus == $captureOrderStatus && $currentOrderStatus !== 'Shipped') {
                    $paymentID = $results['payment_id'];
                    $this->performCapture($paymentID, $params, false, true);
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
     * @param array $params
     *
     * @return bool|string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookAdminOrder($params)
    {
        $results = $this->selectOrder($params);

        if (!$results) {
            return false;
        }

        // collect order info
        $orderDetail = new Order((int) $params['id_order']);
        $productDetail = $orderDetail->getProducts();
        $shippingDetail = $orderDetail->getShipping();

        if ($orderDetail->gift) {
            $giftWrappingFee = $orderDetail->total_wrapping;
            $this->smarty->assign('ap_gift_wrapping', $giftWrappingFee);
        }
        $orderId = $params['id_order'];
        $discounts = $this->getCartRuleDiscounts($orderDetail);
        $this->smarty->assign('ap_order_id', $orderId);
        $this->smarty->assign('ap_product_details', $productDetail);
        if (!empty($shippingDetail[0]['id_order_invoice'])) {
            $this->smarty->assign('ap_shipping_details', $shippingDetail);
        }
        $this->smarty->assign('ap_coupon_discount', $discounts);
        $this->smarty->assign('ap_order_detail', $orderDetail->total_discounts);
        $apOrders = [];
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

        try {
            $reserved = 0;
            $captured = 0;
            $refunded = 0;
            $api = new API\PHP\Altapay\Api\Others\Payments(getAuth());
            $api->setTransaction($results['payment_id']);
            $paymentDetails = $api->call();
            $status = isset($paymentDetails[0]->TransactionStatus) ? $paymentDetails[0]->TransactionStatus : '';

            foreach ($paymentDetails as $pay) {
                $reserved += $pay->ReservedAmount;
                $captured += $pay->CapturedAmount;
                $refunded += $pay->RefundedAmount;
            }

            $ap_payment = [
                'reserved' => $reserved,
                'captured' => $captured,
                'refunded' => $refunded,
                'status' => $status,
            ];

            $this->smarty->assign('ap_paymentinfo', $ap_payment);
        } catch (Exception $e) {
            $this->smarty->assign('ap_error', 'Error: ' . $e->getMessage());
        }

        // prepare for view
        $paymentinfo = [
            'Transaction Date' => Tools::htmlentitiesUTF8(date('F j, Y, g:i a', $results['date_add'])),
            'Transaction ID' => Tools::htmlentitiesUTF8($results['unique_id']),
            'Payment ID' => Tools::htmlentitiesUTF8($results['payment_id']),
            'Card Brand' => Tools::htmlentitiesUTF8($results['cardBrand']),
            'Card Number' => Tools::htmlentitiesUTF8($results['cardMask']),
            'Card Country' => Tools::htmlentitiesUTF8($results['cardCountry']),
            'Payment Type' => Tools::htmlentitiesUTF8($results['paymentType']),
            'Payment Status' => Tools::htmlentitiesUTF8($results['paymentStatus']),
            'Payment Nature' => Tools::htmlentitiesUTF8($results['paymentNature']),
            'Latest Error' => Tools::htmlentitiesUTF8($results['latestError']),
        ];
        $fet = $this->context->link;
        $tname = $this->name;
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
     * @return array|false|mysqli_result|PDOStatement|resource|null
     *
     * @throws PrestaShopDatabaseException
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
     * @return string|void
     *
     * @throws PrestaShopDatabaseException
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
        $currency = $this->getCurrencyForCart($params['cart']);
        $paymentMethods = Altapay_Models_Terminal::getActiveTerminalsForCurrency($currency->iso_code);

        $this->smarty->assign([
            'this_path' => $this->_path,
            'this_path_altapay' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'methods' => $paymentMethods,
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
        $currency_order = new Currency($cart->id_currency);
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
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            return $this->display(__FILE__, 'savedCreditCards.tpl');
        }
    }

    /**
     * Hook payment is being triggered for prestashop 1.7 for payment processing from checkout page
     *
     * @param array $params
     *
     * @return array|null
     *
     * @throws PrestaShopDatabaseException
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
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_saved_credit_card` WHERE userID =' . $customerID;
            $results = Db::getInstance()->executeS($sql);

            if ($results) {
                foreach ($results as $result) {
                    $savedCreditCard[] = [
                        'creditCard' => $result['creditCardNumber'],
                        'cardName' => $result['cardName'],
                        'cardExpiryDate' => $result['cardExpiryDate'],
                    ];
                }
                $this->context->smarty->assign('savedCreditCard', $savedCreditCard);
            }
        }

        $this->context->controller->addCSS($this->_path . 'css/payment.css', 'all');
        // Fetch payment methods
        $currency = $this->getCurrencyForCart($params['cart']);
        $paymentMethods = Altapay_Models_Terminal::getActiveTerminalsForCurrency($currency->iso_code);

        $this->smarty->assign(
            $this->getTemplateVarInfos()
        );

        $paymentsOptions = [];
        foreach ($paymentMethods as $paymentMethod) {
            $this->context->smarty->assign('ccTokenControl', $paymentMethod['ccTokenControl_']);
            if ($customerID) {
                $this->context->smarty->assign('customerID', $customerID);
            }
            $actionText = $this->l('Pay with') . ' ' . $paymentMethod['display_name'];
            $paymentOptions = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $terminal_id = $paymentMethod['id_terminal'];
            $terminal = ['method' => $terminal_id];
            $template = $this->fetch('module:altapay/views/templates/hook/payment17.tpl');

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
     *
     * @throws PrestaShopDatabaseException
     */
    public function getTemplateVarInfos()
    {
        $cart = $this->context->cart;
        $currency = $this->getCurrencyForCart($cart);
        $paymentMethods = Altapay_Models_Terminal::getActiveTerminalsForCurrency($currency->iso_code);

        return [
            'this_path' => $this->_path,
            'this_path_altapay' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name
                               . '/',
            'methods' => $paymentMethods,
            'PS_STOCK_MANAGEMENT' => Configuration::get('PS_STOCK_MANAGEMENT'),
        ];
    }

    /**
     * Hook triggered at the time of payment returns
     *
     * @param $params
     *
     * @return string|void
     *
     * @throws LocalizationException
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

        $state = $params['objOrder']->getCurrentState();
        $results = Db::getInstance()->getRow('SELECT * 
        FROM `' . _DB_PREFIX_ . 'altapay_order` WHERE id_order=' . $params['objOrder']->id);
        if ($state == Configuration::get('PS_OS_PAYMENT') || $state == Configuration::get('PS_OS_OUTOFSTOCK')) {
            $this->smarty->assign([
                'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'status' => 'ok',
                'unique_id' => $results['unique_id'],
                'payment_id' => $results['payment_id'],
                'id_order' => $params['objOrder']->id,
            ]);
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference)) {
                $this->smarty->assign('reference', $params['objOrder']->reference);
            }
        } else {
            $this->smarty->assign([
                'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'status' => 'open',
                'unique_id' => $results['unique_id'],
                'payment_id' => $results['payment_id'],
                'id_order' => $params['objOrder']->id,
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
     * @param bool $payment_method
     * @param string $savedCreditCard
     *
     * @return array If the transaction failed, the array contains information about the failure
     *
     * @throws Exception
     */
    public function createTransaction($savedCreditCard, $payment_method = false)
    {
        $customerCreatedDate = null;
        $cart = $this->context->cart;
        $ccToken = null;

        // Terminal
        $terminal = $this->getTerminal($payment_method, $this->context->currency->iso_code);
        if (!is_object($terminal)) {
            $message = 'Could not determine remote terminal - possibly currency mismatch';
            PrestaShopLogger::addLog($message, 3, 0, $this->name, $this->id, true);

            return [
                'success' => false,
                'result' => 'failure',
                'message' => $message,
                'additionalInfo' => $message,
                'payment_form_url' => false,
            ];
        }
        $cgConf = [];
        // Config
        $cgConf['payment_type'] = $terminal->payment_type;
        $cgConf['currency'] = $this->context->currency->iso_code;
        $cgConf['language'] = $this->context->language->iso_code;
        $cgConf['uniqueid'] = uniqid('PS');
        $cgConf['terminal'] = $terminal->remote_name;
        $cgConf['cookie'] = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : null;

        $callback = [];
        // Callbacks
        $callback['callback_form'] = $this->context->link->getModuleLink(
            $this->name,
            'callbackform',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_ok'] = $this->context->link->getModuleLink(
            $this->name,
            'callbackok',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_fail'] = $this->context->link->getModuleLink(
            $this->name,
            'callbackfail',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_open'] = $this->context->link->getModuleLink(
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
        $callback['callback_redirect'] = $this->context->link->getModuleLink(
            $this->name,
            'callbackredirect',
            [],
            true,
            $this->context->language->id,
            $this->context->shop->id
        );

        // Billing address
        $invoice_address = new Address($this->context->cart->id_address_invoice);
        $country = new Country($invoice_address->id_country);
        $state = new State($invoice_address->id_state);

        $address = new API\PHP\Altapay\Request\Address();
        $address->Firstname = $this->context->customer->firstname;
        $address->Lastname = $this->context->customer->lastname;
        $address->Address = $invoice_address->address1;
        $address->City = $invoice_address->city;
        $address->PostalCode = $invoice_address->postcode;
        $address->Region = $state->iso_code;
        $address->Country = $country->iso_code;

        $customer = new API\PHP\Altapay\Request\Customer($address);
        $customer->setEmail($this->context->customer->email);
        $customer->setPhone($invoice_address->phone ?: $invoice_address->phone_mobile);

        // Shipping address
        $sp_address = new Address($this->context->cart->id_address_delivery);
        $sp_country = new Country($sp_address->id_country);
        $sp_state = new State($sp_address->id_state);

        $address = new API\PHP\Altapay\Request\Address();
        $address->Firstname = $sp_address->firstname;
        $address->Lastname = $sp_address->lastname;
        $address->Address = $sp_address->address1;
        $address->City = $sp_address->city;
        $address->PostalCode = $sp_address->postcode;
        $address->Region = $sp_state->iso_code;
        $address->Country = $sp_country->iso_code;
        $customer->setShipping($address);

        //Calling transactionInfo method from helpers file
        $transactionInfo = transactionInfo();
        $amount = $cart->getOrderTotal(true, Cart::BOTH);
        if ($this->context->customer->isLogged()) {
            $customer->setCreatedDate(new \DateTime($this->context->customer->date_add));
        }

        if (!is_null($savedCreditCard)) {
            $sql = 'SELECT ccToken FROM `' . _DB_PREFIX_ . 'altapay_saved_credit_card` WHERE creditcardNumber ="' . $savedCreditCard . '"';
            $results = Db::getInstance()->executeS($sql);
            foreach ($results as $result) {
                $ccToken = $result['ccToken'];
            }
        }

        if (!$this->altapayApiLogin()) {
            PrestaShopLogger::addLog($this->api_error, 3, null, $this->name, $this->id, true);

            return [
                'success' => false,
                'result' => 'failure',
                'message' => 'unable to connect to gateway',
                'additionalInfo' => $this->api_error,
                'payment_form_url' => false,
            ];
        }

        try {
            $config = new API\PHP\Altapay\Request\Config();
            $config->setCallbackOk($callback['callback_ok']);
            $config->setCallbackFail($callback['callback_fail']);
            $config->setCallbackOpen($callback['callback_open']);
            $config->setCallbackNotification($callback['callback_notification']);
            $config->setCallbackForm($callback['callback_form']);
            $request = new API\PHP\Altapay\Api\Ecommerce\PaymentRequest(getAuth());
            $request->setTerminal($cgConf['terminal'])
                    ->setShopOrderId($cgConf['uniqueid'])
                    ->setAmount($amount)
                    ->setCurrency($cgConf['currency'])
                    ->setCustomerInfo($customer)
                    ->setConfig($config)
                    ->setTransactionInfo($transactionInfo)
                    ->setCookie($cgConf['cookie'])
                    ->setCcToken($ccToken)
                    ->setFraudService(null)
                    ->setLanguage($cgConf['language'])
                    ->setType($cgConf['payment_type'])
                    ->setOrderLines($this->getOrderLines($cart));
            $response = $request->call();

            return [
                'success' => true,
                'uniqueid' => $cgConf['uniqueid'],
                'amount' => $amount,
                'result' => 'Success',
                'payment_form_url' => $response->Url,
            ];
        } catch (API\PHP\Altapay\Exceptions\ClientException $e) {
            $message = $e->getResponse()->getBody();
        } catch (API\PHP\Altapay\Exceptions\ResponseHeaderException $e) {
            $message = $e->getHeader()->ErrorMessage;
        } catch (API\PHP\Altapay\Exceptions\ResponseMessageException $e) {
            $message = $e->getMessage();
        } catch (Exception $e) {
            $message = $e->getMessage();
        }

        PrestaShopLogger::addLog($message, 3, null, $this->name, $this->id, true);

        return [
            'success' => false,
            'result' => 'failure',
            'message' => 'unable to obtain payment form url',
            'additionalInfo' => $message,
            'payment_form_url' => false,
        ];
    }

    /**
     * Get the remote name of the terminal associated with
     * this payment method. Will check if currency matches the remote terminal.
     *
     * @param bool $terminal_id
     * @param bool $currency
     *
     * @return Altapay_Models_Terminal|null
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function getTerminal($terminal_id = false, $currency = false)
    {
        if ($terminal_id === false || $currency === false) {
            return null;
        }

        $terminal = new Altapay_Models_Terminal($terminal_id);
        $terminalId = $terminal->id_terminal;
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

    public function getorderCartRule($orderID)
    {
        $cartDiscount = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'order_cart_rule WHERE id_order = ' . $orderID);

        return $cartDiscount;
    }

    /**
     * Used to create the capture or refund quantity count in order to store in the db
     *
     * @param CartCore $cart
     *
     * @return array
     *
     * @throws PrestaShopException
     */
    private function getOrderLines($cart)
    {
        $i = 0;
        $orderSummary = $cart->getSummaryDetails();
        $orderSubtotal = $orderSummary['total_products_wt'];

        $orderLines = [];
        $products = $cart->getProducts();
        $shippingDiscountPercent = 0;
        $freeGiftVoucher = $this->getCartRuleProperties($cart);
        $vouchers = $this->getVoucherDetails();
        $cartID = $cart->id;
        $orderDetails = [];

        if (in_array('1', $freeGiftVoucher['freeShippingStatus'], true)) {
            $cartRuleFreeShipping = true;
        } else {
            $cartRuleFreeShipping = false;
        }
        foreach ($products as $p) {
            $rateBasePrice = 1 + ($p['rate'] / 100);
            //Calculation of base price
            $basePrice = $p['price_without_reduction'] / $rateBasePrice;

            $singleProductTaxAmount = $p['price_without_reduction'] - $basePrice;
            $productID = $p['id_product'];
            $discountPercent = 0;

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
                $discountAmount = $p['price_without_reduction'] - $p['price_with_reduction'];
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

            $productImageUrl = $this->context->link->getImageLink($p['link_rewrite'], $p['id_image'], 'home_default');
            $orderDetails[$i]['productID'] = $productID;
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
            $gatewaySubTotal = ($orderLines[$i]->unitPrice * $p['cart_quantity']) + $orderLines[$i]->taxAmount;
            $gatewayTotal = $gatewaySubTotal - ($gatewaySubTotal * ($discountPercent / 100));
            $gatewayTotal = round($gatewayTotal, 2);
            $cmsSubTotal = ($basePrice * $p['cart_quantity']) + ($singleProductTaxAmount * $p['cart_quantity']);
            $cmsTotal = $cmsSubTotal - ($cmsSubTotal * ($discountPercent / 100));
            $compensationAmount = $cmsTotal - $gatewayTotal;
            // Send compensation amount if Gateway total is not equal to cms total
            if (($compensationAmount > 0 || $compensationAmount < 0)) {
                ++$i;
                $orderLines[$i] = $this->compensationOrderlines($itemID, $compensationAmount);
            }
            ++$i;
        }

        if ($cart->gift) {
            $orderLines[$i] = $this->createOrderlines('Gift Wrap', 'giftwrap', 1, 0, $cart->getGiftWrappingPrice(), 0, 'item', '', '', '');
            ++$i;
        }

        $carrier = $cart->getSummaryDetails()['carrier'];
        $carrierCostWithTax = $cart->getTotalShippingCost();
        $carrierCostWithoutTax = $cart->getTotalShippingCost(null, false);
        $carrierTax = $carrierCostWithTax - $carrierCostWithoutTax;
        if ($cartRuleFreeShipping) {
            $shippingDiscountPercent = 100;
        }
        if (!empty($carrier->name)) {
            $orderLines[$i] = $this->createOrderlines(
                $carrier->delay,
                $carrier->name,
                1,
                $shippingDiscountPercent,
                $carrierCostWithoutTax,
                $carrierTax,
                'shipment',
                '',
                '',
                ''
            );
        }

        if ($orderDetails) {
            $orderDetails = json_encode($orderDetails);
            $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'altapay_cartInfo (id_cart, productDetails, date_add) VALUES ' . "('" . $cartID . "', '"
                   . $orderDetails . "', '" . time() . "')" .
                   ' ON DUPLICATE KEY UPDATE `productDetails` = ' . "'" . $orderDetails . "'";
            Db::getInstance()->Execute($sql);
        }

        return $orderLines;
    }

    /**
     * Returns the order lines using provided params
     *
     * @param string $productName
     * @param string $itemID
     * @param int $quantity
     * @param float $discount
     * @param float $unitPrice
     * @param float $taxAmount
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
        $orderLine = new API\PHP\Altapay\Request\OrderLine(
            $productName,
            $itemID,
            $quantity,
            number_format((100 * $unitPrice) / 100, 2, '.', '')
        );

        $orderLine->taxAmount = number_format($quantity * $taxAmount, 2, '.', '');
        $orderLine->discount = $discount;
        $orderLine->taxPercent = $unitPrice > 0 ? number_format(($taxAmount / $unitPrice) * 100, 2, '.', '') : 0;
        $orderLine->productUrl = $productUrl ? $productUrl : '';
        $orderLine->imageUrl = $imageUrl ? $imageUrl : '';
        $orderLine->unitCode = $unitCode;
        $orderLine->setGoodsType($goodsType);

        return $orderLine;
    }

    /**
     * Returns the voucher discounts for each product in the order lines
     *
     * @param array $vouchers
     * @param string $productID
     * @param float $discountPercent
     * @param float $basePrice
     * @param float $orderSubtotal
     * @param array $freeGiftVoucher
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
        $discountedAmount = 0;
        $productPriceAfterDiscount = 0;
        foreach ($vouchers as $key => $voucher) {
            if (in_array($productID, $voucher['products']) || $voucher['products'] === 'all') {
                if (!$discountPercent && $voucher['reductionPercent'] !== '0.00') {
                    $discountPercent += $voucher['reductionPercent'];
                    $discountedAmount = $basePrice * ($discountPercent / 100);
                    $productPriceAfterDiscount = $basePrice - $discountedAmount;
                } elseif ($voucher['reductionPercent'] === '0.00' && (empty($freeGiftVoucher['free_gift']) || $freeGiftVoucher['free_gift']) && $freeGiftVoucher['free_gift'] != $productID) {
                    if ($freeGiftVoucher['free_gift']) {
                        $discountPercent += (($freeGiftVoucher['reductionAmount'] + $freeGiftVoucher[$key]) / ($orderSubtotal + $freeGiftVoucher[$key]) * 100);
                    } else {
                        $discountPercent += ($freeGiftVoucher[$key] / ($orderSubtotal)) * 100;
                    }
                } elseif ($voucher['reductionPercent'] === '0.00' && empty($freeGiftVoucher['free_gift']) || $freeGiftVoucher['free_gift'] == $productID) {
                    $discountPercent += (($freeGiftVoucher['reductionAmount'] + $freeGiftVoucher[$key]) / ($orderSubtotal + $freeGiftVoucher[$key]) * 100);
                } else {
                    $totalDiscountedAmount = $discountedAmount + ($productPriceAfterDiscount * ($voucher['reductionPercent'] / 100));
                    $discountPercent = ($totalDiscountedAmount / $basePrice) * 100;
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
        $freeShipping = [];
        $cartRules = $cart->getCartRules();

        foreach ($cartRules as $key => $cartRule) {
            if ($cartRule['gift_product']) {
                $voucherProperties['free_gift'] = $cartRule['gift_product'];
            }
            $cartRuleID = $cartRule['id_cart_rule'];
            $freeShipping[] = $cartRule['free_shipping'];
            $voucherProperties[$cartRuleID] = $cartRule['value_real'];
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
     *
     * @throws PrestaShopDatabaseException
     */
    private function getCartRuleGroupProducts($couponID, $reductionPercent)
    {
        $cartRuleGroupProducts = [];
        $cartRuleGroups = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'cart_rule_product_rule_group WHERE id_cart_rule = ' . $couponID);
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
     *
     * @throws PrestaShopDatabaseException
     */
    private function getCartRuleGroupProductIDs($cartRuleGroupID)
    {
        $productIDs = [];
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
     *
     * @throws PrestaShopDatabaseException
     */
    private function getVoucherDetails()
    {
        $voucherDetails = [];
        $appliedCartRules = $this->context->cart->getCartRules();
        foreach ($appliedCartRules as $cartRule) {
            $reductionPercent = $cartRule['reduction_percent'];
            if (!empty($cartRule['reduction_product'])) {
                $voucherDetails[$cartRule['id_cart_rule']] = $this->getCartRuleGroupProducts($cartRule['id_cart_rule'], $reductionPercent);
            } else {
                $voucherDetails[$cartRule['id_cart_rule']] = [
                    'reductionPercent' => $reductionPercent,
                    'products' => 'all',
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
     *
     * @throws PrestaShopDatabaseException
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
     * @param string $paymentID
     * @param Exception $exception
     *
     * @return string
     *
     * @throws PrestaShopException
     */
    public function returnError($paymentID, $exception)
    {
        $cookie = $this->context->cookie;
        // Saves the error in a cookie, to display it if a HTTP redirect occurs:
        $msg = json_decode($exception->getMessage());
        $cookie->altapayError = Tools::displayError('Error trying to change the order status: ' . $msg->responseMsg);
        // Saves the error in errors[], to display it if there is no HTTP redirect:
        $this->context->controller->errors[] = $cookie->altapayError;
        // Saves the error in the database. The function is loaded from helpers file.
        saveLastErrorMessage($paymentID, $cookie->altapayError);

        return $cookie->altapayError;
    }

    /**
     * @param string $itemID
     * @param float $compensationAmount
     *
     * @return array
     */
    public function compensationOrderlines($itemID, $compensationAmount)
    {
        $orderLine = new API\PHP\Altapay\Request\OrderLine(
            'compensation',
            'comp-' . $itemID,
            1,
            $compensationAmount
        );

        $orderLine->taxAmount = 0;
        $orderLine->discount = 0;
        $orderLine->unitCode = 'unit';
        $orderLine->setGoodsType('item');

        return $orderLine;
    }
}
