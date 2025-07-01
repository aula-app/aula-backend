<?php

require_once (__DIR__ . '/../../config/base_config.php');
require_once(__DIR__ . '/../../config/instances_config.php');
global $instances;

require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');
require_once "Mail.php";

$headers = apache_request_headers();
$code = $headers['aula-instance-code'];
$db = new Database($headers['aula-instance-code']);
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$jwt = new JWT($instances[$code]['jwt_key'], $db, $crypt, $syslog);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $email =  $_GET["email"];
  $stmt = $db->query('SELECT id, realname FROM au_users_basedata WHERE email = :email');
  $db->bind(':email', $email); 
  
  if (count($db->resultSet()) > 0) {
    $user_id = $db->resultSet()[0]["id"];
    $realname = $db->resultSet()[0]["realname"];

    $not_created = true;
    while ($not_created) {
      $secret = bin2hex(random_bytes(32));
      $stmt = $db->query('SELECT user_id FROM au_change_password WHERE secret = :secret');
      $db->bind(':secret', $secret); 

      if (count($db->resultSet()) == 0) {
        $not_created = false;
      }
    }

    $stmt = $db->query('DELETE FROM au_change_password WHERE user_id = :user_id ; INSERT INTO au_change_password (user_id, secret) values (:user_id, :secret)');
    $db->bind(':user_id', $user_id);
    $db->bind(':secret', $secret);

    $db->resultSet();

    $params = array  ('host' => $email_host,
    	'port' => $email_port,
    	'auth' => true,
    	'username' => $email_username,
    	'password' => $email_password);
    
    $smtp = Mail::factory ('smtp', $params);
    $content = "text/html; charset=utf-8";
    $mime = "1.0";

    $headers = array ('From' => $email_from,
    	'To' => $email,
    	'Subject' => 	$email_forgot_password_subject,
    	'Reply-To' => $email_address,
    	'MIME-Version' => $mime,
    	'Content-type' => $content);
    
    $mail = $smtp->send($email, $headers, sprintf($email_forgot_password_body, $realname, $secret, $secret));

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => true]);
  };
}
