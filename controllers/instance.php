<?php

require_once('../base_config.php'); // load base config with paths to classes etc.
require_once('../error_msg.php');
require_once(__DIR__ . '/../config/instances_config.php');
require('../functions.php'); // include Class autoloader (models)
require_once($baseHelperDir . 'Crypt.php');
require_once($baseHelperDir . 'JWT.php');

header('Content-Type: application/json; charset=utf-8');

$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json);

if (array_key_exists($data->code, $instances)) {
  echo json_encode(["status" => true]);
} else {
  echo json_encode(["status" => false]);
}
?>
