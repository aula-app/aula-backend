<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include == 1) {

} else {
  exit;
}



class Command
{

  private $db;

  public function __construct($db, $crypt, $syslog)
  {
    // db = database class, crypt = crypt class
    $this->db = $db;
    $this->crypt = $crypt;
    //$this->syslog = new Systemlog ($db);
    $this->syslog = $syslog;
    $this->converters = new Converters($db);
  }// end function

  protected function buildCacheHash($key)
  {
    return md5($key);
  }

  public function getCommandOrderId($orderby)
  {
    switch (intval($orderby)) {
      case 1:
        return "id";
      case 2:
        return "status";
      case 3:
        return "creator_id";
      case 4:
        return "created";
      default:
        return "last_update";
    }
  }// end function

  public function hasPermissions($user_id, $userlevel, $method, $arguments)
  {
    if ($userlevel >= 60) {
      return ["allowed" => true];
    } else {
      return ["allowed" => false, "message" => "You are not allowed to access Command model."];
    }
  }

  public function getCommandBaseData($command_id)
  {
    /* returns command base data for a specified db id */
    $command_id = intval($command_id);

    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_commands . ' WHERE id = :id');
    $this->db->bind(':id', $command_id); // bind command id
    $commands = $this->db->resultSet();
    if (count($commands) < 1) {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 2; //  error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue; // nothing found, return 0 code
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $commands[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue; // return an array (associative) with all the data
    }
  }// end function

  public function getDueCommands()
  {
    /* returns commands list (associative array) with commands that are due right now 

    */

    // init return array
    $returnvalue['success'] = false; // success (true) or failure (false)
    $returnvalue['errorcode'] = 0; // error code
    $returnvalue['data'] = false; // the actual data
    $returnvalue['count_data'] = 0; // number of datasets

    $date_now = date('Y-m-d H:i:s');

    $count_datasets = 0; // number of datasets retrieved
    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_commands . ' WHERE active = 1 AND date_start = :start_date');

    $this->db->bind(':start_date', $date_now); // bind date

    $err = false;
    try {
      $commands = $this->db->resultSet();


    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // database error while executing query
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $count_datasets = count($commands);

    if ($count_datasets < 1) {
      $returnvalue['success'] = false; // set success value
      $returnvalue['error_code'] = 2; // no data found
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = $count_datasets; // returned count of datasets

      return $returnvalue; // nothing found, return 0 code
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $commands; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets

      return $returnvalue; // return an array (associative) with all the data
    }
  }// end function

  public function getCommands($offset = 0, $limit = 0, $orderby = 0, $asc = 0, $active = 1, $updater_id = 0, $extra_where = "", $last_update = 0)
  {
    /* returns commands list (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (0)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    status (int) 0=inactive, 1=active, 2=suspended, 3=archived, 5= in review defaults to active (1)
    last_update = date that specifies texts younger than last_update date (if set to 0, gets all texts)
    extra_where = extra parameters for where clause, synthax " AND XY=4"
    */

    // init return array
    $returnvalue['success'] = false; // success (true) or failure (false)
    $returnvalue['errorcode'] = 0; // error code
    $returnvalue['data'] = false; // the actual data
    $returnvalue['count_data'] = 0; // number of datasets

    $date_now = date('Y-m-d H:i:s');
    // init vars
    $orderby_field = "";
    $asc_field = "";

    $limit_string = " LIMIT :offset , :limit ";
    $limit_active = true;

    // check if offset an limit are both set to 0, then show whole list (exclude limit clause)
    if ($offset == 0 && $limit == 0) {
      $limit_string = "";
      $limit_active = false;
    }

    if ($updater_id > 0) {
      // if a creator id is set then add to where clause
      $extra_where .= " AND updater_id = " . $updater_id; // get specific texts for a updloader
    }

    if (!(intval($last_update) == 0)) {
      // if a publish date is set then add to where clause
      $extra_where .= " AND last_update > \'" . $last_update . "\'";
    }

    $orderby_field = $this->getCommandOrderId($orderby);

    switch (intval($asc)) {
      case 0:
        $asc_field = "DESC";
        break;
      case 1:
        $asc_field = "ASC";
        break;
      default:
        $asc_field = "DESC";
    }
    $count_datasets = 0; // number of datasets retrieved
    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_commands . ' WHERE active= :active ' . $extra_where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    if ($limit) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    $this->db->bind(':active', $active); // bind status

    $err = false;
    try {
      $commands = $this->db->resultSet();


    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // database error while executing query
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $total_datasets = $this->converters->getTotalDatasets($this->db->au_commands, "id > 0" . $extra_where);

    if ($total_datasets < 1) {
      $returnvalue['success'] = false; // set success value
      $returnvalue['error_code'] = 2; // no data found
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue; // nothing found, return 0 code
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $commands; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue; // return an array (associative) with all the data
    }
  }// end function

  public function addCommand($cmd_id, $command, $parameters, $date_start, $updater_id)
  {
    /* adds a new command
    cmd_id is the id of the scope (int) + the command (int) =>
      0 = system       0 => status change
      1 = users    +   5 => delete
      2 = groups
      etc.
    $command is an human readable format.
    parameters is optional json string with parameters (for later use) => { value: number, target?: number }
    date_start (format sql date) describes when cmd starts execution
    target_id describes the target of the action (i.e. user_id for command delete user xy)
    cron job watches for commands and executes them
*/

    //sanitize the vars
    $cmd_id = intval($cmd_id);
    $command = trim($command);
    $parameters = trim($parameters);
    $date_start = trim($date_start);
    // auto set date to midnight for now
    $date_start = date_format(date_create($date_start),"Y-m-d 00:00:00");

    $updater_id = $this->converters->checkUserId($updater_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_commands . ' (cmd_id, command, date_start, parameters, active, status, created, last_update, updater_id) VALUES (:cmd_id, :command, :date_start, :parameters, 1, 0,NOW(), NOW(), :updater_id)');
    // bind all VALUES

    $this->db->bind(':cmd_id', $cmd_id);
    $this->db->bind(':command', $command);
    $this->db->bind(':parameters', $parameters);
    $this->db->bind(':date_start', $date_start);

    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $insertid = intval($this->db->lastInsertId());

      $this->syslog->addSystemEvent(0, "Added new command (#" . $cmd_id . " (" . $command . ")) by: " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $insertid; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue; // return insert id to calling script

    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }


  }// end function

  public function setActiveStatus($cmd_id, $status, $updater_id = 0)
  {
    /* edits a command, accepts the above parameters, all parameters are mandatory
     active status = status of command (0=inactive, 1=active) if inactive command will not be executed
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $cmd_id = intval($cmd_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $status = intval($status);
    #
    $stmt = $this->db->query('UPDATE ' . $this->db->au_commands . ' SET active= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :command_id');
    // bind all VALUES
    $this->db->bind(':status', $status);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':command_id', $cmd_id); // command that is updated

    $err = false; // set error variable to false
    $count_datasets = 0; // init row count

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $count_datasets = intval($this->db->rowCount());
      $this->syslog->addSystemEvent(0, "Command ACTIVE status changed for command " . $cmd_id . " to " . $status . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $count_datasets; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets


      return $returnvalue; // return number of affected rows to calling script
    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }
  }// end function

  public function setCommandStatus($cmd_id, $status, $updater_id = 0)
  {
    /* edits a command, accepts the above parameters, all parameters are mandatory
     status = status of command (0=not exectued yet, 1=successfully executed, 2 = execution error)
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $cmd_id = intval($cmd_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $status = intval($status);
    #
    $stmt = $this->db->query('UPDATE ' . $this->db->au_commands . ' SET active= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :command_id');
    // bind all VALUES
    $this->db->bind(':status', $status);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':command_id', $cmd_id); // command that is updated

    $err = false; // set error variable to false
    $count_datasets = 0; // init row count

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $count_datasets = intval($this->db->rowCount());
      $this->syslog->addSystemEvent(0, "Command status changed for command " . $cmd_id . " to " . $status . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $count_datasets; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets


      return $returnvalue; // return number of affected rows to calling script
    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }
  }// end function

  public function setCommandDate($cmd_id, $date, $updater_id = 0)
  {
    /* edits a command, accepts the above parameters, all parameters are mandatory
     date = date whewn command is executed (0=inactive, 1=active)
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $cmd_id = intval($cmd_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $date = trim($date);
    #
    $stmt = $this->db->query('UPDATE ' . $this->db->au_commands . ' SET date_start= :date_start, last_update= NOW(), updater_id= :updater_id WHERE id= :command_id');
    // bind all VALUES
    $this->db->bind(':date_start', $date);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':command_id', $cmd_id); // command that is updated

    $err = false; // set error variable to false
    $count_datasets = 0; // init row count

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $count_datasets = intval($this->db->rowCount());
      $this->syslog->addSystemEvent(0, "Command date changed for command " . $cmd_id . " to " . $date . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $count_datasets; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets


      return $returnvalue; // return number of affected rows to calling script
    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }
  }// end function



  public function deleteCommand($command_id, $updater_id = 0)
  {
    /* deletes command, accepts id (int)

    */
    $command_id = intval($command_id);

    if ($command_id > 0) {
      $stmt = $this->db->query('DELETE FROM ' . $this->db->au_commands . ' WHERE id = :id');
      $this->db->bind(':id', $command_id);


      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }
      if (!$err) {
        $count_datasets = intval($this->db->rowCount());
        $this->syslog->addSystemEvent(0, "Command deleted, id=" . $command_id . " by " . $updater_id, 0, "", 1);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = $count_datasets; // returned data
        $returnvalue['count'] = $count_datasets; // returned count of datasets


        return $returnvalue; // return number of affected rows to calling script
      } else {
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets


        return $returnvalue; // return success = false and error code = 1 to indicate that there was an db error executing the statement
      }

    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets


      return $returnvalue; // return success = false and error code = 2 to indicate that there was an out of range error executing the statement
    }



  }// end function

} // end class
?>
