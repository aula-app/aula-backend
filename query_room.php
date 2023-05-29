<?php
require_once ('base_config.php'); // load base config with paths to classes etc.
require_once ('error_msg.php');
require_once ('error_msg.php');
require ('functions.php'); // include Class autoloader (models)

/* IMPORTANT! Every including script that wants to use the user.php, needs to set this variable ($allowed_include) to 1 */

/*
require_once ($baseClassModelDir.'User.php'); // include user model class
// Include the Database class model file
require_once ($baseClassModelDir.'Database.php');

//load Syslogger class
require_once ($baseClassModelDir.'Systemlog.php');
//load Groups class
require_once ($baseClassModelDir.'Group.php');
//load Room class
require_once ($baseClassModelDir.'Room.php');
*/

//load helper classes
require_once ($baseHelperDir.'Crypt.php');




/* */
// Create a new Database object with the MySQL credentials
$db = new Database();
$crypt = new Crypt($cryptFile); // path to $cryptFile is currently known from base_config.php -> will be changed later to be secure
$syslog = new Systemlog ($db); // systemlog
$group = new Group ($db, $crypt, $syslog); // instanciate group model class
$room = new Room ($db, $crypt, $syslog); // instanciate room model class



function out ($text, $form=false){ // lazy helper function
  $formstart="";
  $formend="";

  if ($form) {
    $formstart="<h2>";
    $formend="</h2>";
  }

  echo ("<br>".$formstart.$text.$formend."<br>");
}


out ("EXAMPLES FOR USING THE MODELS (in this case...ROOMS and GROUPS)",true);
/* */
// Example for adding a room in the database
/* */



$user = new User($db, $crypt, $syslog);

// create a random appendix to have different users....
$testrand = rand (100,1000);
$appendix = microtime(true).$testrand;



/* */
// Example for getting data for a single user....
/* */
/*
out ("Reading user data for a single user using USER class...",true);

$userid=100; // sample user id
out ("reading user #".$userid);
// read data from a certain user
$userdata = $user->getUserBaseData($userid); // read base data from user id
if (!$userdata){
  out ("nothing found!");
}else {
  //print_r($userdata);
  out ("real name:".$userdata['realname']);
  out ("real name decrypted:".$crypt->decrypt($userdata['realname']));
  out ("hash id:".$userdata['hash_id']);
  out ("user name decrypted:".$crypt->decrypt($userdata['username']));


}
*/
/* */
// Example for reading all data starting at index X with Y entries
/* */

$offset = 0; // set start at dataset #10
$limit = 5; // get 10 datasets
out ("Reading multiple room datasets (only active rooms) <br>using Room class with limit ".$offset.",".$limit." ordered by id (4) Ascending (1)...",true);

$roomdata = $room->getRooms($offset, $limit, 4, 1, 1);
/* reads room list
  if offset and limit are both set to 0 then there is no limit applied (whole list is shown)
  parameter set is (offset, limit, orderby field (0=name, 1=order field, 2=created, 3=last update, 4=id), asc(1)/desc(0), status (0=inactive, 1=active, 2=suspended, 3=archived))
*/
if (!$roomdata){
  out ("nothing found!");
}else {
  // Loop through the results and output them
  foreach ($roomdata as $result) {
      out ("ID: " . $result['id']);
      out ("Name: " . $result['room_name']);
      out ("Last Update: " . $result['last_update']);
      out ("public description: " . $result['description_public']);
  }

}
/*
// delete single dataset that was previously added....
out ("Deleting the dataset (".$inserted_user_id.") that was just added...", true);

$deleted_usersets = $user->deleteUser($inserted_user_id);
if (!$deleted_usersets){
  out ("nothing deleted!");
}else {
  out ($deleted_usersets." DATASET was deleted");
}


$user_id=15;
out ("Getting the hash id for an integer user id...".$user_id,true);
$hash_id = $user->getUserHashId($user_id);
out ("hash id for user ".$user_id." = ".$hash_id);

$hash_id="bb2aa1d2ea71b7c6154a10e47a23299d";
out ("Getting the database id for a hash id...".$hash_id,true);
$user_id = $user->getUserIdByHashId($hash_id);
out ("id for user with hash ".$hash_id." = ".$user_id);

$user_id = 100;
// check credentials for a user
$username = $crypt->decrypt("SN0OQNPw2UhkLCUX9wf1D7vv20velbHWMOvC2C+B91JieI6fBGjhqaNLzpGAil/9s0o87GnEUauWz1Y=");
out ("checking credentials for user  ".$username." (DB ID: ".$user_id.") using standard pw aula ...", true);
$userdata = $user->checkCredentials( $username,"aula"); // read base data from user id, aula is the standard pw i wrote into the db, $username is the username in clear text
if (!$userdata){
  out ("User is not authorized!");
} else {
  out ("User is authorized! The user id returned from db is: ".$userdata);
}

$userid=100; // user that will be edited

out ("Change status of user#100 to inactive (0)",true);
$userdata = $user->setUserStatus( $userid,0); // set status of user #100 to 0
if (!$userdata){
  out ("User not found!");
} else {
  out ("User status changed: No. of datasets affected: ".$userdata);
}

out ("Change displayname of user#100 to EDGAR",true);
$userdata = $user->setUserDisplayname( $userid,"EDGAR"); // set status of user #100 to 0
if (!$userdata){
  out ("User not found!");
} else {
  out ("User display name changed: No. of datasets affected: ".$userdata);
}

out ("Change real name of user#100 to DANIEL",true);
$userdata = $user->setUserRealname( $userid,"DANIEL"); // set status of user #100 to 0
if (!$userdata){
  out ("User not found!");
} else {
  out ("User real name changed: No. of datasets affected: ".$userdata);
}


out ("Change email adress of user#100 to daniel@aula.de",true);
$userdata = $user->setUserEmail( $userid,"DANIEL"); // set status of user #100 to 0
if (!$userdata){
  out ("User not found!");
} else {
  out ("User email changed: No. of datasets affected: ".$userdata);
}

$testhash = "0720c0e3d7d4185737b6e6f1bbd4168c";
out ("Setting registration status of a user using hash id of user instead of db id", true);
$userdata = $user->setUserRegStatus ($testhash,2);
if (!$userdata){
  out ("User not found!");
} else {
  out ("User reg status changed: No. of datasets affected: ".$userdata);
}

$testhash = "0a7e5754727eabb648b058a6e0947034";
$about ="I am an aula room....";
out ("Adding about text to a user using hash id of user instead of db id", true);
$userdata = $user->setUserAbout ($testhash,$about);
if (!$userdata){
  out ("User not found!");
} else {
  out ("User about text added status changed: No. of datasets affected: ".$userdata);
}
*/

$description_public ="Public description of the group..";
$description_internal ="Internal description of the group..";
$grouporder = rand (10,500);
// create a random appendix to have different groups....
$testrand = rand (100,1000);
$appendix = microtime(true).$testrand;
$group_name ="testgroup".$appendix;
out ("Adding a new group", true);


$inserted_group_id = $group->addGroup($group_name, $description_public, $description_internal, 'internal info', 1, 'aula', 1, $grouporder);

out ("return code:".$inserted_group_id);



$description_public ="Public description of the room..";
$description_internal ="Internal description of the room..";
//generating a random order
$roomorder = rand (10,500);

// create a random appendix to have different groups....
$testrand = rand (100,1000);
$appendix = microtime(true).$testrand;
$room_name ="testroom".$appendix;
out ("Adding a new room", true);

// addRoom($room_name, $description_public, $description_internal, $internal_info, $status, $access_code, $restricted, $updater_id=0)
$inserted_room_id = $room->addRoom($room_name, $description_public, $description_internal, 'internal info', 1, 'aula',0, $roomorder, 1);

out ("return code:".$inserted_room_id);



// Close the database connection
$db = null;
?>
