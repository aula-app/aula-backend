<?php

class DeactivateCommandHandler extends CommandHandler
{
  protected Command $commandModel;

  public static function createWith($db, $crypt, $syslog): DeactivateCommandHandler
  {
    $instance = new DeactivateCommandHandler($db, $crypt, $syslog);
    $instance->commandModel = new Command($instance->db, $instance->crypt, $instance->syslog);
    return $instance;
  }

  protected function execute($command): mixed
  {
    return $this->commandModel->setActiveStatus(intval($command['parameters']), 0, 99);
  }

  protected function isValid(mixed $command): bool
  {
    $newStatus = intval($command['parameters']);
    return $newStatus >= 0 && $newStatus <= 5;
  }
}
