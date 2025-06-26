<?php

require_once('../base_config.php');
require_once('../error_msg.php');
require('../functions.php');
require_once($baseHelperDir . 'Crypt.php');
require_once($baseHelperDir . 'JWT.php');
require_once('../db.php');

$headers = apache_request_headers();
$code = $headers['aula-instance-code'];

$db = new Database($headers['aula-instance-code']);
$crypt = new Crypt($cryptFile);
$syslog = new Systemlog($db);
$jwt = new JWT($databases[$code]['jwt_key'], $db, $crypt, $syslog);
$media = new Media($db, $crypt, $syslog, $filesDir);

$check_jwt = $jwt->check_jwt();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  return;
}

if ($check_jwt) {
  $file_type = $_POST['fileType'];

  try {
    if (
      !isset($_FILES['file']['error']) ||
      is_array($_FILES['file']['error'])
    ) {
      throw new RuntimeException('Invalid parameters.');
    }


    // Check $_FILES['upfile']['error'] value.
    switch ($_FILES['file']['error']) {
      case UPLOAD_ERR_OK:
        break;
      case UPLOAD_ERR_NO_FILE:
        throw new RuntimeException('No file sent.');
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        throw new RuntimeException('Exceeded filesize limit.');
      default:
        throw new RuntimeException('Unknown errors.');
    }

    // You should also check filesize here.
    if ($_FILES['file']['size'] > 10000000) {
      throw new RuntimeException('Exceeded filesize limit.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (
      false === $ext = array_search(
        $finfo->file($_FILES['file']['tmp_name']),
        array(
          'jpg' => 'image/jpeg',
          'png' => 'image/png',
          'gif' => 'image/gif',
        ),
        true
      )
    ) {
      throw new RuntimeException('Invalid file format.');
    }

    $random_part = bin2hex(random_bytes(8)) . number_format(microtime(true), 0, '', '');
    $file_name = sha1_file($_FILES['file']['tmp_name']) . $random_part . "." . $ext;
    $file_path = sprintf($filesDir . '/%s', $file_name);
    if (
      !move_uploaded_file(
        $_FILES['file']['tmp_name'],
        $file_path
      )
    ) {
      throw new RuntimeException('Failed to move uploaded file.');
    } else {
      $jwt_payload = $jwt->payload();
      $user_id = $jwt_payload->user_id;

      $inserted_media = $media->addMedia($file_type, $file_path, 1, 0, $file_name, 1, $user_id);
    }

    echo json_encode(["success" => true, "status" => "File is uploaded successfully.", "data" => $file_name]);

  } catch (RuntimeException $e) {
    echo $e->getMessage();
  }
}

?>
