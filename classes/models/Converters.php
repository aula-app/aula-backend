<?php
// only process script if variable $allowed_include is set to 1, otherwise exit
// this prevents direct call of this script

if ($allowed_include==1){

}else {
  exit;
}



class Converters {

    private $db;

    public function __construct($db) {
        // db = database class, crypt = crypt class, $user_id_editor = user id that calls the methods (i.e. admin)
        $this->db = $db;

    }// end function

    protected function buildCacheHash ($key) {
        return md5 ($key);
    }


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

    public function checkUserId ($user_id) {
      /* helper function that checks if a user id is a standard db id (int) or if a hash userid was passed
      if a hash was passed, function gets db user id and returns db id
      */

      if (is_int(intval ($user_id)))
      {
        return $user_id;
      } else
      {

        return $this->getUserIdByHashId ($user_id);
      }
    } // end function


    public function checkCommentId ($comment_id) {
      /* helper function that checks if a comment id is a standard db id (int) or if a hash was passed
      if a hash was passed, function gets db id and returns db id
      */

      if (is_int(intval ($comment_id)))
      {
        return $comment_id;
      } else
      {

        return $this->getCommentIdByHashId ($comment_id);
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

    public function getCommentIdByHashId($hash_id) {
      /* Returns Database ID of comment when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_comments.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind comment id
      $comments = $this->db->resultSet();
      if (count($comments)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $comments[0]['id']; // return id
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
      /* Returns Database ID of topic when hash_id is provided
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

    public function getMessageIdByHashId($hash_id) {
      /* Returns Database ID of Message when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_messages.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $messages = $this->db->resultSet();
      if (count($messages)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $messages[0]['id']; // return message id
      }
    }// end function


    public function checkGroupId ($group_id) {
      /* helper function that checks if a group id is a standard db id (int) or if a hash group id was passed
      if a hash was passed, function gets db group id and returns db id
      */

      if (is_int(intval ($group_id)))
      {
        return $group_id;
      } else
      {
        return $this->getGroupIdByHashId ($group_id);
      }
    } // end function

    public function getGroupIdByHashId($hash_id) {
        /* Returns Database ID of group when hash_id is provided
        */

        $stmt = $this->db->query('SELECT id FROM '.$this->db->au_groups.' WHERE hash_id = :hash_id');
        $this->db->bind(':hash_id', $hash_id); // bind hash id
        $groups = $this->db->resultSet();
        if (count($groups)<1){
          return 0; // nothing found, return 0 code
        }else {
          return $groups[0]['id']; // return group id
        }
      }// end function


    public function checkIdeaId ($idea_id) {
      /* helper function that checks if a idea id is a standard db id (int) or if a hash idea id was passed
      if a hash was passed, function gets db idea id and returns db id
      */

      if (is_int(intval ($idea_id)))
      {
        return $idea_id;
      } else
      {
        return $this->getIdeaIdByHashId ($idea_id);
      }
    } // end function

    public function checkRoomId ($room_id) {
      /* helper function that checks if a room id is a standard db id (int) or if a hash room id was passed
      if a hash was passed, function gets db room id and returns db id
      */

      if (is_int(intval ($room_id)))
      {
        return $room_id;
      } else
      {

        return $this->getRoomIdByHashId ($room_id);
      }
    } // end function

    public function checkTopicId ($topic_id) {
      /* helper function that checks if a topic id is a standard db id (int) or if a hash topic id was passed
      if a hash was passed, function gets db topic id and returns db id
      */

      if (is_int(intval ($topic_id)))
      {
        return $topic_id;
      } else
      {
        return $this->getTopicIdByHashId ($topic_id);
      }
    } // end function

    public function checkMessageId ($message_id) {
      /* helper function that checks if a message id is a standard db id (int) or if a hash id was passed
      if a hash was passed, function returns db id
      */

      if (is_int(intval ($message_id)))
      {
        return $message_id;
      } else
      {
        return $this->getMessageIdByHashId ($message_id);
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

    public function checkCategoryId ($category_id) {
      /* helper function that checks if a topic id is a standard db id (int) or if a hash topic id was passed
      if a hash was passed, function gets db topic id and returns db id
      */

      if (is_int($category_id))
      {
        return $category_id;
      } else
      {
        return $this->getTopicIdByHashId ($category_id);
      }
    } // end function

    public function getCategoryIdByHashId($hash_id) {
      /* Returns Database ID of category when hash_id is provided
      */

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_categories.' WHERE hash_id = :hash_id');
      $this->db->bind(':hash_id', $hash_id); // bind hash id
      $categories = $this->db->resultSet();
      if (count($categories)<1){
        return 0; // nothing found, return 0 code
      }else {
        return $categories[0]['id']; // return category id
      }
    }// end function

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

    public function checkCategoryExist($category_id) {
      /* returns 0 if category does not exist, 1 if category exists, accepts database id (int)
      */
      $category_id = $this->checkCategoryId($category_id); // checks topic id and converts topic id to db topic id if necessary (when topic hash id was passed)

      $stmt = $this->db->query('SELECT status FROM '.$this->db->au_categories.' WHERE id = :id');
      $this->db->bind(':id', $category_id); // bind topic id
      $categories = $this->db->resultSet();
      if (count($categories)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // topic found, return 1
      }
    } // end function

  public function checkUserExist($user_id) {
      /* helper function to check if a user with a certain id exists, returns 0 if user does not exist, 1 if user exists, accepts database (int) or hash id (varchar)
      */
      $user_id = $this->checkUserId($user_id); // checks user id and converts user id to db user id if necessary (when user hash id was passed)

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_users_basedata.' WHERE id = :id');
      $this->db->bind(':id', $user_id); // bind userid
      $users = $this->db->resultSet();
      if (count($users)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // user found, return 1
      }
    } // end function

    public function checkGroupExist($group_id) {
      /* returns 0 if group does not exist, 1 if group exists, accepts databse id (int)
      */
      $group_id = $this->checkGroupId($group_id); // checks group id and converts group id to db group id if necessary (when group hash id was passed)

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_groups.' WHERE id = :id');
      $this->db->bind(':id', $group_id); // bind group id
      $groups = $this->db->resultSet();
      if (count($groups)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // group found, return 1
      }
    } // end function

    public function checkRoomExist($room_id) {
      /* returns 0 if room does not exist, 1 if room exists, accepts databse id (int)
      */
      $room_id = $this->checkRoomId($room_id); // checks room id and converts room id to db room id if necessary (when room hash id was passed)

      $stmt = $this->db->query('SELECT id FROM '.$this->db->au_rooms.' WHERE id = :id');
      $this->db->bind(':id', $room_id); // bind room id
      $rooms = $this->db->resultSet();
      if (count($rooms)<1){
        return 0; // nothing found, return 0 code
      }else {
        return 1; // room found, return 1
      }
    } // end function

    public function checkIdeaExist($idea_id) {
      /* returns 0 if idea does not exist, 1 if idea exists, accepts database id (int)
      */
      $idea_id = $this->checkIdeaId($idea_id); // checks idea id and converts idea id to db idea id if necessary (when idea hash id was passed)

      $stmt = $this->db->query('SELECT status, room_id FROM '.$this->db->au_ideas.' WHERE id = :id');
      $this->db->bind(':id', $idea_id); // bind idea id
      $ideas = $this->db->resultSet();
      print_r ($ideas);
      if (count($ideas)<1){
        $ideas ['status'] = 0;
        $ideas ['room_id'] = 0;

        return $ideas[0]; // nothing found, return 0 code
      }else {
        return $ideas[0];
      }
    } // end function


} // end class
?>
