<?php

// @TODO: nikola - this class is supposed to be used from the Controller layer but
//   temporarily we can use it from the classes/models/*.php because they are responsible
//   for the request/response communication

class ResponseBuilder
{
  /**
   * @return array<string,mixed> associative array representing successful response
   */
  public function success(mixed $domainEntity): array
  {
    return [
      'success' => true,
      'error_code' => 0,
      'error' => null,
      'data' => $domainEntity,
      'count' => is_array($domainEntity) ? count($domainEntity) : ((int) !(is_null($domainEntity)))
    ];
  }
  /**
   * @return array<string,mixed> associative array representing error response
   */
  public function error(int $errorCode = 1, string $errorDescription = "Something went wrong", array|null $errors = null): array
  {
    return [
      'success' => false,
      'error_code' => $errorCode,
      'error' => $errorCode == 1 ? $errorDescription : null,
      'errors' => $errors,
      'data' => $errorCode == 1 ? null : $errors,
      'count' => 0
    ];
  }
}
