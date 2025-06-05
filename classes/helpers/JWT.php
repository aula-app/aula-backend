<?php

require_once('../config/base_config.php'); // load base config with paths to classes etc.
require_once('../error_msg.php');
require_once($baseHelperDir . 'Crypt.php');

function base64_url_encode($text): string
{
  return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
}

class JWT
{
  private $key;

  public function __construct($keyFile, $db, $crypt, $syslog)
  {
    $this->key = file_get_contents($keyFile);
    $this->key = str_replace(PHP_EOL, '', $this->key);
    $this->db = $db;
    $this->crypt = $crypt;
    $this->syslog = $syslog;
    $this->user = new User($db, $crypt, $syslog);
  }

  public function gen_jwt($user): string
  {
    $header = [
      "alg" => "HS512",
      "typ" => "JWT"
    ];
    $header = base64_url_encode(json_encode($header));
    $payload = [
      "exp" => 0,
      "user_id" => $user["id"],
      "user_hash" => $user["hash_id"],
      "user_level" => $user["userlevel"],
      "roles" => json_decode($user["roles"]),
      "temp_pw" => $user["temp_pw"]
    ];

    $payload = base64_url_encode(json_encode($payload));
    $signature = base64_url_encode(hash_hmac('sha512', "$header.$payload", $this->key, true));
    $jwt = "$header.$payload.$signature";
    return $jwt;
  }

  public function check_jwt($ignore_refresh = false)
  {
    $secret = $this->key;
    $headers = apache_request_headers();

    if (isset($headers['Authorization'])) {
      $matches = array();
      preg_match('/Bearer (.*)/', $headers['Authorization'], $matches);
      if (isset($matches[1])) {
        $token = $matches[1];

        $tokenParts = explode('.', $token);
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signature_provided = $tokenParts[2];


        $base64_url_header = base64_url_encode($header);
        $base64_url_payload = base64_url_encode($payload);
        $signature = hash_hmac('SHA512', $base64_url_header . "." . $base64_url_payload, $secret, true);
        $base64_url_signature = base64_url_encode($signature);

        $valid_signature = $signature_provided == $base64_url_signature;

        if ($valid_signature) {
          $p = json_decode($payload);

          // Check if user exists in database
          $userBaseData = $this->user->getUserBaseData($p->user_hash);
          if (!$userBaseData['success'] || !$userBaseData['data']) {
            return ["success" => false, "error" => "user_not_found"];
          }

          $userData = $userBaseData['data'];

          // Check if user is active
          if ($userData['status'] != 1) { // 1 = active status
            return ["success" => false, "error" => "user_not_active"];
          }

          if ($ignore_refresh) {
            return ["success" => true];
          }

          $refresh = $this->user->checkRefresh($p->user_id);

          if ($refresh) {
            return ["success" => false, "error" => "refresh_token"];
          } else {
            return ["success" => true];
          }
        } else {
          return ["success" => false, "error" => "invalid_signature"];
        }

      } else {
        return false;
      }
    }
  }

  public function payload()
  {
    $secret = $this->key;
    $headers = apache_request_headers();

    if ($this->check_jwt()) {
      $matches = array();
      preg_match('/Bearer (.*)/', $headers['Authorization'], $matches);
      if (isset($matches[1])) {
        $token = $matches[1];
        $tokenParts = explode('.', $token);
        $payload = base64_decode($tokenParts[1]);
        return json_decode($payload);
      }
    }
  }
}
?>
