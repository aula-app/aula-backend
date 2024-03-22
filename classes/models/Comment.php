<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}



class Comment {

    private $db;

    public function __construct($db, $crypt, $syslog) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;
        $this->crypt = $crypt;
        //$this->syslog = new Systemlog ($db);
        $this->syslog = $syslog;
        $this->converters = new Converters ($db);
    }// end function

    protected function buildCacheHash ($key) {
        return md5 ($key);
      }


    public function getCommentHashId($comment_id) {
      /* returns hash_id of a comment for a integer id
      */
      $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT hash_id FROM '.$this->db->au_comments.' WHERE id = :id');
      $this->db->bind(':id', $comment_id); // bind comment id
      $comments = $this->db->resultSet();
      if (count($comments)<1){
        return "0,0"; // nothing found, return 0 code
      }else {
        return "1,".$comments[0]['hash_id']; // return hash id
      }
    }// end function

    public function getCommentsByIdeaId ($idea_id, $offset=0, $limit=0, $orderby=3, $asc=0, $status=1) {
      /* returns comments list (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      $status (int) 0=inactive, 1=active, 2=suspended, 3=archived, defaults to active (1)
      $room_id is the id of the room
      */
      $idea_id = $this->converters->checkIdeaId($idea_id); // checks id and converts id to db id if necessary (when hash id was passed)

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
        $orderby_field = "publish_date";
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
      $where = ' WHERE '.$this->db->au_comments.'.status= :status AND idea_id= :idea_id ';
      $stmt = $this->db->query('SELECT '.$this->db->au_comments.'.*, '.$this->db->au_users_basedata.'.username FROM '.$this->db->au_comments.' JOIN '.$this->db->au_users_basedata.' ON '.$this->db->au_comments.'.user_id = '.$this->db->au_users_basedata.'.id '.$where.' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status
      $this->db->bind(':idea_id', $idea_id); // bind idea id

      $err=false;
      try {
        $comments = $this->db->resultSet();

      } catch (Exception $e) {
          $err=true;
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data is false
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
      }

      if (count($comments)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data is false
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] =0; // no error code
        $returnvalue ['data'] = $comments; // returned data is false
        $returnvalue ['count'] = count ($comments); // returned count of datasets

        return $returnvalue;

      }
    }// end function


    public function archiveComment ($comment_id, $updater_id=0){
      /* sets the status of a comment to 4 = archived
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setCommentStatus($comment_id, 4, $updater_id);

    }

    public function activateComment ($comment_id, $updater_id=0){
      /* sets the status of a comment to 1 = active
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setCommentStatus($comment_id, 1, $updater_id);

    }

    public function deactivateComment ($comment_id, $updater_id){
      /* sets the status of a comment to 0 = inactive
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setCommentStatus($comment_id, 0, $updater_id);
    }

    public function suspendComment ($comment_id, $updater_id){
      /* sets the status of a comment to 2 = suspended
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setCommentStatus($comment_id, 2, $updater_id);
    }

    public function setCommenttoReview ($comment_id, $updater_id){
      /* sets the status of a comment to 5 = to review
      accepts db id and hash id
      updater_id is the id of the user that did the update
      */
      $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

      return $this->setCommentStatus($comment_id, 5, $updater_id);

    }

    public function getCommentBaseData ($comment_id) {
      /* returns comment base data for a specified db id */
      $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_comments.' WHERE id = :id');
      $this->db->bind(':id', $comment_id); // bind comment id
      $comments = $this->db->resultSet();
      if (count($comments)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // no error code
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue; // nothing found, return 0 code
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // no error code
        $returnvalue ['data'] = $comments[0]; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue; // return an array (associative) with all the data
      }
    }// end function

    public function getCommentsByUser ($user_id, $publish_date=0, $idea_id=0){
      // returns all comments for this specific user
      return getComments (0, 0, 3, 0, 1, "", $publish_date, $idea_id, 0, $user_id);
    }

    public function getCommentsByParent ($user_id, $publish_date=0, $parent_id=0){
      // returns all comments for this specific user
      return getComments (0, 0, 3, 0, 1, "", $publish_date, 0, $parent_id, $user_id);
    }

    public function getCommentsToReview ($user_id=0, $publish_date=0, $idea_id=0, $parent_idea=0){
      // returns all comments for this specific user
      return getComments (0, 0, 3, 0, 5, "", $publish_date, 0, $parent_id, $user_id);
    }
    public function getSuspendedComments ($user_id=0, $publish_date=0, $idea_id=0, $parent_idea=0){
      // returns all comments for this specific user
      return getComments (0, 0, 3, 0, 2, "", $publish_date, 0, $parent_id, $user_id);
    }

  public function getComments ($offset=0, $limit=0, $orderby=3, $asc=0, $status=1, $extra_where="", $last_update=0, $idea_id=0, $parent_id=0, $user_id=0) {
      /* returns comments list (associative array) with start and limit provided
      if start and limit are set to 0, then the whole list is read (without limit)
      orderby is the field (int, see switch), defaults to last_update (3)
      asc (smallint), is either ascending (1) or descending (0), defaults to descending
      status (int) 0=inactive, 1=active, 2=suspended, 3=archived, 5= in review defaults to active (1)
      last_update = date that specifies comments younger than last_update date (if set to 0, gets all comments)
      extra_where = extra parameters for where clause, synthax " AND XY=4"
      user_id = specifies a certain user (comments by him) if set to 0 all users are included
      idea_id specifies a certain idea that the comments refer to
      parent_id refer to a parent comment
      */

      // init return array
      $returnvalue ['success'] = false; // success (true) or failure (false)
      $returnvalue ['errorcode'] = 0; // error code
      $returnvalue ['data'] = false; // the actual data
      $returnvalue ['count_data'] = 0; // number of datasets

      $date_now = date('Y-m-d H:i:s');
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
      if ($target_group > 0){
        // if a target group is set then add to where clause
        $extra_where.= " AND target_group = ".$target_group;
      }

      if ($user_id > 0){
        // if a target user id is set then add to where clause
        $extra_where.= " AND target_id = ".$user_id; // get specific comments to this user
      } else {
        $extra_where.= " AND target_id = 0"; // get all comments
      }

      if ($parent_id > 0){
        // if a target group is set then add to where clause
        $extra_where.= " AND parent_id = ".$parent_id;
      }

      if ($idea_id > 0){
        // if a room id is set then add to where clause
        $extra_where.= " AND idea_id = ".$idea_id;
      }

      if (!(intval ($last_update)==0)){
        // if a publish date is set then add to where clause
        $extra_where.= " AND last_update > \'".$last_update."\'";
      }

      switch (intval ($orderby)){
        case 0:
        $orderby_field = "status";
        break;
        case 1:
        $orderby_field = "parent_id";
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
        $orderby_field = "content";
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

      $count_datasets = 0; // number of datasets retrieved

      $stmt = $this->db->query('SELECT * FROM '.$this->db->au_comments.' WHERE status= :status '.$extra_where.' AND publish_date <= \''.$date_now.'\' ORDER BY '.$orderby_field.' '.$asc_field.' '.$limit_string);
      if ($limit){
        // only bind if limit is set
        $this->db->bind(':offset', $offset); // bind limit
        $this->db->bind(':limit', $limit); // bind limit
      }
      $this->db->bind(':status', $status); // bind status

      $err=false;
      try {
        $comments = $this->db->resultSet();


      } catch (Exception $e) {
          $err=true;
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // database error code
          $returnvalue ['data'] = false; // returned data is false
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
      }
      $count_datasets = count ($comments);

      if ($count_datasets<1){
        $returnvalue['success'] = true; // set success value
        $returnvalue['error_code'] = 2; // no data found
        $returnvalue ['data'] = false; // returned data is false
        $returnvalue ['count'] = $count_datasets; // returned count of datasets

        return $returnvalue; // nothing found, return 0 code
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // no error code
        $returnvalue ['data'] = $comments; // returned data
        $returnvalue ['count'] = $count_datasets; // returned count of datasets

        return $returnvalue; // return an array (associative) with all the data
      }
    }// end function

    private function makeBool ($val){
      // helper function that converts ints to bool ints, sanitizes values
      $val = intval ($val);
      if ($val > 1) {
        $val = 1;
      }
      if ($val < 1) {
        $val = 0;
      }
      return $val;
    }

    public function addComment ($content, $user_id, $idea_id=0, $parent_id=0, $status=1, $updater_id=0, $language_id=0) {
        /* adds a new comment and returns insert id (comment id) if successful, accepts the above parameters
        content is the comment itself
        parent_id is the id of the comment this refers to (another comment)
        status = status of the comment (0=inactive, 1=active, 2=
        ed, 3=reported, 4=archived 5= in review)
        updater id specifies the id of the user (i.e. admin) that added this comment
        */
        //sanitize the vars
        $content = trim ($content);

        $updater_id = $this->converters->checkUserId($updater_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)
        if (!(intval ($idea_id)==0)){
          // only check for target id if it is not set to 0
          $idea_id = $this->converters->checkIdeaId($idea_id); // checks id and converts id to db id if necessary (when hash id was passed)
        }
        if (!(intval ($parent_id)==0)){
          $parent_id = $this->converters->checkCommentId($parent_id); // check id and converts id to db id if necessary (when hash id was passed)
        }
        if (!(intval ($user_id)==0)){
          $user_id = $this->converters->checkUserId($user_id); // check id and converts id to db id if necessary (when hash id was passed)
        }

        $status = intval($status);

        $stmt = $this->db->query('INSERT INTO '.$this->db->au_comments.' (sum_likes, user_id, content, idea_id, parent_id, status, hash_id, created, last_update, updater_id, language_id) VALUES (0, :user_id, :content, :idea_id, :parent_id, :status, :hash_id, NOW(), NOW(), :updater_id, :language_id)');
        // bind all VALUES

        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':content', $this->crypt->encrypt($content));
        $this->db->bind(':idea_id', $idea_id);
        $this->db->bind(':parent_id', $parent_id);
        $this->db->bind(':status', $status);
        $this->db->bind(':language_id', $language_id);

        // generate unique hash for this idea
        $testrand = rand (100,10000000);
        $appendix = microtime(true).$testrand;
        $hash_id = md5($content.$appendix); // create hash id for this comment
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

          $this->syslog->addSystemEvent(0, "Added new comment (#".$insertid.") user: ".$user_id, 0, "", 1);
          $returnvalue ['success'] = true; // set return value
          $returnvalue ['error_code'] = 0; // error code
          $returnvalue ['data'] = $insertid; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue; // return insert id to calling script

        } else {
          $this->syslog->addSystemEvent(1, "Error adding comment for user ".$user_id, 0, "", 1);
          $returnvalue ['success'] = false; // set return value
          $returnvalue ['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
        }


    }// end function

    public function getLikeStatus ($user_id, $comment_id) {
      /* Checks if user (user_id) has already liked a specific comment (comment_id)
      returns 0 if not, returns 1 if yes
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_likes.' WHERE user_id = :user_id AND object_id = :comment_id AND object_type = 2'); // object type = 2 = comment
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':comment_id', $comment_id); // bind comment id

      $likes = $this->db->resultSet();

      if (count($likes)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // db error code
        $returnvalue ['data'] = 0; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }else {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // db error code
        $returnvalue ['data'] = 1; // returned data
        $returnvalue ['count'] = 1; // returned count of datasets

        return $returnvalue;
      }
    }// end function

    protected function addLikeUser ($user_id, $comment_id) {
      // add a like into like table for a certain user and idea

      $stmt = $this->db->query('INSERT INTO '.$this->db->au_likes.' (object_type, status, user_id, object_id, last_update, created, hash_id) VALUES (2, 1, :user_id, :comment_id, NOW(), NOW(), :hash_id)');
      // bind all VALUES
      $this->db->bind(':comment_id', $comment_id); // idea id
      $this->db->bind(':user_id', $user_id); // user id
      // generate unique hash for this vote
      $testrand = rand (100,10000000);
      $appendix = microtime(true).$testrand;
      $hash_id = md5($user_id.$comment_id.$appendix); // create hash id for this vote
      $this->db->bind(':hash_id', $hash_id); // hash id

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query

      } catch (Exception $e) {

          $err=true;
      }
      if (!$err)
      {
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
    }

    public function CommentAddLike ($comment_id, $user_id) {
        /* edits a comment and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         Adds a like to an idea, increments sum_likes of a specific comment to a specific value (likes)

        */
        $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)
        $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)

        // Check if user liked already
        if ($this->getLikeStatus($user_id, $comment_id)['data']==1){
          // user has already liked, return without incrementing vote
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 3; // error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;
        }
        else {
          // add like to db
          $this->addLikeUser ($user_id, $comment_id);
        }
        $stmt = $this->db->query('UPDATE '.$this->db->au_comments.' SET sum_likes = sum_likes + 1, last_update= NOW() WHERE id= :comment_id');
        // bind all VALUES
        $this->db->bind(':comment_id', $comment_id); // idea that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Comment  ".$comment_id." incremented likes", 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;
        } else {
          //$this->syslog->addSystemEvent(1, "Error incrementing likes from comment ".$comment_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function removeLikeUser ($user_id, $comment_id) {
      // add a vote into vote table for a certain user and idea

      // get vote value for this user on this idea

      $stmt = $this->db->query('DELETE FROM '.$this->db->au_likes.' WHERE user_id = :user_id AND object_id = :comment_id AND object_type=2');
      // bind all VALUES

      $this->db->bind(':comment_id', $comment_id); // comment id
      $this->db->bind(':user_id', $user_id); // user id

      $err=false; // set error variable to false

      try {
        $action = $this->db->execute(); // do the query
        $rows = intval($this->db->rowCount());

      } catch (Exception $e) {

          $err=true;
      }
      if (!$err)
      {
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 0; // error code
        $returnvalue ['data'] = $rows; // returned data
        $returnvalue ['count'] = $rows; // returned count of datasets

        return $returnvalue;

      } else {
        $returnvalue['success'] = false; // set return value
        $returnvalue['error_code'] = 1; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      }
    }

    public function CommentRemoveLike ($comment_id, $user_id) {
        /* edits a comment and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         Adds a like to a comment, increments sum_likes of a specific comment to a specific value (likes)

        */
        $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

        if ($this->getLikeStatus($user_id, $comment_id)['data']==0){
          // user has already liked, return without incrementing vote
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = 0; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;
        }
        else {
          // add like to db
          $this->removeLikeUser ($user_id, $comment_id);
        }

        $stmt = $this->db->query('UPDATE '.$this->db->au_comments.' SET sum_likes = sum_likes - 1, last_update= NOW() WHERE id= :comment_id');
        // bind all VALUES
        $this->db->bind(':comment_id', $comment_id); // comment that is updated

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Comment  ".$comment_id." decrementing likes", 0, "", 1);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;
        } else {
          //$this->syslog->addSystemEvent(1, "Error decrementing likes from comment ".$comment_id, 0, "", 1);
          $returnvalue['success'] = false; // set return value
          $returnvalue['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets

          return $returnvalue;
        }
    }// end function

    public function reportComment ($comment_id, $user_id, $updater_id, $reason =""){
      /* sets the status of an comment to 3 = reported
      accepts db id and hash id of comment
      user_id is the id of the user that reported the comment
      updater_id is the id of the user that did the update
      type = 1 in reported table = comments
      */
      $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)
      $user_id = $this->converters->checkUserId($user_id); // checks id and converts id to db id if necessary (when hash id was passed)

      // check if idea is existent
      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_comments.' WHERE id = :comment_id');
      $this->db->bind(':comment_id', $comment_id); // bind comment id
      $comments = $this->db->resultSet();
      if (count($comments)<1){
        $returnvalue['success'] = true; // set return value
        $returnvalue['error_code'] = 2; // error code
        $returnvalue ['data'] = false; // returned data
        $returnvalue ['count'] = 0; // returned count of datasets

        return $returnvalue;
      } // else continue processing
      // check if this user has already reported this comment
      $stmt = $this->db->query('SELECT object_id FROM '.$this->db->au_reported.' WHERE user_id = :user_id AND type = 1 AND object_id = :comment_id');
      $this->db->bind(':user_id', $user_id); // bind user id
      $this->db->bind(':comment_id', $comment_id); // bind comment id
      $comments = $this->db->resultSet();
      if (count($comments)<1){
        //add this reporting to db
        $stmt = $this->db->query('INSERT INTO '.$this->db->au_reported.' (reason, object_id, type, user_id, status, created, last_update) VALUES (:reason, :comment_id, 1, :user_id, 0, NOW(), NOW())');
        // bind all VALUES

        $this->db->bind(':comment_id', $comment_id);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':reason', $reason);

        $err=false; // set error variable to false

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        $insertid = intval($this->db->lastInsertId());
        if (!$err)
        {
          $this->syslog->addSystemEvent(0, "Added new reporting comment (#".$insertid.") ".$content, 0, "", 1);
          // set idea status to reported
          $this->setCommentStatus($comment_id, 3, $updater_id=0);
          $returnvalue['success'] = true; // set return value
          $returnvalue['error_code'] = 0; // error code
          $returnvalue ['data'] = 1; // returned data
          $returnvalue ['count'] = 1; // returned count of datasets

          return $returnvalue;

        } else {
          //$this->syslog->addSystemEvent(1, "Error reporting comment ".$content, 0, "", 1);
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


    public function setCommentStatus($comment_id, $status, $updater_id = 0) {
        /* edits a comment and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         status = status of comment (0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review)
         updater_id is the id of the user that does the update (i.E. admin )
        */
        $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

        $stmt = $this->db->query('UPDATE '.$this->db->au_comments.' SET status= :status, last_update= NOW(), updater_id= :updater_id WHERE id= :comment_id');
        // bind all VALUES
        $this->db->bind(':status', $status);
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':comment_id', $comment_id); // comment that is updated

        $err=false; // set error variable to false
        $count_datasets = 0; // init row count

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $count_datasets = intval($this->db->rowCount());
          $this->syslog->addSystemEvent(0, "Comment status changed ".$comment_id." by ".$updater_id, 0, "", 1);
          $returnvalue ['success'] = true; // set return value
          $returnvalue ['error_code'] = 0; // error code
          $returnvalue ['data'] = $count_datasets; // returned data
          $returnvalue ['count'] = $count_datasets; // returned count of datasets


          return $returnvalue; // return number of affected rows to calling script
        } else {
          $returnvalue ['success'] = false; // set return value
          $returnvalue ['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = $count_datasets; // returned count of datasets

          return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function

    public function setCommentContent($comment_id, $content, $updater_id = 0) {
        /* edits a comment and returns number of rows if successful, accepts the above parameters, all parameters are mandatory
         content = content  of comment
         updater_id is the id of the user that does the update (i.E. admin )
        */
        $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db id if necessary (when hash id was passed)

        $content = trim ($content);
        $stmt = $this->db->query('UPDATE '.$this->db->au_comments.' SET content= :content, last_update= NOW(), updater_id= :updater_id WHERE id= :comment_id');
        // bind all VALUES
        $this->db->bind(':content', $this->crypt->encrypt ($content));
        $this->db->bind(':updater_id', $updater_id); // id of the user doing the update (i.e. admin)

        $this->db->bind(':comment_id', $comment_id); // comment that is updated

        $err=false; // set error variable to false
        $count_datasets = 0; // init row count

        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $count_datasets = intval($this->db->rowCount());
          $this->syslog->addSystemEvent(0, "Comment content changed ".$comment_id." by ".$updater_id, 0, "", 1);
          $returnvalue ['success'] = true; // set return value
          $returnvalue ['error_code'] = 0; // error code
          $returnvalue ['data'] = $count_datasets; // returned data
          $returnvalue ['count'] = $count_datasets; // returned count of datasets


          return $returnvalue; // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(0, "Error while changing content of Comment".$comment_id." by ".$updater_id, 0, "", 1);
          $returnvalue ['success'] = false; // set return value
          $returnvalue ['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = $count_datasets; // returned count of datasets

          return $returnvalue; // return 0,2 to indicate that there was an db error executing the statement
        }
    }// end function


    public function deleteComment ($comment_id, $updater_id=0) {
        /* deletes comments, accepts comment_id (hash (varchar) or db id (int))

        */
        $comment_id = $this->converters->checkCommentId($comment_id); // checks id and converts id to db  id if necessary (when hash id was passed)

        $stmt = $this->db->query('DELETE FROM '.$this->db->au_comments.' WHERE id = :id');
        $this->db->bind (':id', $comment_id);

        $err=false;
        try {
          $action = $this->db->execute(); // do the query

        } catch (Exception $e) {

            $err=true;
        }
        if (!$err)
        {
          $count_datasets = intval($this->db->rowCount());
          $this->syslog->addSystemEvent(0, "Comment deleted, id=".$comment_id." by ".$updater_id, 0, "", 1);
          $returnvalue ['success'] = true; // set return value
          $returnvalue ['error_code'] = 0; // error code
          $returnvalue ['data'] =  $count_datasets; // returned data
          $returnvalue ['count'] = $count_datasets; // returned count of datasets


          return $returnvalue; // return number of affected rows to calling script
        } else {
          $this->syslog->addSystemEvent(1, "Error deleting comment with id ".$comment_id." by ".$updater_id, 0, "", 1);
          $returnvalue ['success'] = false; // set return value
          $returnvalue ['error_code'] = 1; // error code
          $returnvalue ['data'] = false; // returned data
          $returnvalue ['count'] = 0; // returned count of datasets


          return $returnvalue; // return success = false and error code = 1 to indicate that there was an db error executing the statement
        }

    }// end function

} // end class
?>
