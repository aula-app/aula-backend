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
        $au_rel_rooms_users ='au_rel_rooms_users';
        $au_rel_groups_users ='au_rel_groups_users';

        $this->$au_users_basedata = $au_users_basedata; // table name for user basedata
        $this->$au_rooms = $au_rooms; // table name for rooms
        $this->$au_groups = $au_groups; // table name for groups
        $this->$au_rel_rooms_users = $au_rel_rooms_users; // table name for relations room - user
        $this->$au_rel_groups_users = $au_rel_groups_users; // table name for relations group - user
    }// end function

    public function getUserBaseData($userid) {
      /* returns user base data for a specified db id */
      $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $userid); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]; // return an array (associative) with all the data for the user
      }
    }// end function

    public function getUserHashId($userid) {
      /* returns hash_id of a user for a integer user id
      */
      $stmt = $this->db->query('SELECT hash_id FROM '.$this->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $userid); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]['hash_id']; // return an array (associative) with all the data for the user
      }
    }// end function

    public function getUserIdByHashId($hashid) {
      /* Returns Database ID of user when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->au_users_basedata.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hashid); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]['id']; // return user id
      }
    }// end function


    public function getRoomIdByHashId($hashid) {
      /* Returns Database ID of room when only hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->au_rooms.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hashid); // bind userid
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $rooms[0]['id']; // return room db id
      }
    }// end function

    public function getGroupIdByHashId($hashid) {
      /* Returns Database ID of group when only hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->au_groups.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hashid); // bind userid
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $groups[0]['id']; // return group db id
      }
    }// end function


    public function addToRoom($userid, $roomid, $status, $updater_id) {
      /* adds a user to a room, accepts user_id (by hash or id) and room id (by hash or id)
      returns 1,1 = ok, 0,1 = user id not in db 0,2 room id not in db 0,3 user id not in db room id not in db */
      $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $roomid = $this->checkRoomId($roomid); // checks room id and converts room id to db room id if necessary (when room hash id was passed)
      // check if user and room exist
      $user_exist = $this->checkUserExist($userid);
      $room_exist = $this->checkRoomExist($roomid);

      if ($user_exist==1 && $room_exist==1) {
        // everything ok, user and room exists
        // add relation to database

        $stmt = $this->db->query('INSERT INTO '.$this->au_rel_rooms_users.' (room_id, user_id, status, created, last_update, updater_id) VALUES (:room_id, :user_id, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE room_id = :room_id, user_id = :user_id, status = :status, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':room_id', $roomid);
        $this->db->bind(':user_id', $userid);
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
          $this->syslog->addSystemEvent(0, "Added user ".$userid." to room ".$roomid, 0, "", 1);
          return "1,1,1"; // return error code 1 = successful

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding user ".$userid." to room ".$roomid, 0, "", 1);

          return "0,1,1"; // return 0 to indicate that there was an error executing the statement
        }

      }else {
        return "0,".$user_exist.",".$room_exist; // returns error and 0 or 1 for user and room (0=doesn't exist, 1=exists)
      }

      return "1,1,1"; // returns 1=ok/successful, user exists (1), room exists (1)

    } // end function


    public function addToGroup($userid, $groupid, $status, $updater_id) {
      /* adds a user to a room, accepts user_id (by hash or id) and room id (by hash or id)
      returns 1,1 = ok, 0,1 = user id not in db 0,2 group id not in db 0,3 user id not in db group id not in db */
      $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $groupid = $this->checkGroupId($groupid); // checks group id and converts room id to db room id if necessary (when room hash id was passed)
      // check if user and room exist
      $user_exist = $this->checkUserExist($userid);
      $group_exist = $this->checkGroupExist($groupid);

      if ($user_exist==1 && $group_exist==1) {
        // everything ok, user and room exists
        // add relation to database

        $stmt = $this->db->query('INSERT INTO '.$this->au_rel_groups_users.' (group_id, user_id, status, created, last_update, updater_id) VALUES (:group_id, :user_id, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE group_id = :group_id, user_id = :user_id, status = :status, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':group_id', $groupid);
        $this->db->bind(':user_id', $userid);
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
          $this->syslog->addSystemEvent(0, "Added user ".$userid." to group ".$groupid, 0, "", 1);
          return "1,1,1"; // return error code 1 = successful

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding user ".$userid." to group ".$groupid, 0, "", 1);

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



    public function checkUserExist($userid) {
      /* helper function to check if a user with a certain id exists, returns 0 if user does not exist, 1 if user exists, accepts database (int) or hash id (varchar)
      */
      $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT id FROM '.$this->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $userid); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // user found, return 1
      }
    } // end function

    private function checkRoomExist($roomid) {
      /* returns 0 if room does not exist, 1 if room exists, accepts databse id (int)
      */
      $roomid = $this->checkRoomId($roomid); // checks room id and converts user id to db room id if necessary (when room hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_rooms.' WHERE id = :id');
      $this->db->bind(':id', $roomid); // bind roomid
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // room found, return 1
      }
    } // end function

    private function checkGroupExist($groupid) {
      /* returns 0 if room does not exist, 1 if room exists, accepts databse id (int)
      */
      $groupid = $this->checkRoomId($groupid); // checks group id and converts user id to db group id if necessary (when room hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_groups.' WHERE id = :id');
      $this->db->bind(':id', $groupid); // bind groupid
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // room found, return 1
      }
    } // end function


    function getUsers($offset, $limit, $orderby=3, $asc=0, $status=1) {
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
        $userid = $users[0]['id']; // get user id from db
        return $userid; // return user id
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

    public function editUserData($userid, $realname, $displayname, $username, $email, $status, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         realname = actual name of the user, status = status of inserted user (0 = inactive, 1=active)
        */
        // query('UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?');
        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET realname = :realname , displayname= :displayname, username= :username, email = :email, status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':username', $this->crypt->encrypt($username));
        $this->db->bind(':realname', $this->crypt->encrypt($realname));
        $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
        $this->db->bind(':email', $this->crypt->encrypt($email));
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $userid); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Edited user ".$userid." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error while editing user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserStatus($userid, $status, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of inserted user (0 = inactive, 1=active)
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $userid); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User status changed ".$userid." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserLevel($userid, $userlevel=10, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         userlevel = level of the user (10 (guest)-50 (techadmin))
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $userlevel = intval (§userlevel);
        
        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET userlevel= :userlevel, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':userlevel', $userlevel);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $userid); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User status changed ".$userid." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserAbout($userid, $about, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a user
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $about = $this->crypt->encrypt(trim ($about)); // sanitize and encrypt about text

        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET about_me= :about, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':about', $about);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $userid); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User abouttext changed ".$userid." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing abouttext of user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserPosition($userid, $userposition, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a user
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $about = $this->crypt->encrypt(trim ($userposition)); // sanitize and encrypt position text

        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET position= :position, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':position', $userposition);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $userid); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User field position changed ".$userid." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing position of user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserRealname($userid, $realname, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         realname = actual name of the user
        */
        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET realname= :realname, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':realname', $this->crypt->encrypt($realname));
        $this->db->bind(':userid', $userid); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User real name changed ".$userid." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing real name of user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserDisplayname($userid, $displayname, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         displayname = shown name of the user in the system
        */
        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET displayname= :displayname, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
        $this->db->bind(':userid', $userid); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User display name changed ".$userid." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing display name of user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserEmail($userid, $email, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         email = email address of the user
        */
        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET email= :email, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':email', $this->crypt->encrypt($email));
        $this->db->bind(':userid', $userid); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User email changed ".$userid." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing email of user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setUserPW($userid, $pw, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         pw = pw in clear text
        */
        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET pw= :pw, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');

        // generate pw hash
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        // bind all VALUES
        $this->db->bind(':pw', $hash);
        $this->db->bind(':userid', $userid); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User pw changed ".$userid." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing pw of user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
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

    private function checkRoomId ($roomid) {
      /* helper function that checks if a room id is a standard db id (int) or if a hash roomid was passed
      if a hash was passed, function gets db room id and returns db id
      */

      if (is_int($roomid))
      {

        return $roomid;
      } else
      {

        return $this->getRoomIdByHashId ($roomid);
      }
    } // end function

    private function checkGroupId ($groupid) {
      /* helper function that checks if a group id is a standard db id (int) or if a hash group id was passed
      if a hash was passed, function gets db group id and returns db id
      */

      if (is_int($groupid))
      {

        return $groupid;
      } else
      {

        return $this->getGroupIdByHashId ($groupid);
      }
    } // end function

    public function setUserRegStatus($userid, $regstatus, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         regstatus (int) sets user registration status
        */
        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_users_basedata.' SET registration_status= :regstatus, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');

        // bind all VALUES
        $this->db->bind(':regstatus', $regstatus);
        $this->db->bind(':userid', $userid); // user that is updated
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
          $this->syslog->addSystemEvent(0, "User reg status changed ".$userid." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing reg status of user ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function deleteUser($userid, $updater_id=0) {
        /* deletes user and returns the number of rows (int) accepts user id or user hash id // */

        $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->au_users_basedata.' WHERE id = :id');
        $this->db->bind (':id', $userid);
        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User deleted with id ".$userid." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting user with id ".$userid." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }

    }// end function

}
?>
