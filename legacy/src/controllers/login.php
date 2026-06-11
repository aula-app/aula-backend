<?php

require_once(__DIR__ . '/../../config/base_config.php');
global $baseHelperDir;
require_once($baseHelperDir . 'InstanceConfig.php');
if (($instance = InstanceConfig::createFromRequestOrEchoBadRequest()) === null) {
  return;
}

require('../functions.php'); // include Class autoloader (models)
require_once($baseHelperDir . 'Crypt.php');
require_once($baseHelperDir . 'JWT.php');

$db = new Database($instance);
$crypt = new Crypt();
$syslog = new Systemlog($db); // systemlog
$user = new User($db, $crypt, $syslog);
$settings = new Settings($db, $crypt, $syslog);

$jwt = new JWT($instance->jwt_key, $db, $crypt, $syslog);


header('Content-Type: application/json; charset=utf-8');

// SSO-only tenants reject password login outright, regardless of which user is
// attempting it. The check happens before any DB work so unauthenticated
// requests cannot probe the user table on SSO-locked tenants.
if ($instance->sso_required) {
  echo json_encode([
    'success'    => false,
    'error_code' => 3,
    'error'      => 'tenant_requires_sso',
  ]);
  return;
}

$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json);


$loginResult = $user->checkLogin($data->username, $data->password);
if ($loginResult["error_code"] == 2) {
  echo json_encode($loginResult);
  return;
}

if ($loginResult["success"] && $loginResult["error_code"] == 0) {
  // Refuse password login for SSO-linked users — local password is bypass surface
  // for an identity that lives in the IdP. Mirrors the Laravel LegacyLoginController
  // check; this controller is the one the React frontend actually hits.
  $user_id = $loginResult["data"]["id"] ?? null;
  if ($user_id !== null) {
    $stmt = $db->query('SELECT sso_sub FROM ' . $db->au_users_basedata . ' WHERE id = :id');
    $db->bind(':id', $user_id);
    $row = $db->resultSet();
    if (!empty($row[0]['sso_sub'])) {
      echo json_encode([
        'success'    => false,
        'error_code' => 3,
        'error'      => 'use_sso',
      ]);
      return;
    }
  }

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
