<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include == 1) {

} else {
  exit;
}



class Text
{

  private $db;

  # deals with everything concering texts (consent texts etc.)

  public function __construct($db, $crypt, $syslog)
  {
    // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
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

  public function getTextOrderId($orderby)
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
        return "headline";
      case 6:
        return "body";
      case 7:
        return "creator_id";
      case 8:
        return "user_needs_to_consent";
      case 9:
        return "consent_text";
      default:
        return "last_update";
    }
  }// end function

  public function validSearchField($search_field)
  {
    # helper method => defines allowed / valid db field names (for filtering)
    return in_array($search_field, [
      "headline",
      "consent_text",
      "body"
    ]);
  }

  public function getTextStatus($text_id)
  {
    /* returns status of a text for a integer id
     */

    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('SELECT status FROM ' . $this->db->au_texts . ' WHERE id = :id');
    $this->db->bind(':id', $text_id); // bind text id
    $texts = $this->db->resultSet();
    if (count($texts) < 1) {
      // no consent found
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = 0; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // consent found
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $texts[0]['status']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    }
  }// end function


  public function getTextBaseData($text_id)
  {
    /* returns text base data for a specified db id */


    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_texts . ' WHERE id = :id');
    $this->db->bind(':id', $text_id); // bind text id
    $texts = $this->db->resultSet();
    if (count($texts) < 1) {
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 2; //  error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue; // nothing found, return 0 code
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $texts[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue; // return an array (associative) with all the data
    }
  }// end function

  public function getTexts($offset = 0, $limit = 0, $orderby = 0, $asc = 0, $status = 1, $extra_where = "", $creator_id = 0, $user_needs_to_consent = -1, $search_field = "", $search_text = "")
  {
    /* returns text list (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (0)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    status (int) 0=inactive, 1=active, 2=suspended, 3=archived, 5= in review defaults to active (1)
    extra_where = extra parameters for where clause, synthax " AND XY=4"
    creator_id = specifies a certain user that wrote the text (author), if set to 0, all texts are displayed
    user_needs_to_consent specifies texts that need (1) or dont need (0) a consent by the user (set to -1, if all texts shoud be shown)
    */

    // sanitize
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);

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

    // check if a status was set (status > -1 default value)
    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND status = :status";
    }

    $search_field_valid = false;
    if ($search_field != "" && $search_text != "") {
      if ($this->validSearchField($search_field)) {
        $search_field_valid = true;
        $extra_where .= " AND " . $search_field . " LIKE :search_text";
      }
    }

    if ($creator_id > 0) {
      $extra_where .= " AND creator_id = :creator_id"; // get specific texts for a creator / moderator / admin
    }

    if ($user_needs_to_consent > -1) {
      // if a target user id is set then add to where clause
      $extra_where .= " AND user_needs_to_consent = :user_needs_to_consent"; // get only texts that need (1)/dont need (0) consent
    }

    $orderby_field = $this->getTextOrderId($orderby);

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

    $stmt = $this->db->query(<<<EOD
      SELECT * FROM au_texts
      WHERE id > 0 {$extra_where}
      ORDER BY {$orderby_field} {$asc_field}
      {$limit_string}
    EOD);

    if ($limit_active) {
      $this->db->bind(':offset', $offset);
      $this->db->bind(':limit', $limit);
    }
    if ($search_field_valid) {
      $this->db->bind(':search_text', '%' . $search_text . '%');
    }
    if ($status > -1) {
      $this->db->bind(':status', $status);
    }
    if ($creator_id > 0) {
      $this->db->bind(':creator_id', $creator_id);
    }
    if ($user_needs_to_consent > -1) {
      $this->db->bind(':user_needs_to_consent', $user_needs_to_consent);
    }

    $err = false;
    try {
      $texts = $this->db->resultSet();
    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value to false
      $returnvalue['error_code'] = 1; // database error while executing query
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    $count_datasets = count($texts);
    if ($limit_active) {
      // only newly calculate datasets if limits are active
      if ($search_field_valid) {
        $count_datasets = $this->converters->getTotalDatasets($this->db->au_texts, "id > 0" . $extra_where, $search_field, $search_text);
      } else {
        $count_datasets = $this->converters->getTotalDatasets($this->db->au_texts, "id > 0" . $extra_where);
      }
    }

    if ($count_datasets < 1) {
      $returnvalue['success'] = false; // set success value
      $returnvalue['error_code'] = 2; // no data found
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = $count_datasets; // returned count of datasets

      return $returnvalue; // nothing found, return 0 code
    } else {
      $returnvalue['success'] = true; // set return value to false
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $texts; // returned data
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

  private function updateConsentsUsers($consent_value)
  {
    // update the consents needed for the individual users depending on consent_value (increment or decrement)
    $stmt = $this->db->query('UPDATE ' . $this->db->au_users_basedata . ' SET consents_needed = consents_needed + :consent_value, last_update= NOW()');
    // bind all VALUES
    $this->db->bind(':consent_value', $consent_value);

    $err = false; // set error variable to false
    $count_datasets = 0; // init row count

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $count_datasets = intval($this->db->rowCount());
      $this->syslog->addSystemEvent(0, "Consent values updated by value " . $consent_value, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $count_datasets; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets


      return $returnvalue; // return number of affected rows to calling script
    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }

  } // end function

  public function addText($headline, $body = "", $consent_text = "", $location = 0, $creator_id = 0, $user_needs_to_consent = 0, $service_id_consent = 0, $status = 1, $updater_id = 0, $language_id = 0)
  {
    /* adds a new text and returns insert id (text id) if successful, accepts the above parameters
    content is the text itself
    headline, body is the content
    consent_text = text that is displayed next to the checkbox for the user consent
    creator_id is the original author of the text
    location is the page this consent is displayed on
    user_needs_to_consent = 0 = display only, no checkbox, no need to consent, 1= consent needed (if consented, checkbox doesnt display anmymore), checkbox displayed, 2= needs to be consented (first) to use aula
    status = status of the text (0=inactive, 1=active, 2=
    ed, 3=reported, 4=archived 5= in review)
    updater id specifies the id of the user (i.e. admin) that added this text

    */

    //sanitize the vars
    $body = trim($body);
    $headline = trim($headline);

    $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $creator_id = $this->converters->checkUserId($creator_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $status = intval($status);
    $language_id = intval($language_id);

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_texts . ' (headline, body, consent_text, creator_id, user_needs_to_consent, service_id_consent, status, hash_id, created, last_update, updater_id, language_id) VALUES (:headline, :body, :consent_text, :creator_id, :user_needs_to_consent, :service_id_consent, :status, :hash_id, NOW(), NOW(), :updater_id, :language_id)');
    // bind all VALUES

    $this->db->bind(':headline', $headline);
    $this->db->bind(':body', $body);
    $this->db->bind(':consent_text', $consent_text);
    $this->db->bind(':creator_id', $creator_id);
    $this->db->bind(':user_needs_to_consent', $user_needs_to_consent);
    $this->db->bind(':service_id_consent', $service_id_consent);


    $this->db->bind(':status', $status);
    $this->db->bind(':language_id', $language_id);

    // generate unique hash for this idea
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($headline . $appendix); // create hash id for this text
    $this->db->bind(':hash_id', $hash_id);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $insertid = intval($this->db->lastInsertId());

      $this->syslog->addSystemEvent(0, "Added new text (#" . $insertid . ") creator: " . $creator_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $insertid; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      // update all users - needed consent field
      if ($user_needs_to_consent == 2) {
        // only update if consent is mandatory
        $this->updateConsentsUsers(1);
      }

      return $returnvalue; // return insert id to calling script

    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }


  }// end function

  public function editText($text_id, $headline, $body, $consent_text, $user_needs_to_consent, $status, $location = 0, $updater_id = 0, $language_id = 0)
  {
    /* edits a text and returns insert id (text id) if successful, accepts the above parameters
    headline, body is the content
    consent_text = text that is displayed next to the checkbox for the user consent
    location is the page this consent is displayed on
    user_needs_to_consent = 0 = display only, no checkbox, no need to consent, 1= consent needed (if consented, checkbox doesnt display anmymore), checkbox displayed, 2= needs to be consented (first) to use aula
    status = status of the text (0=inactive, 1=active, 2=
    ed, 3=reported, 4=archived 5= in review)
    updater id specifies the id of the user (i.e. admin) that added this text

    */

    //sanitize the vars
    $text_id = $this->converters->checkTextId($text_id); // autoconvert id
    $body = trim($body);
    $headline = trim($headline);

    $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $status = intval($status);
    $language_id = intval($language_id);

    $stmt = $this->db->query('UPDATE ' . $this->db->au_texts . ' SET headline = :headline, body = :body, consent_text = :consent_text, user_needs_to_consent = :user_needs_to_consent, status = :status, last_update = NOW(), updater_id = :updater_id, language_id = :language_id WHERE id = :text_id');
    // bind all VALUES

    $this->db->bind(':headline', $headline);
    $this->db->bind(':body', $body);
    $this->db->bind(':consent_text', $consent_text);
    $this->db->bind(':user_needs_to_consent', $user_needs_to_consent);
    $this->db->bind(':status', $status);
    $this->db->bind(':language_id', $language_id);
    $this->db->bind(':text_id', $text_id);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {

      $this->syslog->addSystemEvent(0, "Edited text (#" . $text_id . ") by : " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $insertid; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      // update all users - needed consent field
      if ($user_needs_to_consent == 2) {
        // only update if consent is mandatory
        $this->updateConsentsUsers(1);
      }

      return $returnvalue; // return insert id to calling script

    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }


  }// end function

  public function setTextStatus($text_id, $status, $updater_id = 0)
  {
    /* edits a text and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     status = status of text (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $current_status = $this->getTextStatus($text_id);

    $stmt = $this->db->query('UPDATE ' . $this->db->au_texts . ' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :text_id');
    // bind all VALUES
    $this->db->bind(':status', $status);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':text_id', $text_id); // text that is updated

    $err = false; // set error variable to false
    $count_datasets = 0; // init row count

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $count_datasets = intval($this->db->rowCount());

      // check if text was deactivated or archived before and is now activated, if yes then update all users consent count
      if (!($current_status == 1) && $status == 1) {
        // now activated, so increment consent counter for users
        $this->updateConsentsUsers(1);
      }
      if ($current_status == 1 && !($status == 1)) {
        // now deactivated, so decrement consent counter for users
        $this->updateConsentsUsers(-1);
      }

      $this->syslog->addSystemEvent(0, "Text status changed " . $text_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $count_datasets; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets


      return $returnvalue; // return number of affected rows to calling script
    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }
  }// end function

  public function deleteText($text_id, $updater_id = 0)
  {
    /* deletes texts, accepts text id (hash (varchar) or db id (int))

    */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db  id if necessary (when hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_texts . ' WHERE id = :id');
    $this->db->bind(':id', $text_id);

    $err = false;
    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $count_datasets = intval($this->db->rowCount());
      // update all users - needed consent field
      $this->updateConsentsUsers(-1);

      // clean up table consent
      $stmt = $this->db->query('DELETE FROM ' . $this->db->au_consent . ' WHERE text_id = :id');
      $this->db->bind(':id', $text_id);

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets


        return $returnvalue;
      }

      $this->syslog->addSystemEvent(0, "Text deleted, id = " . $text_id . " by " . $updater_id, 0, "", 1);
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

  }// end function

} // end class
?>
