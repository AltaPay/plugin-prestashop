<?php
/**
* 2010-2021 Webkul.
*
* NOTICE OF LICENSE
*
* All right is reserved,
* Please go through LICENSE.txt file inside our module
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to newer
* versions in the future. If you wish to customize this module for your
* needs please refer to CustomizationPolicy.txt file inside our module for more information.
*
* @author Webkul IN
* @copyright 2010-2021 Webkul IN
* @license LICENSE.txt
* @summary Updated by AltaPay for processing recurring payments. Instead of Webkul's cron controller, this should be used to create and schedule automatic subscription orders and processing recurring payments.
 */
class AltapayCronLegacyModuleFrontController extends ModuleFrontController
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
                        if (!$this->checkStripeSubscriptionStatus($stripeResponse['stripe_subscription_id'])) {
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
                    if ($subsData['payment_module'] == 'wkstripepayment'
                        && WkProductSubscriptionGlobal::isWkStripeRecurringEnabled()
                    ) {
                        $stripeResponse = json_decode($subscriptionData['payment_response'], true);
                        if (!$this->checkStripeSubscriptionStatus($stripeResponse['stripe_subscription_id'])) {
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
                                continue;
                            }
                        } else {
                            continue;
                        }
                    }

                    $id_cart = $this->createSubscriberCart($subscriptionData);

                    if ($id_cart) {
                        $id_order = $this->createSubscriptionOrder(
                            $subscriptionData,
                            (int) $id_cart
                        );
                        // Save subscription order details
                        if ($id_order) {
                            $id_subscription = (int) $subscriptionData['id_subscription'];
                            $subObj = new WkSubscriberOrderModel();
                            $subObj->id_order = (int) $id_order;
                            $subObj->id_cart = (int) $id_cart;
                            $subObj->id_subscription = (int) $id_subscription;
                            $id_schedule = (int) $subscriptionData['schedule']['id_schedule'];
                            $subObj->id_schedule = (int) $id_schedule;
                            if ($subObj->save()) {
                                ++$total_order_create;
                                // Update status in order schedule table
                                $scheudleObj = new WkSubscriberScheduleModel((int) $id_schedule);
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
            exit;
        } else {
            exit("Webkul's Product Subscription module not enabled.");
        }
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
            if ($sub->id && ($sub->status == 'active')) {
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
            //Creating ps_cart table
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

            //Creating ps_cart_product table
            if ($id_cart) {
                Db::getInstance()->insert(
                    'cart_product',
                    [
                        'id_cart' => (int) $id_cart,
                        'id_product' => (int) $subscriptionData['id_product'],
                        'id_address_delivery' => (int) $subscriptionData['id_address_delivery'],
                        'id_shop' => (int) Context::getContext()->shop->id,
                        'id_product_attribute' => $subscriptionData['id_product_attribute'],
                        // 'id_customization' => $subscriptionData['id_customization'],
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
                $payment_module = new BoOrder();
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
        $specific_price = new SpecificPrice();
        $specific_price->id_cart = (int) $idCart;
        $specific_price->id_shop = 0;
        $specific_price->id_shop_group = 0;
        $specific_price->id_currency = 0;
        $specific_price->id_country = 0;
        $specific_price->id_group = 0;
        $specific_price->id_customer = (int) $this->context->customer->id;
        $specific_price->id_product = (int) $idProduct;
        $specific_price->id_product_attribute = (int) $idProductAttr;
        $specific_price->price = (float) $price;
        $specific_price->from_quantity = 1;
        $specific_price->reduction = 0;
        $specific_price->reduction_type = 'amount';
        $specific_price->from = '0000-00-00 00:00:00';
        $specific_price->to = '0000-00-00 00:00:00';
        $specific_price->add();
    }

    public function updateFreeShipping($idCart)
    {
        $objCart = new Cart((int) $idCart);
        $shippingLang = [Configuration::get('PS_LANG_DEFAULT') => $this->trans(
            'Free Shipping',
            [],
            'Admin.Orderscustomers.Feature'
        )];
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
