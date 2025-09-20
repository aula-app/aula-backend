<?php

require_once(__DIR__ . '/../../../config/base_config.php');
global $baseClassDir;
foreach (glob($baseClassDir . "./usecases/commands/*.php") as $file) {
  require_once $file;
}

class CommandDispatcher
{
  private $commandHandlers;

  public function __construct(private $db, private $crypt, private $syslog)
  {
    $this->commandHandlers = [
      // 0-9 - instance related
      0 => function () {
        return InstanceOnlineModeHandler::createWith($this->db, $this->crypt, $this->syslog);
      },
      5 => null, # TBD
      // 10-19 - user related
      10 => null, # TBD
      11 => function () {
        return SendEmailHandler::createWith($this->db, $this->crypt, $this->syslog);
      },
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
      error_log("[{$this->db->code}] ERROR No Handler found for the Command type (cmd_id = {$commandType}).");
      $handler = function () {
        return DeactivateCommandHandler::createWith($this->db, $this->crypt, $this->syslog);
      };
    }

    return ($handler())->executeSafe($command);
  }
}
