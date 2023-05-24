<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script



if ($allowed_include==1){

}else {
  exit;
}

class Systemlog {
    private $db;

    public function __construct($db) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;

        $au_systemlog = 'au_systemlog';
        $this->$au_systemlog = $au_systemlog; // table name for user basedata
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

    public function addSystemEvent($type, $msg, $id=0, $url="-", $id_type) {
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

      $stmt = $this->db->query('INSERT INTO '.$this->au_systemlog.' (type, message, usergroup, url, created, last_update) VALUES (:type, :message, :group, :url, NOW(), NOW())');
      // bind all VALUES
      //echo ("INSERT INTO ".$this->au_systemlog." (type, message, usergroup, url, created, last_update) VALUES (".$type.", ".$msg.", ".$group_id.", ".$url.", NOW(), NOW())");
      $this->db->bind(':type', $type);
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
        return $this->db->lastInsertId(); // return insert id to calling script
      } else {
        return 0; // return 0 to indicate that there was an error executing the statement
      }
    }// end function


    function getSystemlog($date_start, $date_end) {
      /* returns userlist (associative array) with start and limit provided
      */
      $stmt = $this->db->query('SELECT * FROM '.$this->au_systemlog.' WHERE last_update> :date_start AND last_update < :date_end');
      $this->db->bind(':date_start', $date_start); // bind date start
      $this->db->bind(':date_end', $date_end); // bind date_start
      $err=false;
      try {
        $entries = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting system log: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($entries)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $entries; // return an array (associative) with all the data
      }
    }// end function



    private function checkUserId ($userid) {
      /* helper function that checks if a user id is a standard db id (int) or if a hash userid was passed
      if a hash was passed, function gets db user id and returns db id
      */

      if (is_int($userid))
      {
        return $userid;
      } else
      {
        return $this->getUserIdByHashId ($userid);
      }
    } // end function



    public function deleteSyslogEntry($entry_id) {
        /* deletes system log entry and returns the number of rows (int) accepts entry id */

        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->au_systemlog.' WHERE id = :id');
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
          return $this->db->rowCount(); // return row count of deleted
        } else {
          return 0; // return 0 to indicate that there was an error executing the statement
        }

    }// end function

}
?>
