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
$user = new User($db, $crypt, $syslog);
$jwt = new JWT($jwtKeyFile);

$json = file_get_contents('php://input');
$data = json_decode($json);
$check_jwt = $jwt->check_jwt();

header('Content-Type: application/json; charset=utf-8');

if ($check_jwt) {
  $jwt_payload = $jwt->payload();
  $user_id = $data->user_id;
  $new_username = $data->username;
  $realname = $data->realname;
  $displayname = $data->displayname;
  $about_me = $data->about_me;
  $email = $data->email;
  $userlevel = $data->userlevel;
  $position = $data->position;

  $updated_user = $user->editUserData($user_id, $realname, $displayname, $new_username, $email, $about_me, $position, $userlevel, $jwt_payload->user_id);
  
  if ($updated_user['error_code'] == 1) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error updating user']);
  } else if ($updated_user['success']){
    http_response_code(201);
    echo json_encode(['success' => true]);
  }

} else {
  http_response_code(401);
  echo json_encode(['success' => false]);
}

?>
