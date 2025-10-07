<?php

require_once(__DIR__ . '/../../config/base_config.php');
global $baseHelperDir;
require_once($baseHelperDir . 'InstanceConfig.php');
if (($instance = InstanceConfig::createFromRequestOrEchoBadRequest()) === null) {
  return;
}

require ('../functions.php'); // include Class autoloader (models)
require_once ($baseHelperDir . 'Crypt.php');
require_once ($baseHelperDir . 'JWT.php');

require '../vendor/autoload.php';

$auth0 = new \Auth0\SDK\Auth0([
    'domain' => $AUTH0_DOMAIN,
    'clientId' => $AUTH0_CLIENT_ID,
    'clientSecret' => $AUTH0_CLIENT_SECRET,
    'cookieSecret' => $AUTH0_COOKIE_SECRET,
    'redirectUri' => $AUTH0_REDIRECT_URI,
]);

if ($auth0->getExchangeParameters()) {
    // If they're present, we should perform the code exchange.
    $auth0->exchange($AUTH0_REDIRECT_URI);
    $session = $auth0->getCredentials();
    if ($session->user["email"]) {
      $user_email = $session->user["email"];
      $crypt = new Crypt();
      $db = new Database($instance);
      $syslog = new Systemlog($db); // systemlog
      $settings = new Settings($db, $crypt, $syslog);

      $jwt = new JWT($instance->jwt_key, $db, $crypt, $syslog);
      $user = new User ($db, $crypt, $syslog);
      
      $current_settings = $settings->getInstanceSettings();

      // Check if user exist
      $user_email_query = "SELECT id from au_users_basedata where email = :email";
      $stmt = $db->query($user_email_query);
      try {
        $db->bind(':email', $user_email); 
        $user_result = $db->resultSet();
      } catch (Exception $e) {
        print_r($e);
      }

      $user_exist = $user_result;

      // If exist do not create an user
      if ($user_exist) {
        $user_id = $user_result[0]["id"];
      } else {
        // ...otherwise, create a new user
        $realname = $session->user["name"];
        $displayname = $realname;
        $username = $session->user["nickname"];
        $email = $user_email;
        $user_id = $user->addUser($realname, $displayname, $username, $email, nomail: true)["data"]["insert_id"];
      }
      
      if ($current_settings["data"]["online_mode"] != 1 && $loginResult["data"]["userlevel"] < 50) {
        echo json_encode([
          "success" => "false",
          "online_mode" => $current_settings["data"]["online_mode"]
        ]);
        return;
      }

      $stmt = $db->query('SELECT id, username, userlevel FROM ' . $db->au_users_basedata . ' WHERE id = :id AND status = 1');
      try {
        $db->bind(':id', $user_id); // blind index
        $users = $db->resultSet();
      } catch (Exception $e) {
        print_r($e);
      }

      $jwt_token = $jwt->gen_jwt($users[0]);
      header("Location: ".$AUTH0_FRONTEND_REDIRECT.$jwt_token);
    }
}

?>
