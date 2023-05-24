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
        $this->$au_users_basedata = $au_users_basedata; // table name for user basedata
    }// end function

    public function getUserBaseData($userid) {
      /* returns user base data for a specified db id */
      $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed) // checks user id and converts user id to db user id if necessary (when user hash id was passed)

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


    public function checkCredentials($username, $pw) { // pw = clear text
      /* checks credentials and returns database user id (credentials correct) or 0 (credentials not correct)
      username is clear text
      pw is clear text
      */

      $stmt = $this->db->query('SELECT id,username,pw,hash_id FROM '.$this->au_users_basedata);
      $users = $this->db->resultSet();

      if (count($users)<1){
        return 0;
      } // nothing found or empty database
      foreach ($users as $user) {
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

      } // end foreach
        $this->syslog->addSystemEvent(1, "Credentials: username not in db: ".$username, 0, "", 1);
        return 0;
    }// end function



    public function checkUserExist($userid) {
      /* returns 0 if user does not exist, 1 if user exists, accepts databse id (int)
      */
      $userid = $this->checkUserId($userid); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $userid); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // user found, return 1
      }
    } // end function


    function getUsers($offset, $limit) {
      /* returns userlist (associative array) with start and limit provided
      */
      $stmt = $this->db->query('SELECT * FROM '.$this->au_users_basedata.' LIMIT :offset , :limit');
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
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

    public function addUser($realname, $displayname, $username, $email, $password, $status, $updater_id=0) {
        /* adds a user and returns insert id (userid) if successful, accepts the above parameters
         realname = actual name of the user, status = status of inserted user (0 = inactive, 1=active)
        */
        // generate hash password
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->query('INSERT INTO '.$this->au_users_basedata.' (realname, displayname, username, email, pw, status, hash_id, created, last_update, updater_id) VALUES (:realname, :displayname, :username, :email, :password, :status, :hash_id, NOW(), NOW(), :updater_id)');
        // bind all VALUES
        $this->db->bind(':username', $this->crypt->encrypt($username));
        $this->db->bind(':realname', $this->crypt->encrypt($realname));
        $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
        $this->db->bind(':email', $this->crypt->encrypt($email));
        $this->db->bind(':password', $hash);
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

    public function setUserAbout($userid, $about, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a user
         updater_id is the id of the user that commits the update (i.E. admin )
        */
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
