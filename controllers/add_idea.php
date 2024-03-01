<?php

require_once ('../base_config.php');
require_once ('../error_msg.php');
require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$idea = new Idea($db, $crypt, $syslog);
$jwt = new JWT($jwtKeyFile);

$json = file_get_contents('php://input');
$data = json_decode($json);
$check_jwt = $jwt->check_jwt();

header('Content-Type: application/json; charset=utf-8');

if ($check_jwt) {
  $jwt_payload = $jwt->payload();
  $user_id = $jwt_payload->user_id;
  $content = $data->content;
  $room_id = $data->room_id;
  $inserted_idea = $idea->addIdea ($content, $user_id, 1, $room_id=$room_id);
  
  if ($inserted_idea['error_code'] == 1) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Error inserting Idea', 'detail' => $inserted_idea]);
  } else if ($inserted_idea['success']){
    http_response_code(201);
    echo json_encode(['success' => true, 'idea_id' => $inserted_idea['data']]);
  }

} else {
  http_response_code(401);
  echo json_encode(['success' => false]);
}


?>
