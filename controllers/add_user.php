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
  $new_username = $data->username;
  $new_password = $data->password;
  $realname = $data->realname;
  $displayname = $data->displayname;
  $email = $data->email;
  $inserted_user = $user->addUser($realname, $displayname, $new_username, $email, $new_password, 1);
  if ($inserted_user['error_code'] == 2) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'User already exist']);
  } else if ($inserted_user['success']){
    http_response_code(201);
    echo json_encode(['success' => true]);
  }

} else {
  http_response_code(401);
  echo json_encode(['success' => false]);
}



?>
