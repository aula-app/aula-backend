<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include == 1) {

} else {
  exit;
}



class Comment
{

  private $db;

  # class comments deals with everything concering comments of users
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
    # internal helper, returns md5 hash
    return md5($key);
  }

  public function getCommentOrderId($orderby)
  {
    # helper that converts int id to db field id
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
        return "idea_id";
      case 6:
        return "content";
      default:
        return "last_update";
    }
  }// end function

  public function getRoom($comment_id)
  {
    $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $stmt = $this->db->query('SELECT ' . $this->db->au_rooms . '.hash_id FROM '  . $this->db->au_rooms . ' LEFT JOIN ' . $this->db->au_ideas . ' ON ' . $this->db->au_ideas . '.room_id = ' . $this->db->au_rooms . '.id  LEFT JOIN '. $this->db->au_comments . ' ON '. $this->db->au_comments .'.idea_id = '. $this->db->au_ideas . '.id WHERE ' . $this->db->au_comments . '.id = :id');
    $this->db->bind(':id', $comment_id); // bind topic id
    $comments = $this->db->resultSet();

    if (count($comments) > 0) {
      return $comments[0]['hash_id'];
    } else {
      return false;
    }
  }

  public function getRoomByIdea($idea_id)
  {
    $idea = new Idea($this->db, $this->crypt, $this->syslog);
    return $idea->getRoom($idea_id);
  }

  public function getCommentsByIdeaId($idea_id, $offset = 0, $limit = 0, $orderby = 0, $asc = 0, $status = 1)
  {
    /* returns COMMENTS list (associative array) with start and limit provided for a certain IDEA
    if start and limit are set to 0, then the whole list is read (without limit)
    orderby is the field (int, see switch), defaults to last_update (0)
    asc (smallint), is either ascending (1) or descending (0), defaults to descending
    $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
    
    */
    $idea_id = $this->converters->checkIdeaId($idea_id); // checks id and converts id to db id if necessary (when hash id was passed)

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

    $orderby_field = $this->getCommentOrderId($orderby);

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
    $where = ' WHERE ' . $this->db->au_comments . '.status= :status AND idea_id= :idea_id ';
    $stmt = $this->db->query('SELECT ' . $this->db->au_comments . '.*, '. $this->db->au_users_basedata . '.hash_id as user_hash_id, ' . $this->db->au_users_basedata . '.username, ' . $this->db->au_users_basedata . '.displayname FROM ' . $this->db->au_comments . ' JOIN ' . $this->db->au_users_basedata . ' ON ' . $this->db->au_comments . '.user_id = ' . $this->db->au_users_basedata . '.id ' . $where . ' ORDER BY ' . $orderby_field . ' ' . $asc_field . ' ' . $limit_string);
    if ($limit) {
      // only bind if limit is set
      $this->db->bind(':offset', $offset); // bind limit
      $this->db->bind(':limit', $limit); // bind limit
    }
    $this->db->bind(':status', $status); // bind status
    $this->db->bind(':idea_id', $idea_id); // bind idea id

    $err = false;
    try {
      $comments = $this->db->resultSet();

    } catch (Exception $e) {
      $err = true;
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }

    if (count($comments) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 2; // error code
      $returnvalue['data'] = false; // returned data is false
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    } else {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // no error code
      $returnvalue['data'] = $comments; // returned data is false
      $returnvalue['count'] = count($comments); // returned count of datasets

      return $returnvalue;

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

  public function addComment($content, $user_id, $idea_id = 0, $parent_id = 0, $status = 1, $updater_id = 0, $language_id = 0)
  {
    /* adds a new comment and returns insert id (comment id) if successful, accepts the above parameters
    content is the comment itself
    parent_id is the id of the comment this refers to (another comment)
    status = status of the comment (0=inactive, 1=active, 2=
    ed, 3=reported, 4=archived 5= in review)
    updater id specifies the id of the user (i.e. admin) that added this comment
    */
    //sanitize the vars
    $content = trim($content);

    $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
    if (!(is_int($idea_id) && intval($idea_id) == 0)) {
      // only check for target id if it is not set to 0
      $idea_id = $this->converters->checkIdeaId($idea_id); // checks id and converts id to db id if necessary (when hash id was passed)
    }
    if (!(intval($parent_id) == 0)) {
      $parent_id = $this->converters->checkCommentId($parent_id); // check id and converts id to db id if necessary (when hash id was passed)
    }
    if (!(intval($user_id) == 0)) {
      $user_id = $this->converters->checkUserId($user_id); // check id and converts id to db id if necessary (when hash id was passed)
    }

    $status = intval($status);

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_comments . ' (sum_likes, user_id, content, idea_id, parent_id, status, hash_id, created, last_update, updater_id, language_id) VALUES (0, :user_id, :content, :idea_id, :parent_id, :status, :hash_id, NOW(), NOW(), :updater_id, :language_id)');
    // bind all VALUES

    $this->db->bind(':user_id', $user_id);
    $this->db->bind(':content', $this->crypt->encrypt($content));
    $this->db->bind(':idea_id', $idea_id);
    $this->db->bind(':parent_id', $parent_id);
    $this->db->bind(':status', $status);
    $this->db->bind(':language_id', $language_id);

    // generate unique hash for this idea
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($content . $appendix); // create hash id for this comment
    $this->db->bind(':hash_id', $hash_id);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $err = false; // set error variable to false
    $insertid = 0; // init

    try {
      $action = $this->db->execute(); // do the query
      $insertid = intval($this->db->lastInsertId());


    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET sum_comments = sum_comments + 1 WHERE id = :idea_id');
      $this->db->bind(':idea_id', $idea_id);
      $action = $this->db->execute(); // do the query

      $this->syslog->addSystemEvent(0, "Added new comment (#" . $insertid . ") user: " . $user_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $insertid; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue; // return insert id to calling script

    } else {
      $this->syslog->addSystemEvent(1, "Error adding comment for user " . $user_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }


  }// end function

  public function getLikeStatus($user_id, $comment_id)
  {
    /* Checks if user (user_id) has already liked a specific comment (comment_id)
    returns 0 if not, returns 1 if yes
    */

    $stmt = $this->db->query('SELECT id FROM ' . $this->db->au_likes . ' WHERE user_id = :user_id AND object_id = :comment_id AND object_type = 2'); // object type = 2 = comment
    $this->db->bind(':user_id', $user_id); // bind user id
    $this->db->bind(':comment_id', $comment_id); // bind comment id

    $likes = $this->db->resultSet();

    if (count($likes) < 1) {
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // db error code
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

  protected function addLikeUser($user_id, $comment_id)
  {
    // helper function to CommentAddLike () => add a like into like table for a certain user and idea

    $stmt = $this->db->query('INSERT INTO ' . $this->db->au_likes . ' (object_type, status, user_id, object_id, last_update, created, hash_id) VALUES (2, 1, :user_id, :comment_id, NOW(), NOW(), :hash_id)');
    // bind all VALUES
    $this->db->bind(':comment_id', $comment_id); // idea id
    $this->db->bind(':user_id', $user_id); // user id
    // generate unique hash for this vote
    $testrand = rand(100, 10000000);
    $appendix = microtime(true) . $testrand;
    $hash_id = md5($user_id . $comment_id . $appendix); // create hash id for this vote
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

  public function CommentAddLike($comment_id, $user_id)
  {
    /* edits a comment and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     Adds a like to a comment, increments sum_likes of a specific comment to a specific value (likes)

    */
    $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)

    // Check if user liked already
    if ($this->getLikeStatus($user_id, $comment_id)['data'] == 1) {
      // user has already liked, return without incrementing vote
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 3; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      // add like to db
      $this->addLikeUser($user_id, $comment_id);
    }
    $stmt = $this->db->query('UPDATE ' . $this->db->au_comments . ' SET sum_likes = sum_likes + 1, last_update= NOW() WHERE id= :comment_id');
    // bind all VALUES
    $this->db->bind(':comment_id', $comment_id); // idea that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Comment  " . $comment_id . " incremented likes", 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error incrementing likes from comment ".$comment_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function removeLikeUser($user_id, $comment_id)
  {
    // helper to function CommentReoveLike () => remove a like from a user for a comment

    // get vote value for this user on this idea

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_likes . ' WHERE user_id = :user_id AND object_id = :comment_id AND object_type=2');
    // bind all VALUES

    $this->db->bind(':comment_id', $comment_id); // comment id
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

  public function CommentRemoveLike($comment_id, $user_id)
  {
    /* edits a comment and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     Removes like to a comment, decrements sum_likes of a specific comment to a specific value (likes)

    */
    $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

    if ($this->getLikeStatus($user_id, $comment_id)['data'] == 0) {
      // user has already liked, return without incrementing vote
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 0; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      // add like to db
      $this->removeLikeUser($user_id, $comment_id);
    }

    $stmt = $this->db->query('UPDATE ' . $this->db->au_comments . ' SET sum_likes = sum_likes - 1, last_update= NOW() WHERE id= :comment_id');
    // bind all VALUES
    $this->db->bind(':comment_id', $comment_id); // comment that is updated

    $err = false; // set error variable to false

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $this->syslog->addSystemEvent(0, "Comment  " . $comment_id . " decrementing likes", 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = 1; // returned data
      $returnvalue['count'] = 1; // returned count of datasets

      return $returnvalue;
    } else {
      //$this->syslog->addSystemEvent(1, "Error decrementing likes from comment ".$comment_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets

      return $returnvalue;
    }
  }// end function

  public function setCommentStatus($comment_id, $status, $updater_id = 0)
  {
    /* edits a comment and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     status = status of comment (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $stmt = $this->db->query('UPDATE ' . $this->db->au_comments . ' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :comment_id');
    // bind all VALUES
    $this->db->bind(':status', $status);
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

    $this->db->bind(':comment_id', $comment_id); // comment that is updated

    $err = false; // set error variable to false
    $count_datasets = 0; // init row count

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $count_datasets = intval($this->db->rowCount());
      $this->syslog->addSystemEvent(0, "Comment status changed " . $comment_id . " by " . $updater_id, 0, "", 1);
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

  public function isOwner($user_id, $comment_id)
  {
    $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)
    $stmt = $this->db->query('SELECT ' . $this->db->au_comments . '.user_id FROM '  . $this->db->au_comments . '  WHERE ' . $this->db->au_comments . '.id = :id');
    $this->db->bind(':id', $comment_id); // bind topic id
    $comments = $this->db->resultSet();

    if (count($comments) > 0) {
      return $comments[0]['user_id'] == $user_id;
    } else {
      return false;
    }
  }


  public function editComment($comment_id, $content, $status = 1, $updater_id = 0)
  {
    /* edits a comment and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
     content = content  of comment
     updater_id is the id of the user that does the update (i.E. admin )
    */
    $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

    $content = trim($content);
    $stmt = $this->db->query('UPDATE ' . $this->db->au_comments . ' SET content= :content, last_update= NOW(), status= :status, updater_id= :updater_id WHERE id= :comment_id');
    // bind all VALUES
    $this->db->bind(':content', $this->crypt->encrypt($content));
    $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)
    $this->db->bind(':status', $status); // id of the user doing the update (i.e. admin)

    $this->db->bind(':comment_id', $comment_id); // comment that is updated

    $err = false; // set error variable to false
    $count_datasets = 0; // init row count

    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {
      $count_datasets = intval($this->db->rowCount());
      $this->syslog->addSystemEvent(0, "Comment content changed " . $comment_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $count_datasets; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets


      return $returnvalue; // return number of affected rows to calling script
    } else {
      $this->syslog->addSystemEvent(0, "Error while changing content of Comment" . $comment_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets

      return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
    }
  }// end function


  public function deleteComment($comment_id, $updater_id = 0)
  {
    /* deletes comments, accepts comment_id (hash (varchar) or db id (int))

    */
    $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db  id if necessary (when hash id was passed)

    $stmt = $this->db->query('SELECT idea_id FROM ' . $this->db->au_comments . ' WHERE id = :id');
    $this->db->bind(':id', $comment_id);
    $action = $this->db->execute(); // do the query

    $result = $this->db->resultSet();
    $idea_id = $result[0]['idea_id'];

    $stmt = $this->db->query('DELETE FROM ' . $this->db->au_comments . ' WHERE id = :id');
    $this->db->bind(':id', $comment_id);

    $err = false;
    try {
      $action = $this->db->execute(); // do the query

    } catch (Exception $e) {

      $err = true;
    }
    if (!$err) {

      $stmt = $this->db->query('UPDATE ' . $this->db->au_ideas . ' SET sum_comments = sum_comments - 1 WHERE id = :idea_id');
      $this->db->bind(':idea_id', $idea_id);
      $action = $this->db->execute(); // do the query

      $count_datasets = intval($this->db->rowCount());
      $this->syslog->addSystemEvent(0, "Comment deleted, id=" . $comment_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = true; // set return value
      $returnvalue['error_code'] = 0; // error code
      $returnvalue['data'] = $count_datasets; // returned data
      $returnvalue['count'] = $count_datasets; // returned count of datasets


      return $returnvalue; // return number of affected rows to calling script
    } else {
      $this->syslog->addSystemEvent(1, "Error deleting comment with id " . $comment_id . " by " . $updater_id, 0, "", 1);
      $returnvalue['success'] = false; // set return value
      $returnvalue['error_code'] = 1; // error code
      $returnvalue['data'] = false; // returned data
      $returnvalue['count'] = 0; // returned count of datasets


      return $returnvalue; // return success = false and error code = 1 to indicate that there was an db error executing the statement
    }

  }// end function

} // end class
?>
