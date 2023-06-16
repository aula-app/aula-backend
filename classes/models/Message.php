<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}



class Message {

    private $db;

    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->crypt = $crypt;
        //$this->syslog = new Systemlog ($db);
        $this->syslog = $syslog;

    }// end function

    protected function buildCacheHash ($key) {
        return md5 ($key);
      }


    public function getIdeaHashId($idea_id) {
      /* returns hash_id of an idea for a integer idea id
      */
      $stmt = $this->db->query('SELECT hash_id FROM '.$this->db->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return "0,0"; // nothing found, return 0 code
      }else {
        return "1,".$ideas[0]['hash_id']; // return hash id for the idea
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


    public function getUserIdByHashId($hash_id) {
      /* Returns Database ID of user when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_users_basedata.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]['id']; // return user id
      }
    }// end function

    public function getIdeaIdByHashId($hash_id) {
      /* Returns Database ID of idea when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_ideas.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas[0]['id']; // return idea id
      }
    }// end function

    public function getTopicIdByHashId($hash_id) {
      /* Returns Database ID of topic when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_topics.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $topics = $this->db->resultSet();
      if (count($topics)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $topics[0]['id']; // return topic id
      }
    }// end function

    public function getMessageIdByHashId($hash_id) {
      /* Returns Database ID of Message when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_messages.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $messages = $this->db->resultSet();
      if (count($messages)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $messages[0]['id']; // return message id
      }
    }// end function

    public function getMessagesByRoom ($offset, $limit, $orderby=3, $asc=0, $status=1, $room_id) {
      /* returns message list (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $room_id is the id of the room
      */
      $message_id = $this->checkMessageId($message_id); // checks id and converts id to db id if necessary (when hash id was passed)

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
        $orderby_field = "status";
        break;
        case 1:
        $orderby_field = "publish_date";
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
      $where = ' WHERE status= :status AND room_id= :room_id ';
      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_messages.' '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status
      $this->db->bind(':room_id', $room_id); // bind room id

      $err=false;
      try {
        $messages = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting messages for room '.$room_id,  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($messages)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $messages; // return an array (associative) with all the data
      }
    }// end function


    public function archiveMessage ($message_id, $updater_id){
      /* sets the status of a message to 4 = archived
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $message_id = $this->checkMessageId($message_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setMessageStatus($message_id, 4, $updater_id=0);

    }

    public function activateMessage ($message_id, $updater_id){
      /* sets the status of a message to 1 = active
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $message_id = $this->checkMessageId($message_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setMessageStatus($message_id, 1, $updater_id=0);

    }

    public function deactivateMessage ($message_id, $updater_id){
      /* sets the status of a message to 0 = inactive
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $message_id = $this->checkMessageId($message_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setMessageStatus($message_id, 0, $updater_id=0);
    }

    public function setMessagetoReview ($message_id, $updater_id){
      /* sets the status of a message to 5 = to review
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $message_id = $this->checkMessageId($message_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setMessageStatus($message_id, 5, $updater_id=0);

    }

    public function getMessageBaseData ($message_id) {
      /* returns message base data for a specified db id */
      $message_id = $this->checkMessageId($message_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_messages.' WHERE id = :id');
      $this->db->bind(':id', $message_id); // bind idea id
      $messages = $this->db->resultSet();
      if (count($messages)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $messages[0]; // return an array (associative) with all the data
      }
    }// end function

    public function getMessages ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="", $publish_date, $target_group, $room_id) {
      /* returns message list (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, 5= in review defaults to active (1)
      publish_date = date that specifies messages younger than publish date
      extra_where = extra parameters for where clause, synthax " AND XY=4"
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
      if ($target_group > 0){
        // if a target group is set then add to where clause
        $extra_where.= " AND target_group = ".$target_group;
      }

      if ($room_id > 0){
        // if a room id is set then add to where clause
        $extra_where.= " AND room_id = ".$room_id;
      }

      if (checkdate ($publish_date)){
        // if a publish date is set then add to where clause
        $extra_where.= " AND publish_date > ".$publish_date;
      }

      switch (intval ($orderby)){
        case 0:
        $orderby_field = "status";
        break;
        case 1:
        $orderby_field = "name";
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
        $orderby_field = "headline";
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

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_messages.' WHERE status= :status '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $messages = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting messages: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($messages)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $messages; // return an array (associative) with all the data
      }
    }// end function

    private function makeBool ($val){
      // helper function that converts ints to bool ints, sanitizes values
      $val = intval ($val);
      if ($val > 1) {
        $val = 1;
      }
      if ($val < 1) {
        $val = 0;
      }
      return $val;
    }

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

    public function getGroupIdByHashId($hash_id) {
        /* Returns Database ID of group when hash_id is provided
        */

        $stmt = $this->db->query('SELECT id FROM '.$this->db->au_groups.' WHERE hash_id = :hash_id');
        $this->db->bind(':hash_id', $hash_id); // bind hash id
        $groups = $this->db->resultSet();
        if (count($groups)<1){
          return 0; // nothing found, return 0 code
        }else {
          return $groups[0]['id']; // return group id
        }
      }// end function

    public function addMessage ($headline, $body, $target_group, $target_id, $pin_to_top=0, $msg_type, $publish_date, $level_of_detail, $only_on_dashboard, $status=1, $room_id=0, $updater_id=0) {
        /* adds a new message and returns insert id (idea id) if successful, accepts the above parameters
        $headline is the headline of the message, $body the content, $target_group (int) specifies a certain group that this message is intended for, set to 0 for all groups
        target_id specifies a certain user that this message is intended for (like private message), set to 0 for no specification of a certain
        msg_type (int) specifies the type of message (1=system message, 2= message from admin, 3=message from user )
        publish_date (datetime) specifies the date when this message should be published Format DB datetime (2023-06-14 14:21:03)
        level_of_detail (int) specifies how detailed the scope of this message is (low = general, high = very specific)
        only_on_dashboard (int 0,1) specifies if the message should only be displayed on the dashboard (1) or also pushed to the user (email / push notification)
        status = status of the message (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
        room_id specifies a room that this message is adressed to / associated with (all users within this room will receive this message), set to 0 for all rooms
        updater id specifies the id of the user (i.e. admin) that added this message
        */
        //sanitize the vars
        $updater_id = $this->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        if (!(intval ($target_id)==0)){
          // only check for target id if it is not set to 0
          $target_id = $this->checkUserId($target_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        }
        if (!(intval ($target_group)==0)){
          $target_group = $this->checkGroupId($target_group); // check id and converts id to db id if necessary (when hash id was passed)
        }
        $status = intval($status);
        if (!(intval ($room_id)==0)){
          $room_id = $this->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)
        }
        $pin_to_top = makeBool ($pin_to_top);
        $only_on_dashboard = makebool ($only_on_dashboard);
        $level_of_detail = intval ($level_of_detail);
        $msg_type = intval ($msg_type);

        $headline = trim ($headline);
        $body = trim ($body);


        $stmt = $this->db->query('INSERT INTO '.$this->db->au_messages.' (headline, body, target_group, target_id, pin_to_top, msg_type, publish_date, level_of_detail, only_on_dashboard, status, room_id, hash_id, created, last_update, updater_id) VALUES (:headline, :body, :target_group, :target_id, :pin_to_top, :msg_type, :publish_date, :level_of_detail, :only_on_dashboard, :status, :room_id, :hash_id, NOW(), NOW(), :updater_id)');
        // bind all VALUES

        $this->db->bind(':headline', $headline);
        $this->db->bind(':body', $body);
        $this->db->bind(':target_id', $target_id);
        $this->db->bind(':target_group', $target_group);
        $this->db->bind(':pin_to_top', $pin_to_top);
        $this->db->bind(':msg_type', $msg_type);
        $this->db->bind(':publish_date', $publish_date);
        $this->db->bind(':level_of_detail', $level_of_detail);
        $this->db->bind(':only_on_dashboard', $only_on_dashboard);
        $this->db->bind(':status', $status);
        $this->db->bind(':room_id', $room_id);

        // generate unique hash for this idea
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($name.$appendix); // create hash id for this message
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
          $this->syslog->addSystemEvent(0, "Added new message (#".$insertid.") ".$headline, 0, "", 1);
          return $insertid; // return insert id to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error adding message ".$headline, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }


    }// end function


    public function setMessageStatus($message_id, $status, $updater_id = 0) {
        /* edits a message and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of message (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the user that does the update (i.E. admin )
        */
        $message_id = $this->checkMessageId($message_id); // checks id and converts id to db id if necessary (when hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_messages.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :message_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':message_id', $message_id); // message that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Message status changed ".$message_id." by ".$updater_id, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of message ".$message_id." by ".$updater_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function




    private function checkIdeaId ($idea_id) {
      /* helper function that checks if a idea id is a standard db id (int) or if a hash idea id was passed
      if a hash was passed, function gets db idea id and returns db id
      */

      if (is_int($idea_id))
      {
        return $idea_id;
      } else
      {
        return $this->getIdeaIdByHashId ($idea_id);
      }
    } // end function

    private function checkRoomId ($room_id) {
      /* helper function that checks if a room id is a standard db id (int) or if a hash room id was passed
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

    private function checkTopicId ($topic_id) {
      /* helper function that checks if a topic id is a standard db id (int) or if a hash topic id was passed
      if a hash was passed, function gets db topic id and returns db id
      */

      if (is_int($topic_id))
      {
        return $topic_id;
      } else
      {
        return $this->getTopicIdByHashId ($topic_id);
      }
    } // end function

    private function checkMessageId ($message_id) {
      /* helper function that checks if a message id is a standard db id (int) or if a hash id was passed
      if a hash was passed, function returns db id
      */

      if (is_int($message_id))
      {
        return $message_id;
      } else
      {
        return $this->getMessageIdByHashId ($message_id);
      }
    } // end function

    public function getRoomIdByHashId($hash_id) {
      /* Returns Database ID of room when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_rooms.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $rooms[0]['id']; // return room id
      }
    }// end function

    private function sendMessage ($user_id, $msg){
      /* send a message to the dashboard of the user
      yet to be written
      */

      $success = 0;
      return $success;
    }


    public function deleteMessage ($message_id, $updater_id=0) {
        /* deletes message, accepts message_id (hash (varchar) or db id (int))

        */
        $message_id = $this->checkMessageId($message_id); // checks id and converts id to db  id if necessary (when hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->db->au_messages.' WHERE id = :id');
        $this->db->bind (':id', $idea_id);
        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Message deleted, id=".$message_id." by ".$updater_id, 0, "", 1);

          // remove delegations and remove associations with this message

          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting message with id ".$message_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }

    }// end function

} // end class
?>
