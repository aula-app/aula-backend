<?php

require_once ('../base_config.php');
require_once ('../error_msg.php');
require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$text = new Text($db, $crypt, $syslog);
$jwt = new JWT($jwtKeyFile);

$json = file_get_contents('php://input');
$data = json_decode($json);
$check_jwt = $jwt->check_jwt();

header('Content-Type: application/json; charset=utf-8');

if ($check_jwt) {
  $jwt_payload = $jwt->payload();
  $text_id = $data->text_id;
  $delete_text_status = $text->deleteText($text_id);

  echo json_encode($delete_text_status);
}

?>
