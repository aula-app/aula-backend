<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

require_once (__DIR__ . '/../../../config/base_config.php');
require_once "Mail.php";
global $baseHelperDir;
global $baseClassDir;
require_once ($baseHelperDir . 'ResponseBuilder.php');
require_once ($baseClassDir . 'repositories/RoomRepository.php');
require_once ($baseClassDir . 'usecases/users/ResetPasswordForUserUseCase.php');

if ($allowed_include == 1) {

} else {
  exit;
}



class User
{
  # User class provides a collection of methods dealing with everything around the user entity like adding or deleting etc.

  private $responseBuilder;
  private $roomRepository;

  // @TODO: temporarily kept in User model, move after Laravel routing impl.
  private ResetPasswordForUserUseCase $resetPasswordForUserUseCase;

  public function __construct(private $db, private $crypt, private $syslog)
  {
    // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
    $this->db = $db;
    $this->crypt = $crypt;
    $this->syslog = $syslog;
    $this->converters = new Converters($db); // load converters
    $this->roomRepository = new RoomRepository($db);
    $this->responseBuilder = new ResponseBuilder();

    global $email_host;
    global $email_port;
    global $email_username;
    global $email_password;

    $params = array(
      'host' => $email_host,
      'port' => $email_port,
      'auth' => true,
      'username' => $email_username,
      'password' => $email_password
    );

    # deal with sending email to user for welcome mail

    $this->smtp = Mail::factory('smtp', $params);
  }// end function

  private function decrypt($content)
  {
    // decryption helper
    return $content = $this->crypt->decrypt($content);
  }

  public function getUserOrderId($orderby)
  {
    # helper method that provides the db field name based on an int id (for ordering)
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
        return "displayname";
      case 6:
        return "realname";
      case 7:
        return "username";
      case 8:
        return "email";
      case 9:
        return "userlevel";
      case 10:
        return "about_me";
      case 11:
        return "temp_pw";
      default:
        return "last_update";
    }
  }// end function

  public function validSearchField($search_field)
  {
    # helper that defines valid / allowed search fields (db) returns true if ok
    return in_array($search_field, [
      "displayname",
      "realname",
      "username",
      "email",
      "userlevel",
      "about_me"
    ]);
  }

  public function getUserBaseData($user_id)
  {
    /* returns user base data for a specified db id */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('SELECT username, displayname, hash_id, realname, email, about_me, status FROM ' . $this->db->au_users_basedata . ' WHERE id = :id');
    $this->db->bind(':id', $user_id); // bind userid
    $users = $this->db->resultSet();
    if (count($users) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // descrypt the encrypted fields
      /*$users[0]['realname'] = $this->decrypt ($users[0]['realname']);
      $users[0]['displayname'] = $this->decrypt ($users[0]['displayname']);
      $users[0]['username'] = $this->decrypt ($users[0]['username']);
      $users[0]['email'] = $this->decrypt ($users[0]['email']);*/

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $users[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setUserProperty($user_id, $property, $prop_value, $updater_id = 0)
  {
    /* edits a user (user_basedata) and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     $property = field name in db
     $propvalue = value for property
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET ' . $property . '= :prop_value, last_update= NOW(), updater_id= :updater_id WHERE id= :user_id');
    // bind all VALUES
    $this->db->bind(':prop_value', $prop_value);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':user_id', $user_id); // user that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "User property " . $property . " changed for id " . $user_id . " to " . $prop_value . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error changing user property " . $property . " for id " . $user_id . " to " . $prop_value . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function giveConsent($user_id, $text_id, $consent_value = 1, $updater_id = 0)
  {
    /*
    User gives consent to a certain text (i.e. terms of use, privacy terms etc.), coming from text table
    This is usually the first method called when a text is shown the first time . user decides if he wants to comply or reject
    */
    // auto convert
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $text_id = $this->converters->checkTextId($text_id);

    // check if user and topic exist
    $user_exist = $this->converters->checkUserExist($user_id);
    $text_exist = $this->converters->checkTextExist($text_id);

    if ($user_exist == 1 && $text_exist == 1) {
      // everything ok, users and text exists

      // add relation to database (consent)

      $stmt = $this->db->query('INSERT INTO ' . $this->db->au_consent . ' (user_id, text_id, consent, status, created, last_update, date_consent, updater_id) VALUES (:user_id, :text_id, :consent, 1, NOW(), NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE user_id = :user_id, text_id = :text_id, consent = :consent, status = 1, last_update = NOW(), updater_id = :updater_id');

      // bind all VALUES
      $this->db->bind(':text_id', $text_id); // id of the text that the user consents to
      $this->db->bind(':user_id', $user_id); // gives consent
      $this->db->bind(':consent', $consent_value); // consent type 0 = no consent, 1 = consent given
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        // set consent cache value in db - increment
        if ($this->converters->getTextConsentValue($text_id) == 2 && $consent_value == 1) {
          $this->changeGivenConsent($user_id, 1);
        }

        $this->syslog->addSystemEvent(0, "Added consent for user " . $user_id . " for text " . $text_id, 0, "", 1);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; //db  error code
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; //db  error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;

      }

    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; //db  error code
      $returnvalue['data'] = $user_exist . $text_exist; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }

  } // end function

  public function getDelegationStatus($user_id, $topic_id)
  {
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $topic_id = $this->converters->checkTopicId($topic_id);

    # helper - returns the status of delegation for a defined user and topic
    $stmt = $this->db->query('SELECT ' . $this->db->au_delegation . '.*, ' . $this->db->au_users_basedata . '.hash_id as delegate_hash_id, ' . $this->db->au_users_basedata . '.realname as delegate_realname, ' . $this->db->au_users_basedata . '.displayname as delegate_displayname FROM ' . $this->db->au_users_basedata . ' LEFT JOIN ' . $this->db->au_delegation . ' ON (' . $this->db->au_users_basedata . '.id = ' . $this->db->au_delegation . '.user_id_target) WHERE ' . $this->db->au_delegation . '.user_id_original = :user_id AND ' . $this->db->au_delegation . '.topic_id = :topic_id');

    // bind all VALUES
    $this->db->bind(':topic_id', $topic_id);
    $this->db->bind(':user_id', $user_id); // user original id that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    $data = $this->db->resultSet();
    $count_data = intval($this->db->rowCount());

    if (!$err) {
      $this->syslog->addSystemEvent(0, "Delegation status retrieved: user_id: " . $user_id . ", topic_id: " . $topic_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $data; // returned data
      $returnvalue['count'] = $count_data; // returned count of datasets

      return $returnvalue;

    } else {
      $this->syslog->addSystemEvent(1, "Error retrieving delegation status for user " . $user_id . " and topic " . $topic_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }

  public function setDelegationStatus($user_id, $status, $topic_id = 0, $target = 0)
  {
    /* edits the status of a delegation and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     status = status for delegation (0 = inactive, 1=active, 2 = suspended, 4 = archived)
     if topic_id = 0 all delegations of the user are deleted
     target specifies if original or target users are adressed 0 = remove delegations of delegating user (original owner->default) 1= remove delegation of target user
    */

    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $topic_id = $this->converters->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $topic_clause = "";

    if ($topic_id > 0) {
      // topic id is set to >0 -> delete only delegations for this user for the specified topic
      $topic_clause = " AND topic_id = " . $topic_id;
    }

    $target_user = "user_id_original";
    if ($target > 0) {
      $target_user = "user_id_target";
    }

    $stmt = $this->db->query('UPDATE ' . $this->db->au_delegation . ' SET status = :status, last_update = NOW() WHERE ' . $target_user . ' = :user_id' . $topic_clause);
    // bind all VALUES
    $this->db->bind(':status', $status);

    $this->db->bind(':user_id', $user_id); // user original id that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    $count_data = intval($this->db->rowCount());

    if (!$err) {
      $this->syslog->addSystemEvent(0, "Delegation status changed " . $user_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $count_data; // returned data
      $returnvalue['count'] = $count_data; // returned count of datasets

      return $returnvalue;

    } else {
      $this->syslog->addSystemEvent(1, "Error changing delegation status of user " . $user_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getReceivedDelegations($user_id, $topic_id)
  {
    /* returns received delegations for a specific user (user_id) in the topic
     */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $topic_id = $this->converters->checkTopicId($topic_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $query = <<<EOD
      SELECT
          u.hash_id,
          u.displayname,
          u.status,
          u.realname,
          u.username
      FROM
        {$this->db->au_users_basedata} u
      LEFT JOIN
        {$this->db->au_delegation} d
      ON
        (u.id = d.user_id_original)
      WHERE
        d.user_id_target = :id AND d.topic_id = :topic_id
    EOD;

    $stmt = $this->db->query($query);
    $this->db->bind(':id', $user_id); // bind userid
    $this->db->bind(':topic_id', $topic_id); // bind topic id
    $users = $this->db->resultSet();

    if (count($users) < 1) {

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $users; // returned data
      $returnvalue['count'] = count($users); // returned count of datasets

      return $returnvalue;
    }
  } // end function

  public function getMissingConsents($user_id)
  {
    // returns all the missing consents that this user has not yet consented / reacted to

    $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)
    // first get all the mandatory consents for this user already given (consent =1)

    $stmt = $this->db->query('SELECT ' . $this->db->au_texts . '.id FROM ' . $this->db->au_texts . ' INNER JOIN ' . $this->db->au_consent . ' ON (' . $this->db->au_consent . '.text_id = ' . $this->db->au_texts . '.id) WHERE (' . $this->db->au_consent . '.user_id = :user_id AND ' . $this->db->au_consent . '.consent <> 0) AND ' . $this->db->au_texts . '.status = 1');
    $this->db->bind(':user_id', $user_id); // bind userid

    $consents = $this->db->resultSet();
    $given_consents = count($consents);

    $i = 0;
    $ids[0] = 0;

    foreach ($consents as $key) {
      $ids[$i] = $key['id'];
      $i++;
    }

    $stmt = $this->db->query('SELECT ' . $this->db->au_texts . '.id, ' . $this->db->au_texts . '.headline, ' . $this->db->au_texts . '.body, ' . $this->db->au_texts . '.consent_text, ' . $this->db->au_texts . '.user_needs_to_consent, ' . $this->db->au_consent . '.consent FROM ' . $this->db->au_texts . ' LEFT JOIN ' . $this->db->au_consent . ' ON (' . $this->db->au_texts . '.id = ' . $this->db->au_consent . '.text_id AND '. $this->db->au_consent .'.user_id = :user_id) WHERE ' . $this->db->au_texts . '.id NOT IN (' . implode(",", $ids) . ') AND ' . $this->db->au_texts . '.status = 1');
    $this->db->bind(':user_id', $user_id); // bind userid

    $texts = $this->db->resultSet();
    $missing_consents = count($texts);
    if ($missing_consents < 1) {
      // no consents missing...
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 0; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    // everything ok, return 1
    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = $texts; // returned data
    $returnvalue['count'] = $missing_consents; // returned count of datasets

    return $returnvalue;

  } // end function

  public function checkHasUserGivenConsentsForUsage($user_id)
  {
    /* returns 1 if user has consented to all necessary texts
    that are needed for aula usage 0, if not
    */

    $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)
    // first get all the texts that need consents that are crucial for system usage

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_texts . ' WHERE status = 1 AND user_needs_to_consent = 2');

    $texts = $this->db->resultSet();
    $needed_consents = count($texts);

    if (count($texts) < 1) {
      // no texts that need consent present
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // There are texts that need consent - now check if user has given all necessary consents
      $i = 0;
      $ids[0] = 0;

      foreach ($texts as $key) {
        $ids[$i] = $key['id'];
        $i++;
      }

      $stmt = $this->db->query('SELECT text_id FROM ' . $this->db->au_consent . ' WHERE user_id = :user_id AND text_id IN (' . implode(",", $ids) . ') AND consent = 1');
      $this->db->bind(':user_id', $user_id); // bind userid

      $consents = $this->db->resultSet();
      $given_consents = count($consents);
      if ($given_consents < $needed_consents) {
        // not every necessary consent given yet, return 0
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = 0; // returned data
        $returnvalue['count'] = $given_consents; // returned count of datasets

        return $returnvalue;
      }
      // everything ok, return 1
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = $given_consents; // returned count of datasets

      return $returnvalue;
    }
  } // end function

  public function giveBackAllDelegations($user_id, $topic_id = 0)
  {
    // give back all delegations for a) a certain topic (topic id>0) or all delegations (topic_id=0)
    return $this->removeUserDelegations($user_id, $topic_id, 1); // 1 at the end indicates that target user is meant
  }

  public function removeSpecificDelegation($user_id_target, $user_id_original, $topic_id = 0)
  {
    /* remove delegation from a specific user A (user_id_original) to a specific user B (user_id_target) for
    a) a certain topic (topic id>0) or all delegations (topic=0), defaults to all topics
    */
    $user_id_target = $this->converters->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $user_id_original = $this->converters->checkUserId($user_id_original); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $topic_id = $this->converters->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $topic_clause = "";

    if ($topic_id > 0) {
      // topic id is set to >0 -> delete only delegations for this user in the specified topic
      $topic_clause = " AND topic_id = " . $topic_id;
    }

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_delegation . ' WHERE user_id_target = :user_id_target AND user_id_original = :user_id_original' . $topic_clause);
    $this->db->bind(':user_id_target', $user_id_original);
    $this->db->bind(':user_id_original', $user_id_target);

    $err = false;
    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "User delegation(s) deleted with id " . $user_id_target . " for topic " . $topic_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = true; // returned data
      $returnvalue['count'] = intval($this->db->rowCount()); // returned count of datasets

      return $returnvalue;

    } else {
      $this->syslog->addSystemEvent(1, "Error deleting user delegation(s) with id " . $user_id_target . " for topic " . $topic_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  }

  public function removeUserDelegations($user_id, $topic_id = 0, $target = 0)
  {
    /* removes all delegations of a specified user (user id) for a specified topic, accepts db id or hash id
     if topic_id = 0 all delegations of the user are deleted
     target specifies if original or target users are adressed 0 = remove delegations of delegating user (original owner->default) 1= remove delegation of target
    */

    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $topic_id = $this->converters->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $topic_clause = "";

    if ($topic_id > 0) {
      // topic id is set to >0 -> delete only delegations for this user in the specified topic
      $topic_clause = " AND topic_id = " . $topic_id;
    }

    $target_user = "user_id_original";
    if ($target > 0) {
      $target_user = "user_id_target";
    }

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_delegation . ' WHERE topic_id = :topic_id AND user_id_original = :id');
    $this->db->bind(':id', $user_id);
    $this->db->bind(':topic_id', $topic_id);
    $err = false;
    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    $count_data = intval($this->db->rowCount());

    if (!$err) {
      $this->syslog->addSystemEvent(0, "User delegation(s) deleted with id " . $user_id . " for topic " . $topic_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $count_data; // returned data
      $returnvalue['count'] = $count_data; // returned count of datasets

      return $returnvalue;

    } else {
      $this->syslog->addSystemEvent(1, "Error deleting user delegation(s) with id " . $user_id . " for topic " . $topic_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;


    }

  } // end function

  public function getDefaultRole($user_id)
  {
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $stmt = $this->db->query('SELECT userlevel FROM ' . $this->db->au_users_basedata . ' WHERE id = :user_id');
    $this->db->bind(':user_id', $user_id);
    $userlevel = $this->db->resultSet()[0]['userlevel'];

    return $userlevel;
  }

  public function addUserToRoom($user_id, $room_id, $status = 1, $updater_id = 0)
  {
    /* adds a user to a room, accepts user_id (by hash or id) and room id (by hash or id)
    returns 1,1 = ok, 0,1 = user id not in db 0,2 room id not in db 0,3 user id not in db room id not in db */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)
    // check if user and room exist
    $user_exist = $this->converters->checkUserExist($user_id);
    $room_exist = $this->converters->checkRoomExist($room_id);

    if ($user_exist == 1 && $room_exist == 1) {
      // everything ok, user and room exists
      // add relation to database

      $userlevel = $this->getDefaultRole($user_id);
      $this->addUserRole($user_id, $userlevel, $room_id);
      $this->setRefresh($user_id, true);

      $stmt = $this->db->query('INSERT INTO ' . $this->db->au_rel_rooms_users . ' (room_id, user_id, status, created, last_update, updater_id) VALUES (:room_id, :user_id, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE room_id = :room_id, user_id = :user_id, status = :status, last_update = NOW(), updater_id = :updater_id');

      // bind all VALUES
      $this->db->bind(':room_id', $room_id);
      $this->db->bind(':user_id', $user_id);
      $this->db->bind(':status', $status);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $insertid = intval($this->db->lastInsertId());
        $this->syslog->addSystemEvent(0, "Added user " . $user_id . " to room " . $room_id, 0, "", 1);

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = $insertid; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {
        $this->syslog->addSystemEvent(0, "Error while adding user " . $user_id . " to room " . $room_id, 0, "", 1);

        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function

  public function getStandardRoom()
  {
    // returns the id for the standard room (AULA room)
    $room_id = 0; // default to 0
    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_rooms . ' WHERE type = 1 LIMIT 1');
    $room = $this->db->resultSet();

    $room_id = $room[0]['id']; // get room id from db

    return $room_id;

  }

  public function addUserToStandardRoom($user_id, $status = 1, $updater_id = 0, $use_transaction = true)
  {
    /* adds a user to a room, accepts user_id (by hash or id) and room id (by hash or id)
    returns 1,1 = ok, 0,1 = user id not in db 0,2 room id not in db 0,3 user id not in db room id not in db */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    // check if user and room exist
    $user_exist = $this->converters->checkUserExist($user_id);

    // get the id for the standard room
    $room_id = $this->getStandardRoom();
    // check if the room actually exists
    $room_exist = $this->converters->checkRoomExist($room_id);

    if ($user_exist == 1 && $room_exist == 1) {
      // everything ok, user and room exists
      // add relation to database

      $userlevel = 20; // $this->getDefaultRole($user_id);
      $this->addUserRole($user_id, $userlevel, $room_id, $use_transaction);

      $stmt = $this->db->query('INSERT INTO ' . $this->db->au_rel_rooms_users . ' (room_id, user_id, status, created, last_update, updater_id) VALUES (:room_id, :user_id, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE room_id = :room_id, user_id = :user_id, status = :status, last_update = NOW(), updater_id = :updater_id');

      // bind all VALUES
      $this->db->bind(':room_id', $room_id);
      $this->db->bind(':user_id', $user_id);
      $this->db->bind(':status', $status);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $insertid = intval($this->db->lastInsertId());
        $this->syslog->addSystemEvent(0, "Added user " . $user_id . " to standard room " . $room_id, 0, "", 1);

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = $insertid; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {
        $this->syslog->addSystemEvent(0, "Error while adding user " . $user_id . " to standard room " . $room_id, 0, "", 1);

        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function

  public function removeUserFromRoom($room_id, $user_id)
  {
    /* deletes a user from a room
     */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_rooms_users . ' WHERE user_id = :userid AND room_id = :roomid');
    $this->db->bind(':roomid', $room_id); // bind room id
    $this->db->bind(':userid', $user_id); // bind user id

    $err = false;
    try {
      $rooms = $this->db->resultSet();
      $rowcount = $this->db->rowCount();

      $this->deleteUserRole($user_id, $room_id);
      $this->setRefresh($user_id, true);
    } catch (Exception $e) {
      error_log('Error occurred while deleting user ' . $user_id . ' from room: ' . $room_id . ': ' . $e->getMessage()); // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
    // remove delegations for this user
    // todo -> remove delegations from this user
    //  $this->deleteRoomUserDelegations ($room_id, $user_id);
    // remove all delegations for this user
    $this->removeUserDelegations($user_id, 0, 0); // active delegations (original user)
    $this->removeUserDelegations($user_id, 0, 1); // passive delegations (target user)

    $this->deleteUserRole($user_id, $room_id);

    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = false; // returned data
    $returnvalue['count'] = 1; // returned count of datasets

    return $returnvalue;


  }// end function

  public function relateUser($user_id, $user_id_target, $status = 1, $updater_id = 0, $type = 1)
  {
    /*
    user A (user_id) follows user B (user_id_target), type = 1 => follow /  type = 2 => friend / type = 0 => blocked
     */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $user_id_target = $this->converters->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    // check if users exist
    $user_exist = $this->converters->checkUserExist($user_id);
    $user_exist_target = $this->converters->checkUserExist($user_id_target);

    if ($user_exist == 1 && $user_exist_target == 1) {
      // everything ok, both users exist
      // add relation to database

      $stmt = $this->db->query('INSERT INTO ' . $this->db->au_rel_user_user . ' (user_id1, user_id2, type, status, created, last_update, updater_id) VALUES (:user_id1, :user_id2, :type, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE user_id1 = :user_id1, user_id2 = :user_id2, type = :type, status = :status, last_update = NOW(), updater_id = :updater_id');

      // bind all VALUES
      $this->db->bind(':user_id1', $user_id);
      $this->db->bind(':user_id2', $user_id_target);
      $this->db->bind(':status', $status);
      $this->db->bind(':type', $type);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Added user relation (type:" . $type . ") " . $user_id . "-" . $user_id_target, 0, "", 1);
        $insertid = intval($this->db->lastInsertId());
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = $insertid; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {
        $this->syslog->addSystemEvent(0, "Error while adding user relation (type:" . $type . ") " . $user_id, 0, "", 1);

        $this->syslog->addSystemEvent(0, "Added user relation (type:" . $type . ") " . $user_id . "-" . $user_id_target, 0, "", 1);
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function

  public function removeUserRelation($user_id, $user_id_target)
  {
    /* deletes a user relation from the db
     */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $user_id_target = $this->converters->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_user_user . ' WHERE user_id1 = :user_id1 AND user_id2 = :user_id2');
    $this->db->bind(':user_id1', $user_id); // bind user id
    $this->db->bind(':user_id2', $user_id_target); // bind user id

    $err = false;
    try {
      $users = $this->db->execute();
      $rowcount = $this->db->rowCount();
    } catch (Exception $e) {
      error_log('Error occurred while removing relation between user ' . $user_id . ' and user ' . $user_id_target . ': ' . $e->getMessage()); // display error
      //$this->syslog->addSystemEvent(0, "Error while removing user relation (delete from db) ".$user_id."-".$user_id_target, 0, "", 1);
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $this->syslog->addSystemEvent(0, "Removed user relation (delete from db) " . $user_id . "-" . $user_id_target, 0, "", 1);
    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = 1; // returned data
    $returnvalue['count'] = 1; // returned count of datasets

    return $returnvalue;

  }// end function

  public function removeUserFromGroup($group_id, $user_id)
  {
    /* deletes a user from a group
     */

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_groups_users . ' WHERE user_id = :userid AND group_id = :groupid');
    $this->db->bind(':groupid', $group_id); // bind group id
    $this->db->bind(':userid', $user_id); // bind user id

    $err = false;
    try {
      $groups = $this->db->resultSet();
    } catch (Exception $e) {
      error_log('Error occurred while removing user from group: ' . $e->getMessage()); // display error
      $err = true;

      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = $this->db->rowCount(); // returned data
    $returnvalue['count'] = $this->db->rowCount(); // returned count of datasets

    return $returnvalue;

  }// end function

  public function addCSV($csv, $room_id, $user_level = 20, $separator = ";")
  {
    # parses CSV string and creates new users , defaults to user level 20 (student), separator defaults to semicolon
    # CSV must be in the following format:
    # realname;displayname;username;email;about_me; room_id
    # email, about_me, displayname are not mandatory (can be empty in CSV), realname and username are mandatory
    # if no email is provided then a temp password is generated for the user
    # no first line with field names!
    # linebreak must be \n

    # init output array
    $output_user = [];
    $line_counter = 0;
    $real_name = "";
    $display_name = "";
    $email = "";
    $about_me = "";

    if (strlen($csv) > 1 && str_contains($csv, ';')) {
      # basic check of CSV
      $csv_lines = explode("\n", $csv);

      foreach ($csv_lines as $line) {
        $data = str_getcsv($line, $separator);
        $line_counter++;

        $real_name = trim($data[0]);
        $display_name = trim($data[1]);
        $user_name = trim($data[2]);
        $email = strtolower(trim($data[3]));
        $about_me = trim($data[4]);

        // check if user name is still available
        $user_ok = false;
        $attempts = 0;
        $base_user_name = $user_name;


        while ($user_ok == false && $attempts < 100) {
          $temp_user = $this->checkUserExistsByUsername($user_name, $email); // check username / email in db

          $attempts++; # increment attempts to find a proper username

          if ($temp_user['count'] > 0) {
            # user exists, hence not OK
            $user_ok = false;
            # retry with altered username
            $suffix = $this->generate_pass(3);
            $user_name = $base_user_name . "_" . $suffix;
          } else {
            $user_ok = true;
            # add user to db
            $data = $this->addUser($real_name, $display_name, $user_name, $email, "", 1, $about_me, 99, $user_level);
            $insert_id = $data['data']['insert_id'];
            # add to set room
            if (isset($room_id) && $room_id > 0) {
              $this->addUserToRoom($insert_id, $room_id);
            }

            $user_array['real_name'] = $real_name;
            $user_array['display_name'] = $display_name;
            $user_array['user_name'] = $user_name;
            $user_array['email'] = $email;
            $user_array['about_me'] = $about_me;

            array_push($output_user, $user_array);
          }
        }

      } // end foreach
    } else {
      # error occurs on CSV parsing
      $err = true;

      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code (no data in csv or malformed)
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    # return the array after import
    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code 0 = everything ok
    $returnvalue['data'] = $output_user; // returned data
    $returnvalue['count'] = $line_counter; // returned count of datasets

    return $returnvalue;
  } # end function

  public function addAllCSV($csv, $room_ids, $user_level = 20, $updater_id = 0, $separator = ";", $send_emails_at = null)
  {
    /*
      Parses CSV and adds all users to all rooms. If a User already exists with the same fields, its user_id is reused. If a User exists with some of the fields from CSV not matching the ones in the database, this is an error and the whole operation will not be committed.
     */

    try {
      $rooms = $this->roomRepository->getRoomsByHashIds($room_ids);
      if (count($rooms) != count($room_ids)) {
        return $this->responseBuilder->error(
          errorDescription: "Validation of Room ids failed. Make sure all Rooms are existing."
        );
      }
    } catch (Exception $exception) {
      return $this->responseBuilder->error(errorDescription: $exception->getMessage());
    }

    try {
      $csv_lines = explode("\n", $csv);
      if (
        empty($csv_lines)
          || !str_contains($csv, $separator)
          || (count($csv_lines) == 1 && $csv_lines[0] == "realname;displayname;username;email;about_me")
      ) {
        return $this->responseBuilder->error(errorDescription: "CSV file is in bad format.");
      }

      // Parse CSV into array of Users
      $csvUsers = array_map(function($line) use ($separator) {
        $data = str_getcsv($line, $separator);
        $email = strtolower(trim($data[3]));
        $isValidEmail = (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
        return [
          'realname' => trim($data[0]),
          'displayname' => trim($data[1]),
          'username' => trim($data[2]),
          'email' => $isValidEmail ? $email : null,
          'about_me' => trim($data[4])
        ];
      }, $csv_lines);

      $errors = array();
      $warnings = array();
      $addedUsers = array();
      $existingUsers = array();
      $lineNumber = 0;

      $this->db->beginTransaction("SERIALIZABLE");
      foreach ($csvUsers as $csvUser) {
        $csvUser = $this->validateOrDeriveUsername($csvUser);

        // Check if user exists with row-level locking
        $existingUser = $this->getUserForUpdate($csvUser['username'], $csvUser['email']);

        if (!$existingUser) {
          $csvUser['id'] = $this->addUserInternal($csvUser, $updater_id);
          $addedUsers[] = $csvUser;
        } else {
          if ($this->isSameUser($csvUser, $existingUser)) {
            // Fields are equal, reuse user_id
            $warnings[] = $csvUser['username'];
            $csvUser['id'] = $existingUser['id'];
            $existingUsers[] = $csvUser;
          } else {
            // Fields do not match, flag as error
            $collisionKeys = array_filter(['username', 'email'], function ($key) use ($csvUser, $existingUser) {
              return $csvUser[$key] === $existingUser[$key] && !is_null($csvUser[$key]);
            });
            $errors[] = [
              'collision_keys' => $collisionKeys,
              'line_number' => $lineNumber,
            ];
            $lineNumber++;
            continue; // Skip to the next user
          }
        }

        foreach ($rooms as $room) {
          $this->roomRepository->insertOrUpdateUserToRoom($room['id'], $csvUser['id'], $updater_id);
          $this->addUserRole($csvUser['id'], $user_level, $room['id'], false);
        }
        $this->addUserToStandardRoom($csvUser['id'], 1, 0, false);
        $lineNumber++;
      }
    } catch (Exception $e) {
      echo $e;
      $this->db->rollBackTransaction();
      error_log("Error parsing CSV: " . $e->getMessage() . "\n" . $e->getTraceAsString());
      return $this->responseBuilder->error(2);
    }

    if (empty($errors)) {
      $this->syslog->addSystemEvent(0, "Imported CSV users " . json_encode($csvUsers), 0, "", 1);
      $this->addChangePasswordForUpdateAndScheduleSendEmail(array_filter($addedUsers, function ($user) {
        return $user['email'] != null;
      }), $updater_id, $send_emails_at);
      $this->db->commitTransaction();

      // @TODO: nikola - return response with warnings and data
      return $this->responseBuilder->success(array_merge($addedUsers, $existingUsers));
    } else {
      $this->db->rollBackTransaction();
      return $this->responseBuilder->error(1, "Usernames or Emails already exist with different data.", errors: $errors);
    }
  }

  private function validateOrDeriveUsername(array $user)
  {
    /* If username is present, return source object.
     * Else, try extracting the username from email (part before @).
     * Else, try extracting it from displayname or realname (first and the last name).
     * Else, throw an exception */
    if ($user['username'] === null || strlen($user['username']) == 0) {
      $newUser = [...$user];
      if ($user['email'] != null) {
        $newUser['username'] = strtolower(explode("@", $user['email'])[0]);
      } else {
        $src = current(array_filter([$user['displayname'], $user['realname']]));
        if ($src == null) {
          error_log("Cannot derive username from other data " . json_encode($user));
          throw new RuntimeException("Username is missing and cannot be derived from other data");
        }
        $arr = explode(' ', $src);
        if (count($arr) > 1) {
          $newUser['username'] = strtolower(mb_strimwidth($arr[0], 0, 10) . '.' . mb_strimwidth(end($arr), 0, 10));
        } else {
          $newUser['username'] = strtolower(mb_strimwidth($arr[0], 0, 16));;
        }
      }
      return $newUser;
    }

    return $user;
  }

  private function addChangePasswordForUpdateAndScheduleSendEmail(array $users, $updater_id = 0, $send_at = null)
  {
    $numberOfUsers = count($users);
    if ($numberOfUsers == 0) return ;

    // Generate all the secrets
    $secrets = array();
    while (count($secrets) < $numberOfUsers) {
      $secret = bin2hex(random_bytes(32));
      $secretExists = $this->db->prepareStatement('SELECT user_id FROM au_change_password WHERE secret = :secret FOR UPDATE');
      $secretExists->execute([':secret' => $secret]);
      $rows = $secretExists->fetchAll(PDO::FETCH_ASSOC);
      if (count($rows) == 0) {
        $secrets[] = $secret;
      }
    }

    // Prepare values for bulk insertion
    $values = array();
    for ($i = 0; $i < $numberOfUsers; $i++) {
      $values[] = $users[$i]['id'];
      $values[] = $secrets[$i];
    }

    // Bulk insert change_password rows into database
    $placeholders = implode(',', array_fill(0, $numberOfUsers, '(?,?,NOW())'));
    $stmt = $this->db->prepareStatement(<<<EOD
      INSERT INTO au_change_password (user_id, secret, created_at)
      VALUES {$placeholders}
      ON DUPLICATE KEY UPDATE created_at = NOW()
    EOD);
    $stmt->execute($values);

    // Prepare values for bulk insertion
    $sendAtAsMariadbFormat = date_format(date_create($send_at), 'Y-m-d H:i:s');
    $values = array();
    for ($i = 0; $i < $numberOfUsers; $i++) {
      if (!(bool) filter_var($users[$i]['email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("User's email field has invalid value");
      }
      $values[] = "userCreated;{$users[$i]['email']};{$users[$i]['realname']};{$users[$i]['username']};{$secrets[$i]}";
      $values[] = $sendAtAsMariadbFormat;
      $values[] = $users[$i]['id'];
      $values[] = $updater_id;
      $values[] = $updater_id;
    }

    // Bulk insert scheduled commands into database
    // cmd_id = 11 (1 - user, 1 - sendEmail)
    $placeholders = implode(',', array_fill(0, $numberOfUsers, '(11,"sendEmail",?,?,1,0,?,?,NOW(),NOW(),?)'));
    $stmt = $this->db->prepareStatement(<<<EOD
      INSERT INTO au_commands
        (cmd_id, command, parameters, date_start, active, status, target_id, creator_id, created, last_update, updater_id)
      VALUES
        {$placeholders}
      ON DUPLICATE KEY UPDATE last_update = NOW()
    EOD);
    $stmt->execute($values);
  }

  private function getUserForUpdate($username, $email)
  {
    $checkUserStmt = $this->db->prepareStatement("SELECT * FROM {$this->db->au_users_basedata} WHERE username = :username OR (:email IS NOT NULL AND email = :email) FOR UPDATE");
    $checkUserStmt->execute([':username' => $username, ':email' => $email]);
    return $checkUserStmt->fetch(PDO::FETCH_ASSOC);
  }

  private function isSameUser($user, $existingUser): bool
  {
    $fieldsMatch = true;
    foreach ($user as $key => $value) {
      if ($existingUser[$key] !== $value) {
        $fieldsMatch = false;
        break;
      }
    }
    return $fieldsMatch;
  }

  private function addUserInternal($user, $updater_id): int
  {
    $insertUserStmt = $this->db->prepareStatement("INSERT INTO {$this->db->au_users_basedata} (realname, displayname, username, email, about_me, temp_pw, hash_id, pw_changed, status, created, last_update, creator_id, updater_id, userlevel) VALUES (:realname, :displayname, :username, :email, :about_me, :temp_pw, :hash_id, 0, 1, NOW(), NOW(), :updater_id, :updater_id, 20)");
    $send_email = $user['email'] != null;
    // if no email is provided, generate a temporary password
    $temp_pw = $send_email ? "" : $this->generate_pass(8);
    // generate unique hash for this user
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($user['username'] . $appendix);
    // User does not exist, insert new user
    $insertUserStmt->execute([
      ':realname' => $this->crypt->encrypt($user['realname']),
      ':displayname' => $this->crypt->encrypt($user['displayname']),
      ':username' => $this->crypt->encrypt($user['username']),
      ':email' => $user['email'] != null ? $this->crypt->encrypt($user['email']) : null,
      ':about_me' => $this->crypt->encrypt($user['about_me']),
      ':temp_pw' => $temp_pw,
      ':hash_id' => $hash_id,
      ':updater_id' => $updater_id,
    ]);
    return $this->db->lastInsertId();
  }

  public function addUserToGroup($user_id, $group_id, $updater_id, $status = 1)
  {
    /* adds a user to a group, accepts user_id (by hash or id) and group id (by hash or id)
    returns 1,1 = ok, 0,1 = user id not in db 0,2 group id not in db 0,3 user id not in db group id not in db */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $group_id = $this->converters->checkGroupId($group_id); // checks id and converts id to db id if necessary (when hash id was passed)
    // check if user and group exist
    $user_exist = $this->converters->checkUserExist($user_id);
    $group_exist = $this->converters->checkGroupExist($group_id);

    if ($user_exist == 1 && $group_exist == 1) {
      // everything ok, user and group exists
      // add relation to database

      $stmt = $this->db->query('INSERT INTO ' . $this->db->au_rel_groups_users . ' (group_id, user_id, status, created, last_update, updater_id) VALUES (:group_id, :user_id, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE group_id = :group_id, user_id = :user_id, status = :status, last_update = NOW(), updater_id = :updater_id');

      // bind all VALUES
      $this->db->bind(':group_id', $group_id);
      $this->db->bind(':user_id', $user_id);
      $this->db->bind(':status', $status);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query


      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Added user " . $user_id . " to group " . $group_id, 0, "", 1);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {
        $this->syslog->addSystemEvent(0, "Error while adding user " . $user_id . " to group " . $group_id, 0, "", 1);

        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  } // end function

  public function checkLogin($username, $pw)
  {
    # helper method - takes username and pw, returns true if credentials are of, false if not
    $check_credentials = $this->checkCredentials($username, $pw);

    if ($check_credentials['error_code'] == 2) {
      return $check_credentials;
    }

    if ($check_credentials['success'] && $check_credentials['data'] && $check_credentials['count'] == 1 && ($check_credentials['error_code'] == 0 || $check_credentials['error_code'] == 2)) {
      // credentials are ok, set last login in db
      $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET last_login = NOW() WHERE id = :user_id');
      $user = $check_credentials['data'];
      $this->db->bind(':user_id', $check_credentials['user_id']); // bind user id
      $err = false;
      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }
      if (!$err) {
        $insertid = intval($this->db->lastInsertId());
        $this->syslog->addSystemEvent(0, "Successful login user " . $username, 0, "", 1);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = $user; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;


      } else {
        $this->syslog->addSystemEvent(1, "DB Error login user " . $username, 0, "", 1);
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // db error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;

      }
    } else {
      $this->syslog->addSystemEvent(1, "DB Error login user " . $username, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }// end if
  } // end function

  public function getReactivationDate($user_id)
  {
    # returns the reactivation date for a suspended user - checks the commands table if there is a reactivation command (cmd_id = 40). In this case
    # the method returns the date when the user is reactivated (status back to 1). If there is no reactivation command the method returns false
    $reactivation_date = false; # init

    $count_datasets = 0; // number of datasets retrieved
    $stmt = $this->db->query('SELECT date_start FROM ' . $this->db->au_commands . ' WHERE target_id = :target_id AND active= 1 AND cmd_id = 10 ORDER BY date_start DESC LIMIT 1');
    try {
      $this->db->bind(':target_id', $user_id); // set user id
      $res = $this->db->resultSet();
      $reactivation_date = $res[0]['date_start'];
    } catch (Exception $e) {
      error_log('Error occurred while getting reactivation date for user ' . $user_id . ': ' . $e->getMessage());
    }

    return $reactivation_date;
  }

  public function setRefresh($user_id, $refresh_value = true)
  {
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET refresh_token = :refresh_value WHERE id = :user_id ');
    try {
      $this->db->bind(':user_id', $user_id);
      $this->db->bind(':refresh_value', $refresh_value);

      $users = $this->db->execute();
    } catch (Exception $e) {
      error_log('Error occurred while setting refresh toke for user ' . $user_id . ' to ' . $refresh_value . ': ' . $e->getMessage());
    }
  }

  public function getUserPayload($user_id)
  {

    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('SELECT id, userlevel, temp_pw, hash_id, status, roles FROM ' . $this->db->au_users_basedata . ' WHERE id = :user_id ');
    try {
      $this->db->bind(':user_id', $user_id);
      $users = $this->db->resultSet();
      $user_status = $users[0]['status'];
      $user_id = $users[0]['id'];

    } catch (Exception $e) {
      error_log('Error occurred while getting user payload for user ' . $user_id . ': ' . $e->getMessage());
    }

    $reactivation_date = false; // init

    if ($user_status != 1) {
      # get the reactivation date (if there is one) when the user is suspended (status = 2)
      $reactivation_date = $this->getReactivationDate($user_id);
    }

    if (count($users) < 1 || $user_status != 1) {
      # user is either non-existent or not active (status = 0) or suspended (status = 2) or archived (status > 2)
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['user_status'] = $user_status; // error code
      $returnvalue['user_id'] = $user_id;
      $returnvalue['data'] = $reactivation_date; // returned data
      $returnvalue['count'] = count($users); // returned count of datasets

      return $returnvalue;
    } // nothing found, empty database or non active user

    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['user_id'] = $user_id;
    $returnvalue['data'] = $users[0]; // returned data
    $returnvalue['count'] = 1; // returned count of datasets

    return $returnvalue;

  }


  public function checkCredentials($username, $pw)
  {
    /* helper for method checkLogin ()
    checks credentials and returns database user id (credentials correct) or 0 (credentials not correct)
    username is clear text
    pw is clear text
    */

    $user_status = 0;
    $user_id = 0;

    $stmt = $this->db->query('SELECT id, username, pw, refresh_token, temp_pw, userlevel, hash_id, status, roles FROM ' . $this->db->au_users_basedata . ' WHERE username = :username ');
    try {
      $this->db->bind(':username', $username);
      $users = $this->db->resultSet();
      $user_status = $users[0]['status'];
      $user_id = $users[0]['id'];

    } catch (Exception $e) {
      error_log('Error occurred while checking credentials for user ' . $user_id . ': ' . $e->getMessage());
    }

    $reactivation_date = false; // init

    if ($user_status != 1) {
      # get the reactivation date (if there is one) when the user is suspended (status = 2)
      $reactivation_date = $this->getReactivationDate($user_id);
    }

    if (count($users) < 1 || $user_status != 1) {
      # user is either non-existent or not active (status = 0) or suspended (status = 2) or archived (status > 2)
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['user_status'] = $user_status; // error code
      $returnvalue['user_id'] = $user_id;
      $returnvalue['data'] = $reactivation_date; // returned data
      $returnvalue['count'] = count($users); // returned count of datasets

      return $returnvalue;
    } // nothing found, empty database or non active user

    // new
    $dbpw = $users[0]['pw'];
    // check PASSWORD
    $temp_pw = $users[0]['temp_pw'];

    if (($temp_pw != '' && $temp_pw == $pw) || password_verify($pw, $dbpw)) {
      if ($users[0]["refresh_token"]) {
        $this->setRefresh($users[0]["id"], false);
      }

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['user_id'] = $user_id;
      $returnvalue['data'] = $users[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 3; // error code
      $returnvalue['user_id'] = $user_id;
      $returnvalue['data'] = 8888; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }

  }// end function


  public function getUsers($offset, $limit, $orderby = 0, $asc = 0, $both_names = "", $search_field = "", $search_text = "", $extra_where = "", $status = -1, $userlevel = -1, $room_id = '')
  {
    /* returns userlist (associative array) with start and limit provided
    extra_where = SQL Clause that can be added to where in the query like AND status = 1
    */
    // sanitize
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);
    $userlevel = intval($userlevel);

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

    if ($both_names != "") {
      $extra_where .= "AND (realname LIKE :both_names OR displayname LIKE :both_names)";
    }

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND status = " . $status;
    }

    if ($userlevel > -1) {
      // specific level selected / -1 = get all userlevels
      $extra_where .= " AND userlevel = " . $userlevel;
    }

    if (isset($room_id) && $room_id !== '') {
      $extra_where .= " AND JSON_SEARCH(u.roles, 'one', :room_id, NULL, '$[*].room') IS NOT NULL";
    }

    $orderby_field = $this->getUserOrderId($orderby);

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

    $search_field_valid = false;
    if ($search_field != "" && $search_text != "") {
      if ($this->validSearchField($search_field)) {
        $search_field_valid = true;
        $extra_where .= " AND " . $search_field . " LIKE :search_text";
      }
    }

    $stmt = $this->db->query(<<<EOD
      SELECT roles, realname, displayname, username, email, hash_id, about_me, status, registration_status, created, last_update, userlevel, temp_pw
      FROM au_users_basedata u
      WHERE id > 0 {$extra_where}
      ORDER BY {$orderby_field} {$asc_field}
      {$limit_string}
    EOD);

    if ($limit) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    // $this->db->bind(':status', $status); // bind status

    if ($search_field_valid) {
      $this->db->bind(':search_text', '%' . $search_text . '%');
    }

    if (isset($room_id) && $room_id !== '') {
      $this->db->bind(":room_id", $room_id);
    }

    if ($both_names != "") {
      $this->db->bind(':both_names', '%' . $both_names . '%');
    }

    $err = false;
    try {
      $users = $this->db->resultSet();

    } catch (Exception $e) {
      error_log('Error occurred while getting users: ' . $e->getMessage()); // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }

    $count_data = count($users);

    if ($count_data < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;


    } else {
      $total_datasets = 0;
      if ($search_field_valid) {
        $total_datasets = $this->converters->getTotalDatasets($this->db->au_users_basedata, "id > 0", $search_field, $search_text);
      } else {
        $total_datasets = $this->converters->getTotalDatasets($this->db->au_users_basedata, "id > 0");
      }
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $users; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;


    }
  }// end function

  public function checkUserExistsByUsername($username, $email = "")
  {
    // helper function: checks if a user with this username or email adress is already in database

    $check_email = strlen(trim($email)) > 2;

    // our mariadb uses case-insensitive collation by default => string comparisons are case-insensitive
    $this->db->query('SELECT id FROM ' . $this->db->au_users_basedata . ' WHERE username = :username ' .
      ($check_email ? ' OR email = :email' : ''));
    $this->db->bind(':username', trim($username));
    $this->db->bind(($check_email ? ':email' : ''), trim($email));

    $users = $this->db->resultSet();
    $returnvalue['success'] = true;
    $returnvalue['count'] = count($users);
    $returnvalue['error_code'] = 0;
    if (count($users) > 1 || empty($users)) {
      $returnvalue['data'] = false;
    } else {
      $returnvalue['data'] = $users[0]['id'];
    }
    return $returnvalue;
  }

  public function getUsersByRoom($room_id, $status = -1, $offset = 0, $limit = 0, $orderby = 3, $asc = 0, $search_field = "", $search_text = "", $userlevel = -1)
  {
    /* returns users (associative array)for a specific room + extra parameters (for further filtering)
     */
    $status = intval($status);
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $userlevel = intval($userlevel);

    $room_model = new Room($this->db, $this->crypt, $this->syslog);

    if (is_int($room_id)) {
      $room_hash = $room_model->getRoomHashId($room_id)["data"];
    } else {
      $room_hash = $room_id;
    }

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
      // specific status selected / -1 = get all status values
      $extra_where .= " AND u.status = " . $status;
    }

    if ($userlevel > -1) {
      // specific level selected / -1 = get all levels
      $extra_where .= " AND u.userlevel = " . $userlevel;
    }

    $search_field_valid = false;
    if ($search_field != "" && $search_text != "") {
      if ($this->validSearchField($search_field)) {
        $search_field_valid = true;
        $extra_where .= " AND u." . $search_field . " LIKE :search_text ";
      }
    }

    $orderby_field = "u." . $this->getUserOrderId($orderby);

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

    $query = <<<EOD
      SELECT
        u.hash_id,
        u.status,
        u.created,
        u.displayname,
        u.realname,
        u.username,
        u.email,
        u.userlevel,
        u.temp_pw,
        u.last_update
      FROM
        {$this->db->au_rel_rooms_users} ru
      INNER JOIN
        {$this->db->au_users_basedata} u
      ON
        (ru.user_id = u.id)
      WHERE
        ru.room_id = :room_id {$extra_where}
      AND
        JSON_SEARCH(u.roles, 'one', :room_hash, NULL, '$[*].room') IS NOT NULL
    EOD;

    $stmt = $this->db->query($query . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    $this->db->bind(':room_id', $room_id); // bind room id
    $this->db->bind(':room_hash', $room_hash); // bind room id

    //$this->db->bind(':status', $status); // bind status

    if ($search_field_valid) {
      $this->db->bind(':search_text', '%' . $search_text . '%');
    }

    $err = false;
    try {
      $rooms = $this->db->resultSet();
    } catch (Exception $e) {
      error_log('Error ocurred when getUsersByRoom: ' . $e->getMessage());
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
        $total_query = <<<EOD
            {$this->db->au_rel_rooms_users} ru
          INNER JOIN {$this->db->au_users_basedata} u
          ON
            (ru.user_id = u.id)
          WHERE
            ru.room_id = :room_id
        EOD;

        // only newly calculate datasets if limits are active
        if ($search_field_valid) {
          $total_datasets = $this->converters->getTotalDatasets(str_replace(":room_id", $room_id, $total_query), "", $search_field, $search_text);
        } else {
          $total_datasets = $this->converters->getTotalDatasets(str_replace(":room_id", $room_id, $total_query));
        }
      }

      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // error code - db error
      $returnvalue['data'] = $rooms; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;
    }
  }// end function


  function checkForCharacterCondition($string)
  {
    # helper returns true if all chars in string are allowed
    return (bool) preg_match('/(?=.*([A-Z]))(?=.*([a-z]))(?=.*([0-9]))(?=.*([~`\!@#\$%\^&\*\(\)_\{\}\[\]]))/', $string);
  }

  function generate_pass($length = 8)
  {
    // pw generator

    $j = 1;
    $allowedCharacters = '23456789abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ_!@';
    $pass = '';
    $max = mb_strlen($allowedCharacters, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
      $pass .= $allowedCharacters[random_int(0, $max)];
    }

    if ($this->checkForCharacterCondition($pass)) {
      return $pass;
    } else {
      $j++;
      return $this->generate_pass();
    }

  }

  public function getUserLevel($user_id)
  {
    /* returns user level for a certain user id
     */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('SELECT userlevel FROM ' . $this->db->au_users_basedata . ' WHERE id = :id');
    $this->db->bind(':id', $user_id); // bind userid
    $users = $this->db->resultSet();
    if (count($users) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = $users[0]['userlevel']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function addUser($realname, $displayname, $username, $email = "", $password = "", $status = 1, $about_me = "", $updater_id = 0, $userlevel = 10, $nomail = false)
  {
    /* adds a user and returns insert id (userid) if successful, accepts the above parameters
     realname = actual name of the user, status = status of inserted user (0 = inactive, 1=active)
     userlevel = Rights level for the user 0 = inactive, 10 = guest, 20 = standard, 30 = moderator 40 = super mod 50 = admin 60 = tech admin
    */

    // sanitize vars
    $realname = trim($realname);
    $displayname = trim($displayname);
    $username = trim($username);
    $email = strtolower(trim($email));
    $about_me = trim($about_me);
    $password = trim($password);
    $updater_id = intval($updater_id);
    $status = intval($status);
    $userlevel = intval($userlevel);

    // @TODO: validate input, for example Commands can fail if username or realname contains ";" which is used as
    //   a separator in Command parameters

    if (strlen($email) > 0) {
      if (!(bool) filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->responseBuilder->error(errorDescription: "Invalid email address");
      }
    }

    // check if user name is still available
    $temp_user = $this->checkUserExistsByUsername($username, $email); // check username in db
    if ($temp_user['count'] > 0) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = $temp_user['data']; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    // generate hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $send_email = strlen($email) > 0 && !$nomail;
    $temp_pw = $send_email ? "" : $this->generate_pass(8); # if no email is provided, generate a temporary password

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_users_basedata
      . ' ( temp_pw,  pw_changed,  o1,  o2,  o3,  about_me, presence, auto_delegation, realname,  displayname,  username,  email,  pw,        status,  hash_id, created, last_update,  updater_id,  userlevel) VALUES '
      . ' (:temp_pw, :pw_changed, :o1, :o2, :o3, :about_me, 1,        0,              :realname, :displayname, :username, :email, :password, :status, :hash_id, NOW(),   NOW(),       :updater_id, :userlevel)');
    // bind all VALUES
    $this->db->bind(':temp_pw', $temp_pw);
    $this->db->bind(':pw_changed', 0); # set flag so user has to change the temporary password
    $this->db->bind(':o1', mb_ord(strtolower(trim($username))));
    $this->db->bind(':o2', mb_ord(strtolower(trim($realname))));
    $this->db->bind(':o3', mb_ord(strtolower(trim($displayname))));
    $this->db->bind(':about_me', $this->crypt->encrypt($about_me));
    $this->db->bind(':realname', $this->crypt->encrypt($realname));
    $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
    $this->db->bind(':username', $this->crypt->encrypt($username));
    $this->db->bind(':email', $email == '' ? null : $this->crypt->encrypt($email));
    $this->db->bind(':password', $hash);
    $this->db->bind(':status', $status);
    // generate unique hash for this user
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($username . $appendix); // create hash id for this user
    $this->db->bind(':hash_id', $hash_id);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)
    $this->db->bind(':userlevel', $userlevel);
    #set flag so user has to change pw
    $this->db->bind(':pw_changed', 0);

    $data = []; # init return array

    $err = false; // set error variable to false

    $insertid = 0;

    try {
      $action = $this->db->execute(); // do the query
      $insertid = intval($this->db->lastInsertId());

      # add user to default standard room  (aula)
      $this->addUserToStandardRoom($insertid);

    } catch (Exception $e) {
      error_log('Error occurred while adding user ' . $user_id . ' to standard room: ' . $e->getMessage());
      $err = true;
    }

    # set output array
    $data['insert_id'] = $insertid;
    $data['hash_id'] = $hash_id;
    $data['temp_pw'] = $temp_pw;


    if (!$err) {
      if ($send_email) {
        // Send email to new user
        $not_created = true;
        $secret = bin2hex(random_bytes(32));

        while ($not_created) {
          $stmt = $this->db->query('SELECT user_id FROM au_change_password WHERE secret = :secret');
          $this->db->bind(':secret', $secret);

          if (count($this->db->resultSet()) == 0) {
            $not_created = false;
          } else {
            $secret = bin2hex(random_bytes(32));
          }
        }

        $stmt = $this->db->query('SELECT id, realname FROM au_users_basedata WHERE email = :email');
        $this->db->bind(':email', $email);
        $user_id = $this->db->resultSet()[0]["id"];
        $realname = $this->db->resultSet()[0]["realname"];


        $stmt = $this->db->query('INSERT INTO au_change_password (user_id, secret) values (:user_id, :secret)');
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':secret', $secret);

        $this->db->resultSet();

        $content = "text/html; charset=utf-8";
        $mime = "1.0";

        global $email_from;
        global $email_creation_subject;
        global $email_address_support;
        global $email_creation_body;

        $headers = array(
          'From' => $email_from,
          'To' => $email,
          'Subject' => $email_creation_subject,
          'Reply-To' => $email_address_support,
          'MIME-Version' => $mime,
          'Content-type' => $content,
          'message-id' => time() .'-' . md5($email_from . $email) . '@' . $_SERVER['SERVER_NAME'],
          'Date' => date('r')
        );

        $email_body = str_replace("<SECRET_KEY>", $secret, $email_creation_body);
        $email_body = str_replace("<NAME>", $realname, $email_body);
        $email_body = str_replace("<USERNAME>", $username, $email_body);
        $email_body = str_replace("<CODE>", $this->db->code, $email_body);

        $mail = $this->smtp->send($email, $headers, $email_body);

      }

      $this->syslog->addSystemEvent(0, "Added new user " . $insertid, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $data; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;


    } else {
      $this->syslog->addSystemEvent(1, "Error adding user ", 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function editUser($user_id, $realname, $displayname, $username, $email, $userlevel, $about_me = "", $position = "", $updater_id = 0, $status = 1)
  {
    /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     realname = actual name of the user, status = status of inserted user (0 = inactive, 1=active)
    */

    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $status = intval($status);
    $email = strtolower(trim($email));

    if (strlen($email) > 0) {
      if (!(bool) filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->responseBuilder->error(errorDescription: "Invalid email address");
      }
    }

    $temp_user = $this->checkUserExistsByUsername($username, $email); // check username in db
    // if there's more users with the new email/username, or if the new email/username belongs to another user
    if ($temp_user['count'] > 1 || ($temp_user['count'] == 1 && $temp_user['data'] != $user_id)) {
      $this->syslog->addSystemEvent(1, "Error (username or email already exists) while editing user ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets
      return $returnvalue;
    }

    /* if ($temp_user['data'].email != $email && not verified) { */
    /*   send change password email to verify */
    /* } */

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET userlevel = :userlevel, realname = :realname , displayname= :displayname, username= :username, about_me= :about_me, position= :position, email = :email, last_update= NOW(), updater_id= :updater_id, status= :status WHERE id= :userid');
    // bind all VALUES
    $this->db->bind(':username', trim($username));
    $this->db->bind(':realname', trim($realname));
    $this->db->bind(':about_me', trim($about_me));
    $this->db->bind(':displayname', trim($displayname));
    $this->db->bind(':position', trim($position));
    $this->db->bind(':email', $email);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)
    $this->db->bind(':userlevel', $userlevel); // user level (10 default)
    $this->db->bind(':status', $status); // user level (10 default)
    $this->db->bind(':userid', $user_id); // user that is updated

    $err = false; // set error variable to false

    try {
       $action = $this->db->execute(); // do the query
    } catch (Exception $e) {
       $err = true;
    }

    if (!$err) {
      $this->syslog->addSystemEvent(0, "Edited user " . $user_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets
      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error while editing user ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets
      return $returnvalue;
    }
  }// end function

  public function addUserRole($user_id, $role, $room_id, $use_transaction = true)
  {
    $user_id = $this->converters->checkUserId($user_id);
    $room_id = $this->converters->checkRoomId($room_id);

    $stmt = $this->db->query('SELECT hash_id FROM ' . $this->db->au_rooms . ' WHERE id = :room_id');
    $this->db->bind(':room_id', $room_id);
    $room_hash = $this->db->resultSet()[0]["hash_id"];

    if ($use_transaction) {
        $this->db->beginTransaction();
    }
    $stmt = $this->db->query('SELECT roles FROM ' . $this->db->au_users_basedata . ' WHERE id = :user_id FOR UPDATE');
    $this->db->bind(':user_id', $user_id);

    $results = $this->db->resultSet();
    $roles = json_decode($results[0]["roles"]);

    if (is_null($roles)) {
      $roles = [];
    }

    $new_roles = array_values(array_filter($roles, fn($r) => $r->room != $room_hash));
    array_push($new_roles, ["role" => $role, "room" => $room_hash]);

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET roles = :roles, last_update= NOW() WHERE id = :user_id');
    $this->db->bind(':user_id', $user_id);
    $this->db->bind(':roles', json_encode($new_roles));

    try {
      $action = $this->db->execute(); // do the query
      if ($use_transaction) {
        $this->db->commitTransaction();
      }
      $this->setRefresh($user_id, true);

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } catch (Exception $e) {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = 0; // returned data
      $returnvalue['count'] = 0; // returned count of datasets
    }
  }

  public function getUserRooms($user_id, $type = -1)
  {
    /* returns rooms where user is member of for a certain user id
     */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    // additional conditions for the WHERE clause
    $extra_where = "";

    if ($type > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND type = " . $type;
    }

    $query_rooms = <<<EOD
      SELECT
        rooms.hash_id
      FROM
        au_rel_rooms_users rel_rooms_users
      JOIN
        au_rooms rooms
      ON
        rel_rooms_users.room_id = rooms.id
      INNER JOIN
        au_users_basedata users_basedata
      ON
        rel_rooms_users.user_id = users_basedata.id
      WHERE
        rel_rooms_users.user_id = :user_id AND
        JSON_SEARCH(users_basedata.roles, 'one', rooms.hash_id, NULL, '$[*].room') IS NOT NULL
    EOD;

    $stmt = $this->db->query($query_rooms . $extra_where);
    $this->db->bind(':user_id', $user_id); // bind userid
    $rooms = $this->db->resultSet();

    if (count($rooms) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = $rooms; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    }
  }// end function


  public function getUserGroups($user_id)
  {
    /* returns rooms where user is member of for a certain user id
     */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('SELECT hash_id FROM ' . $this->db->au_rel_groups_users . ' LEFT JOIN ' . $this->db->au_groups . ' ON (' . $this->db->au_groups . '.id = ' . $this->db->au_rel_groups_users . '.group_id) WHERE user_id = :user_id');
    $this->db->bind(':user_id', $user_id); // bind userid
    $groups = $this->db->resultSet();

    if (count($groups) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = $groups; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    }
  }// end function


  public function deleteUserRole($user_id, $room_id)
  {
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $room_id = $this->converters->checkRoomId($room_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('SELECT hash_id FROM ' . $this->db->au_rooms . ' WHERE id = :room_id');
    $this->db->bind(':room_id', $room_id);
    $room_hash = $this->db->resultSet()[0]["hash_id"];

    $this->db->beginTransaction();
    $stmt = $this->db->query('SELECT roles FROM ' . $this->db->au_users_basedata . ' WHERE id = :user_id FOR UPDATE');
    $this->db->bind(':user_id', $user_id);

    $roles = json_decode($this->db->resultSet()[0]["roles"]);
    $new_roles = array_values(array_filter($roles, fn($r) => $r->room != $room_hash));

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET roles = json_merge_patch(roles, :roles), last_update= NOW() WHERE id = :user_id');
    $this->db->bind(':user_id', $user_id);
    $this->db->bind(':roles', json_encode($new_roles));

    $this->db->execute();
    $this->db->commitTransaction();

  }


  public function setUserRoles($user_id, $roles, $updater_id = 0)
  {
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET roles = json_merge_patch(roles, :roles), last_update= NOW(), updater_id= :updater_id WHERE id = :user_id');
    $this->db->bind(':user_id', $user_id);
    $this->db->bind(':roles', $roles);
    $this->db->bind(':updater_id', $updater_id);

    try {
      $action = $this->db->execute(); // do the query
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } catch (Exception $e) {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = 0; // returned data
      $returnvalue['count'] = 0; // returned count of datasets
    }
  }

  public function checkRefresh($user_id)
  {
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $stmt = $this->db->query('SELECT refresh_token FROM ' . $this->db->au_users_basedata . '  WHERE id = :user_id');
    $this->db->bind(':user_id', $user_id);
    $action = $this->db->execute(); // do the query
    $users = $this->db->resultSet();

    return $users[0]["refresh_token"];
  }

  public function setUserInfiniteVote($user_id, $infinite, $updater_id = 0)
  {
    /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     sets the specified user to infinite vote capability
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    // sanitize
    $infinite = intval($infinite);
    if ($infinite > 1) {
      $infinite = 1;
    }
    if ($infinite < 0) {
      $infinite = 0;
    }

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET infinite_votes = :infinite, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
    // bind all VALUES
    $this->db->bind(':infinite', $infinite);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':userid', $user_id); // user that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "User infinite status of " . $user_id . " changed to " . $infinite . " by " . $updater_id, 0, "", 1);

      // set delegations for this user to suspended (delegated voting right and received votign right)
      if ($infinite == 1) {
        // remove all delegations from this user since he has infinite votes....
        $this->removeUserDelegations($user_id, 0, 0);
        $this->removeUserDelegations($user_id, 0, 1);
      }

      $row_count = intval($this->db->rowCount());
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = $row_count; // returned data
      $returnvalue['count'] = $row_count; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error changing infinite status of user " . $user_id . " to " . $infinite . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setUserAbout($user_id, $about_me, $updater_id = 0)
  {
    /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     about (text) -> description of a user
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $about_me = $this->crypt->encrypt(trim($about_me)); // sanitize and encrypt about text

    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET about_me= :about_me, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
    // bind all VALUES
    $this->db->bind(':about_me', $about_me);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':userid', $user_id); // user that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "User abouttext changed " . $user_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing abouttext of user ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function setUserRealname($user_id, $realname, $updater_id = 0)
  {
    /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
     realname = actual name of the user
    */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET realname= :realname, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
    // bind all VALUES
    $this->db->bind(':realname', $this->crypt->encrypt($realname));
    $this->db->bind(':userid', $user_id); // user that is updated
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "User real name changed " . $user_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing real name of user ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function setUserDisplayname($user_id, $displayname, $updater_id = 0)
  {
    /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
     displayname = shown name of the user in the system
    */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET displayname= :displayname, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
    // bind all VALUES
    $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
    $this->db->bind(':userid', $user_id); // user that is updated
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "User display name changed " . $user_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing display name of user ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function setUserEmail($user_id, $email, $updater_id = 0)
  {
    /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
     email = email address of the user
    */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $email = strtolower(trim($email));

    if (strlen($email) > 0) {
      if (!(bool) filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->responseBuilder->error(errorDescription: "Invalid email address");
      }
    }

    $stmt = $this->db->query('UPDATE au_users_basedata SET email= :email, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
    // bind all VALUES
    $this->db->bind(':email', $this->crypt->encrypt($email));
    $this->db->bind(':userid', $user_id);
    $this->db->bind(':updater_id', $updater_id);

    try {
      $success = $this->db->execute();
    } catch (Exception $e) {
      $success = false;
    }

    if ($success) {
      $this->syslog->addSystemEvent(0, "User email changed " . $user_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing email of user ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function setUserUsername($user_id, $username, $updater_id = 0)
  {
    /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
     username = username of the user in the system
    */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET username= :username, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
    // bind all VALUES
    $this->db->bind(':username', $this->crypt->encrypt($username));
    $this->db->bind(':userid', $user_id); // user that is updated
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "User username name changed " . $user_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing display name of user ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function setUserPW($user_id, $pw, $updater_id = 0)
  {
    /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
     pw = pw in clear text
    */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET pw= :pw, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');

    // generate pw hash
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    // bind all VALUES
    $this->db->bind(':pw', $hash);
    $this->db->bind(':userid', $user_id); // user that is updated
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "User pw changed " . $user_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing pw of user ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function


  public function deleteUser($user_id, $delete_mode = 0, $updater_id = 0)
  {
    /* deletes user and returns the number of rows (int) accepts user id or user hash id
    user id = id of the user, either hash id or db id
    delete_mode => 0 = delete user only 1 = also delete all associated data with this user (ideas, comment, messages, media), votes are preserved
     */

    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_users_basedata . ' WHERE id = :id');
    $this->db->bind(':id', $user_id);
    $err = false;
    try {
      $action = $this->db->execute(); // do the query
      $rows_affected = intval($this->db->rowCount());

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      // remove all delegations for this user
      $this->removeUserDelegations($user_id, 0, 0); // active delegations (original user)
      $this->removeUserDelegations($user_id, 0, 1); // passive delegations (target user)

      // delete associated data if option delete_mode is set
      if ($delete_mode == 1) {
        // delete ideas from this user
        $stmt = $this->db->query('DELETE FROM ' . $this->db->au_ideas . ' WHERE user_id = :id');
        $this->db->bind(':id', $user_id);
        $err = false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval($this->db->rowCount());

        } catch (Exception $e) {

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue['data'] = false; // returned data
          $returnvalue['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
        // delete comments from this user
        $stmt = $this->db->query('DELETE FROM ' . $this->db->au_comments . ' WHERE user_id = :id');
        $this->db->bind(':id', $user_id);
        $err = false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval($this->db->rowCount());

        } catch (Exception $e) {

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue['data'] = false; // returned data
          $returnvalue['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
        // delete messages from this user
        $stmt = $this->db->query('DELETE FROM ' . $this->db->au_messages . ' WHERE creator_id = :id');
        $this->db->bind(':id', $user_id);
        $err = false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval($this->db->rowCount());

        } catch (Exception $e) {

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue['data'] = false; // returned data
          $returnvalue['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

        // delete group relations from this user
        $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_groups_users . ' WHERE user_id = :id');
        $this->db->bind(':id', $user_id);
        $err = false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval($this->db->rowCount());

        } catch (Exception $e) {

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue['data'] = false; // returned data
          $returnvalue['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

        // delete room relations from this user
        $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_rooms_users . ' WHERE user_id = :id');
        $this->db->bind(':id', $user_id);
        $err = false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval($this->db->rowCount());

        } catch (Exception $e) {

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue['data'] = false; // returned data
          $returnvalue['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

        // delete media relations from this user
        $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_users_media . ' WHERE user_id = :id');
        $this->db->bind(':id', $user_id);
        $err = false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval($this->db->rowCount());

        } catch (Exception $e) {

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue['data'] = false; // returned data
          $returnvalue['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

        // delete user / user relations from this user
        $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_user_user . ' WHERE user_id1 = :id or user_id2 = :id');
        $this->db->bind(':id', $user_id);
        $err = false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval($this->db->rowCount());

        } catch (Exception $e) {

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue['data'] = false; // returned data
          $returnvalue['count'] = 0; // returned count of datasets

          return $returnvalue;
        }


        // Remove user associations in likes from this user
        $stmt = $this->db->query('UPDATE ' . $this->db->au_likes . ' SET user_id = 0 WHERE user_id = :id');
        $this->db->bind(':id', $user_id);
        $err = false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval($this->db->rowCount());

        } catch (Exception $e) {

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue['data'] = false; // returned data
          $returnvalue['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

        // Remove user associations in votes from this user
        $stmt = $this->db->query('UPDATE ' . $this->db->au_votes . ' SET user_id = 0 WHERE user_id = :id');
        $this->db->bind(':id', $user_id);
        $err = false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval($this->db->rowCount());

        } catch (Exception $e) {

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue['data'] = false; // returned data
          $returnvalue['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

      } //end if


      $this->syslog->addSystemEvent(0, "User deleted with id " . $user_id . " by " . $updater_id, 0, "", 1);

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $rows_affected; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error deleting user with id ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }

  }// end function

  public function getPossibleDelegations($user_id, $room_id, $topic_id)
  {
    $room_id = $this->converters->checkRoomId($room_id);
    $user_id = $this->converters->checkUserId($user_id);
    $topic_id = $this->converters->checkTopicId($topic_id);

    // Get users in room with voting rights
    $query = <<<EOD
      SELECT {$this->db->au_users_basedata}.hash_id,
             {$this->db->au_users_basedata}.username,
             {$this->db->au_users_basedata}.displayname,
             {$this->db->au_users_basedata}.realname,
            {$this->db->au_rel_rooms_users}.user_id IN
            (SELECT user_id_original FROM {$this->db->au_delegation} WHERE user_id_target = :user_id AND topic_id = :topic_id)
             as 'is_delegate'
        FROM  {$this->db->au_rel_rooms_users}
        INNER JOIN {$this->db->au_users_basedata} ON ({$this->db->au_rel_rooms_users}.user_id = {$this->db->au_users_basedata}.id)
        WHERE {$this->db->au_rel_rooms_users}.room_id = :room_id AND
        {$this->db->au_users_basedata}.userlevel in (20, 31) AND
        {$this->db->au_users_basedata}.id != :user_id
    EOD;

    // Get delegates in room
    $this->db->query($query);
    $this->db->bind(":room_id", $room_id);
    $this->db->bind(":user_id", $user_id);
    $this->db->bind(":topic_id", $topic_id);

    $this->db->execute();
    $usersInRoom = $this->db->resultSet();

    // Get super users with voting rights
    $query = <<<EOD
      SELECT {$this->db->au_users_basedata}.hash_id,
             {$this->db->au_users_basedata}.username,
             {$this->db->au_users_basedata}.displayname,
             {$this->db->au_users_basedata}.realname,
             {$this->db->au_users_basedata}.id IN (SELECT user_id_original FROM {$this->db->au_delegation} WHERE user_id_target = :user_id AND topic_id = :topic_id) as 'is_delegate'
        FROM {$this->db->au_users_basedata}
       WHERE userlevel in (41, 45)
    EOD;

    $this->db->query($query);
    $this->db->bind(":user_id", $user_id);
    $this->db->bind(":topic_id", $topic_id);
    $this->db->execute();
    $superUsersWithVotingRights = $this->db->resultSet();

    $allUsers = array_merge($usersInRoom, $superUsersWithVotingRights);
    $returnvalue['success'] = true;
    $returnvalue['error_code'] = 0;
    $returnvalue['data'] = $allUsers;
    $returnvalue['count'] = count($allUsers);
    return $returnvalue;

  }


  // @TODO: temporarily kept in User model, move after Laravel routing impl.
  public function resetPasswordForUser($user_id, $updater_id)
  {
    return (new ResetPasswordForUserUseCase($this->db, $this->crypt, $this->syslog, $this))->execute($user_id, $updater_id);
  }
}
