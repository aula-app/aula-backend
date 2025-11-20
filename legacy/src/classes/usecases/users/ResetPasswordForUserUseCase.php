<?php

require_once(__DIR__ . '/../../../../config/base_config.php');
global $baseHelperDir;
global $baseClassDir;
require_once($baseHelperDir . 'ResponseBuilder.php');
require_once($baseClassDir . 'repositories/UserRepository.php');
require_once($baseClassDir . 'repositories/ChangePasswordRepository.php');

class ResetPasswordForUserUseCase
{
  protected User $userModel;
  protected Converters $converters;
  protected ResponseBuilder $responseBuilder;
  protected UserRepository $userRepo;
  protected ChangePasswordRepository $changePasswordRepo;

  public function __construct(protected $db, $crypt, protected $syslog, $userModel = null)
  {
    $this->userModel = $userModel === null ? new User($db, $crypt, $syslog) : $userModel;
    $this->converters = new Converters($db);
    $this->responseBuilder = new ResponseBuilder();
    $this->userRepo = new UserRepository($db);
    $this->changePasswordRepo = new ChangePasswordRepository($db);
  }

  public function execute($userHashId, $updaterId): mixed
  {
    $userId = $this->converters->checkUserId($userHashId);
    $userBaseData = $this->userModel->getUserBaseData($userId);
    if ($userBaseData['error_code'] != 0) {
      error_log("Error fetching user data for user {$userHashId}");
      return $this->responseBuilder->error();
    }
    $user = [...$userBaseData['data'], 'id' => $userId];

    $this->db->beginTransaction("SERIALIZABLE");
    try {
      $new_temp_password = null;
      if (empty($user['email'])) {
          $new_temp_password = $this->userModel->generate_pass();
      }
      $patchedUser = $this->userRepo->patchUserBaseData([
        'hash_id' => $user['hash_id'],
        'updater_id' => $updaterId,
        # temp_pw is part of the JWT, so we will need to ask the user to renew the token
        'temp_pw' => $new_temp_password,
        # refresh_token flag atm means "you need to renew your JWT", and it's checked in check_jwt
        'refresh_token' => true,
        # user never changed their password (at the moment completely unused)
        'pw_changed' => 0,
        'pw' => null
      ]);
      if ($user['email']) {
        $insertId = $this->addChangePasswordForUpdateAndScheduleSendEmail($user, $updaterId);
      }
      $this->syslog->addSystemEvent(0, "User's ({$userHashId}) password was reset by {$updaterId}", 0, "", 1, $updaterId);
      $this->db->commitTransaction();
      return $this->responseBuilder->success($patchedUser);
    } catch (\Throwable $t) {
      error_log("Resetting password for user {$userHashId} has failed due to: " . $t->getMessage());
      $this->db->rollbackTransaction();
      return $this->responseBuilder->error();
    }
  }

  private function addChangePasswordForUpdateAndScheduleSendEmail($user, $updater_id)
  {
    $this->changePasswordRepo->deleteByUserId($user['id']);
    $secret = $this->changePasswordRepo->create($user['id']);
    if ($secret === null) {
      throw new RuntimeException("Error creating change password entry");
    }

    // @TODO: refactor into "new SendForgotPasswordEmailCommand(email, realname, username, secret)"
    //   or $commandRepo->save(
    //        CommandBuilder::forSendEmail()
    //          ->emailType(USER_FORGOT_PASSWORD)
    //          ->userData(email, realname, username)#, secret)
    //          #->extra(['secret' => secret])
    //          ->scheduleAt(date()) // $commandRepo->save(...) should reformat the date into mysql format date("Y-m-d H:i:s")
    //          ->build()
    //      );

    // cmd_id = 11 (1 - user, 1 - sendEmail)
    $this->db->query(<<<EOD
      INSERT INTO au_commands
        (cmd_id, command, parameters, date_start, active, status, target_id, creator_id, created, last_update, updater_id)
      VALUES
        (11, "sendEmail",:parameters, NOW(),      1,      0,     :target_id,:updater_id, NOW(),   NOW(),      :updater_id)
    EOD);
    $this->db->bindAll([
      ':updater_id' => $updater_id,
      ':target_id' => $user['id'],
      ':parameters' => "userForgotPassword;{$user['email']};{$user['realname']};{$user['username']};{$secret}"
    ]);
    $this->db->execute();
    if (($insertId = $this->db->lastInsertId()) <= 0) {
      throw new RuntimeException("Error scheduling reset password email command.");
    }

    return $insertId;
  }
}
