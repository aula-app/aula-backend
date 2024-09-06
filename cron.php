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
$converters = new Converters($db); // load converters
$command_class = new Command($db); // load command class

$now = $converters -> getNow();
$time_only = $converters -> getTimeOnlyNow ();


echo ("<h1>Welcome to the cron job</h1>");
echo ("NOW: ".$now);
echo ("<br>TIME ONLY: ".$time_only);

// check commands first
$commands = $command_class->getDueCommands();
foreach ($commmands as $command) {
    // iterate through due commands
    print_r ($command);
}  
// check phases due for switching

// check if a backup is necessary (daily)
if ($time_only == "00:00") {
    // execute dump every day at midnight
    echo ("<br>DUMP IS DUE NOW: ".$time_only);
    //$converters-> createDBDump();
}





