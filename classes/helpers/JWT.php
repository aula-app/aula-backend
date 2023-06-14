<?php

function base64_url_encode($text):String{
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
}

class JWT {
    private $key;

    public function __construct($keyFile) {
        $this->key = file_get_contents($keyFile);
        $this->key = str_replace(PHP_EOL, '', $this->key);
    }

    public function gen_jwt($username):String {
        $header = [ 
            "alg" => "HS512", 
            "typ" => "JWT" 
        ];
        $header = base64_url_encode(json_encode($header));
        $payload =  [
            "exp" => 0,
            "username" => $username
        ];
        $payload = base64_url_encode(json_encode($payload));
        $signature = base64_url_encode(hash_hmac('sha512', "$header.$payload", $this->key, true));
        $jwt = "$header.$payload.$signature";
        return $jwt;    
    }

    public function check_jwt() {
      $secret = $this->key;
      $headers = apache_request_headers();
    
      if(isset($headers['Authorization'])){
        $matches = array();
        preg_match('/Bearer (.*)/', $headers['Authorization'], $matches);
        if (isset($matches[1])){
          $token = $matches[1];
    
          $tokenParts = explode('.', $token);
          $header = base64_decode($tokenParts[0]);
          $payload = base64_decode($tokenParts[1]);
          $signature_provided = $tokenParts[2];
          
          $base64_url_header = base64_url_encode($header);
          $base64_url_payload = base64_url_encode($payload);
          $signature = hash_hmac('SHA512', $base64_url_header . "." . $base64_url_payload, $secret, true);
          $base64_url_signature = base64_url_encode($signature);
    
          return $signature_provided == $base64_url_signature;
        } else {
          return false;
        }
      }
    }
}
?>
