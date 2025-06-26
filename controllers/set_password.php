<?php

require_once ('../base_config.php');
require_once ('../error_msg.php');
require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');
require_once('../config/instances_config.php');

$db = new Database($headers['aula-instance-code']);
$code = $headers['aula-instance-code'];
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$jwt = new JWT($instances[$code]['jwt_key'], $db, $crypt, $syslog);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $secret =  $_GET["secret"];
  $stmt = $db->query('SELECT user_id FROM au_change_password WHERE secret = :secret');
  $db->bind(':secret', $secret); 
  
  if (count($db->resultSet()) > 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => true]);
  };
} else if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $json = file_get_contents('php://input');
  $input = json_decode($json, true);

  $secret =  $input["secret"];
  $stmt = $db->query('SELECT user_id FROM au_change_password WHERE secret = :secret');
  $db->bind(':secret', $secret); 
  
  if (count($db->resultSet()) > 0) {
    $user_id = $db->resultSet()[0]["user_id"];
    $password = $input['password'];
    $user = new User ($db, $crypt, $syslog);
    $user->setUserPW($user_id, $password);
    // Delete secret from db
    $stmt = $db->query('DELETE FROM au_change_password WHERE secret = :secret');
    $db->bind(':secret', $secret); 
    $user_id = $db->resultSet();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => true]);
  };

}

?>
