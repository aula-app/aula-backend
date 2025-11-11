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
      error_log("getRoomsByHashIds Input array of Room hash IDs has entries in bad format: " . json_encode($roomHashIds));
      throw new RuntimeException(/* no info shared on suspicious client activity */);
    }

    $placeholders = implode(',', array_fill(0, count($roomHashIds), '?'));
    $query = "SELECT * FROM {$this->db->au_rooms} WHERE hash_id IN ({$placeholders})";
    $stmt = $this->db->prepareStatement($query);
    $stmt->execute($roomHashIds);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * @param mixed $room_id
   * @param mixed $user_id
   * @param mixed $updater_id
   */
  public function insertOrUpdateUserToRoom($room_id, $user_id, $updater_id): void
  {
    $upsertRelUserRoomStmt = $this->db->prepareStatement("INSERT INTO {$this->db->au_rel_rooms_users} (room_id, user_id, status, created, last_update, updater_id) VALUES (:room_id, :user_id, 1, NOW(), NOW(), :updater_id) ON DUPLICATE KEY UPDATE status = 1, last_update = NOW(), updater_id = :updater_id");
    $upsertRelUserRoomStmt->execute([
      ':room_id' => $room_id,
      ':user_id' => $user_id,
      ':updater_id' => $updater_id
    ]);
  }
}
