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
    /* returns user base data for a specified db id */
    $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('SELECT ' . $this->db->au_rooms . '.* FROM ' . $this->db->au_rooms . ' LEFT JOIN ' . $this->db->au_topics . ' ON (' . $this->db->au_topics . '.room_id = ' . $this->db->au_rooms . '.id) WHERE ' . $this->db->au_rooms . '.id = :id');
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
    $returnvalue['success'] = true; // set return value to false
    $returnvalue['error_code'] = 0; // error code - db error
    $returnvalue['data'] = count($rooms); // returned data
    $returnvalue['count'] = count($rooms); // returned count of datasets

    return $returnvalue;

  }// end function

  public function getNumberOfTopics($room_id)
  {
    /* returns number of topics in this room (room_id ) */
    $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('SELECT id, phase_id FROM ' . $this->db->au_topics . ' WHERE room_id = :room_id');
    $this->db->bind(':room_id', $room_id); // bind room id
    $rooms = $this->db->resultSet();
    $returnvalue['success'] = true; // set return value to false
    $returnvalue['error_code'] = 0; // error code - db error
    $returnvalue['data'] = count($rooms); // returned data
    $returnvalue['count'] = count($rooms); // returned count of datasets

    return $returnvalue;
  }// end function

  public function getNumberOfIdeas($room_id)
  {
    /* returns number of ideas in this room (room_id ) */
    $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_ideas . ' WHERE room_id = :room_id');
    $this->db->bind(':room_id', $room_id); // bind room id
    $rooms = $this->db->resultSet();
    $returnvalue['success'] = true; // set return value to false
    $returnvalue['error_code'] = 0; // error code - db error
    $returnvalue['data'] = count($rooms); // returned data
    $returnvalue['count'] = count($rooms); // returned count of datasets

    return $returnvalue;
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

  public function setRoomProperty($room_id, $property, $prop_value, $updater_id = 0)
  {
    /* edits a room and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     $property = field name in db
     $propvalue = value for property
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $room_id = $this->converters->checkRoomId($room_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_rooms . ' SET ' . $property . '= :prop_value, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
    // bind all VALUES
    $this->db->bind(':prop_value', $prop_value);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':room_id', $room_id); // room that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Room property " . $property . " changed for id " . $room_id . " to " . $prop_value . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing room property ".$property." for id ".$room_id." to ".$prop_value." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setRoomIdeasDisabled($room_id, $updater_id = 0)
  {

    $room_id = $this->converters->checkRoomId($room_id); // autoconvert id

    $ret_value = $this->setTopicProperty($room_id, "ideas_enabled", 0, $updater_id);

    if ($ret_value['success']) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {

      // error occured
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = $ret_value['error_code']; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  } // end function

  public function setRoomIdeasEnabled($room_id, $updater_id = 0)
  {

    $room_id = $this->converters->checkRoomId($room_id); // autoconvert id

    $ret_value = $this->setTopicProperty($room_id, "ideas_enabled", 1, $updater_id);

    if ($ret_value['success']) {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {

      // error occured
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = $ret_value['error_code']; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  } // end function


  public function checkAccesscode($room_id, $access_code)
  { // access_code = clear text
    /* checks access code and returns database room id (credentials correct) or 0 (credentials not correct)
     */
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('SELECT room_name, id, access_code, hash_id FROM ' . $this->db->au_rooms . ' WHERE id= :id');
    $this->db->bind(':id', $room_id); // bind room id

    $rooms = $this->db->resultSet();

    if (count($rooms) < 1) {
      return 0;
    } // nothing found or empty database

    foreach ($rooms as $room) {
      $db_access_code = $room['access_code'];
      if (password_verify($access_code, $db_access_code)) {
        return $room['id'];
      } else {

        return 0;
      }
    } // end foreach
    $this->syslog->addSystemEvent("Room access code incorrect: " . $room['room_name'], 0, "", 1);
    return 0;
  }// end function


  public function getRooms($offset, $limit, $orderby = 0, $asc = 0, $status = -1, $search_field = "", $search_text = "")
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

  public function getRoomsByUser($user_id, $offset = 0, $limit = 0, $orderby = 0, $asc = 0, $status = -1)
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

    $orderby_field = $this->db->au_users_basedata . "." . $$this->user->getUserOrderId($orderby);

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

    $query = 'SELECT ' . $this->db->au_users_basedata . '.realname, ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_users_basedata . '.id, ' . $this->db->au_users_basedata . '.username, ' . $this->db->au_users_basedata . '.email FROM ' . $this->db->au_rel_rooms_users . ' INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_rel_rooms_users . '.user_id=' . $this->db->au_users_basedata . '.id) WHERE ' . $this->db->au_rel_rooms_users . '.room_id= :room_id ' . $extra_where;

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
    // dleetes all delegations in a specified room
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
    // dleetes all delegations in a specified room
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


  public function emptyRoom($room_id, $idea_delete_option = 0)
  {
    /* deletes all users from a room
    $idea_delete_option = 0 = ideas are archived, 1= ideas are deleted
    */
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

    // sanitize var
    if ($idea_delete_option < 0) {
      $idea_delete_option = 0;
    }
    if ($idea_delete_option > 1) {
      $idea_delete_option = 1;
    }

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_rooms_users . ' WHERE room_id = :roomid');
    $this->db->bind(':roomid', $room_id); // bind room id

    $err = false;
    try {
      $rooms = $this->db->execute(); // do the query
      $room_content_count = $this->db->rowCount();

    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    if ($idea_delete_option == 0) {
      // set ideas to archived from this room
      $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET status=4 WHERE room_id = :roomid'); // set idea status to archived (4)
      $this->db->bind(':roomid', $room_id); // bind room id

      $err = false;
      try {
        $rooms = $this->db->execute(); // do the query


      } catch (Exception $e) {
        $err = true;
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    } else {
      // delete ideas that are associated with this room
      $stmt = $this->db->query('DELETE FROM ' . $this->db->au_ideas . '  WHERE room_id = :roomid'); // delete ideas in this room
      $this->db->bind(':roomid', $room_id); // bind room id

      $err = false;
      try {
        $rooms = $this->db->execute(); // do the query


      } catch (Exception $e) {
        $err = true;
        $returnvalue['success'] = false; // set return value to false
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    }
    //remove delegations from this room

    // remove all delegations in this room
    $this->deleteRoomDelegations($room_id);

    $returnvalue['success'] = true; // set return value to false
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = $room_content_count; // returned data
    $returnvalue['count'] = $room_content_count; // returned count of datasets

    return $returnvalue;


  }// end function

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


  public function editRoom($room_id, $room_name, $description_public = "", $description_internal = "", $internal_info = "", $status = 1, $access_code = "", $order_importance = 10, $updater_id = 0)
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
    $order_importance = intval($order_importance);

    $updater_id = $this->converters->checkUserId($updater_id); // autoconvert
    $room_id = $this->converters->checkRoomId($room_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_rooms . ' SET room_name = :room_name, description_public = :description_public , description_internal= :description_internal, internal_info= :internal_info, status= :status, access_code= :access_code, order_importance= :order_importance, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
    // bind all VALUES
    $this->db->bind(':room_name', $room_name); // name of the room
    $this->db->bind(':description_public', $description_public); // shown in frontend
    $this->db->bind(':description_internal', $description_internal); // only shown in backend admin
    $this->db->bind(':internal_info', $internal_info); // extra internal info, only visible in backend
    $this->db->bind(':status', $status); // status of the room (0=inactive, 1=active, 4=archived)
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
     description_public = actual description of the room, status = status of inserted room (0 = inactive, 1=active)
    */

    $access_code = trim($access_code);
    $hash_access_code = password_hash(trim($access_code), PASSWORD_DEFAULT); // hash access code
    //sanitize in vars
    $restricted = intval($restricted);
    $updater_id = intval($updater_id);
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

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_rooms . ' (room_name, description_public, description_internal, internal_info, status, hash_id, access_code, created, last_update, updater_id, restrict_to_roomusers_only, order_importance, phase_duration_1, phase_duration_2, phase_duration_3, phase_duration_4) VALUES (:room_name, :description_public, :description_internal, :internal_info, :status, :hash_id, :access_code, NOW(), NOW(), :updater_id, :restricted, :order_importance, :phase_duration_0, :phase_duration_1, :phase_duration_2, :phase_duration_3, :phase_duration_4)');
    // bind all VALUES

    $this->db->bind(':room_name', trim($room_name));
    $this->db->bind(':description_public', trim($description_public));
    $this->db->bind(':description_internal', trim($description_internal));
    $this->db->bind(':internal_info', trim($internal_info));
    $this->db->bind(':access_code', $hash_access_code);
    $this->db->bind(':status', $status);
    $this->db->bind(':restricted', $restricted);
    // generate unique hash for this user
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($room_name . $appendix); // create hash id for this user
    $this->db->bind(':hash_id', $hash_id);
    $this->db->bind(':phase_duration_0', $phase_duration_0);
    $this->db->bind(':phase_duration_1', $phase_duration_1);
    $this->db->bind(':phase_duration_2', $phase_duration_2);
    $this->db->bind(':phase_duration_3', $phase_duration_3);
    $this->db->bind(':phase_duration_4', $phase_duration_4);
    
    $this->db->bind(':order_importance', $order_importance); // order parameter
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    $insertid = intval($this->db->lastInsertId());
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Added new room (#" . $insertid . ") " . $room_name, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $insertid; // returned data
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


  public function setRoomStatus($room_id, $status, $updater_id = 0)
  {
    /* edits a room and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     status = status of inserted room (0 = inactive, 1=active)
     updater_id is the id of the room that commits the update (i.E. admin )
    */
    $room_id = $this->converters->checkRoomId($room_id); // checks room  id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_rooms . ' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
    // bind all VALUES
    $this->db->bind(':status', $status);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':room_id', $room_id); // room that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Room status changed " . $room_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing status of room ".$room_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setRoomDescriptionPublic($room_id, $about, $updater_id = 0)
  {
    /* edits a room and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     about (text) -> description of a room
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts user id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_rooms . ' SET description_public= :about, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
    // bind all VALUES
    $this->db->bind(':about', $about);
    $this->db->bind(':updater_id', $updater_id); // id of the room doing the update (i.e. admin)

    $this->db->bind(':room_id', $room_id); // room that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Room description public changed " . $room_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing room description (public) ".$room_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setRoomDescriptionInternal($room_id, $about, $updater_id = 0)
  {
    /* edits a room and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     about (text) -> description of a room
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts user id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_rooms . ' SET description_internal= :about, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
    // bind all VALUES
    $this->db->bind(':about', $about);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':room_id', $room_id); // room that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Room description internal changed " . $room_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing room description internal ".$room_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setRoomname($room_id, $room_name, $updater_id = 0)
  {
    /* edits a room and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
     room_name = name of the room
    */
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db user id if necessary (when room hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_rooms . ' SET room_name= :room_name, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');
    // bind all VALUES
    $this->db->bind(':room_name', $room_name);
    $this->db->bind(':room_id', $room_id); // room that is updated
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Room name changed " . $room_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing name of room ".$room_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setRoomAccesscode($room_id, $access_code, $updater_id = 0)
  {
    /* edits a room and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
     access_code = access code in clear text
    */
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_rooms . ' SET access_code= :access_code, last_update= NOW(), updater_id= :updater_id WHERE id= :room_id');

    // generate access code hash
    $hash = password_hash($access_code, PASSWORD_DEFAULT);
    // bind all VALUES
    $this->db->bind(':access_code', $hash);
    $this->db->bind(':room_id', $room_id); //room that is updated
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Room Access Code changed " . $room_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing access code of room ".$room_id." by ".$updater_id, 0, "", 1);
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
    $mode defines what is to be done. 0=room is deleted only (including relations) 1=room is deleted and users of this room are notified

    */
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

    $room_id = intval ($room_id);

    if ($room_id < 1) {
      # safety check to prevent deletion of main room
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 3; // error code - db error
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    } 

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rooms . ' WHERE id = :id');
    $this->db->bind(':id', $room_id);
    $err = false;
    try {
      $action = $this->db->execute(); // do the query
      $rowcount = intval($this->db->rowCount());

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