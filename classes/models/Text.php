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


  public function getTextHashId($text_id)
  {
    /* returns hash_id of a text for a integer id
     */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('SELECT hash_id FROM ' . $this->db->au_texts . ' WHERE id = :id');
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
      $returnvalue['data'] = $texts[0]['hash_id']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    }
  }// end function

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


  public function getTextConsentStatus($text_id, $user_id)
  {
    /* returns the consent status for this text for a specific user
     */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('SELECT consent FROM ' . $this->db->au_consent . ' WHERE text_id = :text_id AND user_id = :user_id');
    $this->db->bind(':text_id', $text_id); // bind text id
    $this->db->bind(':user_id', $user_id); // bind user id

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
      $returnvalue['data'] = $texts[0]['consent']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function archiveText($text_id, $updater_id = 0)
  {
    /* sets the status of a text to 4 = archived
    accepts db id and hash id
    updater_id is the id of the user that did the update
    */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    return $this->setTextStatus($text_id, 4, $updater_id);

  }

  public function activateText($text_id, $updater_id = 0)
  {
    /* sets the status of a text to 1 = active
    accepts db id and hash id
    updater_id is the id of the user that did the update
    */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    return $this->setTextStatus($text_id, 1, $updater_id);

  }

  public function deactivateText($text_id, $updater_id)
  {
    /* sets the status of a text to 0 = inactive
    accepts db id and hash id
    updater_id is the id of the user that did the update
    */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    return $this->setTextStatus($text_id, 0, $updater_id);
  }

  public function setTextToReview($text_id, $updater_id)
  {
    /* sets the status of a text to 5 = to review
    accepts db id and hash id
    updater_id is the id of the user that did the update
    */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    return $this->setTextStatus($text_id, 5, $updater_id);

  }

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
      $returnvalue['data'] = 1; // returned data
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


  public function searchInTexts($searchstring, $status = 1)
  {
    // searches for a term / string in texts and returns all texts
    $extra_where = " AND (headline LIKE '%" . searchstring . "%' OR body LIKE '%" . searchstring . "%') ";
    $ret_value = getTexts(0, 0, 3, 0, $status, $extra_where);

    return $ret_value;
  }

  public function getTexts($offset = 0, $limit = 0, $orderby = 3, $asc = 0, $status = 1, $extra_where = "", $last_update = 0, $location = 0, $creator_id = 0, $user_needs_to_consent = -1, $service_id_consent = -1)
  {
    /* returns text list (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (3)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    status (int) 0=inactive, 1=active, 2=suspended, 3=archived, 5= in review defaults to active (1)
    last_update = date that specifies texts younger than last_update date (if set to 0, gets all texts)
    extra_where = extra parameters for where clause, synthax " AND XY=4"
    creator_id = specifies a certain user that wrote the text (author), if set to 0, all texts are displayed
    user_needs_to_consent specifies texts that need (1) or dont need (0) a consent by the user (set to -1, if all texts shoud be shown)
    service_id_consent refer to a certain service that this text is for (set to -1 if no service is linked)
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

    if ($location > 0) {
      // if a location id is set then add to where clause
      if ($extra_where == "") {
        $extra_where = " WHERE ";
      }
      $extra_where .= " location = " . $location; // get specific texts for a certain page (page id)
    }

    if ($creator_id > 0) {
      if ($extra_where == "") {
        $extra_where = " WHERE ";
      }
      // if a creator id is set then add to where clause
      $extra_where .= " AND creator_id = " . $creator_id; // get specific texts for a creator / moderator / admin
    }

    if ($user_needs_to_consent > -1) {
      if ($extra_where == "") {
        $extra_where = " WHERE ";
      }
      // if a target user id is set then add to where clause
      $extra_where .= " AND user_needs_to_consent = " . $user_needs_to_consent; // get only texts that need (1)/dont need (0) consent
    }

    if ($service_id_consent > -1) {
      if ($extra_where == "") {
        $extra_where = " WHERE ";
      }
      // if a target user id is set then add to where clause
      $extra_where .= " AND service_id_consent = " . $service_id_consent; // get only texts that are linked to a certain service
    }


    if (!(intval($last_update) == 0)) {
      if ($extra_where == "") {
        $extra_where = " WHERE ";
      }
      // if a publish date is set then add to where clause
      $extra_where .= " AND last_update > \'" . $last_update . "\'";
    }

    switch (intval($orderby)) {
      case 0:
        $orderby_field = "status";
        break;
      case 1:
        $orderby_field = "creator_id";
        break;
      case 2:
        $orderby_field = "created";
        break;
      case 3:
        $orderby_field = "last_update";
        break;
      case 4:
        $orderby_field = "id";
        break;
      case 5:
        $orderby_field = "headline";
        break;
      case 6:
        $orderby_field = "body";
        break;
      case 7:
        $orderby_field = "user_needs_to_consent";
        break;
      case 8:
        $orderby_field = "consent_text";
        break;

      default:
        $orderby_field = "last_update";
    }

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
    $stmt = $this->db->query('SELECT * FROM ' . $this->db->au_texts . $extra_where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    if ($limit) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
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
      $count_datasets = $this->converters->getTotalDatasets($this->db->au_texts, "id > 0" . $status . $extra_where);
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
    /* edits a  text and returns insert id (text id) if successful, accepts the above parameters
    headline, body is the content
    consent_text = text that is displayed next to the checkbox for the user consent
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

  public function setTextNeedsConsent($text_id, $user_needs_to_consent, $updater_id = 0)
  {
    /* edits a text and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     $user_needs_to_consent = user needs (1) or doesnt need to consent to this text (0) or consent is mandatory (2)
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $current_consent_value = $this->converters->getTextConsentValue($text_id);

    $stmt = $this->db->query('UPDATE ' . $this->db->au_texts . ' SET user_needs_to_consent= :user_needs_to_consent, last_update= NOW(), updater_id= :updater_id WHERE id= :text_id');
    // bind all VALUES
    $this->db->bind(':user_needs_to_consent', $user_needs_to_consent);
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

      // update user consent values
      if (intval($consent_value) < intval($current_consent_value)) {
        $this->updateConsentsUsers(-1); // decrement users needed consents (consent value was lowered)
      } else {
        if (intval($consent_value) > intval($current_consent_value) && intval($consent_value) == 2) {
          $this->updateConsentsUsers(1); // increment users needed consents (consent value was set to 2 and is higher than before)
        }
      } // end else

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

  public function linkTextToService($text_id, $service_id_consent, $updater_id = 0)
  {
    /* edits a text and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     $service_id_consent = id of the service this text is linked to
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_texts . ' SET $service_id_consent= :$service_id_consent, last_update= NOW(), updater_id= :updater_id WHERE id= :text_id');
    // bind all VALUES
    $this->db->bind(':$service_id_consent', $$service_id_consent);
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
      $this->syslog->addSystemEvent(0, "Text (" . $text_id . ") linked to service id " . $service_id_consent . " by " . $updater_id, 0, "", 1);
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

  public function setTextContent($text_id, $headline, $body, $consent_text, $user_needs_to_consent = 0, $updater_id = 0)
  {
    /* edits a text and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     headline, body, consent_text  = content  of text
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $text_id = $this->converters->checkTextId($text_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $content = trim($content);
    $stmt = $this->db->query('UPDATE ' . $this->db->au_texts . ' SET headline= :headline, body= :body, consent_text= :consent_text,  last_update= NOW(), updater_id= :updater_id, user_needs_to_consent = :user_needs_to_consent WHERE id= :text_id');
    // bind all VALUES
    $this->db->bind(':headline', $headline);
    $this->db->bind(':body', $body);
    $this->db->bind(':user_needs_to_consent', $user_needs_to_consent);
    $this->db->bind(':consent_text', $consent_text);
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
      $this->syslog->addSystemEvent(0, "Text content changed " . $text_id . " by " . $updater_id, 0, "", 1);
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
