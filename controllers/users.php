<?php

require_once ('../base_config.php'); // load base config with paths to classes etc.
require_once ('../error_msg.php');
require ('../functions.php'); // include Class autoloader (models)
require ($baseHelperDir.'JWT.php');
require_once ($baseHelperDir.'Crypt.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$user = new User ($db, $crypt, $syslog); 
$jwt = new JWT($jwtKeyFile);
$json = file_get_contents('php://input');
$request_data = json_decode($json);

$check_jwt = $jwt->check_jwt();

if ($check_jwt) {
  $offset = $request_data->offset; 
  $limit = $request_data->limit;
  if (!$offset) {
    $offset = 0;
  }
  if (!$limit) {
    $limit = 0;
  }
  $userData = $user->getUsers($offset, $limit, 4, 1, 1);
  $i = 0;
  $newData = array();
  foreach ($userData['data'] as $user) {
    $user["realname"] = $crypt->decrypt ($user['realname']);
    $user["username"] = $crypt->decrypt ($user['username']);
    $user["displayname"] = $crypt->decrypt ($user['displayname']);
    $user["email"] = $crypt->decrypt ($user['email']);
  
    array_push($newData, $user);
    $i = $i + 1;
  }
  $userData["data"] = $newData;
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($userData);
}

?>
