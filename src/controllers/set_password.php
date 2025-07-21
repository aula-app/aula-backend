<?php

require_once (__DIR__ . '/../../config/base_config.php');

require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');
require_once(__DIR__ . '/../../config/instances_config.php');
global $instances;

$headers = apache_request_headers();

if (array_key_exists('aula-instance-code', $headers)) {
  $code = $headers['aula-instance-code'];
} else {
  if (array_key_exists('code', $_GET)) {
    $code = $_GET['code'];
  } else {
    echo json_encode(["success" => false, "message" => "Fail to get instance code."]);
    return;
  }
}
$db = new Database($headers['aula-instance-code']);
$code = $headers['aula-instance-code'];
$crypt = new Crypt();
$syslog = new Systemlog($db);
$jwt = new JWT($instances[$code]['jwt_key'], $db, $crypt, $syslog);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $secret = $_GET["secret"];

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
