<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}


class Group {
    private $db;

    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->crypt = $crypt;
        $this->syslog = $syslog;

        $au_groups = 'au_groups';
        $this->$au_groups = $au_groups; // table name for groups
    }// end function

    public function getGroupBaseData($group_id) {
      /* returns user base data for a specified db id */
      $group_id = $this->checkGroupId($group_id); // checks group_id id and converts group id to db group id if necessary (when group hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_groups.' WHERE id = :id');
      $this->db->bind(':id', $group_id); // bind group id
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $groups[0]; // return an array (associative) with all the data for the group
      }
    }// end function

    public function getGroupHashId($group_id) {
      /* returns hash_id of a group for a integer group id
      */
      $stmt = $this->db->query('SELECT hash_id FROM '.$this->au_groups.' WHERE id = :id');
      $this->db->bind(':id', $group_id); // bind group id
      $group_id = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $groups[0]['hash_id']; // return an array (associative) with all the data for the group
      }
    }// end function

    public function getGroupIdByHashId($hashid) {
      /* Returns Database ID of group when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->au_groups.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hashid); // bind hash id
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $groups[0]['id']; // return group id
      }
    }// end function


    public function checkAccesscode($group_id, $access_code) { // access_code = clear text
      /* checks access code and returns database group id (credentials correct) or 0 (credentials not correct)
      */
      $stmt = $this->db->query('SELECT group_name, id,access_code,hash_id FROM '.$this->au_groups.' WHERE group_id= :group_id');
      $this->db->bind(':id', $group_id); // bind group id

      $groups = $this->db->resultSet();

      if (count($groups)<1){
        return 0;
      } // nothing found or empty database

      foreach ($groups as $group) {
          $db_access_code = $group['pw'];
          if (password_verify($access_code, $db_access_code))
          {
            return $group['id'];
          }else {

            return 0;
          }
      } // end foreach
        $this->syslog->addSystemEvent("Group access code incorrect: ".$group['group_name'], 0, "", 1);
        return 0;
    }// end function



    public function checkGroupExist($group_id) {
      /* returns 0 if group does not exist, 1 if group exists, accepts databse id (int)
      */
      $group_id = $this->checkGroupId($group_id); // checks group id and converts user id to db group id if necessary (when group hash id was passed)

      $stmt = $this->db->query('SELECT id FROM '.$this->au_groups.' WHERE id = :id');
      $this->db->bind(':id', $group_id); // bind group id
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // user found, return 1
      }
    } // end function


    function getGroups($offset, $limit) {
      /* returns grouplist (associative array) with start and limit provided
      */
      $stmt = $this->db->query('SELECT * FROM '.$this->au_groups.' LIMIT :offset , :limit');
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
      $err=false;
      try {
        $groups = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting groups: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $groups; // return an array (associative) with all the data
      }
    }// end function

    public function addGroup($group_name, $description_public, $description_internal, $internal_info, $status, $access_code, $updater_id=0) {
        /* adds a new group and returns insert id (group id) if successful, accepts the above parameters
         description_public = actual description of the group, status = status of inserted group (0 = inactive, 1=active)
        */

        $hash_access_code = password_hash($access_code, PASSWORD_DEFAULT); // hash access code

        $stmt = $this->db->query('INSERT INTO '.$this->au_groups.' (group_name, description_public, description_internal, internal_info, status, hash_id, access_code, created, last_update, updater_id) VALUES (:group_name, :description_public, :description_internal, :internal_info, :status, :hash_id, :access_code, NOW(), NOW(), :updater_id)');
        // bind all VALUES
        $this->db->bind(':group_name', $group_name);
        $this->db->bind(':description_public', $description_public);
        $this->db->bind(':description_internal', $description_internal);
        $this->db->bind(':internal_info', $internal_info);
        $this->db->bind(':access_code', $hash_access_code);
        $this->db->bind(':status', $status);
        // generate unique hash for this user
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($group_name.$appendix); // create hash id for this user
        $this->db->bind(':hash_id', $hash_id);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        $insertid = intval($this->db->lastInsertId());
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added new group (#".$insertid.") ".$group_name, 0, "", 1);
          return $insertid; // return insert id to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error adding group ".$group_name, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function


    public function setGroupStatus($group_id, $status, $updater_id=0) {
        /* edits a group and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of inserted group (0 = inactive, 1=active)
         updater_id is the id of the group that commits the update (i.E. admin )
        */
        $group_id = $this->checkGroupId($group_id); // checks group  id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_groups.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :group_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':group_id', $group_id); // group that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Group status changed ".$group_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of group ".$group_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setGroupDescriptionPublic($group_id, $about, $updater_id=0) {
        /* edits a group and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a group
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $group_id = $this->checkGroupId($group_id); // checks group id and converts user id to db group id if necessary (when group hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_groups.' SET description_public= :about, last_update= NOW(), updater_id= :updater_id WHERE id= :group_id');
        // bind all VALUES
        $this->db->bind(':about', $about);
        $this->db->bind(':updater_id', $updater_id); // id of the group doing the update (i.e. admin)

        $this->db->bind(':group_id', $group_id); // group that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Group description public changed ".$group_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing description public ".$group_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setGroupDescriptionInternal($group_id, $about, $updater_id=0) {
        /* edits a group and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a group
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $group_id = $this->checkGroupId($group_id); // checks group id and converts user id to db group id if necessary (when group hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_groups.' SET description_internal= :about, last_update= NOW(), updater_id= :updater_id WHERE id= :group_id');
        // bind all VALUES
        $this->db->bind(':about', $about);
        $this->db->bind(':updater_id', $updater_id); // id of the group doing the update (i.e. admin)

        $this->db->bind(':group_id', $group_id); // group that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Group description internal changed ".$group_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing description internal of user ".$group_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setGroupname($group_id, $group_name, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         realname = actual name of the user
        */
        $group_id = $this->checkGroupId($group_id); // checks group id and converts group id to db user id if necessary (when group hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_groups.' SET group_name= :group_name, last_update= NOW(), updater_id= :updater_id WHERE id= :group_id');
        // bind all VALUES
        $this->db->bind(':group_name', $group_name);
        $this->db->bind(':group_id', $group_id); // group that is updated
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Group name changed ".$group_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing name of group ".$group_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setGroupAccesscode($group_id, $access_code, $updater_id=0) {
        /* edits a group and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         pw = pw in clear text
        */
        $group_id = $this->checkGroupId($group_id); // checks group id and converts group id to db group id if necessary (when group hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_groups.' SET access_code= :access_code, last_update= NOW(), updater_id= :updater_id WHERE id= :group_id');

        // generate access code hash
        $hash = password_hash($access_code, PASSWORD_DEFAULT);
        // bind all VALUES
        $this->db->bind(':access_code', $hash);
        $this->db->bind(':group_id', $group_id); //group that is updated
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Group Access Code changed ".$group_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing access code of group ".$group_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    private function checkGroupId ($group_id) {
      /* helper function that checks if a user id is a standard db id (int) or if a hash group id was passed
      if a hash was passed, function gets db group id and returns db id
      */

      if (is_int($group_id))
      {
        return $group_id;
      } else
      {
        return $this->getGroupIdByHashId ($group_id);
      }
    } // end function

    public function deleteGroup($group_id, $updater_id=0) {
        /* deletes group and returns the number of rows (int) accepts group id or group hash id // */
        $group_id = $this->checkGroupId($group_id); // checks group id and converts group id to db group id if necessary (when group hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->au_groups.' WHERE id = :id');
        $this->db->bind (':id', $group_id);
        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Group deleted with id ".$group_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting group with id ".$group_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }

    }// end function

}
?>
