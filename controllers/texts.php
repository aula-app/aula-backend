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
$text = new Text ($db, $crypt, $syslog); 
$check_jwt = $jwt->check_jwt();

if ($check_jwt) {
  $offset = $request_data->offset; 
  $limit = $request_data->limit;
  $sort_field = $request_data->sort_field;
  $sort_order = $request_data->sort;
  $sort_param = 3;
  $sort_order_param = 0;

  if (!$offset) {
    $offset = 0;
  }
  if (!$limit) {
    $limit = 0;
  }
  switch ($sort_field) {
  default:
    $sort_param = 3;
    break;
  }

  if ($sort_order == 'asc') {
    $sort_order_param = 1;
  } else {
    $sort_order_param = 0;
  } 

  $messagesData = $text->getTexts ($offset, $limit, 4, 1, 1);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($messagesData);
}

?>
