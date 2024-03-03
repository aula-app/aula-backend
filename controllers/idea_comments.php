<?php

require_once ('../base_config.php'); // load base config with paths to classes etc.
require_once ('../error_msg.php');
require ('../functions.php'); // include Class autoloader (models)
require ($baseHelperDir.'JWT.php');
require_once ($baseHelperDir.'Crypt.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$comment = new Comment ($db, $crypt, $syslog); 

$jwt = new JWT($jwtKeyFile);


$json = file_get_contents('php://input');
$input_data = json_decode($json);

$check_jwt = $jwt->check_jwt();

//    public function getCommentsByIdeaId ($idea_id, $offset=0, $limit=0, $orderby=3, $asc=0, $status=1) {


if ($check_jwt) {
  $commentsData = $comment->getCommentsByIdeaId($input_data->idea_id);
  $i = 0;
  $newData = array();
  foreach ($commentsData['data'] as $comments) {
    $comments['content'] = $crypt->decrypt ($comments['content']);
    array_push($newData, $comments);
    $i = $i + 1;
  }
  $commentsData["data"] = $newData;
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($commentsData);
} else {
  echo $check_jwt;
}
?>
