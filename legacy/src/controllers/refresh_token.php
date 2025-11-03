<?PHP

require_once(__DIR__ . '/../../config/base_config.php');
global $baseHelperDir;
require_once($baseHelperDir . 'InstanceConfig.php');
if (($instance = InstanceConfig::createFromRequestOrEchoBadRequest()) === null) {
  return;
}

require('../functions.php'); // include Class autoloader (models)
require_once($baseHelperDir . 'Crypt.php');
require_once($baseHelperDir . 'JWT.php');
require_once(__DIR__ . '/../../config/instances_config.php');

$db = new Database($instance);
$crypt = new Crypt(); // path to $cryptFile is currently known from base_config.php -> will be changed later to be secure
$syslog = new Systemlog($db); // systemlog
$user = new User($db, $crypt, $syslog);
$settings = new Settings($db, $crypt, $syslog);

$jwt = new JWT($instance->jwt_key, $db, $crypt, $syslog);
$check_jwt = $jwt->check_jwt(true);

if ($check_jwt["success"]) {
  $jwt_payload = $jwt->payload();

  $new_payload = $user->getUserPayload($jwt_payload->user_id);

  if ($new_payload['success']) {
    $new_jwt = $jwt->gen_jwt($new_payload['data']);
    $user->setRefresh($jwt_payload->user_id, false);
    echo json_encode(["success" => true, 'JWT' => $new_jwt]);
  } else {
    return [ "success" => false ];
  }
} else {
  return [ "success" => false ];
}
