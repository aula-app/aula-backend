<?php

require_once (__DIR__ . '/../../config/base_config.php'); // load base config with paths to classes etc.
require_once ('../error_msg.php');
require ('../functions.php'); // include Class autoloader (models)
require ($baseHelperDir.'JWT.php');
require_once ($baseHelperDir.'Crypt.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$user = new User ($db, $crypt, $syslog); 
$jwt = new JWT($jwtKeyFile, $db, $crypt, $syslog);

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

?>
