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


  public function __construct($db, $crypt, $syslog)
  {
    // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
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

  public function getInstanceSettings()
  {
    /* returns base data for the instance */
    
    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_system_current_state  . ' LIMIT 1');
    
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
    // 0=off, 1=on, 2=off(weekend) 3=off (vacation) 4=off (holiday)

    // sanitize
    $status = intval ($status);

    if ($status > -1 && $status < 5)
    {
        $updater_id = $this->converters->checkUserId($updater_id);
    
        $stmt = $this->db->query('UPDATE ' . $this->db->au_system_current_state . ' SET online_mode = :status, last_update = NOW(), updater_id = :updater_id');

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

} // end class
?>