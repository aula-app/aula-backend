<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include == 1) {

} else {
  exit;
}


class Room
{
  private $db;
  # class room deals with all matters around the room entity (like add room, delete room etc.)

  public function __construct($db, $crypt, $syslog)
  {
    // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
    $this->db = $db;
    $this->crypt = $crypt;
    $this->syslog = $syslog;
    $this->converters = new Converters($db); // load converters
    $this->user = new User($db, $crypt, $syslog); // load User

  }// end function

  public function getRoomOrderId($orderby)
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
      case 5:
        return "room_name";
      case 6:
        return "description_public";
      case 7:
        return "description_internal";
      case 8:
        return "order_importance";
      default:
        return "last_update";
    }
  }// end function

  public function validSearchField($search_field)
  {
    return in_array($search_field, [
      "room_name",
      "description_public"
    ]);
  }

  public function getRoomBaseData($room_id)
  {
    /* returns room base data for a specified db id */
    $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('SELECT ' . $this->db->au_rooms . '.* FROM ' . $this->db->au_rooms . ' LEFT JOIN ' . $this->db->au_topics . ' ON (' . $this->db->au_topics . '.room_id = ' . $this->db->au_rooms . '.id) WHERE ' . $this->db->au_rooms . '.id = :id');
    $this->db->bind(':id', $room_id); // bind room id
    $rooms = $this->db->resultSet();

    # now get the number of users in this room (to later calculate the quorum)
    $rooms[0]['number_of_users'] = 0; # init
    $number_of_total_users = $this->getNumberOfUsers($room_id);

    if (is_int($number_of_total_users)) {
      $rooms[0]['number_of_users'] = $number_of_total_users;
    }

    if (count($rooms) < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $rooms[0]; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function


  public function getNumberOfUsers($room_id)
  {
    /* returns number of users in this room (room_id ) */
    $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('SELECT user_id FROM ' . $this->db->au_rel_rooms_users . ' WHERE room_id = :room_id');
    $this->db->bind(':room_id', $room_id); // bind room id
    $rooms = $this->db->resultSet();

    return count($rooms);

  }// end function


  public function getRoomHashId($room_id)
  {
    /* returns hash_id of a room for a integer room id
     */
    $stmt = $this->db->query('SELECT hash_id FROM ' . $this->db->au_rooms . ' WHERE id = :id');
    $this->db->bind(':id', $room_id); // bind room id
    $rooms = $this->db->resultSet();
    if (count($rooms) < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $rooms[0]['hash_id']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getRooms($offset, $limit, $orderby = 0, $asc = 0, $status = -1, $search_field = "", $search_text = "", $type = -1)
  {
    /* returns roomlist (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (0)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=susepended, 3=archived, defaults to active (1)
    */

    // init vars
    $orderby_field = "";
    $asc_field = "";
    $limit_active = true;

    $limit_string = " LIMIT " . $offset . " , " . $limit;

    // additional conditions for the WHERE clause
    $extra_where = "";

    // check if offset an limit are both set to 0, then show whole list (exclude limit clause)
    if ($offset == 0 && $limit == 0) {
      $limit_string = "";
      $limit_active = false; // limit not set

    }
    if ($offset > 0 && $limit == 0) {
      $limit_string = " LIMIT " . $offset . " , 9999999999999999";
    }

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND status = " . $status;
    }

    if ($type > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND type = " . $type;
    }

    $search_field_valid = false;
    $search_where = "";
    if ($search_field != "") {
      if ($this->validSearchField($search_field)) {
        $search_field_valid = true;
        $search_where = " AND " . $search_field . " LIKE :search_text";
      }
    }

    $orderby_field = $this->getRoomOrderId($orderby);

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
    $select_part = 'SELECT * FROM ' . $this->db->au_rooms;
    $where = ' WHERE id > 0 ' . $extra_where;
    $stmt = $this->db->query($select_part . ' ' . $where . $search_where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    if ($search_field_valid) {
      $this->db->bind(':search_text', '%' . $search_text . '%');
    }

    $err = false;
    try {
      $rooms = $this->db->resultSet();

    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $total_datasets = count($rooms);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      if ($limit_active) {
        $total_datasets = $this->converters->getTotalDatasets($this->db->au_rooms, "id > 0" . $extra_where);
      }
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $rooms; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function getRoomsByUser($user_id, $offset = 0, $limit = 0, $orderby = 0, $asc = 0, $status = -1, $type = -1)
  {
    /* returns roomlist (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (0)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=susepended, 3=archived, defaults to active (1)
    All rooms are returned that the user is member of OR that dont have user restriction (open rooms)
    */
    $extra_where = "";
    // sanitize
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);


    // init vars
    $orderby_field = "";
    $asc_field = "";

    $limit_string = " LIMIT " . $offset . " , " . $limit;
    $limit_active = true;

    // check if offset an limit are both set to 0, then show whole list (exclude limit clause)
    if ($offset == 0 && $limit == 0) {
      $limit_string = "";
      $limit_active = false;

    }

    if ($offset > 0 && $limit == 0) {
      $limit_string = " LIMIT " . $offset . " , 9999999999999999";

    }
    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_users_basedata . ".status = " . $status;
    }

    if ($type > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND type = " . $type;
    }

    $orderby_field = $this->getRoomOrderId($orderby);

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

    $query = 'SELECT DISTINCT ' . $this->db->au_rooms . '.id, ' . $this->db->au_rooms . '.hash_id, ' . $this->db->au_rooms . '.room_name, ' . $this->db->au_rooms . '.description_public, ' . $this->db->au_rooms . '.description_internal, ' . $this->db->au_rooms . '.description_public, ' . $this->db->au_rooms . '.last_update, ' . $this->db->au_rooms . '.created FROM ' . $this->db->au_rooms . ' INNER JOIN ' . $this->db->au_rel_rooms_users . ' ON (' . $this->db->au_rooms . '.id = ' . $this->db->au_rel_rooms_users . '.room_id) WHERE (' . $this->db->au_rel_rooms_users . '.user_id = :user_id OR ' . $this->db->au_rooms . '.restrict_to_roomusers_only = 0) ';

    $stmt = $this->db->query($query . $extra_where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);

    //$this->db->bind(':status', $status); // bind status
    $this->db->bind(':user_id', $user_id); // bind user id

    $err = false;
    try {
      $rooms = $this->db->resultSet();

    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    $total_datasets = count($rooms);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // get count
      if ($limit_active) {
        // only newly calculate datasets if limits are active
        $total_datasets = $this->converters->getTotalDatasetsFree(str_replace(":user_id", $user_id, $query . $extra_where));
      }
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $rooms; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function isDefaultRoom($room_id)
  {
    $room_id = $this->converters->checkRoomId($room_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $query = 'SELECT type FROM ' . $this->db->au_rooms . ' WHERE ' . $this->db->au_rooms . '.id= :room_id ';
    $this->db->query($query);
    $this->db->bind(':room_id', $room_id); // bind room id
    $result = $this->db->resultSet();

    return $result[0]['type'] == 1;
  }

  public function getUsersInRoom($room_id, $status = -1, $offset = 0, $limit = 0, $orderby = 3, $asc = 0)

    
  {
    /* returns users (associative array)
    $status (int) relates to the status of the users => 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
    */
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);

    $room_id = $this->converters->checkRoomId($room_id); // checks id and converts id to db id if necessary (when hash id was passed)


    $orderby_field = "";
    $asc_field = "";
    $extra_where = "";

    $limit_string = " LIMIT " . $offset . " , " . $limit;
    $limit_active = true; // default limit to true


    // check if offset an limit are both set to 0, then show whole list (exclude limit clause)
    if ($offset == 0 && $limit == 0) {
      $limit_string = "";
      $limit_active = false;

    }
    if ($offset > 0 && $limit == 0) {
      $limit_string = " LIMIT " . $offset . " , 9999999999999999";
    }


    if ($status > -1) {
      // specific status selected / -1 = get all status valuess
      $extra_where .= " AND " . $this->db->au_users_basedata . ".status = " . $status;
    }

    $orderby_field = $this->db->au_users_basedata . "." . $this->user->getUserOrderId($orderby);

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

    $query = 'SELECT ' . $this->db->au_users_basedata . '.realname, ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_users_basedata . '.hash_id, ' . $this->db->au_users_basedata . '.id, ' . $this->db->au_users_basedata . '.username, ' . $this->db->au_users_basedata . '.email FROM ' . $this->db->au_rel_rooms_users . ' INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_rel_rooms_users . '.user_id=' . $this->db->au_users_basedata . '.id) WHERE ' . $this->db->au_rel_rooms_users . '.room_id= :room_id ' . $extra_where;

    $stmt = $this->db->query($query . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    $this->db->bind(':room_id', $room_id); // bind room id
    //$this->db->bind(':status', $status); // bind status

    $err = false;
    try {
      $rooms = $this->db->resultSet();

    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $total_datasets = count($rooms);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // get count
      if ($limit_active) {
        // only newly calculate datasets if limits are active
        $total_datasets = $this->converters->getTotalDatasetsFree(str_replace(":room_id", $room_id, $query . $extra_where));
      }

      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $rooms; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;
    }
  }// end function


  public function deleteRoomDelegations($room_id)
  {
    // deletes all delegations in a specified room
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)
    // get all topics from this room
    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_delegation . ' WHERE room_id = :room_id');
    $this->db->bind(':room_id', $room_id); // bind room id

    $err = false;
    try {
      $delegations = $this->db->resultSet();
      $delegations_count = $this->db->rowCount();

    } catch (Exception $e) {
      $this->syslog->addSystemEvent("Error occured while deleting delegations in room: " . $room_id, 0, "", 1);
      $err = true;
      return "0,0";
    }
    return "1," . $delegations_count;

  } // end function

  public function deleteRoomUserDelegations($room_id, $user_id)
  {
    // deletes all delegations in a specified room for a specific user
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)
    $user_id = $this->converters->checkUserId($user_id); // checks room id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_delegation . ' WHERE room_id = :room_id AND (user_id_original = :user_id OR user_id_target = :user_id) ');
    $this->db->bind(':room_id', $room_id); // bind room id
    $this->db->bind(':user_id', $user_id); // bind user id

    $err = false;
    try {
      $delegations = $this->db->resultSet();
      $delegations_count = $this->db->rowCount();

    } catch (Exception $e) {
      $this->syslog->addSystemEvent("Error occured while deleting delegations for user " . $user_id . " in room: " . $room_id, 0, "", 1);
      $err = true;
      return "0,0";
    }
    return "1," . $delegations_count;

  } // end function

  public function checkRoomExistsByName($room_name)
  {
    // checks if a room with this name is already in database
    $room_name = trim($room_name); // trim spaces

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_rooms . ' WHERE room_name = :room_name');
    $this->db->bind(':room_name', $room_name); // bind room id
    $rooms = $this->db->resultSet();
    if (count($rooms) < 1) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $rooms[0]['id']; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }

  public function canEditMainRoom($user_id, $userlevel, $arguments) {
    if ($userlevel != 60) {
      return false;
    }

    try {
      $room = $this->getRoomBaseData($arguments["room_id"])["data"];
      if ($room["type"] == 1)
        return true;
    } catch(Exception $e) {
      return false;
    }
  }

  public function editRoom($room_id, $room_name, $description_public = "", $description_internal = "", $internal_info = "", $status = 1, $access_code = "", $order_importance = 10, $updater_id = 0, $phase_duration_0 = 0, $phase_duration_1 = 0, $phase_duration_2 = 0, $phase_duration_3 = 0, $phase_duration_4 = 0)
  {
    /* edits a room and returns number of rows if successful, accepts the above parameters, all parameters are mandatory

    */
    // sanitize
    $room_name = trim($room_name);
    $description_public = trim($description_public);
    $description_internal = trim($description_internal);
    $internal_info = trim($internal_info);
    $access_code = trim($access_code);
    $status = intval($status);

    $phase_duration_0 = intval($phase_duration_0);
    $phase_duration_1 = intval($phase_duration_1);
    $phase_duration_2 = intval($phase_duration_2);
    $phase_duration_3 = intval($phase_duration_3);
    $phase_duration_4 = intval($phase_duration_4);

    $order_importance = intval($order_importance);

    $updater_id = $this->converters->checkUserId($updater_id); // autoconvert
    $room_id = $this->converters->checkRoomId($room_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_rooms . ' SET phase_duration_0 = :phase_duration_0,  phase_duration_1 = :phase_duration_1,  phase_duration_2 = :phase_duration_2,  phase_duration_3 = :phase_duration_3, phase_duration_4 = :phase_duration_4, room_name = :room_name, description_public = :description_public , description_internal= :description_internal, internal_info= :internal_info, status= :status, access_code= :access_code, order_importance= :order_importance, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
    // bind all VALUES
    $this->db->bind(':room_name', $room_name); // name of the room
    $this->db->bind(':description_public', $description_public); // shown in frontend
    $this->db->bind(':description_internal', $description_internal); // only shown in backend admin
    $this->db->bind(':internal_info', $internal_info); // extra internal info, only visible in backend
    $this->db->bind(':status', $status); // status of the room (0=inactive, 1=active, 4=archived)

    $this->db->bind(':phase_duration_0', $phase_duration_0); // phase_duration of the room 
    $this->db->bind(':phase_duration_1', $phase_duration_1); // phase_duration of the room 
    $this->db->bind(':phase_duration_2', $phase_duration_2); // phase_duration of the room 
    $this->db->bind(':phase_duration_3', $phase_duration_3); // phase_duration of the room 
    $this->db->bind(':phase_duration_4', $phase_duration_4); // phase_duration of the room 

    $this->db->bind(':access_code', $access_code); // optional access code for room access
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)
    $this->db->bind(':order_importance', $order_importance); // order for display in frontend

    $this->db->bind(':room_id', $room_id); // room that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Edited room " . $room_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;


    } else {
      //$this->syslog->addSystemEvent(1, "Error while editing room ".$room_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function addRoom($room_name, $description_public = "", $description_internal = "", $internal_info = "", $status = 1, $access_code = "", $restricted = 1, $order_importance = 10, $updater_id = 0, $phase_duration_0 = 0, $phase_duration_1 = 0, $phase_duration_2 = 0, $phase_duration_3 = 0, $phase_duration_4 = 0)
  {
    /* adds a new room and returns insert id (room id) if successful, accepts the above parameters

    */

    $access_code = trim($access_code);
    $hash_access_code = password_hash(trim($access_code), PASSWORD_DEFAULT); // hash access code
    //sanitize in vars
    $restricted = intval($restricted);
    $updater_id = intval($updater_id);

    $phase_duration_0 = intval($phase_duration_0);
    $phase_duration_1 = intval($phase_duration_1);
    $phase_duration_2 = intval($phase_duration_2);
    $phase_duration_3 = intval($phase_duration_3);
    $phase_duration_4 = intval($phase_duration_4);

    $status = intval($status);
    $order_importance = intval($order_importance);
    $room_name = trim($room_name);
    if ($restricted > 0) {
      $restricted = 1;
    }

    // check if room name is still available
    if ($this->checkRoomExistsByName($room_name)['data'] > 0) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 3; // error code - room with this name exists already
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_rooms . ' (phase_duration_0, phase_duration_1, phase_duration_2, phase_duration_3, phase_duration_4, room_name, description_public, description_internal, internal_info, status, hash_id, access_code, created, last_update, updater_id, restrict_to_roomusers_only, order_importance) VALUES (:phase_duration_0, :phase_duration_1, :phase_duration_2, :phase_duration_3, :phase_duration_4, :room_name, :description_public, :description_internal, :internal_info, :status, :hash_id, :access_code, NOW(), NOW(), :updater_id, :restricted, :order_importance)');
    // bind all VALUES

    $this->db->bind(':room_name', trim($room_name));
    $this->db->bind(':description_public', trim($description_public));
    $this->db->bind(':description_internal', trim($description_internal));
    $this->db->bind(':internal_info', trim($internal_info));
    $this->db->bind(':access_code', $hash_access_code);
    $this->db->bind(':status', $status);
    $this->db->bind(':restricted', $restricted);


    $this->db->bind(':phase_duration_0', $phase_duration_0);
    $this->db->bind(':phase_duration_1', $phase_duration_1);
    $this->db->bind(':phase_duration_2', $phase_duration_2);
    $this->db->bind(':phase_duration_3', $phase_duration_3);
    $this->db->bind(':phase_duration_4', $phase_duration_4);

    // generate unique hash for this user
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($room_name . $appendix); // create hash id for this user
    $this->db->bind(':hash_id', $hash_id);
    $this->db->bind(':order_importance', $order_importance); // order parameter
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }

    $insertid = intval($this->db->lastInsertId());

    $data = []; # init return array

    # set output array
    $data['insert_id'] = $insertid;
    $data['hash_id'] = $hash_id;

    if (!$err) {
      $this->syslog->addSystemEvent(0, "Added new room (#" . $insertid . ") " . $room_name, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $data; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue; // return insert id to calling script

    } else {
      //$this->syslog->addSystemEvent(1, "Error adding room ".$room_name, 0, "", 1);

      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function


  private function sendMessage($user_id, $msg)
  {
    /* send a message to the dashboard of the user
    yet to be written
    */

    $success = 0;
    return $success;
  }

  public function deleteRoom($room_id, $mode = 0, $msg = "", $updater_id = 0)
  {
    /* deletes room and returns the number of rows (int) accepts room id or room hash id //
    $mode defines what is to be done. 
    0 = room is deleted only (including relations) 1 = room is deleted and users of this room are notified

    */
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

    $room_id = intval($room_id);

    if ($room_id < 1) {
      # safety check to prevent deletion of main room
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rooms . ' WHERE id = :id AND NOT type = 1');
    $this->db->bind(':id', $room_id);
    $err = false;
    try {
      $action = $this->db->execute(); // do the query
      $rowCount = intval($this->db->rowCount());

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Room deleted with id " . $room_id . " by " . $updater_id, 0, "", 1);
      //check for action
      if ($mode == 1) {
        // notify users that are in this room that room has been deleted
        // get all members of this room, add message to msg stack (au_news)

      }
      // remove all delegations in this room
      $this->deleteRoomDelegations($room_id);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = $rowCount; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error deleting room with id ".$room_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  }// end function

}
?>
