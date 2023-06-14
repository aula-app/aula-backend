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



$vote_value = 1;
if (isset ($_REQUEST['votevalue']))
{
  $vote_value = intval ($_REQUEST['votevalue']);
}

$like_value = 1;
if (isset ($_REQUEST['likevalue']))
{
  $like_value = intval ($_REQUEST['likevalue']);
}

$idea_id = 0;

if (isset ($_REQUEST['vote']))
{
  $idea_id = intval ($_REQUEST['vote']);
  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg.= ("<br>VOTING FOR idea: ".$idea_id.", user id: ".$_SESSION ['user_id']." value:".$vote_value);
  $return_value = $idea->voteForIdea ($idea_id, $vote_value, $_SESSION ['user_id']);
  $msg.= ("<br>Returning value: ".$return_value);
} // end if

if (isset ($_REQUEST['reset']))
{
  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg.= ("<br>Resetting votes and likes");
  $return_value = $idea->resetVotes ();
  $msg.= ("<br>Returning value: ".$return_value);
} // end if

if (isset ($_REQUEST['like']))
{
  $idea_id = intval ($_REQUEST['like']);
  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg.= ("<br>VOTING FOR idea: ".$idea_id.", user id: ".$_SESSION ['user_id']." value:".$like_value);
  if ($like_value==0) {
    //unlike
    $return_value = $idea->IdeaRemoveLike ($idea_id, $_SESSION ['user_id']);

  } else {
    $return_value = $idea->IdeaAddLike ($idea_id, $_SESSION ['user_id']);
  }
  $msg.= ("<br>Returning value: ".$return_value);
} // end if

?>
<html>
<body>

<?php

$offset = 0; // set start at dataset #10
$limit = 5; // get 10 datasets
echo ("<h2>Reading 5 idea datasets (".$room->getNumberOfUsers(4).")</h2>");
echo ("<i>(User 6 has a delegation from user 10 = double vote weight for user 6)</i>");
?>

<body style="font-family: arial;font-size:1.2em;">
  <div style="width:100%;background-color:#e9e9e9; padding:10px;">
    <span style="color:#ff0000; font-weight:bold;"><?php echo $msg ?>&nbsp;</span>
    <br>Set User id <form method ="_POST"><input type='text' name='user' value='<?php echo $_SESSION ['user_id'] ?>'><button type=submit name='submitter'>SET USER</button></form>
    <br><form method ="_POST"><button type=submit name='reset'>RESET VOTES AND LIKES</button></form>
  </div>
  <div id ="content_pane" style="">
<?php
$ideadata = $idea->getIdeas($offset, $limit, 4, 1, 1);
// idea list:
foreach ($ideadata as $result) {
    echo ('<form action="" method ="_POST">');
    echo ("<h2>".$result['id'].".". $crypt->decrypt ($result['content'])."</h2>");
    echo ("<b>Sum Votes: " . ($result['sum_votes']).", Sum Likes: " . ($result['sum_likes'])."</b>");
    echo ("<br><small>Last Update: " . $result['last_update']."</small>");
    echo ("<input type='hidden' name='vote' value='". intval (trim ($result['id']))."'><input type='hidden' name='votevalue' value='1'><br><button type=submit name='submitter'>VOTE for</button></form><form method ='_POST'><input type='hidden' name='vote' value='". intval (trim ($result['id']))."'><input type='hidden' name='votevalue' value='-1'><button type=submit name='submitter'>VOTE against</button></form>");
    echo ('<form action="" method ="_POST">');
    echo ("<input type='hidden' name='like' value='". intval (trim ($result['id']))."'><input type='hidden' name='likevalue' value='1'><button type=submit name='submitter'>LIKE</button></form><form method ='_POST'><input type='hidden' name='like' value='". intval (trim ($result['id']))."'><input type='hidden' name='likevalue' value='0'><button type=submit name='submitter'>UNLIKE</button></form>");
    // User id <input type='text' name='user' value='". $user_id."'>
    echo ("</form>");
}

?>
</div>
</body>
</html>
