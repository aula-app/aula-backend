<?php

require_once ('./base_config.php'); // load base config with paths to classes etc.
require_once ('./error_msg.php');
require ('./functions.php'); // include Class autoloader (models)
require ($baseHelperDir.'JWT.php');
require_once ($baseHelperDir.'Crypt.php');

$db = new Database();
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog ($db);
$user = new User ($db, $crypt, $syslog); 
$userData = $user->getUsers($offset, $limit, 4, 1, 1);

$i = 0;
foreach ($userData as $user) {
  $userData[$i]['realname'] = $crypt->decrypt ($user['realname']);
  $userData[$i]['username'] = $crypt->decrypt ($user['username']);
  $i = $i + 1;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($userData);

?>
