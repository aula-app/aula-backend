<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script



if ($allowed_include==1){

}else {
  exit;
}

class Systemlog {
    private $db;
    # helper class for system logging

    public function __construct($db) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->converters = new Converters ($db);

    }// end function

    public function addSystemEvent($type, $msg, $id=0, $url="-", $id_type, $updater_id=0) {
      /* adds an event to the system php_log
      $type (int) 0=standard, 1=warning, 2=error 3=nuke error
      $msg (text) entry message / error message
      $url (text) url where event occured (i.e. error)
      $id_type 0=id is a user id 1= id is a group id
      */

      // userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed) // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('INSERT INTO '.$this->db->au_systemlog.' (type, message, usergroup, url, created, last_update, updater_id) VALUES (:type, :message, 0, :url, NOW(), NOW(), :updater_id)');
      // bind all VALUES
      $this->db->bind(':type', $type);
      $this->db->bind(':updater_id', $updater_id);
      $this->db->bind(':message', $msg);
      $this->db->bind(':url', $url);

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {
          error_log('Error occured: ' . $e->getMessage()); // display error
          $err=true;
      }
      if (!$err)
      {
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; //  error code
        $returnvalue ['data'] = $this->db->lastInsertId(); // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      } else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; //  error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    }// end function

}
?>
