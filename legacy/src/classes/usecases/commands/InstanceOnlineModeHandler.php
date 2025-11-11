<?php

class InstanceOnlineModeHandler extends CommandHandler
{
  protected Settings $settingsModel;

  public static function createWith($db, $crypt, $syslog): InstanceOnlineModeHandler
  {
    $instance = new InstanceOnlineModeHandler($db, $crypt, $syslog);
    $instance->settingsModel = new Settings($instance->db, $instance->crypt, $instance->syslog);
    return $instance;
  }

  protected function execute($command): mixed
  {
    return $this->settingsModel->setInstanceOnlineMode(intval($command['parameters']), 99);
  }

  protected function isValid(mixed $command): bool
  {
    $newStatus = intval($command['parameters']);
    return $newStatus >= 0 && $newStatus <= 5;
  }
}
