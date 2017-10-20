<?php

require_once '../../../../../globalinclude.php';

$authKey = filter_input(INPUT_POST, 'auth_key');
$accessToken = filter_input(INPUT_POST, 'access_token');
$refreshToken = filter_input(INPUT_POST, 'refresh_token');
$companyId = filter_input(INPUT_POST, 'company_id');
$orderTracking = filter_input(INPUT_POST, 'order_tracking');

if (!empty($authKey)) {
    saveConfig('authkey', $authKey);
}

if (!empty($accessToken)) {
    saveConfig('accesstoken', $accessToken);
}

if (!empty($refreshToken)) {
    saveConfig('refreshtoken', $refreshToken);
}

if (!empty($companyId)) {
    saveConfig('companyid', $companyId);
}

if (!empty($orderTracking)) {
    saveConfig('ordertracking', $orderTracking);
}

function saveConfig($name, $value)
{
    $configExist = $GLOBALS['DB']->executeQuery("SHOW COLUMNS FROM `xplugin_newsletter2go_keys` LIKE '$name''", 1);

    if (!$configExist) {
        $GLOBALS['DB']->executeQuery("ALTER TABLE `xplugin_newsletter2go_keys` ADD COLUMN $name TEXT", 1);
    }

    $GLOBALS['DB']->executeQuery("UPDATE `xplugin_newsletter2go_keys` SET $name = '$value'", 1);
}