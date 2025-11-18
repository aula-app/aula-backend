<?php

require_once(__DIR__ . '/../../config/base_config.php');
global $baseHelperDir;
require_once($baseHelperDir . 'InstanceConfig.php');
try {
  $code = InstanceConfig::validateInstanceCodeFromRequest(searchInPostBodyContent: true);
} catch (Throwable $t) {
  error_log($t->getMessage());
  http_response_code(400);
  echo json_encode(['success' => false, 'error_code' => 1, 'error' => $t->getMessage()]);
  exit(0);
}

if ($code !== null) {
  $instance = InstanceConfig::createFromRequestOrEchoBadRequest(searchInPostBodyContent: true);
  echo json_encode(["status" => true, "instanceApiUrl" => $instance->instance_api_url]);
} else {
  echo json_encode(['status' => false]);
}
