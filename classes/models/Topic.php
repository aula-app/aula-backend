<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}



class Topic {
    private $db;


    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->crypt = $crypt;
        //$this->syslog = new Systemlog ($db);
        $this->syslog = $syslog;
        $this->converters = new Converters ($db); // load converters

        $au_rooms = 'au_rooms';
        $au_groups = 'au_groups';
        $au_ideas = 'au_ideas';
        $au_votes = 'au_votes';
        $au_topics = 'au_topics';
        $au_delegation = 'au_delegation';
        $au_reported = 'au_reported';
        $au_users_basedata = 'au_users_basedata';
        $au_rel_rooms_users ='au_rel_rooms_users';
        $au_rel_groups_users ='au_rel_groups_users';
        $au_rel_topics_ideas ='au_rel_topics_ideas';

        $this->$au_users_basedata = $au_users_basedata; // table name for user basedata
        $this->$au_rooms = $au_rooms; // table name for rooms
        $this->$au_delegation = $au_delegation; // table name for delegation
        $this->$au_groups = $au_groups; // table name for groups
        $this->$au_topics = $au_topics; // table name for topics
        $this->$au_ideas = $au_ideas; // table name for ideas
        $this->$au_votes = $au_votes; // table name for votes
        $this->$au_reported = $au_reported; // table name for reportings

        $this->$au_rel_rooms_users = $au_rel_rooms_users; // table name for relations room - user
        $this->$au_rel_groups_users = $au_rel_groups_users; // table name for relations group - user
        $this->$au_rel_topics_ideas = $au_rel_topics_ideas; // table name for relations topics - ideas
    }// end function

    protected function buildCacheHash ($key) {
        return md5 ($key);
      }

    public function getTopicsByRoom ($offset, $limit, $orderby=3, $asc=0, $status=1, $room_id) {
      /* returns topiclist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $room_id is the id of the room
      */
      // getTopics ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="", $room_id=0)
      return $this->getTopics ($offset, $limit, $orderby=3, $asc=0, $status=1, "", $room_id);

    }// end function


    public function reportTopic ($topic_id, $user_id, $updater_id, $reason =""){
      /* sets the status of an topic to 3 = reported, adds entry to reported table
      accepts db id and hash id of topic
      user_id is the id of the user that reported the topic
      updater_id is the id of the user that did the update
      */
      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      // check if idea is existent
      $stmt = $this->db->query('SELECT id FROM '.$this->au_topics.' WHERE id = :topic_id');
      $this->db->bind(':topic_id', $topic_id); // bind user id
      $topics = $this->db->resultSet();
      if (count($topics)<1){
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 2; // error code - db error
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      } // else continue processing
      // check if this user has already reported this topic
      $stmt = $this->db->query('SELECT object_id FROM '.$this->au_reported.' WHERE user_id = :user_id AND type = 1 AND object_id = :topic_id');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':topic_id', $topic_id); // bind topic id
      $topics = $this->db->resultSet();
      if (count($topics)<1){
        //add this reporting to db
        $stmt = $this->db->query('INSERT INTO '.$this->au_reported.' (reason, object_id, type, user_id, status, created, last_update) VALUES (:reason, :topic_id, 1, :user_id, 0, NOW(), NOW())');
        // bind all VALUES

        $this->db->bind(':topic_id', $topic_id);
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
          $this->syslog->addSystemEvent(0, "Added new reporting topic (#".$insertid.") ".$content, 0, "", 1);
          // set idea status to reported
          $this->setIdeaStatus($idea_id, 3, $updater_id=0);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          $this->syslog->addSystemEvent(1, "Error reporting topic ".$content, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
      }else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 2; // error code - db error
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } // end function


    public function archiveTopic ($topic_id, $updater_id){
      /* sets the status of an topic to 4 = archived
      accepts db id and hash id of topic
      updater_id is the id of the user that did the update
      */
      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

      return $this->setIdeaStatus($topic_id, 4, $updater_id=0);

    }

    public function activateTopic ($topic_id, $updater_id){
      /* sets the status of a topic  to 1 = active
      accepts db id and hash id of topic
      updater_id is the id of the user that did the update
      */
      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)
      return $this->setTopicStatus($topic_id, 1, $updater_id=0);

    }

    public function deactivateTopic ($topic_id, $updater_id){
      /* sets the status of a topic to 0 = inactive
      accepts db id and hash id of topic
      updater_id is the id of the user that did the update
      */
      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)
      return $this->setTopicStatus($topic_id, 0, $updater_id=0);
    }

    public function setTopictoReview ($topic_id, $updater_id){
      /* sets the status of a topic to 5 = in review
      accepts db id and hash id of topic

      updater_id is the id of the user that did the update
      */
      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)
      return $this->setTopicStatus($topic_id, 5, $updater_id=0);

    }

    public function getTopicBaseData ($topic_id) {
      /* returns topic base data for a specified db id */
      $topic_id = $this->converters->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_topics.' WHERE id = :id');
      $this->db->bind(':id', $topic_id); // bind idea id
      $topics = $this->db->resultSet();
      if (count($topics)<1){
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 2; // error code - db error
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue ['data'] = $topics[0]; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;

      }
    }// end function



    public function getTopics ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="", $room_id=0) {
      /* returns topiclist (associative array) with start and limit provided
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

      if ($room_id > 0){
        // if a room id is set then add to where clause
        $extra_where.= " AND room_id = ".$room_id; // get specific topics to a room
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

      $stmt = $this->db->query('SELECT '.$this->au_topics.'.name, '.$this->au_topics.'.hash_id, '.$this->au_topics.'.id, '.$this->au_topics.'.description_internal, '.$this->au_topics.'.description_public, '.$this->au_topics.'.last_update, '.$this->au_topics.'.created FROM '.$this->au_topics.' WHERE '.$this->au_topics.'.status= :status '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $topics = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting topics: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
      }

      if (count($topics)<1){
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 2; // error code - db error
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue ['data'] = $topics; // returned data
        $returnvalue ['count'] = count($topics); // returned count of datasets

        return $returnvalue;

      }
    }// end function

    public function addTopic ($name, $description_internal, $description_public, $status, $order_importance=10, $updater_id=0, $room_id=0) {
        /* adds a new topic and returns insert id (idea id) if successful, accepts the above parameters
         name = name of the topic, description_internal = shown only to admins for internal use
         desciption_public = shown in frontend, order_importance = order bias for sorting in the frontend
         status = status of inserted topic (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)

        */
        //sanitize the vars
        $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $status = intval($status);
        $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

        $order_importance = intval ($order_importance);
        $description_internal = trim ($description_internal);
        $description_public = trim ($description_public);


        $stmt = $this->db->query('INSERT INTO '.$this->au_topics.' (name, description_internal, description_public, status, hash_id, created, last_update, updater_id, order_importance, room_id) VALUES (:name, :description_internal, :description_public, :status, :hash_id, NOW(), NOW(), :updater_id, :order_importance, :room_id)');
        // bind all VALUES

        $this->db->bind(':name', $this->crypt->encrypt($name));
        $this->db->bind(':status', $status);
        $this->db->bind(':description_public', $this->crypt->encrypt($description_public));
        $this->db->bind(':description_internal', $this->crypt->encrypt($description_internal));
        $this->db->bind(':room_id', $room_id);
        // generate unique hash for this idea
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($name.$appendix); // create hash id for this idea
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
          $this->syslog->addSystemEvent(0, "Added new topic (#".$insertid.") ".$name, 0, "", 1);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = $insertid; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;


        } else {
          $this->syslog->addSystemEvent(1, "Error adding topic ".$name, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }


    }// end function


    public function setTopicStatus($topic_id, $status, $updater_id = 0) {
        /* edits a topic and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of idea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_topics.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':topic_id', $topic_id); // topic that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Topic status changed ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = intval($this->db->rowCount()); // returned count of datasets

          return $returnvalue;
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of topic ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function setTopicOrder($topic_id, $order_importance = 10, $updater_id = 0) {
        /* edits a topic and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of idea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_topics.' SET order_importance = :order_importance, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':order_importance', $order_importance);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':topic_id', $topic_id); // topic that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Topic order changed ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = intval($this->db->rowCount()); // returned count of datasets

          return $returnvalue;
        } else {
          $this->syslog->addSystemEvent(1, "Error changing order of topic ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function setTopicName($topic_id, $name, $updater_id=0) {
        /* edits a topic and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         content = content of the idea
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $idea_id = $this->converters->checkIdeaId($topic_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

        // sanitize
        $name = trim ($name);

        $stmt = $this->db->query('UPDATE '.$this->au_topics.' SET name= :name, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':name', $name);
        $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

        $this->db->bind(':topic_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Topic name changed ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = intval($this->db->rowCount()); // returned count of datasets

          return $returnvalue;

        } else {
          $this->syslog->addSystemEvent(1, "Error changing topic name ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function


    public function setTopicDescription($topic_id, $description, $type = 0, $updater_id = 0) {
        /* Chenges the descirption of a topic and returns number of rows if successful, accepts the above parameters
         description = description of the idea
         type = 0 = desciption_public
         type = 1 = description internal
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $idea_id = $this->converters->checkIdeaId($topic_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
        if ($type == 0) {
          $description_appendix = "_public";
        } else {
          $description_appendix = "_internal";
        }
        // sanitize
        $description = trim ($description);

        $stmt = $this->db->query('UPDATE '.$this->au_topics.' SET description'.$description_appendix.' = :description, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':description', $description);
        $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

        $this->db->bind(':topic_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Topic description changed ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = intval($this->db->rowCount()); // returned count of datasets

          return $returnvalue;
        } else {
          $this->syslog->addSystemEvent(1, "Error changing topic description ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function removeDelegationsTopic ($topic_id){
      // removes all delegations for a certain topic (topic_id)
      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

      $stmt = $this->db->query('DELETE FROM '.$this->au_delegation.' WHERE topic_id = :id');
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
        $this->syslog->addSystemEvent(0, "Delegations for topic deleted, id=".$topic_id."", 0, "", 1);
        //check for action
        // remove delegations and remove associations with this topic

        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = intval($this->db->rowCount()); // returned count of datasets

        return $returnvalue;
      } else {
        $this->syslog->addSystemEvent(1, "Error deleting delegations for topic with id ".$topic_id, 0, "", 1);
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    }

    public function deleteTopic($topic_id, $updater_id=0) {
        /* deletes topic, cleans up and returns the number of rows (int) accepts idea id or idea hash id //

        */
        $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->au_topics.' WHERE id = :id');
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
          $this->syslog->addSystemEvent(0, "Topic deleted, id=".$topic_id." by ".$updater_id, 0, "", 1);

          // remove delegations and remove associations with this topic
          $this->removeAllIdeasFromTopic ($topic_id);
          $this->removeDelegationsTopic ($topic_id);

          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = intval($this->db->rowCount()); // returned count of datasets

          return $returnvalue;
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting topic with id ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

    }// end function

} // end class
?>
