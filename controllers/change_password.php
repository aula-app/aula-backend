<?php

require_once ('../base_config.php');
require_once ('../error_msg.php');
require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$jwt = new JWT($jwtKeyFile);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $json = file_get_contents('php://input');
  $payload = $jwt->payload();
  $user_id = $payload->user_id;
  $input = json_decode($json, true);
  $password = $input['password'];
  $new_password = $input['new_password'];

  $stmt = $db->query('SELECT username, pw FROM ' . $db->au_users_basedata . ' WHERE id = :user_id');
  try {
    $db->bind(':user_id', $user_id); // blind index
    $users = $db->resultSet();
  } catch (Exception $e) {
    print_r($e);
  }

  $pw = $users[0]['pw'];
  $check_password = password_verify($password, $pw);

  if ($check_password) {
    $user = new User ($db, $crypt, $syslog);
    $user->setUserPW($user_id, $new_password);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => true]);
  } else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false]);
  }
}

?>
