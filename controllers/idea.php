<?php

require_once ('../base_config.php'); // load base config with paths to classes etc.
require_once ('../error_msg.php');
require ('../functions.php'); // include Class autoloader (models)
require ($baseHelperDir.'JWT.php');
require_once ($baseHelperDir.'Crypt.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$idea = new Idea ($db, $crypt, $syslog);

$json = file_get_contents('php://input');
$input_data = json_decode($json);

$jwt = new JWT($jwtKeyFile);
$check_jwt = $jwt->check_jwt();

if ($check_jwt) {
  $ideaData = $idea->getIdeaContent($input_data->idea_id)["data"];

  $ideaData['content'] = $crypt->decrypt ($ideaData['content']);
  $ideaData['displayname'] = $crypt->decrypt ($ideaData['displayname']);

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($ideaData);
} else {
  echo $check_jwt;
}

?>