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
        $terminal = new Altapay_Models_Terminal($i);
        if ($term->Country == 'DK') {
            $terminal->display_name = $term->Title;
            $terminal->remote_name = $term->Title;
            $terminal->icon_filename = ' ';
            $terminal->currency = $currency;
            $terminal->ccTokenControl_ = 0;
            $terminal->isapplepay_ = 0;
            $terminal->payment_type = 'payment';
            $terminal->active = 1;
            $terminal->position = $i++;
            $terminal->cvvLess = 0;
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
