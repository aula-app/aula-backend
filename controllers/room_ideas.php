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

$jwt = new JWT($jwtKeyFile);


$json = file_get_contents('php://input');
$input_data = json_decode($json);

$check_jwt = $jwt->check_jwt();

if ($check_jwt) {
  $ideaData = $idea->getIdeas(0, 0, 3, 1, 1, '', $input_data->room_id);
  // getIdeas ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="", $room_id=0)
  $i = 0;
  $newData = array();
  foreach ($ideaData['data'] as $idea) {
    $idea['content'] = $crypt->decrypt ($idea['content']);
    array_push($newData, $idea);
    $i = $i + 1;
  }
  $ideaData["data"] = $newData;
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($ideaData);
} else {
  echo $check_jwt;
}
?>
