<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}


class Idea {
    private $db;

    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->crypt = $crypt;
        $this->syslog = $syslog;

        $au_rooms = 'au_rooms';
        $au_groups = 'au_groups';
        $au_ideas = 'au_ideas';
        $au_reported = 'au_reported';
        $au_users_basedata = 'au_users_basedata';
        $au_rel_rooms_users ='au_rel_rooms_users';
        $au_rel_groups_users ='au_rel_groups_users';
        $au_rel_groups_users ='au_rel_groups_users';

        $this->$au_users_basedata = $au_users_basedata; // table name for user basedata
        $this->$au_rooms = $au_rooms; // table name for rooms
        $this->$au_groups = $au_groups; // table name for groups
        $this->$au_ideas = $au_ideas; // table name for ideas
        $this->$au_reported = $au_reported; // table name for reportings

        $this->$au_rel_rooms_users = $au_rel_rooms_users; // table name for relations room - user
        $this->$au_rel_groups_users = $au_rel_groups_users; // table name for relations group - user
    }// end function

    public function getIdeaBaseData($idea_id) {
      /* returns idea base data for a specified db id */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas[0]; // return an array (associative) with all the data for the idea
      }
    }// end function

    public function getIdeaContent ($idea_id) {
      /* returns content, sum votes, sum likes, create, last_update, hash id and the user displayname of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT '.$this->au_ideas.'.content, '.$this->au_ideas.'.hash_id, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes, '.$this->au_ideas.'.last_update, '.$this->au_ideas.'.created, '.$this->au_users_basedata.'.displayname FROM '.$this->au_ideas.' INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.id='.$this->au_users_basedata.'.id) WHERE '.$this->au_ideas.'.id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return  $ideas[0]; // return content for the idea
      }
    }// end function



    public function getIdeaHashId($idea_id) {
      /* returns hash_id of an idea for a integer idea id
      */
      $stmt = $this->db->query('SELECT hash_id FROM '.$this->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas[0]['hash_id']; // return hash id for the idea
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

    public function getIdeaIdByHashId($hashid) {
      /* Returns Database ID of idea when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->au_ideas.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hashid); // bind hash id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas[0]['id']; // return idea id
      }
    }// end function

    public function reportIdea ($idea_id, $user_id, $updater_id, $reason =""){
      /* sets the status of an idea to 3 = reported
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)
      $userid = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      // check if idea is existent
      $stmt = $this->db->query('SELECT id FROM '.$this->au_ideas.' WHERE id = :idea_id');
      $this->db->bind(':idea_id', $idea_id); // bind user id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return ('0,3'); // idea does not exist
      } // else continue processing
      // check if this user has already reported this idea
      $stmt = $this->db->query('SELECT object_id FROM '.$this->au_reported.' WHERE user_id = :user_id AND type = 0 AND object_id = :idea_id');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':idea_id', $idea_id); // bind user id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        //add this reporting to db
        $stmt = $this->db->query('INSERT INTO '.$this->au_reported.' (reason, object_id, type, user_id, status, created, last_update) VALUES (:reason, :idea_id, 0, :user_id, 0, NOW(), NOW())');
        // bind all VALUES

        $this->db->bind(':idea_id', $idea_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':reason', $reason);

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
          $this->syslog->addSystemEvent(0, "Added new reporting (#".$insertid.") ".$content, 0, "", 1);
          // set idea status to reported
          $this->setIdeaStatus($idea_id, 3, $updater_id=0);
          return '1,1'; // nothing found, return 0 code

        } else {
          $this->syslog->addSystemEvent(1, "Error reporting idea ".$content, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
      }else {
        return '0,1'; // return error, user has already reported this idea
      }

    } // end function

    public function suspendIdea ($idea_id, $updater_id){
      /* sets the status of an idea to 3 = suspended
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 3, $updater_id=0);

    } // end function

    public function archiveIdea ($idea_id, $updater_id){
      /* sets the status of an idea to 4 = archived
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 4, $updater_id=0);

    }

    public function activateIdea ($idea_id, $updater_id){
      /* sets the status of an idea to 1 = active
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 1, $updater_id=0);

    }

    public function deactivateIdea ($idea_id, $updater_id){
      /* sets the status of an idea to 0 = inactive
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 0, $updater_id=0);
    }

    public function setIdeatoReview ($idea_id, $updater_id){
      /* sets the status of an idea to 5 = in review
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 5, $updater_id=0);

    }


    public function checkIdeaExist($idea_id) {
      /* returns 0 if idea does not exist, 1 if idea exists, accepts database id (int)
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT id FROM '.$this->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // idea found, return 1
      }
    } // end function


    function getIdeas ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="") {
      /* returns idealist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
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

      switch (intval ($orderby)){
        case 0:
        $orderby_field = "status";
        break;
        case 1:
        $orderby_field = "order_importance";
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

      $stmt = $this->db->query('SELECT '.$this->au_ideas.'.content, '.$this->au_ideas.'.hash_id, '.$this->au_ideas.'.id, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes, '.$this->au_ideas.'.last_update, '.$this->au_ideas.'.created, '.$this->au_users_basedata.'.displayname FROM '.$this->au_ideas.' INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.id='.$this->au_users_basedata.'.id) WHERE '.$this->au_ideas.'.status= :status '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $ideas = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas; // return an array (associative) with all the data
      }
    }// end function

    function getIdeasByRoom ($offset, $limit, $orderby=3, $asc=0, $status=1, $room_id) {
      /* returns idealist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $room_id is the id of the room
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
        $orderby_field = "status";
        break;
        case 1:
        $orderby_field = "order_importance";
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
      $select_part = 'SELECT '.$this->au_users_basedata.'.displayname, '.$this->au_ideas.'.room_id, '.$this->au_ideas.'.created, '.$this->au_ideas.'.last_update, '.$this->au_ideas.'.id, '.$this->au_ideas.'.content, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes FROM '.$this->au_ideas;
      $join =  'INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.user_id='.$this->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->au_ideas.'.status= :status AND '.$this->au_ideas.'.room_id= :room_id ';
      $stmt = $this->db->query($select_part.' '.$join.' '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status
      $this->db->bind(':room_id', $room_id); // bind room id

      $err=false;
      try {
        $ideas = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas; // return an array (associative) with all the data
      }
    }// end function

    function getIdeasByGroup ($offset, $limit, $orderby=3, $asc=0, $status=1, $group_id) {
      /* returns idealist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $room_id is the id of the room
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
        $orderby_field = "status";
        break;
        case 1:
        $orderby_field = "order_importance";
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
      $select_part = 'SELECT '.$this->au_users_basedata.'.displayname, '.$this->au_ideas.'.room_id, '.$this->au_ideas.'.created, '.$this->au_ideas.'.last_update, '.$this->au_ideas.'.id, '.$this->au_ideas.'.content, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes FROM '.$this->au_ideas;
      $join =  'INNER JOIN '.$this->au_rel_groups_users.' ON ('.$this->au_rel_groups_users.'.user_id='.$this->au_ideas.'.user_id) INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.user_id='.$this->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->au_ideas.'.status= :status AND '.$this->au_rel_groups_users.'.group_id= :group_id ';
      $stmt = $this->db->query($select_part.' '.$join.' '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      echo ('QUERY: '.$select_part.' '.$join.' '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status
      $this->db->bind(':group_id', $group_id); // bind group id

      $err=false;
      try {
        $ideas = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas; // return an array (associative) with all the data
      }
    }// end function

    function getIdeasByUser ($offset, $limit, $orderby=3, $asc=0, $status=1, $user_id) {
      /* returns idealist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $room_id is the id of the room
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
        $orderby_field = "status";
        break;
        case 1:
        $orderby_field = "order_importance";
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
      $select_part = 'SELECT '.$this->au_users_basedata.'.displayname, '.$this->au_ideas.'.room_id, '.$this->au_ideas.'.created, '.$this->au_ideas.'.last_update,  '.$this->au_ideas.'.id, '.$this->au_ideas.'.content, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes FROM '.$this->au_ideas;
      $join =  'INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.user_id='.$this->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->au_ideas.'.status= :status AND '.$this->au_ideas.'.user_id= :user_id ';
      $stmt = $this->db->query($select_part.' '.$join.' '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status
      $this->db->bind(':user_id', $user_id); // bind room id

      $err=false;
      try {
        $ideas = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas; // return an array (associative) with all the data
      }
    }// end function


    public function addIdea ($content, $user_id, $status, $order_importance=10, $updater_id=0, $votes_available_per_user=1, $info="", $room_id=0) {
        /* adds a new idea and returns insert id (idea id) if successful, accepts the above parameters
         content = actual content of the idea,
         status = status of inserted indea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         info is internal info or can be used for open aula to enter the name of the person that had the idea
        */

        //sanitize in vars
        $user_id = intval($user_id);
        $updater_id = intval ($updater_id);
        $status = intval($status);
        $room_id = intval($room_id);
        $order_importance = intval ($order_importance);
        $content = trim ($content);
        $info = trim ($info);

        $stmt = $this->db->query('INSERT INTO '.$this->au_ideas.' (info, votes_available_per_user, sum_votes, sum_likes, content, user_id, status, hash_id, created, last_update, updater_id, order_importance, room_id) VALUES (:info, :votes_available_per_user, 0, 0, :content, :user_id, :status, :hash_id, NOW(), NOW(), :updater_id, :order_importance, :room_id)');
        // bind all VALUES

        $this->db->bind(':content', $this->crypt->encrypt($content)); // encrypt the content
        $this->db->bind(':status', $status);
        $this->db->bind(':info', $info);
        $this->db->bind(':room_id', $room_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':votes_available_per_user', $votes_available_per_user);
        // generate unique hash for this user
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($content.$appendix); // create hash id for this idea
        $this->db->bind(':hash_id', $hash_id);
        $this->db->bind(':order_importance', $order_importance); // order parameter
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
          $this->syslog->addSystemEvent(0, "Added new idea (#".$insertid.") ".$content, 0, "", 1);
          return $insertid; // return insert id to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error adding idea ".$content, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function


    public function setIdeaStatus($idea_id, $status, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of idea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea status changed ".$idea_id." by ".$updater_id, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of idea ".$idea_id." by ".$updater_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function setContent($idea_id, $content, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         content = content of the idea
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET content= :content, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':content', $this->crypt->encrypt($content));
        $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea content changed ".$idea_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing idea content ".$idea_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function setIdeaInfo($idea_id, $content, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         content = content of the idea
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET info= :content, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':content', $this->crypt->encrypt($content));
        $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea info changed ".$idea_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing idea info ".$idea_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
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

    private function sendMessage ($userid, $msg){
      /* send a message to the dashboard of the user
      yet to be written
      */

      $success = 0;
      return $success;
    }

    public function deleteIdea($idea_id, $updater_id=0) {
        /* deletes idea and returns the number of rows (int) accepts idea id or idea hash id //

        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->au_ideas.' WHERE id = :id');
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
          $this->syslog->addSystemEvent(0, "Idea deleted, id=".$idea_id." by ".$updater_id, 0, "", 1);
          //check for action


          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting idea with id ".$idea_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }

    }// end function

}
?>
