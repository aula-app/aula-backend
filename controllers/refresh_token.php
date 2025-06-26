<?PHP

require_once('../base_config.php'); // load base config with paths to classes etc.
require_once('../error_msg.php');
require('../functions.php'); // include Class autoloader (models)
require_once($baseHelperDir . 'Crypt.php');
require_once($baseHelperDir . 'JWT.php');
require_once('../db.php');

$headers = apache_request_headers();
$code = $headers["code"];
$db = new Database($headers["code"]);
$crypt = new Crypt($cryptFile); // path to $cryptFile is currently known from base_config.php -> will be changed later to be secure
$syslog = new Systemlog($db); // systemlog
$user = new User($db, $crypt, $syslog);
$settings = new Settings($db, $crypt, $syslog);

$jwt = new JWT($databases[$code]['jwt_key'], $db, $crypt, $syslog);
$check_jwt = $jwt->check_jwt(true);

if ($check_jwt["success"]) {
  $jwt_payload = $jwt->payload();

  $new_payload = $user->getUserPayload($jwt_payload->user_id);

  if ($new_payload['success']) {
    $new_jwt = $jwt->gen_jwt($new_payload['data']);
    $user->setRefresh($jwt_payload->user_id, false);
    echo json_encode(["success" => true, 'JWT' => $new_jwt]);
  } else {
    return [ "succes" => false ];
  }
} else {
  return [ "succes" => false ];
}
 

?>
