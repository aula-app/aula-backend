<?php

require_once(__DIR__ . '/../../config/base_config.php');
global $baseHelperDir;
require_once($baseHelperDir . 'InstanceConfig.php');
if (($instance = InstanceConfig::createFromRequestOrEchoBadRequest()) === null) {
  return;
}

require('../functions.php');
require_once($baseHelperDir . 'Crypt.php');
require_once($baseHelperDir . 'JWT.php');

$db = new Database($instance);
$crypt = new Crypt();
$syslog = new Systemlog($db);
$jwt = new JWT($instance->jwt_key, $db, $crypt, $syslog);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $json = file_get_contents('php://input');
  $payload = $jwt->payload();
  $user_id = $payload->user_id;
  $temp_pw = $payload->temp_pw;
  $input = json_decode($json, true);
  $password = $input['password'];
  $new_password = $input['new_password'];

  // Validate minimum password length for new password
  $min_password_length = 12; // Configure minimum password length here
  if (strlen($new_password) < $min_password_length) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "error" => "Password must be at least {$min_password_length} characters long"]);
    return;
  }

  $stmt = $db->query('SELECT id, hash_id, roles, username, pw, temp_pw, userlevel FROM ' . $db->au_users_basedata . ' WHERE id = :user_id');
  try {
    $db->bind(':user_id', $user_id); // blind index
    $users = $db->resultSet();
  } catch (Exception $e) {
    error_log('Error occurred while changing password for user ' . $user_id . ': ' . $e->getMessage());
  }

  if (!$temp_pw) {
    $pw = $users[0]['pw'];
    $check_password = password_verify($password, $pw);
  } else {
    $check_password = hash_equals($users[0]['temp_pw'], $password);
    if ($check_password) {
      $stmt = $db->query('UPDATE ' . $db->au_users_basedata . ' SET temp_pw = "" WHERE id = :user_id');
      $db->bind(':user_id', $user_id); // blind index
      $remove_temp_pw = $db->resultSet();
    }
  }

  if ($check_password) {
    $user = new User($db, $crypt, $syslog);
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
