<?php

require_once (__DIR__ . '/../../config/base_config.php'); // load base config with paths to classes etc.
global $baseHelperDir;
require_once($baseHelperDir . 'InstanceConfig.php');
if (($instance = InstanceConfig::createFromRequestOrEchoBadRequest()) === null) {
  return;
}

require ('../functions.php'); // include Class autoloader (models)
require_once ($baseHelperDir.'JWT.php');
require_once ($baseHelperDir.'Crypt.php');

$db = new Database($instance);
$crypt = new Crypt();
$syslog = new Systemlog ($db);
$user = new User ($db, $crypt, $syslog); 
$jwt = new JWT($instance['jwt_key'], $db, $crypt, $syslog);

$check_jwt = $jwt->check_jwt();
if ($check_jwt) {
  $payload = $jwt->payload();
  $givenconsents = $user->checkHasUserGivenConsentsForUsage ($payload->user_id);
  echo json_encode($givenconsents);
}
