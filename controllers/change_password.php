<?php

require_once ('../base_config.php');
require_once ('../error_msg.php');
require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$jwt = new JWT($jwtKeyFile, $db, $crypt, $syslog);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $json = file_get_contents('php://input');
  $payload = $jwt->payload();
  $user_id = $payload->user_id;
  $temp_pw = $payload->temp_pw;
  $input = json_decode($json, true);
  $password = $input['password'];
  $new_password = $input['new_password'];

  $stmt = $db->query('SELECT id, hash_id, roles, username, pw, temp_pw, userlevel FROM ' . $db->au_users_basedata . ' WHERE id = :user_id');
  try {
    $db->bind(':user_id', $user_id); // blind index
    $users = $db->resultSet();
  } catch (Exception $e) {
    print_r($e);
  }

  if (!$temp_pw) {
    $pw = $users[0]['pw'];
    $check_password = password_verify($password, $pw);
  } else {
    $check_password = $users[0]['temp_pw'] == $password; 
    if ($check_password) {
      $stmt = $db->query('UPDATE '. $db->au_users_basedata . ' SET temp_pw = "" WHERE id = :user_id');
      $db->bind(':user_id', $user_id); // blind index
      $remove_temp_pw = $db->resultSet();
    }
  }

  if ($check_password) {
    $user = new User ($db, $crypt, $syslog);
    $user->setUserPW($user_id, $new_password);
    if (!$temp_pw) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["success" => true]);
    } else {
      header('Content-Type: application/json; charset=utf-8');
      $users[0]["temp_pw"] = false;
      $jwt_token = $jwt->gen_jwt($users[0]);
      echo json_encode(['JWT' => $jwt_token, "success" => true]);
    }
  } else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false]);
  }
}

?>
