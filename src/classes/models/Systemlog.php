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

    private function getGroupId ($user_id) {
      /* gets group id for a sepcified user_id (int)
      accepts user_id (int)
      returns $group_id (int)
      */
      $group_id=0;
      /* to build: access db, find user if not present than set group id to 0
      */
      return $group_id;
    }

    public function addSystemEvent($type, $msg, $id=0, $url="-", $id_type, $updater_id=0) {
      /* adds an event to the system php_log
      $type (int) 0=standard, 1=warning, 2=error 3=nuke error
      $msg (text) entry message / error message
      $group (int) group (if available) that caused the error / activity
      $url (text) url where event occured (i.e. error)
      $id_type 0=id is a user id 1= id is a group id
      */
      $group_id=0;
      if ($id_type==0){
        // id is user id, get group id for this user in order to anonymize
        $group_id = getGroupId ($id);
      }

      // userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed) // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('INSERT INTO '.$this->db->au_systemlog.' (type, message, usergroup, url, created, last_update, updater_id) VALUES (:type, :message, :group, :url, NOW(), NOW(), :updater_id)');
      // bind all VALUES
      $this->db->bind(':type', $type);
      $this->db->bind(':updater_id', $updater_id);
      $this->db->bind(':message', $msg);
      $this->db->bind(':group', $group_id);
      $this->db->bind(':url', $url);
      //echo ("<br>".$stmt->debugDumpParams());

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {
          echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
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


    function getSystemlog($date_start, $date_end) {
      /* returns userlist (associative array) with start and limit provided
      */
      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_systemlog.' WHERE last_update> :date_start AND last_update < :date_end');
      $this->db->bind(':date_start', $date_start); // bind date start
      $this->db->bind(':date_end', $date_end); // bind date_start
      $err=false;
      try {
        $entries = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting system log: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; //  error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
      }
      if (count($entries)<1){
        // no entries found
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 2; //  error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        // entries found
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; //  error code
        $returnvalue ['data'] = $entries; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;
      }

    }// end function


    public function deleteSyslogEntry($entry_id) {
        /* deletes system log entry and returns the number of rows (int) accepts entry id */

        $stmt = $this->db->query('DELETE FROM '.$this->db->au_systemlog.' WHERE id = :id');
        $this->db->bind (':id', $entry_id);
        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; //  error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

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
