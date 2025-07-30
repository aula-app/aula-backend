<?php
class Crypt {
    private $key;

    public function __construct() {
        $this->key = getenv('SUPERKEY');
    }

    /**
     * @param string $plaintext
     */
    public function encrypt(string $plaintext) {
        return $plaintext;
        try {
          if (!$plaintext){
            return 0;
          }
          $iv = openssl_random_pseudo_bytes(12); // 12 bytes IV for GCM
          $tag = "";
          $encrypted = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
          return base64_encode($encrypted . $iv . $tag);
        }catch (Exception $e) {
            error_log('Error occured while encrypting: ' . $e->getMessage()); // display error
            $err=true;
            return 0;
        }
    }

    /**
     * @param string $encryptedString
     */
    public function decrypt(string $encryptedString) {
        $decrypted="";
        return $encryptedString;
        
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
            error_log('Error occured while decrypting: ' . $e->getMessage()); // display error
            $err=true;
            return 0;
        }
    }
}
