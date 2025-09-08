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

  echo ("Getting due commands...\n");
  $commands = $command_class->getDueCommands();
  if ($commands === null || $commands['error_code'] != 0) {
    echo ("ERROR Due commands: " . json_encode($commands));
    continue;
  }
  echo ("Due commands: " . json_encode($commands['data']) . "\n");

  foreach ($commands['data'] as $command) {
    $id = $command['id'];
    $cmd_id = intval($command['cmd_id']);
    $cmd_params = $command['parameters'];
    $cmd_status = $command['status'];

    switch ($cmd_id) {
      case 0:
        // set online mode
        try {
          $settings_class = new Settings($db, $crypt, $syslog);
          $cmd_params = intval($cmd_params);
          // check if param is valid and within boundaries so faulty values are not updated
          if ($cmd_params >= 0 && $cmd_params <= 5) {
            echo ("Setting online mode to $cmd_params");
            $return = $settings_class->setInstanceOnlineMode($cmd_params, 99);
            if ($return['error_code'] == 0) {
              $command_class->setCommandStatus($id, 1); // command executed successfully
              $command_class->setActiveStatus($id, 0);
            } else {
              $command_class->setCommandStatus($id, 3); // command not executed successfully
            }
          } else {
            $command_class->setCommandStatus($id, 3); // params out of range
          }
        } catch (Exception $err) {
          // error occured
          $command_class->setCommandStatus($id, 4); // misc error occurred
        }
        break;
    }
  }
}
