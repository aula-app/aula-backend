<?php
session_start();
require_once (__DIR__ . '/../config/base_config.php'); // load base config with paths to classes etc.
require_once ('error_msg.php');

require ('functions.php'); // include Class autoloader (models)

//load helper classes
require_once ($baseHelperDir.'Crypt.php');

// Create a new Database object with the MySQL credentials
$db = new Database();
$crypt = new Crypt($cryptFile); // path to $cryptFile is currently known from base_config.php -> will be changed later to be secure
$syslog = new Systemlog ($db); // systemlog
$idea = new Idea ($db, $crypt, $syslog); //, $syslog); // instanciate group model class
$room = new Room ($db, $crypt, $syslog); // instanciate room model class
$topic = new Topic ($db, $crypt, $syslog); // instanciate topic model class
$message = new Message ($db, $crypt, $syslog); // instanciate message model class
$comment = new Comment ($db, $crypt, $syslog); // instanciate message model class

function out ($text, $form=false){ // lazy helper function
  $formstart="";
  $formend="";

  if ($form) {
    $formstart="<h2>";
    $formend="</h2>";
  }

  echo ("<br>".$formstart.$text.$formend."<br>");
}

$msg="";

$user_id = 5;
if (!isset ($_SESSION ['user_id'])){
  $_SESSION ['user_id'] = $user_id;
}
if (isset ($_REQUEST['user']))
{
  $msg.= ("<br>USER ID is set to ".intval ($_REQUEST['user']));
  $_SESSION ['user_id'] = intval ($_REQUEST['user']);
}



$headline = "";
$body = "";
$publish_date = date('Y-m-d H:i:s');

$like_value = 1;
if (isset ($_REQUEST['likevalue']))
{
  $like_value = intval ($_REQUEST['likevalue']);
}

if (isset ($_REQUEST['addmsg']))
{
  $headline = $_REQUEST['headline'];
  $body = $_REQUEST['body'];
  $publish_date = $_REQUEST['publish_date'];


  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg.= ("<br>Adding comment: ".$crypt->decrypt ($headline)." to DB");
  // addComment ($content, $user_id, $idea_id=0, $parent_id=0, $status=1, $updater_id=0, $language_id=0)
  $return_value = $comment->addComment ($body, 42, 1, 0);
  $msg.= ("<br>Returning value: ".$return_value ['data']);
} // end if

if (isset ($_REQUEST['delete']))
{
  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg_id = $_REQUEST['id'];
  $msg.= ("<br>Deleting comment #".$msg_id);
  $return_value = $comment->deleteComment ($msg_id);
  $msg.= ("<br>Returning value: ".$return_value);
} // end if



?>
<html>
<body>

<?php

$offset = 0; // set start at dataset #10
$limit = 50; // get 10 datasets
echo ("<h2>Reading 5 comment datasets for idea #1 </h2>");

$idea_id = 1;
$result =  $idea->getIdeaContent ($idea_id)['data'];
echo ("<br>Idea #".$idea_id." ".$crypt->decrypt ($result['displayname']).": ".$crypt->decrypt ($result['content'])." (".$result['created'].")");
?>

<body style="font-family: arial;font-size:1.2em;">
  <div style="width:100%;background-color:#e9e9e9; padding:10px;">
    <span style="color:#ff0000; font-weight:bold;"><?php echo $msg ?>&nbsp;</span>
    <br>Add comment
    <form method ="_POST">
      <textarea name ="body"></textarea>

      <button type=submit name='addmsg'>ADD comment</button>
    </form>

  </div>
  <div id ="content_pane" style="">
<?php
// getMessages ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="", $publish_date, $target_group, $room_id)
$messagedata = $comment->getCommentsByIdeaId ($idea_id);

$data = $messagedata ['data'];
if (!$data) {
  out ("No comments found yet.");
}else {
  // comment list:
  out ("found ".$messagedata['count']." comment(s) for idea ".$idea_id);
  foreach ($data as $result) {
    //  $votes_made = $idea->getIdeaNumberVotes($result['id']);
      echo ('<form action="" method ="_POST">');
      echo ("<h2>".$result['id'].". ".$crypt->decrypt ($result['content'])."</h2>");
      echo ("<br><small>Last Update: " . $result['last_update']."</small>");
      echo ("<input type='hidden' name='id' value='". intval (trim ($result['id']))."'><br><button type=submit name='delete'>DELETE</button></form>");
      /*echo ('<form action="" method ="_POST">');
      echo ("<input type='hidden' name='vote' value='". intval (trim ($result['id']))."'><input type='hidden' name='votevalue' value='0'><br><button type=submit name='submitter'>VOTE NEUTRAL</button>");
      echo ("</form>");
      echo ('<form action="" method ="_POST">');
      echo ("<input type='hidden' name='like' value='". intval (trim ($result['id']))."'><input type='hidden' name='likevalue' value='1'><button type=submit name='submitter'>LIKE</button></form><form method ='_POST'><input type='hidden' name='like' value='". intval (trim ($result['id']))."'><input type='hidden' name='likevalue' value='0'><button type=submit name='submitter'>UNLIKE</button></form>");
      // User id <input type='text' name='user' value='". $user_id."'>
      echo ("</form>");
      */
  }
}
?>
</div>
</body>
</html>
