# Functions from Group.php

| Function Name               | Parameters                                                                                                                                                                                 | Description                                                                                                           |
|:----------------------------|:-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|:----------------------------------------------------------------------------------------------------------------------|
| __construct                 | $db, $crypt, $syslog                                                                                                                                                                       | only process script if variable $allowed_include is set to 1, otherwise exit this prevents direct call of this script |
| getGroupOrderId             | $orderby                                                                                                                                                                                   | For further description look into code comments.                                                                                              |
| getGroupBaseData            | $group_id                                                                                                                                                                                  | For further description look into code comments.                                                                                              |
| getGroupHashId              | $group_id                                                                                                                                                                                  | For further description look into code comments.                                                                                              |
| setGroupProperty            | $group_id, $property, $prop_value, $updater_id = 0                                                                                                                                         | For further description look into code comments.                                                                                              |
| getGroupVoteBias            | $group_id                                                                                                                                                                                  | For further description look into code comments.                                                                                              |
| getGroupVoteBiasForUser     | $user_id                                                                                                                                                                                   | For further description look into code comments.                                                                                              |
| checkAccesscode             | $group_id, $access_code                                                                                                                                                                    | For further description look into code comments.                                                                                              |
| getUsersInGroup             | $group_id, $status = -1, $offset = 0, $limit = 0, $orderby = 3, $asc = 0                                                                                                                   | For further description look into code comments.                                                                                              |
| emptyGroup                  | $group_id                                                                                                                                                                                  | For further description look into code comments.                                                                                              |
| getGroups                   | $offset = 0, $limit = 0, $orderby = 0, $asc = 1, $status = -1, $extra_where = ""                                                                                                           | For further description look into code comments.                                                                                              |
| editGroup                   | $group_id, $group_name, $description_public = "", $description_internal = "", $internal_info = "", $status = 1, $access_code = "", $vote_bias = 1, $order_importance = 10, $updater_id = 0 | checks if a group with this name is already in database                                                               |
| addGroup                    | $group_name, $description_public = "", $description_internal = "", $internal_info = "", $status = 1, $access_code = 0, $updater_id = 0, $order_importance = 10, $vote_bias = 1             | For further description look into code comments.                                                                                              |
| setGroupStatus              | $group_id, $status, $updater_id = 0                                                                                                                                                        | For further description look into code comments.                                                                                              |
| setGroupVoteBias            | $group_id, $vote_bias = 1, $updater_id = 0                                                                                                                                                 | For further description look into code comments.                                                                                              |
| setGroupVotesPerUser        | $group_id, $votes, $updater_id = 0                                                                                                                                                         | For further description look into code comments.                                                                                              |
| setGroupDescriptionPublic   | $group_id, $about, $updater_id = 0                                                                                                                                                         | For further description look into code comments.                                                                                              |
| setGroupDescriptionInternal | $group_id, $about, $updater_id = 0                                                                                                                                                         | For further description look into code comments.                                                                                              |
| setGroupname                | $group_id, $group_name, $updater_id = 0                                                                                                                                                    | For further description look into code comments.                                                                                              |
| setGroupAccesscode          | $group_id, $access_code, $updater_id = 0                                                                                                                                                   | For further description look into code comments.                                                                                              |
| deleteGroup                 | $group_id, $updater_id = 0                                                                                                                                                                 | For further description look into code comments.                                                                                              |

# Summary of Functionalities


The `Group.php` file is responsible for managing groups within the system. Key functionalities include:

- **Group Management**:
  - Creating, updating, and deleting groups.
  - Managing group-specific details and settings.

- **Data Retrieval**:
  - Fetching data related to groups, such as membership, roles, and statuses.

- **User Interaction**:
  - Facilitating user management within groups, including adding or removing members.

- **Validation and Security**:
  - Ensuring the accuracy and security of group data during interactions.

This file is crucial for organizing and managing groups effectively within the application.
