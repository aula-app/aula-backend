<?php

require_once(__DIR__ . '/../../config/base_config.php');
global $baseHelperDir;
require_once($baseHelperDir . 'InstanceConfig.php');
if (($instance = InstanceConfig::createFromRequestOrEchoBadRequest()) === null) {
  return;
}

require('../functions.php'); // include Class autoloader (models)
require($baseHelperDir . 'JWT.php');
require_once($baseHelperDir . 'Crypt.php');

$db = new Database($instance);
$crypt = new Crypt();
$syslog = new Systemlog($db);
$user = new User($db, $crypt, $syslog);
$jwt = new JWT($instance->jwt_key, $db, $crypt, $syslog);

$check_jwt = $jwt->check_jwt();
$json = file_get_contents('php://input');
$data = json_decode($json);

if ($check_jwt) {
  $text_id = $data->text_id;
  $payload = $jwt->payload();
  $user_id = $payload->user_id;
  $give_consent = $user->giveConsent($user_id, $text_id);
  echo json_encode($give_consent);
}
