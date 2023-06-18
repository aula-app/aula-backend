<?php
require_once ('base_config.php'); // load base config with paths to classes etc.
require_once ('error_msg.php');

require ('functions.php'); // include Class autoloader (models)

//load helper classes
require_once ($baseHelperDir.'Crypt.php');




/* */
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


out ("EXAMPLES FOR USING THE MODELS (in this case...TOPICS)",true);
/* */
// Example for adding a room in the database
/* */



$user = new User($db, $crypt, $syslog);

// create a random appendix to have different users....
$testrand = rand (100,1000);
$appendix = microtime(true).$testrand;


$ideas = array();

$room_id = 5;
$updater_id = 42;
// Random ideas to populate the array
out ("Adding topics for room ".$room_id."<br to >using TOPIC class",true);
// addTopic ($name, $description_internal, $description_public, $status, $order_importance=10, $updater_id=0, $room_id=0)
$i=0;
while ($i<10){
  $i++;
  $topic->addTopic ("Topic #".$i, "Internal description for the topic #".$i,  "Public description for the topic #".$i, 1, "".intval (intval ($i)*10), $updater_id, 5);
  out ("Adding topic #".$i, false);
}

// Get content of single idea
$topic_id = 5;
$result =  $topic->getTopicBaseData($topic_id)['data'];
echo ("<br>Topic #".$topic_id." ".$crypt->decrypt ($result['name']).": ".$crypt->decrypt ($result['description_public'])." (".$result['created'].")");

$offset = 0; // set start at dataset #10
$limit = 5; // get 10 datasets
out ("Reading multiple topic datasets (only active, status = 1) <br>using TOPIC class with limit ".$offset.",".$limit." ordered by id (4) Ascending (1)...",true);
$topicdata = $topic->getTopics($offset, $limit, 4, 1, 1);
// idea list:
foreach ($topicdata['data'] as $result) {
    out ("ID: " . $result['id']);
    out ("Name: " . $crypt->decrypt ($result['name']));
    out ("Description public: " . $crypt->decrypt ($result['description_public']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']);
}

$offset = 0; // set start at dataset #0
$limit = 0; // get 5 datasets
$room_id = 5;
// function getIdeasByRoom ($offset, $limit, $orderby=3, $asc=0, $status=1, $room_id)
out ("Reading multiple TOPIC datasets (of a certain room#".$room_id.") <br>using Idea class with limit ".$offset.",".$limit." ordered by id (4) Ascending (1)...",true);
$topicdata = $topic->getTopicsByRoom($offset, $limit, 4, 1, 1, $room_id);
// idea list:
foreach ($topicdata['data'] as $result) {
    out ("ID: " . $result['id']);
    out ("Description (public): " . $crypt->decrypt ($result['description_public']));
    out ("Description (internal): " . $crypt->decrypt ($result['description_internal']));
    out ("Topic: " . $crypt->decrypt ($result['name']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']);
}

$user_id = 1;
$user_id_target = 5;

$topic_id = 4;
// delegating vote
out ("Delegating voting right from user #".$user_id." to ".$user_id_target."<br to >using User class",true);
$retvalue = $user->delegateVoteRight($user_id, $user_id_target, $room_id, $topic_id, $updater_id=0);
out ("return code:".$retvalue ['data']);

// delegating vote
out ("Revoking delegation right from user #".$user_id_target." back to ".$user_id."<br>using User class",true);
$retvalue = $user->revokeVoteRight($user_id, $user_id_target, $room_id, $topic_id, $updater_id=0);
out ("return code:".$retvalue['data']);

$user_id_target = 15;
$topic_id = 4;
// delegating vote
out ("Delegating voting right from user #".$user_id." to ".$user_id_target."<br to >using User class",true);
$retvalue = $user->delegateVoteRight($user_id, $user_id_target, $room_id, $topic_id, $updater_id=0);
out ("return code:".$retvalue['data']);

// getting delegtaions
out ("Getting received delegations user #".$user_id_target." for topic ".$topic_id."<br to >using User class",true);

$users = $user->getReceivedDelegations ($user_id_target, $topic_id);
foreach ($users['data'] as $result) {
    out ("ID: " . $result['id']);
    out ("Name: " . $crypt->decrypt ($result['displayname']));
    out ("email: " . $crypt->decrypt ($result['email']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']);
}
// print_r ($users);

// getting delegtaions
out ("Getting given delegations user #".$user_id." for room ".$room_id."<br to >using User class",true);

$users = $user->getGivenDelegations ($user_id, $room_id);
print_R ($users);
echo ("<br>success: ".$users['success']);
if ($users ['data']) {
foreach ($users['data'] as $result) {
    out ("ID: " . $result['id']);
    out ("Name: " . $crypt->decrypt ($result['displayname']));
    out ("Email: " . $crypt->decrypt ($result['email']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']);
}
}
else {
  out ("No delegation found.");

}
//print_r ($users);

?>
