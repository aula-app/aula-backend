# ************************************************************
# Sequel Ace SQL dump
# Version 20046
#
# https://sequel-ace.com/
# https://github.com/Sequel-Ace/Sequel-Ace
#
# Host: devel.aula.de (MySQL 5.5.5-10.6.12-MariaDB-0ubuntu0.22.04.1)
# Datenbank: aula_db
# Verarbeitungszeit: 2023-06-23 09:34:35 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
SET NAMES utf8mb4;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE='NO_AUTO_VALUE_ON_ZERO', SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Tabellen-Dump au_activitylog
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_activitylog`;

CREATE TABLE `au_activitylog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` smallint(6) DEFAULT NULL COMMENT 'Which type of activity (i.e. 1=login, 2=logout, 3=vote, 4= new idea etc.)',
  `info` varchar(1024) DEFAULT NULL COMMENT 'comment or activity as clear text',
  `group` int(11) DEFAULT NULL COMMENT 'group type of user that triggered the activity',
  `target` int(11) DEFAULT 0 COMMENT 'target of the activity (i.E. vote for a specific idea id)',
  `created` datetime DEFAULT NULL COMMENT 'Date time of the activity',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update is saved if dataset is altered',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_categories
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_categories`;

CREATE TABLE `au_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of category',
  `description_public` text DEFAULT NULL COMMENT 'public descirption, seen in frontend',
  `description_internal` text DEFAULT NULL COMMENT 'only seen by admins',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active 2=archived',
  `created` datetime DEFAULT NULL COMMENT 'create date',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash id of the category',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_commands
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_commands`;

CREATE TABLE `au_commands` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cmd_id` int(11) DEFAULT NULL COMMENT 'command id (i.e. 1=delete user, 2=suspend user, 3=unsuspend user 4=vacation_on, 5=vacation_off etc.))',
  `date_start` datetime DEFAULT NULL COMMENT 'Date and time, when command is executed',
  `active` tinyint(1) DEFAULT NULL COMMENT '0=inactive, 1=active',
  `status` int(11) DEFAULT NULL COMMENT '0=not executed yet, 1=executed, 2=executed with error',
  `info` varchar(1024) DEFAULT NULL COMMENT 'contains comment of person that entered command',
  `target_id` int(11) DEFAULT NULL COMMENT 'id of target that the command relates to - i.E. user id, group id, organisation',
  `creator_id` int(11) DEFAULT NULL COMMENT 'id of user who created the command',
  `created` datetime DEFAULT NULL COMMENT 'create date of the command',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of command',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_comments
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_comments`;

CREATE TABLE `au_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(4096) DEFAULT NULL COMMENT 'content of the comment',
  `sum_likes` int(11) DEFAULT NULL COMMENT 'count of likes for faster acces and less DB queries',
  `user_id` int(11) DEFAULT NULL COMMENT 'id of user that created comment',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active, 2=suspended, 3=reported, 4=archived',
  `created` datetime DEFAULT NULL COMMENT 'datetime of creation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of comment',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user_id of the updater',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash id of the comment',
  `language_id` int(11) DEFAULT NULL COMMENT 'Language_id',
  `idea_id` int(11) DEFAULT NULL COMMENT 'id of the idea',
  `parent_id` int(11) DEFAULT NULL COMMENT 'id of the parent comment (0=first comment)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_consent
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_consent`;

CREATE TABLE `au_consent` (
  `user_id` int(11) NOT NULL COMMENT 'id of user',
  `text_id` int(1) NOT NULL DEFAULT 0 COMMENT 'id of text',
  `consent` tinyint(1) DEFAULT 0 COMMENT '1= user consented 0= user didnt consent 2=user revoked consent',
  `date_consent` datetime DEFAULT NULL COMMENT 'date of consent to terms',
  `date_revoke` datetime DEFAULT NULL COMMENT 'date of revocation',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  `status` int(11) DEFAULT NULL COMMENT 'status of consent',
  PRIMARY KEY (`user_id`,`text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_delegation
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_delegation`;

CREATE TABLE `au_delegation` (
  `user_id_original` int(11) NOT NULL COMMENT 'original user (delegating)',
  `user_id_target` int(11) NOT NULL COMMENT 'receiving user',
  `room_id` int(11) DEFAULT 0 COMMENT 'id where the delegation is in',
  `topic_id` int(11) NOT NULL COMMENT 'id of the topic the delegation is for',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active, 2=suspended',
  `updater_id` int(11) DEFAULT 0 COMMENT 'id of the updating user',
  `created` datetime DEFAULT NULL COMMENT 'created date',
  `last_update` datetime DEFAULT NULL COMMENT 'last update',
  PRIMARY KEY (`user_id_original`,`user_id_target`,`topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Tabellen-Dump au_groups
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_groups`;

CREATE TABLE `au_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(1024) DEFAULT NULL COMMENT 'name of group',
  `description_public` text DEFAULT NULL COMMENT 'public description of group',
  `description_internal` text DEFAULT NULL COMMENT 'internal description, only seen by admins',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active, 2=suspended, 3=archived',
  `internal_info` varchar(2048) DEFAULT NULL COMMENT 'internal info, only visible to admins',
  `created` datetime DEFAULT NULL COMMENT 'datetime of creation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of group',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash id of the group',
  `access_code` varchar(1024) DEFAULT NULL COMMENT 'if set then access code is needed to join group',
  `group_order` int(11) DEFAULT NULL COMMENT 'order htat groups are shown (used for display)',
  `vote_bias` int(11) DEFAULT NULL COMMENT 'votes weight per user in this group',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_ideas
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_ideas`;

CREATE TABLE `au_ideas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` text DEFAULT NULL COMMENT 'content of the idea',
  `sum_likes` int(11) DEFAULT NULL COMMENT 'aggregated likes for faster access, less DB Queries',
  `sum_votes` int(11) DEFAULT NULL COMMENT 'aggregated votes for faster access, less DB Queries',
  `number_of_votes` int(11) DEFAULT NULL COMMENT 'number of votes given for this idea',
  `user_id` int(11) DEFAULT NULL COMMENT 'creator id',
  `votes_available_per_user` int(11) DEFAULT NULL COMMENT 'number of votes that are available per user',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active, 2=suspended, 3=reported, 4=archived 5= in review',
  `language_id` int(11) DEFAULT 0 COMMENT 'id of the language 0=default',
  `created` datetime DEFAULT NULL COMMENT 'create date',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of idea',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash id for this id',
  `order_importance` int(11) DEFAULT NULL COMMENT 'order of appearance / importance',
  `info` text DEFAULT NULL COMMENT 'free text field, can be used to add extra information i.e. for open aula (name of person that had the idea)',
  `updater_id` int(11) DEFAULT NULL COMMENT 'id of the updater',
  `room_id` int(11) DEFAULT NULL COMMENT 'id of the room',
  `is_winner` int(11) DEFAULT NULL COMMENT 'flag that this idea succeeded in the voting phase',
  `approved` int(11) DEFAULT NULL COMMENT 'approved in approval phase',
  `approval_comment` text DEFAULT NULL COMMENT 'comment or reasonig why an idea was diapproved / approved',
  `topic_id` int(11) DEFAULT NULL COMMENT 'id of topic that idea belongs to',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_likes
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_likes`;

CREATE TABLE `au_likes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'id of liking user',
  `object_id` int(11) DEFAULT NULL COMMENT 'id of liked object (idea or comment)',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active, temporarily 2=suspended',
  `created` datetime DEFAULT NULL COMMENT 'create date',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'update date',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash id of the like',
  `object_type` int(11) DEFAULT NULL COMMENT 'type of liked object 1=idea, 2=comment',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_media
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_media`;

CREATE TABLE `au_media` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` int(11) DEFAULT NULL COMMENT 'type of media (1=picture, 2=video, 3= audio 4=pdf etc. etc)',
  `url` varchar(2048) DEFAULT NULL COMMENT 'Url to media',
  `system_type` int(11) DEFAULT NULL COMMENT '0=default, 1=custom',
  `status` tinyint(1) DEFAULT NULL COMMENT '0=inactive, 1=active 2= reported 3=archived',
  `info` varchar(2028) DEFAULT NULL COMMENT 'description',
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of medium (other than filename)',
  `filename` varchar(2048) DEFAULT NULL COMMENT 'filename with extension (without path)',
  `created` datetime DEFAULT NULL COMMENT 'creation date',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash_id of the media',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_messages
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_messages`;

CREATE TABLE `au_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL COMMENT 'user id of the creator (0=system)',
  `headline` varchar(1024) DEFAULT NULL COMMENT 'headline of the news',
  `body` text DEFAULT NULL COMMENT 'news body',
  `publish_date` datetime DEFAULT NULL COMMENT 'date, when the news are published to the dashboards',
  `target_group` int(11) DEFAULT NULL COMMENT 'defines group that should recreive the news (0=all or group id)',
  `target_id` int(11) DEFAULT NULL COMMENT 'user_id of user that should receive the message',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active 2=archive',
  `only_on_dashboard` tinyint(1) DEFAULT NULL COMMENT '0=no (news are also sent to users that have consented to receiving news) 1= only displayed on dashboard, no active sending',
  `created` datetime DEFAULT NULL COMMENT 'date when news were created',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash_id for news post',
  `language_id` int(11) DEFAULT NULL COMMENT 'id of language 0=default',
  `level_of_detail` int(11) DEFAULT NULL COMMENT 'enables the user to filter msgs....the higher the number is the more detailed the msg is (high = an idea X was voted for)',
  `msg_type` int(11) DEFAULT NULL COMMENT 'type id of a msg 1=general news 2=user specific news, 3=idea news etc.',
  `room_id` int(11) DEFAULT NULL COMMENT 'if specified only displayed to room members',
  `pin_to_top` int(11) DEFAULT 0 COMMENT '0=no, 1 = yes',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_phases_global_config
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_phases_global_config`;

CREATE TABLE `au_phases_global_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id of dataset',
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of phase',
  `phase_id` int(11) DEFAULT NULL COMMENT 'id of phase, can be set independently so logical operations can be performed (i.E. phase <10)',
  `duration` int(11) DEFAULT NULL COMMENT 'default duration of phase',
  `time_scale` int(11) DEFAULT NULL COMMENT 'timescale of default duration (0=hours, 1=days, 2=months)',
  `description_public` varchar(4096) DEFAULT NULL COMMENT 'public description of phase',
  `description_internal` varchar(4096) DEFAULT NULL COMMENT 'description only seen by admins',
  `status` tinyint(1) DEFAULT 0 COMMENT '0=inactive, 1=active',
  `type` int(11) DEFAULT NULL COMMENT 'phase type, 0=voting enabled, 1=voting+likes enabled, 2=likes enabled, 3=no votes, no likes etc.)',
  `created` datetime DEFAULT NULL COMMENT 'time of creation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'time of last update',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_phases_topic_config
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_phases_topic_config`;

CREATE TABLE `au_phases_topic_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id of dataset',
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of phase',
  `phase_id` int(11) DEFAULT NULL COMMENT 'id of phase, can be set independently so logical operations can be performed (i.E. phase <10)',
  `duration` int(11) DEFAULT NULL COMMENT 'default duration of phase',
  `time_scale` int(11) DEFAULT NULL COMMENT 'timescale of default duration (0=hours, 1=days, 2=months)',
  `description_public` varchar(4096) DEFAULT NULL COMMENT 'public description of phase',
  `description_internal` varchar(4096) DEFAULT NULL COMMENT 'description only seen by admins',
  `status` tinyint(1) DEFAULT 0 COMMENT '0=inactive, 1=active',
  `type` int(11) DEFAULT NULL COMMENT 'phase type, 0=voting enabled, 1=voting+likes enabled, 2=likes enabled, 3=no votes, no likes etc.)',
  `created` datetime DEFAULT NULL COMMENT 'datetime of creation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of specific phase',
  `updater_id` int(11) DEFAULT NULL COMMENT 'id of the updateing user',
  `topic_id` int(11) DEFAULT NULL COMMENT 'id of the topic',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_categories_ideas
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_categories_ideas`;

CREATE TABLE `au_rel_categories_ideas` (
  `category_id` int(11) NOT NULL COMMENT 'id of category',
  `idea_id` int(11) NOT NULL COMMENT 'id of idea',
  `created` datetime DEFAULT NULL COMMENT 'creation time of relation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of dataset',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`category_id`,`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_categories_media
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_categories_media`;

CREATE TABLE `au_rel_categories_media` (
  `category_id` int(11) NOT NULL COMMENT 'id of category',
  `media_id` int(11) NOT NULL COMMENT 'id of media in mediatable',
  `type` int(11) DEFAULT NULL COMMENT 'position where media is used within category (i.e. profile pic)',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`category_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_groups_media
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_groups_media`;

CREATE TABLE `au_rel_groups_media` (
  `group_id` int(11) NOT NULL COMMENT 'id of group',
  `media_id` int(11) NOT NULL COMMENT 'id of media',
  `type` int(11) DEFAULT NULL COMMENT 'position of media within group (i.e. 0= profile pic)',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active',
  `created` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`group_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_groups_users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_groups_users`;

CREATE TABLE `au_rel_groups_users` (
  `group_id` int(11) NOT NULL COMMENT 'group id',
  `user_id` int(11) NOT NULL COMMENT 'id of user',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active 2=suspended 3=archive',
  `created` datetime DEFAULT NULL COMMENT 'creation time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last udate',
  `updater_id` int(11) DEFAULT NULL COMMENT 'id of the user who did the update',
  PRIMARY KEY (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_ideas_comments
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_ideas_comments`;

CREATE TABLE `au_rel_ideas_comments` (
  `idea_id` int(11) NOT NULL COMMENT 'id of the idea',
  `comment_id` int(11) NOT NULL COMMENT 'id of the comment',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active 2=suspended 3=archive',
  `created` datetime DEFAULT NULL COMMENT 'time of creation',
  `last_update` datetime DEFAULT NULL COMMENT 'last update of dataset',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`idea_id`,`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_ideas_media
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_ideas_media`;

CREATE TABLE `au_rel_ideas_media` (
  `idea_id` int(11) NOT NULL,
  `media_id` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`idea_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_rooms_media
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_rooms_media`;

CREATE TABLE `au_rel_rooms_media` (
  `room_id` int(11) NOT NULL COMMENT 'id of the room',
  `media_id` int(11) NOT NULL COMMENT 'id of the medium in media table',
  `type` int(11) DEFAULT NULL COMMENT 'position within the room (i.E. 0=profile pic)',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active',
  `created` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`room_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_rooms_users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_rooms_users`;

CREATE TABLE `au_rel_rooms_users` (
  `room_id` int(11) NOT NULL COMMENT 'id of the room',
  `user_id` int(11) NOT NULL COMMENT 'id of the user',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active, 2= temporily suspended, 3= historic',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of updater',
  PRIMARY KEY (`room_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_topics_ideas
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_topics_ideas`;

CREATE TABLE `au_rel_topics_ideas` (
  `topic_id` int(11) NOT NULL COMMENT 'id of the topic',
  `idea_id` int(11) NOT NULL COMMENT 'id of the idea',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `created` datetime DEFAULT NULL,
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`topic_id`,`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_topics_media
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_topics_media`;

CREATE TABLE `au_rel_topics_media` (
  `topic_id` int(11) NOT NULL COMMENT 'id of the topic',
  `media_id` int(11) NOT NULL COMMENT 'id of the media in media table',
  `type` int(11) DEFAULT NULL COMMENT 'position within the topic (0=profile pic)',
  `created` datetime DEFAULT NULL COMMENT 'creation date',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`topic_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_user_user
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_user_user`;

CREATE TABLE `au_rel_user_user` (
  `user_id1` int(11) NOT NULL COMMENT 'id of first user',
  `user_id2` int(11) NOT NULL COMMENT 'id of second user',
  `type` int(11) DEFAULT NULL COMMENT 'type of relation 0=associated 1=associated and following / subscribed',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active, 2=suspended 3= archived',
  `created` datetime DEFAULT NULL COMMENT 'create date',
  `last_update` datetime DEFAULT NULL COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'if of user who did the update',
  PRIMARY KEY (`user_id1`,`user_id2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_users_media
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_users_media`;

CREATE TABLE `au_rel_users_media` (
  `user_id` int(11) NOT NULL COMMENT 'id of the user',
  `media_id` int(11) NOT NULL COMMENT 'id of the media in the media table',
  `type` int(11) DEFAULT NULL COMMENT 'position within the user (i.e. 0=profile pic, 1= uploads etc.)',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rel_users_triggers
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_users_triggers`;

CREATE TABLE `au_rel_users_triggers` (
  `user_id` int(11) NOT NULL COMMENT 'id of the user',
  `trigger_id` int(11) NOT NULL COMMENT 'id of the trigger',
  `user_consent` tinyint(1) DEFAULT NULL COMMENT '0=no 1=yes',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  PRIMARY KEY (`user_id`,`trigger_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_reported
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_reported`;

CREATE TABLE `au_reported` (
  `user_id` int(11) NOT NULL COMMENT 'id of the reporting user',
  `type` int(11) NOT NULL COMMENT 'type of reported object 0=idea, 1=comment, 2=user',
  `object_id` int(11) NOT NULL COMMENT 'id of reported object (i.e. idea)',
  `status` int(11) DEFAULT NULL COMMENT '0=new 1=acknowledged by admin',
  `created` datetime DEFAULT NULL COMMENT 'create date',
  `last_update` datetime DEFAULT NULL COMMENT 'last update',
  `reason` text DEFAULT NULL COMMENT 'reason for reporting',
  `internal_info` text DEFAULT NULL COMMENT 'internal notes on this reporting',
  PRIMARY KEY (`user_id`,`object_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Tabellen-Dump au_roles
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_roles`;

CREATE TABLE `au_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` int(11) DEFAULT NULL COMMENT 'name of role',
  `description_public` text DEFAULT NULL COMMENT 'description useable in frontend',
  `description_internal` text DEFAULT NULL COMMENT 'description only seen by admins',
  `order` int(11) DEFAULT NULL COMMENT 'used for sorting in display in frontend',
  `rights_level` int(11) DEFAULT NULL COMMENT '0=view_only, 10=std_user, 20=privileged user1, 30=privileged user 2, 40=priviledged user 5, 50=admin, 60=tech admin',
  `status` tinyint(1) DEFAULT NULL COMMENT '0=inactive, 1=active 2=suspended 3=archived',
  `created` datetime DEFAULT NULL COMMENT 'time of creation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of dataset',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash id of the role',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_rooms
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rooms`;

CREATE TABLE `au_rooms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'dataset id',
  `room_name` varchar(1024) DEFAULT NULL COMMENT 'Name of the room',
  `description_public` text DEFAULT NULL COMMENT 'public descirption of the room',
  `description_internal` text DEFAULT NULL COMMENT 'info, only visible to admins',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active 2= suspended, 3=archived',
  `restrict_to_roomusers_only` tinyint(1) DEFAULT NULL COMMENT '1=yes, only users that are part of this room can view and vote',
  `order_importance` int(11) DEFAULT NULL COMMENT 'order - useable for display purposes or logical operations',
  `created` datetime DEFAULT NULL COMMENT 'Date time when room was created',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'Last update of room',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user_id of the updater',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hashed id of the room',
  `access_code` varchar(1024) DEFAULT NULL COMMENT 'if set, user needs access code to access room',
  `internal_info` text DEFAULT NULL COMMENT 'internal info and notes on this room',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_services_config
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_services_config`;

CREATE TABLE `au_services_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of the service',
  `type` int(11) DEFAULT NULL COMMENT 'type of service (0=authentication, 1=push notification etc.)',
  `url` text DEFAULT NULL COMMENT 'URL to service',
  `api_secret` varchar(4096) DEFAULT NULL COMMENT 'secret used for service',
  `api_key` text DEFAULT NULL COMMENT 'public key used',
  `api_tok` text DEFAULT NULL COMMENT 'token for api if necessary',
  `parameter1` text DEFAULT NULL COMMENT 'miscellaneous parameter',
  `parameter2` text DEFAULT NULL COMMENT 'miscellaneous parameter',
  `parameter3` text DEFAULT NULL COMMENT 'miscellaneous parameter',
  `parameter4` text DEFAULT NULL COMMENT 'miscellaneous parameter',
  `parameter5` text DEFAULT NULL COMMENT 'miscellaneous parameter',
  `parameter6` text DEFAULT NULL COMMENT 'miscellaneous parameter',
  `description_public` text DEFAULT NULL COMMENT 'Description for public view',
  `description_internal` text DEFAULT NULL COMMENT 'Descirption for internal view only (i.E. seen by admins)',
  `status` tinyint(1) DEFAULT NULL COMMENT '0=inactive, 1=active',
  `order` int(11) DEFAULT NULL COMMENT 'order for frontend display',
  `created` datetime DEFAULT NULL COMMENT 'time of creation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash_id of the service',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_system_current_state
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_system_current_state`;

CREATE TABLE `au_system_current_state` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `online_mode` tinyint(1) DEFAULT NULL COMMENT '0=off, 1=on, 2=off (weekend) 3=off (vacation) 4=off (holiday)',
  `revert_to_on_active` tinyint(1) DEFAULT NULL COMMENT 'auto turn back on active (1) or inactive (0)',
  `revert_to_on_date` datetime DEFAULT NULL COMMENT 'date and time, when system gets back online',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_system_global_config
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_system_global_config`;

CREATE TABLE `au_system_global_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of organisation',
  `internal_hash_id` varchar(2048) DEFAULT NULL COMMENT 'hash id within the organisation',
  `external_hash_id` varchar(2048) DEFAULT NULL COMMENT 'hash id of the organisation to the outside world (to hosting provider)',
  `description_public` text DEFAULT NULL COMMENT 'text that is publically displayed on the frontend (if needed)',
  `base_url` varchar(2048) DEFAULT NULL COMMENT 'base url of the organisation instance (aula system)',
  `media_url` varchar(2048) DEFAULT NULL COMMENT 'url for media contents',
  `preferred_language` int(11) DEFAULT NULL COMMENT 'id for the default language',
  `date_format` int(11) DEFAULT NULL COMMENT 'id for the date format',
  `time_format` int(11) DEFAULT NULL COMMENT 'id for the time format',
  `first_workday_week` int(11) DEFAULT NULL COMMENT 'id for the first workday (1=monday, 2=tuesday etc.)',
  `last_workday_week` int(11) DEFAULT NULL COMMENT 'id for the last workday (1=monday, 2=tuesday etc.)',
  `start_time` datetime DEFAULT NULL COMMENT 'regular starting time',
  `daily_end_time` datetime DEFAULT NULL COMMENT 'regular end_time',
  `allow_regisitration` tinyint(1) DEFAULT NULL COMMENT '0=no 1=yes',
  `default_role_for_registration` int(11) DEFAULT NULL COMMENT 'role id for new self registered users',
  `default_email_address` varchar(1024) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL COMMENT 'default fallback e-mail adress',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of updater',
  `archive_after` int(11) DEFAULT NULL COMMENT 'number of days after which content is automatically archived',
  `organisation_type` int(11) DEFAULT NULL COMMENT '0=school, 1=other organisation - for term set',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_systemlog
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_systemlog`;

CREATE TABLE `au_systemlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` int(11) DEFAULT NULL COMMENT '0=standard, 1=warning, 2=error 3=nuke error',
  `message` text DEFAULT NULL COMMENT 'entry message / error message',
  `usergroup` int(11) DEFAULT NULL COMMENT 'group (if available) that caused the error / activity',
  `url` varchar(2048) DEFAULT NULL COMMENT 'url where event occured (i.e. error)',
  `created` datetime DEFAULT NULL COMMENT 'creation of logentry',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of this entry',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_texts
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_texts`;

CREATE TABLE `au_texts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id',
  `creator_id` int(11) DEFAULT NULL COMMENT 'user id of the creator',
  `headline` varchar(1024) DEFAULT NULL COMMENT 'headline of the text',
  `body` text DEFAULT NULL COMMENT 'the actual text',
  `user_needs_to_consent` tinyint(1) DEFAULT NULL COMMENT '0=no consent is necessary / pure display 1= consent is necessary to use app (strict) 2= consent is necessary to use specific service (service id) 3=user needs to give consent so that the text is not displayed anymore 2= user needs to consent to use aula',
  `service_id_consent` int(11) DEFAULT NULL COMMENT 'id of the service that the consent applies to',
  `consent_text` varchar(512) DEFAULT NULL COMMENT 'text that is displayed to user for consent (i.e. please consentz to the upper terms)',
  `language_id` int(11) DEFAULT NULL COMMENT 'id_of the language (0=default)',
  `location` int(11) DEFAULT NULL COMMENT 'location (page) where the text is shown (id can be used in the frontend)',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last_update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user_id of updater',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash_id of the text',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_topics
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_topics`;

CREATE TABLE `au_topics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id of the topic',
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of topic',
  `description_public` text DEFAULT NULL COMMENT 'public description of the topic (seen in frontend)',
  `description_internal` text DEFAULT NULL COMMENT 'description only seen by admins',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active 2=archived',
  `order_importance` int(11) DEFAULT NULL COMMENT 'order bias fro displaying in frontend',
  `created` datetime DEFAULT NULL COMMENT 'creation time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash id of the topic',
  `updater_id` int(11) DEFAULT 0 COMMENT 'id of the user that does the update',
  `room_id` int(11) DEFAULT 0 COMMENT 'id of the room the topic is in',
  `phase_id` int(11) DEFAULT 1 COMMENT 'if o phase the thopic is in',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_triggers
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_triggers`;

CREATE TABLE `au_triggers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trigger_id` int(11) DEFAULT NULL COMMENT 'id of the trigger',
  `name_public` varchar(512) DEFAULT NULL COMMENT 'public name of the trigger (i.e. "receive updates when there is a new idea)',
  `name_internal` varchar(512) DEFAULT NULL COMMENT 'internal name of the trigger, only admins',
  `description_public` text DEFAULT NULL COMMENT 'descirption of the trigger / helptext',
  `description_internal` text DEFAULT NULL COMMENT 'descirption of the trigger / helptext',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active 2=suspended',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the last updater',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_user_levels
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_user_levels`;

CREATE TABLE `au_user_levels` (
  `level` int(11) NOT NULL COMMENT 'id of level',
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of level',
  `description` text DEFAULT NULL COMMENT 'description of userlevel / rights',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active',
  PRIMARY KEY (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



# Tabellen-Dump au_users_basedata
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_users_basedata`;

CREATE TABLE `au_users_basedata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `realname` varchar(2048) DEFAULT NULL COMMENT 'real name of the user',
  `displayname` varchar(1024) DEFAULT NULL COMMENT 'name displayed in frontend',
  `username` varchar(512) DEFAULT NULL COMMENT 'username of user should be email address',
  `email` varchar(2048) DEFAULT NULL COMMENT 'email address',
  `pw` varchar(2048) DEFAULT NULL COMMENT 'pw',
  `position` varchar(1024) DEFAULT NULL COMMENT 'position within the organisation - not mandatory',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hashed id userspecific',
  `about_me` text DEFAULT NULL COMMENT 'about me text',
  `registration_status` int(11) DEFAULT NULL COMMENT 'Registration status 0=new, 1=in registration 2=completed',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active 2=suspended 3=archive',
  `created` datetime DEFAULT NULL COMMENT 'created time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user_id of the updater',
  `bi` varchar(1024) DEFAULT NULL COMMENT 'blind index',
  `userlevel` int(11) DEFAULT NULL COMMENT 'level of the user (access rights)',
  `infinite_votes` int(11) DEFAULT NULL COMMENT '0=inactive 1= active (this user has infinite votes)',
  `last_login` datetime DEFAULT NULL COMMENT 'date of last login',
  `presence` int(11) DEFAULT 1 COMMENT '0 = user is absent, 1= user is present',
  `absent_until` datetime DEFAULT NULL COMMENT 'date until the user is absent',
  `auto_delegation` int(11) DEFAULT 0 COMMENT '1=on, 0=off - if user is absent, votes are  ',
  `trustee_id` int(11) DEFAULT NULL COMMENT 'id othe the trusted user the votes are delegated to when user is absent (only when auto delegation is on)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_users_settings
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_users_settings`;

CREATE TABLE `au_users_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'id of the user',
  `external_service_login` int(11) DEFAULT NULL COMMENT 'SSO / OAuth2 login 0=no 1=yes',
  `created` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  `external_service_id` int(11) DEFAULT NULL COMMENT 'id of the used service for authentication',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_votes
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_votes`;

CREATE TABLE `au_votes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'id of voting user',
  `idea_id` int(11) DEFAULT NULL COMMENT 'id of idea',
  `vote_value` int(11) DEFAULT NULL COMMENT 'value of the vote (-1, 0, +1)',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active, 2= temporarily suspended',
  `created` datetime DEFAULT NULL COMMENT 'time of first creation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update on this dataset',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash id of the vote',
  `vote_weight` int(11) DEFAULT NULL COMMENT 'absolute value for given votes,  neutral = 1 or =vote_value',
  `number_of_delegations` int(11) DEFAULT NULL COMMENT 'number of delegated votes included',
  `comment` varchar(2048) DEFAULT NULL COMMENT 'Comment that the user added to a vote he did',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_votes_available
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_votes_available`;

CREATE TABLE `au_votes_available` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) DEFAULT NULL COMMENT 'id of the idea',
  `original_user_id` int(11) DEFAULT NULL COMMENT 'id of the original user',
  `current_user_id` int(11) DEFAULT NULL COMMENT 'id of the current user in ownership of the vote',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active 2=suspended 3=archived',
  `state` int(11) DEFAULT NULL COMMENT '0=not used 1=used (no longer available)',
  `created` datetime DEFAULT NULL COMMENT 'creation date (usually idea creation date)',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user_id of the updater',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash id of the vote',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_votes_tracking
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_votes_tracking`;

CREATE TABLE `au_votes_tracking` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vote_id` int(11) DEFAULT NULL COMMENT 'id of the vote',
  `current_owner_id` int(11) DEFAULT NULL COMMENT 'id of current owner',
  `previous_owner_id` int(11) DEFAULT NULL COMMENT 'if of previous owner',
  `iteration` int(11) DEFAULT NULL COMMENT 'step of delegation (1st, 2nd, 3rd)',
  `idea_id` int(11) DEFAULT NULL COMMENT 'id of the id',
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
