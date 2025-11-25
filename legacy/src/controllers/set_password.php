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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $secret = $_GET["secret"];

  $stmt = $db->query('SELECT user_id FROM au_change_password WHERE secret = :secret');
  $db->bind(':secret', $secret);

  if (count($db->resultSet()) > 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => true]);
  };
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $json = file_get_contents('php://input');
  $input = json_decode($json, true);

  $secret =  $input["secret"];
  $stmt = $db->query('SELECT user_id FROM au_change_password WHERE secret = :secret');
  $db->bind(':secret', $secret);

  if (count($db->resultSet()) > 0) {
    $user_id = $db->resultSet()[0]["user_id"];
    $password = $input['password'];
    
    // Validate minimum password length
    $min_password_length = 12; // Configure minimum password length here
    if (strlen($password) < $min_password_length) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(["success" => false, "error" => "Password must be at least {$min_password_length} characters long"]);
      return;
    }
    
    $user = new User($db, $crypt, $syslog);
    $user->setUserPW($user_id, $password);
    // Delete secret from db
    $stmt = $db->query('DELETE FROM au_change_password WHERE secret = :secret');
    $db->bind(':secret', $secret);
    $user_id = $db->resultSet();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => true]);
  };
}
