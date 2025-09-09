<?php

require_once(__DIR__ . '/../../../config/base_config.php');
global $baseClassDir;
foreach (glob($baseClassDir . "./usecases/commands/*.php") as $file) {
  require_once $file;
}

class CommandDispatcher
{
  private $commandHandlers;

  public function __construct($db, $crypt, $syslog)
  {
    $this->commandHandlers = [
      // 0-9 - instance related
      0 => InstanceOnlineModeHandler::createWith($db, $crypt, $syslog),
      5 => null, # TBD
      // 10-19 - user related
      10 => null, # TBD
      11 => SendEmailHandler::createWith($db, $crypt, $syslog),
      15 => null, # TBD
      // 20-29 - group related
      20 => null, # TBD
      25 => null, # TBD
    ];
  }

  public function dispatch(mixed $command)
  {
    $commandType = @$command['cmd_id'];
    if ($commandType === null) {
      throw new RuntimeException("Invalid Command object (there's no ['cmd_id']).");
    }

    $handler = @$this->commandHandlers[$commandType];
    if ($handler === null) {
      throw new RuntimeException("No Handler found for the Command type (cmd_id = {$commandType}).");
    }

    return $handler->executeSafe($command);
  }
}
