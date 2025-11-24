<?php

class UserRepository
{
  private $db;

  /**
   ** @param Database $db
   **/
  public function __construct($db)
  {
    $this->db = $db;
  }

  public function patchUserBaseData($partialUser)
  {
    $setters = ['last_update = ?'];
    $settersValues = [date("Y-m-d H:i:s")];
    $conditionsValues = [];
    $conditions = [];
    $partialUserUpdate = array_diff_key($partialUser, ['id', 'hash_id']);

    foreach ($partialUser as $key => $value) {
      if ($key === 'id' || $key === 'hash_id') {
        $conditions[] = "{$key} = ?";
        $conditionsValues[] = $value;
        continue;
      };
      $setters[] = "{$key} = ?";
      $settersValues[] = $value;
    }

    if (empty($conditions)) {
      throw new RuntimeException("You must include some condition like 'id' or 'hash_id'.");
    }

    $settersString = implode(',', $setters);
    $conditionsString = implode(' AND ', $conditions);
    $query = <<<EOF
      UPDATE {$this->db->au_users_basedata} SET
        {$settersString}
      WHERE
        {$conditionsString};
    EOF;
    $stmt = $this->db->prepareStatement($query);
    $stmt->execute(array_merge($settersValues, $conditionsValues));
    return $partialUserUpdate;
  }
}
