<?php

require_once ('base_config.php');
require_once ('error_msg.php');
require ('functions.php');
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
  $inserted_user_id = $user->addUser('real_testuser', 'display_testuser', $new_username, 'admin.@aula.de', $new_password, 1);
  if (str_contains($inserted_user_id, '0,1')) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'User already exist']);
  } else {
    http_response_code(201);
    echo json_encode(['success' => true]);
  }

} else {
  http_response_code(401);
  echo json_encode(['success' => false]);
}



?>
