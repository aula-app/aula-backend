<?php

require_once ('./base_config.php'); // load base config with paths to classes etc.
require_once ('./error_msg.php');
require ('./functions.php'); // include Class autoloader (models)
require ($baseHelperDir.'JWT.php');
require_once ($baseHelperDir.'Crypt.php');

$db = new Database();
$crypt = new Crypt($cryptFile); // path to $cryptFile is currently known from base_config.php -> will be changed later to be secure
$syslog = new Systemlog ($db); // systemlog
$room = new Room ($db, $crypt, $syslog); // instanciate room model class
$roomdata = $room->getRooms(0, 0, 4, 1, 1);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($roomdata);

?>
