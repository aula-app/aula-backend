<?php
session_start();
require_once ('base_config.php'); // load base config with paths to classes etc.
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
  $msg.= ("<br>Adding message: ".$headline." to DB");
  // addMessage ($headline, $body, $msg_type, $publish_date, $creator_id=0, $target_group=0, $target_id=0, $pin_to_top=0, $level_of_detail=1, $only_on_dashboard=0, $status=1, $room_id=0, $updater_id=0)
  $return_value = $message->addMessage ($headline, $body, 1, $publish_date, 44);
  $msg.= ("<br>Returning value: ".$return_value);
} // end if

if (isset ($_REQUEST['delete']))
{
  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg_id = $_REQUEST['id'];
  $msg.= ("<br>Deleting message #".$msg_id);
  $return_value = $message->deleteMessage ($msg_id);
  $msg.= ("<br>Returning value: ".$return_value);
} // end if



?>
<html>
<body>

<?php

$offset = 0; // set start at dataset #10
$limit = 50; // get 10 datasets
echo ("<h2>Reading 5 message datasets </h2>");

?>

<body style="font-family: arial;font-size:1.2em;">
  <div style="width:100%;background-color:#e9e9e9; padding:10px;">
    <span style="color:#ff0000; font-weight:bold;"><?php echo $msg ?>&nbsp;</span>
    <br>Add message
    <form method ="_POST">
      <input type='text' name='headline' value='<?php echo $headline ?>'>
      <textarea name ="body"></textarea>
      <input type='text' name='publish_date' value='<?php echo $publish_date ?>'>

      <button type=submit name='addmsg'>ADD MESSAGE</button>
    </form>

  </div>
  <div id ="content_pane" style="">
<?php
// getMessages ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="", $publish_date, $target_group, $room_id)
$messagedata = $message->getMessages ($offset, $limit, 4, 1, 1);
//print_r ($messagedata);
$data = $messagedata ['data'];
// idea list:
out ("found ".$messagedata['count']." messages!");
foreach ($data as $result) {
  //  $votes_made = $idea->getIdeaNumberVotes($result['id']);
    echo ('<form action="" method ="_POST">');
    echo ("<h2>".$result['id'].".". $crypt->decrypt ($result['headline'])."</h2>");
    echo ("<b>Body: " . $crypt->decrypt ($result['body']).", publish date: " . ($result['publish_date']));
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

?>
</div>
</body>
</html>
