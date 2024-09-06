<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include == 1) {

} else {
  exit;
}



class Idea
{

  private $db;

  public function __construct($db, $crypt, $syslog)
  {
    // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
    $this->db = $db;
    $this->crypt = $crypt;
    //$this->syslog = new Systemlog ($db);
    $this->syslog = $syslog;
    $this->group = new Group($db, $crypt, $syslog); // init group class
    $this->converters = new Converters($db); // load converters
    /*
    $memcache = new Memcached();
    $memcache->addServer('localhost', 11211) or die ("Could not connect");
    */
    #dele
  }// end function

  public function getIdeaBaseData($idea_id)
  {
    /* returns idea base data for a specified db id */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $stmt = $this->db->query('SELECT ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_ideas . '.custom_field1, ' . $this->db->au_ideas . '.custom_field2, ' . $this->db->au_ideas . '.created, ' . $this->db->au_ideas . '.last_update, ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.topic_id, ' . $this->db->au_ideas . '.content,  ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_votes, ' . $this->db->au_ideas . '.sum_comments, ' . $this->db->au_ideas . '.is_winner, ' . $this->db->au_ideas . '.approved, ' . $this->db->au_ideas . '.approval_comment, ' . $this->db->au_ideas . '.status FROM ' . $this->db->au_ideas . ' INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id) WHERE ' . $this->db->au_ideas . '.id = :id');

    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getIdeaContent($idea_id)
  {
    /* returns content, sum votes, sum likes, number of votes, create, last_update, hash id and the user displayname of an idea for a integer idea id
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('SELECT ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.content, ' . $this->db->au_ideas . '.hash_id, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_votes, ' . $this->db->au_ideas . '.number_of_votes, ' . $this->db->au_ideas . '.last_update, ' . $this->db->au_ideas . '.is_winner, ' . $this->db->au_ideas . '.approved, ' . $this->db->au_ideas . '.approval_comment, ' . $this->db->au_ideas . '.created, ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_users_basedata . '.id as user_id FROM ' . $this->db->au_ideas . ' INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id) WHERE ' . $this->db->au_ideas . '.id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas[0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getIdeaNumberVotes($idea_id)
  {
    /* returns the calculated number of given votes for this idea
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('SELECT SUM(vote_weight) AS totalvotes FROM ' . $this->db->au_votes . ' WHERE idea_id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas[0]['totalvotes']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function


  public function getIdeaVotes($idea_id)
  {
    /* returns sum of votes of an idea for a integer idea id
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('SELECT sum_votes FROM ' . $this->db->au_ideas . ' WHERE id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas[0]['sum_votes']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getIdeaVoteStats($idea_id)
  {
    /* returns the calculated stats of votes (positive, neutral, negative) for this idea
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('SELECT vote_value, vote_weight FROM ' . $this->db->au_votes . ' WHERE idea_id = :id and status = 1');
    $this->db->bind(':id', $idea_id); // bind idea id
    $votes = $this->db->resultSet();

    if (count($votes) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {

      $votes_negative = 0;
      $votes_neutral = 0;
      $votes_positive = 0;

      $data = [];

      foreach ($votes as $vote) {
        $vote_value = $vote['vote_value'];
        $vote_weight = $vote['vote_weight'];

        if ($vote_value > 0) {
          $votes_positive = $votes_positive + $vote_weight;
        }
        if ($vote_value == 0) {
          $votes_neutral = $votes_neutral + $vote_weight;
        }
        if ($vote_value < 0) {
          $votes_negative = $votes_negative + $vote_weight;
        }
        $total_votes = $total_votes + $vote_weight;
      }

      $data['total_votes'] = $total_votes;
      $data['votes_negative'] = $votes_negative;
      $data['votes_neutral'] = $votes_neutral;
      $data['votes_positive'] = $votes_positive;


      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $data; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getIdeaTopic($idea_id)
  {
    /* returns the topic for a specificc idea integer idea id
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('SELECT topic_id FROM ' . $this->db->au_rel_topics_ideas . ' WHERE idea_id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas[0]['topic_id']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getIdeaRoom($idea_id)
  {
    /* returns the topic for a specificc idea integer idea id
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('SELECT room_id FROM ' . $this->db->au_ideas . ' WHERE id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas[0]['room_id']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  protected function buildCacheHash($key)
  {
    return md5($key);
  }

  public function getIdeaLikes($idea_id)
  {
    /* returns sum of likes of an idea for a integer idea id
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('SELECT sum_likes FROM ' . $this->db->au_ideas . ' WHERE id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas[0]['sum_likes']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getIdeaStatus($idea_id)
  {
    /* returns the status of an idea for a integer idea id
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('SELECT status FROM ' . $this->db->au_ideas . ' WHERE id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas[0]['status']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function


  public function getPersonalVoteStatus($user_id, $idea_id, $topic_id)
  {
    /* returns content, sum votes, sum likes, create, last_update, hash id and the user displayname of an idea for a integer idea id
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $topic_id = $this->converters->checkTopicId($topic_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

    // check if this user still has votes available
    $available_votes = $this->checkAvailableVotesUser($user_id, $idea_id);

    // check for delegations
    $vote_factor = $this->getVoteBiasDelegations($user_id, $topic_id, $idea_id);

    $has_delegated = $this->userHasDelegated($user_id, $topic_id)['data'];
    return $has_delegated . "," . $vote_factor . "," . $available_votes; // returns status of the voting for a specific user idea and room

  }// end function


  public function getIdeaHashId($idea_id)
  {
    /* returns hash_id of an idea for a integer idea id
     */
    $stmt = $this->db->query('SELECT hash_id FROM ' . $this->db->au_ideas . ' WHERE id = :id');
    $this->db->bind(':id', $idea_id); // bind idea id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas[0]['hash_id']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function


  public function reportIdea($idea_id, $user_id, $updater_id, $reason = "")
  {
    /* sets the status of an idea to 3 = reported
    accepts db id and hash id of idea
    user_id is the id of the user that reported the idea
    updater_id is the id of the user that did the update
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    // check if idea is existent
    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_ideas . ' WHERE id = :idea_id');
    $this->db->bind(':idea_id', $idea_id); // bind user id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } // else continue processing
    // check if this user has already reported this idea
    $stmt = $this->db->query('SELECT object_id FROM ' . $this->db->au_reported . ' WHERE user_id = :user_id AND type = 0 AND object_id = :idea_id');
    $this->db->bind(':user_id', $user_id); // bind user id
    $this->db->bind(':idea_id', $idea_id); // bind user id
    $ideas = $this->db->resultSet();
    if (count($ideas) < 1) {
      //add this reporting to db
      $stmt = $this->db->query('INSERT INTO ' . $this->db->au_reported . ' (reason, object_id, type, user_id, status, created, last_update) VALUES (:reason, :idea_id, 0, :user_id, 0, NOW(), NOW())');
      // bind all VALUES

      $this->db->bind(':idea_id', $idea_id);
      $this->db->bind(':user_id', $user_id);
      $this->db->bind(':reason', $reason);

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }
      $insertid = intval($this->db->lastInsertId());
      if (!$err) {
        $this->syslog->addSystemEvent(0, "Added new reporting (#" . $insertid . ") " . $content, 0, "", 1);
        // set idea status to reported
        $this->setIdeaStatus($idea_id, 3, $updater_id = 0);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {
        //$this->syslog->addSystemEvent(1, "Error reporting idea ".$content, 0, "", 1);
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

  public function suspendIdea($idea_id, $updater_id)
  {
    /* sets the status of an idea to 3 = suspended
    accepts db id and hash id of idea
    user_id is the id of the user that reported the idea
    updater_id is the id of the user that did the update
    */
    return $this->setIdeaStatus($idea_id, 3, $updater_id = 0);

  } // end function

  public function archiveIdea($idea_id, $updater_id)
  {
    /* sets the status of an idea to 4 = archived
    accepts db id and hash id of idea
    user_id is the id of the user that reported the idea
    updater_id is the id of the user that did the update
    */
    return $this->setIdeaStatus($idea_id, 4, $updater_id = 0);

  }

  public function activateIdea($idea_id, $updater_id)
  {
    /* sets the status of an idea to 1 = active
    accepts db id and hash id of idea
    user_id is the id of the user that reported the idea
    updater_id is the id of the user that did the update
    */
    return $this->setIdeaStatus($idea_id, 1, $updater_id = 0);

  }

  public function deactivateIdea($idea_id, $updater_id)
  {
    /* sets the status of an idea to 0 = inactive
    accepts db id and hash id of idea
    user_id is the id of the user that reported the idea
    updater_id is the id of the user that did the update
    */
    return $this->setIdeaStatus($idea_id, 0, $updater_id = 0);
  }

  public function setIdeatoReview($idea_id, $updater_id)
  {
    /* sets the status of an idea to 5 = in review
    accepts db id and hash id of idea
    user_id is the id of the user that reported the idea
    updater_id is the id of the user that did the update
    */
    return $this->setIdeaStatus($idea_id, 5, $updater_id = 0);

  }

  public function addIdeaToTopic($idea_id, $topic_id, $updater_id)
  {
    // adds an idea (idea_id) to a specified topic (topic_id)

    //
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

    $idea_exist = $this->converters->checkIdeaExist($idea_id);
    $topic_exist = $this->converters->checkTopicExist($topic_id);
    $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $existing_topic_id = $this->getIdeaTopic($idea_id)['data'];

    if ($existing_topic_id == true and $existing_topic_id > 0) {
      // idea already has a topic, initiate moving of idea to destination topiv
      $res = $this->moveIdeaBetweenTopics($idea_id, $existing_topic_id, $topic_id, $updater_id)['success'];

      if ($res == true) {
        $this->syslog->addSystemEvent(0, "Succesfully moved idea " . $idea_id . " to topic " . $topic_id, 0, "", 1);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {
        $this->syslog->addSystemEvent(0, "Error while moving idea " . $idea_id . " to topic " . $topic_id, 0, "", 1);

        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // db error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      } // end else
    } // end if

    if ($idea_exist == 1 && $topic_exist == 1) {
      // everything ok, user and room exists
      // add relation to database

      $stmt = $this->db->query('INSERT INTO ' . $this->db->au_rel_topics_ideas . ' (idea_id, topic_id, created, last_update, updater_id) VALUES (:idea_id, :topic_id, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE last_update = NOW(), updater_id = :updater_id');

      // bind all VALUES
      $this->db->bind(':idea_id', $idea_id);
      $this->db->bind(':topic_id', $topic_id);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Added idea " . $idea_id . " to topic " . $topic_id, 0, "", 1);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {
        $this->syslog->addSystemEvent(0, "Error while adding idea " . $idea_id . " to topic " . $topic_id, 0, "", 1);

        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // db error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code - topic or idea doesn't exist
      $returnvalue['data'] = $idea_exist . "," . $topic_exist; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }

  } // end function

  public function addIdeaToCategory($idea_id, $category_id, $updater_id = 0)
  {
    // adds an idea (idea_id) to a specified topic (topic_id)

    //
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $category_id = $this->converters->checkCategoryId($category_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $idea_exist = $this->converters->checkIdeaExist($idea_id);
    $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)


    if ($idea_exist == 1) {
      // everything ok, idea exists
      // add relation to database

      $stmt = $this->db->query('INSERT INTO ' . $this->db->au_rel_categories_ideas . ' (idea_id, category_id, created, last_update, updater_id) VALUES (:idea_id, :category_id, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE last_update = NOW(), updater_id = :updater_id');

      // bind all VALUES
      $this->db->bind(':idea_id', $idea_id);
      $this->db->bind(':category_id', $category_id);
      $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }

      if (!$err) {
        $this->syslog->addSystemEvent(0, "Added idea " . $idea_id . " to category " . $category_id, 0, "", 1);
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = 1; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;

      } else {
        $this->syslog->addSystemEvent(0, "Error while adding idea " . $idea_id . " to category " . $category_id, 0, "", 1);

        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;
      }

    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = $idea_exist; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }

  } // end function



  public function removeIdeaFromTopic($topic_id, $idea_id)
  {
    /* removes an idea from a topic
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_topics_ideas . ' WHERE idea_id = :idea_id AND topic_id = :topic_id');
    $this->db->bind(':topic_id', $topic_id); // bind topic id
    $this->db->bind(':idea_id', $idea_id); // bind idea id

    $err = false;
    try {
      $topics = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while deleting idea from topic: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = 1; // returned data
    $returnvalue['count'] = 1; // returned count of datasets

    return $returnvalue;


  }// end function

  public function removeIdeaFromCategory($category_id, $idea_id)
  {
    /* removes an idea from a topic
     */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $category_id = $this->converters->checkCategoryId($category_id); // checks  id and converts  id to db  id if necessary (when  hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_categories_ideas . ' WHERE idea_id = :idea_id AND category_id = :category_id');
    $this->db->bind(':category_id', $category_id); // bind topic id
    $this->db->bind(':idea_id', $idea_id); // bind idea id

    $err = false;
    try {
      $topics = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while deleting idea from category: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = 1; // returned data
    $returnvalue['count'] = 1; // returned count of datasets

    return $returnvalue;

  }// end function


  public function removeAllIdeasFromTopic($topic_id)
  {
    /* removes all associations of ideas from a topic
     */
    $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_topics_ideas . ' WHERE topic_id = :topic_id');
    $this->db->bind(':topic_id', $topic_id); // bind topic id

    $err = false;
    try {
      $topics = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while deleting all ideas from topic: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = 1; // returned data
    $returnvalue['count'] = 1; // returned count of datasets

    return $returnvalue;

  }// end function

  public function removeAllIdeasFromCategory($category_id)
  {
    /* removes all associations of ideas from a topic
     */
    $topic_id = $this->converters->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_rel_categories_ideas . ' WHERE category_id = :category_id');
    $this->db->bind(':topic_id', $category_id); // bind topic id

    $err = false;
    try {
      $topics = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while deleting all ideas from category: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = 1; // returned data
    $returnvalue['count'] = 1; // returned count of datasets

    return $returnvalue;

  }// end function

  public function getIdeasByTopic($topic_id, $offset = 0, $limit = 0, $orderby = 3, $asc = 0, $status = -1)
  {
    /* returns idealist (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (3)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
    $room_id is the id of the room
    */
    //sanitize
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);

    $topic_id = $this->converters->checkTopicId($topic_id); // auto convert

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

    // additional conditions for the WHERE clause
    $extra_where = "";

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_ideas . ".status = " . $status;
    }

    switch (intval($orderby)) {
      case 0:
        $orderby_field = $this->db->au_ideas . ".status";
        break;
      case 1:
        $orderby_field = $this->db->au_ideas . ".order_importance";
        break;
      case 2:
        $orderby_field = $this->db->au_ideas . ".created";
        break;
      case 3:
        $orderby_field = $this->db->au_ideas . ".last_update";
        break;
      case 4:
        $orderby_field = $this->db->au_ideas . ".id";
        break;
      case 5:
        $orderby_field = $this->db->au_ideas . ".content";
        break;

      default:
        $orderby_field = $this->db->au_ideas . ".last_update";
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
    $select_part = 'SELECT ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_ideas . '.created, ' . $this->db->au_ideas . '.last_update, ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.topic_id, ' . $this->db->au_ideas . '.content,  ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_votes, ' . $this->db->au_ideas . '.sum_comments, ' . $this->db->au_ideas . '.is_winner, ' . $this->db->au_ideas . '.approved, ' . $this->db->au_ideas . '.approval_comment FROM ' . $this->db->au_ideas;
    $join = 'INNER JOIN ' . $this->db->au_rel_topics_ideas . ' ON (' . $this->db->au_rel_topics_ideas . '.idea_id=' . $this->db->au_ideas . '.id) INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id)';
    $where = ' WHERE ' . $this->db->au_ideas . '.id > 0 AND ' . $this->db->au_rel_topics_ideas . '.topic_id= :topic_id ' . $extra_where;
    $stmt = $this->db->query($select_part . ' ' . $join . ' ' . $where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    if ($limit_active) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    //$this->db->bind(':status', $status); // bind status
    $this->db->bind(':topic_id', $topic_id); // bind group id

    $err = false;
    try {
      $ideas = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while getting ideas: ', $e->getMessage(), "\n"; // display error
      $err = true;
      return 0;
    }

    $total_datasets = count($ideas);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // get count
      if ($limit_active) {
        // only newly calculate datasets if limits are active
        $total_datasets = $this->converters->getTotalDatasetsFree(str_replace(":topic_id", $topic_id, $select_part . ' ' . $join . ' ' . $where));
      }
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;

    }
  }// end function


  public function getCategoryBaseData($category_id, $status = -1, $type = -1, $room_id = -1, $idea_id = -1, $limit = -1, $orderby = 3, $asc = 0, $offset = 0)
  {
    $categories = $this->getCategories($status, $type, $room_id, $idea_id, $limit, $orderby, $asc, $offset, ' AND ' . $this->db->au_categories . '.id= ' . $category_id);

    if ($categories['success'] == true) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $categories['data'][0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      return $categories;
    }
  }// end function

  public function getIdeaCategory($idea_id)
  {
    /* returns idea base data for a specified db id */
    $categories = $this->getCategories(-1, -1, -1, $idea_id, -1, 3, 0, 0, "");

    if ($categories['success'] == true) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $categories['data'][0]; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      return $categories;
    }
  }// end function

  public function getCategories($status = -1, $type = -1, $room_id = -1, $idea_id = -1, $limit = -1, $orderby = 3, $asc = 0, $offset = 0, $extra_where = "")
  {
    /* returns category list (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (3)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
    $room_id is the id of the room
    */
    // sanitize
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);
    $idea_id = $this->converters->checkIdeaId($idea_id); // auto convert
    $room_id = $this->converters->checkRoomId($room_id); // auto convert

    // init vars
    $orderby_field = "";
    $asc_field = "";
    $join = "";

    $limit_string = " LIMIT :offset , :limit ";
    $limit_active = true;

    // check if offset an limit are both set to 0, then show whole list (exclude limit clause)
    if ($offset == 0 && $limit == -1) {
      $limit_string = "";
      $limit_active = false;
    }

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_categories . ".status = " . $status;
    }

    if ($type > -1) {
      // specific type selected / -1 = get all status values / 0 = content type / 1 = media type
      $extra_where .= " AND " . $this->db->au_categories . ".type = " . $type;
    }

    if ($idea_id > -1) {
      // specific status selected / -1 = get all status values / only activate where clause and binding if room_id > -1
      $join = 'LEFT JOIN ' . $this->db->au_rel_categories_ideas . ' ON (' . $this->db->au_rel_categories_ideas . '.category_id = ' . $this->db->au_categories . '.id)';
      $extra_where .= ' AND ' . $this->db->au_rel_categories_ideas . '.idea_id= ' . $idea_id;
    }

    if ($room_id > -1) {
      // specific status selected / -1 = get all status values / only activate where clause and binding if room_id > -1
      $join = 'LEFT JOIN ' . $this->db->au_rel_categories_rooms . ' ON (' . $this->db->au_rel_categories_rooms . '.category_id = ' . $this->db->au_categories . '.id)';
      $extra_where .= " AND " . $this->db->au_rel_categories_rooms . ".room_id = " . $room_id;
    }

    switch (intval($orderby)) {
      case 0:
        $orderby_field = $this->db->au_categories . ".status";
        break;
      case 1:
        $orderby_field = $this->db->au_categories . ".name";
        break;
      case 2:
        $orderby_field = $this->db->au_categories . ".created";
        break;
      case 3:
        $orderby_field = $this->db->au_categories . ".last_update";
        break;
      case 4:
        $orderby_field = $this->db->au_categories . ".id";
        break;

      default:
        $orderby_field = $this->db->au_categories . ".last_update";
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

    $select_part = 'SELECT ' . $this->db->au_categories . '.name, ' . $this->db->au_categories . '.description_public, ' . $this->db->au_categories . '.description_internal, ' . $this->db->au_categories . '.created, ' . $this->db->au_categories . '.last_update, ' . $this->db->au_categories . '.id FROM ' . $this->db->au_categories;
    $where = $this->db->au_categories . '.id > 0 ' . $extra_where;
    $stmt = $this->db->query($select_part . ' ' . $join . ' WHERE ' . $where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);


    $err = false;
    try {
      $categories = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while getting ideas: ', $e->getMessage(), "\n"; // display error
      $err = true;
      return 0;
    }

    $total_datasets = count($categories);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $categories; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;

    }
  }// end function getCategories

  public function getIdeasByCategory($offset, $limit, $orderby = 3, $asc = 0, $status = -1, $category_id)
  {
    /* returns category list (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (3)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
    $room_id is the id of the room
    */
    // sanitize
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);

    $category_id = checkCategoryId($category_id); // auto convert

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

    // additional conditions for the WHERE clause
    $extra_where = "";

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_ideas . ".status = " . $status;
    }

    switch (intval($orderby)) {
      case 0:
        $orderby_field = $this->db->au_ideas . ".status";
        break;
      case 1:
        $orderby_field = $this->db->au_ideas . ".order_importance";
        break;
      case 2:
        $orderby_field = $this->db->au_ideas . ".created";
        break;
      case 3:
        $orderby_field = $this->db->au_ideas . ".last_update";
        break;
      case 4:
        $orderby_field = $this->db->au_ideas . ".id";
        break;

      default:
        $orderby_field = $this->db->au_ideas . ".last_update";
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
    $select_part = 'SELECT ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_ideas . '.created, ' . $this->db->au_ideas . '.last_update, ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.content, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_votes FROM ' . $this->db->au_ideas;
    $join = 'INNER JOIN ' . $this->db->au_rel_categories_ideas . ' ON (' . $this->db->au_rel_categories_ideas . '.idea_id=' . $this->db->au_ideas . '.id) INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id)';
    $where = ' WHERE ' . $this->db->au_ideas . '.id > 0 AND ' . $this->db->au_rel_categories_ideas . '.category_id= :category_id ' . $extra_where;
    $stmt = $this->db->query($select_part . ' ' . $join . ' ' . $where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    if ($limit_active) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    //$this->db->bind(':status', $status); // bind status
    $this->db->bind(':category_id', $category_id); // bind category_id

    $err = false;
    try {
      $ideas = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while getting ideas: ', $e->getMessage(), "\n"; // display error
      $err = true;
      return 0;
    }

    $total_datasets = count($ideas);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // get count
      if ($limit_active) {
        // only newly calculate datasets if limits are active
        $total_datasets = $this->converters->getTotalDatasetsFree(str_replace(":category_id", $category_id, $select_part . ' ' . $join . ' ' . $where));
      }
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  protected function checkIfVoteWasMade($user_id, $idea_id)
  {
    // checks if there already is a vote by this user (user_id) for this idea (idea_id)
    $stmt = $this->db->query('SELECT vote_value FROM ' . $this->db->au_votes . ' WHERE user_id = :user_id AND idea_id = :idea_id AND status = 1 AND (vote_value > 0 OR vote_value < 0)');
    $this->db->bind(':user_id', $user_id); // bind user id
    $this->db->bind(':idea_id', $idea_id); // bind idea id
    $votes = $this->db->resultSet();
    $count_votes = count($votes);
    if ($count_votes > 0) {
      return 1; // vote already given
    } else {
      return 0; // no votes yet
    }
  }

  protected function getVoteBiasDelegations($user_id, $topic_id, $idea_id)
  {
    /* returns number of delegated votes to this user (user_id), accepts database id (int)
     */
    $stmt = $this->db->query('SELECT status, user_id_original FROM ' . $this->db->au_delegation . ' WHERE user_id_target = :user_id AND topic_id = :topic_id AND status = 1');
    $this->db->bind(':user_id', $user_id); // bind user id
    $this->db->bind(':topic_id', $topic_id); // bind topic id
    $delegations = $this->db->resultSet();
    $count_delegations = count($delegations);
    //echo ("getVoteBiasDelegations for user ".$user_id." running with topic ".$topic_id.":".$count_delegations);
    // save delegated votes of original user into votes table of db
    $vote_bias = 1; // init vote bias
    foreach ($delegations as $result) {
      // check if original owner has already voted - if yes then reduce the count for vote bias by 1
      //echo ("<br>FOUND DELEGATION from ...".$result['user_id_original']);
      $user_original = $result['user_id_original'];
      if ($this->checkIfVoteWasMade($user_original, $idea_id) == 0) {
        // original owner of the delegated vote has not voted yet (although he delegated)
        //echo ("<br>Original owner (".$user_original.") has not voted yet...".$vote_bias);
        $vote_bias++; // increase the bias for the vote by 1

      }
      $original_user = $result['user_id_original'];

    } // end foreach

    return $vote_bias;

  } // end function


  public function getIdeas($room_id = 0, $offset = 0, $limit = 0, $orderby = 3, $asc = 0, $status = -1, $wild_idea = false, $extra_where = "")
  {
    /* returns idealist (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (3)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
    extra_where = extra parameters for where clause, synthax " AND XY=4"
    */

    // sanitize
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);

    $room_id = $this->converters->checkRoomId($room_id);

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
      $extra_where .= " AND " . $this->db->au_ideas . ".status = " . $status;
    }

    if ($room_id > 0) {
      // if a room id is set then add to where clause
      $room_id = $this->converters->checkRoomId($room_id); // auto convert id
      $extra_where .= " AND room_id = " . $room_id; // get specific topics to a room
    }

    if ($wild_idea) {
      $extra_where .= " AND au_ideas.id NOT IN (SELECT idea_id FROM au_rel_topics_ideas)";
    }

    switch (intval($orderby)) {
      case 0:
        $orderby_field = "status";
        break;
      case 1:
        $orderby_field = "order_importance";
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
        $orderby_field = "sum_likes";
        break;
      case 6:
        $orderby_field = "sum_votes";
        break;
      case 7:
        $orderby_field = "content";
        break;
      case 8:
        $orderby_field = "room_id";
        break;
      case 9:
        $orderby_field = "title";
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

    #$stmt = $this->db->query('SELECT '.$this->db->au_ideas.'.title, '.$this->db->au_ideas.'.approved, '.$this->db->au_ideas.'.approval_comment, '.$this->db->au_ideas.'.content, '.$this->db->au_ideas.'.hash_id, '.$this->db->au_ideas.'.id, '.$this->db->au_ideas.'.room_id, '.$this->db->au_ideas.'.sum_likes, '.$this->db->au_ideas.'.sum_votes, '.$this->db->au_ideas.'.number_of_votes, '.$this->db->au_ideas.'.last_update, '.$this->db->au_ideas.'.created, '.$this->db->au_users_basedata.'.displayname FROM '.$this->db->au_ideas.' INNER JOIN '.$this->db->au_users_basedata.' ON ('.$this->db->au_ideas.'.user_id='.$this->db->au_users_basedata.'.id) WHERE '.$this->db->au_ideas.'.id > 0 '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
    $stmt = $this->db->query('SELECT ' . $this->db->au_topics . '.phase_id AS phase_id,  ' . $this->db->au_topics . '.description_public AS topic_description,  ' . $this->db->au_topics . '.name AS topic_name, ' . $this->db->au_topics . '.id AS topic_id,  ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.approved, ' . $this->db->au_ideas . '.approval_comment, ' . $this->db->au_ideas . '.content, ' . $this->db->au_ideas . '.hash_id, ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_votes, ' . $this->db->au_ideas . '.number_of_votes, ' . $this->db->au_ideas . '.last_update, ' . $this->db->au_ideas . '.status, ' . $this->db->au_ideas . '.created, ' . $this->db->au_users_basedata . '.displayname FROM ' . $this->db->au_ideas . ' INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id) LEFT JOIN ' . $this->db->au_rel_topics_ideas . ' ON (' . $this->db->au_ideas . '.id = ' . $this->db->au_rel_topics_ideas . '.idea_id) LEFT JOIN ' . $this->db->au_topics . ' ON (' . $this->db->au_topics . '.id = ' . $this->db->au_rel_topics_ideas . '.topic_id)  WHERE ' . $this->db->au_ideas . '.id > 0 ' . $extra_where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    #$stmt = $this->db->query('SELECT '.$this->db->au_ideas.'.*, '.this->db->au_users_basedata.'.displayname FROM '.$this->db->au_ideas.' INNER JOIN '.$this->db->au_users_basedata.' ON ('.$this->db->au_ideas.'.user_id='.$this->db->au_users_basedata.'.id) WHERE '.$this->db->au_ideas.'.id > 0 '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);

    if ($limit_active) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }

    $err = false;
    try {
      $ideas = $this->db->resultSet();

    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }

    $total_datasets = count($ideas);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    } else {
      // determine total number of datasets without pagination limits
      // get count
      if ($limit_active) {
        // only newly calculate datasets if limits are active
        $total_datasets = $this->converters->getTotalDatasets($this->db->au_ideas, $status . $extra_where);
      }
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = $ideas; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets with pagination or $total_datasets returns all datasets (without pagination)
      return $returnvalue;

    }
  }// end function

  public function getWildIdeasByUser($user_id, $offset = 0, $limit = 0, $orderby = 2, $asc = 0, $status = -1, $extra_where = "")
  {
    /* returns idealist (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to creation_date (2)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1) -1 = get all datasets with status values 0-4
    $room_id is the id of the room
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

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_ideas . ".status = " . $status;
    }

    switch (intval($orderby)) {
      case 0:
        $orderby_field = "status";
        break;
      case 1:
        $orderby_field = "order_importance";
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
    $select_part = 'SELECT ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_ideas . '.created, ' . $this->db->au_ideas . '.last_update, ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.content, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_comments, ' . $this->db->au_ideas . '.sum_votes FROM ' . $this->db->au_ideas;
    $join = 'INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id) LEFT OUTER JOIN ' . $this->db->au_rel_topics_ideas . ' ON ' . $this->db->au_ideas . '.id = ' . $this->db->au_rel_topics_ideas . '.idea_id';
    $where = $this->db->au_ideas . '.id > 0 AND ' . $this->db->au_users_basedata . '.id= :user_id AND ' . $this->db->au_rel_topics_ideas . '.idea_id IS NULL' . $extra_where;
    $stmt = $this->db->query($select_part . ' ' . $join . ' WHERE ' . $where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);

    if ($limit_active) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    //$this->db->bind(':status', $status); // bind status
    $this->db->bind(':user_id', $user_id); // bind user id

    $err = false;
    try {
      $ideas = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while getting ideas: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $total_datasets = count($ideas);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // get count
      if ($limit_active) {
        // only newly calculate datasets if limits are active
        $total_datasets = $this->converters->getTotalDatasetsFree(str_replace(":room_id", $room_id, $select_part . ' ' . $join . ' ' . $where));
      }
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;

    }
  }// end function


  public function getIdeasByRoom($room_id, $offset = 0, $limit = 0, $orderby = 2, $asc = 0, $status = -1, $extra_where = "")
  {
    /* returns idealist (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to creation_date (2)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1) -1 = get all datasets with status values 0-4
    $room_id is the id of the room
    */
    // sanitize
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);

    $room_id = $this->converters->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

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

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_ideas . ".status = " . $status;
    }

    switch (intval($orderby)) {
      case 0:
        $orderby_field = "status";
        break;
      case 1:
        $orderby_field = "order_importance";
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
    $select_part = 'SELECT ' . $this->db->au_users_basedata . '.id as user_id, ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_ideas . '.created, ' . $this->db->au_ideas . '.last_update, ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.content, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_comments, ' . $this->db->au_ideas . '.sum_votes FROM ' . $this->db->au_ideas;
    $join = 'INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id) LEFT OUTER JOIN ' . $this->db->au_rel_topics_ideas . ' ON ' . $this->db->au_ideas . '.id = ' . $this->db->au_rel_topics_ideas . '.idea_id';
    $where = $this->db->au_ideas . '.id > 0 AND ' . $this->db->au_ideas . '.room_id= :room_id AND ' . $this->db->au_rel_topics_ideas . '.idea_id IS NULL' . $extra_where;
    $stmt = $this->db->query($select_part . ' ' . $join . ' WHERE ' . $where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);

    if ($limit_active) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    //$this->db->bind(':status', $status); // bind status
    $this->db->bind(':room_id', $room_id); // bind room id

    $err = false;
    try {
      $ideas = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while getting ideas: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $total_datasets = count($ideas);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // get count
      if ($limit_active) {
        // only newly calculate datasets if limits are active
        $total_datasets = $this->converters->getTotalDatasetsFree(str_replace(":room_id", $room_id, $select_part . ' ' . $join . ' ' . $where));
      }
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function getIdeasByGroup($offset, $limit, $orderby = 3, $asc = 0, $status = -1, $group_id, $room_id = -1)
  {
    /* returns idealist (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (3)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
    $$group_id is the id of the group
    */
    // sanitize
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);

    $group_id = $this->converters->checkGroupId($group_id); // auto convert

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

    // additional conditions for the WHERE clause
    $extra_where = "";

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_ideas . ".status = " . $status;
    }

    if ($room_id > -1) {
      // specific status selected / -1 = get all status values
      $room_id = $this->converters->checkRoomId($room_id); // auto convert id
      $extra_where .= " AND " . $this->db->au_ideas . ".room_id = " . $room_id;
    }


    switch (intval($orderby)) {
      case 0:
        $orderby_field = "status";
        break;
      case 1:
        $orderby_field = "order_importance";
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
    $select_part = 'SELECT ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_ideas . '.created, ' . $this->db->au_ideas . '.last_update, ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.content, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_votes FROM ' . $this->db->au_ideas;
    $join = 'INNER JOIN ' . $this->db->au_rel_groups_users . ' ON (' . $this->db->au_rel_groups_users . '.user_id=' . $this->db->au_ideas . '.user_id) INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id)';
    $where = ' WHERE ' . $this->db->au_ideas . '.id > 0 AND ' . $this->db->au_rel_groups_users . '.group_id= :group_id ' . $extra_where;
    $stmt = $this->db->query($select_part . ' ' . $join . ' ' . $where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    if ($limit_active) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    //$this->db->bind(':status', $status); // bind status
    $this->db->bind(':group_id', $group_id); // bind group id

    $err = false;
    try {
      $ideas = $this->db->resultSet();

    } catch (Exception $e) {
      //echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    $total_datasets = count($ideas);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // get count
      if ($limit_active) {
        // only newly calculate datasets if limits are active
        $total_datasets = $this->converters->getTotalDatasetsFree(str_replace(":group_id", $group_id, $select_part . ' ' . $join . ' ' . $where));
      }
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function getIdeasByUser($user_id, $offset = 0, $limit = 0, $orderby = 3, $asc = 0, $status = -1, $room_id = -1)
  {
    /* returns idealist (associative array) with start and limit provided
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (3)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
    $$user_id is the id of the user
    */
    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);

    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

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
    // additional conditions for the WHERE clause
    $extra_where = "";

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_ideas . ".status = " . $status;
    }

    if ($room_id > -1) {
      $room_id = $this->converters->checkRoomId($room_id); // auto convert id
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_ideas . ".room_id = " . $room_id;
    }

    switch (intval($orderby)) {
      case 0:
        $orderby_field = "status";
        break;
      case 1:
        $orderby_field = "order_importance";
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
        $orderby_field = "room_id";
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
    $select_part = 'SELECT ' . $this->db->au_users_basedata . '.displayname, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_ideas . '.created, ' . $this->db->au_ideas . '.last_update,  ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.content, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_votes FROM ' . $this->db->au_ideas;
    $join = 'INNER JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id=' . $this->db->au_users_basedata . '.id)';
    $where = ' WHERE ' . $this->db->au_ideas . '.id > 0 AND ' . $this->db->au_ideas . '.user_id= :user_id ' . $extra_where;
    $stmt = $this->db->query($select_part . ' ' . $join . ' ' . $where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    if ($limit_active) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    //$this->db->bind(':status', $status); // bind status
    $this->db->bind(':user_id', $user_id); // bind room id

    $err = false;
    try {
      $ideas = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while getting ideas: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $total_datasets = count($ideas);

    if ($total_datasets < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // get count
      if ($limit_active) {
        // only newly calculate datasets if limits are active
        $total_datasets = $this->converters->getTotalDatasetsFree(str_replace(":user_id", $user_id, $select_part . ' ' . $join . ' ' . $where));
      }
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $ideas; // returned data
      $returnvalue['count'] = count($ideas); // returned count of datasets

      return $returnvalue;

    }
  }// end function

  //public function addIdea ($content, $user_id, $status, $order_importance=10, $updater_id=0, $votes_available_per_user=1, $info="", $room_id=0) {


  public function editIdea($idea_id, $content, $status = 1, $title = "", $votes_available_per_user = 1, $info = "", $order_importance = 10, $room_id = 0, $updater_id = 0, $approved = 0, $approval_comment = "")
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory

    */
    // sanitize
    $content = trim($content);
    $title = trim($title);
    $info = trim($info);
    $approval_comment = trim($approval_comment);

    $status = intval($status);
    $order_importance = intval($order_importance);
    $votes_available_per_user = intval($votes_available_per_user);

    $updater_id = $this->converters->checkUserId($updater_id); // autoconvert
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $room_id = $this->converters->checkRoomId($room_id); // checks id and converts id to db id if necessary (when hash id was passed)


    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET title = :title, content = :content, info = :info, room_id = :room_id, votes_available_per_user= :votes_available_per_user, status= :status, approved= :approved, approval_comment= :approval_comment, order_importance= :order_importance, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':content', $this->crypt->encrypt($content)); // the actual idea
    $this->db->bind(':title', $title); // title only shown in backend
    $this->db->bind(':info', $info); // info only shown in backend
    $this->db->bind(':votes_available_per_user', $votes_available_per_user); // only shown in backend admin
    $this->db->bind(':status', $status); // status of the idea (0=inactive, 1=active, 2=suspended, 4=archived)
    $this->db->bind(':room_id', $room_id); // room id
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)
    $this->db->bind(':order_importance', $order_importance); // order for display in frontend
    $this->db->bind(':approved', $approved); // order for display in frontend
    $this->db->bind(':approval_comment', $approval_comment); // order for display in frontend
    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Edited idea " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = intval($this->db->rowCount()); // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;


    } else {
      //$this->syslog->addSystemEvent(1, "Error while editing idea ".$idea_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function addIdea ($content, $title, $user_id, $status = 1, $room_id = 0, $order_importance = 10, $updater_id = 0, $votes_available_per_user = 1, $info = "", $customfield1 = "", $customfield2 = "")
  {
    /* adds a new idea and returns insert id (idea id) if successful, accepts the above parameters
     content = actual content of the idea,
     status = status of inserted indea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
     info is internal info or can be used for open aula to enter the name of the person that had the idea
    */

    //sanitize in vars
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $status = intval($status);
    $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)
    $order_importance = intval($order_importance);
    $content = trim($content);
    $title = trim($title);
    $info = trim($info);

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_ideas . ' (is_winner, approved, info, votes_available_per_user, sum_votes, sum_likes, number_of_votes, title, content, user_id, status, hash_id, created, last_update, updater_id, order_importance, room_id) VALUES (0, 0, :info, :votes_available_per_user, 0, 0, 0, :title, :content, :user_id, :status, :hash_id, NOW(), NOW(), :updater_id, :order_importance, :room_id)');
    // bind all VALUES

    $this->db->bind(':content', $this->crypt->encrypt($content)); // encrypt the content
    $this->db->bind(':title', $title);
    $this->db->bind(':status', $status);
    $this->db->bind(':info', $info);
    $this->db->bind(':room_id', $room_id);
    $this->db->bind(':user_id', $user_id);
    $this->db->bind(':votes_available_per_user', $votes_available_per_user);
    // generate unique hash for this idea
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($content . $appendix); // create hash id for this idea
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
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Added new idea (#" . $insertid . ") " . $content, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $insertid; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      $this->syslog->addSystemEvent(1, "Error adding idea " . $content, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }


  }// end function

  public function addCategory($name, $description_public = "", $description_internal = "", $status = 1, $order_importance = 10, $room_id = 0, $updater_id = 0)
  {
    /* adds a new category and returns insert id (idea id) if successful, accepts the above parameters
     content = actual content of the idea,
     status = status of inserted category (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
     info is internal info or can be used for open aula to enter the name of the person that had the idea
    */

    //sanitize vars
    $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $status = intval($status);
    $room_id = $this->converters->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)
    $order_importance = intval($order_importance);
    $description_public = trim($description_public);
    $description_internal = trim($description_internal);

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_categories . ' (name, description_public, description_internal, status, hash_id, created, last_update, updater_id) VALUES (:name, :description_public, :description_internal, :status, :hash_id, NOW(), NOW(), :updater_id)');
    // bind all VALUES

    $this->db->bind(':name', $name);
    $this->db->bind(':status', $status);
    $this->db->bind(':description_public', $description_public);
    $this->db->bind(':description_internal', $description_internal);
    // generate unique hash for this idea
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($$name . $appendix); // create hash id for this idea
    $this->db->bind(':hash_id', $hash_id);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    $insertid = intval($this->db->lastInsertId());
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Added new category (#" . $insertid . ") " . $name, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $insertid; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      $this->syslog->addSystemEvent(1, "Error adding category " . $name, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }


  }// end function

  public function deleteCategory($category_id, $updater_id)
  {
    // deletes a category

    $category_id = $this->converters->checkCategoryId($category_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_categories . ' WHERE id = :category_id');
    // bind all VALUES

    $this->db->bind(':category_id', $category_id); // category id

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query
      $rows = intval($this->db->rowCount());

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $rows; // returned data
      $returnvalue['count'] = $rows; // returned count of datasets

      return $returnvalue;

    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }

  public function setIdeaStatus($idea_id, $status, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     status = status of idea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':status', $status);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea status changed " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error changing status of idea " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function moveIdeaBetweenTopics($idea_id, $topic_id1, $topic_id2, $updater_id)
  {
    // moves an idea from topic 1 to topic 2
    $idea_id = $this->converters->checkIdeaId($idea_id); // auto convert
    $topic_id1 = $this->converters->checkTopicId($topic_id1); // auto convert
    $topic_id2 = $this->converters->checkTopicId($topic_id2); // auto convert

    $ret_value = $this->removeIdeaFromTopic($topic_id1, $idea_id);

    if ($ret_value['success']) {
      // only if removal was successful add to topic 2
      $ret_value = $this->addIdeaToTopic($topic_id2, $idea_id, $updater_id);

      if ($ret_value['success']) {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 1; // returned count of datasets

        return $returnvalue;
      } else {
        // error occured while adding to topic 2
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue['data'] = false; // returned data
        $returnvalue['count'] = 0; // returned count of datasets

        return $returnvalue;

      }

    } else {
      // error occured while removing from group 1
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } // end else
  } // end function

  public function setCategoryStatus($category_id, $status, $updater_id = 0)
  {
    /* edits a category and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     status = status of category (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_categories . ' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :category_id');
    // bind all VALUES
    $this->db->bind(':status', $status);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':category_id', $category_id); // category that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Category status changed " . $category_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error changing status of  category " . $category_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setIdeaRoom($idea_id, $room_id, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     status = status of idea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $room_id = $this->converters->checkRoomId($room_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET room_id= :room_id, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':room_id', $room_id);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea room changed " . $idea_id . " to " . $room_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error changing status of idea " . $idea_id . " to " . $room_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setIdeaProperty($idea_id, $property, $prop_value, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     $property = field name in db
     $propvalue = value for property
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET ' . $property . '= :prop_value, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':prop_value', $prop_value);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea property " . $property . " changed for id " . $idea_id . " to " . $prop_value . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing status of idea property ".$property." for id ".$idea_id." to ".$prop_value." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function approveIdea($idea_id, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     approves an idea (usually by school administration)
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET approved = 1, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea approved " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error approving idea " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function disapproveIdea($idea_id, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     approves an idea (usually by school administration)
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET approved = 0, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea approved " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error approving idea " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setToWinning($idea_id, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     flags an idea as winner in voting phase
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET is_winner = 1, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea set to winning " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error setting idea to winning " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setToLosing($idea_id, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     flags an idea as winner in voting phase
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET is_winner = 0, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea set to losing " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error setting idea to losing ".$idea_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function IdeaSetVotes($idea_id, $votes)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     sets sum_votes of a specific idea to a specific value (votes)
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET sum_votes = :votes, last_update= NOW() WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':votes', $votes); // vote value

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea  " . $idea_id . " votes set to " . $votes, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error setting votes from idea " . $idea_id . " to " . $votes, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function IdeaSetNumberOfVotesGiven($idea_id, $votes)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     sets number of votes given to an idea to a specific value (votes)
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET number_of_votes = :votes, last_update= NOW() WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':votes', $votes); // vote value

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea  " . $idea_id . " number of votes given set to " . $votes, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error setting number of votes given for idea " . $idea_id . " to " . $votes, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function IdeaSetLikes($idea_id, $likes)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     sets sum_likes of a specific idea to a specific value (likes)
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET sum_likes = :likes, last_update= NOW() WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':likes', $likes); // like value

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea  " . $idea_id . " likes set to " . $likes, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error setting likes from idea ".$idea_id." to ".$likes, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function IdeaAddLike($idea_id, $user_id)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     Adds a like to an idea, increments sum_likes of a specific idea to a specific value (likes)
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)

    // Check if user liked already
    if ($this->getLikeStatus($user_id, $idea_id)['data'] == 1) {
      // user has already liked, return without incrementing vote
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 3; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      // add like to db
      $this->addLikeUser($user_id, $idea_id);
    }
    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET sum_likes = sum_likes + 1, last_update= NOW() WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea  " . $idea_id . " incremented likes", 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error incrementing likes from idea " . $idea_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function IdeaRemoveLike($idea_id, $user_id)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     Adds a like to an idea, increments sum_likes of a specific idea to a specific value (likes)
     updater_id is the id of the idea that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

    if ($this->getLikeStatus($user_id, $idea_id)['data'] == 0) {
      // user has already liked, return without incrementing vote
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 0; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      // add like to db
      $this->removeLikeUser($user_id, $idea_id);
    }

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET sum_likes = sum_likes - 1, last_update= NOW() WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea  " . $idea_id . " decrementing likes", 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $this->syslog->addSystemEvent(1, "Error decrementing likes from idea " . $idea_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function resetVotes()
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     resets all votes for ideas in the database (vote_sum)

    */

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET sum_votes = 0, sum_likes = 0, last_update= NOW()');

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {

      $stmt = $this->db->query('UPDATE ' . $this->db->au_delegation . ' SET status = 1, last_update= NOW()');

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {
        echo 'Error occured while setting status: ', $e->getMessage(), "\n"; // display error
        $err = true;
      }
      $stmt = $this->db->query('DELETE FROM ' . $this->db->au_votes);

      $err = false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

        $err = true;
      }
      $stmt = $this->db->query('DELETE FROM ' . $this->db->au_likes . " WHERE object_type = 1");

      $err = false; // set error variable to false

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

      $this->syslog->addSystemEvent(0, "Resetting all votes", 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error resetting votes", 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function


  public function setContent($idea_id, $content, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     content = content of the idea
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET content= :content, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':content', $this->crypt->encrypt($content));
    $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea content changed " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing idea content ".$idea_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function


  public function setCategory($idea_id, $category_id, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     Sets all categories for an idea to specified idea id - use with caution!
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $category_id = $this->converters->checkCategoryId($category_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_rel_categories_ideas . ' SET category_id= :category_id, last_update= NOW(), updater_id= :updater_id WHERE idea_id= :idea_id');
    // bind all VALUES
    $this->db->bind(':category_id', $category_id);
    $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea category changed " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error changing idea category ".$idea_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  private function checkAvailableVotesUser($user_id, $idea_id)
  {
    // returns how many votes are still available for a certain idea
// get available votes for idea_id
    // check if user has delegated votes

    $stmt = $this->db->query('SELECT user_id FROM ' . $this->db->au_votes . ' WHERE user_id = :user_id AND idea_id = :idea_id');
    $this->db->bind(':idea_id', $idea_id); // bind idea id
    $this->db->bind(':user_id', $user_id); // bind user id

    $votes = $this->db->resultSet();

    $actual_votes_available = intval(1 - intval(count($votes))); // return number of total votes for this idea by this user

    if ($actual_votes_available < 0 || $actual_votes_available == 0) {
      $actual_votes_available = 0;
    } else {
      $actual_votes_available = 1;
    }

    return $actual_votes_available;
  }

  protected function addVoteUser($user_id, $idea_id, $vote_value, $number_of_delegations, $vote_bias_group = 1, $comment = "")
  {
    // add a vote into vote table for a certain user and idea

    //sanitize
    $idea_id = intval($idea_id);
    $vote_value = intval($vote_value);

    $number_of_delegations = intval($number_of_delegations);

    // get absolute value for vote value
    $vote_weight = intval(abs($vote_value) / $vote_bias_group); // compensate group bias for vote weight


    // compensate for neutral votes
    if ($vote_weight == 0) {
      $vote_weight = intval(1 + $number_of_delegations); // in this case add delegations since value is 0
    }


    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_votes . ' (comment, number_of_delegations, vote_weight, status, vote_value, user_id, idea_id, last_update, created, hash_id) VALUES (:comment, :number_of_delegations, :vote_weight, 1, :vote_value, :user_id, :idea_id, NOW(), NOW(), :hash_id)');
    // bind all VALUES
    $this->db->bind(':idea_id', $idea_id); // idea id
    $this->db->bind(':user_id', $user_id); // user id
    $this->db->bind(':comment', $comment); // user id
    $this->db->bind(':vote_value', $vote_value); // vote value
    $this->db->bind(':vote_weight', $vote_weight); // vote weight
    $this->db->bind(':number_of_delegations', $number_of_delegations); // vote delegations in this vote
    // generate unique hash for this vote
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($user_id . $idea_id . $appendix); // create hash id for this vote
    $this->db->bind(':hash_id', $hash_id); // hash id

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }

  protected function addLikeUser($user_id, $idea_id)
  {
    // add a like into like table for a certain user and idea

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_likes . ' (object_type, status, user_id, object_id, last_update, created, hash_id) VALUES (1, 1, :user_id, :idea_id, NOW(), NOW(), :hash_id)');
    // bind all VALUES
    $this->db->bind(':idea_id', $idea_id); // idea id
    $this->db->bind(':user_id', $user_id); // user id
    // generate unique hash for this vote
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($user_id . $idea_id . $appendix); // create hash id for this vote
    $this->db->bind(':hash_id', $hash_id); // hash id

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }


  public function setVoteUser($user_id, $idea_id, $vote_value, $number_of_delegations = 0)
  {
    //sanitize

    $vote_weight = abs($vote_value);
    // compensate for neutral votes
    if ($vote_weight < 1) {
      $vote_weight = 1;
    }
    // update sum of votes
    $stmt = $this->db->query('UPDATE ' . $this->db->au_votes . ' SET number_of_delegations= :number_of_delegations, vote_value = :vote_value, last_update= NOW(), vote_weight  = :vote_weight WHERE user_id = :user_id AND idea_id = :idea_id');
    // bind all VALUES
    $this->db->bind(':user_id', $user_id); // id of the user
    $this->db->bind(':vote_value', $vote_value); // vote value
    $this->db->bind(':vote_weight', $vote_weight); // vote weight
    $this->db->bind(':number_of_delegations', $number_of_delegations); // number_of_delegations

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea (#" . $idea_id . ") setting Vote - value: " . $vote_value . " by " . $user_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error setting vote value:  ".$vote_value." by ".$user_id." for idea #".$idea_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }

  protected function revokeVoteUser($user_id, $idea_id)
  {
    // add a vote into vote table for a certain user and idea

    // get vote value for this user on this idea

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_votes . ' WHERE user_id = :user_id AND idea_id = :idea_id');
    // bind all VALUES

    $this->db->bind(':idea_id', $idea_id); // idea id
    $this->db->bind(':user_id', $user_id); // user id

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query
      $rows = intval($this->db->rowCount());

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $rows; // returned data
      $returnvalue['count'] = $rows; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }

  public function removeLikeUser($user_id, $idea_id)
  {
    // add a vote into vote table for a certain user and idea

    // get vote value for this user on this idea

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_likes . ' WHERE user_id = :user_id AND object_id = :idea_id AND object_type=1');
    // bind all VALUES

    $this->db->bind(':idea_id', $idea_id); // idea id
    $this->db->bind(':user_id', $user_id); // user id

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query
      $rows = intval($this->db->rowCount());

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $rows; // returned data
      $returnvalue['count'] = $rows; // returned count of datasets

      return $returnvalue;

    } else {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }

  public function getUserInfiniteVotesStatus($user_id)
  {
    /* returns hash_id of a user for a integer user id
     */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    $stmt = $this->db->query('SELECT infinite_votes FROM ' . $this->db->au_users_basedata . ' WHERE id = :id');
    $this->db->bind(':id', $user_id); // bind userid
    $users = $this->db->resultSet();
    if (count($users) < 1) {
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $users[0]['infinite_votes']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
  }// end function

  public function userHasDelegated($user_id, $topic_id)
  {
    // checks if the user with user id has already delegated his votes for this idea (topic this idea belongs to)
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    $topic_id = $this->converters->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('SELECT user_id_target FROM ' . $this->db->au_delegation . ' WHERE user_id_original = :user_id AND topic_id = :topic_id AND status = 1');
    //$stmt = $this->db->query('SELECT user_id_target FROM '.$this->db->au_delegation.' INNER JOIN '.$this->db->au_rel_topics_ideas.' ON ('.$this->db->au_rel_topics_ideas.'.idea_id = WHERE (user_id_original = :user_id) = :user_id AND room_id = :room_id AND status = 1');
    $this->db->bind(':user_id', $user_id); // bind user id
    $this->db->bind(':topic_id', $topic_id); // bind topic id
    $has_delegated = $this->db->resultSet();
    //echo ("<br>userhasdelegated:".count ($has_delegated));
    // user has delegated?
    if (count($has_delegated) > 0) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = $has_delegated[0]['user_id_target']; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    }
    // user has not delegated
    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // db error code
    $returnvalue['data'] = 0; // returned data
    $returnvalue['count'] = 0; // returned count of datasets

    return $returnvalue;
  }

  protected function revokeDelegationToInactive($original_user, $target_user, $topic_id)
  {
    // remove delegation from db table
    $stmt = $this->db->query('UPDATE ' . $this->db->au_delegation . ' SET status = 0 WHERE user_id_original = :user_id AND user_id_target = :user_id_target AND topic_id = :topic_id');
    // bind all VALUES
    $this->db->bind(':user_id', $original_user); // gives the voting right
    $this->db->bind(':topic_id', $topic_id); // id of the topic
    $this->db->bind(':user_id_target', $target_user); // receives the voting right

    $err = false;
    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    $count_data = intval($this->db->rowCount());
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Delegation deleted for user id " . $user_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $count_data; // returned data
      $returnvalue['count'] = $count_data; // returned count of datasets

      return $returnvalue;

      return; // return number of affected rows to calling script
    } else {
      //$this->syslog->addSystemEvent(1, "Error deleting delegation for user with id ".$user_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // no error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  } // end function

  public function voteForIdea($idea_id, $vote_value, $user_id, $comment = "")
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     idea_id is obvious...accepts db id or hash id
     vote_value is -1, 0 , +1 (depending on positive or negative)
     user_id is the id of the user voting for the idea
     updater_id is the id of the user that commits the update (i.E. admin )
    */

    // sanitize vote value
    $vote_value = intval($vote_value);
    // set maximum boundaries for vote value
    if ($vote_value > 1) {
      $vote_value = 1;
    }
    if ($vote_value < -1) {
      $vote_value = -1;
    }

    //echo ("<br>Voting for idea:  ".$idea_id." by user: ".$user_id." vote value: ".$vote_value);

    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    // check if idea und user exist

    $idea_basedata = $this->getIdeaBaseData($idea_id)['data'];
    if ($idea_basedata['id'] == 0) {
      // idea does not exist, return with error code
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // idea doesn't exist
      $returnvalue['data'] = false; // returned data -false
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $status_idea = $idea_basedata['status']; // get idea status
    $topic_id = $this->getIdeaTopic($idea_id)['data']; // get topic id for idea
    $room_id = $idea_basedata['room_id'];

    // check if user is member of the group

    if ($status_idea == 0 || $status_idea > 1) {
      // idea does not exist/inactive or status >1 (suspended or archived)
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 3; //  error code - idea inactive, suspended or archived
      $returnvalue['data'] = false; // returned data - final vote value
      $returnvalue['count'] = 0; // returned count of datasets
      return $returnvalue;
    } // else continue processing

    $sum_votes_correction = 0; // init correction value for vote_sum in idea table

    $only_voting_once_allowed = 0; // 1 = user can only vote once, 0 = user can change vote any time
    $number_of_delegations = 0;

    // check if user has infinite votes, if yes - disable everything
    $infinite = $this->getUserInfiniteVotesStatus($user_id);

    $group_vote_bias = $this->group->getGroupVoteBiasForUser($user_id); // get group vote bias

    // sanitize
    if ($group_vote_bias < 1) {
      $group_vote_bias = 1;
    }

    if ($infinite['data'] == 0) {
      // user does not have infinite votes
      // check if user has already used up his votes
      $user_already_voted = 0; // helper var, used for detecting if user already voted (important for delegated votes)

      if ($this->checkAvailableVotesUser($user_id, $idea_id) < 1) {
        // votes are not available, user has used all votes
        $user_already_voted = 1; // user has already voted
        if ($only_voting_once_allowed == 1) {
          // voting is only allowed once
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 4; //  error code - all available votes used already
          $returnvalue['data'] = false; // returned data - final vote value
          $returnvalue['count'] = 0; // returned count of datasets
          return $returnvalue;
        } else {
          $vote_value_original_array = $this->getVoteValue($user_id, $idea_id);
          $vote_value_original = $vote_value_original_array['data']; // returns 0 if user has not yet voted
          // user can vote (change his mind) as often as he wishes
          //echo ("<br>vote value original: ".$vote_value_original);
          $this->revokeVoteUser($user_id, $idea_id); // remove vote from user
          // correct sum votes for the idea
          $current_sum = $this->getIdeaVotes($idea_id)['data']; // get votes for this idea
          //echo ("<br>current sum: ".$current_sum);
          // echo ("<br>current sum: ".$current_sum." vote value original: ".$vote_value_original);
          $new_vote_value = intval(intval($current_sum) - intval($vote_value_original)); // calculate difference votes
          $this->IdeaSetVotes($idea_id, $new_vote_value); // adjust sum_votes in idea (note - group bias is already in calculation)

        }
      } // else continue processing

      // check if user has delegated his votes to another user
      $delegated_user = $this->userHasDelegated($user_id, $topic_id)['data'];
      //echo ("<br>user ".$user_id." has delegated votes to user: ".$delegated_user);
      if ($delegated_user == 0) {
        // user has not delegated his votes, get vote bias by delegations to this user from other users
        $votes_bias = $this->getVoteBiasDelegations($user_id, $topic_id, $idea_id); // calculates all delegations to this user
        //echo ("<br>Votes bias (from delegations): ".$votes_bias);

        $number_of_delegations = (intval($votes_bias) - 1); // number of users that have delegated their vote to this user
        // add total votes to db
        // sum up votes
        $vote_value_final = intval(intval($votes_bias) * intval($vote_value)); // calculate total vote weight
        // addVoteUser ($user_id, $idea_id, $vote_value, $updater_id, $original_user_id)

        // apply group vote bias
        $vote_value_final = intval(intval($vote_value_final) * intval($group_vote_bias));

        $this->addVoteUser($user_id, $idea_id, $vote_value_final, $number_of_delegations, $group_vote_bias, $comment);
        $sum_votes_correction = $vote_value_final;
        //echo ("<br>user has not delegated, correction ".$sum_votes_correction." vote value final: ".$vote_value_final);

      } else {
        // user has delegated his votes, check if the user that has received the votes already voted for the idea
        // reduce vote of the target user vote and add one vote
        $vote_value_delegated_array = $this->getVoteValue($delegated_user, $idea_id); // returns 0 if user has not yet voted
        $vote_value_delegated = $vote_value_delegated_array['data']; // returns 0 if user has not yet voted
        //echo ("<br>vote_value_delegated: ".$vote_value_delegated);

        $delegation_correction_sum = 0; // correction factor for sum_votes in idea table

        // check if delegating user has already voted, if yes, then don't do any corrections (do corrections only at first vote of delegating user)
        //echo ("<br>user (".$user_id.") has voted: ".$user_already_voted);
        if ($user_already_voted == 0) {
          // user has not voted yet, do correction
          if ($vote_value_delegated > 0) {
            $vote_value_delegated--; // decrement vote value for the vote of the user that it was delegated to
            $delegation_correction_sum = -1; // correction for sum_votes in idea table
          }
          if ($vote_value_delegated < 0) {
            $vote_value_delegated++; // increment vote value for the vote of the user that it was delegated to
            $delegation_correction_sum = 1; // correction for sum_votes in idea table
          }

        }
        //echo ("<br>delegation_correction_sum: ".$delegation_correction_sum);
        // add one vote to db for this user

        // apply group vote bias
        $vote_value = intval(intval($vote_value) * intval($group_vote_bias));

        $this->addVoteUser($user_id, $idea_id, $vote_value, $number_of_delegations, $group_vote_bias, $comment);
        //echo ("<br>user has delegated, correction ".$sum_votes_correction." vote value final: ".$vote_value_final);


        $sum_votes_correction = intval(intval($vote_value) + intval($delegation_correction_sum));
        //echo ("<br>Votes  (from delegations): ".$vote_value. "delegation_correction_sum: ".$delegation_correction_sum);
        // correct vote of the delegated user and update in db
        $this->setVoteUser($delegated_user, $idea_id, $vote_value_delegated, $number_of_delegations);
        // revoke the delegation -> set to inactive
        //$this->revokeDelegationToInactive ($user_id, $delegated_user, $topic_id);

      } // end else
    } else {
      // user has infinite votes
      $vote_value = intval(intval($vote_value) * intval($group_vote_bias)); // appy group vote bias

      $this->addVoteUser($user_id, $idea_id, $vote_value, 0, $group_vote_bias, $comment); // add vote to vote table
      $sum_votes_correction = $vote_value; // set bias value for sum_votes of idea
    }

    // update sum of votes in idea (sum_votes)
    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET sum_votes = sum_votes +' . intval($sum_votes_correction) . ', last_update= NOW() WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      //update the number of votes for this idea
      $this->IdeaSetNumberOfVotesGiven($idea_id, intval($this->getIdeaNumberVotes($idea_id)['data']));
      $this->syslog->addSystemEvent(0, "Idea (#" . $idea_id . ") added Vote - value: " . $vote_value . " by " . $user_id, 0, "", 1); // add to systemlog
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $sum_votes_correction; // returned data - final vote value
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;


    } else {
      // $this->syslog->addSystemEvent(1, "Error adding vote idea (#".$idea_id.") value:  ".$vote_value." by ".$user_id, 0, "", 1); // add to systemlog
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data - final vote value
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    // add vote to database

  }// end function

  public function RevokeVoteFromIdea($idea_id, $user_id, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     idea_id is obvious...accepts db id or hash id
     user_id is the id of the user voting for the idea
     updater_id is the id of the user that commits the update (i.E. admin )
    */

    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    //check if idea exists
    $idea_exists = $this->converters->checkIdeaExist($idea_id);
    $status_idea = $idea_exists['status'];
    // $room_id = $idea_exists['room_id'];

    if ($status_idea == 0 || $status_idea > 1) {
      // idea does not exist or status >1 (suspended or archived)
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    } // else continue processing

    // add user vote to db
    $affected = $this->revokeVoteUser($user_id, $idea_id)['data'];
    if ($affected < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 3; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } // else continue processing

    $vote_value = 1; // will be exchanged with vote value read from database

    // update sum of votes
    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET sum_votes = sum_votes -' . $vote_value . ', last_update = NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea (#" . $idea_id . ") revoked Vote by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error revoking vote for idea (#".$idea_id.") by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;

    }
    // add vote to database

  }// end function


  public function setIdeaInfo($idea_id, $content, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     content = content of the idea
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET info= :content, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    $this->db->bind(':content', $this->crypt->encrypt($content));
    $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea info changed " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing idea info ".$idea_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setCustomField ($idea_id, $field_id, $content, $updater_id = 0)
  {
    /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     content = content of the idea
     $field_id = 1 or 2 (depending on which field is adressed)
     updater_id is the id of the user that commits the update (i.E. admin )
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $field_id = intval ($field_id);

    if ($field_id < 1 || $field_id > 2) {
      // field id out of range
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET custom_field'.$field_id.' = :content, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
    // bind all VALUES
    //$this->db->bind(':content', $this->crypt->encrypt($content));
    $this->db->bind(':content', $content);
    
    $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

    $this->db->bind(':idea_id', $idea_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea custom field ".$field_id." changed " . $idea_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;

    } else {
      //$this->syslog->addSystemEvent(1, "Error changing idea info ".$idea_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function getVoteValue($user_id, $idea_id)
  {
    /* Returns vote value for a specified user and idea
     */
    //echo ("<br>Call get Vote value: ".$user_id);

    $stmt = $this->db->query('SELECT vote_value FROM ' . $this->db->au_votes . ' WHERE user_id = :user_id AND idea_id = :idea_id');
    $this->db->bind(':user_id', $user_id); // bind user id
    $this->db->bind(':idea_id', $idea_id); // bind idea id

    $votes = $this->db->resultSet();
    //print_r ($votes);
    //echo ("<br>count: ".count ($votes));
    if (count($votes) < 1) {
      //echo ("<br>no votes found");
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = 0; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      //echo ("<br>Vote value for user (".$user_id.") found...".$votes[0]['vote_value']);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = intval($votes[0]['vote_value']); // returned data
      $returnvalue['count'] = count($votes); // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function getLikeStatus($user_id, $idea_id)
  {
    /* Checks if user (user_id) has already liked a specific idea (idea_id)
    returns 0 if not, returns 1 if yes
    */

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_likes . ' WHERE user_id = :user_id AND object_id = :idea_id AND object_type = 1'); // object type = 1 = idea
    $this->db->bind(':user_id', $user_id); // bind user id
    $this->db->bind(':idea_id', $idea_id); // bind idea id

    $likes = $this->db->resultSet();
    if (count($likes) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // db error code
      $returnvalue['data'] = 0; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

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

  public function deleteIdea($idea_id, $updater_id = 0)
  {
    /* deletes idea and returns the number of rows (int) accepts idea id or idea hash id //

    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_ideas . ' WHERE id = :id');
    $this->db->bind(':id', $idea_id);
    $err = false;
    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Idea deleted, id=" . $idea_id . " by " . $updater_id, 0, "", 1);
      //check for action
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error deleting idea with id ".$idea_id." by ".$updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // db error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

  }// end function

  public function getUpdatesByUser($user_id, $mode = 0)
  {
    /* returns statistics / updates for a defined user (userid) since last login of the user
    returns votes and comments with counts
    if $mode is set to zero, the method returns activity since last login of the specified user
    $mode = 1 gives back all actvity for this user
    */
    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

    # init return array
    $data = [];

    $select_defaults = 'SELECT ' . $this->db->au_ideas . '.id AS idea_id, ' . $this->db->au_ideas . '.title, ' . $this->db->au_ideas . '.room_id, ' . $this->db->au_rel_topics_ideas . '.topic_id, ' . $this->db->au_topics . '.phase_id';
    $join_topic = ' LEFT JOIN ' . $this->db->au_rel_topics_ideas . ' ON (' . $this->db->au_rel_topics_ideas . '.idea_id = ' . $this->db->au_ideas . '.id) LEFT JOIN ' . $this->db->au_topics . ' ON (' . $this->db->au_rel_topics_ideas . '.topic_id=' . $this->db->au_topics . '.id)';
    $join_user = ' LEFT JOIN ' . $this->db->au_users_basedata . ' ON (' . $this->db->au_ideas . '.user_id = ' . $this->db->au_users_basedata . '.id)';

    # first get votes
    $select_part = ', ' . $this->db->au_votes . '.id, ' . $this->db->au_votes . '.vote_value, ' . $this->db->au_votes . '.vote_weight, ' . $this->db->au_votes . '.number_of_delegations';
    $from = ' FROM ' . $this->db->au_votes . ' LEFT JOIN ' . $this->db->au_ideas . ' ON (' . $this->db->au_votes . '.idea_id = ' . $this->db->au_ideas . '.id)';

    if ($mode == 0) {
      // activity since last login of the user
      $where = ' WHERE ' . $this->db->au_votes . '.last_update > (SELECT ' . $this->db->au_users_basedata . '.last_login FROM ' . $this->db->au_users_basedata . ' WHERE ' . $this->db->au_ideas . '.user_id  = :user_id LIMIT 1)';
    } else {
      // all activity
      $where = ' WHERE ' . $this->db->au_ideas . '.user_id = :user_id';
    }

    $stmt = $this->db->query($select_defaults . $select_part . $from . $join_topic . $join_user . $where);
    $this->db->bind(':user_id', $user_id); // bind user id

    $err = false;
    try {
      $votes = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while getting updates: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    # second get comments
    $select_part = ', ' . $this->db->au_comments . '.id';
    $from_part = ' FROM ' . $this->db->au_comments . ' LEFT JOIN ' . $this->db->au_ideas . ' ON (' . $this->db->au_comments . '.idea_id = ' . $this->db->au_ideas . '.id)';

    if ($mode == 0) {
      // activity since last login of the user
      $where = ' WHERE ' . $this->db->au_ideas . '.user_id = :user_id AND ' . $this->db->au_comments . '.last_update > (SELECT ' . $this->db->au_users_basedata . '.last_login FROM ' . $this->db->au_users_basedata . ' WHERE ' . $this->db->au_users_basedata . '.id  = :user_id LIMIT 1)';
    } else {
      // all activity
      $where = ' WHERE ' . $this->db->au_ideas . '.user_id = :user_id';
    }

    $stmt = $this->db->query($select_defaults . $select_part . $from_part . $join_topic . $join_user . $where);
    $this->db->bind(':user_id', $user_id); // bind user id

    $err = false;
    try {
      $comments = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while getting updates: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }


    $total_datasets = count($votes) + count($comments);

    if ($total_datasets < 1) { # nothing happened in the meantime
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {

      // fill data array
      $data = [];

      // $count_votes = count($votes);
      // $count_comments = count($comments);

      // $total_datasets = $count_votes + $count_comments;
      // $data['votes_count'] = $count_votes;
      // $data['comments_count'] = $count_comments;

      $data['votes'] = $votes;
      $data['comments'] = $comments;

      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $data; // returned data
      $returnvalue['count'] = $total_datasets; // returned count of datasets

      return $returnvalue;

    }

  } // end function getUpdatesByUser

  public function getDashboardByUser($user_id, $status = 1, $room_id = -1, $limit = 0, $offset = 0)
  {
    /* returns
     (associative array)
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
    $user_id is the id of the user
    */

    $asc = 0;
    $orderby = 0;

    $offset = intval($offset);
    $limit = intval($limit);
    $orderby = intval($orderby);
    $asc = intval($asc);
    $status = intval($status);

    $user_id = $this->converters->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

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
    // additional conditions for the WHERE clause
    $extra_where = "";

    if ($status > -1) {
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_ideas . ".status = " . $status;
    }

    if ($room_id > -1) {
      $room_id = $this->converters->checkRoomId($room_id); // auto convert id
      // specific status selected / -1 = get all status values
      $extra_where .= " AND " . $this->db->au_ideas . ".room_id = " . $room_id;
    }


    $select_part = 'SELECT ' . $this->db->au_ideas . '.id, ' . $this->db->au_ideas . '.sum_likes, ' . $this->db->au_ideas . '.sum_votes, ' . $this->db->au_rel_topics_ideas . '.topic_id AS topic_id , ' . $this->db->au_topics . '.phase_id AS phase_id FROM ' . $this->db->au_ideas;
    $join = 'LEFT JOIN ' . $this->db->au_rel_topics_ideas . ' ON (' . $this->db->au_rel_topics_ideas . '.idea_id=' . $this->db->au_ideas . '.id) LEFT JOIN ' . $this->db->au_topics . ' ON (' . $this->db->au_rel_topics_ideas . '.topic_id=' . $this->db->au_topics . '.id)';
    $where = ' WHERE ' . $this->db->au_ideas . '.id > 0 AND ' . $this->db->au_ideas . '.user_id= :user_id ' . $extra_where;
    $stmt = $this->db->query($select_part . ' ' . $join . ' ' . $where);

    $this->db->bind(':user_id', $user_id); // bind user id

    $err = false;
    try {
      $ideas = $this->db->resultSet();

    } catch (Exception $e) {
      echo 'Error occured while getting ideas: ', $e->getMessage(), "\n"; // display error
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
    $total_datasets = count($ideas);

    // if ($total_datasets < 1) {
    //   $returnvalue['success'] = true; // set return value
    //   $returnvalue['error_code'] = 2; // error code
    //   $returnvalue['data'] = false; // returned data
    //   $returnvalue['count'] = 0; // returned count of datasets

    //   return $returnvalue;
    // } else {
    // get count
    $count_by_phase[0] = 0; # wild ideas
    $count_by_phase[10] = 0; # wild ideas
    $count_by_phase[20] = 0; # wild ideas
    $count_by_phase[30] = 0; # wild ideas
    $count_by_phase[40] = 0; # wild ideas/ approved / results /
    $count_by_phase[41] = 0; # wild ideas/ approved / results /
    $count_by_phase[42] = 0; # wild ideas/ disapproved / results /

    $count_by_phase[50] = 0; # wild ideas

    $data = [];

    $data['total_wild'] = 0;
    $data['total_idea_box'] = 0;
    $data['idea_ids'] = "";
    $data['idea_ids_wild'] = "";
    $data['idea_ids_box'] = "";

    $total_counter = 0;

    foreach ($ideas as $idea_row) {
      // get individual counts
      $idea_phase_id = $idea_row['phase_id'];
      $idea_topic_id = $idea_row['topic_id'];
      $idea_id = $idea_row['id'];

      if ($idea_phase_id == NULL) {
        $idea_phase_id = 0;
      }

      $data['idea_ids'] .= $idea_id . ",";

      if ($idea_topic_id == NULL) {
        $idea_topic_id = 0;
        $data['total_wild']++;
        $data['idea_ids_wild'] .= $idea_id . ",";
      } else {
        $data['total_idea_box']++;
        $data['idea_ids_box'] .= $idea_id . ",";

      }

      $count_by_phase[$idea_phase_id]++;



    } // end foreach
    $data['phase_counts'] = $count_by_phase;

    $returnvalue['success'] = true; // set return value
    $returnvalue['error_code'] = 0; // error code
    $returnvalue['data'] = $data; // returned data
    $returnvalue['count'] = count($ideas); // returned count of datasets

    return $returnvalue;

    //}
  }// end function



} // end class
?>