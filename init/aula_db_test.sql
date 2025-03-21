/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.4.3-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: aula_Q9Ik0
-- ------------------------------------------------------
-- Server version	11.4.3-MariaDB-ubu2310

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `au_activitylog`
--

DROP TABLE IF EXISTS `au_activitylog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_activitylog`
--

LOCK TABLES `au_activitylog` WRITE;
/*!40000 ALTER TABLE `au_activitylog` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_activitylog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_categories`
--

DROP TABLE IF EXISTS `au_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_categories`
--

LOCK TABLES `au_categories` WRITE;
/*!40000 ALTER TABLE `au_categories` DISABLE KEYS */;
INSERT INTO `au_categories` VALUES
(1,'Formaggio','','cheese',1,'2025-02-19 13:28:15','2025-02-19 13:28:15',1,'38b870e5af03fbb84f2932d480d4b14f'),
(2,'Robot','','bot',1,'2025-02-19 13:28:30','2025-02-19 13:28:30',1,'b51d1289c08716d969b981788654a2b7');
/*!40000 ALTER TABLE `au_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_change_password`
--

DROP TABLE IF EXISTS `au_change_password`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_change_password` (
  `user_id` int(11) DEFAULT NULL,
  `secret` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_change_password`
--

LOCK TABLES `au_change_password` WRITE;
/*!40000 ALTER TABLE `au_change_password` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_change_password` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_commands`
--

DROP TABLE IF EXISTS `au_commands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_commands` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cmd_id` int(11) DEFAULT NULL COMMENT 'command id (i.e. 1=delete user, 2=suspend user, 3=unsuspend user 4=vacation_on, 5=vacation_off etc.))',
  `command` varchar(1024) DEFAULT NULL COMMENT 'command in text form',
  `parameters` varchar(2048) DEFAULT NULL COMMENT 'parameters for the command',
  `date_start` datetime DEFAULT NULL COMMENT 'Date and time, when command is executed',
  `date_end` datetime DEFAULT NULL COMMENT 'Date and time, when command execution ends',
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_commands`
--

LOCK TABLES `au_commands` WRITE;
/*!40000 ALTER TABLE `au_commands` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_commands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_comments`
--

DROP TABLE IF EXISTS `au_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_comments`
--

LOCK TABLES `au_comments` WRITE;
/*!40000 ALTER TABLE `au_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_consent`
--

DROP TABLE IF EXISTS `au_consent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_consent` (
  `user_id` int(11) NOT NULL COMMENT 'id of user',
  `text_id` int(1) NOT NULL DEFAULT 0 COMMENT 'id of text',
  `consent` tinyint(1) DEFAULT 0 COMMENT '1= user consented 0= user didnt consent 2=user revoked consent',
  `date_consent` datetime DEFAULT NULL COMMENT 'date of consent to terms',
  `date_revoke` datetime DEFAULT NULL COMMENT 'date of revocation',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  `status` int(11) DEFAULT 1 COMMENT 'status of consent',
  PRIMARY KEY (`user_id`,`text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_consent`
--

LOCK TABLES `au_consent` WRITE;
/*!40000 ALTER TABLE `au_consent` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_consent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_delegation`
--

DROP TABLE IF EXISTS `au_delegation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_delegation`
--

LOCK TABLES `au_delegation` WRITE;
/*!40000 ALTER TABLE `au_delegation` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_delegation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_groups`
--

DROP TABLE IF EXISTS `au_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `order_importance` int(11) DEFAULT NULL COMMENT 'order htat groups are shown (used for display)',
  `vote_bias` int(11) DEFAULT NULL COMMENT 'votes weight per user in this group',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_groups`
--

LOCK TABLES `au_groups` WRITE;
/*!40000 ALTER TABLE `au_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_ideas`
--

DROP TABLE IF EXISTS `au_ideas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_ideas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` text DEFAULT NULL,
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
  `sum_comments` int(11) DEFAULT 0,
  `custom_field1` text DEFAULT NULL COMMENT 'custom_field1',
  `custom_field2` text DEFAULT NULL COMMENT 'custom_field2',
  `type` int(11) DEFAULT 0 COMMENT 'type of idea 0=std 1=school induced (i.e.survey)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_ideas`
--

LOCK TABLES `au_ideas` WRITE;
/*!40000 ALTER TABLE `au_ideas` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_ideas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_likes`
--

DROP TABLE IF EXISTS `au_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_likes`
--

LOCK TABLES `au_likes` WRITE;
/*!40000 ALTER TABLE `au_likes` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_media`
--

DROP TABLE IF EXISTS `au_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_media` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` int(11) DEFAULT NULL COMMENT 'type of media (1=picture, 2=video, 3= audio 4=pdf etc. etc)',
  `url` varchar(2048) DEFAULT NULL COMMENT 'URL to media (i.e. https://...)',
  `system_type` int(11) DEFAULT NULL COMMENT '0=default, 1=custom',
  `path` varchar(2048) DEFAULT NULL COMMENT 'system path to the file (i.e. /var/www/files/...)',
  `status` tinyint(1) DEFAULT NULL COMMENT '0=inactive, 1=active 2= reported 3=archived',
  `info` varchar(2028) DEFAULT NULL COMMENT 'description',
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of medium (other than filename)',
  `filename` varchar(2048) DEFAULT NULL COMMENT 'filename with extension (without path)',
  `created` datetime DEFAULT NULL COMMENT 'creation date',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `hash_id` varchar(1024) DEFAULT NULL COMMENT 'hash_id of the media',
  `updater_id` int(11) DEFAULT NULL COMMENT 'id of the user that uploaded',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_media`
--

LOCK TABLES `au_media` WRITE;
/*!40000 ALTER TABLE `au_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_messages`
--

DROP TABLE IF EXISTS `au_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_messages`
--

LOCK TABLES `au_messages` WRITE;
/*!40000 ALTER TABLE `au_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_phases_global_config`
--

DROP TABLE IF EXISTS `au_phases_global_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_phases_global_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id of dataset',
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of phase',
  `phase_id` int(11) DEFAULT NULL COMMENT '0=wild idea 10=workphase 20=approval 30=voting 40=implemtation',
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_phases_global_config`
--

LOCK TABLES `au_phases_global_config` WRITE;
/*!40000 ALTER TABLE `au_phases_global_config` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_phases_global_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_categories_ideas`
--

DROP TABLE IF EXISTS `au_rel_categories_ideas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_categories_ideas` (
  `category_id` int(11) NOT NULL COMMENT 'id of category',
  `idea_id` int(11) NOT NULL COMMENT 'id of idea',
  `created` datetime DEFAULT NULL COMMENT 'creation time of relation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of dataset',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`category_id`,`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_categories_ideas`
--

LOCK TABLES `au_rel_categories_ideas` WRITE;
/*!40000 ALTER TABLE `au_rel_categories_ideas` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_categories_ideas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_categories_media`
--

DROP TABLE IF EXISTS `au_rel_categories_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_categories_media` (
  `category_id` int(11) NOT NULL COMMENT 'id of category',
  `media_id` int(11) NOT NULL COMMENT 'id of media in mediatable',
  `type` int(11) DEFAULT NULL COMMENT 'position where media is used within category (i.e. profile pic)',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`category_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_categories_media`
--

LOCK TABLES `au_rel_categories_media` WRITE;
/*!40000 ALTER TABLE `au_rel_categories_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_categories_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_categories_rooms`
--

DROP TABLE IF EXISTS `au_rel_categories_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_categories_rooms` (
  `category_id` int(11) NOT NULL COMMENT 'id of category',
  `room_id` int(11) NOT NULL COMMENT 'id of room',
  `created` datetime DEFAULT NULL COMMENT 'creation time of relation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of dataset',
  `updater_id` int(11) DEFAULT NULL COMMENT 'id of updater',
  PRIMARY KEY (`category_id`,`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_categories_rooms`
--

LOCK TABLES `au_rel_categories_rooms` WRITE;
/*!40000 ALTER TABLE `au_rel_categories_rooms` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_categories_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_groups_media`
--

DROP TABLE IF EXISTS `au_rel_groups_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_groups_media`
--

LOCK TABLES `au_rel_groups_media` WRITE;
/*!40000 ALTER TABLE `au_rel_groups_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_groups_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_groups_users`
--

DROP TABLE IF EXISTS `au_rel_groups_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_groups_users` (
  `group_id` int(11) NOT NULL COMMENT 'group id',
  `user_id` int(11) NOT NULL COMMENT 'id of user',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active 2=suspended 3=archive',
  `created` datetime DEFAULT NULL COMMENT 'creation time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last udate',
  `updater_id` int(11) DEFAULT NULL COMMENT 'id of the user who did the update',
  PRIMARY KEY (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_groups_users`
--

LOCK TABLES `au_rel_groups_users` WRITE;
/*!40000 ALTER TABLE `au_rel_groups_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_groups_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_ideas_comments`
--

DROP TABLE IF EXISTS `au_rel_ideas_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_ideas_comments` (
  `idea_id` int(11) NOT NULL COMMENT 'id of the idea',
  `comment_id` int(11) NOT NULL COMMENT 'id of the comment',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active 2=suspended 3=archive',
  `created` datetime DEFAULT NULL COMMENT 'time of creation',
  `last_update` datetime DEFAULT NULL COMMENT 'last update of dataset',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`idea_id`,`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_ideas_comments`
--

LOCK TABLES `au_rel_ideas_comments` WRITE;
/*!40000 ALTER TABLE `au_rel_ideas_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_ideas_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_ideas_media`
--

DROP TABLE IF EXISTS `au_rel_ideas_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_ideas_media` (
  `idea_id` int(11) NOT NULL,
  `media_id` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`idea_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_ideas_media`
--

LOCK TABLES `au_rel_ideas_media` WRITE;
/*!40000 ALTER TABLE `au_rel_ideas_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_ideas_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_rooms_media`
--

DROP TABLE IF EXISTS `au_rel_rooms_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_rooms_media`
--

LOCK TABLES `au_rel_rooms_media` WRITE;
/*!40000 ALTER TABLE `au_rel_rooms_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_rooms_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_rooms_users`
--

DROP TABLE IF EXISTS `au_rel_rooms_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_rooms_users` (
  `room_id` int(11) NOT NULL COMMENT 'id of the room',
  `user_id` int(11) NOT NULL COMMENT 'id of the user',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive, 1=active, 2= temporily suspended, 3= historic',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of updater',
  PRIMARY KEY (`room_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_rooms_users`
--

LOCK TABLES `au_rel_rooms_users` WRITE;
/*!40000 ALTER TABLE `au_rel_rooms_users` DISABLE KEYS */;
INSERT INTO `au_rel_rooms_users` VALUES
(1,3,1,'2025-02-19 13:36:17','2025-02-19 13:36:17',0),
(1,4,1,'2025-02-19 13:40:13','2025-02-19 13:40:13',0),
(1,5,1,'2025-02-19 13:45:30','2025-02-19 13:45:30',0),
(1,6,1,'2025-02-19 13:48:06','2025-02-19 13:48:06',0),
(1,7,1,'2025-02-19 13:53:06','2025-02-19 13:53:06',0),
(1,8,1,'2025-02-19 13:54:34','2025-02-19 13:54:34',0),
(1,9,1,'2025-02-19 13:54:52','2025-02-19 13:54:52',0),
(1,10,1,'2025-02-19 13:55:05','2025-02-19 13:55:05',0),
(2,4,1,'2025-02-19 13:42:18','2025-02-19 13:42:18',1),
(3,5,1,'2025-02-19 13:46:21','2025-02-19 13:46:21',1),
(4,6,1,'2025-02-19 13:48:22','2025-02-19 13:48:22',1);
/*!40000 ALTER TABLE `au_rel_rooms_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_topics_ideas`
--

DROP TABLE IF EXISTS `au_rel_topics_ideas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_topics_ideas` (
  `topic_id` int(11) NOT NULL COMMENT 'id of the topic',
  `idea_id` int(11) NOT NULL COMMENT 'id of the idea',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `created` datetime DEFAULT NULL,
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`topic_id`,`idea_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_topics_ideas`
--

LOCK TABLES `au_rel_topics_ideas` WRITE;
/*!40000 ALTER TABLE `au_rel_topics_ideas` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_topics_ideas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_topics_media`
--

DROP TABLE IF EXISTS `au_rel_topics_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_topics_media` (
  `topic_id` int(11) NOT NULL COMMENT 'id of the topic',
  `media_id` int(11) NOT NULL COMMENT 'id of the media in media table',
  `type` int(11) DEFAULT NULL COMMENT 'position within the topic (0=profile pic)',
  `created` datetime DEFAULT NULL COMMENT 'creation date',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`topic_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_topics_media`
--

LOCK TABLES `au_rel_topics_media` WRITE;
/*!40000 ALTER TABLE `au_rel_topics_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_topics_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_user_user`
--

DROP TABLE IF EXISTS `au_rel_user_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_user_user`
--

LOCK TABLES `au_rel_user_user` WRITE;
/*!40000 ALTER TABLE `au_rel_user_user` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_user_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_users_media`
--

DROP TABLE IF EXISTS `au_rel_users_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_users_media` (
  `user_id` int(11) NOT NULL COMMENT 'id of the user',
  `media_id` int(11) NOT NULL COMMENT 'id of the media in the media table',
  `type` int(11) DEFAULT NULL COMMENT 'position within the user (i.e. 0=profile pic, 1= uploads etc.)',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`,`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_users_media`
--

LOCK TABLES `au_rel_users_media` WRITE;
/*!40000 ALTER TABLE `au_rel_users_media` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_users_media` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rel_users_triggers`
--

DROP TABLE IF EXISTS `au_rel_users_triggers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_rel_users_triggers` (
  `user_id` int(11) NOT NULL COMMENT 'id of the user',
  `trigger_id` int(11) NOT NULL COMMENT 'id of the trigger',
  `user_consent` tinyint(1) DEFAULT NULL COMMENT '0=no 1=yes',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of the updater',
  PRIMARY KEY (`user_id`,`trigger_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rel_users_triggers`
--

LOCK TABLES `au_rel_users_triggers` WRITE;
/*!40000 ALTER TABLE `au_rel_users_triggers` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_rel_users_triggers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_reported`
--

DROP TABLE IF EXISTS `au_reported`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_reported`
--

LOCK TABLES `au_reported` WRITE;
/*!40000 ALTER TABLE `au_reported` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_reported` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_roles`
--

DROP TABLE IF EXISTS `au_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_roles`
--

LOCK TABLES `au_roles` WRITE;
/*!40000 ALTER TABLE `au_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_rooms`
--

DROP TABLE IF EXISTS `au_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `phase_duration_0` int(11) DEFAULT 0 COMMENT 'phase duration 0',
  `phase_duration_1` int(11) DEFAULT 0 COMMENT 'phase_duration_1',
  `phase_duration_2` int(11) DEFAULT 0 COMMENT 'phase_duration_2',
  `phase_duration_3` int(11) DEFAULT 0 COMMENT 'phase_duration_3',
  `phase_duration_4` int(11) DEFAULT 0 COMMENT 'phase_duration_4',
  `type` int(11) DEFAULT 0 COMMENT '0 = standard room 1 = MAIN ROOM (aula)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rooms`
--

LOCK TABLES `au_rooms` WRITE;
/*!40000 ALTER TABLE `au_rooms` DISABLE KEYS */;
INSERT INTO `au_rooms` VALUES
(1,'Schule',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'965bbe32b06bbb58f1e28cfd78906371',NULL,NULL,0,0,0,0,0,1),
(2,'user','user room','',1,1,10,'2025-02-19 13:36:43','2025-02-19 13:36:43',1,'a1af19831c8edea6c8b120939756fe25','$2y$10$0jLLfUN4rxkOG.qswOPCrugPPLUdMUzqaUUUeM/pnRx69UFJk5A8i','',0,5,5,5,5,0),
(3,'moderator','moderator','',1,1,10,'2025-02-19 13:46:20','2025-02-19 13:46:20',1,'6ada88a18ccc458a8f047303cb79096b','$2y$10$Ul2MeYGC15QVz99SPVfumehQRPJdz18MNc59lquz5jzH5jff4zQy6','',0,5,5,5,5,0),
(4,'moderator V','moderator with voting rights','',1,1,10,'2025-02-19 13:47:47','2025-02-19 13:47:47',1,'4b036414d99256f4e49fdb6ad56b1366','$2y$10$vyd61DyAdrSaglWQARtKYOMG/vUfNIzptGpLubnB209H.T0N8mOrW','',0,5,5,5,5,0),
(5,'super_moderator','super\\_moderator','',1,1,10,'2025-02-19 13:52:49','2025-02-19 13:52:49',1,'a6e1d5a4b7403799221a99fae04c2344','$2y$10$y3NAqXbxCyEqQwMP1d61B.KVmseK2UPWLYaltuwqRZuiGyPkYH4p2','',0,5,5,5,5,0);
/*!40000 ALTER TABLE `au_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_services`
--

DROP TABLE IF EXISTS `au_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of the service',
  `type` int(11) DEFAULT NULL COMMENT 'type of service (0=authentication, 1=push notification etc.)',
  `url` text DEFAULT NULL COMMENT 'URL to service',
  `return_url` text DEFAULT NULL COMMENT 'return url to main system',
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_services`
--

LOCK TABLES `au_services` WRITE;
/*!40000 ALTER TABLE `au_services` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_system_current_state`
--

DROP TABLE IF EXISTS `au_system_current_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_system_current_state` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `online_mode` tinyint(1) DEFAULT NULL COMMENT '0=off, 1=on, 2=off (weekend) 3=off (vacation) 4=off (holiday)',
  `revert_to_on_active` tinyint(1) DEFAULT NULL COMMENT 'auto turn back on active (1) or inactive (0)',
  `revert_to_on_date` datetime DEFAULT NULL COMMENT 'date and time, when system gets back online',
  `created` datetime DEFAULT NULL COMMENT 'create time',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_system_current_state`
--

LOCK TABLES `au_system_current_state` WRITE;
/*!40000 ALTER TABLE `au_system_current_state` DISABLE KEYS */;
INSERT INTO `au_system_current_state` VALUES
(1,1,NULL,NULL,NULL,'2025-02-19 13:27:47',1);
/*!40000 ALTER TABLE `au_system_current_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_system_global_config`
--

DROP TABLE IF EXISTS `au_system_global_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `allow_registration` tinyint(1) NOT NULL COMMENT '0=no 1=yes',
  `default_role_for_registration` int(11) DEFAULT NULL COMMENT 'role id for new self registered users',
  `default_email_address` varchar(1024) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL COMMENT 'default fallback e-mail adress',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of updater',
  `archive_after` int(11) DEFAULT NULL COMMENT 'number of days after which content is automatically archived',
  `organisation_type` int(11) DEFAULT NULL COMMENT '0=school, 1=other organisation - for term set',
  `enable_oauth` int(11) DEFAULT 0 COMMENT '0 = disable,1 = enable',
  `custom_field1_name` text DEFAULT NULL COMMENT 'Name custom field 1',
  `custom_field2_name` text DEFAULT NULL COMMENT 'Name custom field 2',
  `quorum_wild_ideas` int(11) DEFAULT 80 COMMENT 'percentage (i.e. 80) for wild idea quorum',
  `quorum_votes` int(11) DEFAULT 80 COMMENT 'percentage (i.e. 80) for votes',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_system_global_config`
--

LOCK TABLES `au_system_global_config` WRITE;
/*!40000 ALTER TABLE `au_system_global_config` DISABLE KEYS */;
INSERT INTO `au_system_global_config` VALUES
(1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,80,80);
/*!40000 ALTER TABLE `au_system_global_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_systemlog`
--

DROP TABLE IF EXISTS `au_systemlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_systemlog`
--

LOCK TABLES `au_systemlog` WRITE;
/*!40000 ALTER TABLE `au_systemlog` DISABLE KEYS */;
INSERT INTO `au_systemlog` VALUES
(1,0,'Successful login user aula',0,'','2025-02-19 13:16:44','2025-02-19 13:16:44',0),
(2,0,'Successful login user tech_aula',0,'','2025-02-19 13:17:04','2025-02-19 13:17:04',0),
(3,0,'Successful login user tech_aula',0,'','2025-02-19 13:17:47','2025-02-19 13:17:47',0),
(4,0,'Online mode set to 1',0,'','2025-02-19 13:17:47','2025-02-19 13:17:47',0),
(5,0,'Online mode set to 1',0,'','2025-02-19 13:17:47','2025-02-19 13:17:47',0),
(6,0,'Successful login user admin',0,'','2025-02-19 13:18:22','2025-02-19 13:18:22',0),
(7,0,'Successful login user tech_admin',0,'','2025-02-19 13:18:30','2025-02-19 13:18:30',0),
(8,0,'Online mode set to 1',0,'','2025-02-19 13:18:31','2025-02-19 13:18:31',0),
(9,0,'Online mode set to 1',0,'','2025-02-19 13:18:31','2025-02-19 13:18:31',0),
(10,0,'Successful login user admin',0,'','2025-02-19 13:23:40','2025-02-19 13:23:40',0),
(11,0,'Online mode set to 1',0,'','2025-02-19 13:27:47','2025-02-19 13:27:47',0),
(12,0,'Online mode set to 1',0,'','2025-02-19 13:27:47','2025-02-19 13:27:47',0),
(13,0,'Added new category (#1) Formaggio',0,'','2025-02-19 13:28:15','2025-02-19 13:28:15',0),
(14,0,'Added new category (#2) Robot',0,'','2025-02-19 13:28:30','2025-02-19 13:28:30',0),
(15,0,'Added user 3 to standard room 1',0,'','2025-02-19 13:36:17','2025-02-19 13:36:17',0),
(16,0,'Added new user 3',0,'','2025-02-19 13:36:17','2025-02-19 13:36:17',0),
(17,0,'Added new room (#2) user',0,'','2025-02-19 13:36:43','2025-02-19 13:36:43',0),
(18,0,'Successful login user guest',0,'','2025-02-19 13:37:14','2025-02-19 13:37:14',0),
(19,0,'User pw changed 3 by 0',0,'','2025-02-19 13:37:23','2025-02-19 13:37:23',0),
(20,0,'Successful login user guest',0,'','2025-02-19 13:39:41','2025-02-19 13:39:41',0),
(21,0,'Successful login user admin',0,'','2025-02-19 13:39:56','2025-02-19 13:39:56',0),
(22,0,'Added user 4 to standard room 1',0,'','2025-02-19 13:40:13','2025-02-19 13:40:13',0),
(23,0,'Added new user 4',0,'','2025-02-19 13:40:13','2025-02-19 13:40:13',0),
(24,0,'Successful login user user',0,'','2025-02-19 13:40:32','2025-02-19 13:40:32',0),
(25,0,'User pw changed 4 by 0',0,'','2025-02-19 13:40:48','2025-02-19 13:40:48',0),
(26,0,'Successful login user user',0,'','2025-02-19 13:41:35','2025-02-19 13:41:35',0),
(27,0,'Successful login user admin',0,'','2025-02-19 13:41:43','2025-02-19 13:41:43',0),
(28,0,'Edited user 4 by 1',0,'','2025-02-19 13:42:17','2025-02-19 13:42:17',0),
(29,0,'Added user 4 to room 2',0,'','2025-02-19 13:42:18','2025-02-19 13:42:18',0),
(30,1,'DB Error login user user',0,'','2025-02-19 13:42:35','2025-02-19 13:42:35',0),
(31,0,'Successful login user user',0,'','2025-02-19 13:42:39','2025-02-19 13:42:39',0),
(32,0,'Successful login user admin',0,'','2025-02-19 13:42:50','2025-02-19 13:42:50',0),
(33,0,'Added user 5 to standard room 1',0,'','2025-02-19 13:45:30','2025-02-19 13:45:30',0),
(34,0,'Added new user 5',0,'','2025-02-19 13:45:30','2025-02-19 13:45:30',0),
(35,0,'Successful login user moderator',0,'','2025-02-19 13:45:42','2025-02-19 13:45:42',0),
(36,0,'User pw changed 5 by 0',0,'','2025-02-19 13:45:49','2025-02-19 13:45:49',0),
(37,0,'Successful login user moderator',0,'','2025-02-19 13:45:56','2025-02-19 13:45:56',0),
(38,0,'Successful login user admin',0,'','2025-02-19 13:46:03','2025-02-19 13:46:03',0),
(39,0,'Added new room (#3) moderator',0,'','2025-02-19 13:46:20','2025-02-19 13:46:20',0),
(40,0,'Added user 5 to room 3',0,'','2025-02-19 13:46:21','2025-02-19 13:46:21',0),
(41,0,'Successful login user moderator',0,'','2025-02-19 13:46:31','2025-02-19 13:46:31',0),
(42,0,'Successful login user admin',0,'','2025-02-19 13:46:43','2025-02-19 13:46:43',0),
(43,0,'Added new room (#4) moderator V',0,'','2025-02-19 13:47:47','2025-02-19 13:47:47',0),
(44,0,'Added user 6 to standard room 1',0,'','2025-02-19 13:48:06','2025-02-19 13:48:06',0),
(45,0,'Added new user 6',0,'','2025-02-19 13:48:06','2025-02-19 13:48:06',0),
(46,0,'Edited user 6 by 1',0,'','2025-02-19 13:48:22','2025-02-19 13:48:22',0),
(47,0,'Added user 6 to room 4',0,'','2025-02-19 13:48:22','2025-02-19 13:48:22',0),
(48,0,'Successful login user moderator_v',0,'','2025-02-19 13:48:33','2025-02-19 13:48:33',0),
(49,0,'User pw changed 6 by 0',0,'','2025-02-19 13:48:40','2025-02-19 13:48:40',0),
(50,0,'Successful login user moderator_v',0,'','2025-02-19 13:48:47','2025-02-19 13:48:47',0),
(51,0,'Successful login user admin',0,'','2025-02-19 13:48:56','2025-02-19 13:48:56',0),
(52,0,'Added new room (#5) super_moderator',0,'','2025-02-19 13:52:49','2025-02-19 13:52:49',0),
(53,0,'Added user 7 to standard room 1',0,'','2025-02-19 13:53:06','2025-02-19 13:53:06',0),
(54,0,'Added new user 7',0,'','2025-02-19 13:53:06','2025-02-19 13:53:06',0),
(55,0,'Added user 8 to standard room 1',0,'','2025-02-19 13:54:34','2025-02-19 13:54:34',0),
(56,0,'Added new user 8',0,'','2025-02-19 13:54:34','2025-02-19 13:54:34',0),
(57,0,'Added user 9 to standard room 1',0,'','2025-02-19 13:54:52','2025-02-19 13:54:52',0),
(58,0,'Added new user 9',0,'','2025-02-19 13:54:52','2025-02-19 13:54:52',0),
(59,0,'Added user 10 to standard room 1',0,'','2025-02-19 13:55:05','2025-02-19 13:55:05',0),
(60,0,'Added new user 10',0,'','2025-02-19 13:55:05','2025-02-19 13:55:05',0),
(61,0,'Successful login user super_moderator',0,'','2025-02-19 13:55:22','2025-02-19 13:55:22',0),
(62,0,'User pw changed 7 by 0',0,'','2025-02-19 13:55:28','2025-02-19 13:55:28',0),
(63,0,'Successful login user super_moderator',0,'','2025-02-19 13:55:36','2025-02-19 13:55:36',0),
(64,0,'Successful login user admin',0,'','2025-02-19 13:55:55','2025-02-19 13:55:55',0),
(65,0,'Successful login user super_moderator_v',0,'','2025-02-19 13:56:30','2025-02-19 13:56:30',0),
(66,0,'User pw changed 8 by 0',0,'','2025-02-19 13:56:37','2025-02-19 13:56:37',0),
(67,0,'Successful login user super_moderator_v',0,'','2025-02-19 13:56:45','2025-02-19 13:56:45',0),
(68,0,'Successful login user admin',0,'','2025-02-19 13:56:56','2025-02-19 13:56:56',0),
(69,0,'Successful login user principal',0,'','2025-02-19 13:57:08','2025-02-19 13:57:08',0),
(70,0,'User pw changed 9 by 0',0,'','2025-02-19 13:57:13','2025-02-19 13:57:13',0),
(71,1,'DB Error login user principal',0,'','2025-02-19 13:57:21','2025-02-19 13:57:21',0),
(72,0,'Successful login user principal',0,'','2025-02-19 13:57:26','2025-02-19 13:57:26',0),
(73,0,'Successful login user admin',0,'','2025-02-19 13:57:37','2025-02-19 13:57:37',0),
(74,0,'Successful login user principal_v',0,'','2025-02-19 13:57:50','2025-02-19 13:57:50',0),
(75,0,'User pw changed 10 by 0',0,'','2025-02-19 13:57:57','2025-02-19 13:57:57',0),
(76,1,'DB Error login user principal_v',0,'','2025-02-19 13:58:04','2025-02-19 13:58:04',0),
(77,0,'Successful login user principal_v',0,'','2025-02-19 13:58:08','2025-02-19 13:58:08',0),
(78,0,'Successful login user admin',0,'','2025-02-19 14:04:41','2025-02-19 14:04:41',0),
(79,0,'Successful login user guest',0,'','2025-02-19 15:18:03','2025-02-19 15:18:03',0),
(80,0,'Successful login user user',0,'','2025-02-19 15:18:12','2025-02-19 15:18:12',0),
(81,1,'DB Error login user guest',0,'','2025-02-19 15:18:59','2025-02-19 15:18:59',0),
(82,0,'Successful login user guest',0,'','2025-02-19 15:19:02','2025-02-19 15:19:02',0),
(83,0,'Successful login user user',0,'','2025-02-19 15:19:15','2025-02-19 15:19:15',0),
(84,0,'Successful login user admin',0,'','2025-02-19 15:51:56','2025-02-19 15:51:56',0);
/*!40000 ALTER TABLE `au_systemlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_texts`
--

DROP TABLE IF EXISTS `au_texts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_texts`
--

LOCK TABLES `au_texts` WRITE;
/*!40000 ALTER TABLE `au_texts` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_texts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_topics`
--

DROP TABLE IF EXISTS `au_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `phase_id` int(11) DEFAULT 1 COMMENT 'Number of phase the topic is in (0=wild idea 1=work 2=approval 3=voting 4=implemenation',
  `wild_ideas_enabled` int(11) DEFAULT 1 COMMENT '1=enabled 0=disabled',
  `publishing_date` datetime DEFAULT NULL COMMENT 'Date, when the topic is active (Phases start working)',
  `phase_duration_0` int(11) DEFAULT 0 COMMENT 'Duration of phase 0',
  `phase_duration_1` int(11) DEFAULT 0 COMMENT 'Duration of phase 1',
  `phase_duration_2` int(11) DEFAULT 0 COMMENT 'Duration of phase 2',
  `phase_duration_3` int(11) DEFAULT 0 COMMENT 'Duration of phase 3',
  `phase_duration_4` int(11) DEFAULT 0 COMMENT 'Duration of phase 4',
  `type` int(11) DEFAULT 0 COMMENT 'type of box (0=std, 1= special)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_topics`
--

LOCK TABLES `au_topics` WRITE;
/*!40000 ALTER TABLE `au_topics` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_topics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_triggers`
--

DROP TABLE IF EXISTS `au_triggers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_triggers`
--

LOCK TABLES `au_triggers` WRITE;
/*!40000 ALTER TABLE `au_triggers` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_triggers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_user_levels`
--

DROP TABLE IF EXISTS `au_user_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `au_user_levels` (
  `level` int(11) NOT NULL COMMENT 'id of level',
  `name` varchar(1024) DEFAULT NULL COMMENT 'name of level',
  `description` text DEFAULT NULL COMMENT 'description of userlevel / rights',
  `status` int(11) DEFAULT NULL COMMENT '0=inactive 1=active',
  PRIMARY KEY (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_user_levels`
--

LOCK TABLES `au_user_levels` WRITE;
/*!40000 ALTER TABLE `au_user_levels` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_user_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_users_basedata`
--

DROP TABLE IF EXISTS `au_users_basedata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `last_update_retrieval` datetime DEFAULT NOW() ON UPDATE current_timestamp() COMMENT 'last update_retrieval',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user_id of the updater',
  `creator_id` int(11) DEFAULT NULL COMMENT 'user_id of the creator',
  `bi` varchar(1024) DEFAULT NULL COMMENT 'blind index',
  `userlevel` int(11) DEFAULT NULL COMMENT 'level of the user (access rights)',
  `infinite_votes` int(11) DEFAULT NULL COMMENT '0=inactive 1= active (this user has infinite votes)',
  `last_login` datetime DEFAULT NULL COMMENT 'date of last login',
  `presence` int(11) DEFAULT 1 COMMENT '0 = user is absent, 1= user is present',
  `absent_until` datetime DEFAULT NULL COMMENT 'date until the user is absent',
  `auto_delegation` int(11) DEFAULT 0 COMMENT '1=on, 0=off - if user is absent, votes are  ',
  `trustee_id` int(11) DEFAULT NULL COMMENT 'id othe the trusted user the votes are delegated to when user is absent (only when auto delegation is on)',
  `o1` int(11) DEFAULT NULL,
  `o2` int(11) DEFAULT NULL,
  `o3` int(11) DEFAULT NULL,
  `consents_given` int(11) DEFAULT 0 COMMENT 'consents given',
  `consents_needed` int(11) DEFAULT 0 COMMENT 'needed consents',
  `temp_pw` varchar(256) DEFAULT NULL COMMENT 'temp pw for user',
  `pw_changed` int(11) DEFAULT 0 COMMENT 'user has changed his initial pw',
  `refresh_token` tinyint(1) DEFAULT 0 COMMENT 'refresh token request',
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' COMMENT 'roles of the user' CHECK (json_valid(`roles`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_users_basedata`
--

LOCK TABLES `au_users_basedata` WRITE;
/*!40000 ALTER TABLE `au_users_basedata` DISABLE KEYS */;
INSERT INTO `au_users_basedata` VALUES
(1,'Admin User','Admin','admin','aula@aula.de','$2y$10$.IPqFlsIXv71/l2Chtopx.GnAuL55I75l.a5fxjn7BLlzPda71AbK','0','3ca2a93f5f309431f65c6770194d1dc6','',NULL,1,'2023-06-17 14:58:43','2025-02-19 15:51:56',0,0,'21232f297a57a5a743894a0e4a801fc3',50,NULL,'2025-02-19 15:51:56',NULL,NULL,0,NULL,NULL,NULL,NULL,0,-3,NULL,0,0,'[]'),
(2,'Tech Admin User','Tech Admin','tech_admin','aula@aula.de','$2y$10$.IPqFlsIXv71/l2Chtopx.GnAuL55I75l.a5fxjn7BLlzPda71AbK','0','3ca2a93f5f309431f65c6770194d1dc6','',NULL,1,'2023-06-17 14:58:43','2025-02-19 13:18:30',0,0,'21232f297a57a5a743894a0e4a801fc3',60,NULL,'2025-02-19 13:18:30',NULL,NULL,0,NULL,NULL,NULL,NULL,0,-3,NULL,0,0,'[]'),
(3,'guest','guest','guest','','$2y$10$c3..tFKsz.BDmprIXETgQeRhINt.h6/PKlG.TwFJQZ6aTEPKtRMXS',NULL,'d01f1059c96aef19942a97c52f064c60','',NULL,1,'2025-02-19 13:36:17','2025-02-19 15:19:02',0,NULL,'084e0343a0486ff05530df6c705c8bb4',10,NULL,'2025-02-19 15:19:02',1,NULL,0,NULL,103,103,103,0,0,'',0,0,'[{\"role\": 10, \"room\": \"965bbe32b06bbb58f1e28cfd78906371\"}]'),
(4,'user','user','user','','$2y$10$xtHrWb1q//Y0Cu015ri9b.CUP84EA60nbAN1K7FNgy3.z7OPHS3CG','','1956d91a937eafe8d5d1475b161c9ccf','',NULL,1,'2025-02-19 13:40:13','2025-02-19 15:19:15',1,NULL,'ee11cbb19052e40b07aac0ca060c23ee',20,NULL,'2025-02-19 15:19:15',1,NULL,0,NULL,117,117,117,0,0,'',0,0,'[{\"role\": 20, \"room\": \"965bbe32b06bbb58f1e28cfd78906371\"}, {\"role\": 20, \"room\": \"a1af19831c8edea6c8b120939756fe25\"}]'),
(5,'moderator','moderator','moderator','','$2y$10$fWMBTS3HQE6t.rv9TE84ReV.jLlPXPU4xew6T9H.RaPp9UP4hJnNC',NULL,'753ec30b3af529aff1501d6227a6eeea','',NULL,1,'2025-02-19 13:45:30','2025-02-19 13:46:31',0,NULL,'0408f3c997f309c03b08bf3a4bc7b730',30,NULL,'2025-02-19 13:46:31',1,NULL,0,NULL,109,109,109,0,0,'',0,0,'[{\"role\": 30, \"room\": \"965bbe32b06bbb58f1e28cfd78906371\"}, {\"role\": 30, \"room\": \"6ada88a18ccc458a8f047303cb79096b\"}]'),
(6,'moderator_v','moderator_v','moderator_v','','$2y$10$K/nNUoiqYTo.r8/0SVRBweoGmyAzwvz7BhBwkQaGrcB/BZp.p5vF2','','16e8a2bb4708256e2e0506ec85d270f1','',NULL,1,'2025-02-19 13:48:06','2025-02-19 13:48:47',0,NULL,'be41282168af65caed9122caa2040955',31,NULL,'2025-02-19 13:48:47',1,NULL,0,NULL,109,109,109,0,0,'',0,0,'[{\"role\": 31, \"room\": \"965bbe32b06bbb58f1e28cfd78906371\"}, {\"role\": 31, \"room\": \"4b036414d99256f4e49fdb6ad56b1366\"}]'),
(7,'super_moderator','super_moderator','super_moderator','','$2y$10$uhIxBylkaXYhQM6hdXayhOrWA70TkwLPfhUYvED5VjYRdrI01npYu',NULL,'7a02453e589199dc9e042256e065c9f6','',NULL,1,'2025-02-19 13:53:06','2025-02-19 13:55:36',0,NULL,'8c8339460247459152d10c5020294358',40,NULL,'2025-02-19 13:55:36',1,NULL,0,NULL,115,115,115,0,0,'',0,0,'[{\"role\": 40, \"room\": \"965bbe32b06bbb58f1e28cfd78906371\"}]'),
(8,'super_moderator_v','super_moderator_v','super_moderator_v','','$2y$10$OdcqrE1VhT/lgOMVRJfNwub3DdktPYr3k3IG736cJwIUyg./yIiKm',NULL,'5d57eadb0dae759b1d01daca64048395','',NULL,1,'2025-02-19 13:54:34','2025-02-19 13:56:45',0,NULL,'782fa82970b4e8a1a2e3bf5edc902ec1',41,NULL,'2025-02-19 13:56:45',1,NULL,0,NULL,115,115,115,0,0,'',0,0,'[{\"role\": 41, \"room\": \"965bbe32b06bbb58f1e28cfd78906371\"}]'),
(9,'principal','principal','principal','','$2y$10$clWA.I/CJNRuNPfqtn7IU.t3/69Bdvsn4WAVBfTHblCzQP7dJIQva',NULL,'0245048fb6ae9d5b34b3db87e30bdc3c','',NULL,1,'2025-02-19 13:54:52','2025-02-19 13:57:26',0,NULL,'e7d715a9b79d263ae527955341bbe9b1',44,NULL,'2025-02-19 13:57:26',1,NULL,0,NULL,112,112,112,0,0,'',0,0,'[{\"role\": 44, \"room\": \"965bbe32b06bbb58f1e28cfd78906371\"}]'),
(10,'principal_v','principal_v','principal_v','','$2y$10$YIAX/NAZ/0WV/ut.viJOnORAM8Gwgi.IbzptKSoLaLPviDeyWCujq',NULL,'ad89561d8f0af8a04021e07a72298e3d','',NULL,1,'2025-02-19 13:55:05','2025-02-19 13:58:08',0,NULL,'9f4e9b26f3ddfad618d70954194afbf3',45,NULL,'2025-02-19 13:58:08',1,NULL,0,NULL,112,112,112,0,0,'',0,0,'[{\"role\": 45, \"room\": \"965bbe32b06bbb58f1e28cfd78906371\"}]');
/*!40000 ALTER TABLE `au_users_basedata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_users_settings`
--

DROP TABLE IF EXISTS `au_users_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_users_settings`
--

LOCK TABLES `au_users_settings` WRITE;
/*!40000 ALTER TABLE `au_users_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_users_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `au_votes`
--

DROP TABLE IF EXISTS `au_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_votes`
--

LOCK TABLES `au_votes` WRITE;
/*!40000 ALTER TABLE `au_votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_votes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-02-19 17:01:21
