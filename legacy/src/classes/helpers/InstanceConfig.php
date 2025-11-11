<?php

require_once(__DIR__ . '/../../../config/instances_config.php');

class InstanceConfig
{
  public string $code;
  public string $host;
  public string $user;
  public string $pass;
  public string $dbname;
  public string $jwt_key;
  public string $instance_api_url;

  /**
   * @param array<string,string> $instance
   */
  public function __construct(string $code, array $instance)
  {
    $this->code = $code;
    $this->host = $instance['host'];
    $this->user = $instance['user'];
    $this->pass = $instance['pass'];
    $this->dbname = $instance['dbname'];
    $this->jwt_key = $instance['jwt_key'];
    $this->instance_api_url = $instance['instance_api_url'];
  }

  public static function validateInstanceCodeFromRequest(bool $searchInPostBodyContent): ?string
  {
    $headers = apache_request_headers();
    if ($searchInPostBodyContent) {
      $json = file_get_contents('php://input');
      $data = json_decode($json);
      $code = $data->code;
    } elseif (array_key_exists('aula-instance-code', $headers)) {
      $code = $headers['aula-instance-code'];
    } elseif (array_key_exists('code', $_GET)) {
      $code = $_GET['code'];
    } else {
      $code = null;
    }

    // check if provided code is alphanumeric(5) // could also be "SINGLE", so length 6 is allowed
    if ($code === null || !((bool) preg_match('/^[0-9a-zA-Z]{5}|SINGLE$/', $code))) {
      throw new RuntimeException("Please provide a valid aula-instance-code header");
    }

    global $instances;
    if (!array_key_exists($code, $instances)) {
      return null;
    }

    return $code;
  }

  public static function createFromRequestOrEchoBadRequest(bool $searchInPostBodyContent = false): InstanceConfig | null
  {
    try {
      if ($code = InstanceConfig::validateInstanceCodeFromRequest($searchInPostBodyContent)) {
        return InstanceConfig::createFromCode($code);
      } else {
        throw new RuntimeException("No instance with provided instance code found.");
      }
    } catch (Throwable $t) {
      error_log($t->getMessage());
      http_response_code(400);
      echo json_encode(['success' => false, 'error_code' => 1, 'error' => $t->getMessage()]);
      return null;
    }
  }

  public static function createFromCode(string $code): InstanceConfig
  {
    global $instances;
    return new InstanceConfig($code, $instances[$code]);
  }
}
