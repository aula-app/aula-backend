<?php

require_once(__DIR__ . '/../../../../config/base_config.php');
require_once "Mail.php";

class SendEmailHandler extends CommandHandler
{
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

    // gethostname() for cli
    $server_name = $_SERVER['SERVER_NAME'] ?? gethostname();

    $headers = array(
      'From' => $email_from,
      'To' => $email,
      'Subject' => $email_subject,
      'Reply-To' => $email_address,
      'MIME-Version' => $mime,
      'Content-type' => $content,
      'Message-Id' => time() . '-' . md5($email_from . $email) . '@' . $server_name,
      'Date' => date('r')
    );

    $email_body = str_replace("<SECRET_KEY>", $secret, $email_body);
    $email_body = str_replace("<NAME>", $realname, $email_body);
    $email_body = str_replace("<USERNAME>", $username, $email_body);
    $email_body = str_replace("<CODE>", $this->db->code, $email_body);

    $mail = $this->smtp->send($email, $headers, $email_body);
    if (PEAR::isError($mail)) {
      error_log("[{$this->db->code}] sendEmail command {$command['id']} failed: " . $mail->getMessage());
      return (new ResponseBuilder())->error(1, $mail->getMessage());
    }
    return (new ResponseBuilder())->success(true);
  }

  protected function isValid(mixed $command): bool
  {
    $parts = explode(";", $command['parameters'] ?? '');
    if (count($parts) !== 5) {
      return false;
    }
    [$emailType, $email, , $username, $secret] = $parts;
    return
      in_array($emailType, ['userCreated', 'userForgotPassword'], true) &&
      filter_var($email, FILTER_VALIDATE_EMAIL) !== false &&
      (bool) preg_match('/^[0-9a-f]+$/', $secret) &&
      strlen($username) > 0;
  }
}
