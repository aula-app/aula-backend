ALTER TABLE `au_users_basedata` ADD
  `token_data_version` int(11) DEFAULT 1 AFTER `refresh_token`;
