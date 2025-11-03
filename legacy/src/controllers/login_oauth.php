<?php

require_once (__DIR__ . '/../../config/base_config.php'); // load base config with paths to classes etc.

require ('../functions.php'); // include Class autoloader (models)
require_once ($baseHelperDir . 'Crypt.php');
require_once ($baseHelperDir . 'JWT.php');

require '../vendor/autoload.php';

$auth0 = new \Auth0\SDK\Auth0([
    'domain' => $AUTH0_DOMAIN,
    'clientId' => $AUTH0_CLIENT_ID,
    'clientSecret' => $AUTH0_CLIENT_SECRET,
    'cookieSecret' => $AUTH0_COOKIE_SECRET,
    'redirectUri' => $AUTH0_REDIRECT_URI
]);

$auth0->clear();

$auth0->clear();

header("Location: " . $auth0->login($AUTH0_REDIRECT_URI));

?>
