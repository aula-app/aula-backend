<?php

require_once ('../base_config.php');
require_once ('../error_msg.php');
require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$group = new Group ($db, $crypt, $syslog);
$room = new Room ($db, $crypt, $syslog);
$text = new Text($db, $crypt, $syslog);
$jwt = new JWT($jwtKeyFile);

$json = file_get_contents('php://input');
$data = json_decode($json);
$check_jwt = $jwt->check_jwt();

header('Content-Type: application/json; charset=utf-8');

if ($check_jwt) {
  $jwt_payload = $jwt->payload();
  $user_id = $jwt_payload->user_id;

  $text_id = $data->text_id;
  $headline = $data->headline;
  $body = $data->body;
  $consent_text = $data->consent_text;
  $user_needs_to_consent = $data->user_needs_to_consent;

  $text_update_1 = $text->setTextNeedsConsent($text_id, $user_needs_to_consent, $updater_id = $user_id);
  $text_update_2 = $text->setTextContent($text_id, $headline, $body, $consent_text, $updater_id = $user_id);

  http_response_code(201);
  echo json_encode(['success' => true]);
}

?>
