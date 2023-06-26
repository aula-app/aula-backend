<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}


class User {
    private $db;

    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->crypt = $crypt;
        $this->syslog = $syslog;
        $this->converters = new Converters ($db); // load converters


    }// end function

    private function decrypt ($content){
      // decryption helper
      return $content = $this->crypt->decrypt ($content);
    }

    public function getUserBaseData($user_id) {
      /* returns user base data for a specified db id */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        // descrypt the encrypted fields
        /*$users[0]['realname'] = $this->decrypt ($users[0]['realname']);
        $users[0]['displayname'] = $this->decrypt ($users[0]['displayname']);
        $users[0]['username'] = $this->decrypt ($users[0]['username']);
        $users[0]['email'] = $this->decrypt ($users[0]['email']);*/

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $users[0]; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;
      }
    }// end function

    public function getUserHashId($user_id) {
      /* returns hash_id of a user for a integer user id
      */
      $stmt = $this->db->query('SELECT hash_id FROM '.$this->db->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $users[0]['hash_id']; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;

      }
    }// end function

    public function setUserProperty ($user_id, $property, $prop_value, $updater_id=0) {
        /* edits a user (user_basedata) and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         $property = field name in db
         $propvalue = value for property
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET '.$property.'= :prop_value, last_update= NOW(), updater_id= :updater_id WHERE id= :user_id');
        // bind all VALUES
        $this->db->bind(':prop_value', $prop_value);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':user_id', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User property ".$property." changed for id ".$user_id." to ".$prop_value." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;
        } else {
          $this->syslog->addSystemEvent(1, "Error changing user property ".$property." for id ".$user_id." to ".$prop_value." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function revokeVoteRight($user_id, $user_id_target, $topic_id, $updater_id) {
      /* Returns Database ID of user when hash_id is provided
      */
      //sanitize variables
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)
      $user_id_target = $this->converters->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)


      $stmt = $this->db->query('SELECT topic_id FROM '.$this->db->au_delegation.' WHERE user_id_original = :user_id AND user_id_target = :user_id_target AND topic_id = :topic_id');
      // bind all VALUES
      $this->db->bind(':user_id', $user_id); // gives the voting right
      $this->db->bind(':topic_id', $topic_id); // id of the topic
      $this->db->bind(':user_id_target', $user_id_target); // receives the voting right

      $users = $this->db->resultSet();
      if (count($users)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // no error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        // remove delegation from db table
        $stmt = $this->db->query('DELETE FROM '.$this->db->au_delegation.' WHERE user_id_original = :user_id AND user_id_target = :user_id_target AND topic_id = :topic_id');
        // bind all VALUES
        $this->db->bind(':user_id', $user_id); // gives the voting right
        $this->db->bind(':topic_id', $topic_id); // id of the topic
        $this->db->bind(':user_id_target', $user_id_target); // receives the voting right

        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        $count_data = intval ($this->db->rowCount());
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Delegation deleted for user id ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // no error code
          $returnvalue ['data'] = $count_data; // returned data
          $returnvalue ['count'] = $count_data; // returned count of datasets

          return $returnvalue;

          return ; // return number of affected rows to calling script
        } else {
          //$this->syslog->addSystemEvent(1, "Error deleting delegation for user with id ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // no error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

      }
    }// end function
    /*
    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // no error code
    $returnvalue ['data'] = $messages; // returned data
    $returnvalue ['count'] = $count_datasets; // returned count of datasets
    */


    public function delegateVoteRight ($user_id, $user_id_target, $topic_id, $updater_id) {
      /* delegates voting rights from one user to another within a topic, accepts user_id (by hash or id) and topic id (by hash or id)
      */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $user_id_target = $this->converters->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

      // check if user and topic exist
      $user_exist = $this->converters->checkUserExist($user_id);
      $user_exist_target = $this->converters->checkUserExist($user_id_target);
      $topic_exist = $this->converters->checkTopicExist($topic_id);

      $data_delegation ['user_exist_target']=$user_exist_target;

      if ($user_exist==1 && $topic_exist==1 && $user_exist_target==1) {
        // everything ok, users and topic exists

        // add relation to database (delegation)

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_delegation.' (topic_id, user_id_original, user_id_target, status, created, last_update, updater_id) VALUES (:topic_id, :user_id, :user_id_target, 1, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE user_id_original = :user_id, user_id_target = :user_id_target, status = 1, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':topic_id', $topic_id);
        $this->db->bind(':user_id', $user_id); // gives the voting right
        $this->db->bind(':user_id_target', $user_id_target); // receives the voting right
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }

        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added delegation for user ".$user_id." for topic ".$topic_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; //db  error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;


        } else {
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; //db  error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }

      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; //  error code
        $returnvalue ['data'] = $user_exist.$user_exist_target; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      }

    } // end function

    public function giveConsent ($user_id, $text_id, $consent_value = 1, $updater_id = 0) {
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

      if ($user_exist==1 && $text_exist==1) {
        // everything ok, users and text exists

        // add relation to database (consent)

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_consent.' (user_id, text_id, consent, status, created, last_update, date_consent, updater_id) VALUES (:user_id, :text_id, :consent, 1, NOW(), NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE user_id = :user_id, text_id = :text_id, consent = :consent, status = 1, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':text_id', $text_id); // id of the text that the user consents to
        $this->db->bind(':user_id', $user_id); // gives consent
        $this->db->bind(':consent', $consent_value); // consent type 0 = no consent, 1 = consent given
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }

        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added consent for user ".$user_id." for text ".$text_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; //db  error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;


        } else {
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; //db  error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }

      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; //db  error code
        $returnvalue ['data'] = $user_exist.$text_exist; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      }

    } // end function


    public function setDelegationStatus ($user_id, $status, $topic_id = 0, $target = 0) {
        /* edits the status of a delegation and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status for delegation (0 = inactive, 1=active, 2 = suspended, 4 = archived)
         if topic_id = 0 all delegations of the user are deleted
         target specifies if original or target users are adressed 0 = remove delegations of delegating user (original owner->default) 1= remove delegation of target user
        */

        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $topic_id = $this->converters->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

        $topic_clause = "";

        if ($topic_id > 0){
          // topic id is set to >0 -> delete only delegations for this user for the specified topic
          $topic_clause = " AND topic_id = ".$topic_id;
        }

        $target_user = "user_id_original";
        if ($target>0) {
          $target_user = "user_id_target";
        }

        $stmt = $this->db->query('UPDATE '.$this->db->au_delegation.' SET status = :status, last_update = NOW() WHERE '.$target_user.' = :user_id'.$topic_clause);
        // bind all VALUES
        $this->db->bind(':status', $status);

        $this->db->bind(':user_id', $user_id); // user original id that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        $count_data = intval($this->db->rowCount());

        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Delegation status changed ".$user_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = $count_data; // returned data
          $returnvalue ['count'] = $count_data; // returned count of datasets

          return $returnvalue;

        } else {
          $this->syslog->addSystemEvent(1, "Error changing delegation status of user ".$user_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 2; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function getReceivedDelegations ($user_id, $topic_id) {
      /* returns received delegations for a specific user (user_id) in the topic
      */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_users_basedata.' LEFT JOIN '.$this->db->au_delegation.' ON ('.$this->db->au_users_basedata.'.id = '.$this->db->au_delegation.'.user_id_original) WHERE '.$this->db->au_delegation.'.user_id_target = :id AND '.$this->db->au_delegation.'.topic_id = :topic_id');
      $this->db->bind(':id', $user_id); // bind userid
      $this->db->bind(':topic_id', $topic_id); // bind topic id
      $users = $this->db->resultSet();
      if (count($users)<1){

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $users; // returned data
        $returnvalue ['count'] = count ($users); // returned count of datasets

        return $returnvalue;
      }
    } // end function


    public function getGivenDelegations ($user_id, $topic_id) {
      /* returns received delegations for a specific user (user_id) for the topic
      */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_users_basedata.' LEFT JOIN '.$this->db->au_delegation.' ON ('.$this->db->au_users_basedata.'.id = '.$this->db->au_delegation.'.user_id_target) WHERE '.$this->db->au_delegation.'.user_id_original = :id AND '.$this->db->au_delegation.'.topic_id = :topic_id');
      $this->db->bind(':id', $user_id); // bind userid
      $this->db->bind(':topic_id', $topic_id); // bind topic id
      $users = $this->db->resultSet();
      if (count($users)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $users; // returned data
        $returnvalue ['count'] = count ($users); // returned count of datasets

        return $returnvalue;
      }
    } // end function

    public function getGivenConsents ($user_id) {
      /* returns all given consents to texts / terms
      returns ids of the texts, headline and body of the text
      */
      $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT '.$this->db->au_texts.'.id, '.$this->db->au_texts.'.headline, '.$this->db->au_texts.'.body, '.$this->db->au_texts.'.consent_text, '.$this->db->au_texts.'.status, '.$this->db->au_texts.'.user_needs_to_consent, '.$this->db->au_texts.'.service_id_consent, '.$this->db->au_consent.'.date_consent ,'.$this->db->au_consent.'.consent , '.$this->db->au_consent.'.date_revoke FROM '.$this->db->au_texts.' LEFT JOIN '.$this->db->au_consent.' ON ('.$this->db->au_texts.'.id = '.$this->db->au_consent.'.text_id) WHERE '.$this->db->au_consent.'.usr_id = :user_id');
      $this->db->bind(':id', $user_id); // bind userid
      $consents = $this->db->resultSet();
      if (count($consents)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $consensts; // returned data
        $returnvalue ['count'] = count ($consents); // returned count of datasets

        return $returnvalue;
      }
    } // end function

    public function getNecessaryConsents () {
      // gets consents that are necessary to use the system
      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_texts.' WHERE status = 1 AND user_needs_to_consent = 2');

      $texts = $this->db->resultSet();
      $needed_consents = count ($texts);

      if (count($texts)<1){
        // no texts that need consent present
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2 ; // error code
        $returnvalue ['data'] = 0; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2 ; // error code
        $returnvalue ['data'] =  $texts; // returned data
        $returnvalue ['count'] = $needed_consents; // returned count of datasets

        return $returnvalue;

      }
    } // end function

    public function getMissingConsents ($user_id) {
      // returns all the missing consents that this user has not yet consented to
      $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)
      // first get all the mandatory consents for this user already given (consent =1)

      $stmt = $this->db->query('SELECT '.$this->db->au_texts.'.id FROM '.$this->db->au_texts.' INNER JOIN '.$this->db->au_consent.' ON ('.$this->db->au_consent.'.text_id = '.$this->db->au_texts.'.id) WHERE ('.$this->db->au_consent.'.user_id = :user_id AND '.$this->db->au_consent.'.consent = 1) AND '.$this->db->au_texts.'.status = 1 AND '.$this->db->au_texts.'.user_needs_to_consent  = 2');
      $this->db->bind(':user_id', $user_id); // bind userid

      $consents = $this->db->resultSet();
      $given_consents = count ($consents);

      $i=0;
      $ids[0] = 0;

      foreach ($consents as $key) {
        $ids[$i] = $key['id'];
        $i++;
      }

      $stmt = $this->db->query('SELECT id, headline, body, consent_text FROM '.$this->db->au_texts.' WHERE id NOT IN ('.implode(",", $ids).') AND user_needs_to_consent = 2');

      $texts = $this->db->resultSet();
      $missing_consents = count ($texts);
      if ($missing_consents<1){
        // no consents missing...
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = 0; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
      // everything ok, return 1
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue ['data'] = $texts; // returned data
      $returnvalue ['count'] = $missing_consents; // returned count of datasets

      return $returnvalue;

    } // end function

    public function checkHasUserGivenConsentsForUsage ($user_id) {
      /* returns 1 if user has consented to all necessary texts
      that are needed for aula usage 0, if not
      */

      $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)
      // first get all the texts that need consents that are crucial for system usage

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_texts.' WHERE status = 1 AND user_needs_to_consent = 2');

      $texts = $this->db->resultSet();
      $needed_consents = count ($texts);

      if (count($texts)<1){
        // no texts that need consent present
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2 ; // error code
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        // There are texts that need consent - now check if user has given all necessary consents
        $i=0;
        $ids[0] = 0;

        foreach ($texts as $key) {
          $ids[$i] = $key['id'];
          $i++;
        }
        //echo ("<br>IDs: ".implode(",", $ids));

        $stmt = $this->db->query('SELECT text_id FROM '.$this->db->au_consent.' WHERE user_id = :user_id AND text_id IN ('.implode(",", $ids).') AND consent = 1');
        //echo ('<br>SELECT text_id FROM '.$this->db->au_consent.' WHERE user_id = :user_id AND text_id IN ('.implode(",", $ids).') AND consent = 1');
        $this->db->bind(':user_id', $user_id); // bind userid

        $consents = $this->db->resultSet();
        $given_consents = count ($consents);
        if ($given_consents < $needed_consents){
          // not every necessary consent given yet, return 0
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = 0; // returned data
          $returnvalue ['count'] = $given_consents; // returned count of datasets

          return $returnvalue;
        }
          // everything ok, return 1
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = $given_consents; // returned count of datasets

          return $returnvalue;
      }
    } // end function


    public function giveBackAllDelegations ($user_id, $topic_id = 0){
      // give back all delegations for a) a certain topic (topic id>0) or all delegations (topic_id=0)
      return $this->removeUserDelegations ($user_id, $topic, 1); // 1 at the end indicates that target user is meant
    }

    public function giveBackDelegation ($my_user_id, $user_id_original, $topic_id = 0){
      // give back delegations from a certain user ($user_id_original) for a) a certain topic (topic id>0) or all delegations (topic=0)
      return $this->removeSpecificDelegation ($my_user_id, $user_id_original, $topic_id); // 1 at the end indicates that target user is meant
    }

    public function removeSpecificDelegation ($user_id_target, $user_id_original, $topic_id = 0){
      /* remove delegation from a specific user A (user_id_original) to a specific user B (user_id_target) for
      a) a certain topic (topic id>0) or all delegations (topic=0), defaults to all topics
      */
      $user_id_target = $this->converters->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $user_id_original = $this->converters->checkUserId($user_id_original); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $topic_id = $this->converters->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $topic_clause = "";

      if ($topic_id > 0){
        // topic id is set to >0 -> delete only delegations for this user in the specified topic
        $topic_clause = " AND topic_id = ".$topic_id;
      }

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_delegation.' WHERE user_id_target = :user_id_target AND user_id_original = :user_id_original'.$topic_clause);
      $this->db->bind (':user_id_target', $user_id_original);
      $this->db->bind (':user_id_original', $user_id_target);

      $err=false;
      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

          $err=true;
      }
      if (!$err)
      {
        $this->syslog->addSystemEvent(0, "User delegation(s) deleted with id ".$user_id." for topic ".$topic_id, 0, "", 1);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $users; // returned data
        $returnvalue ['count'] = intval ($this->db->rowCount()); // returned count of datasets

        return $returnvalue;

      } else {
        $this->syslog->addSystemEvent(1, "Error deleting user delegation(s) with id ".$user_id." for topic ".$topicid, 0, "", 1);
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    }

    public function removeUserDelegations ($user_id, $topic_id = 0, $target = 0)
    {
      /* removes all delegations of a specified user (user id) for a specified topic, accepts db id or hash id
       if topic_id = 0 all delegations of the user are deleted
       target specifies if original or target users are adressed 0 = remove delegations of delegating user (original owner->default) 1= remove delegation of target
      */

      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $topic_id = $this->converters->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $topic_clause = "";

      if ($topic_id > 0){
        // topic id is set to >0 -> delete only delegations for this user in the specified topic
        $topic_clause = " AND topic_id = ".$topic_id;
      }

      $target_user = "user_id_original";
      if ($target>0) {
        $target_user = "user_id_target";
      }

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_delegation.' WHERE '.$target_user.' = :id'.$topic_clause);
      $this->db->bind (':id', $user_id);
      $err=false;
      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

          $err=true;
      }
      $count_data = intval ($this->db->rowCount());

      if (!$err)
      {
        $this->syslog->addSystemEvent(0, "User delegation(s) deleted with id ".$user_id." for topic ".$topic_id, 0, "", 1);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $count_data; // returned data
        $returnvalue ['count'] = $count_data; // returned count of datasets

        return $returnvalue;

      } else {
        $this->syslog->addSystemEvent(1, "Error deleting user delegation(s) with id ".$user_id." for topic ".$topic_id, 0, "", 1);
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;


      }

    } // end function




    public function addUserToRoom($user_id, $room_id, $status=1, $updater_id=0) {
      /* adds a user to a room, accepts user_id (by hash or id) and room id (by hash or id)
      returns 1,1 = ok, 0,1 = user id not in db 0,2 room id not in db 0,3 user id not in db room id not in db */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)
      // check if user and room exist
      $user_exist = $this->converters->checkUserExist($user_id);
      $room_exist = $this->converters->checkRoomExist($room_id);

      if ($user_exist==1 && $room_exist==1) {
        // everything ok, user and room exists
        // add relation to database

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_rel_rooms_users.' (room_id, user_id, status, created, last_update, updater_id) VALUES (:room_id, :user_id, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE room_id = :room_id, user_id = :user_id, status = :status, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':room_id', $room_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }

        if (!$err)
        {
          $insertid = intval($this->db->lastInsertId());
          $this->syslog->addSystemEvent(0, "Added user ".$user_id." to room ".$room_id, 0, "", 1);

          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = $insertid; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding user ".$user_id." to room ".$room_id, 0, "", 1);

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } // end function

    public function moveUserBetweenRooms ($user_id, $room_id1, $room_id2, $updater_id) {
      // moves a user from room 1 to room 2
      $user_id = $this->converters->checkUserId($user_id); // auto convert
      $room_id1 = $this->converters->checkRoomId($room_id1); // auto convert
      $room_id2 = $this->converters->checkRoomId($room_id2); // auto convert

      $ret_value = removeUserFromRoom($room_id1, $user_id);

      if ($ret_value['success']){
          // only if removal was successful add to room 2
          $ret_value = addUserToRoom ($room_id2, $user_id);

          if ($ret_value['success']){
            $returnvalue['success'] = true; // set return value
            $returnvalue['error_code'] = 0; // error code
            $returnvalue ['data'] = false; // returned data
            $returnvalue ['count'] = 1; // returned count of datasets

            return $returnvalue;
          } else {
            // error occured while adding to room 2
            $returnvalue['success'] = false; // set return value
            $returnvalue['error_code'] = 1; // error code
            $returnvalue ['data'] = false; // returned data
            $returnvalue ['count'] = 0; // returned count of datasets

            return $returnvalue;

          }

      }else {
        // error occured while removing from room 1
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      } // end else
    } // end function

    public function moveUserBetweenGroups ($user_id, $group_id1, $group_id2, $updater_id) {
      // moves a user from group 1 to group 2
      $user_id = $this->converters->checkUserId($user_id); // auto convert
      $group_id1 = $this->converters->checkGroupId($group_id1); // auto convert
      $group_id2 = $this->converters->checkGroupId($group_id2); // auto convert

      $ret_value = removeUserFromGroup($group_id1, $user_id);

      if ($ret_value['success']){
          // only if removal was successful add to group 2
          $ret_value = addUserToGroup ($group_id2, $user_id);

          if ($ret_value['success']){
            $returnvalue['success'] = true; // set return value
            $returnvalue['error_code'] = 0; // error code
            $returnvalue ['data'] = false; // returned data
            $returnvalue ['count'] = 1; // returned count of datasets

            return $returnvalue;
          } else {
            // error occured while adding to group 2
            $returnvalue['success'] = false; // set return value
            $returnvalue['error_code'] = 1; // error code
            $returnvalue ['data'] = false; // returned data
            $returnvalue ['count'] = 0; // returned count of datasets

            return $returnvalue;

          }

      }else {
        // error occured while removing from group 1
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      } // end else
    } // end function

    public function removeUserFromRoom($room_id, $user_id) {
      /* deletes a user from a room
      */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_rel_rooms_users.' WHERE user_id = :userid AND room_id = :roomid' );
      $this->db->bind(':roomid', $room_id); // bind room id
      $this->db->bind(':userid', $user_id); // bind user id

      $err=false;
      try {
        $rooms = $this->db->resultSet();
        $rowcount = $this->db->rowCount();

      } catch (Exception $e) {
          echo 'Error occured while deleting user '.$user_id.' from room: '.$room_id,  $e->getMessage(), "\n"; // display error
          $err=true;
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

      }
      // remove delegations for this user
      // todo -> remove delegations from this user
      //  $this->deleteRoomUserDelegations ($room_id, $user_id);
      // remove all delegations for this user
      $this->removeUserDelegations ($user_id, 0, 0); // active delegations (original user)
      $this->removeUserDelegations ($user_id, 0, 1); // passive delegations (target user)


      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue ['data'] = false; // returned data
      $returnvalue ['count'] = 1; // returned count of datasets

      return $returnvalue;


    }// end function

    public function followUser ($user_id, $user_id_target) {
      return $this->relateUser ($user_id, $user_id_target, 1, 0, 1);
    }

    public function friendUser ($user_id, $user_id_target) {
      return $this->relateUser ($user_id, $user_id_target, 1, 0, 2);
    }

    public function blockUser ($user_id, $user_id_target) {
      return $this->relateUser ($user_id, $user_id_target, 1, 0, 0);
    }

    public function unfriendUser ($user_id, $user_id_target) {
      return $this->removeUserRelation ($user_id, $user_id_target);
    }

    public function unblockUser ($user_id, $user_id_target) {
      return $this->removeUserRelation ($user_id, $user_id_target);
    }

    public function unfollowUser ($user_id, $user_id_target) {
      return $this->removeUserRelation ($user_id, $user_id_target);
    }

    public function relateUser($user_id, $user_id_target, $status=1, $updater_id=0, $type=1) {
      /*
      user A (user_id) follows user B (user_id_target), type = 1 => follow /  type = 2 => friend / type = 0 => blocked
       */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $user_id_target = $this->converters->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      // check if users exist
      $user_exist = $this->converters->checkUserExist($user_id);
      $user_exist_target = $this->converters->checkUserExist($user_id_target);

      if ($user_exist==1 && $user_exist_target==1) {
        // everything ok, both users exist
        // add relation to database

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_rel_user_user.' (user_id1, user_id2, type, status, created, last_update, updater_id) VALUES (:user_id1, :user_id2, :type, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE user_id1 = :user_id1, user_id2 = :user_id2, type = :type, status = :status, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':user_id1', $user_id);
        $this->db->bind(':user_id2', $user_id_target);
        $this->db->bind(':status', $status);
        $this->db->bind(':type', $type);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }

        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added user relation (type:".$type.") ".$user_id."-".$user_id_target, 0, "", 1);
          $insertid = intval($this->db->lastInsertId());
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = $insertid; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding user relation (type:".$type.") ".$user_id, 0, "", 1);

          $this->syslog->addSystemEvent(0, "Added user relation (type:".$type.") ".$user_id."-".$user_id_target, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } // end function

    public function removeUserRelation($user_id, $user_id_target) {
      /* deletes a user relation form the db
      */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $user_id_target = $this->converters->checkUserId($user_id_target); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_rel_user_user.' WHERE user_id1 = :user_id1 AND user_id2 = :user_id2' );
      $this->db->bind(':user_id1', $user_id); // bind user id
      $this->db->bind(':user_id2', $user_id_target); // bind user id

      $err=false;
      try {
        $users = $this->db->execute();
        $rowcount = $this->db->rowCount();

      } catch (Exception $e) {
          echo 'Error occured while removing relation between user '.$user_id.' and user '.$user_id_target,  $e->getMessage(), "\n"; // display error
          //$this->syslog->addSystemEvent(0, "Error while removing user relation (delete from db) ".$user_id."-".$user_id_target, 0, "", 1);
          $err=true;
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
      }
      $this->syslog->addSystemEvent(0, "Removed user relation (delete from db) ".$user_id."-".$user_id_target, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue ['data'] = 1; // returned data
      $returnvalue ['count'] = 1; // returned count of datasets

      return $returnvalue;

    }// end function

    public function removeUserFromGroup($group_id, $user_id) {
      /* deletes a user from a group
      */

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_rel_groups_users.' WHERE user_id = :userid AND group_id = :groupid' );
      $this->db->bind(':groupid', $group_id); // bind group id
      $this->db->bind(':userid', $user_id); // bind user id

      $err=false;
      try {
        $groups = $this->db->resultSet();

      } catch (Exception $e) {
          //echo 'Error occured while removing user from group: ',  $e->getMessage(), "\n"; // display error
          $err=true;

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
      }

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue ['data'] = $this->db->rowCount(); // returned data
      $returnvalue ['count'] = $this->db->rowCount(); // returned count of datasets

      return $returnvalue;

    }// end function

    public function addUserToGroup($user_id, $group_id, $status, $updater_id) {
      /* adds a user to a group, accepts user_id (by hash or id) and group id (by hash or id)
      returns 1,1 = ok, 0,1 = user id not in db 0,2 group id not in db 0,3 user id not in db group id not in db */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $group_id = $this->converters->checkGroupId($group_id); // checks id and converts id to db id if necessary (when hash id was passed)
      // check if user and group exist
      $user_exist = $this->converters->checkUserExist($user_id);
      $group_exist = $this->converters->checkGroupExist($group_id);

      if ($user_exist==1 && $group_exist==1) {
        // everything ok, user and group exists
        // add relation to database

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_rel_groups_users.' (group_id, user_id, status, created, last_update, updater_id) VALUES (:group_id, :user_id, :status, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE group_id = :group_id, user_id = :user_id, status = :status, last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':group_id', $group_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }

        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added user ".$user_id." to group ".$group_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding user ".$user_id." to group ".$group_id, 0, "", 1);

          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } // end function

    public function checkLogin ($username, $pw){

      $check_credentials = $this->checkCredentials ($username, $pw);

      if ($check_credentials['success']){
        // credentials are ok, set last login in db
        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET last_login = NOW() WHERE id = :user_id');
        $user_id = $check_credentials['data'];
        $this->db->bind(':user_id', $user_id); // bind user id
        $err = false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $insertid = intval($this->db->lastInsertId());
          $this->syslog->addSystemEvent(0, "Successful login user ".$user_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = $user_id; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;


        } else {
          $this->syslog->addSystemEvent(1, "DB Error login user ".$user_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // db error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
      } else {
        $this->syslog->addSystemEvent(1, "DB Error login user ".$user_id, 0, "", 1);
        $returnvalue['success'] = truex; // set return value
        $returnvalue['error_code'] = 2; // db error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      }// end if
    } // end function

    public function checkCredentials($username, $pw) { // pw = clear text
      /* checks credentials and returns database user id (credentials correct) or 0 (credentials not correct)
      username is clear text
      pw is clear text
      */

      // create temp blind index
      $bi = md5(strtolower($username));

      $stmt = $this->db->query('SELECT id,username,pw,hash_id FROM '.$this->db->au_users_basedata.' WHERE bi= :bi');
      $this->db->bind(':bi', $bi); // blind index
      $users = $this->db->resultSet();


      if (count($users)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      } // nothing found or empty database

      // new
      $dbpw = $users[0]['pw'];
      // check PASSWORD
      if (password_verify($pw, $dbpw))
      {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $users[0]['id']; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      }else {

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 3; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      }

      /*foreach ($users as $user) {
          $decrypted_username = $this->crypt->decrypt ($user['username']);
          // check if match
          if (strcmp($decrypted_username,$username)==0)
          {
            $dbpw = $user['pw'];
            // check PASSWORD
            if (password_verify($pw, $dbpw))
            {
              return $user['id'];
            }else {

              return 0;
            }
          } // end if (strcmp....)

      } // end foreach */


    }// end function


    public function getUsers($offset, $limit, $orderby=3, $asc=0, $status=-1, $extra_where="") {
      /* returns userlist (associative array) with start and limit provided
      extra_where = SQL Clause that can be added to where in the query like AND status = 1
      */
      // init vars
      $orderby_field="";
      $asc_field ="";

      $limit_string=" LIMIT :offset , :limit ";
      $limit_active=true;

      // check if offset an limit are both set to 0, then show whole list (exclude limit clause)
      if ($offset==0 && $limit==0){
        $limit_string="";
        $limit_active=false;
      }

      if ($status > -1){
        // specific status selected / -1 = get all status values
        $extra_where .= " AND status = ".$status;
      }


      switch (intval ($orderby)){
        case 0:
        $orderby_field = "registration_status";
        break;
        case 1:
        $orderby_field = "realname";
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
        $orderby_field = "username";
        break;
        case 6:
        $orderby_field = "status";
        break;

        default:
        $orderby_field = "last_update";
      }

      switch (intval ($asc)){
        case 0:
        $asc_field = "DESC";
        break;
        case 1:
        $asc_field = "ASC";
        break;
        default:
        $asc_field = "DESC";
      }

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_users_basedata.' WHERE id > 0 '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);

      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      // $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $users = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting users: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // db error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

      }

      $count_data = count ($users);

      if ($count_data<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;


      }else {
        $total_datasets = $this->converters->getTotalDatasets ($this->db->au_users_basedata, "id > 0".$extra_where);

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $users; // returned data
        $returnvalue ['count'] = $total_datasets; // returned count of datasets

        return $returnvalue;


      }
    }// end function

    public function checkUserExistsByUsername($username){
      // checks if a group with this name is already in database
      // generate blind index
      $bi = md5 (strtolower (trim ($username)));

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_users_basedata.' WHERE bi = :bi');
      $this->db->bind(':bi', $bi); // bind blind index
      $users = $this->db->resultSet();
      if (count($users)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      }else {
        $user_id = $users[0]['id']; // get user id from db
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $user_id; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;

      }
    }


    public function addUser($realname, $displayname, $username, $email, $password, $status, $updater_id=0, $userlevel=10) {
        /* adds a user and returns insert id (userid) if successful, accepts the above parameters
         realname = actual name of the user, status = status of inserted user (0 = inactive, 1=active)
         userlevel = Rights level for the user 10=guest, 20 = standard, 30 =moderator 40 = super mod 50 = admin 60 = tech admin
        */

        // sanitize vars
        $realname = trim ($realname);
        $displayname = trim ($displayname);
        $username = trim ($username);
        $email = trim ($email);
        $password = trim ($password);
        $updater_id = intval ($updater_id);
        $status = intval($status);
        $userlevel = intval ($userlevel);


        // check if user name is still available
        $temp_user = $this->checkUserExistsByUsername($username); // check username in db
        $temp_user_id = $temp_user ['data']; // get id from array

        if ($temp_user_id>0){
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 2; // db error code
          $returnvalue ['data'] = $temp_user_id; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }

        // generate hash password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // generate blind index
        $bi = md5 (strtolower (trim ($username)));

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_users_basedata.' (presence, auto_delegation, realname, displayname, username, email, pw, status, hash_id, created, last_update, updater_id, bi, userlevel) VALUES (1, 0, :realname, :displayname, :username, :email, :password, :status, :hash_id, NOW(), NOW(), :updater_id, :bi, :userlevel)');
        // bind all VALUES
        $this->db->bind(':username', $this->crypt->encrypt($username));
        $this->db->bind(':realname', $this->crypt->encrypt($realname));
        $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
        $this->db->bind(':email', $this->crypt->encrypt($email));
        $this->db->bind(':password', $hash);
        $this->db->bind(':bi', $bi);
        $this->db->bind(':userlevel', $userlevel);
        $this->db->bind(':status', $status);
        // generate unique hash for this user
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($username.$appendix); // create hash id for this user
        $this->db->bind(':hash_id', $hash_id);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }


        if (!$err)
        {
          $insertid = intval($this->db->lastInsertId());
          $this->syslog->addSystemEvent(0, "Added new user ".$insertid, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = $insertid; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;


        } else {
          $this->syslog->addSystemEvent(1, "Error adding user ", 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // db error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function editUserData($user_id, $realname, $displayname, $username, $email, $about_me="", $position="", $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         realname = actual name of the user, status = status of inserted user (0 = inactive, 1=active)
        */
        // query('UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?');
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET realname = :realname , displayname= :displayname, username= :username, about_me= :about_me, position= :position, email = :email, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':username', $this->crypt->encrypt($username));
        $this->db->bind(':realname', $this->crypt->encrypt($realname));
        $this->db->bind(':about_me', $this->crypt->encrypt($about_me));
        $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
        $this->db->bind(':position', $this->crypt->encrypt($position));
        $this->db->bind(':email', $this->crypt->encrypt($email));
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Edited user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;


        } else {
          $this->syslog->addSystemEvent(1, "Error while editing user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function setUserAbsence ($user_id, $presence = 1, $absent_until = "", $auto_delegation = 0){
      /* sets a user to absent until a specified date
       $presence = 0 = user is absent 1 = user is present
       absent_until = date until the user is absent
       auto_delegation = 0 = auto delegation off, 1= votes for this user are
      */
      //sanitize
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $presence = intval ($presence);
      $auto_delegation = intval ($auto_delegation);

      if ($presence<0){
        $presence = 0;
      }
      if ($presence>1){
        $presence = 1;
      }

      $ret_value = $this->setUserProperty ($user_id, "presence", $presence); // set user to present / absent
      if ($ret_value ['success']){
        // if setting worked, continue
        $ret_value = $this->setUserProperty ($user_id, "auto_delegation", $auto_delegation); // set auto delegation
        $ret_value = $this->setUserProperty ($user_id, "absent_until", $absent_until); // set user until
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = $ret_value['error_code']; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      } // end else
    } // end function

    public function setUserStatus($user_id, $status, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of inserted user (0 = inactive, 1=active)
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User status of ".$user_id." changed to ".$status." by ".$updater_id, 0, "", 1);

          // set delegations for this user to suspended (delegated voting right and received voting right)
          $this->setDelegationStatus ($user_id, $status, 0, 0); // set status for received delegations
          $this->setDelegationStatus ($user_id, $status, 0, 1); // set status for target delegations

          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of user ".$user_id." to ".$status." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function revokeConsent ($user_id, $text_id){
      // revoke a previousely given consent to a cretain text (text_id)
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $this->converters->checkTextId($text_id);
      $stmt = $this->db->query('UPDATE '.$this->db->au_consent.' SET consent= 2, last_update= NOW(), updater_id= :updater_id, date_revoke = NOW() WHERE user_id= :userid AND text_id= : text_id');
      // bind all VALUES
      $this->db->bind(':userid', $user_id);
      $this->db->bind(':text_id', $text_id);

      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

          $err=true;
      }
      if (!$err)
      {
        $this->syslog->addSystemEvent(0, "User consent of ".$user_id." changed to ".$consent." by ".$updater_id, 0, "", 1);

        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;

      }

    } // end function

    public function setUserConsent ($user_id, $text_id, $consent, $status=1, $updater_id=0) {
        /* edits the consent of a user and returns number of rows if successful, accepts the above parameters, not all parameters are mandatory
         status = status of consent (0 = inactive, 1=active)
         consent = value that consent field is set to 0=not given, 1=given, 2=revoked
         text_id = id of the text this applies to
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $this->converters->checkTextId($text_id);

        $stmt = $this->db->query('UPDATE '.$this->db->au_consent.' SET status= :status, consent= :consent, last_update= NOW(), updater_id= :updater_id WHERE user_id= :userid AND text_id= : text_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':userid', $user_id);
        $this->db->bind(':text_id', $text_id);
        $this->db->bind(':consent', $consent);

        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User consent of ".$user_id." changed to ".$consent." by ".$updater_id, 0, "", 1);

          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function grantInfiniteVotesToUser ($user_id){
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      return $this->setUserInfiniteVote ($user_id, 1);
    }

    public function revokeInfiniteVotesFromUser ($user_id){
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      return $this->setUserInfiniteVote ($user_id, 0);
    }

    public function getUserInfiniteVotesStatus($user_id) {
      /* returns infinite vote status of a user for user id
      */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT infinite_votes FROM '.$this->db->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // db error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // db error code
        $returnvalue ['data'] = $users [0]['infinite_votes']; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;
      }
    }// end function


    public function getUserAbsence($user_id) {
      /* returns status of absence of a user for a user id
      */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT absence, absent_until, auto_delegation FROM '.$this->db->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // db error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // db error code
        $returnvalue ['data'] = $users[0]; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;
      }
    }// end function

    public function getUserLastLogin($user_id) {
      /* returns last login of a user for a integer user id
      */
      $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT last_login FROM '.$this->db->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // db error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // db error code
        $returnvalue ['data'] = $users[0]['last_login']; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;
      }
    }// end function



    public function setUserInfiniteVote($user_id, $infinite, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         sets the specified user to infinite vote capability
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        // sanitize
        $infinite = intval ($infinite);
        if ($infinite>1){
          $infinite = 1;
        }
        if ($infinite<0){
          $infinite = 0;
        }

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET infinite_votes = :infinite, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':infinite', $infinite);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User infinite status of ".$user_id." changed to ".$infinite." by ".$updater_id, 0, "", 1);

          // set delegations for this user to suspended (delegated voting right and received votign right)
          if ($infinite==1){
            // remove all delegations from this user since he has infinite votes....
            $this->removeUserDelegations ($user_id, 0, 0);
            $this->removeUserDelegations ($user_id, 0, 1);
          }

          $row_count = intval($this->db->rowCount());
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // db error code
          $returnvalue ['data'] = $row_count; // returned data
          $returnvalue ['count'] = $row_count; // returned count of datasets

          return $returnvalue;
        } else {
          $this->syslog->addSystemEvent(1, "Error changing infinite status of user ".$user_id." to ".$infinite." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // db error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function suspendUser ($user_id, $updater_id=0){
      // set user status to 2 = suspended

      // set delegations for this user to suspended (delegated voting right and received votign right)

      return setUserStatus ($user_id, 2, $updater_id);
    }

    public function activateUser ($user_id, $updater_id=0){
      // set user status to 1 = active
      // set delegations for this user to suspended (delegated voting right and received votign right)

      return setUserStatus ($user_id, 1, $updater_id);
    }

    public function deactivateUser ($user_id, $updater_id=0){
      // set user status to 0 = inactive

      // set delegations for this user to suspended (delegated voting right and received votign right)

      return setUserStatus ($user_id, 0, $updater_id);
    }

    public function archiveUser ($user_id, $archive_mode=0, $updater_id=0){
      /* set user status to 4 = archived
        user_id = id of the user that will be archived
        archive_mode = 0 = only the user will be set to archived 1= associated data will also be archived (ideas, comments, messages, media)
       set delegations for this user to suspended (delegated voting right and received votign right)
       */

      return setUserStatus ($user_id, 4, $updater_id);
    }



    public function setUserLevel($user_id, $userlevel=10, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         userlevel = level of the user (10 (guest)-50 (techadmin))
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $userlevel = intval (§userlevel);

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET userlevel= :userlevel, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':userlevel', $userlevel);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User status changed ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function setUserAbout($user_id, $about, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a user
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $about = $this->crypt->encrypt(trim ($about)); // sanitize and encrypt about text

        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET about_me= :about, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':about', $about);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User abouttext changed ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error changing abouttext of user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function setUserPosition($user_id, $userposition, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         about (text) -> description of a user
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $about = $this->crypt->encrypt(trim ($userposition)); // sanitize and encrypt position text

        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET position= :position, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':position', $userposition);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':userid', $user_id); // user that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User field position changed ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error changing position of user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function setUserRealname($user_id, $realname, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         realname = actual name of the user
        */
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET realname= :realname, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':realname', $this->crypt->encrypt($realname));
        $this->db->bind(':userid', $user_id); // user that is updated
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User real name changed ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error changing real name of user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function setUserDisplayname($user_id, $displayname, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         displayname = shown name of the user in the system
        */
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET displayname= :displayname, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':displayname', $this->crypt->encrypt($displayname));
        $this->db->bind(':userid', $user_id); // user that is updated
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User display name changed ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error changing display name of user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function setUserEmail($user_id, $email, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         email = email address of the user
        */
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET email= :email, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');
        // bind all VALUES
        $this->db->bind(':email', $this->crypt->encrypt($email));
        $this->db->bind(':userid', $user_id); // user that is updated
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User email changed ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error changing email of user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function setUserPW($user_id, $pw, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         pw = pw in clear text
        */
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET pw= :pw, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');

        // generate pw hash
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        // bind all VALUES
        $this->db->bind(':pw', $hash);
        $this->db->bind(':userid', $user_id); // user that is updated
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User pw changed ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error changing pw of user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function



    public function setUserRegStatus($user_id, $regstatus, $updater_id=0) {
        /* edits a user and returns number of rows if successful, accepts the above parameters (clear text), all parameters are mandatory
         regstatus (int) sets user registration status
        */
        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_users_basedata.' SET registration_status= :regstatus, last_update= NOW(), updater_id= :updater_id WHERE id= :userid');

        // bind all VALUES
        $this->db->bind(':regstatus', $regstatus);
        $this->db->bind(':userid', $user_id); // user that is updated
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "User reg status changed ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = intval($this->db->rowCount()); // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error changing reg status of user ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }
    }// end function

    public function deleteUser($user_id, $delete_mode=0, $updater_id=0) {
        /* deletes user and returns the number of rows (int) accepts user id or user hash id
        user id = id of the user, either hash id or db id
        delete_mode = 0 = delete user only 1= also delete all associated data with this user (ideas, comment, messages, media), votes are preserved
         */

        $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->db->au_users_basedata.' WHERE id = :id');
        $this->db->bind (':id', $user_id);
        $err=false;
        try {
          $action = $this->db->execute(); // do the query
          $rows_affected = intval ($this->db->rowCount());

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          // remove all delegations for this user
          $this->removeUserDelegations ($user_id, 0, 0); // active delegations (original user)
          $this->removeUserDelegations ($user_id, 0, 1); // passive delegations (target user)

          // delete associated data if option delete_mode is set
          if ($delete_mode==1){
            $stmt = $this->db->query('DELETE FROM '.$this->db->au_ideas.' WHERE user_id = :id');
            $this->db->bind (':id', $user_id);
            $err=false;
            try {
              $action = $this->db->execute(); // do the query
              $rows_affected = intval ($this->db->rowCount());

            } catch (Exception $e) {

              $returnvalue['success'] = false; // set return value
              $returnvalue['error_code'] = 1; // error code
              $returnvalue ['data'] = false; // returned data
              $returnvalue ['count'] = 0; // returned count of datasets

              return $returnvalue;
            }
            $stmt = $this->db->query('DELETE FROM '.$this->db->au_comments.' WHERE user_id = :id');
            $this->db->bind (':id', $user_id);
            $err=false;
            try {
              $action = $this->db->execute(); // do the query
              $rows_affected = intval ($this->db->rowCount());

            } catch (Exception $e) {

              $returnvalue['success'] = false; // set return value
              $returnvalue['error_code'] = 1; // error code
              $returnvalue ['data'] = false; // returned data
              $returnvalue ['count'] = 0; // returned count of datasets

              return $returnvalue;
            }
            $stmt = $this->db->query('DELETE FROM '.$this->db->au_messages.' WHERE creator_id = :id');
            $this->db->bind (':id', $user_id);
            $err=false;
            try {
              $action = $this->db->execute(); // do the query
              $rows_affected = intval ($this->db->rowCount());

            } catch (Exception $e) {

              $returnvalue['success'] = false; // set return value
              $returnvalue['error_code'] = 1; // error code
              $returnvalue ['data'] = false; // returned data
              $returnvalue ['count'] = 0; // returned count of datasets

              return $returnvalue;
            }

          } //end if


          $this->syslog->addSystemEvent(0, "User deleted with id ".$user_id." by ".$updater_id, 0, "", 1);

          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = $rows_affected; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error deleting user with id ".$user_id." by ".$updater_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;

        }

    }// end function

}
?>
