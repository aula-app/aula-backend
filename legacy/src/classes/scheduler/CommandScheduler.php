<?php

require_once(__DIR__ . '/../../../config/base_config.php');
require_once(__DIR__ . '/../../functions.php');

global $baseHelperDir;
require_once($baseHelperDir . 'InstanceConfig.php');
require_once($baseHelperDir . 'Crypt.php');

global $baseClassDir;
require_once($baseClassDir . 'scheduler/CommandDispatcher.php');

class CommandSchedulerForInstance
{
  private $commandModelForInstance;
  private $commandDispatcherForInstance;

  public function __construct(protected string $code)
  {
    $instance = InstanceConfig::createFromCode($code);
    $db = new Database($instance);
    $crypt = new Crypt();
    $syslog = new Systemlog($db);
    $this->commandModelForInstance = new Command($db, $crypt, $syslog);
    $this->commandDispatcherForInstance = new CommandDispatcher($db, $crypt, $syslog);
  }

  public function dispatchAllDueCommands()
  {
    $commands = array_filter($this->getDueCommands());

    foreach ($commands as $command) {
      try {
        $this->commandDispatcherForInstance->dispatch($command);
        // trigger_error("[{$this->code}] Success dispatching command id={$command['id']}\n", E_USER_NOTICE);
      } catch (Throwable $exc) {
        error_log("[{$this->code}] ERROR Dispatching/Executing command ({$command['id']}): " . $exc->getMessage());
      }
    }
  }

  protected function getDueCommands()
  {
    $dueCommandsResult = $this->commandModelForInstance->getDueCommands();
    if ($dueCommandsResult === null || $dueCommandsResult['error_code'] != 0) {
      error_log("[{$this->code}] ERROR Due commands: " . json_encode($dueCommandsResult));
      return;
    }

    $commands = $dueCommandsResult['data'];
    // trigger_error("[{$this->code}] Due commands: " . array_reduce($commands, function ($acc, $cmd) {
    //   $acc .= "{'id':'{$cmd['id']}','cmd_id':'{$cmd['cmd_id']}'} ";
    //   return $acc;
    // }, "") . "\n", E_USER_NOTICE);
    return $commands;
  }
}

class CommandScheduler
{
  public function dispatchDueCommands()
  {
    global $instances;
    foreach ($instances as $code => $instance) {
      try {
        $forInstance = new CommandSchedulerForInstance($code);
        $forInstance->dispatchAllDueCommands();
      } catch (Throwable $exc) {
        error_log("[{$code}] ERROR Dispatching all due commands for a single instance. " . $exc->getMessage());
      }
    }
  }
}
