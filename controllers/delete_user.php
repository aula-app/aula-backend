<?php

require_once ('../base_config.php');
require_once ('../error_msg.php');
require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$user = new User($db, $crypt, $syslog);
$jwt = new JWT($jwtKeyFile);

$json = file_get_contents('php://input');
$data = json_decode($json);
$check_jwt = $jwt->check_jwt();

header('Content-Type: application/json; charset=utf-8');

if ($check_jwt) {
  $user_id = $data->user_id;
  $remove_user_status = $user->deleteUser($user_id);

  echo json_encode($remove_user_status);
}

?>
