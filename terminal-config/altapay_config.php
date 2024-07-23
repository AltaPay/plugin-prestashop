<?php

require_once __DIR__ . '/modules/altapay/vendor/autoload.php';
require dirname(__FILE__) . '/config/config.inc.php';
require dirname(__FILE__) . '/init.php';

// Settings
$apiUser = '~gatewayusername~';
$apiPass = '~gatewaypass~';
$url = '~gatewayurl~';

Configuration::updateValue('ALTAPAY_USERNAME', $apiUser);
Configuration::updateValue('ALTAPAY_PASSWORD', $apiPass);
Configuration::updateValue('ALTAPAY_URL', $url);
Configuration::updateValue('PS_GUEST_CHECKOUT_ENABLED', 1);

try {
    $api = new API\PHP\Altapay\Api\Test\TestAuthentication(getAuth());
    $response = $api->call();
    if (!$response) {
        echo 'API credentials are incorrect';
        exit();
    }
} catch (API\PHP\Altapay\Exceptions\ClientException $e) {
    echo 'Error:' . $e->getMessage();
    exit();
} catch (\Exception $e) {
    echo 'Error:' . $e->getMessage();
    exit();
}

$currency = 'DKK';

try {
    $api = new API\PHP\Altapay\Api\Others\Terminals(getAuth());
    $response = $api->call();
    $i = 1;
    foreach ($response->Terminals as $term) {
        $terminal = new Altapay_Models_Terminal();
        if ($term->Country == 'DK') {
            $terminal->display_name = $term->Title;
            $terminal->remote_name = $term->Title;
            $terminal->icon_filename = getPaymentMethodIcon($term->PrimaryMethod->Identifier ?? '');
            $terminal->currency = $currency;
            $terminal->ccTokenControl_ = 0;
            $terminal->applepay = 0;
            $terminal->payment_type = 'payment';
            $terminal->active = 1;
            $terminal->position = $i++;
            $terminal->cvvLess = 0;
            $terminal->shop_id = 1;
            $terminal->nature = json_encode($term->Natures);
            $terminal->identifier = $term->PrimaryMethod->Identifier ?? '';
            $terminal->save();
        }
    }
} catch (API\PHP\Altapay\Exceptions\ClientException $e) {
    echo 'Error:' . $e->getMessage();
    exit();
} catch (\Exception $e) {
    echo 'Error:' . $e->getMessage();
    exit();
}

echo 'Settings are imported successfully';

/**
 * Get Terminal logo based on payment method identifier
 *
 * @param string $identifier
 *
 * @return string
 */
function getPaymentMethodIcon($identifier = '')
{
    $defaultValue = '';

    $paymentMethodIcons = [
        'ApplePay' => 'apple_pay.png',
        'Bancontact' => 'bancontact.png',
        'BankPayment' => 'bank.png',
        'CreditCard' => 'creditcard.png',
        'iDeal' => 'ideal.png',
        'Invoice' => 'invoice.png',
        'Klarna' => 'klarna_pink.png',
        'MobilePay' => 'mobilepay.png',
        'OpenBanking' => 'bank.png',
        'Payconiq' => 'payconiq.png',
        'PayPal' => 'paypal.png',
        'Przelewy24' => 'przelewy24.png',
        'Sepa' => 'sepa.png',
        'SwishSweden' => 'swish.png',
        'Trustly' => 'trustly_primary.png',
        'Twint' => 'twint.png',
        'ViaBill' => 'viabill.png',
        'Vipps' => 'vipps.png',
    ];

    if (isset($paymentMethodIcons[$identifier])) {
        return $paymentMethodIcons[$identifier];
    }

    return $defaultValue;
}
