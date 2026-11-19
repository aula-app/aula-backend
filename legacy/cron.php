<?php

require_once(__DIR__ . '/config/base_config.php');
require_once($baseClassDir . './scheduler/CommandScheduler.php');

// Skip this tick if the previous run is still dispatching.
$lock = fopen(sys_get_temp_dir() . '/aula-cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
  error_log("Previous cron run still active, skipping.");
  exit(0);
}

$commandScheduler = new CommandScheduler();
$commandScheduler->dispatchDueCommands();
