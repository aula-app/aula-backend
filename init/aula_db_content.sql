# ************************************************************
# Sequel Ace SQL dump
# Version 20062
#
# https://sequel-ace.com/
# https://github.com/Sequel-Ace/Sequel-Ace
#
# Host: devel.aula.de (MySQL 5.5.5-10.6.18-MariaDB-0ubuntu0.22.04.1)
# Datenbank: aula_db
# Verarbeitungszeit: 2024-11-28 12:53:05 +0000

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


# Tabellen-Dump au_change_password
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_change_password`;

CREATE TABLE `au_change_password` (
  `user_id` int(11) DEFAULT NULL,
  `secret` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;



# Tabellen-Dump au_commands
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_commands`;

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

LOCK TABLES `au_comments` WRITE;
/*!40000 ALTER TABLE `au_comments` DISABLE KEYS */;

INSERT INTO `au_comments` (`id`, `content`, `sum_likes`, `user_id`, `status`, `created`, `last_update`, `updater_id`, `hash_id`, `language_id`, `idea_id`, `parent_id`)
VALUES
	(1,'While the idea of solar-powered charging stations is admirable, it may not be the most cost-effective solution for our school at this time.',0,165,1,'2024-06-24 22:20:58','2024-06-24 22:20:58',0,'a02c31c880b78d596ba9784e64d13981',0,267,0),
	(2,'This idea for solar-powered charging stations is a fantastic initiative for our school. Not only will it reduce our carbon footprint by utilizing renewable energy, but it also sets a great example for students on sustainable practices. It\'s a practical solution that aligns with our commitment to environmental stewardship and can potentially save costs in the long run by reducing energy expenses. Plus, it educates students about the benefits of renewable energy sources like solar power.',0,165,1,'2024-06-24 22:21:53','2024-06-24 22:21:53',0,'22e8eab0d638f32c348f4e6a04050483',0,267,0),
	(3,'A campus-wide recycling initiative is long overdue! It\'s a practical step towards reducing our environmental impact and promoting responsible waste management among students and staff.',1,165,1,'2024-06-24 22:22:59','2024-06-24 22:23:03',0,'735404f40c91a2bd39161e569dbb5723',0,268,0),
	(4,'While recycling is important, implementing a campus-wide initiative might be challenging. It requires significant resources for infrastructure, maintenance, and education. We should explore other sustainability efforts that are more feasible and impactful within our current budget constraints.',0,165,1,'2024-06-24 22:23:20','2024-06-24 22:23:20',0,'fda296d1a58c0e400639182ada616542',0,268,0),
	(5,'Implementing a Virtual Learning Lab may divert resources away from traditional educational methods that have proven effective.',0,165,1,'2024-06-24 22:24:51','2024-06-24 22:24:51',0,'287e0423885d65d509a3d74b393bdb07',0,262,0),
	(6,'Virtual campus tours offer a convenient and inclusive way for prospective students to explore our campus from anywhere!',0,165,1,'2024-06-24 22:26:07','2024-06-24 22:26:07',0,'53e7dafd1c12437f45a46b34c3578186',0,261,0),
	(7,'This program not only strengthens community bonds but also teaches students valuable life skills like empathy, responsibility, and the importance of giving back.',0,165,1,'2024-06-24 22:28:12','2024-06-24 22:28:12',0,'a63540efa56b609ae6f5555085b3dce0',0,266,0),
	(8,'Directly involving students in personal care tasks for neighbors could raise privacy concerns and may not always align with the needs or preferences of the elderly individuals involved.',0,165,1,'2024-06-24 22:28:25','2024-06-24 22:28:25',0,'3ff37e558e7cd5c986c9dcf50d27d490',0,266,0),
	(9,'ome argue that social service programs like Adopt-a-Neighbor should be voluntary rather than mandatory, as forcing participation may dilute the altruistic spirit and impact of genuine volunteerism.',0,165,1,'2024-06-24 22:28:34','2024-06-24 22:28:34',0,'75158963dcd3dd83a357ad53031a20fd',0,266,0),
	(10,'Managing a school garden requires significant time, resources, and expertise that may detract from core academic priorities and other extracurricular activities.',1,165,1,'2024-06-24 22:29:30','2024-06-24 22:29:49',0,'48648ad4dd02124f1ca7eae54d5c1281',0,265,0),
	(11,'A school garden program teaches students about sustainability, nutrition, and responsibility, fostering a deeper connection to nature and promoting healthier eating habits.',0,165,1,'2024-06-24 22:29:46','2024-06-24 22:29:46',0,'ae8064cb703824b30ba9274aee8b0de6',0,265,0),
	(12,'Vertical garden walls could potentially pose maintenance challenges such as irrigation and plant care, requiring ongoing resources and expertise that may outweigh their aesthetic and environmental benefits in a school setting.',1,165,1,'2024-06-24 22:39:05','2024-06-24 22:39:20',0,'0b7cb5092f9ef3d66b0e55c4046e9bf6',0,260,0),
	(13,'test',0,165,1,'2024-06-30 13:11:42','2024-06-30 13:11:42',165,'c657731b106b5f1508857201cc211643',0,272,0),
	(14,'Maybe testing comments could be an idea too.',0,165,1,'2024-07-17 17:18:17','2024-07-17 17:19:23',165,'e77b45b6b9fb10b42ba388778783623d',0,278,0);

/*!40000 ALTER TABLE `au_comments` ENABLE KEYS */;
UNLOCK TABLES;


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
  `status` int(11) DEFAULT 1 COMMENT 'status of consent',
  PRIMARY KEY (`user_id`,`text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

LOCK TABLES `au_consent` WRITE;
/*!40000 ALTER TABLE `au_consent` DISABLE KEYS */;

INSERT INTO `au_consent` (`user_id`, `text_id`, `consent`, `date_consent`, `date_revoke`, `created`, `last_update`, `updater_id`, `status`)
VALUES
	(1,9,1,'2024-06-23 12:45:18',NULL,'2024-06-23 12:45:18','2024-10-28 18:18:01',0,1),
	(2,11,1,'2024-06-23 12:47:20',NULL,'2024-06-23 12:47:20','2024-06-23 12:47:20',0,1),
	(3,9,1,'2024-09-12 17:00:13',NULL,'2024-09-12 17:00:13','2024-09-12 17:00:13',0,1),
	(4,11,1,'2024-09-12 17:00:12',NULL,'2024-09-12 17:00:12','2024-09-12 17:00:12',0,1);

/*!40000 ALTER TABLE `au_consent` ENABLE KEYS */;
UNLOCK TABLES;


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

LOCK TABLES `au_delegation` WRITE;
/*!40000 ALTER TABLE `au_delegation` DISABLE KEYS */;

INSERT INTO `au_delegation` (`user_id_original`, `user_id_target`, `room_id`, `topic_id`, `status`, `updater_id`, `created`, `last_update`)
VALUES
	(165,264,0,474,1,165,'2024-09-11 11:10:24','2024-09-11 11:10:24');

/*!40000 ALTER TABLE `au_delegation` ENABLE KEYS */;
UNLOCK TABLES;


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
  `order_importance` int(11) DEFAULT NULL COMMENT 'order htat groups are shown (used for display)',
  `vote_bias` int(11) DEFAULT NULL COMMENT 'votes weight per user in this group',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;


# Tabellen-Dump au_ideas
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_ideas`;

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

LOCK TABLES `au_ideas` WRITE;
/*!40000 ALTER TABLE `au_ideas` DISABLE KEYS */;

INSERT INTO `au_ideas` (`id`, `title`, `content`, `sum_likes`, `sum_votes`, `number_of_votes`, `user_id`, `votes_available_per_user`, `status`, `language_id`, `created`, `last_update`, `hash_id`, `order_importance`, `info`, `updater_id`, `room_id`, `is_winner`, `approved`, `approval_comment`, `topic_id`, `sum_comments`, `custom_field1`, `custom_field2`, `type`)
VALUES
	(1,'Augmented Reality Campus Tours','Develop an augmented reality (AR) app that provides interactive campus tours for new students and visitors. Users can explore key campus locations, historical landmarks, and facilities by overlaying digital information and interactive elements through their mobile devices.',1,0,0,165,1,1,0,'2024-06-24 22:02:38','2024-07-29 15:03:18','837ed523d9dbb6138ebfbda01261027b',10,'',165,106,0,0,'',NULL,1,NULL,NULL,0),
	(2,'Virtual Learning Lab','Create a dedicated virtual learning lab equipped with high-speed internet, VR headsets, and interactive digital resources. This lab would offer students immersive learning experiences in subjects like science, history, and geography, enabling them to explore concepts in a virtual environment.',0,0,0,165,1,1,0,'2024-06-24 22:02:54','2024-07-29 15:03:41','e983b05c294e9b2fecadc765005bcf3b',10,'',165,106,0,0,'',NULL,1,NULL,NULL,0),
	(3,'Student Art Gallery','Establish a student-run art gallery within the school where students can showcase their artworks, including paintings, sculptures, photographs, and digital art. This space would not only promote creativity but also provide a platform for students to express themselves artistically and share their work with the school community.',0,0,1,165,1,1,0,'2024-06-24 22:04:12','2024-12-11 18:41:18','d7f60e32b79357492dc64e286cdf5172',10,'',165,106,0,1,'The Student Art Gallery will be a fantastic platform to showcase and celebrate our students\' artistic talents.',NULL,0,NULL,NULL,0),
	(4,'Performing Arts Festival','Organize an annual performing arts festival featuring student performances in music, dance, theater, and spoken word. The festival could include workshops, masterclasses with professional artists, and culminate in a showcase event that celebrates the diverse talents and creativity of students.',0,0,1,165,1,1,0,'2024-06-24 22:04:28','2024-12-11 18:41:34','ffae7587ac3b700f1774a2de9d6826b3',10,'',165,106,0,1,'Excited to approve the Performing Arts Festival — can\'t wait to see the creativity it will bring to our community!',NULL,0,NULL,NULL,0),
	(5,'School Garden for Food Donation','Create a school garden dedicated to growing fresh produce, which is then donated to local food banks or community organizations supporting food-insecure individuals and families. Students would be involved in all aspects of gardening, from planting to harvesting, promoting sustainability and community service simultaneously.',1,0,0,165,1,1,0,'2024-06-24 22:05:21','2024-06-26 17:02:04','c4531c1acb5d3bd3f2f0bd05f972da7d',10,'',165,106,1,1,'I’m thrilled to approve the Create a School Garden program! Growing fresh produce for local food banks and supporting food-insecure families is a fantastic initiative.',NULL,2,NULL,NULL,0),
	(6,'Adopt-a-Neighbor Program','Launch an adopt-a-neighbor program where students volunteer to assist elderly or disabled community members with tasks such as grocery shopping, yard work, or companionship visits. This program aims to foster intergenerational connections and provide valuable support to those in need within the local community.',0,0,0,165,1,1,0,'2024-06-24 22:05:52','2024-07-29 15:55:02','13f8ff68df55ae94723a95a5cb0b2100',10,'',165,106,0,0,'',NULL,3,NULL,NULL,0),
	(7,'Solar-Powered Charging Stations','Install solar-powered charging stations throughout the school campus. These stations would allow students to charge their devices using renewable energy, reducing the reliance on traditional electricity sources and promoting sustainable practices.',1,0,0,165,1,1,0,'2024-06-24 22:07:32','2024-06-24 22:21:53','f8fc3f54931117c7e89b44408e3ace2b',10,'',165,106,0,0,NULL,NULL,2,NULL,NULL,0),
	(8,'Campus-wide Recycling Initiative','Implement a comprehensive recycling program across the school. This initiative would include clear signage, designated recycling bins for paper, plastic, and glass, as well as educational campaigns to encourage students and staff to recycle effectively.',1,0,0,165,1,1,0,'2024-06-24 22:07:47','2024-12-12 16:07:39','a9e8e8420bb1167b56c8a026797f22a8',10,'',165,106,0,0,'',NULL,4,NULL,NULL,0),
	(9,'Student Tech Lab','The Student Tech Lab is a creative space where students can explore and develop new apps, digital learning tools, and robotics projects, fostering hands-on learning and innovation.',0,0,0,165,1,1,0,'2024-06-26 17:32:49','2024-06-26 17:32:49','857ad644ee906135ede100e3a8c6d606',10,'',0,106,0,0,NULL,NULL,0,NULL,NULL,0),
	(10,'Community Mural Project','This project will not only beautify the community but also provide a platform for young artists to collaborate and express their creativity.',0,-1,1,165,1,1,0,'2024-06-26 17:34:33','2024-06-26 17:35:30','9491c7450a1a946cfee61a575e51df90',10,'',0,106,0,0,NULL,NULL,0,NULL,NULL,0),
	(11,'Enhancing Outdoor Learning Spaces','Create dedicated outdoor classrooms to foster hands-on learning and environmental education.',1,0,0,165,1,1,0,'2024-06-26 18:18:58','2024-12-12 16:08:23','2930c3a7b46fd5daf3ecdca5704fcbcf',10,'',165,106,0,0,'',NULL,1,NULL,NULL,0),
	(12,'Testabstimmung: Kino oder Theater?','Bitte stimmt ab, ob wir in das Theater gehen oder nicht',0,0,1,165,1,1,0,'2024-10-03 11:07:44','2024-10-28 15:42:27','807278ddcaa13c48e93a5a80eed8f22d',10,'',165,106,0,0,NULL,NULL,0,'','',0),
	(13,'Vertical Garden Walls','Create vertical garden walls in unused spaces around the school. These walls would feature plants that improve air quality indoors, enhance aesthetic appeal, and provide educational opportunities about gardening and sustainable agriculture.',1,0,0,165,1,1,0,'2024-06-24 22:01:53','2024-07-29 15:55:07','4337391284fa79271f76a270de027c6e',10,'',165,106,0,0,'',NULL,1,NULL,NULL,0);
/*!40000 ALTER TABLE `au_ideas` ENABLE KEYS */;
UNLOCK TABLES;


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

LOCK TABLES `au_rel_categories_ideas` WRITE;
/*!40000 ALTER TABLE `au_rel_categories_ideas` DISABLE KEYS */;

INSERT INTO `au_rel_categories_ideas` (`category_id`, `idea_id`, `created`, `last_update`, `updater_id`)
VALUES
	(1,2,'2024-10-03 11:07:44','2024-10-03 11:07:44',1),
	(3,3,'2024-07-29 15:55:07','2024-07-29 15:55:07',1),
	(3,4,'2024-07-29 15:55:02','2024-07-29 15:55:02',1),
	(3,5,'2024-07-29 15:54:55','2024-07-29 15:54:55',1);

/*!40000 ALTER TABLE `au_rel_categories_ideas` ENABLE KEYS */;
UNLOCK TABLES;


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



# Tabellen-Dump au_rel_categories_rooms
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_rel_categories_rooms`;

CREATE TABLE `au_rel_categories_rooms` (
  `category_id` int(11) NOT NULL COMMENT 'id of category',
  `room_id` int(11) NOT NULL COMMENT 'id of room',
  `created` datetime DEFAULT NULL COMMENT 'creation time of relation',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update of dataset',
  `updater_id` int(11) DEFAULT NULL COMMENT 'id of updater',
  PRIMARY KEY (`category_id`,`room_id`)
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

LOCK TABLES `au_rel_rooms_users` WRITE;
/*!40000 ALTER TABLE `au_rel_rooms_users` DISABLE KEYS */;

INSERT INTO `au_rel_rooms_users` (`room_id`, `user_id`, `status`, `created`, `last_update`, `updater_id`)
VALUES
	(1,1,1,'2024-07-21 11:02:07','2024-07-21 11:02:07',1),
	(1,2,1,'2024-07-21 11:02:08','2024-07-21 11:02:08',1),
	(1,3,1,'2024-07-21 11:02:07','2024-07-21 11:02:07',1),
	(1,4,1,'2024-07-21 11:02:14','2024-07-21 11:02:14',1),
	(1,5,1,'2024-07-21 11:02:11','2024-07-21 11:02:11',1),
	(1,6,1,'2024-07-21 11:02:11','2024-07-21 11:02:11',1),
	(1,7,1,'2024-07-21 11:02:13','2024-07-21 11:02:13',1),
	(1,8,1,'2024-07-21 11:02:12','2024-07-21 11:02:12',1),
	(2,1,1,'2024-07-21 11:02:07','2024-07-21 11:02:07',1),
	(2,2,1,'2024-07-21 11:02:08','2024-07-21 11:02:08',1),
	(2,3,1,'2024-07-21 11:02:07','2024-07-21 11:02:07',1),
	(2,4,1,'2024-07-21 11:02:14','2024-07-21 11:02:14',1),
	(2,5,1,'2024-07-21 11:02:11','2024-07-21 11:02:11',1);


/*!40000 ALTER TABLE `au_rel_rooms_users` ENABLE KEYS */;
UNLOCK TABLES;


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

LOCK TABLES `au_reported` WRITE;
/*!40000 ALTER TABLE `au_reported` DISABLE KEYS */;

INSERT INTO `au_reported` (`user_id`, `type`, `object_id`, `status`, `created`, `last_update`, `reason`, `internal_info`)
VALUES
	(4,0,3,0,'2023-06-03 07:04:27','2023-06-03 07:04:27','this idea is scandalous',NULL),
	(4,0,5,0,'2023-06-03 07:13:36','2023-06-03 07:13:36','this idea is scandalous',NULL);

/*!40000 ALTER TABLE `au_reported` ENABLE KEYS */;
UNLOCK TABLES;


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
  `phase_duration_0` int(11) DEFAULT 0 COMMENT 'phase duration 0',
  `phase_duration_1` int(11) DEFAULT 0 COMMENT 'phase_duration_1',
  `phase_duration_2` int(11) DEFAULT 0 COMMENT 'phase_duration_2',
  `phase_duration_3` int(11) DEFAULT 0 COMMENT 'phase_duration_3',
  `phase_duration_4` int(11) DEFAULT 0 COMMENT 'phase_duration_4',
  `type` int(11) DEFAULT 0 COMMENT '0 = standard room 1 = MAIN ROOM (aula)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

LOCK TABLES `au_rooms` WRITE;
/*!40000 ALTER TABLE `au_rooms` DISABLE KEYS */;

INSERT INTO `au_rooms` (`id`, `room_name`, `description_public`, `description_internal`, `status`, `restrict_to_roomusers_only`, `order_importance`, `created`, `last_update`, `updater_id`, `hash_id`, `access_code`, `internal_info`, `phase_duration_0`, `phase_duration_1`, `phase_duration_2`, `phase_duration_3`, `phase_duration_4`, `type`)
VALUES
	(1,NULL,NULL,NULL,1,NULL,NULL,NULL,'2024-11-28 12:52:48',NULL,'defaultRoom',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1),
  (2,'The Innovation Hub','DI:6:0','DI:6:0',1,1,10,'2024-06-24 21:48:04','2024-07-25 16:43:46',165,'02a9374ae856c01ebb647c3b7570312d','','',NULL,NULL,NULL,NULL,NULL);

/*!40000 ALTER TABLE `au_rooms` ENABLE KEYS */;
UNLOCK TABLES;


# Tabellen-Dump au_services
# ------------------------------------------------------------

DROP TABLE IF EXISTS `au_services`;

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

LOCK TABLES `au_system_current_state` WRITE;
/*!40000 ALTER TABLE `au_system_current_state` DISABLE KEYS */;

INSERT INTO `au_system_current_state` (`id`, `online_mode`, `revert_to_on_active`, `revert_to_on_date`, `created`, `last_update`, `updater_id`)
VALUES
	(1,1,0,'2024-09-09 08:00:00',NULL,'2024-10-29 10:23:11',266);

/*!40000 ALTER TABLE `au_system_current_state` ENABLE KEYS */;
UNLOCK TABLES;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

LOCK TABLES `au_system_global_config` WRITE;
/*!40000 ALTER TABLE `au_system_global_config` DISABLE KEYS */;

INSERT INTO `au_system_global_config` (`id`, `name`, `internal_hash_id`, `external_hash_id`, `description_public`, `base_url`, `media_url`, `preferred_language`, `date_format`, `time_format`, `first_workday_week`, `last_workday_week`, `start_time`, `daily_end_time`, `allow_registration`, `default_role_for_registration`, `default_email_address`, `last_update`, `updater_id`, `archive_after`, `organisation_type`, `enable_oauth`, `custom_field1_name`, `custom_field2_name`, `quorum_wild_ideas`, `quorum_votes`)
VALUES
	(1,'Test School',NULL,NULL,'This is the public description for the test school','https://devel.aula.de',NULL,1,1,1,1,5,'2024-01-01 08:00:00','2024-01-01 16:00:00',0,10,X'696E666F4061756C612E6465','2024-10-29 10:23:11',266,NULL,1,0,'Kosten','',0,0);

/*!40000 ALTER TABLE `au_system_global_config` ENABLE KEYS */;
UNLOCK TABLES;


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

LOCK TABLES `au_texts` WRITE;
/*!40000 ALTER TABLE `au_texts` DISABLE KEYS */;

INSERT INTO `au_texts` (`id`, `creator_id`, `headline`, `body`, `user_needs_to_consent`, `service_id_consent`, `consent_text`, `language_id`, `location`, `created`, `last_update`, `updater_id`, `hash_id`, `status`)
VALUES
	(8,0,'Sample Text','test message',0,0,'Agree',0,NULL,'2024-06-22 22:30:30','2024-06-22 22:30:30',165,'3ce902fa6d5fc806c02017188a2e0daa',1),
	(9,0,'Test Mandatory message','test this message',2,0,'Agree',0,NULL,'2024-06-23 12:27:27','2024-10-28 18:18:01',165,'0a4f7789ffe37e398393af1fa120f4d0',3),
	(10,0,'Optional consent message','This message is not mandatory',1,0,'Agree',0,NULL,'2024-06-23 12:30:58','2024-06-23 12:30:58',165,'faca362395a9bf1f4add5370aa6ee67d',1),
	(11,0,'Another consent','Mandatory consent',2,0,'Agree',0,NULL,'2024-06-23 12:47:07','2024-06-23 12:47:07',165,'da35ed97b7014ba1a8f456c47e0f54ba',1);

/*!40000 ALTER TABLE `au_texts` ENABLE KEYS */;
UNLOCK TABLES;


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

LOCK TABLES `au_topics` WRITE;
/*!40000 ALTER TABLE `au_topics` DISABLE KEYS */;

INSERT INTO `au_topics` (`id`, `name`, `description_public`, `description_internal`, `status`, `order_importance`, `created`, `last_update`, `hash_id`, `updater_id`, `room_id`, `phase_id`, `wild_ideas_enabled`, `publishing_date`, `phase_duration_0`, `phase_duration_1`, `phase_duration_2`, `phase_duration_3`, `phase_duration_4`, `type`)
VALUES
	(1,'Green Innovations Vault','Ideas aimed at reducing our environmental footprint, promoting recycling initiatives, sustainable energy projects, and conservation efforts.','Internal description?',1,10,'2024-06-24 21:51:34','2024-06-24 21:51:34','7ba1cc8d79a7cb7457427a5254f69d41',165,106,10,1,NULL,14,14,14,14,14,0),
	(2,'Tech Frontier','New apps, digital learning tools, robotics projects, smart solutions for the school, and advancements in virtual reality or augmented reality.','',1,10,'2024-06-24 21:52:11','2024-09-07 17:27:25','ea45d51cb116ce44f5e69e992393146f',165,106,20,1,NULL,14,14,14,14,14,0),
	(3,'Creative Canvas','Imaginative ideas spanning visual arts, music performances, theater productions, literary works and other art expression.','',1,10,'2024-06-24 21:53:20','2024-09-07 17:24:00','d2c9fb9871033f36407ab0bcf0e676a3',165,106,30,1,NULL,14,14,14,14,14,0),
	(4,'Service Heart','Proposals for volunteer programs, fundraising events, social justice initiatives, outreach campaigns, and projects aimed at improving the well-being of others.','Internal description?',1,10,'2024-06-24 21:54:04','2024-06-24 21:54:04','aacc93c0cdabb2bf4106f9f1ce3bf2b5',165,106,40,1,NULL,14,14,14,14,14,0);

/*!40000 ALTER TABLE `au_topics` ENABLE KEYS */;
UNLOCK TABLES;


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

LOCK TABLES `au_user_levels` WRITE;
/*!40000 ALTER TABLE `au_user_levels` DISABLE KEYS */;

INSERT INTO `au_user_levels` (`level`, `name`, `description`, `status`)
VALUES
	(10,'Guest','Read only',1),
	(20,'Basic','Read, Create Ideas',1),
	(30,'Moderator','Read ',1),
	(40,'Super Moderator','',1),
	(50,'Admin',' ',1),
	(60,'Tech admin','',1);

/*!40000 ALTER TABLE `au_user_levels` ENABLE KEYS */;
UNLOCK TABLES;


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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

LOCK TABLES `au_users_basedata` WRITE;
/*!40000 ALTER TABLE `au_users_basedata` DISABLE KEYS */;

INSERT INTO `au_users_basedata` (`id`, `realname`, `displayname`, `username`, `email`, `pw`, `position`, `hash_id`, `about_me`, `registration_status`, `status`, `created`, `last_update`, `updater_id`, `creator_id`, `bi`, `userlevel`, `infinite_votes`, `last_login`, `presence`, `absent_until`, `auto_delegation`, `trustee_id`, `o1`, `o2`, `o3`, `consents_given`, `consents_needed`, `temp_pw`, `pw_changed`)
VALUES
	(1,'Admin User','Admin','aula','aula@aula.de','$2y$10$.IPqFlsIXv71/l2Chtopx.GnAuL55I75l.a5fxjn7BLlzPda71AbK','0','3ca2a93f5f309431f65c6770194d1dc6','',NULL,1,'2023-06-17 14:58:43','2024-10-29 13:44:28',0,0,'21232f297a57a5a743894a0e4a801fc3',50,NULL,'2024-10-29 12:32:24',NULL,NULL,0,NULL,NULL,NULL,NULL,0,-3,NULL,0),
	(2,'Albrecht Durer','durer','adurer','adurer@aula.de','$2y$10$1NNLacyzYP3zI5Ipy1FgOOxbyP9Ezhuch3OkT5uSWIdRBaafJYpS2',NULL,'940ec2b51215d712b2228989e9d04863','description?',NULL,1,'2024-07-03 16:31:19','2024-07-17 16:37:09',165,0,'d47b6ae7477f77ac3c5ff48d0ca8cded',10,NULL,NULL,1,NULL,0,NULL,97,97,100,0,-3,NULL,0),
	(3,'Alfred Doblin','doblin','adoblin','adoblin@aula.de','$2y$10$/4NX2p3PEK4YkXVrUHCfiOJL7Jve2kJzP21qMeTsJX5uQ/SjgLIV6',NULL,'599cf1b62ab5ac0b1b3facbd5f08c215','description?',NULL,1,'2024-07-03 16:32:37','2024-07-17 16:37:09',165,0,'4347d0beb442602471ae29cd56a73d9e',10,NULL,NULL,1,NULL,0,NULL,97,97,100,0,-3,NULL,0),
	(4,'Friedrich Nietzsche','zaratustra','fnietzsche','fnietzsche@aula.de','$2y$10$YTH2z9ucipRCHxvqAE.Q3Oaj1/KF0r0u2Roflx.DNZVyNUXvOjzLe',NULL,'a50a9bb4f3f792eb7ea94f05629f0cc1','description?',NULL,1,'2024-07-03 16:33:24','2024-10-02 09:02:59',165,0,'e6d4d10df940311f3642f0bd7e19b22e',60,NULL,'2024-10-02 09:02:59',1,NULL,0,NULL,102,102,122,0,-3,NULL,0),
	(5,'Marlene Dietrich','merlene','mdietrich','mdietrich@aula.de','$2y$10$vi3.uIrXyLj4vOQi87GWuegNQ13CLucA.SMYziQvCqvUaJypJu74u',NULL,'c4c1cdcdebf21ea35097c3137dc9c405','description?',NULL,1,'2024-07-03 16:34:58','2024-07-17 16:37:09',165,0,'5c6b5df6b09cfc0ee7dabaa8014aae6c',10,NULL,NULL,1,NULL,0,NULL,109,109,109,0,-3,NULL,0),
	(6,'Hannah Arendt','hannah','harendt','harendt@aula.de','$2y$10$nThWaeYj9QdPzDVk5Hcsme3mrOKVsH9/y2acEWe3YyLkuaTzSqYvG',NULL,'67bb4e8a810b2d123b3172e75c69c57d','description?',NULL,1,'2024-07-03 16:35:38','2024-07-17 16:37:09',165,0,'cfa5a4515d77172fee99e37d30216efe',10,NULL,NULL,1,NULL,0,NULL,104,104,104,0,-3,NULL,0),
	(7,'Zazie Beetz','Vanessa','zbeetz','zbeetz@aula.de','$2y$10$IOb9eH2qv7OkSj1yP7ySretynqD/nLY9WTPPVmcp9riuUF9swCmUG',NULL,'33908f14d6f6693ba059ffcfdc618e4d','description?',NULL,1,'2024-07-03 16:36:51','2024-07-17 16:37:09',165,0,'ee377903e5d1311d7f5f30bee4bfed33',10,NULL,NULL,1,NULL,0,NULL,122,122,118,0,-3,NULL,0),
	(8,'Nastassja Kinski','nk','nkinski','nkinski@aula.de','$2y$10$hID/c4EKc7ntojB770cbFuqvQ24IqHz6v0rtC7f3WNc6D0BQyUIGK',NULL,'77993b028df7e814c3a20d17be57511c','description?',NULL,1,'2024-07-03 16:37:53','2024-07-17 16:37:09',165,0,'279c9e41c5082bdf3f3b4bcf699edbb1',10,NULL,NULL,1,NULL,0,NULL,110,110,110,0,-3,NULL,0);

/*!40000 ALTER TABLE `au_users_basedata` ENABLE KEYS */;
UNLOCK TABLES;


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


/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
