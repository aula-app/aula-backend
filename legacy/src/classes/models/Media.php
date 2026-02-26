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
