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
  $user_id = $jwt_payload->$user_id;

  $headline = $data->headline;
  $body = $data->body;
  $consent_text = $data->consent_text;
  $user_needs_to_consent = $data->user_needs_to_consent;

  $inserted_text = $text->addText($headline, $body, $consent_text, 0, $user_id, $user_needs_to_consent);
  
  if ($inserted_text['error_code'] == 1) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Error inserting Text']);
  } else if ($inserted_text['success']){
    http_response_code(201);
    echo json_encode(['success' => true, 'text_id' => $inserted_text['data']]);
  }

} else {
  http_response_code(401);
  echo json_encode(['success' => false]);
}



?>
