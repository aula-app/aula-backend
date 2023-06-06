<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}


class User {
    private $db;

    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->crypt = $crypt;
        $this->syslog = $syslog;

        $au_users_basedata = 'au_users_basedata';
        $au_rooms = 'au_rooms';
        $au_groups = 'au_groups';
        $au_votes = 'au_votes';
        $au_delegation = 'au_delegation';

        $au_rel_rooms_users ='au_rel_rooms_users';
        $au_rel_groups_users ='au_rel_groups_users';
        $au_rel_user_user ='au_rel_user_user';

        $this->$au_users_basedata = $au_users_basedata; // table name for user basedata
        $this->$au_rooms = $au_rooms; // table name for rooms
        $this->$au_groups = $au_groups; // table name for groups
        $this->$au_votes = $au_votes; // table name for votes
        $this->$au_delegation = $au_delegation; // table name for delegation
        $this->$au_rel_rooms_users = $au_rel_rooms_users; // table name for relations room - user
        $this->$au_rel_groups_users = $au_rel_groups_users; // table name for relations group - user
        $this->$au_rel_user_user = $au_rel_user_user;
    }// end function

    public function getUserBaseData($user_id) {
      /* returns user base data for a specified db id */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]; // return an array (associative) with all the data for the user
      }
    }// end function

    public function getUserHashId($user_id) {
      /* returns hash_id of a user for a integer user id
      */
      $stmt = $this->db->query('SELECT hash_id FROM '.$this->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]['hash_id']; // return an array (associative) with all the data for the user
      }
    }// end function

    public function getUserIdByHashId($hash_id) {
      /* Returns Database ID of user when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->au_users_basedata.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]['id']; // return user id
      }
    }// end function

    public function revokeVoteRight($user_id, $user_id_target, $room_id, $updater_id) {
      /* Returns Database ID of user when hash_id is provided
      */
      //sanitize variables
      $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $user_id_target = $this->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)


      $stmt = $this->db->query('SELECT room_id FROM '.$this->au_delegation.' WHERE room_id = :room_id AND user_id_original = :user_id AND user_id_target = :user_id_target');
      // bind all VALUES
      $this->db->bind(':room_id', $room_id);
      $this->db->bind(':user_id', $user_id); // gives the voting right
      $this->db->bind(':user_id_target', $user_id_target); // receives the voting right

      $users = $this->db->resultSet();
      if (count($users)<1){
        return "0,1"; // nothing found (no delegation), return 0,1 code
      }else {
        // remove delegation from db table
        $stmt = $this->db->query('DELETE FROM '.$this->au_delegation.' WHERE room_id = :room_id AND user_id_original = :user_id AND user_id_target = :user_id_target');
        // bind all VALUES
        $this->db->bind(':room_id', $room_id);
        $this->db->bind(':user_id', $user_id); // gives the voting right
        $this->db->bind(':user_id_target', $user_id_target); // receives the voting right

        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Delegation deleted for user id ".$user_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting dlegation for user with id ".$user_id." by ".$updater_id, 0, "", 1);
          return "0,0"; // return 0 to indicate that there was an error executing the statement
        }

        return "1,1"; // return ok
      }
    }// end function


    public function delegateVoteRight ($user_id, $user_id_target, $room_id, $updater_id) {
      /* delegates voting rights from one user to another within a room, accepts user_id (by hash or id) and room id (by hash or id)
      returns 1,1 = ok, 0,1 = user id not in db 0,2 room id not in db 0,3 user id not in db room id not in db */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $user_id_target = $this->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)
      // check if user and room exist
      $user_exist = $this->checkUserExist($user_id);
      $user_exist_target = $this->checkUserExist($user_id_target);
      $room_exist = $this->checkRoomExist($room_id);

      if ($user_exist==1 && $room_exist==1 && $user_exist_target==1) {
        // everything ok, users and room exists
        // add relation to database (delegation)

        $stmt = $this->db->query('INSERT INTO '.$this->au_delegation.' (room_id, user_id_original, user_id_target, status, created, last_update, updater_id) VALUES (:room_id, :user_id, :user_id_target, 1, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE room_id = :room_id, user_id_original = :user_id, user_id_target = :user_id_target, status = 1, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':room_id', $room_id);
        $this->db->bind(':user_id', $user_id); // gives the voting right
        $this->db->bind(':user_id_target', $user_id_target); // receives the voting right
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
          $this->syslog->addSystemEvent(0, "Added user ".$user_id." to room ".$room_id, 0, "", 1);
          return "1,1,1"; // return error code 1 = successful

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding user ".$user_id." to room ".$room_id, 0, "", 1);

          return "0,1,1"; // return 0 to indicate that there was an error executing the statement
        }

      }else {
        return "0,".$user_exist.",".$user_exist_target.",".$room_exist; // returns error and 0 or 1 for user and room (0=doesn't exist, 1=exists)
      }

      return "1,1,1"; // returns 1=ok/successful, user exists (1), room exists (1)

    } // end function


    public function getRoomIdByHashId($hash_id) {
      /* Returns Database ID of room when only hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->au_rooms.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind userid
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $rooms[0]['id']; // return room db id
      }
    }// end function

    public function getGroupIdByHashId($hash_id) {
      /* Returns Database ID of group when only hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->au_groups.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind userid
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $groups[0]['id']; // return group db id
      }
    }// end function

    public function setDelegationStatus ($user_id, $status, $room_id = 0, $target = 0) {
        /* edits the status of a delegation and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status for delegation (0 = inactive, 1=active, 2 = suspended, 4 = archived)
         if room_id = 0 all delegations of the user are deleted
         target specifies if original or target users are adressed 0 = remove delegations of delegating user (original owner->default) 1= remove delegation of target user
        */

        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

        $room_clause = "";

        if ($room_id > 0){
          // room id is set to >0 -> delete only delegations for this user in the specified room
          $room_clause = " AND room_id = ".$room_id;
        }

        $target_user = "user_id_original";
        if ($target>0) {
          $target_user = "user_id_target";
        }

        $stmt = $this->db->query('UPDATE '.$this->au_delegation.' SET status = :status, last_update = NOW() WHERE '.$target_user.' = :user_id'.$room_clause);
        // bind all VALUES
        $this->db->bind(':status', $status);

        $this->db->bind(':user_id', $user_id); // user original id that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Delegation status changed ".$user_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing delegation status of user ".$user_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function getReceivedDelegations ($user_id, $room_id) {
      /* returns received delegations for a specific user (user_id) in the room
      */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_users_basedata.' LEFT JOIN '.$this->au_delegation.' ON ('.$this->au_users_basedata.'.id = '.$this->au_delegation.'.user_id_original) WHERE '.$this->au_delegation.'.user_id_target = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users; // return an array (associative) with all the data for the user
      }
    } // end function


    public function getGivenDelegations ($user_id, $room_id) {
      /* returns received delegations for a specific user (user_id) in the room
      */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_users_basedata.' LEFT JOIN '.$this->au_delegation.' ON ('.$this->au_users_basedata.'.id = '.$this->au_delegation.'.user_id_target) WHERE '.$this->au_delegation.'.user_id_original = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users; // return an array (associative) with all the data for the user
      }
    } // end function


    public function giveBackAllDelegations ($user_id, $room_id = 0){
      // give back all delegations for a) a certain room (room id>0) or all delegations (room_id=0)
      return $this->removeUserDelegations ($user_id, $room_id, 1); // 1 at the end indicates that target user is meant
    }

    public function giveBackDelegation ($my_user_id, $user_id_original, $room_id = 0){
      // give back delegations from a certain user ($user_id_original) for a) a certain room (room id>0) or all delegations (room_id=0)
      return $this->removeSpecificDelegation ($my_user_id, $user_id_original, $room_id); // 1 at the end indicates that target user is meant
    }

    public function removeSpecificDelegation ($user_id_target, $user_id_original, $room_id = 0){
      /* remove delegation from a specific user A (user_id_original) to a specific user B (user_id_target) for
      a) a certain room (room id>0) or all delegations (room_id=0), defaults to all rooms
      */
      $user_id_target = $this->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $user_id_original = $this->checkUserId($user_id_original); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

      $room_clause = "";

      if ($room_id > 0){
        // room id is set to >0 -> delete only delegations for this user in the specified room
        $room_clause = " AND room_id = ".$room_id;
      }

      $stmt = $this->db->query('DELETE FROM '.$this->au_delegation.' WHERE user_id_target = :user_id_target AND user_id_original = :user_id_original'.$room_clause);
      $this->db->bind (':user_id_target', $user_id_original);
      $this->db->bind (':user_id_original', $user_id_target);

      $err=false;
      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {
          echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
          $err=true;
      }
      if (!$err)
      {
        $this->syslog->addSystemEvent(0, "User delegation(s) deleted with id ".$user_id." for room ".$room_id, 0, "", 1);
        return ("1,".intval ($this->db->rowCount())); // return number of affected rows to calling script
      } else {
        $this->syslog->addSystemEvent(1, "Error deleting user delegation(s) with id ".$user_id." for room ".$room_id, 0, "", 1);
        return "0,0"; // return 0 to indicate that there was an error executing the statement
      }

    }

    public function removeUserDelegations ($user_id, $room_id = 0, $target = 0)
    {
      /* removes all delegations of a specified user (user id) for a specified room, accepts db id or hash id
       if room_id = 0 all delegations of the user are deleted
       target specifies if original or target users are adressed 0 = remove delegations of delegating user (original owner->default) 1= remove delegation of target
      */

      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

      $room_clause = "";

      if ($room_id > 0){
        // room id is set to >0 -> delete only delegations for this user in the specified room
        $room_clause = " AND room_id = ".$room_id;
      }

      $target_user = "user_id_original";
      if ($target>0) {
        $target_user = "user_id_target";
      }

      $stmt = $this->db->query('DELETE FROM '.$this->au_delegation.' WHERE '.$target_user.' = :id'.$room_clause);
      $this->db->bind (':id', $user_id);
      $err=false;
      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {
          echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
          $err=true;
      }
      if (!$err)
      {
        $this->syslog->addSystemEvent(0, "User delegation(s) deleted with id ".$user_id." for room ".$room_id, 0, "", 1);
        return "1,".intval ($this->db->rowCount()); // return number of affected rows to calling script
      } else {
        $this->syslog->addSystemEvent(1, "Error deleting user delegation(s) with id ".$user_id." for room ".$room_id, 0, "", 1);
        return "0,0"; // return 0 to indicate that there was an error executing the statement
      }

    } // end function


    public function addToRoom($user_id, $room_id, $status, $updater_id) {
      /* adds a user to a room, accepts user_id (by hash or id) and room id (by hash or id)
      returns 1,1 = ok, 0,1 = user id not in db 0,2 room id not in db 0,3 user id not in db room id not in db */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)
      // check if user and room exist
      $user_exist = $this->checkUserExist($user_id);
      $room_exist = $this->checkRoomExist($room_id);

      if ($user_exist==1 && $room_exist==1) {
        // everything ok, user and room exists
        // add relation to database

        $stmt = $this->db->query('INSERT INTO '.$this->au_rel_rooms_users.' (room_id, user_id, status, created, last_update, updater_id) VALUES (:room_id, :user_id, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE room_id = :room_id, user_id = :user_id, status = :status, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':room_id', $room_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':status', $status);
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
          $this->syslog->addSystemEvent(0, "Added user ".$user_id." to room ".$room_id, 0, "", 1);
          return "1,1,1"; // return error code 1 = successful

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding user ".$user_id." to room ".$room_id, 0, "", 1);

          return "0,1,1"; // return 0 to indicate that there was an error executing the statement
        }

      }else {
        return "0,".$user_exist.",".$room_exist; // returns error and 0 or 1 for user and room (0=doesn't exist, 1=exists)
      }

      return "1,1,1"; // returns 1=ok/successful, user exists (1), room exists (1)

    } // end function

    public function followUser ($user_id, $user_id_target) {
      return $this->relateUser ($user_id, $user_id_target, 1, 0, 1);
    }

    public function friendUser ($user_id, $user_id_target) {
      return $this->relateUser ($user_id, $user_id_target, 1, 0, 2);
    }

    public function blockUser ($user_id, $user_id_target) {
      return $this->relateUser ($user_id, $user_id_target, 1, 0, 0);
    }

    public function unfriendUser ($user_id, $user_id_target) {
      return $this->removeUserRelation ($user_id, $user_id_target);
    }

    public function unblockUser ($user_id, $user_id_target) {
      return $this->removeUserRelation ($user_id, $user_id_target);
    }

    public function unfollowUser ($user_id, $user_id_target) {
      return $this->removeUserRelation ($user_id, $user_id_target);
    }

    public function relateUser($user_id, $user_id_target, $status=1, $updater_id=0, $type=1) {
      /*
      user A (user_id) follows user B (user_id_target), type = 1 => follow /  type = 2 => friend / type = 0 => blocked
       */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $user_id_target = $this->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      // check if user and room exist
      $user_exist = $this->checkUserExist($user_id);
      $user_exist_target = $this->checkUserExist($user_id_target);

      if ($user_exist==1 && $user_exist_target==1) {
        // everything ok, both users exist
        // add relation to database

        $stmt = $this->db->query('INSERT INTO '.$this->au_rel_user_user.' (user_id1, user_id2, type, status, created, last_update, updater_id) VALUES (:user_id1, :user_id2, :type, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE user_id1 = :user_id1, user_id2 = :user_id2, type = :type, status = :status, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':user_id1', $user_id);
        $this->db->bind(':user_id2', $user_id_target);
        $this->db->bind(':status', $status);
        $this->db->bind(':type', $type);
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
          $this->syslog->addSystemEvent(0, "Added user relation (follow) ".$user_id."-".$user_id_target, 0, "", 1);
          return "1,1,1"; // return error code 1 = successful

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding user relation (follow) ".$user_id, 0, "", 1);

          return "0,1,1"; // return 0 to indicate that there was an error executing the statement
        }

      }else {
        return "0,".$user_exist.",".$user_exist_target; // returns error and 0 or 1 for user and room (0=doesn't exist, 1=exists)
      }

      return "1,1,1"; // returns 1=ok/successful, user exists (1), room exists (1)

    } // end function

    public function removeUserRelation($user_id, $user_id_target) {
      /* deletes a user relation form the db
      */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $user_id_target = $this->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('DELETE FROM '.$this->au_rel_user_user.' WHERE user_id1 = :user_id1 AND user_id2 = :user_id2' );
      $this->db->bind(':user_id1', $user_id); // bind user id
      $this->db->bind(':user_id2', $user_id_target); // bind user id

      $err=false;
      try {
        $users = $this->db->execute();
        $rowcount = $this->db->rowCount();

      } catch (Exception $e) {
          echo 'Error occured while removing relation between user '.$user_id.' and user '.$user_id_target,  $e->getMessage(), "\n"; // display error
          $this->syslog->addSystemEvent(0, "Error while removing user relation (delete from db) ".$user_id."-".$user_id_target, 0, "", 1);
          $err=true;
          return "0,0";
      }
      $this->syslog->addSystemEvent(0, "Removed user relation (delete from db) ".$user_id."-".$user_id_target, 0, "", 1);
      return "1,".$rowcount; // return number of affected rows to calling script

    }// end function



    public function addToGroup($user_id, $group_id, $status, $updater_id) {
      /* adds a user to a room, accepts user_id (by hash or id) and room id (by hash or id)
      returns 1,1 = ok, 0,1 = user id not in db 0,2 group id not in db 0,3 user id not in db group id not in db */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $group_id = $this->checkGroupId($group_id); // checks group id and converts room id to db room id if necessary (when room hash id was passed)
      // check if user and room exist
      $user_exist = $this->checkUserExist($user_id);
      $group_exist = $this->checkGroupExist($group_id);

      if ($user_exist==1 && $group_exist==1) {
        // everything ok, user and room exists
        // add relation to database

        $stmt = $this->db->query('INSERT INTO '.$this->au_rel_groups_users.' (group_id, user_id, status, created, last_update, updater_id) VALUES (:group_id, :user_id, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE group_id = :group_id, user_id = :user_id, status = :status, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':group_id', $group_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':status', $status);
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
          $this->syslog->addSystemEvent(0, "Added user ".$user_id." to group ".$group_id, 0, "", 1);
          return "1,1,1"; // return error code 1 = successful

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding user ".$user_id." to group ".$group_id, 0, "", 1);

          return "0,1,1"; // return 0 to indicate that there was an error executing the statement
        }

      }else {
        return "0,".$user_exist.",".$group_exist; // returns error and 0 or 1 for user and group (0=doesn't exist, 1=exists)
      }

      return "1,1,1"; // returns 1=ok/successful, user exists (1), group exists (1)

    } // end function

    public function checkCredentials($username, $pw) { // pw = clear text
      /* checks credentials and returns database user id (credentials correct) or 0 (credentials not correct)
      username is clear text
      pw is clear text
      */

      // create temp blind index
      $bi = md5(strtolower($username));

      $stmt = $this->db->query('SELECT id,username,pw,hash_id FROM '.$this->au_users_basedata.' WHERE bi= :bi');
      $this->db->bind(':bi', $bi); // blind index
      $users = $this->db->resultSet();


      if (count($users)<1){
        return 0;
      } // nothing found or empty database

      // new
      $dbpw = $users[0]['pw'];
      // check PASSWORD
      if (password_verify($pw, $dbpw))
      {
        return $users[0]['id'];
      }else {

        return 0;
      }

      /*foreach ($users as $user) {
          $decrypted_username = $this->crypt->decrypt ($user['username']);
          // check if match
          if (strcmp($decrypted_username,$username)==0)
          {
            $dbpw = $user['pw'];
            // check PASSWORD
            if (password_verify($pw, $dbpw))
            {
              return $user['id'];
            }else {

              return 0;
            }
          } // end if (strcmp....)

      } // end foreach */

        $this->syslog->addSystemEvent(1, "Credentials: username not in db: ".$username, 0, "", 1);
        return 0;
    }// end function



    public function checkUserExist($user_id) {
      /* helper function to check if a user with a certain id exists, returns 0 if user does not exist, 1 if user exists, accepts database (int) or hash id (varchar)
      */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT id FROM '.$this->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // user found, return 1
      }
    } // end function

    private function checkRoomExist($room_id) {
      /* returns 0 if room does not exist, 1 if room exists, accepts databse id (int)
      */
      $room_id = $this->checkRoomId($room_id); // checks room id and converts user id to db room id if necessary (when room hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_rooms.' WHERE id = :id');
      $this->db->bind(':id', $room_id); // bind roomid
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // room found, return 1
      }
    } // end function

    private function checkGroupExist($group_id) {
      /* returns 0 if room does not exist, 1 if room exists, accepts databse id (int)
      */
      $group_id = $this->checkRoomId($group_id); // checks group id and converts user id to db group id if necessary (when room hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_groups.' WHERE id = :id');
      $this->db->bind(':id', $group_id); // bind groupid
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // room found, return 1
      }
    } // end function


    public function getUsers($offset, $limit, $orderby=3, $asc=0, $status=1) {
      /* returns userlist (associative array) with start and limit provided
      */
      // init vars
      $orderby_field="";
      $asc_field ="";

      $limit_string=" LIMIT :offset , :limit ";
      $limit_active=true;

      // check if offset an limit are both set to 0, then show whole list (exclude limit clause)
      if ($offset==0 && $limit==0){
        $limit_string="";
        $limit_active=false;
      }

      switch (intval ($orderby)){
        case 0:
        $orderby_field = "registration_status";
        break;
        case 1:
        $orderby_field = "realname";
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
        case 5:
        $orderby_field = "username";
        break;
        case 6:
        $orderby_field = "status";
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

      $stmt = $this->db->query('SELECT * FROM '.$this->au_users_basedata.' WHERE status= :status ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);

      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $users = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting users: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users; // return an array (associative) with all the data
      }
    }// end function

    public function checkUserExistsByUsername($username){
      // checks if a group with this name is already in database
      // generate blind index
      $bi = md5 (strtolower (trim ($username)));

      $stmt = $this->db->query('SELECT id FROM '.$this->au_users_basedata.' WHERE bi = :bi');
      $this->db->bind(':bi', $bi); // bind blind index
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        $user_id = $users[0]['id']; // get user id from db
        return $user_id; // return user id
      }
    }



    public function addUser($realname, $displayname, $username, $email, $password, $status, $updater_id=0, $userlevel=10) {
        /* adds a user and returns insert id (userid) if successful, accepts the above parameters
         realname = actual name of the user, status = status of inserted user (0 = inactive, 1=active)
         userlevel = Rights level for the user 10=guest, 20 = standard, 30 =moderator 40 = super mod 50 = admin 60 = tech admin
        */

        // sanitize vars
        $realname = trim ($realname);
        $displayname = trim ($displayname);
        $username = trim ($username);
        $email = trim ($email);
        $password = trim ($password);
        $updater_id = intval ($updater_id);
        $status = intval($status);
        $userlevel = intval ($userlevel);


        // check if user name is still available
        $temp_user_id = $this->checkUserExistsByUsername($username);
        if ($temp_user_id>0){
          return "0,1,".$temp_user_id; // user exists, stop exectuing, return errorcode 1 = user exists, returning userid
        }


        // generate hash password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // generate blind index
        $bi = md5 (strtolower (trim ($username)));

        $stmt = $this->db->query('INSERT INTO '.$this->au_users_basedata.' (realname, displayname, username, email, pw, status, hash_id, created, last_update, updater_id, bi, userlevel) VALUES (:realname, :displayname, :username, :email, :password, :status, :hash_id, NOW(), NOW(), :updater_id, :bi, :userlevel)');
        // bind all VALUES
        $this->db->bind(':username', $this->crypt->encrypt($username));
        $this->db->bind(':realname', $this->crypt->encrypt($realname));
        $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
        $this->db->bind(':email', $this->crypt->encrypt($email));
        $this->db->bind(':password', $hash);
        $this->db->bind(':bi', $bi);
        $this->db->bind(':userlevel', $userlevel);
        $this->db->bind(':status', $status);
        // generate unique hash for this user
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($username.$appendix); // create hash id for this user
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
          $this->syslog->addSystemEvent(0, "Added new user ".$insertid, 0, "", 1);
          return $insertid; // return insert id to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error adding user ", 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function editUserData($user_id, $realname, $displayname, $username, $email, $status, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         realname = actual name of the user, status = status of inserted user (0 = inactive, 1=active)
        */
        // query('UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?');
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET realname = :realname , displayname= :displayname, username= :username, email = :email, status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':username', $this->crypt->encrypt($username));
        $this->db->bind(':realname', $this->crypt->encrypt($realname));
        $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
        $this->db->bind(':email', $this->crypt->encrypt($email));
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Edited user ".$user_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error while editing user ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserStatus($user_id, $status, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of inserted user (0 = inactive, 1=active)
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User status of ".$user_id." changed to ".$status." by ".$updater_id, 0, "", 1);

          // set delegations for this user to suspended (delegated voting right and received votign right)
          $this->setDelegationStatus ($user_id, $status, 0, 0);
          $this->setDelegationStatus ($user_id, $status, 0, 1);

          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of user ".$user_id." to ".$status." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function suspendUser ($user_id, $updater_id=0){
      // set user status to 2 = suspended

      // set delegations for this user to suspended (delegated voting right and received votign right)

      return setUserStatus ($user_id, 2, $updater_id);
    }

    public function activateUser ($user_id, $updater_id=0){
      // set user status to 1 = active
      // set delegations for this user to suspended (delegated voting right and received votign right)

      return setUserStatus ($user_id, 1, $updater_id);
    }

    public function deactivateUser ($user_id, $updater_id=0){
      // set user status to 0 = inactive

      // set delegations for this user to suspended (delegated voting right and received votign right)

      return setUserStatus ($user_id, 0, $updater_id);
    }

    public function archiveUser ($user_id, $updater_id=0){
      // set user status to 3 = archived
      // set delegations for this user to suspended (delegated voting right and received votign right)

      return setUserStatus ($user_id, 3, $updater_id);
    }



    public function setUserLevel($user_id, $userlevel=10, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         userlevel = level of the user (10 (guest)-50 (techadmin))
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $userlevel = intval (§userlevel);

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET userlevel= :userlevel, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':userlevel', $userlevel);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User status changed ".$user_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of user ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserAbout($user_id, $about, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a user
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $about = $this->crypt->encrypt(trim ($about)); // sanitize and encrypt about text

        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET about_me= :about, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':about', $about);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User abouttext changed ".$user_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing abouttext of user ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserPosition($user_id, $userposition, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a user
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $about = $this->crypt->encrypt(trim ($userposition)); // sanitize and encrypt position text

        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET position= :position, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':position', $userposition);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User field position changed ".$user_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing position of user ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserRealname($user_id, $realname, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         realname = actual name of the user
        */
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET realname= :realname, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':realname', $this->crypt->encrypt($realname));
        $this->db->bind(':userid', $user_id); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User real name changed ".$user_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing real name of user ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserDisplayname($user_id, $displayname, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         displayname = shown name of the user in the system
        */
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET displayname= :displayname, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
        $this->db->bind(':userid', $user_id); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User display name changed ".$user_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing display name of user ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserEmail($user_id, $email, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         email = email address of the user
        */
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET email= :email, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':email', $this->crypt->encrypt($email));
        $this->db->bind(':userid', $user_id); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User email changed ".$user_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing email of user ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserPW($user_id, $pw, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         pw = pw in clear text
        */
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET pw= :pw, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');

        // generate pw hash
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        // bind all VALUES
        $this->db->bind(':pw', $hash);
        $this->db->bind(':userid', $user_id); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User pw changed ".$user_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing pw of user ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    private function checkUserId ($user_id) {
      /* helper function that checks if a user id is a standard db id (int) or if a hash userid was passed
      if a hash was passed, function gets db user id and returns db id
      */

      if (is_int($user_id))
      {
        return $user_id;
      } else
      {

        return $this->getUserIdByHashId ($user_id);
      }
    } // end function

    private function checkRoomId ($room_id) {
      /* helper function that checks if a room id is a standard db id (int) or if a hash roomid was passed
      if a hash was passed, function gets db room id and returns db id
      */

      if (is_int($room_id))
      {

        return $room_id;
      } else
      {

        return $this->getRoomIdByHashId ($room_id);
      }
    } // end function

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

    public function setUserRegStatus($user_id, $regstatus, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         regstatus (int) sets user registration status
        */
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET registration_status= :regstatus, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');

        // bind all VALUES
        $this->db->bind(':regstatus', $regstatus);
        $this->db->bind(':userid', $user_id); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User reg status changed ".$user_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing reg status of user ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function deleteUser($user_id, $updater_id=0) {
        /* deletes user and returns the number of rows (int) accepts user id or user hash id // */

        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->au_users_basedata.' WHERE id = :id');
        $this->db->bind (':id', $user_id);
        $err=false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval ($this->db->rowCount());

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          // remove all delegations for this user
          $this->removeUserDelegations ($user_id, 0, 0); // active delegations (original user)
          $this->removeUserDelegations ($user_id, 0, 1); // passive delegations (target user)

          $this->syslog->addSystemEvent(0, "User deleted with id ".$user_id." by ".$updater_id, 0, "", 1);
          return $rows_affected; // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting user with id ".$user_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }

    }// end function

}
?>
