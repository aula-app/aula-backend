<?php

require_once(__DIR__ . '/../../../../config/base_config.php');
global $baseHelperDir;
global $baseClassDir;
require_once($baseHelperDir . 'ResponseBuilder.php');
require_once($baseClassDir . 'repositories/UserRepository.php');

class ResetPasswordForUserUseCase
{
  protected User $userModel;
  protected Converters $converters;
  protected ResponseBuilder $responseBuilder;
  protected UserRepository $userRepo;

  public function __construct(protected $db, $crypt, protected $syslog, $userModel = null)
  {
    $this->userModel = $userModel === null ? new User($db, $crypt, $syslog) : $userModel;
    $this->converters = new Converters($db);
    $this->responseBuilder = new ResponseBuilder();
    $this->userRepo = new UserRepository($db);
  }

  public function execute($user_hash_id, $updater_id): mixed
  {
    $userId = $this->converters->checkUserId($user_hash_id);
    $userBaseData = $this->userModel->getUserBaseData($userId);
    if ($userBaseData['error_code'] != 0) {
      error_log("Error fetching user data for user {$userId}");
      return $this->responseBuilder->error();
    }
    $user = $userBaseData['data'];

    $tempPass = $this->userModel->generate_pass();
    if ($user['email']) {
      // @TODO: https://github.com/aula-app/aula-frontend/issues/800
      //   erase existing pass and temp pass
      //     $patchedUser = $this->userRepo->patchUserBaseData([
      //       'id' => $user['id'],
      //       'temp_pw' => $tempPass,   // temp_pw is part of the JWT, so we will need to ask the user to renew the token
      //       'refresh_token' => true,  // refresh_token flag atm means "you need to renew your JWT", and it's checked in check_jwt
      //       'pw' => null
      //     ]);
      //   delete from au_change_password
      //   insert into au_change_password
      //   send email
      return $this->responseBuilder->error(1, errorDescription: "Not yet implemented");
    } else {
      $this->db->beginTransaction("SERIALIZABLE");
      try {
        $patchedUser = $this->userRepo->patchUserBaseData([
          'hash_id' => $user_hash_id,
          'temp_pw' => $tempPass,   // temp_pw is part of the JWT, so we will need to ask the user to renew the token
          'refresh_token' => true,  // refresh_token flag atm means "you need to renew your JWT", and it's checked in check_jwt
          'pw' => null
        ]);
        $this->syslog->addSystemEvent(0, "User's ({$user_hash_id}) password was reset by {$updater_id}", 0, "", 1, $updater_id);
        $this->db->commitTransaction();
        return $this->responseBuilder->success($patchedUser);
      } catch (\Throwable $t) {
        error_log("Resetting password for user {$user_hash_id} has failed due to: " . $t->getMessage());
        $this->db->rollbackTransaction();
        return $this->responseBuilder->error();
      }
    }
  }
}
