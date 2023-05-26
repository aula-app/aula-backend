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

        $au_rooms = 'au_rooms';
        $au_groups = 'au_groups';
        $au_users_basedata = 'au_users_basedata';
        $au_rel_rooms_users ='au_rel_rooms_users';
        $au_rel_groups_users ='au_rel_groups_users';

        $this->$au_users_basedata = $au_users_basedata; // table name for user basedata
        $this->$au_rooms = $au_rooms; // table name for rooms
        $this->$au_groups = $au_groups; // table name for groups
        $this->$au_rel_rooms_users = $au_rel_rooms_users; // table name for relations room - user
        $this->$au_rel_groups_users = $au_rel_groups_users; // table name for relations group - user

    }// end function

    public function getGroupBaseData($group_id) {
      /* returns group base data for a specified db id or 0 if nothing is found */
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
      $stmt = $this->db->query('SELECT group_name, id,access_code,hash_id FROM '.$this->au_groups.' WHERE id= :id');
      $this->db->bind(':id', $group_id); // bind group id

      $groups = $this->db->resultSet();

      if (count($groups)<1){
        return 0;
      } // nothing found or empty database

      foreach ($groups as $group) {
          $db_access_code = $group['access_code'];
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
      $group_id = $this->checkGroupId($group_id); // checks group id and converts group id to db group id if necessary (when group hash id was passed)

      $stmt = $this->db->query('SELECT id FROM '.$this->au_groups.' WHERE id = :id');
      $this->db->bind(':id', $group_id); // bind group id
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // group found, return 1
      }
    } // end function

    function getUsersInGroup($groupid, $status=1) {
      /* returns users (associative array)
      $status (int) relates to the status of the users => 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      */

      $stmt = $this->db->query('SELECT '.$this->au_users_basedata.'.realname, '.$this->au_users_basedata.'.displayname, '.$this->au_users_basedata.'.id, '.$this->au_users_basedata.'.username, '.$this->au_users_basedata.'.email FROM '.$this->au_rel_groups_users.' INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_rel_groups_users.'.user_id='.$this->au_users_basedata.'.id) WHERE '.$this->au_rel_groups_users.'.group_id= :groupid AND '.$this->au_users_basedata.'.status= :status' );
      $this->db->bind(':groupid', $groupid); // bind group id
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $groups = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting users in group: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $groups; // return an array (associative) with all the data
      }
    }// end function

    function deleteUserFromGroup($groupid, $userid) {
      /* deletes a user from a group
      */

      $stmt = $this->db->query('DELETE FROM '.$this->au_rel_groups_users.' WHERE user_id = :userid AND group_id = :groupid' );
      $this->db->bind(':groupid', $groupid); // bind room id
      $this->db->bind(':userid', $userid); // bind user id

      $err=false;
      try {
        $groups = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while deleting user from group: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return "0,0";
      }


      return "1,".$this->db->rowCount(); // return number of affected rows to calling script

    }// end function

    function emptyGroup($groupid) {
      /* deletes all users from a group
      */

      $stmt = $this->db->query('DELETE FROM '.$this->au_rel_groups_users.' WHERE group_id = :groupid' );
      $this->db->bind(':groupid', $groupid); // bind room id

      $err=false;
      try {
        $groups = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while emptying group: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return "0,0";
      }


      return "1,".$this->db->rowCount(); // return number of affected rows to calling script

    }// end function



    function getGroups($offset, $limit, $orderby, $asc, $status=1) {
      /* returns group list (associative array) with start and limit provided
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      */
      // init vars
      $orderby_field="";
      $asc_field ="";

      $limit_string=" LIMIT :offset , :limit ";
      $limit_active=true;

      switch (intval ($orderby)){
        case 0:
        $orderby_field = "group_name";
        break;
        case 1:
        $orderby_field = "order";
        break;
        case 2:
        $orderby_field = "created";
        break;
        case 3:
        $orderby_field = "last_update";
        break;
        case 4:
        $orderby_field = "id";
        break;

        default:
        $orderby_field = "last_update";
      }

      switch (intval ($asc)){
        case 0:
        $asc_field = "DESC";
        break;
        case 1:
        $asc_field = "ASC";
        break;
        default:
        $asc_field = "DESC";
      }

      $stmt = $this->db->query('SELECT * FROM '.$this->au_groups.' WHERE status= :status ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);

      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }

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

    private function checkGroupExistsByName($group_name){
      // checks if a group with this name is already in database
      $group_name=trim ($group_name); // trim spaces

      $stmt = $this->db->query('SELECT id FROM '.$this->au_groups.' WHERE group_name = :group_name');
      $this->db->bind(':group_name', $group_name); // bind room id
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // return 1 = exists
      }
    }

    public function addGroup($group_name, $description_public, $description_internal, $internal_info, $status, $access_code, $updater_id=0, $group_order=10) {
        /* adds a new group and returns insert id (group id) if successful, accepts the above parameters
         description_public = actual description of the group, status = status of inserted group (0 = inactive, 1=active)
        */
        //sanitize in vars
        $group_order = intval ($group_order);
        $updater_id = intval ($updater_id);
        $status = intval($status);


        // check if group name is still available
        if ($this->checkGroupExistsByName($group_name)>0){
          return "0,1"; // group exists, stop exectuing, return errorcode 1 = group exists
        }


        $hash_access_code = password_hash(trim ($access_code), PASSWORD_DEFAULT); // hash access code

        $stmt = $this->db->query('INSERT INTO '.$this->au_groups.' (group_name, description_public, description_internal, internal_info, status, hash_id, access_code, created, last_update, updater_id, group_order) VALUES (:group_name, :description_public, :description_internal, :internal_info, :status, :hash_id, :access_code, NOW(), NOW(), :updater_id, :group_order)');
        // bind all VALUES
        $this->db->bind(':group_name', trim ($group_name));
        $this->db->bind(':description_public', trim ($description_public));
        $this->db->bind(':description_internal', trim ($description_internal));
        $this->db->bind(':internal_info', trim ($internal_info));
        $this->db->bind(':access_code', trim ($hash_access_code));
        $this->db->bind(':status', $status);
        $this->db->bind(':group_order', intval($group_order));
        // generate unique hash for this group
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($group_name.$appendix); // create hash id for this group
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
        $group_id = $this->checkGroupId($group_id); // checks group  id and converts group id to db group id if necessary (when group hash id was passed)

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
        $group_id = $this->checkGroupId($group_id); // checks group id and converts group id to db group id if necessary (when group hash id was passed)

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
        $group_id = $this->checkGroupId($group_id); // checks group id and converts group id to db group id if necessary (when group hash id was passed)

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
          $this->syslog->addSystemEvent(1, "Error changing description internal of group ".$group_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setGroupname($group_id, $group_name, $updater_id=0) {
        /* edits a group and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         group_name =  name of the group
        */
        $group_id = $this->checkGroupId($group_id); // checks group id and converts group id to db group id if necessary (when group hash id was passed)

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
      /* helper function that checks if a group id is a standard db id (int) or if a hash group id was passed
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
