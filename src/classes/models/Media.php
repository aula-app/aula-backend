<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include == 1) {

} else {
  exit;
}


class Media
{

  private $db;

  public function __construct($db, $crypt, $syslog, $files_dir = "")
  {
    // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
    $this->db = $db;
    $this->crypt = $crypt;
    $this->files_dir = $files_dir;
    //$this->syslog = new Systemlog ($db);
    $this->syslog = $syslog;
    $this->converters = new Converters($db);
  }// end function

  protected function buildCacheHash($key)
  {
    return md5($key);
  }

  public function getMediaOrderId($orderby)
  {
     # helper method => converts an int id to a db field name (for ordering)
    switch (intval($orderby)) {
      case 1:
        return "id";
      case 2:
        return "status";
      case 3:
        return "creator_id";
      case 4:
        return "created";
      case 5:
        return "name";
      case 6:
        return "uploader_id";
      default:
        return "last_update";
    }
  }// end function

  public function getMedia($offset = 0, $limit = 0, $orderby = 0, $asc = 0, $status = 1, $extra_where = "", $last_update = 0, $updater_id = 0)
  {
    /* returns media list (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (0)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    status (int) 0=inactive, 1=active, 2=suspended, 3=archived, 5= in review defaults to active (1)
    last_update = date that specifies texts younger than last_update date (if set to 0, gets all texts)
    extra_where = extra parameters for where clause, synthax " AND XY=4"
    updater_id = specifies a certain user thatuploaded the media (uploader), if set to 0, all texts are displayed
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

    $orderby_field = $this->getMediaOrderId($orderby);

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
    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_media . ' WHERE status= :status ' . $extra_where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    if ($limit) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    $this->db->bind(':status', $status); // bind status

    $err = false;
    try {
      $media = $this->db->resultSet();


    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // database error while executing query
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $count_datasets = count($media);

    if ($count_datasets < 1) {
      $returnvalue['success'] = true; // set success value
      $returnvalue['error_code'] = 2; // no data found
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = $count_datasets; // returned count of datasets

      return $returnvalue; // nothing found, return 0 code
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $media; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets

      return $returnvalue; // return an array (associative) with all the data
    }
  }// end function

  private function makeBool($val)
  {
    // helper function that converts ints to bool ints, sanitizes values
    $val = intval($val);
    if ($val > 1) {
      $val = 1;
    }
    if ($val < 1) {
      $val = 0;
    }
    return $val;
  }

  public function addMedia($name, $path, $type, $system_type, $filename, $status = 1, $updater_id = 0)
  {
    /* adds a new medium and returns insert id (text id) if successful, accepts the above parameters
    name is the shown name for the medium in the frontend
    description is the shown description for the medium in the frontend
    filename is the filename without path
    url is the https link to the medium
    path is the system path to the medium
    type defines the type of the file (type of media (1=picture, 2=video, 3= audio 4=pdf 5=doc 6=txt)
    status = status of the medium (0=inactive, 1=active, 2=
    ed, 3=reported, 4=archived 5= in review)
    updater id specifies the id of the user (i.e. admin) that uploaded this medium
    */

    //sanitize the vars
    $name = trim($name);

    $updater_id = $this->converters->checkUserId($updater_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $status = intval($status);
    $type = intval($type);
    $system_type = intval($system_type);
    $result = [];

    // if it is an avatar
    if ($system_type == 0) {
      $stmt = $this->db->query('SELECT filename FROM ' . $this->db->au_media . ' WHERE updater_id = :updater_id');
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      try {
        $result = $this->db->resultSet(); // do the query
        $has_avatar = !empty($result);
      } catch (Exception $e) {
        $err = true;
      }
    }

    if ($has_avatar) {
      $old_avatar = $result[0];
      if (file_exists($this->files_dir . '/' . $old_avatar["filename"])) {
        unlink($this->files_dir . '/' . $old_avatar["filename"]);
      }
      $stmt = $this->db->query('DELETE FROM ' . $this->db->au_media . ' WHERE updater_id = :updater_id');
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)
      try {
        $action = $this->db->execute(); // do the query
      } catch (Exception $e) {
        $err = true;
      }
    }

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_media . ' (name, type, system_type, path, filename, status, hash_id, created, last_update, updater_id) VALUES (:name, :type, :system_type, :path, :filename, :status, :hash_id, NOW(), NOW(), :updater_id)');
    // bind all VALUES

    $this->db->bind(':name', $name);
    $this->db->bind(':type', $type);
    $this->db->bind(':system_type', $system_type);
    $this->db->bind(':path', $path);
    $this->db->bind(':filename', $filename);

    $this->db->bind(':status', $status);

    // generate unique hash for this idea
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($filename . $appendix); // create hash id for this medium
    $this->db->bind(':hash_id', $hash_id);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query
    } catch (Exception $e) {

      echo $e;
      $err = true;
    }
    if (!$err) {
      $insertid = intval($this->db->lastInsertId());

      $this->syslog->addSystemEvent(0, "Added new medium (#" . $insertid . ") uploader: " . $updater_id, 0, "", 1);
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

  public function setMediaStatus($media_id, $status, $updater_id = 0)
  {
    /* edits a medium and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     status = status of medium (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $media_id = $this->converters->checkMediaId($media_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_media . ' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :media_id');
    // bind all VALUES
    $this->db->bind(':status', $status);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':media_id', $media_id); // text that is updated

    $err = false; // set error variable to false
    $count_datasets = 0; // init row count

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $count_datasets = intval($this->db->rowCount());
      $this->syslog->addSystemEvent(0, "Medium status changed " . $media_id . " by " . $updater_id, 0, "", 1);
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

  public function userAvatar($user_id)
  {
    $user_id  = $this->converters->checkUserId($user_id);
    $stmt = $this->db->query('SELECT filename FROM ' . $this->db->au_media . ' WHERE updater_id = :user_id AND system_type = 0');
    $this->db->bind(':user_id', $user_id);

    $err = false;
    try {
      $action = $this->db->execute(); // do the query
    } catch (Exception $e) {
      $err = true;
    }
    if (!$err) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $this->db->resultSet(); // returned data
      $returnvalue['count'] = count($this->db->resultSet()); // returned data

      return $returnvalue; // return number of affected rows to calling script
    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned data

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }

  }

} // end class
?>
