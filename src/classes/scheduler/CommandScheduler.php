<?php

require_once(__DIR__ . '/../../../config/base_config.php');
require_once(__DIR__ . '/../../../config/instances_config.php');
require(__DIR__ . '/../../functions.php');
require_once($baseHelperDir . 'Crypt.php');
global $baseClassDir;
require_once($baseClassDir . 'scheduler/CommandDispatcher.php');

class CommandSchedulerForInstance
{
  private $commandModelForInstance;
  private $commandDispatcherForInstance;

  public function __construct(protected $code)
  {
    $db = new Database($code);
    $crypt = new Crypt();
    $syslog = new Systemlog($db);
    $this->commandModelForInstance = new Command($db, $crypt, $syslog);
    $this->commandDispatcherForInstance = new CommandDispatcher($db, $crypt, $syslog);
  }

  public function dispatchAllDueCommands()
  {
    $commands = $this->getDueCommands();

    foreach ($commands as $command) {
      try {
        $this->commandDispatcherForInstance->dispatch($command);
        echo ("[{$this->code}] Success dispatching command id={$command['id']}\n");
      } catch (Exception $exc) {
        error_log("[{$this->code}] ERROR Dispatching/Executing command ({$command['id']}): " . $exc->getMessage());
      }
    }
  }

  protected function getDueCommands()
  {
    echo ("[{$this->code}] Getting due commands...\n");
    $dueCommandsResult = $this->commandModelForInstance->getDueCommands();
    if ($dueCommandsResult === null || $dueCommandsResult['error_code'] != 0) {
      error_log("[{$this->code}] ERROR Due commands: " . json_encode($dueCommandsResult));
      return;
    }

    $commands = $dueCommandsResult['data'];
    echo ("[{$this->code}] Due commands: " . array_reduce($commands, function ($acc, $cmd) {
      $acc .= "{'id':'{$cmd['id']}','cmd_id':'{$cmd['cmd_id']}'} ";
      return $acc;
    }, "") . "\n");
    return $dueCommandsResult['data'];
  }
}

class CommandScheduler
{
  public function dispatchDueCommands()
  {
    global $instances;
    foreach ($instances as $code => $instance) {
      $forInstance = new CommandSchedulerForInstance($code);
      $forInstance->dispatchAllDueCommands();
    }
  }
}
