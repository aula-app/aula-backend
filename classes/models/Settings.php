<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include == 1) {

} else {
  exit;
}



class Settings
{
  private $db;
  # deals with everything around system settings


  public function __construct($db, $crypt, $syslog)
  {
    // db = database class, crypt = crypt class, $syslog = system logger class
    $this->db = $db;
    $this->crypt = $crypt;
    //$this->syslog = new Systemlog ($db);
    $this->syslog = $syslog;
    $this->converters = new Converters($db); // load converters



  }// end function

  protected function buildCacheHash($key)
  {
    return md5($key);
  }

  public function hasPermissions($user_id, $userlevel, $method, $arguments)
  {
    # helper method to retireve if a certain user has rights on sepcified methods 
    # returns true if ok, false if not 

    if ($method == "getInstanceSettings" or $method == "getCustomfields") {
       return ["allowed" => true];
    }

    if ($userlevel >= 50) {
      return ["allowed" => true];
    } else {
      return ["allowed" => false, "message" => "Not Authorized"];
    }
  }

  public function getInstanceSettings()
  {
    /* returns base status data for the instance */

    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_system_current_state . ' LIMIT 1');

    $settings = $this->db->resultSet();
    if (count($settings) < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $settings[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getGlobalConfig()
  {
    /* returns base configuration data for the instance */

    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_system_global_config . ' LIMIT 1');

    $settings = $this->db->resultSet();
    if (count($settings) < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $settings[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function


  public function getCustomfields()
  {
    /* helper method that returns custom fields 1 & 2 */

    $stmt = $this->db->query('SELECT custom_field1_name, custom_field2_name FROM ' . $this->db->au_system_global_config . ' LIMIT 1');

    $settings = $this->db->resultSet();

    if (count($settings) < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $settings[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getQuorum()
  {
    /* helper method that returns custom fields 1 & 2 */

    $stmt = $this->db->query('SELECT quorum_wild_ideas, quorum_votes FROM ' . $this->db->au_system_global_config . ' LIMIT 1');

    $settings = $this->db->resultSet();

    if (count($settings) < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $settings[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function


  public function setInstanceOnlineMode($status, $updater_id = 0)
  {
    // sets on- / offline mode of the instance
    // 0=off, 1=on, 2=off(weekend) 3=off (vacation) 4=off (holiday) // 5=off for all roles (Lock out)

    // sanitize
    $status = intval($status);

    if ($status > -1 && $status < 6) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_current_state . ' SET online_mode = :status, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':status', $status);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Online mode set to " . $status, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        //$this->syslog->addSystemEvent(1, "Error editing topic ".$name, 0, "", 1);
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function

  public function setQuorum ($quorum_wild_ideas, $quorum_votes, $updater_id = 0)
  {
    // sets quorum percentage (i.e. 80 ) for wild ideas and votes 


    // sanitize
    $quorum_wild_ideas = intval($quorum_wild_ideas);
    $quorum_votes = intval($quorum_votes);

    if ($quorum_wild_ideas > -1 && $quorum_wild_ideas < 101 && $quorum_votes > -1 && $quorum_votes < 101) 
    {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET quorum_wild_ideas = :quorum_wild_ideas,  quorum_votes = :quorum_votes, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':quorum_wild_ideas', $quorum_wild_ideas);
      $this->db->bind(':quorum_votes', $quorum_votes);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Quorum set", 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function

  public function setInstanceInfo($name, $description = "", $updater_id = 0)
  {
    // sets name and description for the instance

    // sanitize
    $name = trim($name);
    $description = trim($description);

    if (strlen($name) > 1) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET name = :name, description_public = :description, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':name', $name);
      $this->db->bind(':description', $description);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Name set to " . $name . " and description set to " . $description, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        //$this->syslog->addSystemEvent(1, "Error editing topic ".$name, 0, "", 1);
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function



  public function setAllowRegistration($status, $updater_id = 0)
  {
    // sets allow registration parameter for the instance

    // sanitize
    $status = intval($status);

    if ($status > -1) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET allow_registration = :status, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':status', $status);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Allow registration set to " . $status, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function

  public function setDefaultRoleForRegistration($role, $updater_id = 0)
  {
    // sets default role (int) 10 = guest, 20 = student etc. for the instance

    // sanitize
    $role = intval($role);

    if ($role > -1) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET default_role_for_registration = :role, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':role', $role);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Default role set to " . $role, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function


  public function setOauthStatus($status = 0, $updater_id = 0)
  {
    // enables (1) / disables (0) OAuth for the instance

    // sanitize
    $status = intval($status);

    if ($status > -1) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET enable_oauth = :status, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':status', $status);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "status for OAUTH set to " . $status, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function



  public function setWorkdays($first_day = 1, $last_day = 5, $updater_id = 0)
  {
    // sets working days (int) for the instance
    // 1 = monday 2 = tuesday 3 = wednesday 4= thursday 5 = friday 6 = saturday 7 = sunday

    // sanitize
    $first_day = intval($first_day);
    $last_day = intval($last_day);

    if ($last_day > 0 && $last_day < 8 && $first_day > 0 && $first_day < 8) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET first_workday_week = :first_day, last_workday_week = :last_day, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':first_day', $first_day);
      $this->db->bind(':last_day', $last_day);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Working days set to first: " . $first_day . " and last: " . $last_day, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function


  public function setDefaultEmail($email, $updater_id = 0)
  {
    // sets default e-mail address for the instance

    // sanitize
    $email = trim($email);

    if (strlen($email) > 1) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET default_email_address = :email, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':email', $email);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Default email set to " . $email, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function


  public function setDailyStartTime($time, $updater_id = 0)
  {
    // sets daily start time (FORMAT SQL DATE) for the instance

    // sanitize
    $time = trim($time);

    if (strlen($time) > 1) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET start_time = :time, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':time', $time);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Daily time set to " . $time, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        echo $err;
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function

  public function setDailyEndTime($time, $updater_id = 0)
  {
    // sets daily end time (FORMAT SQL DATE) for the instance

    // sanitize
    $time = trim($time);

    if (strlen($time) > 1) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET daily_end_time = :time, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':time', $time);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Daily time set to " . $time, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function

  public function setCustomFields($custom_field1_name, $custom_field2_name = "", $updater_id = 0)
  {
    // sets names for the custom fields (1 & 2)

    // sanitize
    $custom_field1_name = trim($custom_field1_name);
    $custom_field2_name = trim($custom_field2_name);

    if (strlen($custom_field1_name) > 0) {
      $updater_id = $this->converters->checkUserId($updater_id);

      $stmt = $this->db->query('UPDATE ' . $this->db->au_system_global_config . ' SET custom_field1_name = :custom_field1_name, custom_field2_name = :custom_field2_name, last_update = NOW(), updater_id = :updater_id ');

      // bind all VALUES
      $this->db->bind(':custom_field1_name', $custom_field1_name);
      $this->db->bind(':custom_field2_name', $custom_field2_name);

      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Custom field names set to " . $custom_field1_name . " and " . $custom_field2_name, 0, "", 1);
        $returnvalue['success'] = true; // set return value to false
        $returnvalue['error_code'] = 0; // error code - db error
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        //$this->syslog->addSystemEvent(1, "Error editing topic ".$name, 0, "", 1);
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code - db error
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - status out of range error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function


} // end class
?>
