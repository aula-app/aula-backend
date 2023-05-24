<?php
class HashGenerator {
    private $salt;

    public function __construct($salt_file) {
        // Read the salt from a file
        $this->salt = file_get_contents($salt_file);
    }

    public function generateHash($string) {
        $time = time();
        $random = rand();
        $hash = sha1($this->salt . $time . $random . $string);
        return $hash;
    }
}
?>
