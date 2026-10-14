<?php

require_once(__DIR__ . '/../../../config/base_config.php'); // load base config with paths to classes etc.

require_once($baseHelperDir . 'Crypt.php');

function base64_url_encode($text): string
{
  return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
}

function base64_url_decode($data) {
    $data = strtr($data, '-_', '+/');
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    return base64_decode($data);
}

class JWT
{
  private $key;

  public function __construct($key, $db, $crypt, $syslog)
  {
    $this->key = ($key);
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
    $authHeader = $headers['authorization'] ?? $headers['Authorization'];

    if (isset($authHeader)) {
      $matches = array();
      preg_match('/Bearer (.*)/', $authHeader, $matches);
      if (isset($matches[1])) {
        $token = $matches[1];

        $tokenParts = explode('.', $token);
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signature_provided = $tokenParts[2];
        $base64_url_header = base64_url_encode($header);
        $base64_url_payload = base64_url_encode($payload);
        $data_signed = $base64_url_header . "." . $base64_url_payload;

        // determine signing algorithm based on JWT alg claim
        if (json_decode($header)->alg === "HS512") {
          $signature = hash_hmac('sha512', $data_signed, $secret, true);
          $base64_url_signature = base64_url_encode($signature);
          $valid_signature = $signature_provided == $base64_url_signature;
        } elseif (json_decode($header)->alg === "RS256") {
          $valid_signature = $this->verifyRS256JWT($data_signed, $signature_provided);
        } else {
          return ["success" => false, "error" => "unsupported_signing_algorithm"];
        }

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

          $refresh = $this->user->checkRefresh($p->user_hash);

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
    $authHeader = $headers['authorization'] ?? $headers['Authorization'];

    if ($this->check_jwt()) {
      $matches = array();
      preg_match('/Bearer (.*)/', $authHeader, $matches);
      if (isset($matches[1])) {
        $token = $matches[1];
        $tokenParts = explode('.', $token);
        $payload = base64_decode($tokenParts[1]);
        return json_decode($payload);
      }
    }
  }

  function verifyRS256JWT($data_signed, $signature) {
    $signature = base64_url_decode($signature);

    $publicKey = file_get_contents(__DIR__ . '/../../../config/oauth-public.key');
    $publicKeyResource = openssl_pkey_get_public($publicKey);
    if (!$publicKeyResource) {
        return false;
    }

    $result = openssl_verify($data_signed, $signature, $publicKeyResource, OPENSSL_ALGO_SHA256);
    openssl_free_key($publicKeyResource);

    return $result === 1;
  }
}
