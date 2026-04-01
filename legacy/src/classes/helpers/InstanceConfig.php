<?php

class InstanceConfig
{
  public string $code;
  public string $host;
  public string $port;
  public string $user;
  public string $pass;
  public string $dbname;
  public string $jwt_key;
  public string $instance_api_url;

  private static ?PDO $centralDb = null;

  public function __construct(
    string $code,
    string $host,
    string $user,
    string $pass,
    string $dbname,
    string $jwt_key,
    string $instance_api_url,
    string $port = '3306'
  ) {
    $this->code = $code;
    $this->host = $host;
    $this->port = $port;
    $this->user = $user;
    $this->pass = $pass;
    $this->dbname = $dbname;
    $this->jwt_key = $jwt_key;
    $this->instance_api_url = $instance_api_url;
  }

  private static function getCentralDb(): PDO
  {
    if (self::$centralDb !== null) {
      return self::$centralDb;
    }
    $host   = getenv('CENTRAL_DB_HOST') ?: 'localhost';
    $port   = getenv('CENTRAL_DB_PORT') ?: '3306';
    $dbname = getenv('CENTRAL_DB_NAME') ?: 'aula_database';
    $user   = getenv('CENTRAL_DB_USER') ?: '';
    $pass   = getenv('CENTRAL_DB_PASS') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    self::$centralDb = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    return self::$centralDb;
  }

  private static function findTenantByCode(string $code): ?array
  {
    $stmt = self::getCentralDb()->prepare(
      'SELECT instance_code, jwt_key, api_base_url, data FROM tenants WHERE instance_code = ? LIMIT 1'
    );
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  public static function findAll(): ?array {
    $stmt = self::getCentralDb()->prepare('SELECT instance_code, jwt_key, api_base_url, data FROM tenants');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tenants = [];
    foreach ($rows as $tenant) {
      $data = json_decode($tenant['data'] ?? '{}', true);

      if (empty($data['tenancy_db_name']) || empty($data['tenancy_db_username']) || empty($data['tenancy_db_password'])) {
        error_log("Tenant '{$tenant['instance_code']}' is missing database credentials. Ensure the tenant was fully provisioned.");
        continue;
      }

      $tenants[$tenant['instance_code']] = new InstanceConfig(
        code: $tenant['instance_code'],
        host: getenv('CENTRAL_DB_HOST') ?: 'localhost',
        user: $data['tenancy_db_username'],
        pass: $data['tenancy_db_password'],
        dbname: $data['tenancy_db_name'],
        jwt_key: $tenant['jwt_key'] ?? '',
        instance_api_url: $tenant['api_base_url'] ?? '',
        port: getenv('CENTRAL_DB_PORT') ?: '3306',
      );
    }

    return $tenants;
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
    if ($code === null || !((bool) preg_match('/^[0-9a-zA-Z]{5}$|^SINGLE$/', $code))) {
      throw new RuntimeException("Please provide a valid aula-instance-code header");
    }

    return self::findTenantByCode($code) !== null ? $code : null;
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
    $tenant = self::findTenantByCode($code);
    if ($tenant === null) {
      throw new RuntimeException("No instance with provided instance code found.");
    }

    $data = json_decode($tenant['data'] ?? '{}', true);

    if (empty($data['tenancy_db_name']) || empty($data['tenancy_db_username']) || empty($data['tenancy_db_password'])) {
      throw new RuntimeException("Tenant '{$code}' is missing database credentials. Ensure the tenant was fully provisioned.");
    }

    return new InstanceConfig(
      code: $code,
      host: getenv('CENTRAL_DB_HOST') ?: 'localhost',
      user: $data['tenancy_db_username'],
      pass: $data['tenancy_db_password'],
      dbname: $data['tenancy_db_name'],
      jwt_key: $tenant['jwt_key'] ?? '',
      instance_api_url: $tenant['api_base_url'] ?? '',
      port: getenv('CENTRAL_DB_PORT') ?: '3306',
    );
  }
}
