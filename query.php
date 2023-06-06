<?php
require_once ('base_config.php'); // load base config with paths to classes etc.
require_once ('error_msg.php');
require ('functions.php'); // include Class autoloader (models)

/* IMPORTANT! Every including script that wants to use the user.php, needs to set this variable ($allowed_include) to 1 */

//$allowed_include = 1; // set allow to 1 in order to include the user.php script
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


out ("EXAMPLES FOR USING THE MODELS (in this case...USER)",true);
/* */
// Example for adding a user in the database
/* */


out ("Writing something into the systemlog using model Systemlog...",true);
// write something into the system log:
$syslog->addSystemEvent(0, "Example process started", 0, "", 1); // 0 = msg, 1= error (see databse description)
// Create a new User object (params: db object, crypt class, user id of editor)
out ("Adding user using User class...",true);

$user = new User($db, $crypt, $syslog);

// create a random appendix to have different users....
$testrand = rand (100,1000);
$appendix = microtime(true).$testrand;
$testusername = "username_".$appendix;

// Add a user
// Synthax realname, shown name, username, e-mail, pw clear, etc
// public function addUser($realname, $displayname, $username, $email, $password, $status) $status-> 0=inactive, 1=active

$inserted_user_id = $user->addUser('real_testuser'.$appendix, 'display_testuser'.$appendix, $testusername, 'testuser'.$appendix.'.@aula.de', 'aula', 1);

out ("Inserted user at id:".$inserted_user_id);
// write to system log
$syslog->addSystemEvent(0, "Added new user ".$inserted_user_id, 0, "", 1);


/* */
// Example for getting data for a single user....
/* */
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

/* */
// Example for reading all data starting at index X with Y entries
/* */

$offset = 10; // set start at dataset #10
$limit = 5; // get 5 datasets
out ("Reading multiple users (only active, sorted by last update ASC) using User class with limit ".$offset.",".$limit."...limit 0,0 shows all datasets",true);

$userdata = $user->getUsers($offset, $limit, 4, 1, 1); // read base data from users
if (!$userdata){
  out ("nothing found!");
}else {
  // Loop through the results and output them
  foreach ($userdata as $result) {
      out ("ID: " . $result['id']);
      out ("Name: " . $crypt->decrypt ($result['realname']));
      out ("Email: " . $crypt->decrypt ($result['email']));
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
*/

$user_id=5;
out ("Getting the hash id for user id ".$user_id,true);
$hash_id = $user->getUserHashId($user_id);
out ("hash id for user ".$user_id." = ".$hash_id);

$hash_id="5790bd186ff18db1bed495b3f6411ba3";
out ("Getting the database id for a hash id...".$hash_id,true);
$user_id = $user->getUserIdByHashId($hash_id);
out ("id for user with hash ".$hash_id." = ".$user_id);

$user_id = 12;
// check credentials for a user
$username = $crypt->decrypt("QOzPiMqW9IxM3Gb4hSBLXuJCA8xRDFAKxdZMNfrMSTeU/riZEMZ55p6f+5/727stKRWqtMb4vQ====");
out ("checking credentials for user  ".$username." (DB ID: ".$user_id.") using standard pw aula ...", true);
$userdata = $user->checkCredentials( $username,"aula"); // read base data from user id, aula is the standard pw i wrote into the db, $username is the username in clear text
if (!$userdata){
  out ("User is not authorized!");
} else {
  out ("User is authorized! The user id returned from db is: ".$userdata);
}

$userid=10; // user that will be edited

out ("Change status of user#100 to inactive (0)",true);
$userdata = $user->setUserStatus( $userid,0); // set status of user #100 to 0
if (!$userdata){
  out ("User not found!");
} else {
  out ("User status changed: No. of datasets affected: ".$userdata);
}

$username="username_1685098107.2936873"; // username that will be checked

out ("Checking if ".$username." exists in db and getting the user id ",true);
$userdata = $user->checkUserExistsByUsername($username); // check if user exists
if ($userdata==0){
  out ("User not found!");
} else {
  out ("User ".$username." exists! User has user id ".$userdata);
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

$testhash = "eee26e36837a64181a9264754097553e";
out ("Setting registration status of a user using hash id of user instead of db id", true);
$userdata = $user->setUserRegStatus ($testhash,2);
if (!$userdata){
  out ("User not found!");
} else {
  out ("User reg status changed: No. of datasets affected: ".$userdata);
}

$testhash = "7754767dd4d9fc697cbd8b21fc9eb20a";
$about ="I am an aula developer....";
out ("Adding about text to a user using hash id of user instead of db id", true);
$userdata = $user->setUserAbout ($testhash,$about);
if (!$userdata){
  out ("User not found!");
} else {
  out ("User about text added status changed: No. of datasets affected: ".$userdata);
}


$roomid = 1;
$userid = 1;
out ("Adding User ".$userid." to Room ".$roomid, true);
$userdata = $user->addToRoom ($userid, $roomid, 1,44); // 1=active (could also be set to suspended or inactive), 44 = updater id of the user doing the update
out ("USER Class returned: ".$userdata);

$roomid = 5;
$userid = 1;
out ("Deleting User ".$userid." from Room ".$roomid, true);
$userdata = $room->deleteUserFromRoom ($roomid,$userid);
out ("ROOM Class returned: ".$userdata);

$roomid = 6;
out ("Deleting (emptying) all users from Room ".$roomid, true);
$userdata = $room->emptyRoom ($roomid,$userid);
out ("ROOM Class returned: ".$userdata);


$groupid = 9;
$userid = 45;
out ("Deleting User ".$userid." from group ".$groupid, true);
$userdata = $group->deleteUserFromGroup ($groupid,$userid);
out ("GROUP Class returned: ".$userdata);



$groupid = 9;
$userid = 42;
out ("Granting infinite votes to User ".$userid, true);
$userdata = $user->grantInfiniteVotesToUser ($userid);
out ("USER Class returned: ".$userdata);

$userid = 42;
out ("Getting infinite votes status from User ".$userid, true);
$userdata = $user->getUserInfiniteVotesStatus ($userid);
out ("USER Class returned: ".$userdata);

$groupid = 7;
out ("Deleting (emptying) all users from group ".$groupid, true);
$userdata = $group->emptyGroup ($groupid, $userid);
out ("GROUP Class returned: ".$userdata);



$groupid = 2;
$userid = 3;
out ("Adding User ".$userid." to Group ".$groupid, true);
$userdata = $user->addToGroup ($userid,$groupid, 1,44); // 1=active (could also be set to suspended or inactive), 44 = updater id of the user doing the update
out ("USER Class returned: ".$userdata);

$roomid = 1;
$userid = 1;
out ("Getting Userlist (only active users status = 1) from room ".$roomid, true);
$userdata = $room->getUsersInRoom ($roomid, 1); // 1=active (could also be set to suspended or inactive)

if (!$userdata){
  out ("nothing found!");
}else {
  // Loop through the results and output them
  $i=1;
  foreach ($userdata as $result) {
      out ("<b>User #".$i."</b>");
      out ("ID: " . $result['id']);
      out ("Real name: " . $crypt->decrypt ($result['realname']));
      out ("Display name: " . $crypt->decrypt ($result['displayname']));
      out ("User name: " . $crypt->decrypt ($result['username']));

      out ("Email: " . $crypt->decrypt ($result['email']));
      $i++;
  }
}


$groupid = 3;
$userid = 1;
out ("Getting Userlist (only active users status = 1) from group ".$groupid, true);
$userdata = $group->getUsersInGroup ($groupid, 1); // 1=active (could also be set to suspended or inactive)

if (!$userdata){
  out ("nothing found!");
}else {
  // Loop through the results and output them
  $i=1;
  foreach ($userdata as $result) {
      out ("<b>User #".$i."</b>");
      out ("ID: " . $result['id']);
      out ("Real name: " . $crypt->decrypt ($result['realname']));
      out ("Display name: " . $crypt->decrypt ($result['displayname']));
      out ("User name: " . $crypt->decrypt ($result['username']));

      out ("Email: " . $crypt->decrypt ($result['email']));
      $i++;
  }
}





// Close the database connection
$db = null;
?>
