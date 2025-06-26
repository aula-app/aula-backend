<?php

require_once(__DIR__ . '/../../config/base_config.php'); // load base config with paths to classes etc.
require_once('../error_msg.php');
require('../functions.php'); // include Class autoloader (models)
require_once($baseHelperDir . 'Crypt.php');
require_once($baseHelperDir . 'JWT.php');
require_once('../db.php');

$headers = apache_request_headers();

$db = new Database($headers["code"]);
$crypt = new Crypt($cryptFile); // path to $cryptFile is currently known from base_config.php -> will be changed later to be secure
$syslog = new Systemlog($db); // systemlog
$user = new User($db, $crypt, $syslog);
$settings = new Settings($db, $crypt, $syslog);

$jwt = new JWT($databases[$code]['jwt_key'], $db, $crypt, $syslog);


header('Content-Type: application/json; charset=utf-8');
$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json);


$loginResult = $user->checkLogin($data->username, $data->password);
if ($loginResult["error_code"] == 2) {
  echo json_encode($loginResult);
  return;
}

if ($loginResult["success"] && $loginResult["error_code"] == 0) {
  $current_settings = $settings->getInstanceSettings();
  if ($current_settings["data"]["online_mode"] != 1 && $loginResult["data"]["userlevel"] < 50) {
    echo json_encode([
      "success" => "false",
      "online_mode" => $current_settings["data"]["online_mode"]
    ]);
    return;
  }

  if (!empty($loginResult["data"]["temp_pw"]) && $loginResult["data"]["temp_pw"] != '') {
    $loginResult["data"]["temp_pw"] = true;
  } else {
    $loginResult["data"]["temp_pw"] = false;
  }

  $jwt_token = $jwt->gen_jwt($loginResult["data"]);
  echo json_encode(['JWT' => $jwt_token, "success" => true]);
} else {
  echo json_encode(["success" => false]);
}

?>
