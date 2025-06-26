<?php

require_once (__DIR__ . '/../../config/base_config.php'); // load base config with paths to classes etc.
require_once ('../error_msg.php');
require ('../functions.php'); // include Class autoloader (models)
require ($baseHelperDir.'JWT.php');
require_once ($baseHelperDir.'Crypt.php');
require_once('../db.php');

$headers = apache_request_headers();

$db = new Database($headers["code"]);
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$user = new User ($db, $crypt, $syslog);
$jwt = new JWT($databases[$code]['jwt_key'], $db, $crypt, $syslog);

$check_jwt = $jwt->check_jwt();

if ($check_jwt) {
  $payload = $jwt->payload();
  $user_id = $payload->user_id;
  $necessary_consents = $user->getMissingConsents($user_id);
  echo json_encode($necessary_consents);
}

?>
