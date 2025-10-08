<?php

class ChangePasswordRepository
{
  private $db;

  /**
   ** @param Database $db
   **/
  public function __construct($db)
  {
    $this->db = $db;
  }

  public function deleteByUserId($userId): bool
  {
    $stmt = $this->db->prepareStatement("DELETE FROM au_change_password WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    return $stmt->rowCount() > 0;
  }

  public function create($userId): string|null
  {
    // generate a secret and ensure it is unique in the whole instance
    do {
      $secret = bin2hex(random_bytes(32));
      $secretExists = $this->db->prepareStatement('SELECT 1 FROM au_change_password WHERE secret = :secret FOR UPDATE');
      $secretExists->execute(['secret' => $secret]);
    } while ($secretExists->rowCount() > 0);

    $stmt = $this->db->prepareStatement(<<<EOF
      INSERT INTO au_change_password
        (user_id, secret, created_at)
      VALUES
        (:user_id, :secret, NOW())
    EOF);
    $stmt->execute(['user_id' => $userId, 'secret' => $secret]);
    return $stmt->rowCount() > 0 ? $secret : null;
  }
}
