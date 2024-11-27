# Summary of Functionalities


The `Settings.php` file manages system configuration and user-specific settings. Key functionalities include:

- **Configuration Management**:
  - Retrieving, updating, and storing system-wide configuration settings.

- **User Preferences**:
  - Handling user-specific settings and preferences for customizing their experience.

- **Database Interaction**:
  - Reading and writing configuration data from and to the database.

- **Validation**:
  - Ensuring the correctness and security of the configuration data.

This file plays a critical role in maintaining the adaptability and stability of the application by enabling configurable parameters and user personalization.

# Functions from Settings.php

| Function Name                 | Parameters                                                     | Description                                                                                                           |
|:------------------------------|:---------------------------------------------------------------|:----------------------------------------------------------------------------------------------------------------------|
| __construct                   | $db, $crypt, $syslog                                           | only process script if variable $allowed_include is set to 1, otherwise exit this prevents direct call of this script |
| hasPermissions                | $user_id, $userlevel, $method, $arguments                      | Check if a user has permission on a specific method                                                                                            |
| getInstanceSettings           |                                                                | returns the instance settings.                                                                                              |
| getGlobalConfig               |                                                                | returns the global configuration |
| getCustomfields               |                                                                | gets the name of the custom fields (idea)                                                                                              |
| setInstanceOnlineMode         | $status, $updater_id = 0                                       | sets the online mode of the instance (0 = deactivating etc.)                                                                                            |
| setInstanceInfo               | $name, $description = "", $updater_id = 0                      | Sets the name and desctiption of this instance                                                                                             |
| setAllowRegistration          | $status, $updater_id = 0                                       | Activates self-registration (future use)                                                                                              |
| setDefaultRoleForRegistration | $role, $updater_id = 0                                         | Sets the default role when a user is registered                                                                                             |
| setOauthStatus                | $status = 0, $updater_id = 0                                   | sets Oauth status (0=disable, 1 = enable)                                                                                            |
| setWorkdays                   | $first_day = 1, $last_day = 5, $updater_id = 0                 | sets the work days 1 being monday and so forth                                                                                             |
| setDefaultEmail               | $email, $updater_id = 0                                        |                                                                                               |
| setDailyStartTime             | $time, $updater_id = 0                                         | sets daily start time (format datetime)                                                                                             |
| setDailyEndTime               | $time, $updater_id = 0                                         | sets daily end time (format datetime)                                                                                              |
| setCustomFields               | $custom_field1_name, $custom_field2_name = "", $updater_id = 0 | sets the names for the custom fields in an idea - 2 fields are possible                                                                                            |
