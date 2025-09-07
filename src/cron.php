<?php

require_once(__DIR__ . '/../config/base_config.php'); // load base config with paths to classes etc.
require_once(__DIR__ . '/../config/instances_config.php'); // load base config with paths to classes etc.
global $instances;

echo ("LOADING CONFIG\n");
echo ("baseClassModelDir: " . $baseClassModelDir . "\n");

require_once($baseHelperDir . 'Crypt.php');
require_once($baseHelperDir . 'ResponseBuilder.php');

require('functions.php'); // include Class autoloader (models)

foreach ($instances as $code => $instance) {
  echo ("\n\nLOADING {$code}...\n");
  $db = new Database($code);
  $crypt = new Crypt();
  $syslog = new Systemlog($db);
  $command_class = new Command($db, $crypt, $syslog);
  $converters = new Converters($db);

  $now = $converters->getNow();
  $time_only = $converters->getTimeOnlyNow();

  echo ("NOW: $now\n");
  echo ("TIME ONLY: $time_only\n");
  echo ("Getting due commands...\n");
  $commands = $command_class->getDueCommands();
  if ($commands === null || $commands['error_code'] != 0) {
    continue;
  }
  echo ("Due commands: " . json_encode($commands['data']) . "\n");

  foreach ($commands['data'] as $command) {
    // iterate through due commands
    echo ($command);
    $cmd_id = intval($command['cmd_id']);
    $cmd_params = $command['parameters'];
    $cmd_status = $command['status'];

    // check which command and execute
    /* implementeed commands:
        10 = set online mode
        20 = delete user
        40 = set user status (user_Id ; 0,1,2)
        */
    switch ($cmd_id) {
      case 10:
        // set online mode
        try {
          $settings_class = new Settings($db, $crypt, $syslog);
          $cmd_params = intval($cmd_params);
          // check if param is valid and within boundaries so faulty values are not updated
          if ($cmd_params > -1 && $cmd_params < 6) {
            $return = $settings_class->setInstanceOnlineMode($status, 99);
            if ($return['error_code'] == 0) {
              $command_class->setCommandStatus($cmd_id, 1); // command executed successfully
            } else {
              $command_class->setCommandStatus($cmd_id, 3); // command not executed successfully
            }
          } else {
            $command_class->setCommandStatus($cmd_id, 3); // params out of range
          }
        } catch (Exception $err) {
          // error occured
          $command_class->setCommandStatus($cmd_id, 4); // misc error occurred
        }
        break;

      case 20:
        // delete user
        try {
          $cmd_params_elems = explode(";", $cmd_params);

          $delete_mode = $cmd_params_elems[0]; // 0 = delete user only 1 = delete user + associated data (danger!)
          $user_id = $cmd_params_elems[1];

          // check if param is valid and within boundaries so faulty values are not updated
          if ($cmd_params > 0) {
            $return = $user->deleteUser($user_id, $delete_mode, 99);
            if ($return['error_code'] == 0) {
              $command_class->setCommandStatus($cmd_id, 1); // command executed successfully
            } else {
              $command_class->setCommandStatus($cmd_id, 3); // command not executed successfully
            }
          } else {
            $command_class->setCommandStatus($cmd_id, 3); // params out of range
          }
        } catch (Exception $err) {
          // error occured
          $command_class->setCommandStatus($cmd_id, 4); // misc error occurred
        }
        break;

      case 40:
        try {
          // set user status
          $cmd_params_elems = explode(";", $cmd_params);

          $status = $cmd_params_elems[0]; // 0 = deactivate user only 1 = activate user 2 = suspend
          $user_id = $cmd_params_elems[1];

          // check if param is valid and within boundaries so faulty values are not updated
          if ($cmd_params > 0) {
            $return = $user->setUserStatus($user_id, $status, $updater_id = 0);
            if ($return['error_code'] == 0) {
              $command_class->setCommandStatus($cmd_id, 1); // command executed successfully
            } else {
              $command_class->setCommandStatus($cmd_id, 3); // command not executed successfully
            }
          } else {
            $command_class->setCommandStatus($cmd_id, 3); // params out of range
          }
        } catch (Exception $err) {
          // error occured
          $command_class->setCommandStatus($cmd_id, 4); // misc error occurred
        }
        break;

    }
  }
  // check phases due for switching

  // check if a backup is necessary (daily)
  if ($time_only == "00:00") {
    // execute dump every day at midnight
    try {
      echo ("<br>DUMP IS DUE NOW: " . $time_only);
      //$converters-> createDBDump();
    } catch (Exception $err) {
      // error occured
    }
  }
}
