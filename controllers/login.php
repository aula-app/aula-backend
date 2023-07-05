<?php

require_once ('../base_config.php'); // load base config with paths to classes etc.
require_once ('../error_msg.php');
require ('../functions.php'); // include Class autoloader (models)
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');

$db = new Database();
$crypt = new Crypt($cryptFile); // path to $cryptFile is currently known from base_config.php -> will be changed later to be secure
$syslog = new Systemlog ($db); // systemlog
$user = new User($db, $crypt, $syslog);

$jwt = new JWT($jwtKeyFile);


header('Content-Type: application/json; charset=utf-8');
$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json);

$loginResult = $user->checkLogin($data->username, $data->password);
if ($loginResult["success"]) {
  $jwt_token = $jwt->gen_jwt($loginResult["data"]);
  echo json_encode(['JWT' => $jwt_token, "success" => true]);
} else {
  echo json_encode(["success" => "false"]);
}

?>
