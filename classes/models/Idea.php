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
        $this->syslog = $syslog;

        $au_rooms = 'au_rooms';
        $au_groups = 'au_groups';
        $au_ideas = 'au_ideas';
        $au_votes = 'au_votes';
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
        $this->$au_ideas = $au_ideas; // table name for ideas
        $this->$au_votes = $au_votes; // table name for votes
        $this->$au_reported = $au_reported; // table name for reportings

        $this->$au_rel_rooms_users = $au_rel_rooms_users; // table name for relations room - user
        $this->$au_rel_groups_users = $au_rel_groups_users; // table name for relations group - user
        $this->$au_rel_topics_ideas = $au_rel_topics_ideas; // table name for relations topics - ideas
    }// end function

    public function getIdeaBaseData($idea_id) {
      /* returns idea base data for a specified db id */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas[0]; // return an array (associative) with all the data for the idea
      }
    }// end function

    public function getIdeaContent ($idea_id) {
      /* returns content, sum votes, sum likes, create, last_update, hash id and the user displayname of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT '.$this->au_ideas.'.content, '.$this->au_ideas.'.hash_id, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes, '.$this->au_ideas.'.last_update, '.$this->au_ideas.'.created, '.$this->au_users_basedata.'.displayname FROM '.$this->au_ideas.' INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.id='.$this->au_users_basedata.'.id) WHERE '.$this->au_ideas.'.id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas[0]; // return content for the idea
      }
    }// end function


    public function getIdeaVotes ($idea_id) {
      /* returns sum of votes of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT sum_votes FROM '.$this->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0,0 code
      }else {
        return $ideas[0]['sum_votes']; // return sum of the votes for the idea
      }
    }// end function

    public function getIdeaTopic ($idea_id) {
      /* returns sum of votes of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT topic_id FROM '.$this->au_rel_topics_ideas.' WHERE idea_id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0,0 code
      }else {
        return $ideas[0]['topic_id']; // return topic id for the idea
      }
    }// end function

  protected function buildCacheHash ($key) {
      return md5 ($key);
    }

    public function getIdeaLikes ($idea_id) {
      /* returns sum of likes of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT sum_likes FROM '.$this->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0,0 code
      }else {
        return $ideas[0]['sum_likes']; // return sum of the likes for the idea
      }
    }// end function


  public function getPersonalVoteStatus ($user_id, $idea_id, $room_id) {
      /* returns content, sum votes, sum likes, create, last_update, hash id and the user displayname of an idea for a integer idea id
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea_id id and converts idea id to db idea id if necessary (when idea hash id was passed)
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      // check if this user still has votes available
      $available_votes = $this->checkAvailableVotesUser ($user_id, $idea_id);

      // check for delegations
      $vote_factor = $this->getDelegations ($user_id, $room_id, $idea_id);

      $has_delegated = $this->userHasDelegated($user_id, $room_id);
      return $has_delegated.",".$vote_factor.",".$available_votes; // returns status of the voting for a specific user idea and room

    }// end function



    public function getIdeaHashId($idea_id) {
      /* returns hash_id of an idea for a integer idea id
      */
      $stmt = $this->db->query('SELECT hash_id FROM '.$this->au_ideas.' WHERE id = :id');
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

      $stmt = $this->db->query('SELECT id FROM '.$this->au_users_basedata.' WHERE hash_id = :hash_id');
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

      $stmt = $this->db->query('SELECT id FROM '.$this->au_ideas.' WHERE hash_id = :hash_id');
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

      $stmt = $this->db->query('SELECT id FROM '.$this->au_topics.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $topics = $this->db->resultSet();
      if (count($topics)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $topics[0]['id']; // return idea id
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
      $stmt = $this->db->query('SELECT id FROM '.$this->au_ideas.' WHERE id = :idea_id');
      $this->db->bind(':idea_id', $idea_id); // bind user id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return ('0,3'); // idea does not exist
      } // else continue processing
      // check if this user has already reported this idea
      $stmt = $this->db->query('SELECT object_id FROM '.$this->au_reported.' WHERE user_id = :user_id AND type = 0 AND object_id = :idea_id');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':idea_id', $idea_id); // bind user id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        //add this reporting to db
        $stmt = $this->db->query('INSERT INTO '.$this->au_reported.' (reason, object_id, type, user_id, status, created, last_update) VALUES (:reason, :idea_id, 0, :user_id, 0, NOW(), NOW())');
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

      $stmt = $this->db->query('SELECT status, room_id FROM '.$this->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      if (count($ideas)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $ideas[0]; // idea found, return status
      }
    } // end function


      public function checkTopicExist($topic_id) {
        /* returns 0 if topic does not exist, 1 if topic exists, accepts database id (int)
        */
        $topic_id = $this->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

        $stmt = $this->db->query('SELECT status, room_id FROM '.$this->au_topics.' WHERE id = :id');
        $this->db->bind(':id', $topic_id); // bind topic id
        $topic_id = $this->db->resultSet();
        if (count($topic_id)<1){
          return 0; // nothing found, return 0 code
        }else {
          return $topic_id[0]; // topic found, return status
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

        $stmt = $this->db->query('INSERT INTO '.$this->au_rel_topics_ideas.' (idea_id, topic_id, status, created, last_update, updater_id) VALUES (:idea_id, :topic_id, 1, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE last_update = NOW(), updater_id = :updater_id');

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

      $stmt = $this->db->query('DELETE FROM '.$this->au_rel_topics_ideas.' WHERE idea_id = :idea_id AND topic_id = :topic_id' );
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

      $stmt = $this->db->query('DELETE FROM '.$this->au_rel_topics_ideas.' WHERE topic_id = :topic_id' );
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
        $orderby_field = $this->au_ideas."status";
        break;
        case 1:
        $orderby_field = $this->au_ideas."order_importance";
        break;
        case 2:
        $orderby_field = $this->au_ideas."created";
        break;
        case 3:
        $orderby_field = $this->au_ideas."last_update";
        break;
        case 4:
        $orderby_field = $this->au_ideas."id";
        break;

        default:
        $orderby_field = $this->au_ideas."last_update";
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
      $select_part = 'SELECT '.$this->au_users_basedata.'.displayname, '.$this->au_ideas.'.room_id, '.$this->au_ideas.'.created, '.$this->au_ideas.'.last_update, '.$this->au_ideas.'.id, '.$this->au_ideas.'.content, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes FROM '.$this->au_ideas;
      $join =  'INNER JOIN '.$this->au_rel_topics_ideas.' ON ('.$this->au_rel_topics_ideas.'.idea_id='.$this->au_ideas.'.id) INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.user_id='.$this->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->au_ideas.'.status= :status AND '.$this->au_rel_topics_ideas.'.topic_id= :topic_id ';
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
      $stmt = $this->db->query('SELECT id FROM '.$this->au_votes.' WHERE user_id_original = :user_id AND idea_id = :idea_id AND status = 1');
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

    protected function getDelegations($user_id, $room_id, $idea_id) {
      /* returns number of delegated votes to this user (user_id), accepts database id (int)
      */
      $stmt = $this->db->query('SELECT '.$this->au_delegation.'.status, '.$this->au_delegation.'.user_id_original, '.$this->au_rel_topics_ideas.'.topic_id FROM '.$this->au_delegation.' INNER JOIN '.$this->au_rel_topics_ideas.' ON ('.$this->au_delegation.'.idea_id = '.$this->au_rel_topics_ideas.'.idea_id) WHERE user_id_target = :user_id AND room_id = :room_id AND status = 1');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':room_id', $room_id); // bind room id
      $delegations = $this->db->resultSet();
      $count_delegations = count ($delegations);

      // save delegated votes of original user into votes table of db
      foreach ($delegations as $result) {
          // check if original owner has already voted - if yes then reduce the count for vote bias by 1
          $user_original = $result['user_id_original'];
          if ($this->checkIfVoteWasMade($user_original, $idea_id)==1)
          {
            // original owner of the delegated vote has already voted (although he delegated)
            $count_delegations = $count_delegations - 1; // reduce the bias for the vote by 1
            // safety
            if ($count_delegations<0){
              $count_delegations = 0;
            }
          }
          $original_user = $result['user_id_original'];
          $this->addVoteUser($original_user, $idea_id, 0 , $original_user);
      } // end foreach

      return $count_delegations;

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

      $stmt = $this->db->query('SELECT '.$this->au_ideas.'.content, '.$this->au_ideas.'.hash_id, '.$this->au_ideas.'.id, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes, '.$this->au_ideas.'.last_update, '.$this->au_ideas.'.created, '.$this->au_users_basedata.'.displayname FROM '.$this->au_ideas.' INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.id='.$this->au_users_basedata.'.id) WHERE '.$this->au_ideas.'.status= :status '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
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



    public function getTopics ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="") {
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
        $orderby_field = "name";
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

      $stmt = $this->db->query('SELECT '.$this->au_topics.'.content, '.$this->au_topics.'.hash_id, '.$this->au_topics.'.id, '.$this->au_topics.'.sum_likes, '.$this->au_topics.'.sum_votes, '.$this->au_topics.'.last_update, '.$this->au_topics.'.created FROM '.$this->au_topics.' WHERE '.$this->au_ideas.'.status= :status '.$extra_where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $topics = $this->db->resultSet();

      } catch (Exception $e) {
          echo 'Error occured while getting topics: ',  $e->getMessage(), "\n"; // display error
          $err=true;
          return 0;
      }

      if (count($topics)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $topics; // return an array (associative) with all the data
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
      $select_part = 'SELECT '.$this->au_users_basedata.'.displayname, '.$this->au_ideas.'.room_id, '.$this->au_ideas.'.created, '.$this->au_ideas.'.last_update, '.$this->au_ideas.'.id, '.$this->au_ideas.'.content, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes FROM '.$this->au_ideas;
      $join =  'INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.user_id='.$this->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->au_ideas.'.status= :status AND '.$this->au_ideas.'.room_id= :room_id ';
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
      $select_part = 'SELECT '.$this->au_users_basedata.'.displayname, '.$this->au_ideas.'.room_id, '.$this->au_ideas.'.created, '.$this->au_ideas.'.last_update, '.$this->au_ideas.'.id, '.$this->au_ideas.'.content, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes FROM '.$this->au_ideas;
      $join =  'INNER JOIN '.$this->au_rel_groups_users.' ON ('.$this->au_rel_groups_users.'.user_id='.$this->au_ideas.'.user_id) INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.user_id='.$this->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->au_ideas.'.status= :status AND '.$this->au_rel_groups_users.'.group_id= :group_id ';
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
      $room_id is the id of the room
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
      $select_part = 'SELECT '.$this->au_users_basedata.'.displayname, '.$this->au_ideas.'.room_id, '.$this->au_ideas.'.created, '.$this->au_ideas.'.last_update,  '.$this->au_ideas.'.id, '.$this->au_ideas.'.content, '.$this->au_ideas.'.sum_likes, '.$this->au_ideas.'.sum_votes FROM '.$this->au_ideas;
      $join =  'INNER JOIN '.$this->au_users_basedata.' ON ('.$this->au_ideas.'.user_id='.$this->au_users_basedata.'.id)';
      $where = ' WHERE '.$this->au_ideas.'.status= :status AND '.$this->au_ideas.'.user_id= :user_id ';
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
        $room_id = intval($room_id);
        $order_importance = intval ($order_importance);
        $content = trim ($content);
        $info = trim ($info);

        $stmt = $this->db->query('INSERT INTO '.$this->au_ideas.' (is_winner, approved, info, votes_available_per_user, sum_votes, sum_likes, content, user_id, status, hash_id, created, last_update, updater_id, order_importance, room_id) VALUES (0, 0, :info, :votes_available_per_user, 0, 0, :content, :user_id, :status, :hash_id, NOW(), NOW(), :updater_id, :order_importance, :room_id)');
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

    public function addTopic ($name, $description_internal, $description_public, $status, $order_importance=10, $updater_id=0, $room_id=0) {
        /* adds a new topic and returns insert id (idea id) if successful, accepts the above parameters
         name = name of the topic, description_internal = shown only to admins for internal use
         desciption_public = shown in frontend, order_importance = order bias for sorting in the frontend
         status = status of inserted topic (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)

        */

        //sanitize the vars
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $updater_id = $this->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        $status = intval($status);
        $room_id = intval($room_id);
        $order_importance = intval ($order_importance);
        $description_internal = trim ($description_internal);
        $description_public = trim ($description_public);


        $stmt = $this->db->query('INSERT INTO '.$this->au_ideas.' (name, description_internal, description_public, status, hash_id, created, last_update, updater_id, order_importance, room_id) VALUES (:name, :description_internal, :description_public, :status, :hash_id, NOW(), NOW(), :updater_id, :order_importance, :room_id)');
        // bind all VALUES

        $this->db->bind(':name', $name);
        $this->db->bind(':status', $status);
        $this->db->bind(':description_public', $description_public);
        $this->db->bind(':description_internal', $description_internal);
        $this->db->bind(':room_id', $room_id);
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
          $this->syslog->addSystemEvent(0, "Added new topic (#".$insertid.") ".$name, 0, "", 1);
          return $insertid; // return insert id to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error adding topic ".$name, 0, "", 1);
          return "0,2"; // return 0,2 to indicate that there was an db error executing the statement
        }


    }// end function


    public function setIdeaStatus($idea_id, $status, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of idea (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the idea that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea  id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
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

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET approved = 1, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
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

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET approved = 0, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
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

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET is_winner = 1, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
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

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET is_winner = 0, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
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



    public function setContent($idea_id, $content, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         content = content of the idea
         updater_id is the id of the user that commits the update (i.E. admin )
        */
        $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET content= :content, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
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

    protected function checkAvailableVotesUser ($user_id, $idea_id){
      // returns how many votes are still available for a certain idea
// get available votes for idea_id
      $stmt = $this->db->query('SELECT votes_available_per_user FROM '.$this->au_ideas.' WHERE id = :idea_id');
      $this->db->bind(':idea_id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      $votes_available = $ideas[0]['votes_available_per_user'];

      // check if user has delegated votes
      // check if vote is delegated
      $original_user_id = $user_id;


      $stmt = $this->db->query('SELECT user_id FROM '.$this->au_votes.' WHERE user_id = :user_id AND idea_id = :idea_id');
      $this->db->bind(':idea_id', $idea_id); // bind idea id
      $this->db->bind(':user_id', $user_id); // bind user id

      $votes = $this->db->resultSet();

      $actual_votes_available = intval (intval ($votes_available)-intval (count($votes))); // return number of total votes for this idea by this user

      return $actual_votes_available;
    }

    protected function addVoteUser ($user_id, $idea_id, $vote_value, $updater_id, $original_user_id) {
      // add a vote into vote table for a certain user and idea

      $stmt = $this->db->query('INSERT INTO '.$this->au_votes.' (status, vote_value, user_id, idea_id, last_update, created, updater_id, hash_id, original_user_id) VALUES (1, :vote_value, :user_id, :idea_id, NOW(), NOW(), :updater_id, :hash_id, :original_user_id)');
      // bind all VALUES
      $this->db->bind(':updater_id', $updater_id); // id of the idea doing the update (i.e. admin)

      $this->db->bind(':idea_id', $idea_id); // idea id
      $this->db->bind(':user_id', $user_id); // user id
      $this->db->bind(':original_user_id', $original_user_id); // original user id
      $this->db->bind(':updater_id', $updater_id); // updater id
      $this->db->bind(':vote_value', $vote_value); // vote value1
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

    protected function revokeVoteUser ($user_id, $idea_id, $updater_id) {
      // add a vote into vote table for a certain user and idea

      // get vote value for this user on this idea

      $stmt = $this->db->query('DELETE FROM '.$this->au_votes.' WHERE user_id = :user_id AND idea_id = :idea_id');
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

      $stmt = $this->db->query('SELECT infinite_votes FROM '.$this->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $users[0]['infinite_votes']; // returns value of infinite votes
      }
    }// end function

    public function userHasDelegated($user_id, $room_id) {
      // checks if the user with user id has already delegated his votes for this idea (topic this idea belongs to)
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
      //$idea_id = $this->checkIdeaId($idea_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT user_id_target FROM '.$this->au_delegation.' WHERE user_id_original = :user_id AND room_id = :room_id AND status = 1');
      //$stmt = $this->db->query('SELECT user_id_target FROM '.$this->au_delegation.' INNER JOIN '.$this->au_rel_topics_ideas.' ON ('.$this->au_rel_topics_ideas.'.idea_id = WHERE (user_id_original = :user_id) = :user_id AND room_id = :room_id AND status = 1');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':room_id', $room_id); // bind room id
      $has_delegated = $this->db->resultSet();

      if (count ($has_delegated)>0) {
        return 1;
      }
      return 0;
    }

    public function voteForIdea($idea_id, $vote_value, $user_id, $updater_id=0) {
        /* edits an idea and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         idea_id is obvious...accepts db id or hash id
         vote_value is -1, 0 , +1 (depending on positive or negative)
         user_id is the id of the user voting for the idea
         updater_id is the id of the user that commits the update (i.E. admin )
        */

        // sanitize vote value
        $vote_value = intval ($vote_value);
        // set maximum boundaries for vote value
        if ($vote_value>1) {
          $vote_value = 1;
        }
        if ($vote_value<-1) {
          $vote_value = -1;
        }

        $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)
        $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

        // check if idea und user exist
        $idea_exists = $this->checkIdeaExist ($idea_id);
        $status_idea = $idea_exists['status'];
        $room_id = $idea_exists['room_id'];


        if ($status_idea == 0 || $status_idea >1) {
          // idea does not exist or status >1 (suspended or archived)
          return ("0,1"); // return error (0) idea does not exist or is suspended /archived / in review (1)
        } // else continue processing

        // check if user has infinite votes, if yes - disable everything
        if ($this->getUserInfiniteVotesStatus($user_id)==0){
          // user does not have infinite votes
          // check if user has delegated his votes to another user
          if ($this->userHasDelegated($user_id, $room_id)==1){
            // user has delegated his votes, check if the user that has received the votes already voted for the idea
            // if yes, then remove the vote from the delegee and add a vote from the original owner of the vote
            return "0,3"; // user has delegated his votes, return errorcode
          }

          // check if user has already used up his votes
          if ($this->checkAvailableVotesUser ($user_id, $idea_id)<1) {
            // votes are not available, user has used all votes
            return ("0,2"); // all votes used already, return error
          } // else continue processing

          // check if this user has delegated votes
          $vote_factor = $this->getDelegations ($user_id, $room_id, $idea_id);
          // calculate vote factor based on delegations
          if ($vote_factor <1) {
            // no delegations were made, keep standard value
            $vote_factor = 1;
          }

        } else {
          // user has infinite votes
          $vote_factor=1;
        }



        $vote_value = intval (intval ($vote_factor)*intval ($vote_value)+$vote_value);

        // add user vote to db
        $this->addVoteUser ($user_id, $idea_id, $vote_value, $updater_id, $user_id);

        // update sum of votes
        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET sum_votes = sum_votes +'.$vote_value.', last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
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
          $this->syslog->addSystemEvent(0, "Idea (#".$idea_id.") added Vote - value: ".$vote_value." by ".$updater_id, 0, "", 1);
          return ("1,1");
        } else {
          $this->syslog->addSystemEvent(1, "Error adding vote idea (#".$idea_id.") value:  ".$vote_value." by ".$updater_id, 0, "", 1);
          return ("0,0"); // return 0 to indicate that there was an error executing the statement
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
        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET sum_votes = sum_votes -'.$vote_value.', last_update = NOW(), updater_id= :updater_id WHERE id= :idea_id');
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

        $stmt = $this->db->query('UPDATE '.$this->au_ideas.' SET info= :content, last_update= NOW(), updater_id= :updater_id WHERE id= :idea_id');
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

        $stmt = $this->db->query('DELETE FROM '.$this->au_ideas.' WHERE id = :id');
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

    public function removeDelegationsTopic ($topic_id){
      $topic_id = $this->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

      $stmt = $this->db->query('DELETE FROM '.$this->au_delegation.' WHERE topic_id = :id');
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
        $this->syslog->addSystemEvent(0, "Delegations for topic deleted, id=".$topic_id."", 0, "", 1);
        //check for action
        // remove delegations and remove associations with this topic

        return intval ($this->db->rowCount()); // return number of affected rows to calling script
      } else {
        $this->syslog->addSystemEvent(1, "Error deleting delegations for topic with id ".$topic_id, 0, "", 1);
        return 0; // return 0 to indicate that there was an error executing the statement
      }
    }

    public function deleteTopic($topic_id, $updater_id=0) {
        /* deletes topic, cleans up and returns the number of rows (int) accepts idea id or idea hash id //

        */
        $topic_id = $this->checkTopicId($topic_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->au_topics.' WHERE id = :id');
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
          $this->syslog->addSystemEvent(0, "Topic deleted, id=".$topic_id." by ".$updater_id, 0, "", 1);

          // remove delegations and remove associations with this topic
          $this->removeAllIdeasFromTopic ($topic_id);
          $this->removeDelegationsTopic ($topic_id);

          return intval ($this->db->rowCount()); // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting topic with id ".$topic_id." by ".$updater_id, 0, "", 1);
          return 0; // return 0 to indicate that there was an error executing the statement
        }

    }// end function

} // end class
?>
