<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}



class Idea {
    private $db;


    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->crypt = $crypt;
        //$this->syslog = new Systemlog ($db);
        $this->syslog = $syslog;
        $this->group = new Group ($db, $crypt, $syslog); // init group class

        $au_rooms = 'au_rooms';
        $au_groups = 'au_groups';
        $au_votes = 'au_votes';
        $au_likes = 'au_likes';
        $au_topics = 'au_topics';
        $au_delegation = 'au_delegation';
        $au_reported = 'au_reported';
        $au_users_basedata = 'au_users_basedata';
        $au_rel_rooms_users ='au_rel_rooms_users';
        $au_rel_groups_users ='au_rel_groups_users';
        $au_rel_topics_ideas ='au_rel_topics_ideas';

        $this->$au_users_basedata = $au_users_basedata; // table name for user basedata
        $this->$au_rooms = $au_rooms; // table name for rooms
        $this->$au_delegation = $au_delegation; // table name for delegation
        $this->$au_groups = $au_groups; // table name for groups
        $this->$au_topics = $au_topics; // table name for topics
        $this->$au_votes = $au_votes; // table name for votes
        $this->$au_likes = $au_likes; // table name for likes
        $this->$au_reported = $au_reported; // table name for reportings

        $this->$au_rel_rooms_users = $au_rel_rooms_users; // table name for relations room - user
        $this->$au_rel_groups_users = $au_rel_groups_users; // table name for relations group - user
        $this->$au_rel_topics_ideas = $au_rel_topics_ideas; // table name for relations topics - ideas
    }// end function

    public function getIdeaBaseData($idea_id) {
      /* returns idea base data for a specified db id */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)
      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        $ideas['id'] = 0;
        return $ideas['id']; // nothing found, return 0 code
      }else {
        return $ideas[0]; // return an array (associative) with all the data for the idea
      }
    }// end function

    public function getIdeaContent ($idea_id) {
      /* returns content, sum votes, sum likes, number of votes, create, last_update, hash id and the user displayname of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT '.$this->db->au_ideas.'.content, '.$this->db->au_ideas.'.hash_id, '.$this->db->au_ideas.'.sum_likes, '.$this->db->au_ideas.'.sum_votes, '.$this->db->au_ideas.'.number_of_votes, '.$this->db->au_ideas.'.last_update, '.$this->db->au_ideas.'.created, '.$this->db->au_users_basedata.'.displayname FROM '.$this->db->au_ideas.' INNER JOIN '.$this->db->au_users_basedata.' ON ('.$this->db->au_ideas.'.id='.$this->db->au_users_basedata.'.id) WHERE '.$this->db->au_ideas.'.id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas[0]; // return content for the idea
      }
    }// end function

    public function getIdeaNumberVotes ($idea_id) {
      /* returns the calculated number of given votes for this idea
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT SUM(vote_weight) AS totalvotes FROM '.$this->db->au_votes.' WHERE idea_id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return intval ($ideas[0]['totalvotes']); // return total made votes for the idea
      }
    }// end function


    public function getIdeaVotes ($idea_id) {
      /* returns sum of votes of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT sum_votes FROM '.$this->db->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0,0 code
      }else {
        return $ideas[0]['sum_votes']; // return sum of the votes for the idea
      }
    }// end function

    public function getIdeaTopic ($idea_id) {
      /* returns the topic for a specificc idea integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT topic_id FROM '.$this->db->au_rel_topics_ideas.' WHERE idea_id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0,0 code
      }else {
        return $ideas[0]['topic_id']; // return topic id for the idea
      }
    }// end function

    public function getIdeaRoom ($idea_id) {
      /* returns the topic for a specificc idea integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT room_id FROM '.$this->db->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0,0 code
      }else {
        return $ideas[0]['room_id']; // return room id for the idea
      }
    }// end function

  protected function buildCacheHash ($key) {
      return md5 ($key);
    }

    public function getIdeaLikes ($idea_id) {
      /* returns sum of likes of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT sum_likes FROM '.$this->db->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0,0 code
      }else {
        return $ideas[0]['sum_likes']; // return sum of the likes for the idea
      }
    }// end function

    public function getIdeaStatus ($idea_id) {
      /* returns the status of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT status FROM '.$this->db->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0,0 code
      }else {
        return $ideas[0]['status']; // return status of the idea
      }
    }// end function


  public function getPersonalVoteStatus ($user_id, $idea_id, $room_id) {
      /* returns content, sum votes, sum likes, create, last_update, hash id and the user displayname of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $room_id = $this->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)

      // check if this user still has votes available
      $available_votes = $this->checkAvailableVotesUser ($user_id, $idea_id);

      // check for delegations
      $vote_factor = $this->getVoteBiasDelegations ($user_id, $room_id, $idea_id);

      $has_delegated = $this->userHasDelegated($user_id, $room_id);
      return $has_delegated.",".$vote_factor.",".$available_votes; // returns status of the voting for a specific user idea and room

    }// end function



    public function getIdeaHashId($idea_id) {
      /* returns hash_id of an idea for a integer idea id
      */
      $stmt = $this->db->query('SELECT hash_id FROM '.$this->db->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return "0,0"; // nothing found, return 0 code
      }else {
        return "1,".$ideas[0]['hash_id']; // return hash id for the idea
      }
    }// end function

    private function checkUserId ($user_id) {
      /* helper function that checks if a user id is a standard db id (int) or if a hash userid was passed
      if a hash was passed, function gets db user id and returns db id
      */

      if (is_int($user_id))
      {
        return $user_id;
      } else
      {

        return $this->getUserIdByHashId ($user_id);
      }
    } // end function


    public function getUserIdByHashId($hash_id) {
      /* Returns Database ID of user when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_users_basedata.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]['id']; // return user id
      }
    }// end function

    public function getIdeaIdByHashId($hash_id) {
      /* Returns Database ID of idea when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_ideas.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas[0]['id']; // return idea id
      }
    }// end function

    public function getTopicIdByHashId($hash_id) {
      /* Returns Database ID of idea when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_topics.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $topics = $this->db->resultSet();
      if (count($topics)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $topics[0]['id']; // return topic id
      }
    }// end function


    public function reportIdea ($idea_id, $user_id, $updater_id, $reason =""){
      /* sets the status of an idea to 3 = reported
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      // check if idea is existent
      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_ideas.' WHERE id = :idea_id');
      $this->db->bind(':idea_id', $idea_id); // bind user id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return ('0,3'); // idea does not exist
      } // else continue processing
      // check if this user has already reported this idea
      $stmt = $this->db->query('SELECT object_id FROM '.$this->db->au_reported.' WHERE user_id = :user_id AND type = 0 AND object_id = :idea_id');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':idea_id', $idea_id); // bind user id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        //add this reporting to db
        $stmt = $this->db->query('INSERT INTO '.$this->db->au_reported.' (reason, object_id, type, user_id, status, created, last_update) VALUES (:reason, :idea_id, 0, :user_id, 0, NOW(), NOW())');
        // bind all VALUES

        $this->db->bind(':idea_id', $idea_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':reason', $reason);

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        $insertid = intval($this->db->lastInsertId());
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added new reporting (#".$insertid.") ".$content, 0, "", 1);
          // set idea status to reported
          $this->setIdeaStatus($idea_id, 3, $updater_id=0);
          return '1,1'; // nothing found, return 0 code

        } else {
          $this->syslog->addSystemEvent(1, "Error reporting idea ".$content, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
      }else {
        return '0,1'; // return error, user has already reported this idea
      }

    } // end function

    public function suspendIdea ($idea_id, $updater_id){
      /* sets the status of an idea to 3 = suspended
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 3, $updater_id=0);

    } // end function

    public function archiveIdea ($idea_id, $updater_id){
      /* sets the status of an idea to 4 = archived
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 4, $updater_id=0);

    }

    public function activateIdea ($idea_id, $updater_id){
      /* sets the status of an idea to 1 = active
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 1, $updater_id=0);

    }

    public function deactivateIdea ($idea_id, $updater_id){
      /* sets the status of an idea to 0 = inactive
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 0, $updater_id=0);
    }

    public function setIdeatoReview ($idea_id, $updater_id){
      /* sets the status of an idea to 5 = in review
      accepts db id and hash id of idea
      user_id is the id of the user that reported the idea
      updater_id is the id of the user that did the update
      */
      return $this->setIdeaStatus($idea_id, 5, $updater_id=0);

    }


    public function checkIdeaExist($idea_id) {
      /* returns 0 if idea does not exist, 1 if idea exists, accepts database id (int)
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT status, room_id FROM '.$this->db->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // idea found, return 1
      }
    } // end function

      public function checkTopicExist($topic_id) {
        /* returns 0 if topic does not exist, 1 if topic exists, accepts database id (int)
        */
        $topic_id = $this->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

        $stmt = $this->db->query('SELECT status, room_id FROM '.$this->db->au_topics.' WHERE id = :id');
        $this->db->bind(':id', $topic_id); // bind topic id
        $topic_id = $this->db->resultSet();
        if (count($topic_id)<1){
          return 0; // nothing found, return 0 code
        }else {
          return 1; // topic found, return 1
        }
      } // end function

    public function addIdeaToTopic ($idea_id, $topic_id, $updater_id){
      // adds an idea (idea_id) to a specified topic (topic_id)

      //
      $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
      $topic_id = $this->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

      $idea_exist = $this->checkIdeaExist($idea_id);
      $topic_exist = $this->checkTopicExist($topic_id);
      $updater_id = $this->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)


      if ($idea_exist==1 && $topic_exist==1) {
        // everything ok, user and room exists
        // add relation to database

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_rel_topics_ideas.' (idea_id, topic_id, status, created, last_update, updater_id) VALUES (:idea_id, :topic_id, 1, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE last_update = NOW(), updater_id = :updater_id');

        // bind all VALUES
        $this->db->bind(':idea_id', $idea_id);
        $this->db->bind(':topic_id', $topic_id);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)


        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }

        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added idea ".$idea_id." to topic ".$topic_id, 0, "", 1);
          return "1,1,1"; // return error code 1 = successful

        } else {
          $this->syslog->addSystemEvent(0, "Error while adding idea ".$idea_id." to room ".$topic_id, 0, "", 1);

          return "0,1,1"; // return 0 to indicate that there was an error executing the statement
        }

      }else {
        return "0,".$idea_exist.",".$topic_exist; // returns error and 0 or 1 for user and room (0=doesn't exist, 1=exists)
      }

      return "1,1,1"; // returns 1=ok/successful, user exists (1), room exists (1)

    }



    public function removeIdeaFromTopic($topic_id, $idea_id) {
      /* removes an idea from a topic
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
      $topic_id = $this->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_rel_topics_ideas.' WHERE idea_id = :idea_id AND topic_id = :topic_id' );
      $this->db->bind(':topic_id', $topic_id); // bind topic id
      $this->db->bind(':idea_id', $idea_id); // bind idea id

      $err=false;
      try {
        $topics = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while deleting idea from topic: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return "0,0";
      }


      return "1,".$this->db->rowCount(); // return number of affected rows to calling script

    }// end function


    public function removeAllIdeasFromTopic ($topic_id) {
      /* removes all associations of ideas from a topic
      */
      $topic_id = $this->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_rel_topics_ideas.' WHERE topic_id = :topic_id' );
      $this->db->bind(':topic_id', $topic_id); // bind topic id
      $this->db->bind(':idea_id', $idea_id); // bind idea id

      $err=false;
      try {
        $topics = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while deleting all ideas from topic: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return "0,0";
      }


      return "1,".$this->db->rowCount(); // return number of affected rows to calling script

    }// end function

    public function getIdeasByTopic ($offset, $limit, $orderby=3, $asc=0, $status=1, $topic_id) {
      /* returns idealist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $room_id is the id of the room
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

      switch (intval ($orderby)){
        case 0:
        $orderby_field = $this->db->au_ideas."status";
        break;
        case 1:
        $orderby_field = $this->db->au_ideas."order_importance";
        break;
        case 2:
        $orderby_field = $this->db->au_ideas."created";
        break;
        case 3:
        $orderby_field = $this->db->au_ideas."last_update";
        break;
        case 4:
        $orderby_field = $this->db->au_ideas."id";
        break;

        default:
        $orderby_field = $this->db->au_ideas."last_update";
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
      $select_part = 'SELECT '.$this->db->au_users_basedata.'.displayname, '.$this->db->au_ideas.'.room_id, '.$this->db->au_ideas.'.created, '.$this->db->au_ideas.'.last_update, '.$this->db->au_ideas.'.id, '.$this->db->au_ideas.'.content, '.$this->db->au_ideas.'.sum_likes, '.$this->db->au_ideas.'.sum_votes FROM '.$this->db->au_ideas;
      $join =  'INNER JOIN '.$this->db->au_rel_topics_ideas.' ON ('.$this->db->au_rel_topics_ideas.'.idea_id='.$this->db->au_ideas.'.id) INNER JOIN '.$this->db->au_users_basedata.' ON ('.$this->db->au_ideas.'.user_id='.$this->db->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->db->au_ideas.'.status= :status AND '.$this->db->au_rel_topics_ideas.'.topic_id= :topic_id ';
      $stmt = $this->db->query($select_part.' '.$join.' '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status
      $this->db->bind(':group_id', $group_id); // bind group id

      $err=false;
      try {
        $ideas = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas; // return an array (associative) with all the data
      }
    }// end function

    protected function checkIfVoteWasMade ($user_id, $idea_id){
      // checks if there already is a vote by this user (user_id) for this idea (idea_id)
      $stmt = $this->db->query('SELECT vote_value FROM '.$this->db->au_votes.' WHERE user_id = :user_id AND idea_id = :idea_id AND status = 1 AND (vote_value > 0 OR vote_value < 0)');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':idea_id', $idea_id); // bind idea id
      $votes = $this->db->resultSet();
      $count_votes = count ($votes);
      if ($count_votes>0){
        return 1; // vote already given
      }else {
        return 0; // no votes yet
      }
    }

    protected function getVoteBiasDelegations ($user_id, $topic_id, $idea_id) {
      /* returns number of delegated votes to this user (user_id), accepts database id (int)
      */
      $stmt = $this->db->query('SELECT status, user_id_original FROM '.$this->db->au_delegation.' WHERE user_id_target = :user_id AND topic_id = :topic_id AND status = 1');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':topic_id', $topic_id); // bind topic id
      $delegations = $this->db->resultSet();
      $count_delegations = count ($delegations);
      //echo ("getVoteBiasDelegations for user ".$user_id." running with topic ".$topic_id.":".$count_delegations);
      // save delegated votes of original user into votes table of db
      $vote_bias = 1; // init vote bias
      foreach ($delegations as $result) {
          // check if original owner has already voted - if yes then reduce the count for vote bias by 1
          //echo ("<br>FOUND DELEGATION from ...".$result['user_id_original']);
          $user_original = $result['user_id_original'];
          if ($this->checkIfVoteWasMade($user_original, $idea_id)==0)
          {
            // original owner of the delegated vote has not voted yet (although he delegated)
            //echo ("<br>Original owner (".$user_original.") has not voted yet...".$vote_bias);
            $vote_bias++; // increase the bias for the vote by 1

          }
          $original_user = $result['user_id_original'];

      } // end foreach

      return $vote_bias;

    } // end function


    public function getIdeas ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="") {
      /* returns idealist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      extra_where = extra parameters for where clause, synthax " AND XY=4"
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

      switch (intval ($orderby)){
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

      $stmt = $this->db->query('SELECT '.$this->db->au_ideas.'.content, '.$this->db->au_ideas.'.hash_id, '.$this->db->au_ideas.'.id, '.$this->db->au_ideas.'.sum_likes, '.$this->db->au_ideas.'.sum_votes, '.$this->db->au_ideas.'.number_of_votes, '.$this->db->au_ideas.'.last_update, '.$this->db->au_ideas.'.created, '.$this->db->au_users_basedata.'.displayname FROM '.$this->db->au_ideas.' INNER JOIN '.$this->db->au_users_basedata.' ON ('.$this->db->au_ideas.'.id='.$this->db->au_users_basedata.'.id) WHERE '.$this->db->au_ideas.'.status= :status '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $ideas = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas; // return an array (associative) with all the data
      }
    }// end function


    public function getIdeasByRoom ($offset, $limit, $orderby=3, $asc=0, $status=1, $room_id) {
      /* returns idealist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $room_id is the id of the room
      */
      $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

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

      switch (intval ($orderby)){
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
      $select_part = 'SELECT '.$this->db->au_users_basedata.'.displayname, '.$this->db->au_ideas.'.room_id, '.$this->db->au_ideas.'.created, '.$this->db->au_ideas.'.last_update, '.$this->db->au_ideas.'.id, '.$this->db->au_ideas.'.content, '.$this->db->au_ideas.'.sum_likes, '.$this->db->au_ideas.'.sum_votes FROM '.$this->db->au_ideas;
      $join =  'INNER JOIN '.$this->db->au_users_basedata.' ON ('.$this->db->au_ideas.'.user_id='.$this->db->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->db->au_ideas.'.status= :status AND '.$this->db->au_ideas.'.room_id= :room_id ';
      $stmt = $this->db->query($select_part.' '.$join.' '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status
      $this->db->bind(':room_id', $room_id); // bind room id

      $err=false;
      try {
        $ideas = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas; // return an array (associative) with all the data
      }
    }// end function

    public function getIdeasByGroup ($offset, $limit, $orderby=3, $asc=0, $status=1, $group_id) {
      /* returns idealist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $$group_id is the id of the group
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

      switch (intval ($orderby)){
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
      $select_part = 'SELECT '.$this->db->au_users_basedata.'.displayname, '.$this->db->au_ideas.'.room_id, '.$this->db->au_ideas.'.created, '.$this->db->au_ideas.'.last_update, '.$this->db->au_ideas.'.id, '.$this->db->au_ideas.'.content, '.$this->db->au_ideas.'.sum_likes, '.$this->db->au_ideas.'.sum_votes FROM '.$this->db->au_ideas;
      $join =  'INNER JOIN '.$this->db->au_rel_groups_users.' ON ('.$this->db->au_rel_groups_users.'.user_id='.$this->db->au_ideas.'.user_id) INNER JOIN '.$this->db->au_users_basedata.' ON ('.$this->db->au_ideas.'.user_id='.$this->db->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->db->au_ideas.'.status= :status AND '.$this->db->au_rel_groups_users.'.group_id= :group_id ';
      $stmt = $this->db->query($select_part.' '.$join.' '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status
      $this->db->bind(':group_id', $group_id); // bind group id

      $err=false;
      try {
        $ideas = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas; // return an array (associative) with all the data
      }
    }// end function

    public function getIdeasByUser ($offset, $limit, $orderby=3, $asc=0, $status=1, $user_id) {
      /* returns idealist (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $$user_id is the id of the user
      */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

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

      switch (intval ($orderby)){
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
      $select_part = 'SELECT '.$this->db->au_users_basedata.'.displayname, '.$this->db->au_ideas.'.room_id, '.$this->db->au_ideas.'.created, '.$this->db->au_ideas.'.last_update,  '.$this->db->au_ideas.'.id, '.$this->db->au_ideas.'.content, '.$this->db->au_ideas.'.sum_likes, '.$this->db->au_ideas.'.sum_votes FROM '.$this->db->au_ideas;
      $join =  'INNER JOIN '.$this->db->au_users_basedata.' ON ('.$this->db->au_ideas.'.user_id='.$this->db->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->db->au_ideas.'.status= :status AND '.$this->db->au_ideas.'.user_id= :user_id ';
      $stmt = $this->db->query($select_part.' '.$join.' '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status
      $this->db->bind(':user_id', $user_id); // bind room id

      $err=false;
      try {
        $ideas = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting ideas: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas; // return an array (associative) with all the data
      }
    }// end function


    public function addIdea ($content, $user_id, $status, $order_importance=10, $updater_id=0, $votes_available_per_user=1, $info="", $room_id=0) {
        /* adds a new idea and returns insert id (idea id) if successful, accepts the above parameters
         content = actual content of the idea,
         status = status of inserted indea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         info is internal info or can be used for open aula to enter the name of the person that had the idea
        */

        //sanitize in vars
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $updater_id = $this->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $status = intval($status);
        $room_id = $this->checkRoomId($room_id); // checks room_id id and converts room id to db room id if necessary (when room hash id was passed)
        $order_importance = intval ($order_importance);
        $content = trim ($content);
        $info = trim ($info);

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_ideas.' (is_winner, approved, info, votes_available_per_user, sum_votes, sum_likes, votes_given, content, user_id, status, hash_id, created, last_update, updater_id, order_importance, room_id) VALUES (0, 0, :info, :votes_available_per_user, 0, 0, 0, :content, :user_id, :status, :hash_id, NOW(), NOW(), :updater_id, :order_importance, :room_id)');
        // bind all VALUES

        $this->db->bind(':content', $this->crypt->encrypt($content)); // encrypt the content
        $this->db->bind(':status', $status);
        $this->db->bind(':info', $info);
        $this->db->bind(':room_id', $room_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':votes_available_per_user', $votes_available_per_user);
        // generate unique hash for this idea
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($content.$appendix); // create hash id for this idea
        $this->db->bind(':hash_id', $hash_id);
        $this->db->bind(':order_importance', $order_importance); // order parameter
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        $insertid = intval($this->db->lastInsertId());
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added new idea (#".$insertid.") ".$content, 0, "", 1);
          return $insertid; // return insert id to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error adding idea ".$content, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }


    }// end function

    public function setIdeaStatus($idea_id, $status, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of idea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea status changed ".$idea_id." by ".$updater_id, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing status of idea ".$idea_id." by ".$updater_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function approveIdea ($idea_id, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         approves an idea (usually by school administration)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET approved = 1, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea approved ".$idea_id." by ".$updater_id, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error approving idea ".$idea_id." by ".$updater_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function disapproveIdea ($idea_id, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         approves an idea (usually by school administration)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET approved = 0, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea approved ".$idea_id." by ".$updater_id, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error approving idea ".$idea_id." by ".$updater_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function setToWinning ($idea_id, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         flags an idea as winner in voting phase
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET is_winner = 1, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea set to winning ".$idea_id." by ".$updater_id, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error setting idea to winning ".$idea_id." by ".$updater_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function setToLosing ($idea_id, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         flags an idea as winner in voting phase
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET is_winner = 0, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea set to losing ".$idea_id." by ".$updater_id, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error setting idea to losing ".$idea_id." by ".$updater_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function IdeaSetVotes ($idea_id, $votes) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         sets sum_votes of a specific idea to a specific value (votes)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET sum_votes = :votes, last_update= NOW() WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':votes', $votes); // vote value

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea  ".$idea_id." votes set to ".$votes, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error setting votes from idea ".$idea_id." to ".$votes, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function IdeaSetNumberOfVotesGiven ($idea_id, $votes) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         sets number of votes given to an idea to a specific value (votes)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET number_of_votes = :votes, last_update= NOW() WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':votes', $votes); // vote value

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea  ".$idea_id." number of votes given set to ".$votes, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error setting number of votes given for idea ".$idea_id." to ".$votes, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function IdeaSetLikes ($idea_id, $likes) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         sets sum_likes of a specific idea to a specific value (likes)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET sum_likes = :likes, last_update= NOW() WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':likes', $likes); // like value

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea  ".$idea_id." likes set to ".$likes, 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error setting likes from idea ".$idea_id." to ".$likes, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function IdeaAddLike ($idea_id, $user_id) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         Adds a like to an idea, increments sum_likes of a specific idea to a specific value (likes)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)
        $user_id = $this->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)

        // Check if user liked already
        if ($this->getLikeStatus($user_id, $idea_id)==1){
          // user has already liked, return without incrementing vote
          return "0,1";
        }
        else {
          // add like to db
          $this->addLikeUser ($user_id, $idea_id);
        }
        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET sum_likes = sum_likes + 1, last_update= NOW() WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea  ".$idea_id." incremented likes", 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error incrementing likes from idea ".$idea_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function IdeaRemoveLike ($idea_id, $user_id) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         Adds a like to an idea, increments sum_likes of a specific idea to a specific value (likes)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        if ($this->getLikeStatus($user_id, $idea_id)==0){
          // user has already liked, return without incrementing vote
          return "0,1";
        }
        else {
          // add like to db
          $this->removeLikeUser ($user_id, $idea_id);
        }

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET sum_likes = sum_likes - 1, last_update= NOW() WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea  ".$idea_id." decrementing likes", 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error decrementing likes from idea ".$idea_id, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function resetVotes () {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         resets all votes for ideas in the database (vote_sum)

        */

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET sum_votes = 0, sum_likes = 0, last_update= NOW()');

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $stmt = $this->db->query('DELETE FROM '.$this->db->au_votes);

          $err=false; // set error variable to false

          try {
            $action = $this->db->execute(); // do the query

          } catch (Exception $e) {
              echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
              $err=true;
          }
          $stmt = $this->db->query('DELETE FROM '.$this->db->au_likes);

          $err=false; // set error variable to false

          try {
            $action = $this->db->execute(); // do the query

          } catch (Exception $e) {
              echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
              $err=true;
          }

          $this->syslog->addSystemEvent(0, "Resetting all votes", 0, "", 1);
          return "1,".intval($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error resetting votes", 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function


    public function setContent($idea_id, $content, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         content = content of the idea
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET content= :content, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':content', $this->crypt->encrypt($content));
        $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea content changed ".$idea_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing idea content ".$idea_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    public function checkAvailableVotesUser ($user_id, $idea_id){
      // returns how many votes are still available for a certain idea
// get available votes for idea_id
      // check if user has delegated votes

      $stmt = $this->db->query('SELECT user_id FROM '.$this->db->au_votes.' WHERE user_id = :user_id AND idea_id = :idea_id');
      $this->db->bind(':idea_id', $idea_id); // bind idea id
      $this->db->bind(':user_id', $user_id); // bind user id

      $votes = $this->db->resultSet();

      $actual_votes_available = intval (1-intval (count($votes))); // return number of total votes for this idea by this user

      if ($actual_votes_available < 0 || $actual_votes_available == 0){
        $actual_votes_available = 0;
      } else {
        $actual_votes_available = 1;
      }

      return $actual_votes_available;
    }

    protected function addVoteUser ($user_id, $idea_id, $vote_value, $number_of_delegations) {
      // add a vote into vote table for a certain user and idea
      //sanitize
      $idea_id = intval ($idea_id);
      $number_of_delegations = intval ($number_of_delegations);
      // get absolute value for vote value
      $vote_weight = abs ($vote_value);
      // compensate for neutral votes
      if ($vote_weight == 0){
        $vote_weight = intval (1 + $number_of_delegations); // in this case add delegations since value is 0
      }


      $stmt = $this->db->query('INSERT INTO '.$this->db->au_votes.' (number_of_delegations, vote_weight, status, vote_value, user_id, idea_id, last_update, created, hash_id) VALUES (:number_of_delegations, :vote_weight, 1, :vote_value, :user_id, :idea_id, NOW(), NOW(), :hash_id)');
      // bind all VALUES
      $this->db->bind(':idea_id', $idea_id); // idea id
      $this->db->bind(':user_id', $user_id); // user id
      $this->db->bind(':vote_value', $vote_value); // vote value
      $this->db->bind(':vote_weight', $vote_weight); // vote weight
      $this->db->bind(':number_of_delegations', $number_of_delegations); // vote delegations in this vote
      // generate unique hash for this vote
      $testrand = rand (100,10000000);
      $appendix = microtime(true).$testrand;
      $hash_id = md5($user_id.$idea_id.$appendix); // create hash id for this vote
      $this->db->bind(':hash_id', $hash_id); // hash id

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {
          echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
          $err=true;
      }
      if (!$err)
      {
        return 1;
      } else {
        return 0; // return 0 to indicate that there was an error executing the statement
      }
    }

    protected function addLikeUser ($user_id, $idea_id) {
      // add a like into like table for a certain user and idea

      $stmt = $this->db->query('INSERT INTO '.$this->db->au_likes.' (status, user_id, idea_id, last_update, created, hash_id) VALUES (1, :user_id, :idea_id, NOW(), NOW(), :hash_id)');
      // bind all VALUES
      $this->db->bind(':idea_id', $idea_id); // idea id
      $this->db->bind(':user_id', $user_id); // user id
      // generate unique hash for this vote
      $testrand = rand (100,10000000);
      $appendix = microtime(true).$testrand;
      $hash_id = md5($user_id.$idea_id.$appendix); // create hash id for this vote
      $this->db->bind(':hash_id', $hash_id); // hash id

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {
          echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
          $err=true;
      }
      if (!$err)
      {
        return 1;
      } else {
        return 0; // return 0 to indicate that there was an error executing the statement
      }
    }


    public function setVoteUser ($user_id, $idea_id, $vote_value, $number_of_delegations=0) {
      //sanitize

      $vote_weight = abs ($vote_value);
      // compensate for neutral votes
      if ($vote_weight < 1){
        $vote_weight = 1;
      }
      // update sum of votes
      $stmt = $this->db->query('UPDATE '.$this->db->au_votes.' SET number_of_delegations= :number_of_delegations, vote_value = :vote_value, last_update= NOW(), vote_weight  = :vote_weight WHERE user_id = :user_id AND idea_id = :idea_id');
      // bind all VALUES
      $this->db->bind(':user_id', $user_id); // id of the user
      $this->db->bind(':vote_value', $vote_value); // vote value
      $this->db->bind(':vote_weight', $vote_weight); // vote weight
      $this->db->bind(':number_of_delegations', $number_of_delegations); // number_of_delegations

      $this->db->bind(':idea_id', $idea_id); // idea that is updated

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {
          echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
          $err=true;
      }
      if (!$err)
      {
        $this->syslog->addSystemEvent(0, "Idea (#".$idea_id.") setting Vote - value: ".$vote_value." by ".$user_id, 0, "", 1);
        return ("1,1");
      } else {
        $this->syslog->addSystemEvent(1, "Error setting vote value:  ".$vote_value." by ".$user_id." for idea #".$idea_id, 0, "", 1);
        return ("0,0"); // return 0 to indicate that there was an error executing the statement
      }
    }

    protected function revokeVoteUser ($user_id, $idea_id) {
      // add a vote into vote table for a certain user and idea

      // get vote value for this user on this idea
      echo ("<br>DELETING in revokeVoteUser:".$user_id);
      $stmt = $this->db->query('DELETE FROM '.$this->db->au_votes.' WHERE user_id = :user_id AND idea_id = :idea_id');
      // bind all VALUES

      $this->db->bind(':idea_id', $idea_id); // idea id
      $this->db->bind(':user_id', $user_id); // user id

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query
        $rows = intval($this->db->rowCount());

      } catch (Exception $e) {
          echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
          $err=true;
      }
      if (!$err)
      {
        return $rows;
      } else {
        return 0; // return 0 to indicate that there was an error executing the statement
      }
    }

    public function removeLikeUser ($user_id, $idea_id) {
      // add a vote into vote table for a certain user and idea

      // get vote value for this user on this idea

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_likes.' WHERE user_id = :user_id AND idea_id = :idea_id');
      // bind all VALUES

      $this->db->bind(':idea_id', $idea_id); // idea id
      $this->db->bind(':user_id', $user_id); // user id

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query
        $rows = intval($this->db->rowCount());

      } catch (Exception $e) {
          echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
          $err=true;
      }
      if (!$err)
      {
        return $rows;
      } else {
        return 0; // return 0 to indicate that there was an error executing the statement
      }
    }

    public function getUserInfiniteVotesStatus($user_id) {
      /* returns hash_id of a user for a integer user id
      */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT infinite_votes FROM '.$this->db->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]['infinite_votes']; // returns value of infinite votes
      }
    }// end function

    public function userHasDelegated($user_id, $topic_id) {
      // checks if the user with user id has already delegated his votes for this idea (topic this idea belongs to)
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      $topic_id = $this->checkTopicId($topic_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT user_id_target FROM '.$this->db->au_delegation.' WHERE user_id_original = :user_id AND topic_id = :topic_id AND status = 1');
      //$stmt = $this->db->query('SELECT user_id_target FROM '.$this->db->au_delegation.' INNER JOIN '.$this->db->au_rel_topics_ideas.' ON ('.$this->db->au_rel_topics_ideas.'.idea_id = WHERE (user_id_original = :user_id) = :user_id AND room_id = :room_id AND status = 1');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':topic_id', $topic_id); // bind topic id
      $has_delegated = $this->db->resultSet();
      //echo ("<br>userhasdelegated:".count ($has_delegated));
      if (count ($has_delegated)>0) {
        return $has_delegated ['user_id_target'];
      }
      return 0;
    }

    public function voteForIdea($idea_id, $vote_value, $user_id) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         idea_id is obvious...accepts db id or hash id
         vote_value is -1, 0 , +1 (depending on positive or negative)
         user_id is the id of the user voting for the idea
         updater_id is the id of the user that commits the update (i.E. admin )
        */

        // sanitize vote value
      $vote_value = intval ($vote_value);
      // set maximum boundaries for vote value
      if ($vote_value > 1) {
        $vote_value = 1;
      }
      if ($vote_value < -1) {
        $vote_value = -1;
      }

      // echo ("<br>Voting for idea:  ".$idea_id." by user: ".$user_id." vote value: ".$vote_value);

      $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      // check if idea und user exist

      $idea_basedata = $this->getIdeaBaseData ($idea_id);
      if ($idea_basedata['id'] == 0){
        // idea does not exist, return with error code
        return ("0,0,0"); // return error code - idea non existant
      }
      $status_idea = $idea_basedata['status']; // get idea status
      $topic_id = $this->getIdeaTopic ($idea_id); // get toipic id for idea
      $room_id = $idea_basedata['room_id'];

      // check if user is member of the room

      if ($status_idea == 0 || $status_idea >1) {
        // idea does not exist/inactive or status >1 (suspended or archived)
        return ("0,1,0"); // return error (0) idea is inactive / suspended /archived / in review (1)
      } // else continue processing

      $sum_votes_correction = 0; // init correction value for vote_sum in idea table

      $only_voting_once_allowed = 0; // 1 = user can only vote once, 0 = user can change vote any time
      $number_of_delegations = 0;

      // check if user has infinite votes, if yes - disable everything
      if ($this->getUserInfiniteVotesStatus($user_id)==0){
        // user does not have infinite votes
        // check if user has already used up his votes
        if ($this->checkAvailableVotesUser ($user_id, $idea_id)<1) {
          // votes are not available, user has used all votes
          if ($only_voting_once_allowed==1) {

            // voting is only allowed once
            return ("0,2,0"); // all votes used already, return error
          } else {
            $vote_value_original = $this->getVoteValue ($user_id, $idea_id); // returns 0 if user has not yet voted
            // user can vote (change his mind) as often as he wishes
            $this->revokeVoteUser ($user_id, $idea_id); // remove vote from user
            // correct sum votes for the idea
            $current_sum = $this->getIdeaVotes ($idea_id); // get votes for this idea
            // echo ("<br>current sum: ".$current_sum." vote value original: ".$vote_value_original);
            $new_vote_value = intval (intval ($current_sum)-intval ($vote_value_original)); // calculate difference votes
            $this->IdeaSetVotes ($idea_id, $new_vote_value); // adjust sum_votes in idea

          }
        } // else continue processing


        // check if user has delegated his votes to another user
        $delegated_user = $this->userHasDelegated($user_id, $topic_id);
        //echo ("<br>user ".$user_id." has delegated votes to user: ".$delegated_user);
        if ($delegated_user==0){
          // user has not delegated his votes, get vote bias by delegations to this user from other users
          $votes_bias = $this->getVoteBiasDelegations ($user_id, $topic_id, $idea_id); // calculates all delegations to this user

          $number_of_delegations = (intval ($votes_bias)-1); // number of users that have delgetaed their vote to this user
          // add total votes to db
          // sum up votes
          $vote_value_final = intval (intval ($votes_bias) * intval ($vote_value)); // calculate total vote weight
          // addVoteUser ($user_id, $idea_id, $vote_value, $updater_id, $original_user_id)

          // apply group vote bias
          $this->addVoteUser ($user_id, $idea_id, $vote_value_final, $number_of_delegations);
          $sum_votes_correction = $vote_value_final;
          // echo ("<br>user has not delegated, correction ".$sum_votes_correction." vote value final: ".$vote_value_final);

        } else {
          // user has delegated his votes, check if the user that has received the votes already voted for the idea
          // reduce vote of the target user vote and add one vote
          $vote_value_delegated = getVoteValue ($delegated_user, $idea_id); // returns 0 if user has not yet voted
          $delegation_correction_sum = 0; // correction factor for sum_votes in idea table

          if ($vote_value_delegated > 0 ){
            $vote_value_delegated--; // decrement vote value for the vote of the user that it was delegated to
            $delegation_correction_sum = -1; // correction for sum_votes in idea table
          }
          if ($vote_value_delegated < 0 ){
            $vote_value_delegated++; // increment vote value for the vote of the user that it was delegated to
            $delegation_correction_sum = 1; // correction for sum_votes in idea table
          }
          // add one vote to db for this user

          // apply group vote bias

          $this->addVoteUser ($user_id, $idea_id, $vote_value, $number_of_delegations);
          // echo ("<br>user has delegated, correction ".$sum_votes_correction." vote value final: ".$vote_value_final);


          $sum_votes_correction = intval (intval ($vote_value) + intval ($delegation_correction_sum));
          // correct vote of the delegated user and update in db
          $this->setVoteUser ($delegated_user, $idea_id, $vote_value_delegated, $number_of_delegations);

        } // end else
      } else {
        // user has infinite votes
        $this->addVoteUser ($user_id, $idea_id, $vote_value, 0); // add vote to vote table
        $sum_votes_correction = $vote_value; // set bias value for sum_votes of idea
      }

      // update sum of votes in idea (sum_votes)
      $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET sum_votes = sum_votes +'.intval ($sum_votes_correction).', last_update= NOW() WHERE id= :idea_id');
      // bind all VALUES
      $this->db->bind(':idea_id', $idea_id); // idea that is updated

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {
          echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
          $err=true;
      }
      if (!$err)
      {
        //update the number of votes for this idea
        $this->IdeaSetNumberOfVotesGiven ($idea_id, intval ($this->getIdeaNumberVotes($idea_id)));
        $this->syslog->addSystemEvent(0, "Idea (#".$idea_id.") added Vote - value: ".$vote_value." by ".$user_id, 0, "", 1); // add to systemlog
        return ("1,1,".$sum_votes_correction); // return success and total vote value

      } else {
        $this->syslog->addSystemEvent(1, "Error adding vote idea (#".$idea_id.") value:  ".$vote_value." by ".$user_id, 0, "", 1); // add to systemlog
        return ("0,0,0"); // return 0 to indicate that there was an error executing the statement
      }
      // add vote to database

    }// end function

    public function RevokeVoteFromIdea($idea_id, $user_id, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         idea_id is obvious...accepts db id or hash id
         user_id is the id of the user voting for the idea
         updater_id is the id of the user that commits the update (i.E. admin )
        */

        $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        //check if idea exists
        $idea_exists = $this->checkIdeaExist ($idea_id);
        $status_idea = $idea_exists['status'];
        $room_id = $idea_exists['room_id'];

        if ($status_idea == 0 || $status_idea >1) {
          // idea does not exist or status >1 (suspended or archived)
          return ("0,1"); // return error (0) idea does not exist or is suspended /archived / in review (1)
        } // else continue processing

        // add user vote to db
        $affected = $this->revokeVoteUser ($user_id, $idea_id, $updater_id);
        if ($affected<1){
          return ("0,2"); // return with error message
        } // else continue processing

        $vote_value=1; // will be exchanged with vote value read from database

        // update sum of votes
        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET sum_votes = sum_votes -'.$vote_value.', last_update = NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea (#".$idea_id.") revoked Vote by ".$updater_id, 0, "", 1);
          return ("1,1");
        } else {
          $this->syslog->addSystemEvent(1, "Error revoking vote for idea (#".$idea_id.") by ".$updater_id, 0, "", 1);
          return ("0,0"); // return 0 to indicate that there was an error executing the statement
        }
        // add vote to database

    }// end function


    public function setIdeaInfo($idea_id, $content, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         content = content of the idea
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_ideas.' SET info= :content, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
        // bind all VALUES
        $this->db->bind(':content', $this->crypt->encrypt($content));
        $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

        $this->db->bind(':idea_id', $idea_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea info changed ".$idea_id." by ".$updater_id, 0, "", 1);
          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error changing idea info ".$idea_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }
    }// end function

    private function checkIdeaId ($idea_id) {
      /* helper function that checks if a idea id is a standard db id (int) or if a hash idea id was passed
      if a hash was passed, function gets db idea id and returns db id
      */
      if (is_int($idea_id))
      {
        return $idea_id;
      } else
      {
        return $this->getIdeaIdByHashId ($idea_id);
      }
    } // end function

    private function checkRoomId ($room_id) {
      /* helper function that checks if a room id is a standard db id (int) or if a hash room id was passed
      if a hash was passed, function gets db room id and returns db id
      */

      if (is_int($room_id))
      {
        return $room_id;
      } else
      {

        return $this->getRoomIdByHashId ($room_id);
      }
    } // end function

    private function checkTopicId ($topic_id) {
      /* helper function that checks if a topic id is a standard db id (int) or if a hash topic id was passed
      if a hash was passed, function gets db topic id and returns db id
      */

      if (is_int($topic_id))
      {
        return $topic_id;
      } else
      {
        return $this->getTopicIdByHashId ($topic_id);
      }
    } // end function

    public function getRoomIdByHashId($hash_id) {
      /* Returns Database ID of room when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_rooms.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $rooms[0]['id']; // return room id
      }
    }// end function

    public function getVoteValue ($user_id, $idea_id) {
      /* Returns vote value for a specified user and idea
      */

      $stmt = $this->db->query('SELECT vote_value FROM '.$this->db->au_votes.' WHERE user_id = :user_id AND idea_id = :idea_id');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':idea_id', $idea_id); // bind idea id

      $votes = $this->db->resultSet();
      if (count($votes)<1){
        return 0; // nothing found, return 0 code
      }else {
        return intval ($votes[0]['vote_value']); // return vote value for this idea and user
      }
    }// end function

    public function getLikeStatus ($user_id, $idea_id) {
      /* Checks if user (user_id) has already liked a specific idea (idea_id)
      returns 0 if not, returns 1 if yes
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_likes.' WHERE user_id = :user_id AND idea_id = :idea_id');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':idea_id', $idea_id); // bind idea id

      $likes = $this->db->resultSet();
      if (count($likes)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // return user has already liked
      }
    }// end function

    private function sendMessage ($user_id, $msg){
      /* send a message to the dashboard of the user
      yet to be written
      */

      $success = 0;
      return $success;
    }

    public function deleteIdea($idea_id, $updater_id=0) {
        /* deletes idea and returns the number of rows (int) accepts idea id or idea hash id //

        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->db->au_ideas.' WHERE id = :id');
        $this->db->bind (':id', $idea_id);
        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {
            echo 'Error occured: ',  $e->getMessage(), "\n"; // display error
            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Idea deleted, id=".$idea_id." by ".$updater_id, 0, "", 1);
          //check for action


          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting idea with id ".$idea_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }

    }// end function


} // end class
?>
