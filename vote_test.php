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


$user_id = 5;
if (!isset ($_SESSION ['user_id'])){
  $_SESSION ['user_id'] = $user_id;
}
if (isset ($_REQUEST['user']))
{
  echo ("<br>USER ID is set to ".intval ($_REQUEST['user']));
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
  echo ("<br>VOTING FOR idea: ".$idea_id.", user id: ".$_SESSION ['user_id']." value:".$vote_value);
  $return_value = $idea->voteForIdea ($idea_id, $vote_value, $_SESSION ['user_id']);
  echo ("Returning value: ".$return_value);
} // end if

if (isset ($_REQUEST['like']))
{
  $idea_id = intval ($_REQUEST['like']);
  // voteForIdea($idea_id, $vote_value, $user_id)
  echo ("<br>VOTING FOR idea: ".$idea_id.", user id: ".$_SESSION ['user_id']." value:".$like_value);
  if ($like_value==0) {
    //unlike
    $return_value = $idea->IdeaRemoveLike ($idea_id, $_SESSION ['user_id']);

  } else {
    $return_value = $idea->IdeaAddLike ($idea_id, $_SESSION ['user_id']);
  }
  echo ("Returning value: ".$return_value);
} // end if

?>
<html>
<body>

<?php

$offset = 0; // set start at dataset #10
$limit = 5; // get 10 datasets
out ("Reading idea datasets (only active, status = 1) <br>using Idea class with limit ".$offset.",".$limit." ordered by id (4) Ascending (1)...",true);
echo ("<br>(User 6 has a delegation from user 15 = double vote weight for user 6)");
?>
<br><br>User id <form method ="_POST"><input type='text' name='user' value='<?php echo $_SESSION ['user_id'] ?>'><button type=submit name='submitter'>SET USER</button></form>
<?php
$ideadata = $idea->getIdeas($offset, $limit, 4, 1, 1);
// idea list:
foreach ($ideadata as $result) {
    echo ('<form action="" method ="_POST">');
    out ("ID: " . $result['id']);
    out ("Name: " . $crypt->decrypt ($result['displayname']));
    out ("Idea: " . $crypt->decrypt ($result['content']));
    out ("Sum Votes: " . ($result['sum_votes']).", Sum Likes: " . ($result['sum_likes']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']."<br><input type='hidden' name='vote' value='". intval (trim ($result['id']))."'><input type='hidden' name='votevalue' value='1'><br><button type=submit name='submitter'>VOTE for</button></form><form method ='_POST'><input type='hidden' name='vote' value='". intval (trim ($result['id']))."'><input type='hidden' name='votevalue' value='-1'><button type=submit name='submitter'>VOTE against</button></form>");
    echo ('<form action="" method ="_POST">');
    out ("<input type='hidden' name='like' value='". intval (trim ($result['id']))."'><input type='hidden' name='likevalue' value='1'><button type=submit name='submitter'>LIKE</button></form><form method ='_POST'><input type='hidden' name='like' value='". intval (trim ($result['id']))."'><input type='hidden' name='likevalue' value='0'><button type=submit name='submitter'>UNLIKE</button></form>");
    // User id <input type='text' name='user' value='". $user_id."'>
    echo ("</form>");
}

?>

</body>
</html>
