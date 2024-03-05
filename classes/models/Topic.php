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



    }// end function

    protected function buildCacheHash ($key) {
        return md5 ($key);
      }

    public function getTopicsByRoom ($room_id, $offset=0, $limit=0, $orderby=3, $asc=0, $status=1) {
      /* returns topiclist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $room_id is the id of the room
      */
      $room_id = $this->converters->checkRoomId ($room_id);

      // getTopics ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="", $room_id=0)
      return $this->getTopics ($offset, $limit, $orderby, $asc, $status, "", $room_id);

    }// end function



    public function getTopicsByPhase ($offset, $limit, $orderby=3, $asc=0, $status=1, $phase_id, $room_id = 0){
      // returns topics by phase
      // phase_id is the id of the phase 0 = wild ideas 10 = discussion 20 = approval 30 = voting 40 = implementation
      // room_id = 0 means all rooms or specify a certain room
      //sanitize
      $phase_id = intval ($phase_id);
      $room_id = $this->converters->checkRoomId ($room_id);

      return $this->getTopics ($offset, $limit, $orderby, $asc, $status, "", $room_id, $phase_id);
    }

    public function reportTopic ($topic_id, $user_id, $updater_id, $reason =""){
      /* sets the status of an topic to 3 = reported, adds entry to reported table
      accepts db id and hash id of topic
      user_id is the id of the user that reported the topic
      updater_id is the id of the user that did the update
      */
      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      // check if topic is existent
      if ($this->converters->checkTopicExist($topic_id)==0) {
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 2; // error code - db error
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      } // else continue processing
      // check if this user has already reported this topic
      $stmt = $this->db->query('SELECT object_id FROM '.$this->db->au_reported.' WHERE user_id = :user_id AND type = 1 AND object_id = :topic_id');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':topic_id', $topic_id); // bind topic id
      $topics = $this->db->resultSet();
      if (count($topics)<1){
        //add this reporting to db
        $stmt = $this->db->query('INSERT INTO '.$this->db->au_reported.' (reason, object_id, type, user_id, status, created, last_update) VALUES (:reason, :topic_id, 1, :user_id, 0, NOW(), NOW())');
        // bind all VALUES

        $this->db->bind(':topic_id', $topic_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':reason', $reason);

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        $insertid = intval($this->db->lastInsertId());
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added new reporting topic (#".$insertid.") ".$content, 0, "", 1);
          // set topic status to reported
          $this->setTopicStatus($topic_id, 3, $updater_id=0);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error reporting topic ".$content, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
      }else {
        $returnvalue['success'] = true; // set return value to false
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
      $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
      $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

      return $this->setTopicStatus($topic_id, 4, $updater_id=0);

    }

    public function activateTopic ($topic_id, $updater_id){
      /* sets the status of a topic  to 1 = active
      accepts db id and hash id of topic
      updater_id is the id of the user that did the update
      */
      $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
      $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

      return $this->setTopicStatus($topic_id, 1, $updater_id=0);

    }

    public function deactivateTopic ($topic_id, $updater_id){
      /* sets the status of a topic to 0 = inactive
      accepts db id and hash id of topic
      updater_id is the id of the user that did the update
      */
      $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
      $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

      return $this->setTopicStatus($topic_id, 0, $updater_id=0);
    }

    public function setTopictoReview ($topic_id, $updater_id){
      /* sets the status of a topic to 5 = in review
      accepts db id and hash id of topic

      updater_id is the id of the user that did the update
      */
      $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
      $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

      return $this->setTopicStatus($topic_id, 5, $updater_id=0);

    }

    public function getTopicPhase ($topic_id) {
      // returns the phase of the topic
      $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id

      $ret_value = getTopicBaseData($topic_id);
      if ($ret_value['success']){
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $ret_value['data']['phase_id']; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;

      }else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = $ret_value ['error_code']; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } // end function

    public function getTopicBaseData ($topic_id) {
      /* returns topic base data for a specified db id */
      $topic_id = $this->converters->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_topics.' WHERE id = :id');
      $this->db->bind(':id', $topic_id); // bind topic id
      $topics = $this->db->resultSet();
      if (count($topics)<1){
        $returnvalue['success'] = true; // set return value to false
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



    public function getTopics ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="", $room_id=0, $phase_id = -1) {
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

      if ($phase_id > -1){
        // if a room id is set then add to where clause
        $extra_where.= " AND phase_id = ".$phase_id; // get specific topics in a phase
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

      $stmt = $this->db->query('SELECT '.$this->db->au_topics.'.name, '.$this->db->au_topics.'.hash_id, '.$this->db->au_topics.'.id, '.$this->db->au_topics.'.description_internal, '.$this->db->au_topics.'.description_public, '.$this->db->au_topics.'.last_update, '.$this->db->au_topics.'.created FROM '.$this->db->au_topics.' WHERE '.$this->db->au_topics.'.status= :status '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
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
          //echo 'Error occured while getting topics: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
      }

      if (count($topics)<1){
        $returnvalue['success'] = true; // set return value to false
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

    public function setTopicPhaseDurations ($topic_id, $phase_duration_0 = -1, $phase_duration_1 = -1, $phase_duration_2 = -1, $phase_duration_3 = -1, $phase_duration_4 = -1, $updater_id = 0) {
      // sets topic specific phase durations‚ returns success and error code 0 if everything is ok

      // sanitize
      $phase_duration_0 = intval ($phase_duration_0);
      $phase_duration_1 = intval ($phase_duration_1);
      $phase_duration_2 = intval ($phase_duration_2);
      $phase_duration_3 = intval ($phase_duration_3);
      $phase_duration_4 = intval ($phase_duration_4);

      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)
      $updater_id = $this->converters->checkUserId ($updater_id);
      // get phase global durations, if durations are set to -1 then get global config, 0 = phase deactivated
      $global_phase_durations = $this->converters->getGlobalPhaseDurations ();

      // check if globals are fully set (all 5 phases)
      if ($global_phase_durations['success'] && intval ($global_phase_durations['error_code'])==0 && intval ($global_phase_durations['count']) > 4){
        // set topic specific durations
        $i = 0; // counter_var

        foreach ($global_phase_durations['data'] as $global_phase_duration)
        {

            $global_duration =  $global_phase_duration ['duration'];
            //echo ("<br>".$i." A specific: ".$specific_phase_duration." global: ".$global_duration);
            $specific_phase_duration = ${'phase_duration_'.$i};
            if (intval ($specific_phase_duration) < 0) {
              // sepcific duration is not set, apply global duration
              ${$phase_duration_.$i} = intval ($global_duration);
            }
            $i++;
            if ($i > 4){
              // safety
              break;
            }
        } // end foreach
      } else {
        // set rescue fallback defaults if necessary (db global values not set)
        for ($i=0; $i < 5; $i++)
        {
            $specific_phase_duration = ${'phase_duration_'.$i};
            if ($specific_phase_duration < 0) {
              // sepcific duration is not set, apply global duration
              ${'phase_duration_'.$i} = $this->converters->global_default_phase_duration; // default duration value for every phase if globals are not set
            }
        } // end foreach
      }
      $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET phase_duration_0 = :phase_duration_0, phase_duration_1 = :phase_duration_1, phase_duration_2 = :phase_duration_2, phase_duration_3 = :phase_duration_3, phase_duration_4 = :phase_duration_4, last_update = NOW(), updater_id = :updater_id WHERE id = :topic_id');

      // bind all VALUES

      $this->db->bind(':phase_duration_0', $phase_duration_0);
      $this->db->bind(':phase_duration_1', $phase_duration_1);
      $this->db->bind(':phase_duration_2', $phase_duration_2);
      $this->db->bind(':phase_duration_3', $phase_duration_3);
      $this->db->bind(':phase_duration_4', $phase_duration_4);
      $this->db->bind(':topic_id', $topic_id);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

          $err=true;
      }

      if (!$err)
      {
        $this->syslog->addSystemEvent(0, "Phase durations set for topic (#".$topic_id.") ", 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        //$this->syslog->addSystemEvent(1, "Error editing topic ".$name, 0, "", 1);
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } // end function

    public function setTopicToVotingOnly ($topic_id, $phase_duration, $updater_id = 0) {
      // sets topic specific phase durations‚ returns success and error code 0 if everything is ok

      // sanitize
      $phase_duration = intval ($phase_duration);

      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)
      $updater_id = $this->converters->checkUserId ($updater_id);
      // get phase global durations, if durations are set to -1 then get global config, 0 = phase deactivated

      $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET phase_duration_0 = 0, phase_duration_1 = 0, phase_duration_2 = :phase_duration_2, phase_duration_3 = 0, phase_duration_4 = 0, last_update = NOW(), updater_id = :updater_id WHERE id = :topic_id');

      // bind all VALUES
      $this->db->bind(':phase_duration_2', $phase_duration);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)
      $this->db->bind(':topic_id', $topic_id);

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

          $err=true;
      }

      if (!$err)
      {
        $this->syslog->addSystemEvent(0, "Phases set to voting only for topic (#".$topic_id.") ".$name, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        //$this->syslog->addSystemEvent(1, "Error editing topic ".$name, 0, "", 1);
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } // end function

      public function setSpecificTopicPhaseDuration ($topic_id, $phase_duration_id, $duration, $updater_id = 0) {
        // sets topic specific single  phase duration‚ returns success and error code 0 if everything is ok

        // sanitize
        $phase_duration_id = intval ($phase_duration_id);
        $duration = intval ($duration);

        if ($phase_duration_id < 0){
          $phase_duration_id = 0;
        }

        if ($phase_duration_id > 4){
          $phase_duration_id = 4;
        }

        if ($duration < 0){
          $duration = 0;
        }

        $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)
        $updater_id = $this->converters->checkUserId ($updater_id);


        $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET phase_duration_'.$phase_duration_id.' = :duration, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':duration', $duration);

        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':topic_id', $topic_id); // topic that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Topic phase ".$phase_duration_id." duration changed to ".$duration." for ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = intval($this->db->rowCount()); // returned count of datasets

          return $returnvalue;
        } else {
          //$this->syslog->addSystemEvent(1, "Error changing status of topic ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
        return 0;

    }

    public function addTopic ($name, $description_internal, $description_public, $status = 1, $order_importance=10, $updater_id=0, $room_id=0, $wild_ideas_enabled = 1, $phase_id = -1, $phase_duration_0= -1,  $phase_duration_1= -1, $phase_duration_2 = -1, $phase_duration_3 = -1, $phase_duration_4 = -1) {
        /* adds a new topic and returns insert id (topic id) if successful, accepts the above parameters
         name = name of the topic, description_internal = shown only to admins for internal use
         desciption_public = shown in frontend, order_importance = order bias for sorting in the frontend
         status = status of inserted topic (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         $wild_ideas_enabled =  users can post ideas =  0=disabled,1=enabled (default)

        */
        //sanitize the vars
        $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $status = intval($status);
        $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

        $order_importance = intval ($order_importance);
        $description_internal = trim ($description_internal);
        $description_public = trim ($description_public);

        // get phase global durations, if durations are set to -1 then get global config, 0 = phase deactivated
        $global_phase_durations = $this->converters->getGlobalPhaseDurations ();

        // check if globals are fully set (all 5 phases)
        if ($global_phase_durations['success'] && intval ($global_phase_durations['error_code'])==0 && intval ($global_phase_durations['count']) > 4){
          // set topic specific durations
          $i = 0; // counter_var

          foreach ($global_phase_durations['data'] as $global_phase_duration)
          {

              $global_duration =  $global_phase_duration ['duration'];
              //echo ("<br>".$i." A specific: ".$specific_phase_duration." global: ".$global_duration);
              $specific_phase_duration = ${'phase_duration_'.$i};
              if (intval ($specific_phase_duration) < 0) {
                // sepcific duration is not set, apply global duration
                ${$phase_duration_.$i} = intval ($global_duration);
              }
              $i++;
              if ($i > 5){
                // safety
                break;
              }
          } // end foreach
        } else {
          // set rescue fallback defaults if necessary (db global values not set)
          for ($i=0; $i < 5; $i++)
          {
              $specific_phase_duration = ${'phase_duration_'.$i};
              if ($specific_phase_duration < 0) {
                // sepcific duration is not set, apply global duration
                ${'phase_duration_'.$i} = $this->converters->global_default_phase_duration; // default duration value for every phase if globals are not set
              }
          } // end foreach
        }


        $stmt = $this->db->query('INSERT INTO '.$this->db->au_topics.' (phase_duration_0, phase_duration_1, phase_duration_2, phase_duration_3, phase_duration_4, name, description_internal, description_public, status, hash_id, created, last_update, updater_id, order_importance, room_id) VALUES (:phase_duration_0, :phase_duration_1, :phase_duration_2, :phase_duration_3, :phase_duration_4, :name, :description_internal, :description_public, :status, :hash_id, NOW(), NOW(), :updater_id, :order_importance, :room_id)');
        // bind all VALUES

        $this->db->bind(':name', $this->crypt->encrypt($name));
        $this->db->bind(':status', $status);
        $this->db->bind(':phase_duration_0', $phase_duration_0);
        $this->db->bind(':phase_duration_1', $phase_duration_1);
        $this->db->bind(':phase_duration_2', $phase_duration_2);
        $this->db->bind(':phase_duration_3', $phase_duration_3);
        $this->db->bind(':phase_duration_4', $phase_duration_4);
        $this->db->bind(':description_public', $this->crypt->encrypt($description_public));
        $this->db->bind(':description_internal', $this->crypt->encrypt($description_internal));
        $this->db->bind(':room_id', $room_id);
        // generate unique hash for this topic
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($name.$appendix); // create hash id for this topic
        $this->db->bind(':hash_id', $hash_id);
        $this->db->bind(':order_importance', $order_importance); // order parameter
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

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
          //$this->syslog->addSystemEvent(1, "Error adding topic ".$name, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }


    }// end function

    public function editTopic ($name, $description_internal, $description_public, $status = 1, $order_importance=10, $updater_id=0, $room_id=0, $wild_ideas_enabled = 1, $phase_id = -1, $phase_duration_0= -1,  $phase_duration_1= -1, $phase_duration_2 = -1, $phase_duration_3 = -1, $phase_duration_4 = -1) {
        /* edits a topic and returns insert id (topic id) if successful, accepts the above parameters
         name = name of the topic, description_internal = shown only to admins for internal use
         desciption_public = shown in frontend, order_importance = order bias for sorting in the frontend
         status = status of inserted topic (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         $wild_ideas_enabled =  users can post ideas =  0=disabled,1=enabled (default)

        */
        //sanitize the vars
        $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $status = intval($status);
        $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

        $order_importance = intval ($order_importance);
        $description_internal = trim ($description_internal);
        $description_public = trim ($description_public);

        // get phase global durations, if durations are set to -1 then get global config, 0 = phase deactivated
        $global_phase_durations = $this->converters->getGlobalPhaseDurations ();

        // check if globals are fully set (all 5 phases)
        if ($global_phase_durations['success'] && intval ($global_phase_durations['error_code'])==0 && intval ($global_phase_durations['count']) > 4){
          // set topic specific durations
          $i = 0; // counter_var

          foreach ($global_phase_durations['data'] as $global_phase_duration)
          {

              $global_duration =  $global_phase_duration ['duration'];
              //echo ("<br>".$i." A specific: ".$specific_phase_duration." global: ".$global_duration);
              $specific_phase_duration = ${'phase_duration_'.$i};
              if (intval ($specific_phase_duration) < 0) {
                // sepcific duration is not set, apply global duration
                ${$phase_duration_.$i} = intval ($global_duration);
              }
              $i++;
              if ($i > 5){
                // safety
                break;
              }
          } // end foreach
        } else {
          // set rescue fallback defaults if necessary (db global values not set)
          for ($i=0; $i < 5; $i++)
          {
              $specific_phase_duration = ${'phase_duration_'.$i};
              if ($specific_phase_duration < 0) {
                // sepcific duration is not set, apply global duration
                ${'phase_duration_'.$i} = $this->converters->global_default_phase_duration; // default duration value for every phase if globals are not set
              }
          } // end foreach
        }


        $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET phase_duration_0 = :phase_duration_0, phase_duration_1 = :phase_duration_1, phase_duration_2 = :phase_duration_2, phase_duration_3 = :phase_duration_3, phase_duration_4 = :phase_duration_4, name = :name, description_internal = :description_internal , description_public = :description_public, status = :status, last_update = NOW(), updater_id = :updater_id, order_importance = :order_importance, room_id = :room_id WHERE id = :topic_id');

        // bind all VALUES

        $this->db->bind(':name', $this->crypt->encrypt($name));
        $this->db->bind(':status', $status);
        $this->db->bind(':phase_duration_0', $phase_duration_0);
        $this->db->bind(':phase_duration_1', $phase_duration_1);
        $this->db->bind(':phase_duration_2', $phase_duration_2);
        $this->db->bind(':phase_duration_3', $phase_duration_3);
        $this->db->bind(':phase_duration_4', $phase_duration_4);
        $this->db->bind(':description_public', $this->crypt->encrypt($description_public));
        $this->db->bind(':description_internal', $this->crypt->encrypt($description_internal));
        $this->db->bind(':room_id', $room_id);
        $this->db->bind(':topic_id', $topic_id);
        $this->db->bind(':order_importance', $order_importance); // order parameter
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }

        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Edited topic (#".$topic_id.") ".$name, 0, "", 1);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;


        } else {
          //$this->syslog->addSystemEvent(1, "Error editing topic ".$name, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

    }// end function editTopic()


    public function setTopicStatus($topic_id, $status, $updater_id = 0) {
        /* edits a topic and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of topic (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the topic that commits the update (i.E. admin )
        */
        $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
        $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id
        $status = intval ($status);

        $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':topic_id', $topic_id); // topic that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

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
          //$this->syslog->addSystemEvent(1, "Error changing status of topic ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function setTopicIdeasEnable ($topic_id, $wild_ideas_enabled, $updater_id = 0) {
        /* edits a topic and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of topic (0=disabled, 1=enabled)
         updater_id is the id of the topic that commits the update (i.E. admin )
        */
        //sanitize
        $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
        $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

        $wild_ideas_enabled = intval ($wild_ideas_enabled);

        if ($wild_ideas_enabled > 1){
          $wild_ideas_enabled = 1;
        }
        if ($wild_ideas_enabled < 0){
          $wild_ideas_enabled = 0;
        }

        $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET wild_ideas_enabled= :wild_ideas_enabled, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':wild_ideas_enabled', $wild_ideas_enabled);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':topic_id', $topic_id); // topic that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Topic ideas posting status changed ".$topic_id." to ".$wild_ideas_enabled." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = intval($this->db->rowCount()); // returned count of datasets

          return $returnvalue;
        } else {
          //$this->syslog->addSystemEvent(1, "Error changing ideas posting status of topic ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function setTopicOrder($topic_id, $order_importance = 10, $updater_id = 0) {
        /* edits a topic and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of topic (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the topic that commits the update (i.E. admin )
        */
        $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
        $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

        $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET order_importance = :order_importance, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':order_importance', $order_importance);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':topic_id', $topic_id); // topic that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

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
          //$this->syslog->addSystemEvent(1, "Error changing order of topic ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function setTopicName($topic_id, $name, $updater_id=0) {
        /* edits a topic and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         name = name of the topic
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
        $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

        // sanitize
        $name = trim ($name);

        $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET name= :name, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':name', $name);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':topic_id', $topic_id); // topic that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

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
          //$this->syslog->addSystemEvent(1, "Error changing topic name ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function setTopicProperty ($topic_id, $property, $propvalue, $updater_id=0) {
        /* edits a topic and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         property = name of db field
         propvalue = value
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
        $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

        // sanitize
        $property = trim ($property);

        $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET '.$property.'= :propvalue, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':propvalue', $propvalue);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':topic_id', $topic_id); // topic that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Topic property ".$property." changed for #".$topic_id." to ".$propvalue." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value to false
          $returnvalue['error_code'] = 0; // error code - db error
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = intval($this->db->rowCount()); // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error changing topic property ".$property." for #".$topic_id." to ".$propvalue." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function setTopicRoom ($topic_id, $room_id, $updater_id=0){

      $room_id = $this->converters->checkRoomId ($room_id); // autoconvert id
      $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
      $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

      $ret_value = $this->setTopicProperty ($topic_id, "room_id", $room_id, $updater_id);

      if ($ret_value['success']){
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {

        // error occured
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = $ret_value ['error_code']; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      }
    } // end function

    public function setTopicPhase ($topic_id, $phase_id, $updater_id=0){

      $idea_id = $this->converters->checkIdeaId ($idea_id); // autoconvert id
      $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
      $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id


      // sanitize phase
      if (intval ($phase_id)<0){
        $phase_id=0;
      }
      if (intval ($phase_id)>40){
        $phase_id=40;
      }

      $ret_value = $this->setTopicProperty ($topic_id, "phase_id", $phase_id, $updater_id);

      if ($ret_value['success']){
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {

        // error occured
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = $ret_value ['error_code']; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      }
    } // end function


    public function setTopicDescription($topic_id, $description, $type = 0, $updater_id = 0) {
        /* Chenges the descirption of a topic and returns number of rows if successful, accepts the above parameters
         description = description of the topic
         type = 0 = desciption_public
         type = 1 = description internal
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $topic_id = $this->converters->checkTopicId($topic_id); // checks  id and converts id to db id if necessary (when hash id was passed)
        $topic_id = $this->converters->checkTopicId ($topic_id); // autoconvert id
        $updater_id = $this->converters->checkUserId ($updater_id); // autoconvert id

        if ($type == 0) {
          $description_appendix = "_public";
        } else {
          $description_appendix = "_internal";
        }
        // sanitize
        $description = trim ($description);

        $stmt = $this->db->query('UPDATE '.$this->db->au_topics.' SET description'.$description_appendix.' = :description, last_update= NOW(), updater_id= :updater_id WHERE id= :topic_id');
        // bind all VALUES
        $this->db->bind(':description', $description);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':topic_id', $topic_id); // topic that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

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
          //$this->syslog->addSystemEvent(1, "Error changing topic description ".$topic_id." by ".$updater_id, 0, "", 1);
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

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_delegation.' WHERE topic_id = :id');
      $this->db->bind (':id', $topic_id);
      $err=false;
      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

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
        //$this->syslog->addSystemEvent(1, "Error deleting delegations for topic with id ".$topic_id, 0, "", 1);
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    }

    public function deleteTopic($topic_id, $updater_id=0) {
        /* deletes topic, cleans up and returns the number of rows (int) accepts top id or topic hash id //

        */
        $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->db->au_topics.' WHERE id = :id');
        $this->db->bind (':id', $topic_id);
        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

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
          //$this->syslog->addSystemEvent(1, "Error deleting topic with id ".$topic_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value to false
          $returnvalue['error_code'] = 1; // error code - db error
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

    }// end function

} // end class
?>
