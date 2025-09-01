<?php

class RoomRepository
{
  private $db;

  /**
   ** @param Database $db
   **/
  public function __construct($db)
  {
    $this->db = $db;
  }

  /**
   ** @param array $roomHashIds
   ** @return array|<missing> array of Rooms if success, otherwise throws RuntimeException
   ** @throws RuntimeException if validation of input fails
   */
  public function getRoomsByHashIds($roomHashIds)
  {
    // fail-fast on input validation
    if (empty($roomHashIds)) {
      return [];
    }
    if (count($roomHashIds) > 100) {
      error_log("Getting more than 100 Rooms at the same time not possible.");
      throw new RuntimeException('Not able to fetch more than 100 rooms at a time');
    }
    if (
      count(array_filter($roomHashIds, function (string $room) {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $room);
      })) != count($roomHashIds)
    ) {
      error_log("getRoomsByHashIds Room hash IDs has entries in bad format: " . json_encode($roomHashIds));
      throw new RuntimeException();
    }

    $query = "SELECT * FROM {$this->db->au_rooms} WHERE hash_id IN ('" .  join("','", $roomHashIds) . "')";
    $this->db->query($query);
    return $this->db->resultSet();
  }

  /**
   * @param mixed $room_id
   * @param mixed $user_id
   * @param mixed $updater_id
   */
  public function insertOrUpdateUserToRoom($room_id, $user_id, $updater_id): void
  {
    $upsertRelUserRoomStmt = $this->db->prepareStatement("INSERT INTO {$this->db->au_rel_rooms_users} (room_id, user_id, status, created, last_update, updater_id) VALUES (:room_id, :user_id, 1, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE room_id = :room_id, user_id = :user_id, status = 1, last_update = NOW(), updater_id = :updater_id");
    $upsertRelUserRoomStmt->execute([
      ':room_id' => $room_id,
      ':user_id' => $user_id,
      ':updater_id' => $updater_id
    ]);
  }
}
