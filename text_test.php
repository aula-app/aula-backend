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
$user = new User ($db, $crypt, $syslog); // instanciate user model class
$text = new Text ($db, $crypt, $syslog); // instanciate message model class

function out ($text, $form=false){ // lazy helper function
  $formstart="";
  $formend="";

  if ($form) {
    $formstart="<h2>";
    $formend="</h2>";
  }

  echo ("<br>".$formstart.$text.$formend);
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

$userconsentval = 0;

if (isset ($_REQUEST['addmsg']))
{
  $headline = $_REQUEST['headline'];
  $body = $_REQUEST['body'];
  $consent_text = $_REQUEST['consent_text'];
  $publish_date = $_REQUEST['publish_date'];
  $userconsentval = $_REQUEST['userconsentval'];


  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg.= ("<br>Adding Text: ".$headline." to DB");
  // public function addText ($headline, $body="", $consent_text="", $location=0, $creator_id=0, $user_needs_to_consent=0, $service_id_consent=0, $status=1, $updater_id=0, $language_id=0) {
  $return_value = $text->addText ($headline, $body, $consent_text,0,0,$userconsentval);
  $msg.= ("<br>Returning value: ".$return_value['data']);
} // end if

if (isset ($_REQUEST['delete']))
{
  // voteForIdea($idea_id, $vote_value, $user_id)
  $msg_id = $_REQUEST['id'];
  $msg.= ("<br>Deleting Text #".$msg_id);
  $return_value = $text->deleteText ($msg_id);
  $msg.= ("<br>Returning value: ".$return_value);
} // end if



?>
<html>
<body>

<?php

$offset = 0; // set start at dataset #10
$limit = 50; // get 10 datasets
echo ("<h2>Reading first 5 text datasets </h2>");

?>

<body style="font-family: arial;font-size:1.2em;">
  <div style="width:100%;background-color:#e9e9e9; padding:10px;">
    <span style="color:#ff0000; font-weight:bold;"><?php echo $msg ?>&nbsp;</span>
    <br>Add message
    <form method ="_POST">
      <input type='text' name='headline' value='<?php echo $headline ?>'>
      body: <textarea name ="body"></textarea>
      consent text: <textarea name ="consent_text"></textarea>
      <input type='hidden' name='publish_date' value='<?php echo $publish_date ?>'>
      User needs to consent value: <input type='text' name='userconsentval' value='<?php echo $userconsentval ?>'>

      <button type=submit name='addmsg'>ADD TEXT</button>
    </form>

  </div>
  <div id ="content_pane" style="">
<?php

out ("These texts need consent so that the user can use the system.", true);
$messagedata = $user->getNecessaryConsents();
//print_r ($messagedata);
if ($messagedata['success'] && $messagedata['data']){
  $data = $messagedata ['data'];
  // idea list:
  out ("<br>Found ".$messagedata['count']." Text(s) that need user consent to use the system:");
  // print_r ($data);
  foreach ($data as $result) {
    //  $votes_made = $idea->getIdeaNumberVotes($result['id']);
      echo ("<h3><i>".$result['id'].".".$result['headline']."</i></h3>");
  }
}else {
  out ("No Texts that are necessary to be consented to were found.");
}
out ("--------------");

$user_id = 4;
out ("check if user #".$user_id." has given all above consents", true);
$givenconsents = $user->checkHasUserGivenConsentsForUsage ($user_id);
//print_r ($givenconsents);
if ($givenconsents['success'] && $givenconsents ['data']==1){
  echo ("<br>User #".$user_id." has consented to all necessary texts/terms to use the application!");
  echo ("<br>User #".$user_id." gave ".$givenconsents ['count']." consents.");
}else {
  echo ("<br>User #".$user_id." has NOT consented to all necessary texts/terms to use the application");
  echo ("<br>User #".$user_id." only gave ".$givenconsents ['count']." consents.");
}


out ("--------------");
out ("These consents are missing so that the user can use the system:", true);
$messagedata = $user->getMissingConsents($user_id);
//print_r ($messagedata);
if ($messagedata['success'] && intval ($messagedata['data'])>0){
  $data = $messagedata ['data'];
  // idea list:
  out ("<br>Found ".$messagedata['count']." Text(s) that the user #".$user_id." hasn't consented to yet:");
  // print_r ($data);
  foreach ($data as $result) {
    //  $votes_made = $idea->getIdeaNumberVotes($result['id']);
      echo ("<h3><i>".$result['id'].".".$result['headline']."</i></h3>");
  }
}else {
  out ("No Texts that are necessary to be consented to were found.");
}

// getMessages ($offset, $limit, $orderby=3, $asc=0, $status=1, $extra_where="", $publish_date, $target_group, $room_id)
$messagedata = $text->getTexts ($offset, $limit, 4, 1, 1);
//print_r ($messagedata);
out ("--------------");
out ("Listing Texts from database",true);
$data = $messagedata ['data'];
//print_r ($messagedata);
// idea list:
out ("<br>Found ".$messagedata['count']." Text(s)!");

foreach ($data as $result) {
  //  $votes_made = $idea->getIdeaNumberVotes($result['id']);
    echo ('<form action="" method ="_POST">');
    echo ("<h3><i>".$result['id'].".".$result['headline']."</i></h3>");
    echo ("<b>Body: ".$result['body']."<br>consent text: " .$result['consent_text']);
    echo ("<br><b>User needs to consent? DB value: ".$result['user_needs_to_consent']." </b><br>(0=no, text will only be displayed, 1=yes, to make text disappear on next call, 2=yes, necessary to use the aula system)</b>");
    echo ("<br><br><small>Last Update: " . $result['last_update']."</small>");
    echo ("<input type='hidden' name='id' value='". intval (trim ($result['id']))."'><br><button type=submit name='delete'>DELETE</button></form>");

}

?>
</div>
</body>
</html>
