<?php
class Crypt {
    private $key;

    public function __construct($keyFile) {
        $this->key = file_get_contents($keyFile);
    }

    public function encrypt($string) {
        try {
          if (!$string){
            return 0;
          }
          $iv = openssl_random_pseudo_bytes(12); // 12 bytes IV for GCM
          $tag = "";
          $encrypted = openssl_encrypt($string, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
          return base64_encode($encrypted . $iv . $tag);
        }catch (Exception $e) {
            echo 'Error occured while encrypting: ',  $e->getMessage(), "\n"; // display error
            $err=true;
            return 0;
        }
    }

    public function decrypt($encryptedString) {
        $decrypted="";
      try {
          if (!$encryptedString){
          return 0;
          }
          $data = base64_decode($encryptedString);
          $iv = substr($data, -28, 12); // IV is the last 12 bytes
          $tag = substr($data, -16); // Tag is the last 16 bytes
          $encrypted = substr($data, 0, -28); // Encrypted data is everything except the last 28 bytes
          $decrypted = openssl_decrypt($encrypted, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
          return $decrypted;
        } catch (Exception $e) {
            echo 'Error occured while decrypting: ',  $e->getMessage(), "\n"; // display error
            $err=true;
            return 0;
        }

    }
}
?>
