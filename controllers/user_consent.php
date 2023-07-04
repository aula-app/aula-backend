<?php

require_once ('../base_config.php'); // load base config with paths to classes etc.
require_once ('../error_msg.php');
require ('../functions.php'); // include Class autoloader (models)
require ($baseHelperDir.'JWT.php');
require_once ($baseHelperDir.'Crypt.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$user = new User ($db, $crypt, $syslog); 
$jwt = new JWT($jwtKeyFile);

$check_jwt = $jwt->check_jwt();

if ($check_jwt) {
  $payload = $jwt->payload();
  $givenconsents = $user->checkHasUserGivenConsentsForUsage ($payload->user_id);
  echo json_encode($givenconsents);
}

?>
