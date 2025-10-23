<?php

require_once(__DIR__ . '/../../config/base_config.php');
global $baseHelperDir;
require_once($baseHelperDir . 'InstanceConfig.php');
if (($instance = InstanceConfig::createFromRequestOrEchoBadRequest()) === null) {
  return;
}

require('../functions.php');
require_once($baseHelperDir . 'Permissions.php');
require_once($baseHelperDir . 'Crypt.php');
require_once($baseHelperDir . 'JWT.php');

$db = new Database($instance);
$crypt = new Crypt();
$syslog = new Systemlog($db);
$jwt = new JWT($instance->jwt_key, $db, $crypt, $syslog);
$settings = new Settings($db, $crypt, $syslog);

$json = file_get_contents('php://input');
$input = json_decode($json, true);
$check_jwt = $jwt->check_jwt();

header('Content-Type: application/json; charset=utf-8');

if (!!$check_jwt && $check_jwt["success"]) {
  $jwt_payload = $jwt->payload();
  $user_id = $jwt_payload->user_id;
  $userlevel = $jwt_payload->user_level;
  $user_hash = $jwt_payload->user_hash;
  $roles = $jwt_payload->roles;

  $current_settings = $settings->getInstanceSettings();
  if ($current_settings["data"]["online_mode"] != 1 && $userlevel < 50) {
    echo json_encode([
      "success" => false,
      "online_mode" => $current_settings["data"]["online_mode"]
    ]);
    return;
  }

  if (array_key_exists("model", $input)) {
    $model_name = $input["model"];
  } else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'You need to provide a model attribute in the request']);
    return;
  }

  $method = $input["method"];

  if (array_key_exists("arguments", $input)) {
    $arguments = $input["arguments"];
  } else {
    $arguments = [];
  }

  if (array_key_exists("decrypt", $input)) {
    $decrypt_fields = $input["decrypt"];
  } else {
    $decrypt_fields = [];
  }

  $permissions = checkPermissions($db, $crypt, $syslog, $model_name, $method, $arguments, $user_id, $userlevel, $roles, $user_hash);

  if (!$permissions["allowed"]) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    return;
  }

  $model = new $model_name($db, $crypt, $syslog);
  $data = $model->$method(...$arguments);

  if ($data['error_code'] == 1) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Error fetching data for ' . $model_name, 'detail' => $data]);
    return;
  } else if ($data['success']) {
    http_response_code(200);

    if ($data['data']) {
      if (is_array($data['data']) && count($data['data']) > 0) {
        $newData = array();
        if (count($decrypt_fields) > 0) {
          if (!array_key_exists(0, $data['data'])) {
            $result = $data['data'];
            foreach ($decrypt_fields as $field) {
              $result[$field] = $crypt->decrypt($result[$field]);
            }
            echo json_encode(['success' => true, 'count' => $data['count'], 'data' => $result]);
            return;
          } else {
            foreach ($data['data'] as $item) {
              foreach ($decrypt_fields as $field) {

                $item[$field] = $crypt->decrypt($item[$field]);
              }
              array_push($newData, $item);
            }
            echo json_encode(['success' => true, 'count' => $data['count'], 'data' => $newData]);
            return;
          }
        }
      } else if (is_numeric($data['data'])) {
        echo json_encode(['success' => true, 'count' => $data['count'], 'error_code' => $data['error_code'], 'data' => $data['data']]);
        return;
      }
    }
    echo json_encode(['success' => true, 'count' => $data['count'], 'error_code' => $data['error_code'], 'data' => $data['data']]);
  } else {
    echo json_encode(['success' => true, 'count' => $data['count'], 'error_code' => $data['error_code'], 'data' => $data['data']]);
  }
} else {

  http_response_code(401);
  echo json_encode(['success' => false, 'error' => $check_jwt ? $check_jwt["error"] : 'JWT token is invalid']);
}
