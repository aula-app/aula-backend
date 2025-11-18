<?php

require_once(__DIR__ . '/config/base_config.php');
require_once($baseClassDir . './scheduler/CommandScheduler.php');

$commandScheduler = new CommandScheduler();
$commandScheduler->dispatchDueCommands();
