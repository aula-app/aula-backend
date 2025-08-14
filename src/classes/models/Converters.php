<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include == 1) {
} else {
  exit;
}


class Converters
{
  # The converters class is a collection of useful methods for the system. An example is the conversion of entity hash ids (like user id) to int ids.
  # please see the description in the methods 

  private $db;

  public function __construct($db)
  {
    $this->db = $db;

    $this->cache = new Memcached();
    $this->cache->addServer('localhost', 11211) or die("Could not connect");
    $this->caching_time = 30; // time in seconds for caching (variable data)
    $this->long_caching_time = 300; // time in seconds for long caching (persistent data)
    $this->global_default_phase_duration = 14; // default phase duration if globals are not set in db

  } // end function

  protected function buildCacheHash($key)
  {
    return md5($key);
  }

  public function getToday()
  {
    # returns today's date
    $day = strtotime("today", $dt1);
    $return_date = date("Y-m-d H:i:s", $day);
    return $return_date;
  }
  public function getYesterday()
  {
    # returns yesterday's date
    $dt1 = strtotime(date("Y-m-d H:i:s"));
    $day = strtotime("yesterday", $dt1);
    $return_date = date("Y-m-d H:i:s", $day);
    return $return_date;
  }

  public function getNow()
  {
    # returns time / date for now
    $return_date = date("Y-m-d H:i:s");
    return $return_date;
  }

  public function getTimeOnlyNow()
  {
    # returns current time
    $return_date = date("H:i:s");
    return $return_date;
  }

  public function getThisMonth()
  {
    #returns current month
    $dt1 = strtotime(date("Y-m-d H:i:s"));
    $month = strtotime("first day of this month", $dt1);
    $return_date = date("Y-m-d H:i:s", $month);
    return $return_date;
  }

  public function getLastMonth()
  {
    # returns last month as date
    $dt1 = strtotime(date("Y-m-d H:i:s"));
    $month = strtotime("first day of last month", $dt1);
    $return_date = date("Y-m-d H:i:s", $month);
    return $return_date;
  }
  public function getlastWeek()
  {
    # returns last week as date
    $dt1 = strtotime(date("Y-m-d"));
    $day = strtotime("-1 week", $dt1);
    $return_date = date("Y-m-d H:i:s", $day);
    return $return_date;
  }

  public function getThisYear()
  {
    # returns this year as date
    $dt1 = strtotime("01.01." . date("Y") . "0:0:0");
    $return_date = date('Y-m-d H:i:s', $dt1);
    return $return_date;
  }

  public function getLastYear()
  {
    # returns last year as date
    $dt1 = strtotime("01.01." . (date("Y") - 1) . "0:0:0");
    $return_date = date('Y-m-d H:i:s', $dt1);
    return $return_date;
  }


  public function exportIdeasCSV($status = 1)
  {
    // exports all ideas with a certain status, defaults to active ideas (status = 1)

    $stmt = $this->db->query('SELECT ' . $this->db->au_topics . '.phase_id AS phase_id,  ' . $this->db->au_topics . '.description_public AS topic_description,  ' . $this->db->au_topics . '.name AS topic_name, ' . $this->db->au_topics . '.id AS topic_id,  ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.approved, ' . $this->db->au_ideas . '.approval_comment, ' . $this->db->au_ideas . '.content, ' . $this->db->au_ideas . '.hash_id, ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_votes, ' . $this->db->au_ideas . '.number_of_votes, ' . $this->db->au_ideas . '.last_update, ' . $this->db->au_ideas . '.status, ' . $this->db->au_ideas . '.created, ' . $this->db->au_users_basedata . '.displayname FROM ' . $this->db->au_ideas . ' INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id) LEFT JOIN ' . $this->db->au_rel_topics_ideas . ' ON (' . $this->db->au_ideas . '.id = ' . $this->db->au_rel_topics_ideas . '.idea_id) LEFT JOIN ' . $this->db->au_topics . ' ON (' . $this->db->au_topics . '.id = ' . $this->db->au_rel_topics_ideas . '.topic_id)  WHERE ' . $this->db->au_ideas . '.status = :status');

    $this->db->bind(':status', $status); // bind status

    $err = false;
    try {
      $ideas = $this->db->resultSet();

      foreach ($texts as $key) {
        $ids[$i] = $key['id'];
        $i++;
      }
    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    $total_datasets = count($ideas);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // no datasets error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = $ideas; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets with pagination or $total_datasets returns all datasets (without pagination)
      return $returnvalue;
    }
  } // end function

  public function getTextConsentValue($text_id)
  {
    /* returns need consents for a certain text for a integer text id
     */
    $stmt = $this->db->query('SELECT user_needs_to_consent FROM ' . $this->db->au_texts . ' WHERE id = :text_id');
    $this->db->bind(':text_id', $text_id); // bind text_id
    $texts = $this->db->resultSet();

    return "1," . $texts[0]['user_needs_to_consent']; // return consent value id for the text

  } // end function

  public function checkAuthorization($user_id, $method_name)
  {
    /* checks if user with the id user_id is 
     to use method with the name $method_name 
    returns data = true (ok) or false (not ok) 
    */
    $user_id = checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)


    $stmt = $this->db->query('SELECT userlevel FROM ' . $this->db->au_users_basedata . ' WHERE id = :id LIMIT 1');
    $this->db->bind(':id', $user_id); // bind userid

    $users = $this->db->resultSet();
    // init return value
    $result_check = false; // default to not 

    $user_level = 0; // default user_level

    if (count($users) < 1) {
      # user not existent
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code (user not existent)
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // user found, get user level
      $user_level = intval($users[0]['userlevel']); // returned data

    }
    // get minimum needed level to access method 
    $stmt = $this->db->query('SELECT user_level FROM ' . $this->db->au_userlevel_methods . ' WHERE method_name = :method_name LIMIT 1');
    $this->db->bind(':method_name', $method_name); // bind user_level

    $level = $this->db->resultSet();

    if (count($level) < 1) {
      # method not existent, default to allow access (true), error code = 3
      $result_check = true;
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 3; // error code (method not in authorization table)
      $returnvalue['data'] = $result_check; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {

      $method_level = intval($level[0]['user_level']);

      if ($user_level == $method_level || $user_level > $method_level) {
        // user is authorized
        $result_check = true;
      } else {
        $result_check = false;
      }
    }

    $returnvalue['success'] = true; // set return value to false
    $returnvalue['error_code'] = 0; // error code - db error
    $returnvalue['data'] = $result_check; // returned data
    $returnvalue['count'] = 1; // returned count of datasets

    return $returnvalue;
  } // end function

  public function checkRoleAuthorization($user_level, $method_name)
  {
    /* checks if a user level is permitted
     to use method with the name $method_name 
    returns data = true (ok) or false (not ok) 
    */

    // get minimum needed level to access method 
    $stmt = $this->db->query('SELECT user_level FROM ' . $this->db->au_userlevel_methods . ' WHERE method_name = :method_name LIMIT 1');
    $this->db->bind(':method_name', $method_name); // bind user_level

    $level = $this->db->resultSet();

    if (count($level) < 1) {
      # method not existent, default to allow access (true), error code = 3
      $result_check = true;
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 3; // error code (method not in authorization table)
      $returnvalue['data'] = $result_check; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {

      $method_level = intval($level[0]['user_level']);

      if ($user_level == $method_level || $user_level > $method_level) {
        // user is authorized
        $result_check = true;
      } else {
        $result_check = false;
      }
    }

    $returnvalue['success'] = true; // set return value to false
    $returnvalue['error_code'] = 0; // error code - db error
    $returnvalue['data'] = $result_check; // returned data
    $returnvalue['count'] = 1; // returned count of datasets

    return $returnvalue;
  } // end function

  public function getLastDataChange()
  {
    /* returns date of last change in system data
     */
    $stmt = $this->db->query('SELECT last_data_change FROM ' . $this->db->au_system_global_config . '  LIMIT 1');
    try {
      $field = $this->db->resultSet(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $field[0]['last_data_change']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing last data change, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    return "1," . $texts[0]['user_needs_to_consent']; // return consent value id for the text

  } // end function

  public function setLastDataChange()
  {
    // sets the field last data change to now in global settings table
    $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET last_data_change = NOW()');
    // bind all VALUES
    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing last data change, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }

  public function getGlobalPhaseDurations()
  {
    // returns the global settings for duration of all phases
    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_phases_global_config . ' ORDER BY phase_id ASC LIMIT 0,5');
    $global_config_phases = $this->db->resultSet();
    $total_datasets = count($global_config_phases);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code - db error
      $returnvalue['data'] = $global_config_phases; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $global_config_phases; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;
    }
  }


  public function getIdeaHashId($idea_id)
  {
    /* returns hash_id of an idea for a integer idea id
     */
    $stmt = $this->db->query('SELECT hash_id FROM ' . $this->db->au_ideas . ' WHERE id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      return "0,0"; // nothing found, return 0 code
    } else {
      return "1," . $ideas[0]['hash_id']; // return hash id for the idea
    }
  } // end function

  public function checkUserId($user_id)
  {
    /* helper function that checks if a user id is a standard db id (int) or if a hash userid was passed
    if a hash was passed, function gets db user id and returns db id
    */

    if (is_int(($user_id))) {
      return $user_id;
    } else {

      return $this->getUserIdByHashId($user_id);
    }
  } // end function

  public function checkServiceId($service_id)
  {
    /* helper function that checks if a service id is a standard db id (int) or if a hash id was passed
    if a hash was passed, function returns db id
    */

    if (is_int(($service_id))) {
      return $service_id;
    } else {

      return $this->getServiceIdByHashId($service_id);
    }
  } // end function

  public function checkCommandId($command_id)
  {
    /* helper function that checks if a command id is a standard db id (int) or if a hash id was passed
    if a hash was passed, function returns db id
    */

    if (is_int(($command_id))) {
      return $command_id;
    } else {

      return $this->getCommandIdByHashId($command_id);
    }
  } // end function


  public function checkCommentId($comment_id)
  {
    /* helper function that checks if a comment id is a standard db id (int) or if a hash was passed
    if a hash was passed, function gets db id and returns db id
    */

    if (is_int(($comment_id))) {
      return $comment_id;
    } else {

      return $this->getCommentIdByHashId($comment_id);
    }
  } // end function


  public function getUserIdByHashId($hash_id)
  {
    /* Returns Database ID of user when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getUserIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }
    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_users_basedata . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind userid
    $users = $this->db->resultSet();
    if (count($users) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $users[0]['id'], $this->long_caching_time);

      return $users[0]['id']; // return user id
    }
  } // end function

  public function getCommandIdByHashId($hash_id)
  {
    /* Returns Database ID of user when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getCommandIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }
    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_commands . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind userid
    $commands = $this->db->resultSet();
    if (count($commands) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $commands[0]['id'], $this->long_caching_time);

      return $commands[0]['id']; // return command id
    }
  } // end function

  public function setSpecificGlobalPhaseDuration($phase_id, $duration, $updater_id = 0)
  {
    // sets topic specific single  phase duration‚ returns success and error code 0 if everything is ok

    // sanitize
    $phase_id = intval($phase_id);
    $duration = intval($duration);

    if ($duration < 0) {
      $duration = 0;
    }

    $updater_id = $this->checkUserId($updater_id);


    $stmt = $this->db->query('UPDATE ' . $this->db->au_phases_global_config . ' SET duration = :duration, last_update= NOW(), updater_id= :updater_id WHERE phase_id = :phase_id');
    // bind all VALUES
    $this->db->bind(':duration', $duration);

    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':phase_id', $phase_id); // phase that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Global phase duration of phase #" . $phase_id . " duration changed to " . $duration . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = intval($this->db->rowCount()); // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing status of topic ".$topic_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }

  public function editSpecificGlobalPhase($phase_id, $duration, $name, $description_internal, $description_public, $time_scale = 0, $status = 1, $updater_id = 0)
  {
    // sets topic specific single  phase duration‚ returns success and error code 0 if everything is ok

    // sanitize
    $phase_id = intval($phase_id);
    $duration = intval($duration);
    $name = trim($name);
    $description_internal = trim($description_internal);
    $description_public = trim($description_public);
    $time_scale = intval($time_scale);
    $status = intval($status);

    if ($duration < 0) {
      $duration = 0;
    }

    $updater_id = $this->checkUserId($updater_id);


    $stmt = $this->db->query('UPDATE ' . $this->db->au_phases_global_config . ' SET time_scale = :time_scale, duration = :duration, name = :name, status = :status, description_internal = :description_internal, description_public = :description_public, last_update= NOW(), updater_id= :updater_id WHERE phase_id = :phase_id');
    // bind all VALUES
    $this->db->bind(':duration', $duration);
    $this->db->bind(':name', $name);
    $this->db->bind(':status', $status);
    $this->db->bind(':description_internal', $description_internal);
    $this->db->bind(':description_public', $description_public);
    $this->db->bind(':time_scale', $time_scale);


    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':phase_id', $phase_id); // phase that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Global phase edited, phase #" . $phase_id . " values changed by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = intval($this->db->rowCount()); // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing status of topic ".$topic_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    return 0;
  }


  public function getServiceIdByHashId($hash_id)
  {
    /* Returns Database ID of service when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getServiceIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }
    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_services . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind service id
    $services = $this->db->resultSet();
    if (count($services) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $services[0]['id'], $this->long_caching_time);

      return $services[0]['id']; // return service id
    }
  } // end function

  public function getCommentIdByHashId($hash_id)
  {
    /* Returns Database ID of comment when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getCommentIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_comments . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind comment id
    $comments = $this->db->resultSet();
    if (count($comments) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $comments[0]['id'], $this->long_caching_time);
      return $comments[0]['id']; // return id
    }
  } // end function

  public function getIdeaIdByHashId($hash_id)
  {
    /* Returns Database ID of idea when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getIdeaIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_ideas . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind hash id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $ideas[0]['id'], $this->long_caching_time);
      return $ideas[0]['id']; // return idea id
    }
  } // end function

  public function getTextIdByHashId($hash_id)
  {
    /* Returns Database ID of text when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getTextIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_texts . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind hash id
    $texts = $this->db->resultSet();
    if (count($texts) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $texts[0]['id'], $this->long_caching_time);
      return $texts[0]['id']; // return idea id
    }
  } // end function

  public function getTopicIdByHashId($hash_id)
  {
    /* Returns Database ID of topic when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getTopicIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_topics . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind hash id
    $topics = $this->db->resultSet();
    if (count($topics) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);

      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $topics[0]['id'], $this->long_caching_time);

      return $topics[0]['id']; // return topic id
    }
  } // end function

  public function getMessageIdByHashId($hash_id)
  {
    /* Returns Database ID of Message when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getMessageIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }
    error_log('convesion base:' . $hash_id);

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_messages . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind hash id
    $messages = $this->db->resultSet();
    if (count($messages) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $messages[0]['id'], $this->long_caching_time);
      return $messages[0]['id']; // return message id
    }
  } // end function

  public function getMediaIdByHashId($hash_id)
  {
    /* Returns Database ID of medium when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getMediaIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_media . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind hash id
    $media = $this->db->resultSet();
    if (count($media) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $media[0]['id'], $this->long_caching_time);
      return $media[0]['id']; // return media id
    }
  } // end function


  public function checkGroupId($group_id)
  {
    /* helper function that checks if a group id is a standard db id (int) or if a hash group id was passed
    if a hash was passed, function gets db group id and returns db id
    */

    if (is_int(($group_id))) {
      return $group_id;
    } else {
      return $this->getGroupIdByHashId($group_id);
    }
  } // end function

  public function checkTextId($text_id)
  {
    /* helper function that checks if a text id is a standard db id (int) or if a hash id was passed
    if a hash was passed, function returns db id
    */

    if (is_int(($text_id))) {
      return $text_id;
    } else {
      return $this->getTextIdByHashId($text_id);
    }
  } // end function

  public function getGroupIdByHashId($hash_id)
  {
    /* Returns Database ID of group when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getGroupIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_groups . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind hash id
    $groups = $this->db->resultSet();
    if (count($groups) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);

      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $groups[0]['id'], $this->long_caching_time);

      return $groups[0]['id']; // return group id
    }
  } // end function


  public function checkIdeaId($idea_id)
  {
    /* helper function that checks if a idea id is a standard db id (int) or if a hash idea id was passed
    if a hash was passed, function gets db idea id and returns db id
    */

    if (is_int(($idea_id))) {
      return $idea_id;
    } else {
      return $this->getIdeaIdByHashId($idea_id);
    }
  } // end function

  public function checkRoomId($room_id)
  {
    /* helper function that checks if a room id is a standard db id (int) or if a hash room id was passed
    if a hash was passed, function gets db room id and returns db id
    */

    if (is_int(($room_id))) {

      return $room_id;
    } else {

      return $this->getRoomIdByHashId($room_id);
    }
  } // end function

  public function checkTopicId($topic_id)
  {
    /* helper function that checks if a topic id is a standard db id (int) or if a hash topic id was passed
    if a hash was passed, function gets db topic id and returns db id
    */

    if (is_int(($topic_id))) {
      return $topic_id;
    } else {
      return $this->getTopicIdByHashId($topic_id);
    }
  } // end function

  public function checkMessageId($message_id)
  {
    /* helper function that checks if a message id is a standard db id (int) or if a hash id was passed
    if a hash was passed, function returns db id
    */

    if (is_int(($message_id))) {
      return $message_id;
    } else {

      return $this->getMessageIdByHashId($message_id);
    }
  } // end function

  public function checkMediaId($media_id)
  {
    /* helper function that checks if a medium id is a standard db id (int) or if a hash id was passed
    if a hash was passed, function returns db id
    */

    if (is_int(($media_id))) {
      return $media_id;
    } else {
      return $this->getMediaIdByHashId($media_id);
    }
  } // end function

  public function getRoomIdByHashId($hash_id)
  {
    /* Returns Database ID of room when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getRoomIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        error_log("Using cache for " . $hash_id . " data = " . $data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_rooms . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind hash id
    $rooms = $this->db->resultSet();
    if (count($rooms) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $rooms[0]['id'], $this->long_caching_time);
      return $rooms[0]['id']; // return room id
    }
  } // end function

  public function checkCategoryId($category_id)
  {
    /* helper function that checks if a topic id is a standard db id (int) or if a hash topic id was passed
    if a hash was passed, function gets db topic id and returns db id
    */

    if (is_int($category_id)) {
      return $category_id;
    } else {
      return $this->getCategoryIdByHashId($category_id);
    }
  } // end function

  public function getCategoryIdByHashId($hash_id)
  {
    /* Returns Database ID of category when hash_id is provided
     */
    $check_hash = $this->buildCacheHash("getCategoryIdByHashId" . $hash_id);
    // check if hash is in cache
    try {
      if ($this->cache->get($check_hash) != null) {
        $data = $this->cache->get($check_hash);
        // echo ("Using cache for ".$hash_id." data = ".$data);
        return $data;
      }
    } catch (Exception $e) {
      // cache error
    }


    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_categories . ' WHERE hash_id = :hash_id');
    $this->db->bind(':hash_id', $hash_id); // bind hash id
    $categories = $this->db->resultSet();
    if (count($categories) < 1) {
      $this->cache->set($check_hash, 0, $this->long_caching_time);
      return 0; // nothing found, return 0 code
    } else {
      $this->cache->set($check_hash, $categories[0]['id'], $this->long_caching_time);
      return $categories[0]['id']; // return category id
    }
  } // end function

  public function checkTopicExist($topic_id)
  {
    /* returns 0 if topic does not exist, 1 if topic exists, accepts database id (int)
     */
    $topic_id = $this->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

    $stmt = $this->db->query('SELECT status, room_id FROM ' . $this->db->au_topics . ' WHERE id = :id');
    $this->db->bind(':id', $topic_id); // bind topic id
    $topic_id = $this->db->resultSet();
    if (count($topic_id) < 1) {
      return 0; // nothing found, return 0 code
    } else {
      return 1; // topic found, return 1
    }
  } // end function

  public function checkCategoryExist($category_id)
  {
    /* returns 0 if category does not exist, 1 if category exists, accepts database id (int)
     */
    $category_id = $this->checkCategoryId($category_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

    $stmt = $this->db->query('SELECT status FROM ' . $this->db->au_categories . ' WHERE id = :id');
    $this->db->bind(':id', $category_id); // bind topic id
    $categories = $this->db->resultSet();
    if (count($categories) < 1) {
      return 0; // nothing found, return 0 code
    } else {
      return 1; // topic found, return 1
    }
  } // end function

  public function checkUserExist($user_id)
  {
    /* helper function to check if a user with a certain id exists, returns 0 if user does not exist, 1 if user exists, accepts database (int) or hash id (varchar)
     */
    $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_users_basedata . ' WHERE id = :id');
    $this->db->bind(':id', $user_id); // bind userid
    $users = $this->db->resultSet();
    if (count($users) < 1) {
      return 0; // nothing found, return 0 code
    } else {
      return 1; // user found, return 1
    }
  } // end function

  public function checkTextExist($text_id)
  {
    /* helper function to check if a text with a certain id exists, returns 0 if text does not exist, 1 if text exists, accepts database (int) or hash id (varchar)
     */
    $text_id = $this->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_texts . ' WHERE id = :id');
    $this->db->bind(':id', $text_id); // bind text id
    $texts = $this->db->resultSet();
    if (count($texts) < 1) {
      return 0; // nothing found, return 0 code
    } else {
      return 1; // user found, return 1
    }
  } // end function

  public function checkGroupExist($group_id)
  {
    /* returns 0 if group does not exist, 1 if group exists, accepts databse id (int)
     */
    $group_id = $this->checkGroupId($group_id); // checks group id and converts group id to db group id if necessary (when group hash id was passed)

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_groups . ' WHERE id = :id');
    $this->db->bind(':id', $group_id); // bind group id
    $groups = $this->db->resultSet();
    if (count($groups) < 1) {
      return 0; // nothing found, return 0 code
    } else {
      return 1; // group found, return 1
    }
  } // end function

  public function checkRoomExist($room_id)
  {
    /* returns 0 if room does not exist, 1 if room exists, accepts databse id (int)
     */
    $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_rooms . ' WHERE id = :id');
    $this->db->bind(':id', $room_id); // bind room id
    $rooms = $this->db->resultSet();
    if (count($rooms) < 1) {
      return 0; // nothing found, return 0 code
    } else {
      return 1; // room found, return 1
    }
  } // end function

  public function checkIdeaExist($idea_id)
  {
    /* returns 0 if idea does not exist, 1 if idea exists, accepts database id (int)
     */
    $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('SELECT status, room_id FROM ' . $this->db->au_ideas . ' WHERE id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $ideas['status'] = 0;
      $ideas['room_id'] = 0;

      return 0; // nothing found, return 0 code
    } else {
      return 1;
    }
  } // end function

  public function getTotalDatasets($table, $extra_where = "", $search_field = "", $search_text = "")
  {
    /* returns the total number of rows with
    extra_where parameter (i.e. $extra_where = "status = 1 AND id >50");
    $tablefield(varchar) = field (i.e. id) that exists in the destination table $table (i.e. ideas)
    */
    $extra_where = trim($extra_where);

    if (strlen($extra_where) > 0 && !str_contains($table, 'WHERE')) {
      $extra_where = " WHERE " . $extra_where;
    }

    if ($search_field != "") {
      $append_in_query = "";
      if ($extra_where == "" && !str_contains($table, 'WHERE')) {
        $append_in_query = " WHERE ";
      } else {
        $append_in_query = " AND ";
      }

      if (!str_contains($table, 'JOIN')) {
        if (!str_contains($search_field, 'au_')) {
          $extra_where .= $append_in_query . ' ' . $table . '.' . $search_field . " LIKE :search_text";
        } else {
          $extra_where .= $append_in_query . $search_field . " LIKE :search_text";
        }
      } else {
        $extra_where .= $append_in_query . ' ' . $search_field . " LIKE :search_text";
      }
    }

    $stmt = $this->db->query('SELECT COUNT(*) as total FROM ' . $table . $extra_where);

    if ($search_text != "") {
      $this->db->bind(":search_text", '%' . $search_text . '%');
    }
    $res = $this->db->resultSet();
    $total_rows = $res[0]['total'];
    return $total_rows;
  } // end function

  public function getTotalDatasetsFree($query, $search_field = "", $search_text = "")
  {
    /* returns the total number of rows with
    $query being the query string without select
    */
    $total_rows = 0;

    $query = trim($query);

    $append_in_query = "";
    if ($search_field != "") {
      $append_in_query = " AND " . $search_field . " LIKE :search_text";
    }

    try {
      $stmt = $this->db->query($query . $append_in_query);

      $res = $this->db->resultSet();
      $total_rows = count($res);
    } catch (Exception $e) {

      return -1;
    }
    return intval($total_rows);
  } // end function

} // end class
