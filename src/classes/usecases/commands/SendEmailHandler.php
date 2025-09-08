<?php

require_once(__DIR__ . '/../../../../config/base_config.php');
require_once "Mail.php";

class SendEmailHandler extends CommandHandler
{
  public const CMD_ID = 11;

  protected $smtp;

  public static function createWith($db, $crypt, $syslog): SendEmailHandler
  {
    global $email_host;
    global $email_port;
    global $email_username;
    global $email_password;
    $smtpParams = array(
      'host' => $email_host,
      'port' => $email_port,
      'auth' => true,
      'username' => $email_username,
      'password' => $email_password
    );

    $instance = new SendEmailHandler($db, $crypt, $syslog);
    $instance->smtp = Mail::factory('smtp', $smtpParams);
    return $instance;
  }

  protected function execute($command): mixed
  {
    $content = "text/html; charset=utf-8";
    $mime = "1.0";
    global $email_from;
    global $email_address;
    global $email_creation_subject;
    global $email_creation_body;
    global $email_forgot_password_subject;
    global $email_forgot_password_body;
    [
      $emailType,
      $email,
      $realname,
      $username,
      $secret
    ] = explode(";", $command['parameters']);

    switch ($emailType) {
      case "userCreated":
        $email_subject = $email_creation_subject;
        $email_body = $email_creation_body;
        break;
      case "userForgotPassword":
        $email_subject = $email_forgot_password_subject;
        $email_body = $email_forgot_password_body;
        break;
    }

    $headers = array(
      'From' => $email_from,
      'To' => $email,
      'Subject' => $email_subject,
      'Reply-To' => $email_address,
      'MIME-Version' => $mime,
      'Content-type' => $content
    );

    $email_body = str_replace("<SECRET_KEY>", $secret, $email_body);
    $email_body = str_replace("<NAME>", $realname, $email_body);
    $email_body = str_replace("<USERNAME>", $username, $email_body);
    $email_body = str_replace("<CODE>", $this->db->code, $email_body);

    $mail = $this->smtp->send($email, $headers, $email_body);
    if ($mail != true && (bool) (preg_match('/fail|err|reject|refuse/', $mail->message))) {
      return (new ResponseBuilder())->error(1, $mail);
    } else {
      return (new ResponseBuilder())->success($mail);
    }
  }

  protected function isValid(mixed $command): bool
  {
    [
      $emailType,
      $email,
      $realname,
      $username,
      $secret
    ] = explode(";", $command['parameters']);
    // @TODO: nikola - make better validation
    return
      (bool) preg_match('/^userCreated|userForgotPassword$/', $emailType) &&
      (bool) preg_match('/^\w+$/', $realname) &&
      (bool) preg_match('/^\w+$/', $secret) &&
      (bool) preg_match('/^\w+$/', $email) &&
      (bool) preg_match('/^\w+$/', $username);
  }
}
