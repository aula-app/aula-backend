# Functions from Database.php

| Function Name     | Parameters                   | Description                                                                                                                               |
|:------------------|:-----------------------------|:------------------------------------------------------------------------------------------------------------------------------------------|
| __construct       |                              | For further description look into code comments.                                                                                                                  |
| query             | $query                       | Load the database configuration from db config base config table names Set up a PDO connection echo ("ERROR occured: ".$e->getMessage()); |
| bind              | $param, $value, $type = null | For further description look into code comments.                                                                                                                  |
| execute           |                              | For further description look into code comments.                                                                                                                  |
| resultSet         |                              | For further description look into code comments.                                                                                                                  |
| single            |                              | For further description look into code comments.                                                                                                                  |
| rowCount          |                              | For further description look into code comments.                                                                                                                  |
| lastInsertId      |                              | For further description look into code comments.                                                                                                                  |
| beginTransaction  |                              | For further description look into code comments.                                                                                                                  |
| endTransaction    |                              | For further description look into code comments.                                                                                                                  |
| cancelTransaction |                              | For further description look into code comments.                                                                                                                  |
| debugDumpParams   |                              | For further description look into code comments.                                                                                                                  |
| getHost           |                              | For further description look into code comments.                                                                                                                  |
| getUser           |                              | For further description look into code comments.                                                                                                                  |
| getPass           |                              | For further description look into code comments.                                                                                                                  |
| getDbname         |                              | For further description look into code comments.                                                                                                                  |

# Summary of Functionalities


The `Database.php` file is responsible for managing database interactions in the system. Key functionalities include:

- **Database Connection**:
  - Establishing and managing connections to the database.

- **Query Execution**:
  - Running SQL queries (select, insert, update, delete) and handling results.

- **Data Retrieval and Manipulation**:
  - Fetching data from tables and performing CRUD operations.

- **Validation and Error Handling**:
  - Ensuring safe query execution and logging errors for debugging.

This file is critical for facilitating secure and efficient interactions with the database.
