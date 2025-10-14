<?php

require_once (__DIR__ . '/../../config/base_config.php');
global $baseHelperDir;
require_once ($baseHelperDir.'InstanceConfig.php');
if (($instance = InstanceConfig::createFromRequestOrEchoBadRequest()) === null) {
  return;
}

require ('../functions.php');
require_once ($baseHelperDir.'Crypt.php');
require_once ($baseHelperDir.'JWT.php');
require_once "Mail.php";

$db = new Database($instance);
$crypt = new Crypt();
$syslog = new Systemlog ($db);
$jwt = new JWT($instance->jwt_key, $db, $crypt, $syslog);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $email =  $_GET["email"];
  $stmt = $db->query('SELECT id,username,realname FROM au_users_basedata WHERE email = :email');
  $db->bind(':email', $email);

  $results = $db->resultSet();

  if (count($results) > 0) {
    $user_id = $results[0]["id"];
    $username = $results[0]["username"];
    $realname = $results[0]["realname"];

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

    $db->execute();

    $params = [
      'host' => $email_host,
      'port' => $email_port,
      'auth' => true,
      'username' => $email_username,
      'password' => $email_password
    ];

    $smtp = Mail::factory ('smtp', $params);
    $content = "text/html; charset=utf-8";
    $mime = "1.0";

    $headers = array ('From' => $email_from,
    	'To' => $email,
    	'Subject' => 	$email_forgot_password_subject,
    	'Reply-To' => $email_address_support,
    	'MIME-Version' => $mime,
    	'Content-type' => $content);

    $email_body = $email_forgot_password_body;
    $email_body = str_replace("<CODE>", $instance->code, $email_body);
    $email_body = str_replace("<NAME>", $realname, $email_body);
    $email_body = str_replace("<USERNAME>", $username, $email_body);
    $email_body = str_replace("<SECRET_KEY>", $secret, $email_body);

    $mail = $smtp->send($email, $headers, $email_body);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => true]);
  };
}
