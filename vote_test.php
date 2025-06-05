<?php
session_start();
require_once ('config/base_config.php'); // load base config with paths to classes etc.
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
  $msg.= ("<br>Voting for idea: ".$idea_id." by user id: ".$_SESSION ['user_id']);
  $return_value = $idea->voteForIdea ($idea_id, $vote_value, $_SESSION ['user_id']);

  if ($return_value['success']){
    $msg.="<br>".abs ($return_value['data'])." is the difference in absolute value";
  }
  else {
    $msg.="<br>There was an error while voting - error code: ".$return_value['error_code'];
  }

} // end if

if (isset ($_REQUEST['reset']))
{
  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg.= ("<br>Resetting votes and likes");
  $return_value = $idea->resetVotes ();
  $msg.= ("<br>Returning value: ".$return_value['data']);
} // end if

if (isset ($_REQUEST['like']))
{
  $idea_id = intval ($_REQUEST['like']);
  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg.= ("<br>Liking idea: ".$idea_id.", user id: ".$_SESSION ['user_id']." value:".$like_value);
  if ($like_value==0) {
    //unlike
    $return_value = $idea->IdeaRemoveLike ($idea_id, $_SESSION ['user_id']);

  } else {
    $return_value = $idea->IdeaAddLike ($idea_id, $_SESSION ['user_id']);
  }
  $msg.= ("<br>Returning value: ".$return_value['data']);
} // end if

?>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<html>
<body>

<?php

$offset = 0; // set start at dataset #10
$limit = 5; // get 10 datasets
echo ("<h2>Reading 5 idea datasets (".$room->getNumberOfUsers(4)['data'].")</h2>");

?>

<body style="font-family: arial;font-size:1.2em;">
  <div style="width:100%;background-color:#e9e9e9; padding:10px;">
    <span style="color:#ff0000; font-weight:bold;"><?php echo $msg ?>&nbsp;</span>
    <br>Set User id, Enter number here and press SET USER<form method ="_POST"><input type='text' name='user' value='<?php echo $_SESSION ['user_id'] ?>'><button type=submit name='submitter'>SET USER</button></form>
    <br><form method ="_POST"><button type=submit name='reset'>RESET VOTES AND LIKES</button></form>
    <small><i>User 7 has a delegation from user 10</i></small>
    <br><small><i>User 9 has 2 delegations from users 43, 44 </i></small>
    <br><small><i>Have fun with voting! Try voting with users 6, 9, 10, 42, 43, 44 and others (like 5, 8 or whatever) in different constellations and watch the results :)
      <br><b>To change the user id, enter id in the above field and press the button "SET USER"</b>
      <br><b>"RESET VOTES AND LIKES" resets the votes and likes to 0 to start over again</b></i></small>

  </div>
  <div id ="content_pane" style="">
<?php
$ideadata = $idea->getIdeas($offset, $limit, 4, 1, 1);
// idea list:
foreach ($ideadata['data'] as $result) {
    $votes_made = $idea->getIdeaNumberVotes($result['id'])['data'];
    echo ('<form action="" method ="_POST">');
    echo ("<h2>".$result['id'].".". $crypt->decrypt ($result['content'])."</h2>");
    echo ("<b>Voting result: " . ($result['sum_votes']).", number of likes: " . ($result['sum_likes']).", votes given in total: ".$votes_made."</b>");
    echo ("<br><small>Last Update: " . $result['last_update']."</small>");
    echo ("<input type='hidden' name='vote' value='". intval (trim ($result['id']))."'><input type='hidden' name='votevalue' value='1'><br><button type=submit name='submitter'>VOTE for</button></form><form method ='_POST'><input type='hidden' name='vote' value='". intval (trim ($result['id']))."'><input type='hidden' name='votevalue' value='-1'><button type=submit name='submitter'>VOTE against</button></form>");
    echo ('<form action="" method ="_POST">');
    echo ("<input type='hidden' name='vote' value='". intval (trim ($result['id']))."'><input type='hidden' name='votevalue' value='0'><br><button type=submit name='submitter'>VOTE NEUTRAL</button>");
    echo ("</form>");
    echo ('<form action="" method ="_POST">');
    echo ("<input type='hidden' name='like' value='". intval (trim ($result['id']))."'><input type='hidden' name='likevalue' value='1'><button type=submit name='submitter'>LIKE</button></form><form method ='_POST'><input type='hidden' name='like' value='". intval (trim ($result['id']))."'><input type='hidden' name='likevalue' value='0'><button type=submit name='submitter'>UNLIKE</button></form>");
    // User id <input type='text' name='user' value='". $user_id."'>
    echo ("</form>");
}

?>
</div>
</body>
</html>
