<?php

enum CommandStatus: int
{
  case NOT_RUN = 0;
  case SUCCESS = 1;
  case EXECUTION_FAILURE = 2;
  case VALIDATION_FAILED = 3;
  case EXECUTION_EXCEPTION = 4;
}

abstract class CommandHandler
{
  private $commandModel;

  public function __construct(protected $db, protected $crypt, protected $syslog)
  {
    $this->commandModel = new Command($db, $crypt, $syslog);
  }

  /**
   * @param array $command to be executed
   */
  public function executeSafe(array $command)
  {
    if ($command === null || !is_array($command) || $command['id'] === null) {
      throw new RuntimeException("Bad Command");
    }

    $id = $command['id'];
    try {
      $validationResult = $this->isValid($command);
      if ($validationResult != true) {
        error_log("[{$this->db->code}] Command validation FAILED for command {$id}");
        $this->commandModel->setCommandStatus($id, CommandStatus::VALIDATION_FAILED->value);
        $this->commandModel->setActiveStatus($id, 0);
        return;
      }

      // @TODO: nikola - use db transaction with SELECT .. FOR UPDATE for isolation

      // @TODO: nikola - ensure no other handler is running the same command at the same time
      //   (due to retries and long-running commands or multiple concurrent BE services)
      //   try setting active = 2 (RUNNING) and when done (or on error), set active = 0 (or 1 if retry makes sense)
      $returnValue = $this->execute($command);

      if ($returnValue['error_code'] == 0) {
        $this->commandModel->setCommandStatus($id, CommandStatus::SUCCESS->value);
        $this->commandModel->setActiveStatus($id, 0);
      } elseif ($returnValue['error_code'] == 1) {
        $this->commandModel->setCommandStatus($id, CommandStatus::EXECUTION_FAILURE->value);
        error_log("[{$this->db->code}] Command execution FAILED(retry) " . json_encode($returnValue));
        $this->commandModel->backoffCommand($command);
      } else {
        $this->commandModel->setCommandStatus($id, CommandStatus::EXECUTION_FAILURE->value);
        error_log("[{$this->db->code}] Command execution FAILED(drop) " . json_encode($returnValue));
        $this->commandModel->setActiveStatus($id, 0);
      }
    } catch (Exception $err) {
      error_log("[{$this->db->code}] Command execution EXCEPTION " . $err->getMessage());
      $this->commandModel->setCommandStatus($id, CommandStatus::EXECUTION_EXCEPTION->value);
      $this->commandModel->backoffCommand($command);
    }
  }

  abstract protected function isValid(array $command): bool;
  abstract protected function execute(array $command): mixed;
}
