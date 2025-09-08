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
      InstanceOnlineModeHandler::CMD_ID => InstanceOnlineModeHandler::createWith($db, $crypt, $syslog),
      SendEmailHandler::CMD_ID => SendEmailHandler::createWith($db, $crypt, $syslog)
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
