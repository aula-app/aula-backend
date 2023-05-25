<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}


class Room {
    private $db;

    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->crypt = $crypt;
        $this->syslog = $syslog;

        $au_rooms = 'au_rooms';
        $this->$au_rooms = $au_rooms; // table name for rooms
    }// end function

    public function getRoomBaseData($room_id) {
      /* returns user base data for a specified db id */
      $room_id = $this->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_rooms.' WHERE id = :id');
      $this->db->bind(':id', $room_id); // bind room id
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $rooms[0]; // return an array (associative) with all the data for the room
      }
    }// end function



    public function getRoomHashId($room_id) {
      /* returns hash_id of a room for a integer room id
      */
      $stmt = $this->db->query('SELECT hash_id FROM '.$this->au_rooms.' WHERE id = :id');
      $this->db->bind(':id', $room_id); // bind room id
      $room_id = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $rooms[0]['hash_id']; // return an array (associative) with all the data for the room
      }
    }// end function

    public function getRoomIdByHashId($hashid) {
      /* Returns Database ID of room when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->au_rooms.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hashid); // bind hash id
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $rooms[0]['id']; // return room id
      }
    }// end function


    public function checkAccesscode($room_id, $access_code) { // access_code = clear text
      /* checks access code and returns database room id (credentials correct) or 0 (credentials not correct)
      */
      $stmt = $this->db->query('SELECT room_name, id,access_code,hash_id FROM '.$this->au_rooms.' WHERE id= :id');
      $this->db->bind(':id', $room_id); // bind room id

      $rooms = $this->db->resultSet();

      if (count($rooms)<1){
        return 0;
      } // nothing found or empty database

      foreach ($rooms as $room) {
          $db_access_code = $room['access_code'];
          if (password_verify($access_code, $db_access_code))
          {
            return $room['id'];
          }else {

            return 0;
          }
      } // end foreach
        $this->syslog->addSystemEvent("Room access code incorrect: ".$room['room_name'], 0, "", 1);
        return 0;
    }// end function



    public function checkRoomExist($room_id) {
      /* returns 0 if room does not exist, 1 if room exists, accepts databse id (int)
      */
      $room_id = $this->checkRoomId($room_id); // checks room id and converts user id to db room id if necessary (when room hash id was passed)

      $stmt = $this->db->query('SELECT id FROM '.$this->au_rooms.' WHERE id = :id');
      $this->db->bind(':id', $room_id); // bind room id
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // user found, return 1
      }
    } // end function


    function getRooms($offset, $limit, $orderby=3, $asc=0, $status=1) {
      /* returns roomlist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=susepended, 3=archived, defaults to active (1)
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
        $orderby_field = "room_name";
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

      $stmt = $this->db->query('SELECT * FROM '.$this->au_rooms.' WHERE status= :status ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $rooms = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting rooms: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $rooms; // return an array (associative) with all the data
      }
    }// end function

    private function checkRoomExistsByName($room_name){
      // checks if a room with this name is already in database
      $room_name=trim ($room_name); // trim spaces

      $stmt = $this->db->query('SELECT id FROM '.$this->au_rooms.' WHERE room_name = :room_name');
      $this->db->bind(':room_name', $room_name); // bind room id
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; //  return 1 = exists
      }
    }

    public function addRoom($room_name, $description_public, $description_internal, $internal_info, $status, $access_code, $restricted, $room_order=10, $updater_id=0) {
        /* adds a new room and returns insert id (room id) if successful, accepts the above parameters
         description_public = actual description of the room, status = status of inserted room (0 = inactive, 1=active)
        */

        $hash_access_code = password_hash(trim ($access_code), PASSWORD_DEFAULT); // hash access code
        //sanitize in vars
        $restricted = intval($restricted);
        $updater_id = intval ($updater_id);
        $status = intval($status);
        $room_order = intval ($room_order);
        $room_name = trim ($room_name);
        if ($restricted>0){
          $restricted=1;
        }

        // check if room name is still available
        if ($this->checkRoomExistsByName($room_name)>0){
          return "0,1"; // room exists, stop exectuing, return errorcode 1 = room exists
        }

        $stmt = $this->db->query('INSERT INTO '.$this->au_rooms.' (room_name, description_public, description_internal, internal_info, status, hash_id, access_code, created, last_update, updater_id, restrict_to_roomusers_only, roomorder) VALUES (:room_name, :description_public, :description_internal, :internal_info, :status, :hash_id, :access_code, NOW(), NOW(), :updater_id, :restricted, :roomorder)');
        // bind all VALUES

        $this->db->bind(':room_name', trim ($room_name));
        $this->db->bind(':description_public', trim ($description_public));
        $this->db->bind(':description_internal', trim ($description_internal));
        $this->db->bind(':internal_info', trim ($internal_info));
        $this->db->bind(':access_code', $hash_access_code);
        $this->db->bind(':status', $status);
        $this->db->bind(':restricted', $restricted);
        // generate unique hash for this user
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($room_name.$appendix); // create hash id for this user
        $this->db->bind(':hash_id', $hash_id);
        $this->db->bind(':roomorder', $room_order); // order parameter
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
          $this->syslog->addSystemEvent(0, "Added new room (#".$insertid.") ".$room_name, 0, "", 1);
          return $insertid; // return insert id to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error adding room ".$room_name, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function


    public function setRoomStatus($room_id, $status, $updater_id=0) {
        /* edits a room and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of inserted room (0 = inactive, 1=active)
         updater_id is the id of the room that commits the update (i.E. admin )
        */
        $room_id = $this->checkRoomId($room_id); // checks room  id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_rooms.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':room_id', $room_id); // room that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Room status changed ".$room_id." by ".$updater_id, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of room ".$room_id." by ".$updater_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function setRoomDescriptionPublic($room_id, $about, $updater_id=0) {
        /* edits a room and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a room
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $room_id = $this->checkRoomId($room_id); // checks room id and converts user id to db room id if necessary (when room hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_rooms.' SET description_public= :about, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
        // bind all VALUES
        $this->db->bind(':about', $about);
        $this->db->bind(':updater_id', $updater_id); // id of the room doing the update (i.e. admin)

        $this->db->bind(':room_id', $room_id); // room that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Room description public changed ".$room_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing room description (public) ".$room_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setRoomDescriptionInternal($room_id, $about, $updater_id=0) {
        /* edits a room and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a room
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $room_id = $this->checkRoomId($room_id); // checks room id and converts user id to db room id if necessary (when room hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_rooms.' SET description_internal= :about, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
        // bind all VALUES
        $this->db->bind(':about', $about);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':room_id', $room_id); // room that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Room description internal changed ".$room_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing room description internal ".$room_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setRoomname($room_id, $room_name, $updater_id=0) {
        /* edits a room and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         room_name = name of the room
        */
        $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db user id if necessary (when room hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_rooms.' SET room_name= :room_name, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
        // bind all VALUES
        $this->db->bind(':room_name', $room_name);
        $this->db->bind(':room_id', $room_id); // room that is updated
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
          $this->syslog->addSystemEvent(0, "Room name changed ".$room_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing name of room ".$room_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setRoomAccesscode($room_id, $access_code, $updater_id=0) {
        /* edits a room and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         access_code = access code in clear text
        */
        $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_rooms.' SET access_code= :access_code, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');

        // generate access code hash
        $hash = password_hash($access_code, PASSWORD_DEFAULT);
        // bind all VALUES
        $this->db->bind(':access_code', $hash);
        $this->db->bind(':room_id', $room_id); //room that is updated
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
          $this->syslog->addSystemEvent(0, "Room Access Code changed ".$room_id." by ".$updater_id, 0, "", 1);
          return intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing access code of room ".$room_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    private function checkRoomId ($room_id) {
      /* helper function that checks if a user id is a standard db id (int) or if a hash room id was passed
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

    private function sendMessage ($userid, $msg){
      /* send a message to the dashboard of the user
      yet to be written
      */

      $success = 0;
      return $success;
    }

    public function deleteRoom($room_id, $mode=0, $msg="", $updater_id=0) {
        /* deletes room and returns the number of rows (int) accepts room id or room hash id //
        $mode defines what is to be done. 0=room is deleted only (including relations) 1=room is deleted and users of this room are notified

        */
        $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->au_rooms.' WHERE id = :id');
        $this->db->bind (':id', $room_id);
        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Room deleted with id ".$room_id." by ".$updater_id, 0, "", 1);
          //check for action
          if ($mode==1)
          {
            // notify users that are in this room that room has been deleted
            // get all members of this room, add message to msg stack (au_news)

          }

          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting room with id ".$room_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }

    }// end function

}
?>
