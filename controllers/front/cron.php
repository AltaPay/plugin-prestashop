<?php
/**
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License version 3.0
* that is bundled with this package in the file LICENSE.txt
* It is also available through the world-wide-web at this URL:
* https://opensource.org/licenses/AFL-3.0
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to a newer
* versions in the future. If you wish to customize this module for your needs
* please refer to CustomizationPolicy.txt file inside our module for more information.
*
* @author Webkul IN
* @copyright Since 2010 Webkul
* @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
* @summary Updated by AltaPay for processing recurring payments. Instead of Webkul's cron controller, this should be used to create and schedule automatic subscription orders and processing recurring payments.
*/
class AltapayCronModuleFrontController extends ModuleFrontController
{
    public $todayDate;
    public $todayDateTime;

    public function __construct()
    {
        parent::__construct();
        $this->todayDate = date('Y-m-d');
        $this->todayDateTime = date('Y-m-d H:i:s');
    }

    /**
     * Initialize cron controller.
     *
     * @see ModuleFrontController::init()
     */
    public function init()
    {
        parent::init();
        if (Module::isEnabled('wkproductsubscription')) {
            include_once _PS_MODULE_DIR_ . 'wkproductsubscription/classes/WkSubscriptionRequired.php';

            $total_order_scheduled = 0;
            $total_order_create = 0;

            // Get tomorrow scheduled orders
            $tomScheduledOrders = $this->getTomrowScheduledOrders();

            $objGlobal = new WkProductSubscriptionGlobal();

            if ($tomScheduledOrders) {
                foreach ($tomScheduledOrders as $subsData) {
                    if ($subsData['payment_module'] == 'wkstripepayment'
                        && WkProductSubscriptionGlobal::isWkStripeRecurringEnabled()
                    ) {
                        $stripeResponse = json_decode($subsData['payment_response'], true);
                        if ($stripeResponse) {
                            if (!$this->checkStripeSubscriptionStatus($stripeResponse['stripe_subscription_id'])) {
                                $objStripe = new WkSubscriptionStripe();
                                $objStripe->cancelStripeSubscription(
                                    $subsData['id_customer'],
                                    $stripeResponse['stripe_subscription_id']
                                );
                                continue;
                            }
                        } else {
                            continue;
                        }
                    } elseif ($subsData['payment_module'] == 'psadyenpayment'
                        && WkProductSubscriptionGlobal::isWkAdyenRecurringEnabled()
                    ) {
                        $adyenObj = new WkSubscriptionAdyen();
                        $adyenSubData = $adyenObj->getAdyenSubscriptionDetailsByIdCustomer(
                            $subsData['id_customer'],
                            $subsData['first_order_id'],
                            $subsData['id_product']
                        );

                        if ($adyenSubData) {
                            if (!$adyenObj->checkIfAdyenSubscriptionActive($adyenSubData['id'])) {
                                $adyenObj->cancelAdyenSubscription(
                                    $adyenSubData['id'],
                                    $subsData
                                );
                                continue;
                            }
                        } else {
                            continue;
                        }
                    } elseif ($subsData['payment_module'] == 'wkwepay'
                        && WkProductSubscriptionGlobal::isWkWepayRecurringEnabled()
                    ) {
                        $paymentData = json_decode($subsData['payment_response'], true);
                        $wepayObj = new WkSubscriptionWepay();
                        $wePaySubData = $wepayObj->getWepaySubscriptionDetailsByIdCustomer(
                            $subsData['id_customer'],
                            $subsData['first_order_id'],
                            $subsData['id_product']
                        );

                        if ($wePaySubData) {
                            if ($wePaySubData['is_plan_cancel']
                                || (strtotime($wePaySubData['expiry_date']) < strtotime(date('H:i:s')))
                            ) {
                                $wepayObj->cancelWepaySubscription(
                                    (int) $subsData['id_customer'],
                                    $paymentData['id']
                                );
                                continue;
                            }
                        } else {
                            continue;
                        }
                    } elseif ($subsData['payment_module'] == 'wkpaypalsubscription'
                        && WkProductSubscriptionGlobal::isWkPayPalRecurringEnabled()
                    ) {
                        $objPayPal = new WkSubscriptionPayPal();
                        $payPalSubsId = $subsData['payment_response'];
                        if ($payPalSubsId) {
                            if (!$this->checkPayPalSubscriptionStatus($payPalSubsId)) {
                                continue;
                            }
                        } else {
                            continue;
                        }
                    }

                    $id_subscription = $subsData['id_subscription'];
                    $order_date = date('Y-m-d', strtotime($subsData['order_date']));
                    $next_order_delivery_date = date('Y-m-d', strtotime($subsData['next_order_delivery_date']));
                    if (!$objGlobal->checkIfScheduleCreated($id_subscription, $order_date)) {
                        $scheudleObj = new WkSubscriberScheduleModel();
                        $scheudleObj->id_subscription = (int) $id_subscription;
                        $scheudleObj->order_date = $order_date;
                        $scheudleObj->delivery_date = $next_order_delivery_date;
                        $scheudleObj->is_order_created = 0;
                        $scheudleObj->is_email_send = $objGlobal->sendPreOrderMail((int) $subsData['id_subscription']);
                        $scheudleObj->active = 1;
                        if ($scheudleObj->save()) {
                            ++$total_order_scheduled;
                        }
                    }
                }
            }

            // Get scheduled orders
            $todayScheduledOrders = $this->getTodayScheduledOrders();

            if ($todayScheduledOrders) {
                foreach ($todayScheduledOrders as $subscriptionData) {
                    if ($subscriptionData['payment_module'] == 'wkstripepayment'
                        && WkProductSubscriptionGlobal::isWkStripeRecurringEnabled()
                    ) {
                        $stripeResponse = json_decode($subscriptionData['payment_response'], true);
                        if ($stripeResponse) {
                            if (!$this->checkStripeSubscriptionStatus($stripeResponse['stripe_subscription_id'])) {
                                $objStripe = new WkSubscriptionStripe();
                                $objStripe->cancelStripeSubscription(
                                    $subscriptionData['id_customer'],
                                    $stripeResponse['stripe_subscription_id']
                                );
                                continue;
                            }
                        } else {
                            continue;
                        }
                    } elseif ($subscriptionData['payment_module'] == 'psadyenpayment'
                        && WkProductSubscriptionGlobal::isWkAdyenRecurringEnabled()
                    ) {
                        $adyenObj = new WkSubscriptionAdyen();
                        $adyenSubData = $adyenObj->getAdyenSubscriptionDetailsByIdCustomer(
                            $subscriptionData['id_customer'],
                            $subscriptionData['first_order_id'],
                            $subscriptionData['id_product']
                        );

                        if ($adyenSubData) {
                            if (!$adyenObj->checkIfAdyenSubscriptionActive($adyenSubData['id'])) {
                                $adyenObj->cancelAdyenSubscription(
                                    $adyenSubData['id'],
                                    $subscriptionData
                                );
                                continue;
                            }
                        } else {
                            continue;
                        }
                    } elseif ($subscriptionData['payment_module'] == 'wkwepay'
                        && WkProductSubscriptionGlobal::isWkWepayRecurringEnabled()
                    ) {
                        $paymentData = json_decode($subscriptionData['payment_response'], true);
                        $wepayObj = new WkSubscriptionWepay();
                        $wePaySubData = $wepayObj->getWepaySubscriptionDetailsByIdCustomer(
                            $subscriptionData['id_customer'],
                            $subscriptionData['first_order_id'],
                            $subscriptionData['id_product']
                        );

                        if ($wePaySubData) {
                            if ($wePaySubData['is_plan_cancel']
                                || (strtotime($wePaySubData['expiry_date']) < strtotime(date('H:i:s')))
                            ) {
                                $wepayObj->cancelWepaySubscription(
                                    (int) $subscriptionData['id_customer'],
                                    $paymentData['id']
                                );
                                continue;
                            }
                        } else {
                            continue;
                        }
                    } elseif ($subscriptionData['payment_module'] == 'wkpaypalsubscription'
                        && WkProductSubscriptionGlobal::isWkPayPalRecurringEnabled()
                    ) {
                        $objPayPal = new WkSubscriptionPayPal();
                        $payPalSubsId = $subscriptionData['payment_response'];
                        if ($payPalSubsId) {
                            if (!$this->checkPayPalSubscriptionStatus($payPalSubsId)) {
                                $objPayPal->cancelSubscription(
                                    $payPalSubsId,
                                    $subscriptionData['first_order_id']
                                );
                                continue;
                            }
                        } else {
                            continue;
                        }
                    }

                    $idCart = $this->createSubscriberCart($subscriptionData);

                    if ($idCart) {
                        // Set shop context
                        Shop::setContext(
                            Shop::CONTEXT_SHOP,
                            (int) $subscriptionData['id_shop']
                        );
                        $objCart = new Cart((int) $idCart);
                        Context::getContext()->cart = $objCart;
                        $objOrder = new Order($subscriptionData['first_order_id']);
                        if (Validate::isLoadedObject($objOrder)) {
                            $cartRules = $objOrder->getCartRules();
                            if ($cartRules) {
                                foreach ($cartRules as $cartRule) {
                                    $objCartRule = new CartRule($cartRule['id_cart_rule']);
                                    if (Validate::isLoadedObject($objCartRule)) {
                                        $cartRuleName = [];
                                        foreach (Language::getLanguages(false) as $lang) {
                                            $cartRuleName[$lang['id_lang']] = sprintf(
                                                $this->module->l('Subscription discount (%s%%)', 'cron'),
                                                $objCartRule->reduction_percent
                                            );
                                        }
                                        $objCartRuleNew = new CartRule();
                                        $objCartRuleNew->name = $cartRuleName;
                                        $objCartRuleNew->id_customer = (int) $subscriptionData['id_customer'];
                                        $objCartRuleNew->reduction_percent = $objCartRule->reduction_percent;
                                        $objCartRuleNew->date_from = date('Y-m-d');
                                        $objCartRuleNew->date_to = date('Y-m-d H:i:s', time() + 24 * 36000);
                                        $objCartRuleNew->description = $this->module->l('Subscription discount cron', 'cron');
                                        $objCartRuleNew->quantity = 1;
                                        $objCartRuleNew->quantity_per_user = 1;
                                        $objCartRuleNew->priority = 1;
                                        $objCartRuleNew->partial_use = 0;
                                        $objCartRuleNew->code = '';
                                        $objCartRuleNew->minimum_amount = 0;
                                        $objCartRuleNew->product_restriction = 1;
                                        $objCartRuleNew->shop_restriction = Shop::isFeatureActive() ? 1 : 0;
                                        $objCartRuleNew->reduction_product = (int) $subscriptionData['id_product'];
                                        $objCartRuleNew->active = 1;
                                        $objCartRuleNew->save();
                                        if ($objCartRuleNew->id) {
                                            if (Shop::isFeatureActive()) {
                                                Db::getInstance()->insert('cart_rule_shop', [
                                                    'id_cart_rule' => (int) $objCartRuleNew->id,
                                                    'id_shop' => (int) $subscriptionData['id_shop'],
                                                ]);
                                            }
                                            $objCart->addCartRule($objCartRuleNew->id);
                                        }
                                        unset($objCartRuleNew);
                                        unset($objCartRule);
                                    }
                                }
                            }
                        }

                        $idOrder = $this->createSubscriptionOrder(
                            $subscriptionData,
                            (int) $idCart
                        );
                        // Save subscription order details
                        if ($idOrder) {
                            $id_subscription = (int) $subscriptionData['id_subscription'];
                            $subObj = new WkSubscriberOrderModel();
                            $subObj->id_order = (int) $idOrder;
                            $subObj->id_cart = (int) $idCart;
                            $subObj->id_shop = (int) $subscriptionData['id_shop'];
                            $subObj->id_shop_group = (int) Shop::getContextShopGroupID();
                            $subObj->id_subscription = (int) $id_subscription;
                            $idSchedule = (int) $subscriptionData['schedule']['id_wk_subscription_schedule'];
                            $subObj->id_schedule = (int) $idSchedule;
                            if ($subObj->save()) {
                                ++$total_order_create;
                                // Update status in order schedule table
                                $scheudleObj = new WkSubscriberScheduleModel((int) $idSchedule);
                                $scheudleObj->is_order_created = 1;
                                $scheudleObj->save();
                            }
                        }
                    }
                }
            }
            echo 'Total Order Scheduled for Tomorrow: ' . $total_order_scheduled;
            echo '<br>';
            echo 'Total Order Created: ' . $total_order_create;
            $file = fopen(dirname(__FILE__) . '/../../cron_logs.log', 'a');
            $msg = "\r\n\n";
            $msg .= '[' . date('d-m-Y H:i:s') . ']  ----  Cron run successfully run on ' . $this->todayDateTime . "\n";
            $msg .= '---------------- Total Order Scheduled for Tomorrow: ' . $total_order_scheduled . "\n";
            $msg .= '---------------- Total Order Created: ' . $total_order_create . "\n";
            $msg .= '---------------- Shop: ' . $this->context->shop->name . ' (ID: ' . $this->context->shop->id . ')';
            fwrite($file, $msg);
            fclose($file);
            exit;
        } else {
            exit("Webkul's Product Subscription module not enabled.");
        }
    }

    /**
     * Check if PayPal subscription is active
     *
     * @param string $idSubscription
     *
     * @return bool
     */
    private function checkPayPalSubscriptionStatus($idSubscription)
    {
        $subsData = WkPaypalSubscriber::getSubscriptionDetails($idSubscription);
        if ($subsData['success'] && $subsData['data']) {
            if ($subsData['data']->status == 'ACTIVE') {
                return true;
            }
        }

        return false;
    }

    /**
     * checkStripeSubscriptionStatus
     *
     * @param string $subscription_id Stripe Subscription ID
     *
     * @return bool
     */
    private function checkStripeSubscriptionStatus($subscription_id)
    {
        $stripePublishableKey = WkStripeApiService::getSecretKey();
        WkStripeApiService::setApiKey($stripePublishableKey);
        try {
            $sub = WkStripeApiService::retrieveSubscription($subscription_id);
            if ($sub->id && (Tools::strtolower($sub->status) == 'active')) {
                return true;
            }
        } catch (\Stripe\Error\InvalidRequest $e) {
            $e->getJsonBody();

            return false;
        }

        return false;
    }

    /**
     * Get scheduled order for today (prior to delivery days)
     *
     * @return array
     */
    private function getTodayScheduledOrders()
    {
        $objGlobal = new WkProductSubscriptionGlobal();

        return $objGlobal->getTodayScheduledOrders($this->todayDate);
    }

    private function getTomrowScheduledOrders()
    {
        $objGlobal = new WkProductSubscriptionGlobal();

        return $objGlobal->getTomrowScheduledOrders();
    }

    /**
     * Create subscription cart using subscriber subscription data
     *
     * @param mixed $subscriptionData
     *
     * @return void
     */
    private function createSubscriberCart($subscriptionData)
    {
        $id_lang = (int) $subscriptionData['id_lang'];
        if ($subscriptionData) {
            $option = [$subscriptionData['id_address_delivery'] => $subscriptionData['id_carrier'] . ','];
            // Creating ps_cart table
            $obj_cart = new Cart();
            $customer = new Customer($subscriptionData['id_customer']);

            $obj_cart->id_shop = (int) $subscriptionData['id_shop'];
            $shopObj = new Shop((int) $subscriptionData['id_shop']);
            $obj_cart->id_shop_group = (int) $shopObj->id_shop_group;

            $obj_cart->id_carrier = (int) $subscriptionData['id_carrier'];
            $obj_cart->delivery_option = json_encode($option);
            $obj_cart->id_lang = (int) $id_lang;
            $obj_cart->id_address_delivery = (int) $subscriptionData['id_address_delivery'];
            $obj_cart->id_address_invoice = (int) $subscriptionData['id_address_invoice'];
            $obj_cart->id_currency = (int) $subscriptionData['id_currency'];
            $obj_cart->id_customer = (int) $subscriptionData['id_customer'];
            $obj_cart->secure_key = $customer->secure_key;
            $obj_cart->recyclable = 0;
            $obj_cart->save();
            $id_cart = (int) $obj_cart->id;

            // Add code for customization products
            $idCustomization = 0;
            if ($subscriptionData['id_customization']) {
                $objCustomization = new Customization((int) $subscriptionData['id_customization']);
                if (Validate::isLoadedObject($objCustomization)) {
                    $newCustObj = $objCustomization->duplicateObject();
                    $idCustomization = (int) $newCustObj->id;
                    $newCustObj->id_cart = (int) $id_cart;
                    $newCustObj->save();

                    // Add customization data
                    $objGlobal = new WkProductSubscriptionGlobal();
                    $objGlobal->addCustomizationData(
                        $subscriptionData['id_customization'],
                        $idCustomization
                    );
                    unset($objGlobal);
                }
            }

            // Creating ps_cart_product table
            if ($id_cart) {
                Db::getInstance()->insert(
                    'cart_product',
                    [
                        'id_cart' => (int) $id_cart,
                        'id_product' => (int) $subscriptionData['id_product'],
                        'id_address_delivery' => (int) $subscriptionData['id_address_delivery'],
                        'id_shop' => (int) Context::getContext()->shop->id,
                        'id_product_attribute' => $subscriptionData['id_product_attribute'],
                        'id_customization' => $idCustomization,
                        'quantity' => (int) $subscriptionData['quantity'],
                        'date_add' => $this->todayDateTime,
                    ]
                );

                return $id_cart;
            } else {
                return false;
            }
        }
    }

    /**
     * Create subscription order
     *
     * @param array $subscriptionData Subscription Data
     * @param int $id_cart Cart ID
     *
     * @return array Return order data
     */
    private function createSubscriptionOrder($subscriptionData, $id_cart)
    {
        $module_name = $subscriptionData['payment_module'];

        if (Validate::isModuleName($module_name)) {
            $payment_module = Module::getInstanceByName($module_name);

            if (!Configuration::get('PS_CATALOG_MODE')) {
                $payment_module = Module::getInstanceByName($module_name);
            } else {
                if (class_exists('BoOrder')) {
                    $payment_module = new BoOrder();
                } else {
                    $payment_module = new BoOrderCore();
                }
            }

            $cart = new Cart((int) $id_cart);

            if ((float) $cart->getOrderTotal(true, Cart::BOTH) != (float) $subscriptionData['raw_total_amount']) {
                $orderObj = new Order((int) $subscriptionData['first_order_id']);
                $this->createSpcificProductPrice(
                    $id_cart,
                    $subscriptionData['id_product'],
                    $subscriptionData['id_product_attribute'],
                    $subscriptionData['raw_base_price'] + $orderObj->total_shipping_tax_excl
                );
                $this->updateFreeShipping($id_cart);
            }

            Context::getContext()->currency = new Currency((int) $cart->id_currency);
            Context::getContext()->customer = new Customer((int) $cart->id_customer);
            $address = new Address($cart->id_address_delivery);
            Context::getContext()->country = new Country((int) $address->id_country);
            Context::getContext()->cart = $cart;

            if (($module_name == 'ps_wirepayment')
                || ($module_name == 'wirepayment')
            ) {
                $current_state = (int) Configuration::get('PS_OS_BANKWIRE');
            } elseif (($module_name == 'ps_checkpayment')
                || ($module_name == 'checkpayment')
            ) {
                $current_state = (int) Configuration::get('PS_OS_CHEQUE');
            } elseif (($module_name == 'ps_cashondelivery')
                || ($module_name == 'cashondelivery')
            ) {
                $current_state = (int) Configuration::get('PS_OS_COD_VALIDATION');
            } else {
                $current_state = (int) Configuration::get('ALTAPAY_OS_PENDING');
            }

            $payment_module->validateOrder(
                (int) $cart->id,
                (int) $current_state,
                $cart->getOrderTotal(true, Cart::BOTH),
                $payment_module->displayName,
                $this->module->l('Subscription order -- CRON:', 'cron'),
                [],
                null,
                false,
                $cart->secure_key
            );

            if ($payment_module->currentOrder) {
                chargeAltaPayAgreement($payment_module->currentOrder, $subscriptionData['first_order_id']);

                return $payment_module->currentOrder;
            } else {
                return false;
            }
        }
    }

    // If subscription product price changed then create specific price
    public function createSpcificProductPrice($idCart, $idProduct, $idProductAttr, $price)
    {
        SpecificPrice::deleteByIdCart((int) $idCart, (int) $idProduct, (int) $idProductAttr);
        $objCart = new Cart((int) $idCart);
        if (Validate::isLoadedObject($objCart)) {
            $specificPrice = new SpecificPrice();
            $specificPrice->id_cart = (int) $idCart;
            $specificPrice->id_shop = 0;
            $specificPrice->id_shop_group = 0;
            $specificPrice->id_currency = 0;
            $specificPrice->id_country = 0;
            $specificPrice->id_group = 0;
            $specificPrice->id_customer = (int) $objCart->id_customer;
            $specificPrice->id_product = (int) $idProduct;
            $specificPrice->id_product_attribute = (int) $idProductAttr;
            $specificPrice->price = (float) $price;
            $specificPrice->from_quantity = 1;
            $specificPrice->reduction = 0;
            $specificPrice->reduction_type = 'amount';
            $specificPrice->from = '0000-00-00 00:00:00';
            $specificPrice->to = '0000-00-00 00:00:00';
            $specificPrice->add();
        }
    }

    public function updateFreeShipping($idCart)
    {
        $objCart = new Cart((int) $idCart);
        $shippingLang = [Configuration::get('PS_LANG_DEFAULT') => $this->module->l('Discounts', 'cron')];
        if (Validate::isLoadedObject($objCart)) {
            $cart_rule = new CartRule();
            $cart_rule->code = CartRule::BO_ORDER_CODE_PREFIX . (int) $objCart->id;
            $cart_rule->name = $shippingLang;
            $cart_rule->id_customer = (int) $objCart->id_customer;
            $cart_rule->free_shipping = true;
            $cart_rule->quantity = 1;
            $cart_rule->quantity_per_user = 1;
            $cart_rule->minimum_amount_currency = (int) $objCart->id_currency;
            $cart_rule->reduction_currency = (int) $objCart->id_currency;
            $cart_rule->date_from = date('Y-m-d H:i:s', time());
            $cart_rule->date_to = date('Y-m-d H:i:s', time() + 24 * 36000);
            $cart_rule->active = 1;
            $cart_rule->add();
            $objCart->addCartRule((int) $cart_rule->id);
        }
    }
}
