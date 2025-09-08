<?php

abstract class CommandHandler
{
  private $commandModel;

  private const COMMAND_STATUS_NOT_RUN = 0;
  private const COMMAND_STATUS_SUCCESS = 1;
  private const COMMAND_STATUS_EXECUTION_FAILURE = 2;
  private const COMMAND_STATUS_VALIDATION_FAILED = 3;
  private const COMMAND_STATUS_EXECUTION_EXCEPTION = 4;

  public function __construct(protected $db, protected $crypt, protected $syslog)
  {
    $this->commandModel = new Command($db, $crypt, $syslog);
  }

  public function executeSafe(mixed $command)
  {
    if ($command === null || !is_array($command) || $command['id'] === null) {
      throw new RuntimeException("Bad Command");
    }

    $id = $command['id'];
    try {
      $validationResult = $this->isValid($command);
      if ($validationResult != true) {
        $this->commandModel->setCommandStatus($id, self::COMMAND_STATUS_VALIDATION_FAILED);
      }

      // @TODO: nikola - use db transaction with SELECT .. FOR UPDATE for isolation

      // @TODO: nikola - ensure no other handler is running the same command at the same time
      //   (due to retries and long-running commands or multiple concurrent BE services)
      $returnValue = $this->execute($command);

      if ($returnValue['error_code'] == 0) {
        $this->commandModel->setCommandStatus($id, self::COMMAND_STATUS_SUCCESS);
        $this->commandModel->setActiveStatus($id, 0);
      } else {
        $this->commandModel->setCommandStatus($id, self::COMMAND_STATUS_EXECUTION_FAILURE);
        $this->commandModel->setActiveStatus($id, 0);
      }
    } catch (Exception $err) {
      error_log("Command execution ERROR " . $err->getMessage());
      $this->commandModel->setCommandStatus($id, self::COMMAND_STATUS_EXECUTION_EXCEPTION);
    }
  }

  abstract protected function isValid(mixed $command): bool;
  abstract protected function execute(mixed $command): mixed;
}
