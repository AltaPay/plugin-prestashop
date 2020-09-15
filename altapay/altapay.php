<?php
/**
 * Altapay module for Prestashop
 *
 * Copyright © 2020 Altapay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/Terminal.php';
require_once dirname(__FILE__) . '/classes/MerchantAPI.php';
require_once dirname(__FILE__) . '/helpers.php';

class ALTAPAY extends PaymentModule
{
    public $url;
    public $captureStatus;
    public $terminal;
    public $username;
    public $password;
    public $payment_type;
    private $Mhtml = '';
    private $postErrors = array();
    private $paymentMethodIconDir = 'views/img/payment_icons';

    public function __construct()
    {
        $this->name = 'altapay';
        $this->tab = 'payments_gateways';
        $this->version = '3.1.0';
        $this->v16 = _PS_VERSION_ >= '1.6.1.24';
        $this->v17 = _PS_VERSION_ >= '1.7.6.5';
        $this->author = 'Altapay A/S';
        $this->is_eu_compatible = 1;
        $this->ps_versions_compliancy = array('min' => '1.6.1.24', 'max' => '1.7.6.5');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        $config = Configuration::getMultiple(array(
            'ALTAPAY_USERNAME',
            'ALTAPAY_PASSWORD',
            'ALTAPAY_URL',
            'AUTOCAPTURE_STATUS',
            'ALTAPAY_TERMINAL'
        ));
        if (isset($config['ALTAPAY_USERNAME'])) {
            $this->username = $config['ALTAPAY_USERNAME'];
        }
        if (isset($config['ALTAPAY_PASSWORD'])) {
            $this->password = $config['ALTAPAY_PASSWORD'];
        }
        if (isset($config['ALTAPAY_URL'])) {
            $this->url = $config['ALTAPAY_URL'];
        }
        if (isset($config['AUTOCAPTURE_STATUS'])) {
            $this->captureStatus = $config['AUTOCAPTURE_STATUS'];
        }


        parent::__construct();

        $this->displayName = $this->l('Altapay for Prestashop');
        $this->description = $this->l('Altapay for Prestashop - Payments less complicated');
        $this->confirmUninstall = $this->l('Are you sure about removing these details?');

        // Make sure currencies are configured for this payment module
        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    /**
     * Called on install
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

        /* This table captures the payment information */
        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_order`")) {

            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_order`  TO `" . _DB_PREFIX_ . "altapay_order`  ";
            Db::getInstance()->Execute($sql);

            $sql1 = "ALTER TABLE  `" . _DB_PREFIX_ . "altapay_order`  add column cardExpiryDate varchar(255) NOT NULL AFTER cardBrand";
            Db::getInstance()->Execute($sql1);
            $sql2 = "ALTER TABLE  `" . _DB_PREFIX_ . "altapay_order`  add column paymentTerminal varchar(255) NOT NULL AFTER paymentType";
            Db::getInstance()->Execute($sql2);
        }
        else {
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

        }
        else {
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



        /* This table contains the payment methods / terminals */
        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_terminals`")) {

            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_terminals`  TO `" . _DB_PREFIX_ . "altapay_terminals`  ";
            Db::getInstance()->Execute($sql);

            $sql1 = "ALTER TABLE  `" . _DB_PREFIX_ . "altapay_terminals`  add column ccTokenControl_ int(255) NOT NULL AFTER currency";
            Db::getInstance()->Execute($sql1);

        }
        else {
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


        /* This table contains count of captured/refunded order lines */
        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_orderlines`")) {

            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_orderlines`  TO `" . _DB_PREFIX_ . "altapay_orderlines`  ";
            Db::getInstance()->Execute($sql);

        }
        else {
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

        }
        else {
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
            MODIFY `product_id` varchar(36) NOT NULL")) {
            $this->context->controller->errors[] = Db::getInstance()->getMsgError();
            return false;
        }

        /* This table captures the payment information */
        if (Db::getInstance()->Execute("SELECT 1 FROM `" . _DB_PREFIX_ . "valitor_cartInfo`")) {

            $sql = "RENAME TABLE  `" . _DB_PREFIX_ . "valitor_cartInfo`  TO `" . _DB_PREFIX_ . "altapay_cartInfo`  ";
            Db::getInstance()->Execute($sql);

        }
        else {
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
     */
    public function createOrderState()
    {
        if (!Configuration::get('ALTAPAY_OS_PENDING')) {
            $orderState = new OrderState();
            $orderState->name = array();

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
                $source = dirname(__FILE__) . '/views/img/os_pending.gif';
                $destination = dirname(__FILE__) . '/../../img/os/' . (int)$orderState->id . '.gif';
                copy($source, $destination);
            }
            Configuration::updateValue('ALTAPAY_OS_PENDING', (int)$orderState->id);
        }
    }

    /**
     * Called on uninstall
     * Leaves tables in place in order to not loose history.
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
     * @return String HTML for display
     */
    public function getContent()
    {
        /* Display: add/edit terminal form */
        if (Tools::isSubmit('updatealtapay_terminals') || Tools::isSubmit('addaltapay')) {
            $this->Mhtml .= $this->renderAddForm();
            return $this->Mhtml;
        } /* Process: capture, refund, release */ elseif (Tools::isSubmit('payment_actions')) {
            $this->processPaymentActions();
        } /* Process: save terminal */ elseif (Tools::isSubmit('savealtapay_terminals')) {
            if (!$this->postProcessTerminal()) {
                return $this->Mhtml . $this->renderAddForm();
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false) . '&configure='
                    . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'));
            }
        } /* Process: enable/disable */ elseif (Tools::isSubmit('activealtapay_terminals')) {
            $this->postProcessActive();
            return $this->displayAltapay();
        } /* Process: save merchant details */ elseif (Tools::isSubmit('btnSubmit')) {
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
        } /* Default display */ else {
            $this->Mhtml .= $this->displayAltapay();
            return $this->Mhtml;
        }
    }
    /* ******************************** */

    /**
     * Form for adding and editing terminals
     *
     * @return String HTML for display
     */
    public function renderAddForm()
    {
        $currencyOptions = array();
        $terminalNature = array();
        foreach (Currency::getCurrencies((int)Context::getContext()->language->id) as $currency) {
            $currencyOptions[] = array(
                "id" => $currency->iso_code,
                "name" => $currency->name . " (" . $currency->iso_code . ")"
            );
        }

        $iconOptions = array();
        $fieldsForm = array();
        $tokenControl = array();
        $directory = _PS_MODULE_DIR_ . "/" . $this->name . "/" . $this->paymentMethodIconDir;
        $scanned_directory = array_diff(scandir($directory), array('..', '.', '.DS_Store'));
        foreach ($scanned_directory as $filename) {
            $iconOptions[] = array(
                "id" => $filename,
                "name" => $filename
            );
        }

        $ccTokenControlOptions = [
            [
                'name'=>'Enable',
                'val' => 1
            ]
        ];

        $terminals = $this->getAltapayTerminals();
        foreach ($terminals as $terminal) {
            $terminalNature[] = array(
                'id' => $terminal['nature'],
                'name' => $terminal['nature'],
            );
        }

        if (_PS_VERSION_ >= '1.7.0.0') {
            $tokenControl =  array(
                'type' => 'checkbox',
                'label' => $this->l('Credit Card Token Control'),
                'desc' => $this->l('Check this box to enable Credit Card Control for this terminal'),
                'name' =>'ccTokenControl',
                'id' =>'ccTokenControl',
                'required' => false,
                'lang' => false,
                'values' => array(
                    'query' => $ccTokenControlOptions,
                    'id'=>'id',
                    'name' => 'name',
                ));
        }

        $fieldsForm[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Terminal details'),
                'icon' => 'icon-cog'
            ),
            'input' => array(
                array(
                    'type' => 'hidden',
                    'name' => 'id_terminal'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Display name'),
                    'desc' => $this->l('What the customer sees'),
                    'name' => 'display_name',
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Icon'),
                    'desc' => $this->l('Upload icons in size 20x20 pixels to ')
                        . $this->_path . $this->paymentMethodIconDir,
                    'name' => 'icon_filename',
                    'required' => true,
                    'options' => array(
                        'query' => $iconOptions,
                        'id' => 'id',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Altapay terminal'),
                    'desc' => $this->l('Name of the terminal in the Altapay merchant information interface'),
                    'name' => 'remote_name',
                    'id' => 'terminalName',
                    'required' => true,
                    'options' => array(
                        'query' => $this->getAltapayTerminals(),
                        'id' => 'id',
                        'name' => 'name',
                    )
                ),

                array(
                    'type' => 'select',
                    'name' => 'terminal_nature',
                    'id' => 'terminalNature',
                    'required' => false,
                    'options' => array(
                        'query' => $terminalNature,
                        'id' => 'id',
                        'name' => 'name',
                    )
                ),

                $tokenControl,

                array(
                    'type' => 'select',
                    'label' => $this->l('Currency'),
                    'name' => 'currency',
                    'required' => true,
                    'options' => array(
                        'query' => $currencyOptions,
                        'id' => 'id',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Payment type'),
                    'desc' => $this->l('How the payment is handled'),
                    'name' => 'payment_type',
                    'required' => true,
                    'options' => array(
                        'query' => array(
                            array(
                                'id_option' => 'payment',
                                'name' => 'Authorize only'
                            ),
                            array(
                                'id_option' => 'paymentAndCapture',
                                'name' => 'Authorize and capture'
                            ),
                        ),
                        'id' => 'id_option',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Status'),
                    'name' => 'active',
                    'required' => true,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        ),
                    ),
                ),
            ),
            'submit' => array(
        'title' => $this->l('Save'),
    ),
            'buttons' => array(
        array(
            'href' => AdminController::$currentIndex .
                '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'title' => $this->l('Back to list'),
            'icon' => 'process-icon-back'
        )
    ),
        );
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = 'altapay';
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->show_toolbar = false;
        $helper->table = 'altapay_terminals';

        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;

        $helper->id = (int)Tools::getValue('id_terminal');
        $helper->submit_action = 'savealtapay_terminals';

        $helper->tpl_vars = array(
            'fields_value' => (array)$this->getFormValues(),
            'languages' => (array)$this->context->controller->getLanguages(),
            'id_language' => (array)$this->context->language->id
        );
        return $helper->generateForm($fieldsForm);
    }

    /**
     * Query the ALTAPAY API for available terminals
     *
     * @return array Array of terminals
     */
    private function getAltapayTerminals($objects = false)
    {
        require_once(_PS_MODULE_DIR_ . '/altapay/lib/altapay/altapay-php-sdk/lib/AltapayMerchantAPI.class.php');
        $cgConf = array();
        $cgConf['user'] = $this->getAPIUsername();
        $cgConf['password'] = $this->getAPIPassword();
        $cgConf['altapay_url'] = $this->getAltapayUrl();

        $terminals = array();

        $api = null;
        try {
            $api = new AltapayMerchantAPI($cgConf['altapay_url'], $cgConf['user'], $cgConf['password'], null);
            $response = $api->login();
            if (!$response->wasSuccessful()) {
                $resErrMsg = $response->getErrorMessage();
                $resErrCode = $response->getErrorCode();
                throw new Exception("Could not login to the Merchant API: " . $resErrMsg, $resErrCode);
            }

            $responseTerminals = $api->getTerminals();
            $terminals = $responseTerminals->getTerminals();
        } catch (Exception $e) {
            Logger::addLog($e->getMessage(), 3, $e->getCode(), $this->name, $this->id, true);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false) . '&configure='
                . $this->name . '&errorMessage&token=' . Tools::getAdminTokenLite('AdminModules'));
            die;
        }

        $terminalArray = array();
        $termNature = '';

        foreach ($terminals as $terminal) {
            if (!$objects) {
                $terminalNature = $terminal->getNature();
                if(in_array('CreditCard', $terminalNature))
                {
                    $termNature = 'CreditCard';
                }
                else if(in_array('Invoice', $terminalNature))
                {
                    $termNature = 'Invoice';
                }
                $terminalArray[$terminal->getTitle()] = array(
                    'id' => $terminal->getTitle(),
                    'name' => $terminal->getTitle(),
                    'nature' => $termNature
                );
            } else {
                $terminalArray[$terminal->getTitle()] = $terminal;
            }
        }

        return $terminalArray;
    }


    /* ******************************** */

    private function getAPIUsername()
    {
        return $this->username;
    }

    /* ******************************** */

    private function getAPIPassword()
    {
        return $this->password;
    }

    /* ******************************** */

    public function getAltapayUrl()
    {
        return $this->url;
    } /* ******************************** */

    /**
     * Get field values for add/edit terminal form
     *
     * @return Array Associative array of object values
     */
    public function getFormValues()
    {
        $data = array();
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
     */
    private function processPaymentActions()
    {
        // $cookie param is native prestashop framework param, being utilized in the frontend payment hook processing
        global $cookie;
        header('Content-Type: application/json');
        if (!(Tools::getValue('action') && Tools::getValue('payment_id'))) {
            return;
        }

        $paymentID = (int)Tools::getValue('payment_id');
        // Merchant API
        $api = new MerchantAPI();
        try {
            $api->init($this->getAltapayUrl(), $this->getAPIUsername(), $this->getAPIPassword());
        } catch (Exception $e) {
            saveLastErrorMessage($paymentID, $e->getMessage());

            echo json_encode(
                array(
                    'status' => "error",
                    'message' => "Connection error: " . $e->getMessage()
                )
            );
            die();
        }

        $action = Tools::ucfirst(Tools::getValue('action'));
        $goodWillRefund = false;
        $orderID = Tools::getValue('ap_order_id');
        $orderLines = Tools::getValue('ap_order_qty');
        $orderLineGiftWrap = Tools::getValue('ap_order_wrap');
        // CAPTURE
        if ($action == 'Capture') {
            try {
                $finalOrderLines = $this->populateOrderLinesFromPost($orderLines, $orderLineGiftWrap, $orderID);
                $api->captureAmount($paymentID, $finalOrderLines, Tools::getValue('amount'));
                markAsCaptured($paymentID, $this->getItemCaptureRefundQuantityCount($finalOrderLines));
            } catch (Exception $e) {
                //save the latest error message in db
                $response = json_decode($e->getMessage(), true);
                saveLastErrorMessage($paymentID, $response['responseMsg']);

                echo json_encode(
                    array(
                        'status' => $response['responseResult'],
                        'message' => "Could not capture reservation. " . $response['responseMsg']
                    )
                );
                die();
            }

            echo json_encode(
                array(
                    'status' => "success",
                    'message' => "Reservation captured successfully"
                )
            );
            die();
        } // REFUND
        elseif ($action == 'Refund') {
            try {
                $refundAmount = Tools::getValue('amount');
                if (Tools::getValue('goodwillrefund') == 'yes') {
                    $goodWillRefund = true;
                }
                $finalOrderLines = $this->populateOrderLinesFromPost($orderLines, $orderLineGiftWrap , $orderID, $goodWillRefund);

                // Add a dummy orderLine array in case no orderLines are parsed in the refund
                if ($finalOrderLines === array() && $goodWillRefund) {
                    $finalOrderLines = $this->createDummyOrderLinesArr($refundAmount);
                }
                $api->refundAmount($paymentID, $finalOrderLines, $refundAmount);
                $refundUpdate = markAsRefund($paymentID, $this->getItemCaptureRefundQuantityCount($finalOrderLines));
                if (!$refundUpdate) {
                    throw new Exception("The refund could not be updated in database");
                }
            } catch (Exception $e) {
                $response = json_decode($e->getMessage(), true);
                saveLastErrorMessage($paymentID, $response['responseMsg']);

                echo json_encode(
                    array(
                        'status' => $response['responseResult'],
                        'message' => "Could not refund payment. " . $response['responseMsg']
                    )
                );
                die();
            }

            echo json_encode(
                array(
                    'status' => "success",
                    'message' => "Payment refunded successfully"
                )
            );
            die();
        } // RELEASE
        elseif ($action == 'Release') {
            try {
                $api->release($paymentID, $action);
                updatePaymentStatus($paymentID, "Payment Released");
            } catch (Exception $e) {
                $response = json_decode($e->getMessage(), true);
                saveLastErrorMessage($paymentID, $response['responseMsg']);

                echo json_encode(
                    array(
                        'status' => $response['responseResult'],
                        'message' => "Could not release reservation. " . $response['responseResult'] . ": " . $response['responseMsg']
                    )
                );
                die();
            }
            echo json_encode(
                array(
                    'status' => "success",
                    'message' => "Reservation released successfully"
                )
            );
            die();
        }
    }

    /* ******************************** */

    /**
     * Method for generating order lines from order backend
     * @param $orderLines
     * @param $orderLineGiftWrap
     * @param $orderID
     * @param bool $goodWillRefund
     * @return array
     */
    private function populateOrderLinesFromPost($orderLines, $orderLineGiftWrap = null, $orderID, $goodWillRefund = false)
    {
        $i = 0;
        $priceAfterDiscountRounded = 0;
        $priceAfterDiscount = 0;
        $totalQuantity = 0;
        $compensationAmountPerQuantity = 0;
        $altapayOrderLines = array();
        $discountPercentage = 0;


        $orderDetail = new Order((int)$orderID);
        $productDetailObject = new OrderDetail;

        $productDetail = $productDetailObject->getList($orderID);

        $compensationQuantity = 0;

        foreach ($orderLines as $key => $orderedQuantity) {
            if ($orderedQuantity > 0) {
                $productDetails = $productDetail[$key];
                if(!empty($productDetails)) {
                    $productName = $productDetails['product_name'];

                    $priceWithoutReductionTaxIncl = $productDetails['unit_price_tax_incl']/(1-($productDetails['reduction_percent']/100));
                    $basePrice = $productDetails['original_product_price'];


                    $cartRuleDiscounts = $this->getCartRuleDiscounts($orderDetail);
                    //Calculation of base price

                    if ($productDetails['reduction_percent'] > 0) {
                        $discountPercentage = $productDetails['reduction_percent'];

                    } else if(!empty($cartRuleDiscounts)) {
                        foreach ($cartRuleDiscounts as $cartRuleDiscount) {
                            if($productDetails['product_id'] == $cartRuleDiscount['productID']) {
                                $discountPercentage = $cartRuleDiscount['discountPercent'];
                              break;
                            } else {
                                $discountPercentage = 0;
                            }

                        }
                    }

                    if ($productDetails['product_attribute_id']) {
                        $itemID = $productDetails['product_reference'] . '-' . $productDetails['product_attribute_id'];
                    } else {
                        $itemID = $productDetails['product_reference'];
                    }

                    $productQuantity = $orderedQuantity;
                    $productTax = $priceWithoutReductionTaxIncl - $basePrice;
                    $goodsType = 'item';

                    if ($goodWillRefund) {
                        $goodsType = 'refund';
                    }

                    //Looping into the product array to get the difference regarding compensation amount
                    foreach ($productDetail as $proKeys) {
                        $productPriceTaxIncl = $proKeys['total_price_tax_incl'];
                        $priceAfterDiscountRounded += (float)number_format($productPriceTaxIncl - ($productPriceTaxIncl * ($discountPercentage / 100)), 2, '.', '');
                        $priceAfterDiscount += $productPriceTaxIncl - ($productPriceTaxIncl * ($discountPercentage / 100));
                        $totalQuantity += $proKeys['product_quantity'];
                    }
                    //Calculation of Total Compensation Amount
                    $compensationAmount = (float)number_format($priceAfterDiscountRounded - $priceAfterDiscount, 2, '.', '');
                    $compensationAmountPerQuantity = $compensationAmount / $totalQuantity;
                    $totalProductsTaxAmount = (float)number_format($productTax * $productQuantity, 2, '.', '');


                    // Mandatory keys for orderLines:
                    $altapayOrderLines[$i]['description'] = $productName; // Description of item.
                    $altapayOrderLines[$i]['itemId'] = $itemID; // Item number (SKU)
                    $altapayOrderLines[$i]['quantity'] = $productQuantity;
                    // Unit price excluding sales tax, only two digits.
                    $altapayOrderLines[$i]['unitPrice'] = (float)number_format($basePrice, 2, '.', '');

                    // Optional keys for orderLines:
                    $altapayOrderLines[$i]['taxAmount'] = $totalProductsTaxAmount; //Taxamount should be the total tax amount for order line.
                    // The type of order line it is. Should be one of the following: shipment|handling|item|refund
                    $altapayOrderLines[$i]['goodsType'] = $goodsType;
                    $altapayOrderLines[$i]['discount'] = $discountPercentage;

                    $compensationQuantity += $productQuantity;
                }
                else
                    {
                        $orderDetail = new Order((int)$orderID);
                        $shippingDetail = reset($orderDetail->getShipping());
                        // Mandatory keys for orderLines:
                        $altapayOrderLines[$i]['description'] = $shippingDetail['carrier_name']; // Description of item.
                        $altapayOrderLines[$i]['itemId'] = $shippingDetail['carrier_name']; // Item number (SKU)
                        $altapayOrderLines[$i]['quantity'] = 1;
                        // Unit price excluding sales tax, only two digits.
                        $altapayOrderLines[$i]['unitPrice'] = $shippingDetail['shipping_cost_tax_excl'];

                        // Optional keys for orderLines:
                        $altapayOrderLines[$i]['taxAmount'] = $shippingDetail['shipping_cost_tax_incl'] - $shippingDetail['shipping_cost_tax_excl']; //Taxamount should be the total tax amount for order line.
                        // The type of order line it is. Should be one of the following: shipment|handling|item|refund
                        $altapayOrderLines[$i]['goodsType'] = 'shipment';
                }
            } else {
                continue;
            }
            $i++;
        }
        if ($orderLineGiftWrap && $orderLineGiftWrap[0] == 1) {
            $orderDetail = new Order((int)$orderID);
            $giftWrappingFee = $orderDetail->total_wrapping;
            // Mandatory keys for orderLines:
            $altapayOrderLines[$i]['description'] = 'Gift Wrap'; // Description of item.
            $altapayOrderLines[$i]['itemId'] = 'giftwrap'; // Item number (SKU)
            $altapayOrderLines[$i]['quantity'] = 1;
            // Unit price excluding sales tax, only two digits.
            $altapayOrderLines[$i]['unitPrice'] = $giftWrappingFee;

            // The type of order line it is. Should be one of the following: shipment|handling|item|refund
            $altapayOrderLines[$i]['goodsType'] = 'item';

            $i++;

        }
        if ($compensationAmountPerQuantity > 0) {
            $altapayOrderLines[$i]['description'] = 'compensation'; // Description of item.
            $altapayOrderLines[$i]['itemId'] = 'comp-1'; // Item number (SKU)
            $altapayOrderLines[$i]['quantity'] = 1;
            // Unit price excluding sales tax, only two digits.
            $altapayOrderLines[$i]['unitPrice'] = $compensationQuantity * $compensationAmountPerQuantity;

            // Optional keys for orderLines:
            $altapayOrderLines[$i]['taxAmount'] = 0; //Taxamount should be the total tax amount for order line.
            // The type of order line it is. Should be one of the following: shipment|handling|item|refund
            $altapayOrderLines[$i]['goodsType'] = 'item';
        }

        return $altapayOrderLines;
    }

    /**
     * Method to get quantity count of captured or refunded items from order backend
     * @param $orderLines
     * @return array|false
     */
    public function getItemCaptureRefundQuantityCount($orderLines)
    {
        //get the array of the itemIDs to be captured or refund of each orderline
        $itemIDs = array_column($orderLines, 'itemId');
        // get the array of quantities of the Items to be captured or refund of each orderline
        $quantities = array_column($orderLines, 'quantity');

        return array_combine($itemIDs, $quantities);
    }
    /* ******************************** */

    /* Handle merchant details form */

    /**
     * Method for creating dummy order lines array in case no order lines selected for refund action
     * @param $totalAmount
     * @return array
     */
    private function createDummyOrderLinesArr($totalAmount)
    {
        $dummyItemOrderLine = array();
        // Mandatory keys for orderLines:
        $dummyItemOrderLine['description'] = 'Good-will refund';
        $dummyItemOrderLine['itemId'] = '100200';
        $dummyItemOrderLine['quantity'] = 1;
        $dummyItemOrderLine['unitPrice'] = $totalAmount;
        // Optional keys for orderLines:
        $dummyItemOrderLine['taxAmount'] = '0.00';
        $dummyItemOrderLine['taxPercent'] = '0.00';
        $dummyItemOrderLine['goodsType'] = 'refund';

        return array($dummyItemOrderLine);
    }
    /* ******************************** */

    /**
     * Handle submission of terminal form
     */
    private function postProcessTerminal()
    {
        $terminalRemoteName = $_POST['remote_name'];
        $terminalId = getTerminalId($terminalRemoteName)[0]['id_terminal'];
        // Update existing
        if ($idTerminal = Tools::getValue('id_terminal')) {
            $terminal = new Terminal((int)$idTerminal);
        } // New
        elseif(!($idTerminal = Tools::getValue('id_terminal')) && $terminalId ) {
            $idTerminal = $terminalId;
            $terminal = new Terminal((int)$idTerminal);
        }
        else {
            $terminal = new Terminal;
        }

        $altapayTerminal = new AltapayTerminal();

        // Currency supported?
        if (!$altapayTerminal->hasCurrency(Tools::getValue('currency'))) {
            $getVal = Tools::getValue('currency');
            $this->Mhtml .=
                sprintf('<div class="alert alert-danger">Selected terminal does not support currency %s</div>', $getVal);
            return false;
        }


        // Fields
        $fields = array('display_name', 'remote_name', 'icon_filename', 'currency', 'ccTokenControl_' , 'payment_type', 'active');
        foreach ($fields as $fieldName) {
            $terminal->{$fieldName} = Tools::getValue($fieldName);
        }

        // Validate
        $result = $terminal->validateFields(false, true);

        if ($result == 1) {

            $terminal->save();
            return true;
        } else {
            $this->Mhtml .= '<div class="alert alert-danger">' . $result . '</div>';
        }

        return false;
    }

    /**
     * Return a single terminal from ALTAPAY API
     */
    private function getAltapayTerminal($name)
    {
        $name = substr($name, 0, strpos($name, "("));
        $terminals = $this->getAltapayTerminals(true); // Objects please

        return $terminals[$name];
    }

    /**
     * Method for getting terminal status after creation
     */
    private function postProcessActive()
    {
        $idTerminal = Tools::getValue('id_terminal');
        if (!$idTerminal) {
            return;
        }

        $terminal = new Terminal((int)$idTerminal);
        $terminal->active = !(bool)$terminal->active;
        $terminal->save();
    }

    /**
     * Info displayed at the top on the module config page
     */
    protected function displayAltapay()
    {
        $html = $this->display(__FILE__, 'config.tpl');
        $html .= $this->renderForm();
        $html .= $this->renderTerminalList();

        return $html;
    }

    //hookPayment is utilized in prestashop 1.6

    /**
     * Merchant details form
     *
     * @return String HTML for display
     */
    public function renderForm()
    {
        $orderStatus = new OrderState($this->context->language->id);

        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Merchant details'),
                    'icon' => 'icon-cog'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('API username'),
                        'name' => 'ALTAPAY_USERNAME',
                        'required' => true
                    ),
                    array(
                        'type' => 'password',
                        'label' => $this->l('API password'),
                        'desc' => 'Fill this to change the password',
                        'name' => 'ALTAPAY_PASSWORD'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('API URL'),
                        'desc' => 'Typically your installation for testing will be 
                        "https://testgateway.altapaysecure.com/" and for production it will be 
                        "https://yourdomain.altapaysecure.com/". 
                        Your Username and Password may be different for testing and live.',
                        'name' => 'ALTAPAY_URL',
                        'required' => true
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Auto-capture status'),
                        'desc' => 'Enter the status to trigger autocapture when order status is updated',
                        'name' => 'AUTOCAPTURE_STATUS',
                        'options' => array(
                            'query' => $orderStatus->getOrderStates($this->context->language->id),
                            'id' => 'id_order_state',
                            'name' => 'name',
                            'default' => array(
                                'label' => 'Select an auto-capture status',
                                'value' => '',
                            )
                        ),
                        'required' => false
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),
        );
        if (isset($_GET['errorMessage'])) {
            $this->Mhtml .= '<div class="alert alert-danger">Incorrect payment gateway account details</div>';
        }
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name .
            '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fieldsForm));
    }

    /**
     * Field values for the merchant details form
     *
     * @return Array Array of curent configuration values
     */
    public function getConfigFieldsValues()
    {
        return array(
            'ALTAPAY_USERNAME' => Tools::getValue('ALTAPAY_USERNAME', Configuration::get('ALTAPAY_USERNAME')),
            'ALTAPAY_PASSWORD' => Tools::getValue('ALTAPAY_PASSWORD', Configuration::get('ALTAPAY_PASSWORD')),
            'ALTAPAY_URL' => Tools::getValue('ALTAPAY_URL', Configuration::get('ALTAPAY_URL')),
            'AUTOCAPTURE_STATUS' => Tools::getValue('AUTOCAPTURE_STATUS', Configuration::get('AUTOCAPTURE_STATUS')),
        );
    }

    /**
     * List of terminals
     *
     * @return String HTML for display
     */
    public function renderTerminalList()
    {
        $fields_list = array(
            'id_terminal' => array(
                'title' => $this->l('ID'),
                'width' => 100,
                'type' => 'text',
            ),
            'display_name' => array(
                'title' => $this->l('Name'),
                'width' => 140,
                'type' => 'text',
            ),
            'currency' => array(
                'title' => $this->l('Currency'),
                'width' => 50,
                'type' => 'text',
            ),
            'remote_name' => array(
                'title' => $this->l('Terminal'),
                'width' => 140,
                'type' => 'text',
            ),
            'ccTokenControl_' => array(
                'title' => $this->l('Token control'),
                'type' => 'bool',
                'width' => 'auto',
                'orderby' => false,
                'search' => false,
            ),
            'payment_type' => array(
                'title' => $this->l('Payment type'),
                'width' => 140,
                'type' => 'text',
            ),
            'active' => array(
                'title' => $this->l('Status'),
                'active' => 'active',
                'type' => 'bool',
                'width' => 'auto',
                'orderby' => false,
                'search' => false,
            ),
        );

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = array('edit');
        $helper->identifier = 'id_terminal';
        $helper->position_identifier = 'position';
        $helper->show_toolbar = true;
        $helper->toolbar_btn = array(
            'new' => array(
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&add' . $this->name . '&token='
                    . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Add new')
            )
        );
        $helper->title = 'Terminals';
        $helper->table = 'altapay_terminals';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->orderBy = 'id_terminal';
        $helper->orderWay = 'ASC';
        $content = Terminal::getTerminals();

        return $helper->generateList($content, $fields_list);
    }

    /**
     * Validate merchant details form
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
     */
    private function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('ALTAPAY_USERNAME', Tools::getValue('ALTAPAY_USERNAME'));
            $urlPath = preg_replace('/\s+/', '', Tools::getValue('ALTAPAY_URL'));
            if (Tools::substr($urlPath, -1) != '/') {
                Configuration::updateValue('ALTAPAY_URL', $urlPath .= '/');
            } elseif (Tools::substr($urlPath, -1) == '/') {
                Configuration::updateValue('ALTAPAY_URL', $urlPath);
            }
            if (Tools::getValue('ALTAPAY_PASSWORD') !== "") {
                Configuration::updateValue('ALTAPAY_PASSWORD', Tools::getValue('ALTAPAY_PASSWORD'));
            }
            if (Tools::getValue('AUTOCAPTURE_STATUS') !== "") {
                Configuration::updateValue('AUTOCAPTURE_STATUS', Tools::getValue('AUTOCAPTURE_STATUS'));
            }
        }
        $this->Mhtml .= '<div class="alert alert-success"> ' . $this->l('Settings updated') . '</div>';
    }

    /**
     * Displays the error that occurred in the hookActionOrderStatusUpdate method, if any.
     *
     * @param $params
     * @return mixed
     */
    public function hookBackOfficeHeader($params)
    {
        $cookie = $this->context->cookie;

        $this->context->controller->addJquery();
        $this->context->controller->addJS($this->_path . 'views/js/form.js', 'all');

        if ($cookie->altapayError) {
            $this->context->controller->errors[] = $cookie->altapayError;

            // unset the variable:
            $cookie->altapayError = null;
        }

        // always returns false because there is no template to display
        return false;
    }

    /**
     * Captures a payment when the status is changed to Shipped
     *
     * @param $params
     * @return mixed
     */
    public function hookActionOrderStatusUpdate($params)
    {
        $results = $this->selectOrder($params);

        if (!$results) {
            return;
        }

        $paymentID = $results['payment_id'];

        /** @var OrderStateCore */
        $newStatus = $params['newOrderStatus'];

        $shippedStatus = Configuration::get('PS_OS_SHIPPING');

        if (!empty($newStatus)) {
            if ($newStatus->id == $shippedStatus) { // a capture will be made if necessary
                $this->performCapture($paymentID, $params);
            }
        } else {
            return;
        }
        return $results;
    }

    /**
     * @param $params
     * @return mixed
     */
    private function selectOrder($params)
    {
        return Db::getInstance()->getRow('SELECT ' . _DB_PREFIX_ . 'altapay_order.*, '
            . _DB_PREFIX_ . 'altapay_transaction.amount FROM `'
            . _DB_PREFIX_ . 'altapay_order` INNER JOIN ' . _DB_PREFIX_ . 'altapay_transaction ON '
            . _DB_PREFIX_ . 'altapay_transaction.unique_id = ' . _DB_PREFIX_ . 'altapay_order.unique_id 
        WHERE id_order=' . $params['id_order']);
    }

    /**
     * Method is being triggered whenever capture action is performed
     * @param $paymentID
     * @param $params
     * @param bool $captureRemainedAmount
     */
    public function performCapture($paymentID, $params, $captureRemainedAmount = true)
    {
        try {
            $api = new MerchantAPI();
            $productDetails = new OrderDetail;
            $api->init($this->getAltapayUrl(), $this->getAPIUsername(), $this->getAPIPassword());
            $paymentDetails = $api->getPaymentDetails($paymentID);
            $orderReservedAmount = $paymentDetails->getReservedAmount();
            $orderCapturedAmount = $paymentDetails->getCapturedAmount();
            $amountToCapture = $orderReservedAmount - $orderCapturedAmount;

            $giftWrappingFee = null;

            if($productDetails->gift) {
                $giftWrappingFee = $productDetails->total_wrapping;
            }

            if ($amountToCapture == 0) {
                return;
            } elseif ($amountToCapture > 0 && $orderCapturedAmount == 0) {
                $orderLines = $this->populateOrderLinesFromPost(array_column($productDetails->getList($params['id_order']), 'product_quantity'), $giftWrappingFee,$params['id_order'], false);
                $api->captureAmount($paymentID, $orderLines, $amountToCapture);
                markAsCaptured($paymentID, $this->getItemCaptureRefundQuantityCount($orderLines));
            } elseif ($amountToCapture > 0 && $orderCapturedAmount > 0 && $captureRemainedAmount) {
                $orderLines = $this->createOrderStatusOrderLines($amountToCapture);
                $api->captureAmount($paymentID, $orderLines, $amountToCapture);
            }
        } catch (Exception $e) {
            $cookie = $this->context->cookie;

            // Saves the error in a cookie, to display it if a HTTP redirect occurs:
            $msg = $e->getMessage();
            $cookie->altapayError = Tools::displayError('Error trying to change the order status:' . $msg);

            // Saves the error in errors[], to display it if there is no HTTP redirect:
            $this->context->controller->errors[] = $cookie->altapayError;

            // Saves the error in the database. The function is loaded from helpers file.
            saveLastErrorMessage($paymentID, $cookie->altapayError);
        }
    }

    /**
     * Method is being triggered when release action is being performed
     * @param $paymentID
     * @param $params
     * @param bool $captureRemainedAmount
     * @return mixed
     */
    public function performRelease($paymentID, $params, $captureRemainedAmount = true)
    {
        try {
            $api = new MerchantAPI();
            $api->init($this->getAltapayUrl(), $this->getAPIUsername(), $this->getAPIPassword());
            $paymentDetails = $api->getPaymentDetails($paymentID);
            $orderReservedAmount = $paymentDetails->getReservedAmount();
            $orderCapturedAmount = $paymentDetails->getCapturedAmount();
            $refundedAmount = $paymentDetails->getRefundedAmount();

            if ($orderCapturedAmount == 0 && $refundedAmount == 0) {
                $releaseResult = $api->release($paymentID);
                if ($releaseResult->wasSuccessful()) {
                    // Saves the payment stays in the database. The function is loaded from helpers file.
                    updatePaymentStatus($paymentID, "Payment Released");
                }
            } else if ($orderCapturedAmount == $refundedAmount && $refundedAmount == $orderReservedAmount || $refundedAmount == $orderReservedAmount) {
                $api->release($paymentID);

            } else {
                $api->release($paymentID);
            }
        } catch (Exception $e) {
            $cookie = $this->context->cookie;

            // Saves the error in a cookie, to display it if a HTTP redirect occurs:
            $msg = json_decode($e->getMessage());
            $cookie->altapayError = Tools::displayError('Error trying to change the order status: ' . $msg->responseMsg);

            // Saves the error in errors[], to display it if there is no HTTP redirect:
            $this->context->controller->errors[] = $cookie->altapayError;

            // Saves the error in the database. The function is loaded from helpers file.
            saveLastErrorMessage($paymentID, $cookie->altapayError);

            return $cookie->altapayError;
        }
    }

    /**
     * Method being triggered when complete capture is being performed without selecting orderlines
     * @param $amountToCapture
     * @return array
     */
    public function createOrderStatusOrderLines($amountToCapture)
    {
        $orderLines = array();
        $orderLines[] = array(
            'description' => 'Complete amount Capture',
            'itemId' => 'Capture-1',
            'quantity' => 1,
            'unitPrice' => (float)number_format($amountToCapture, 2, '.', ''),
            'taxAmount' => 0,
            'goodsType' => 'handling'
        );

        return $orderLines;
    }

    /**
     * Captures a payment when the status is changed to Delivered.
     * @param $params
     * @return mixed|void
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        global $cookie;
        $results = $this->selectOrder($params);
        if (!$results) {
            return;
        }
        $orderStatus = new OrderState($this->context->language->id);
        $configuredStatus = $orderStatus->getOrderStates($this->context->language->id);
        $objectID = array_search($this->captureStatus, array_column($configuredStatus, 'id_order_state'));
        if (!empty($objectID)) {
            $orderstatusName = $configuredStatus[$objectID]['name'];
        }

        $currentOrderStatus = $params['newOrderStatus'];
        if (!empty($currentOrderStatus)) {
            $currentOrderStatus = $params['newOrderStatus']->name;
            if ($currentOrderStatus == $orderstatusName) {
                $paymentID = $results['payment_id'];
                $this->performCapture($paymentID, $params, false);
            }
        } else {
            return;
        }
        return $results;
    }

    /**
     * Displays payment info on order detail pages in back office
     */
    public function hookAdminOrder($params)
    {
        $results = $this->selectOrder($params);

        if (!$results) {
            return false;
        }

        # collect order info

        $orderDetail = new Order((int)$params['id_order']);
        $productDetail = $orderDetail->getProducts();
        $shippingDetail = $orderDetail->getShipping();
        if($orderDetail->gift) {
            $giftWrappingFee = $orderDetail->total_wrapping;
            $this->smarty->assign('ap_gift_wrapping', $giftWrappingFee);
        }


        $orderId = $params['id_order'];
        $discounts = $this->getCartRuleDiscounts($orderDetail);

        $this->smarty->assign('ap_order_id', $orderId);
        $this->smarty->assign('ap_product_details', $productDetail);
        $this->smarty->assign('ap_shipping_details', $shippingDetail);
        $this->smarty->assign('ap_coupon_discount', $discounts);
        $apOrders = array();
        $apOrderlines = $this->getOrderActions($results['payment_id']);
        foreach ($productDetail as $product) {
            $apOrders[$product['product_id']] = array(
                'captured' => "0",
                'refunded' => "0",
            );

            foreach ($apOrderlines as $orderline) {
                if ($orderline['product_id'] == $product['product_id']) {
                    $apOrders[$product['product_id']]['captured'] = $orderline['captured'];
                    $apOrders[$product['product_id']]['refunded'] = $orderline['refunded'];
                }
            }
        }
        $this->smarty->assign('ap_orders', $apOrders);

        # collect info from Altapay - fail gracefully
        $api = new MerchantAPI();
        try {
            $api->init($this->getAltapayUrl(), $this->getAPIUsername(), $this->getAPIPassword());
            $ap_payment = $api->getPaymentDetails($results['payment_id']);
            $this->smarty->assign('ap_paymentinfo', $ap_payment);
        } catch (Exception $e) {
            $this->smarty->assign('ap_error', "Error: " . $e->getMessage());
        }

        # prepare for view
        $paymentinfo = array(
            'Transaction Date' => Tools::htmlentitiesUTF8(date("F j, Y, g:i a", $results['date_add'])),
            'Transaction ID' => Tools::htmlentitiesUTF8($results['unique_id']),
            'Payment ID' => Tools::htmlentitiesUTF8($results['payment_id']),
            'Card Brand' => Tools::htmlentitiesUTF8($results['cardBrand']),
            'Card Number' => Tools::htmlentitiesUTF8($results['cardMask']),
            'Card Country' => Tools::htmlentitiesUTF8($results['cardCountry']),
            'Payment Type' => Tools::htmlentitiesUTF8($results['paymentType']),
            'Payment Status' => Tools::htmlentitiesUTF8($results['paymentStatus']),
            'Payment Nature' => Tools::htmlentitiesUTF8($results['paymentNature']),
            'Latest Error' => Tools::htmlentitiesUTF8($results['latestError']),
        );
        $fet = $this->context->link;
        $tname = $this->name;
        $this->smarty->assign('paymentinfo', $paymentinfo);
        $this->smarty->assign('payment_id', $results['payment_id']);
        $this->smarty->assign('payment_amount', $results['amount']);
        $this->smarty->assign('payment_captured', !$results['requireCapture']);
        $this->smarty->assign('this_path', $this->_path);
        $this->smarty->assign('ajax_url', $fet->getAdminLink('AdminModules') . '&configure=' . $tname . "&payment_actions");
        $this->smarty->assign('token', Tools::getAdminTokenLite('AdminModules'));

        $this->context->controller->addCSS($this->_path . 'views/css/admin_order.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/admin_order.js');

        return $this->display(__FILE__, '/views/templates/hook/admin_order.tpl');
    }

    /**
     * Method to get order actions from db against payment id
     * @param $paymentId
     * @return mixed
     */
    private function getOrderActions($paymentId)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_orderlines` WHERE altapay_payment_id = "' . $paymentId . '"';
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Hook payment is being triggered for prestashop 1.6 for payment processing from checkout page
     * @param $params
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
        $paymentMethods = Terminal::getActiveTerminalsForCurrency($currency->iso_code);

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_altapay' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'methods' => $paymentMethods,
            'PS_STOCK_MANAGEMENT' => Configuration::get('PS_STOCK_MANAGEMENT'),
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * Method for checking the current currency in cart with module selected currency
     * @param $cart
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
     * @param $cart
     * @return Currency
     */
    private function getCurrencyForCart($cart)
    {
        return new Currency($cart->id_currency);
    }

    /**
     * Hook for displaying custom section in  user account page in prestashop
     * @return mixed
     */
    public function hookDisplayCustomerAccount()
    {
        if(_PS_VERSION_ >= '1.7.0.0') {
            return $this->display(__FILE__, 'savedCreditCards.tpl');
        }
    }

    /**
     * Hook payment is being triggered for prestashop 1.7 for payment processing from checkout page
     * @param $params
     * @return array|void
     */
    public function hookPaymentOptions($params)
    {
        $savedCreditCard = array();

        if (!$this->active) {
            return;
        }
        // Check that we can accept this currency (currency restrictions)
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        if ($this->context->customer->isLogged()) {
            $customerID = $this->context->customer->id;
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'altapay_saved_credit_card` WHERE userID ='.$customerID;
            $results = Db::getInstance()->executeS($sql);

            if(!empty($results)) {
                foreach($results as $result) {
                    $savedCreditCard[] = array (
                        'creditCard' => $result['creditCardNumber'],
                        'cardName' => $result['cardName'],
                        'cardExpiryDate' => $result['cardExpiryDate']);
                }
                $this->context->smarty->assign('savedCreditCard', $savedCreditCard);
            }

        }

        $this->context->controller->addCSS($this->_path . 'css/payment.css', 'all');
        // Fetch payment methods
        $currency = $this->getCurrencyForCart($params['cart']);
        $paymentMethods = Terminal::getActiveTerminalsForCurrency($currency->iso_code);

        $this->smarty->assign(
            $this->getTemplateVarInfos()
        );

        $paymentsOptions = array();
        foreach ($paymentMethods as $paymentMethod) {
            $this->context->smarty->assign('ccTokenControl', $paymentMethod['ccTokenControl_']);
            if(!empty($customerID)){
                $this->context->smarty->assign('customerID', $customerID);
            }
            $actionText = $this->l('Pay with') . ' ' . $paymentMethod['display_name'];
            $paymentOptions = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $terminal_id = $paymentMethod['id_terminal'];
            $terminal = array('method' => $terminal_id);
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
     * @param $params
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        $this->context->controller->addJquery();
        $this->context->controller->addJS($this->_path . '/views/js/creditCardFront.js', 'all');
    }

    /**
     * Method to get template variable information like path, ssl path, methods
     * @return array
     */
    public function getTemplateVarInfos()
    {
        $cart = $this->context->cart;
        $currency = $this->getCurrencyForCart($cart);
        $paymentMethods = Terminal::getActiveTerminalsForCurrency($currency->iso_code);

        return array(
            'this_path' => $this->_path,
            'this_path_altapay' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'methods' => $paymentMethods,
            'PS_STOCK_MANAGEMENT' => Configuration::get('PS_STOCK_MANAGEMENT'),
        );
    }

    /**
     * Hook triggered at the time of payment returns
     * @param $params
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        //Prestashop 1.7 doesn't have the $params['objOrder']
        if (!isset($params['objOrder']) || !is_object($params['objOrder'])) {
            $params['objOrder'] = $params['order'];
        }

        $state = $params['objOrder']->getCurrentState();
        $results = Db::getInstance()->getRow('SELECT * 
        FROM `' . _DB_PREFIX_ . 'altapay_order` WHERE id_order=' . $params['objOrder']->id);
        if ($state == Configuration::get('PS_OS_PAYMENT') || $state == Configuration::get('PS_OS_OUTOFSTOCK')) {
            $this->smarty->assign(array(
                'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'status' => 'ok',
                'unique_id' => $results['unique_id'],
                'payment_id' => $results['payment_id'],
                'id_order' => $params['objOrder']->id
            ));
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference)) {
                $this->smarty->assign('reference', $params['objOrder']->reference);
            }
        } else {
            //if 'open' = Configuration::get('ALTAPAY_OS_PENDING')
            $this->smarty->assign(array(
                'total_to_pay' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'status' => 'open',
                'unique_id' => $results['unique_id'],
                'payment_id' => $results['payment_id'],
                'id_order' => $params['objOrder']->id
            ));
            if (isset($params['objOrder']->reference) && !empty($params['objOrder']->reference)) {
                $this->smarty->assign('reference', $params['objOrder']->reference);
            }
        }
        return $this->display(__FILE__, 'payment_return.tpl');
    }

    /**
     * Creates the transaction to ALTAPAY which should result in the payment form page URL.
     * @param bool $payment_method
     * @param $savedCreditCard
     * @return array
     *  If the transaction failed, the array contains information about the failure
     * @throws Exception
     */
    public function createTransaction($payment_method = false, $savedCreditCard)
    {
        //$userType = 'private';
        $customerCreatedDate = null;
        $cart = $this->context->cart;
        $ccToken = null;

        // terminal
        $terminal = $this->getTerminal($payment_method, $this->context->currency->iso_code);
        if (!is_object($terminal)) {
            $message = "Could not determine remote terminal - possibly currency mismatch";
            Logger::addLog($message, 3, 0, $this->name, $this->id, true);
            return array(
                'success' => false,
                'result' => 'failure',
                'message' => $message,
                'additionalInfo' => $message,
                'payment_form_url' => false,
            );
        }
        $cgConf = array();
        // config
        $cgConf['user'] = $this->getAPIUsername();
        $cgConf['password'] = $this->getAPIPassword();
        $cgConf['payment_type'] = $terminal->payment_type;
        $cgConf['altapay_url'] = $this->getAltapayUrl();
        $cgConf['currency'] = $this->context->currency->iso_code;
        $cgConf['language'] = $this->context->language->iso_code;
        $cgConf['uniqueid'] = $cart->id;
        $cgConf['terminal'] = $terminal->remote_name;
        $cgConf['cookie'] = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : null;

        $callback = array();
        // callbacks
        $callback['callback_form'] = $this->context->link->getModuleLink(
            $this->name,
            'callbackform',
            array(),
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_ok'] = $this->context->link->getModuleLink(
            $this->name,
            'callbackok',
            array(),
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_fail'] = $this->context->link->getModuleLink(
            $this->name,
            'callbackfail',
            array(),
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_open'] = $this->context->link->getModuleLink(
            $this->name,
            'callbackopen',
            array(),
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_notification'] = $this->context->link->getModuleLink(
            $this->name,
            'callbacknotification',
            array(),
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $callback['callback_redirect'] = $this->context->link->getModuleLink(
            $this->name,
            'callbackredirect',
            array(),
            true,
            $this->context->language->id,
            $this->context->shop->id
        );
        $customer = array();
        // customer info
        $customer['billing_firstname'] = $this->context->customer->firstname;
        $customer['billing_lastname'] = $this->context->customer->lastname;
        $customer['email'] = $this->context->customer->email;

        // billing address
        $invoice_address = new Address($this->context->cart->id_address_invoice);
        $country = new Country($invoice_address->id_country);
        $state = new State($invoice_address->id_state);
//        $customer['type'] = $userType;$userType

        $customer['billing_address'] = $invoice_address->address1;
        $customer['billing_city'] = $invoice_address->city;
        $customer['billing_postal'] = $invoice_address->postcode;
        $customer['billing_region'] = $state->iso_code;
        $customer['billing_country'] = $country->iso_code;

        // phone
        $invoiceAph = $invoice_address->phone;
        $customer['customer_phone'] = $invoice_address->phone != '' ? $invoiceAph : $invoice_address->phone_mobile;

        // shipping address
        $sp_address = new Address($this->context->cart->id_address_delivery);
        $sp_country = new Country($sp_address->id_country);
        $sp_state = new State($sp_address->id_state);
        $customer['shipping_address'] = $sp_address->address1;
        $customer['shipping_city'] = $sp_address->city;
        $customer['shipping_postal'] = $sp_address->postcode;
        $customer['shipping_region'] = $sp_state->iso_code;
        $customer['shipping_country'] = $sp_country->iso_code;
        $customer['shipping_firstname'] = $sp_address->firstname;
        $customer['shipping_lastname'] = $sp_address->lastname;

//        if ($userType == 'business') {
//            $customerInfo['shipping_ref'] = ''; //need to tackle it in case of type = business
//            $customerInfo['company_name'] = '';
//            $customerInfo['company_type'] = '';
//            $customerInfo['vat_id'] = '';
//            $customerInfo['shipping_att'] = '';
//            $customerInfo['shipping_ref'] = '';
//            $customerInfo['billing_att'] = '';
//            $customerInfo['billing_ref'] = '';
//        }
        //Calling transactionInfo method from helpers file
        $transactionInfo = transactionInfo();

        // Decode the HTML entities from the address data
        $customer = $this->decodeHtmlEntitiesArrayValues($customer);

        $amount = $cart->getOrderTotal(true, Cart::BOTH);

        if ($this->context->customer->isLogged()) {
            $customerCreatedDate = convertDateTimeFormat($this->context->customer->date_add);
        }

        if (!is_null($savedCreditCard)) {
            $sql = "SELECT ccToken FROM `" . _DB_PREFIX_ . 'altapay_saved_credit_card` WHERE creditcardNumber ="'.$savedCreditCard.'"';
            $results = Db::getInstance()->executeS($sql);
            foreach ($results as $result){
                $ccToken = $result['ccToken'];
            }
        }

        $api = null;
        try {
            $api = new AltapayMerchantAPI($cgConf['altapay_url'], $cgConf['user'], $cgConf['password'], null);
            $response = $api->login();

            if (!$response->wasSuccessful()) {
                $resErrMsg = $response->getErrorMessage();
                $resErrCode = $response->getErrorCode();
                throw new Exception("Could not login to the Merchant API: " . $resErrMsg, $resErrCode);
            }
        } catch (Exception $e) {
            Logger::addLog($e->getMessage(), 3, $e->getCode(), $this->name, $this->id, true);
            return array(
                'success' => false,
                'result' => 'failure',
                'message' => 'unable to connect to gateway',
                'additionalInfo' => $e->getMessage(),
                'payment_form_url' => false,
            );
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
                $resErrMsg = $response->getErrorMessage();
                $resErrCode = $response->getErrorCode();
                throw new Exception("Could not create the payment request: " . $resErrMsg, $resErrCode);
            }

            return array(
                'success' => true,
                'uniqueid' => $cgConf['uniqueid'],
                'amount' => $amount,
                'result' => 'Success',
                'payment_form_url' => $response->getRedirectURL(),
            );
        } catch (Exception $e) {
            Logger::addLog($e->getMessage(), 3, $e->getCode(), $this->name, $this->id, true);
            return array(
                'success' => false,
                'result' => 'failure',
                'message' => 'unable to obtain payment form url',
                'additionalInfo' => $e->getMessage(),
                'payment_form_url' => false,
            );
        }
    }

    /**
     * Get the remote name of the terminal associated with
     * this payment method. Will check if currency matches the remote terminal.
     * @param bool $terminal_id
     * @param bool $currency
     * @return bool|Terminal
     */
    private function getTerminal($terminal_id = false, $currency = false)
    {
        if ($terminal_id === false || $currency === false) {
            return false;
        }

        $terminal = new Terminal($terminal_id);
        $terminalId = $terminal->id_terminal;
        $terminalCurr = $terminal->currency;
        if ($terminalId === null || Tools::strtolower($terminalCurr) !== Tools::strtolower($currency)) {
            return false;
        }

        return $terminal;
    }

    /**
     * @param $arr
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

    //function will be used to create the capture or refund quantity count in order to store in the db

    /** @var CartCore */
    private function getOrderLines($cart)
    {
        $i = 0;
        $orderLines = array();
        $products = $cart->getProducts();
        $vouchers = $this->getVoucherDetails();
        $cartID = $cart->id;
        $orderDetails = array();
        if (!empty($vouchers)) {
                foreach ($products as $p) {
                    $discountPercent = 0;
                    $discountedAmount = 0;
                    $productPriceAfterDiscount =0;
                    $productID = $p['id_product'];
                    $unitCode = 'unit';
                    if ($p['cart_quantity'] > 1) {
                        $unitCode = 'units';
                    }
                    if ($p['id_product_attribute']) {
                        $itemID = $p['reference'] . '-' . $p['id_product_attribute'];
                    } else {
                        $itemID = $p['reference'];
                    }
                    $rateBasePrice = 1 + $p['rate'] / 100;

                    $productUrl = $this->context->link->getProductLink($p['id_product']);
                    $productImageUrl = $this->context->link->getImageLink($p['link_rewrite'], $p['id_image'], 'home_default');
                    //Calculation of base price
                    $basePrice = round($p['price_without_reduction'], 2) / $rateBasePrice;
                    $singleProductTaxAmount = $p['price_without_reduction'] - $basePrice;

                    foreach($vouchers as $voucher)
                    {
                        if(in_array($productID, $voucher['products']) || $voucher['products'] == 'all') {
                            if (empty($discountPercent)) {
                                $discountPercent += $voucher['reductionPercent'];
                                $discountedAmount = $basePrice * ($discountPercent/100);
                                $productPriceAfterDiscount = $basePrice - $discountedAmount;
                            } else {
                                $totalDiscountedAmount = $discountedAmount + ($productPriceAfterDiscount * ($voucher['reductionPercent']/100));
                                $discountPercent = ($totalDiscountedAmount / $basePrice) * 100;
                            }
                        }

                    }

                    $orderDetails[$i]['productID'] = $productID;
                    $orderDetails[$i]['discountPercent'] = $discountPercent;
                    // Mandatory keys for orderLines:
                    $orderLines[$i]['description'] = $p['name']; // Description of item.
                    $orderLines[$i]['itemId'] = $itemID; // Item number (SKU)
                    $orderLines[$i]['quantity'] = $p['cart_quantity'];

                    // Unit price excluding sales tax, only two digits.
                    $orderLines[$i]['unitPrice'] = number_format(floor(100 * $basePrice) / 100, 2, '.', '');

                    // Optional keys for orderLines:
                    $orderLines[$i]['taxAmount'] = number_format($p['cart_quantity'] * $singleProductTaxAmount, 2, '.', ''); // Tax amount should be the total tax amount.
                    $orderLines[$i]['taxPercent'] = number_format(($singleProductTaxAmount / $basePrice) * 100, 2, '.', '');
                    //$orderLines[$i]['taxPercent'] = $couponAmount; //Tax Rate specified
                    $orderLines[$i]['goodsType'] = 'item'; // Order line Type - one of the following shipment|handling|item
                    $orderLines[$i]['unitCode'] = $unitCode;
                    $orderLines[$i]['discount'] = $discountPercent;
                    $orderLines[$i]['imageUrl'] = $productImageUrl;
                    $orderLines[$i]['productUrl'] = $productUrl;

                    $i++;
                }
        } else {
            foreach ($products as $p) {
                $unitCode = 'unit';
                if ($p['cart_quantity'] > 1) {
                    $unitCode = 'units';
                }
                if ($p['id_product_attribute']) {
                    $itemID = $p['reference'] . '-' . $p['id_product_attribute'];
                } else {
                    $itemID = $p['reference'];
                }
                $discountAmount = $p['price_without_reduction'] - $p['price_with_reduction'];
                $amountBeforeTax = ($discountAmount / $p['price_without_reduction']) * 100;
                $rateBasePrice = 1 + $p['rate'] / 100;
                $productUrl = $this->context->link->getProductLink( $p['id_product']);
                $productImageUrl = $this->context->link->getImageLink( $p['link_rewrite'],$p['id_image'], 'home_default');
                //Calculation of base price
                $basePrice = round($p['price_without_reduction'])/ $rateBasePrice;
                $singleProductTaxAmount = $p['price_without_reduction'] - $basePrice;
                // Mandatory keys for orderLines:
                $orderLines[$i]['description'] = $p['name']; // Description of item.
                $orderLines[$i]['itemId'] = $itemID; // Item number (SKU)
                $orderLines[$i]['quantity'] = $p['cart_quantity'];

                // Unit price excluding sales tax, only two digits.
                $orderLines[$i]['unitPrice'] = number_format(floor(100 * $basePrice) / 100, 2, '.', '');

                // Optional keys for orderLines:
                $orderLines[$i]['taxAmount'] = number_format($p['cart_quantity'] * $singleProductTaxAmount, 2, '.', ''); // Tax amount should be the total tax amount.
                $orderLines[$i]['taxPercent'] = number_format(($singleProductTaxAmount / $basePrice) * 100, 2, '.', '');
                $orderLines[$i]['goodsType'] = 'item'; // Order line Type - one of the following shipment|handling|item
                $orderLines[$i]['unitCode'] = $unitCode;
                $orderLines[$i]['discount'] = $amountBeforeTax;
                $orderLines[$i]['imageUrl'] = $productImageUrl;
                $orderLines[$i]['productUrl'] = $productUrl;

                $i++;
            }
        }

        $giftWrappingFee = 0;
        if($cart->gift) {
            $giftWrappingFee = $cart->getGiftWrappingPrice();
        }
        if($giftWrappingFee) {
            $orderLines[$i]['description'] = 'Gift Wrap';
            $orderLines[$i]['itemId'] = 'giftwrap';
            $orderLines[$i]['quantity'] = 1;
            $orderLines[$i]['unitPrice'] = $giftWrappingFee;  // Shipping cost without tax
            //$orderLines[$i]['taxPercent'] = number_format(($carrierTax / $carrier) * 100, 2, '.', '');
            $orderLines[$i]['goodsType'] = 'item';
            $i++;
        }

        $carrier = $cart->getSummaryDetails()['carrier'];
        $carrierCostWithTax = $cart->getTotalShippingCost();
        $carrierCostWithoutTax = $cart->getTotalShippingCost(null, false);
        $carrierTax = $carrierCostWithTax - $carrierCostWithoutTax;
        $orderLines[$i]['description'] = $carrier->delay;
        $orderLines[$i]['itemId'] = $carrier->name;
        $orderLines[$i]['quantity'] = 1;
        $orderLines[$i]['unitPrice'] = $carrierCostWithoutTax;  // Shipping cost without tax
        // Optional keys for orderLines:
        $orderLines[$i]['taxAmount'] = $carrierTax;
        //$orderLines[$i]['taxPercent'] = number_format(($carrierTax / $carrier) * 100, 2, '.', '');
        $orderLines[$i]['goodsType'] = 'shipment';

        if(!empty($orderDetails)){
            $orderDetails = json_encode($orderDetails);
            $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'altapay_cartInfo
		(id_cart, productDetails, date_add)
        VALUES ' .
                "('".$cartID."', '". $orderDetails. "', '" . time() . "')";
            Db::getInstance()->Execute($sql);
        }

        return $orderLines;
    }

    /**
     * Returns the array with products in cart rule group along with the reduction percentage
     * @param $couponID
     * @param $reductionPercent
     * @return array
     */
    private function getCartRuleGroupProducts($couponID, $reductionPercent)
    {
        $cartRuleGroupProducts = array();
        $cartRuleGroups = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'cart_rule_product_rule_group WHERE id_cart_rule = ' . $couponID);
        foreach ($cartRuleGroups as $cartRuleGroup) {
            $cartRuleGroupProducts['reductionPercent'] = $reductionPercent;
            $cartRuleGroupProducts['products'] = $this->getCartRuleGroupProductIDs($cartRuleGroup['id_product_rule_group']);
        }

        return $cartRuleGroupProducts;
    }

    /**
     * Return IDs of the products in cart rule group
     * @param $cartRuleGroupID
     * @return array
     */
    private function getCartRuleGroupProductIDs($cartRuleGroupID)
    {
        $productIDs = array();
        $cartRuleGroups = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'cart_rule_product_rule_value WHERE id_product_rule = ' . $cartRuleGroupID);
        foreach ($cartRuleGroups as $cartRuleGroup) {
            $productIDs[] = $cartRuleGroup['id_item'];
        }

        return $productIDs;
    }

    /**
     * Returns array of applied voucher details from cart
     * @return array
     */
    private function getVoucherDetails()
    {
        $voucherDetails = array();
        $appliedCartRules = $this->context->cart->getCartRules();
        foreach($appliedCartRules as $cartRule)
        {
            $reductionPercent = $cartRule['reduction_percent'];
            if(!empty($cartRule['reduction_product'])){

                $voucherDetails[$cartRule['id_cart_rule']] = $this->getCartRuleGroupProducts($cartRule['id_cart_rule'], $reductionPercent );
            }
            else{
                $voucherDetails[$cartRule['id_cart_rule']] = array(
                    'reductionPercent' => $reductionPercent,
                    'products'=>'all');
            }
        }
        return $voucherDetails;
    }

    /**
     * Returns array of cart rule discounts applied on each product from created order
     * @param $order
     * @return int|mixed
     */
    private function getCartRuleDiscounts($order)
    {
        $cartRuleDiscounts = 0;
        $discountPercent = reset(Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'altapay_cartInfo WHERE id_cart = ' . $order->id_cart));
        if(!empty($discountPercent)) {
            $discountPercent = json_decode($discountPercent['productDetails']);
            $cartRuleDiscounts = json_decode(json_encode($discountPercent), true);
        }
        return $cartRuleDiscounts;
    }

}
