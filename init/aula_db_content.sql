-- MariaDB dump 10.19-11.2.2-MariaDB, for osx10.19 (x86_64)
--
-- Host: localhost    Database: aula
-- ------------------------------------------------------
-- Server version	11.2.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_categories`
--

LOCK TABLES `au_categories` WRITE;
/*!40000 ALTER TABLE `au_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `au_categories` ENABLE KEYS */;
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
  `parameters` int(11) DEFAULT NULL COMMENT 'parameters for the command',
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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_comments`
--

LOCK TABLES `au_comments` WRITE;
/*!40000 ALTER TABLE `au_comments` DISABLE KEYS */;
INSERT INTO `au_comments` VALUES
(16,'While the idea of solar-powered charging stations is admirable, it may not be the most cost-effective solution for our school at this time.',0,165,1,'2024-06-24 22:20:58','2024-06-24 22:20:58',0,'a02c31c880b78d596ba9784e64d13981',0,267,0),
(17,'This idea for solar-powered charging stations is a fantastic initiative for our school. Not only will it reduce our carbon footprint by utilizing renewable energy, but it also sets a great example for students on sustainable practices. It\'s a practical solution that aligns with our commitment to environmental stewardship and can potentially save costs in the long run by reducing energy expenses. Plus, it educates students about the benefits of renewable energy sources like solar power.',0,165,1,'2024-06-24 22:21:53','2024-06-24 22:21:53',0,'22e8eab0d638f32c348f4e6a04050483',0,267,0),
(18,'A campus-wide recycling initiative is long overdue! It\'s a practical step towards reducing our environmental impact and promoting responsible waste management among students and staff.',1,165,1,'2024-06-24 22:22:59','2024-06-24 22:23:03',0,'735404f40c91a2bd39161e569dbb5723',0,268,0),
(19,'While recycling is important, implementing a campus-wide initiative might be challenging. It requires significant resources for infrastructure, maintenance, and education. We should explore other sustainability efforts that are more feasible and impactful within our current budget constraints.',0,165,1,'2024-06-24 22:23:20','2024-06-24 22:23:20',0,'fda296d1a58c0e400639182ada616542',0,268,0),
(20,'Implementing a Virtual Learning Lab may divert resources away from traditional educational methods that have proven effective.',0,165,1,'2024-06-24 22:24:51','2024-06-24 22:24:51',0,'287e0423885d65d509a3d74b393bdb07',0,262,0),
(21,'Virtual campus tours offer a convenient and inclusive way for prospective students to explore our campus from anywhere!',0,165,1,'2024-06-24 22:26:07','2024-06-24 22:26:07',0,'53e7dafd1c12437f45a46b34c3578186',0,261,0),
(22,'This program not only strengthens community bonds but also teaches students valuable life skills like empathy, responsibility, and the importance of giving back.',0,165,1,'2024-06-24 22:28:12','2024-06-24 22:28:12',0,'a63540efa56b609ae6f5555085b3dce0',0,266,0),
(23,'Directly involving students in personal care tasks for neighbors could raise privacy concerns and may not always align with the needs or preferences of the elderly individuals involved.',0,165,1,'2024-06-24 22:28:25','2024-06-24 22:28:25',0,'3ff37e558e7cd5c986c9dcf50d27d490',0,266,0),
(24,'ome argue that social service programs like Adopt-a-Neighbor should be voluntary rather than mandatory, as forcing participation may dilute the altruistic spirit and impact of genuine volunteerism.',0,165,1,'2024-06-24 22:28:34','2024-06-24 22:28:34',0,'75158963dcd3dd83a357ad53031a20fd',0,266,0),
(25,'Managing a school garden requires significant time, resources, and expertise that may detract from core academic priorities and other extracurricular activities.',1,165,1,'2024-06-24 22:29:30','2024-06-24 22:29:49',0,'48648ad4dd02124f1ca7eae54d5c1281',0,265,0),
(26,'A school garden program teaches students about sustainability, nutrition, and responsibility, fostering a deeper connection to nature and promoting healthier eating habits.',0,165,1,'2024-06-24 22:29:46','2024-06-24 22:29:46',0,'ae8064cb703824b30ba9274aee8b0de6',0,265,0),
(27,'Vertical garden walls could potentially pose maintenance challenges such as irrigation and plant care, requiring ongoing resources and expertise that may outweigh their aesthetic and environmental benefits in a school setting.',1,165,1,'2024-06-24 22:39:05','2024-06-24 22:39:20',0,'0b7cb5092f9ef3d66b0e55c4046e9bf6',0,260,0);
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
INSERT INTO `au_consent` VALUES
(165,9,1,'2024-06-23 12:45:18',NULL,'2024-06-23 12:45:18','2024-06-23 12:45:18',0,1),
(165,11,1,'2024-06-23 12:47:20',NULL,'2024-06-23 12:47:20','2024-06-23 12:47:20',0,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `title` text DEFAULT NULL,
  `sum_comments` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=269 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_ideas`
--

LOCK TABLES `au_ideas` WRITE;
/*!40000 ALTER TABLE `au_ideas` DISABLE KEYS */;
INSERT INTO `au_ideas` VALUES
(260,'Create vertical garden walls in unused spaces around the school. These walls would feature plants that improve air quality indoors, enhance aesthetic appeal, and provide educational opportunities about gardening and sustainable agriculture.',0,0,0,165,1,1,0,'2024-06-24 22:01:53','2024-06-24 22:39:05','4337391284fa79271f76a270de027c6e',10,'',165,106,0,0,NULL,NULL,'Vertical Garden Walls',1),
(261,'Develop an augmented reality (AR) app that provides interactive campus tours for new students and visitors. Users can explore key campus locations, historical landmarks, and facilities by overlaying digital information and interactive elements through their mobile devices.',1,0,0,165,1,1,0,'2024-06-24 22:02:38','2024-06-25 00:58:44','837ed523d9dbb6138ebfbda01261027b',10,'',165,106,0,1,'Sounds like an innovative and engaging way to explore our campus.',NULL,'Augmented Reality Campus Tours',1),
(262,'Create a dedicated virtual learning lab equipped with high-speed internet, VR headsets, and interactive digital resources. This lab would offer students immersive learning experiences in subjects like science, history, and geography, enabling them to explore concepts in a virtual environment.',0,0,0,165,1,1,0,'2024-06-24 22:02:54','2024-06-25 00:59:49','e983b05c294e9b2fecadc765005bcf3b',10,'',165,106,0,-1,'The proposal for the Virtual Learning Lab, while valuable, is not approved due to budget constraints.',NULL,'Virtual Learning Lab',1),
(263,'Establish a student-run art gallery within the school where students can showcase their artworks, including paintings, sculptures, photographs, and digital art. This space would not only promote creativity but also provide a platform for students to express themselves artistically and share their work with the school community.',0,0,0,165,1,1,0,'2024-06-24 22:04:12','2024-06-25 00:56:25','d7f60e32b79357492dc64e286cdf5172',10,'',165,106,0,1,'The Student Art Gallery will be a fantastic platform to showcase and celebrate our students\' artistic talents.',NULL,'Student Art Gallery',0),
(264,'Organize an annual performing arts festival featuring student performances in music, dance, theater, and spoken word. The festival could include workshops, masterclasses with professional artists, and culminate in a showcase event that celebrates the diverse talents and creativity of students.',0,0,0,165,1,1,0,'2024-06-24 22:04:28','2024-06-25 00:55:37','ffae7587ac3b700f1774a2de9d6826b3',10,'',165,106,0,1,'Excited to approve the Performing Arts Festival — can\'t wait to see the creativity it will bring to our community!',NULL,'Performing Arts Festival',0),
(265,'Create a school garden dedicated to growing fresh produce, which is then donated to local food banks or community organizations supporting food-insecure individuals and families. Students would be involved in all aspects of gardening, from planting to harvesting, promoting sustainability and community service simultaneously.',1,0,0,165,1,1,0,'2024-06-24 22:05:21','2024-06-25 00:53:34','c4531c1acb5d3bd3f2f0bd05f972da7d',10,'',165,106,0,1,'I’m thrilled to approve the Create a School Garden program! Growing fresh produce for local food banks and supporting food-insecure families is a fantastic initiative.',NULL,'School Garden for Food Donation',2),
(266,'Launch an adopt-a-neighbor program where students volunteer to assist elderly or disabled community members with tasks such as grocery shopping, yard work, or companionship visits. This program aims to foster intergenerational connections and provide valuable support to those in need within the local community.',0,0,0,165,1,1,0,'2024-06-24 22:05:52','2024-06-25 00:52:09','13f8ff68df55ae94723a95a5cb0b2100',10,'',165,106,0,1,'It’s a wonderful way for students to support elderly and disabled community members while building intergenerational connections. Looking forward to its positive impact!',NULL,'Adopt-a-Neighbor Program',3),
(267,'Install solar-powered charging stations throughout the school campus. These stations would allow students to charge their devices using renewable energy, reducing the reliance on traditional electricity sources and promoting sustainable practices.',1,0,0,165,1,1,0,'2024-06-24 22:07:32','2024-06-24 22:21:53','f8fc3f54931117c7e89b44408e3ace2b',10,'',165,106,0,0,NULL,NULL,'Solar-Powered Charging Stations',2),
(268,'Implement a comprehensive recycling program across the school. This initiative would include clear signage, designated recycling bins for paper, plastic, and glass, as well as educational campaigns to encourage students and staff to recycle effectively.',1,0,0,165,1,1,0,'2024-06-24 22:07:47','2024-06-24 22:23:20','a9e8e8420bb1167b56c8a026797f22a8',10,'',165,106,0,0,NULL,NULL,'Campus-wide Recycling Initiative',4);
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
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_likes`
--

LOCK TABLES `au_likes` WRITE;
/*!40000 ALTER TABLE `au_likes` DISABLE KEYS */;
INSERT INTO `au_likes` VALUES
(90,165,267,1,'2024-06-24 22:20:59','2024-06-24 22:20:59','15c9a8b0cf946afbd29bc511bc4049ba',1),
(92,165,18,1,'2024-06-24 22:23:03','2024-06-24 22:23:03','1cc92b86fb1b54e00a2e5851556d4e4b',2),
(93,165,268,1,'2024-06-24 22:23:06','2024-06-24 22:23:06','d973f5da7b1a145b44ffd83edfc2feb9',1),
(94,165,261,1,'2024-06-24 22:26:09','2024-06-24 22:26:09','12998fe680ee3821a31f7eb960ebd51a',1),
(95,165,265,1,'2024-06-24 22:29:47','2024-06-24 22:29:47','aa3b4f148ca22fd64a0fb57083ed7d7f',1),
(96,165,25,1,'2024-06-24 22:29:49','2024-06-24 22:29:49','2e1615c3f144ccf6cf5bd445e1ab9cda',2),
(97,165,27,1,'2024-06-24 22:39:20','2024-06-24 22:39:20','3ba7a3a8a63601fca718f586b7fc0469',2);
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
INSERT INTO `au_rel_topics_ideas` VALUES
(468,251,'2024-06-24 14:17:04','2024-06-24 14:17:04',165),
(468,252,'2024-06-24 14:17:04','2024-06-24 14:17:04',165),
(469,253,'2024-06-24 14:16:42','2024-06-24 14:12:42',165),
(469,254,'2024-06-24 14:16:42','2024-06-24 14:16:42',165),
(469,255,'2024-06-24 14:16:42','2024-06-24 14:12:42',165),
(470,249,'2024-06-24 14:13:06','2024-06-24 14:13:06',165),
(470,250,'2024-06-24 14:13:06','2024-06-24 14:13:06',165),
(471,256,'2024-06-24 14:16:33','2024-06-24 14:16:33',165),
(471,257,'2024-06-24 14:16:33','2024-06-24 14:16:33',165),
(472,267,'2024-06-24 22:08:15','2024-06-24 22:08:15',165),
(472,268,'2024-06-24 22:08:15','2024-06-24 22:08:15',165),
(473,258,'2024-06-24 22:02:00','2024-06-24 22:02:00',165),
(473,259,'2024-06-24 22:02:00','2024-06-24 22:02:00',165),
(473,261,'2024-06-24 22:03:33','2024-06-24 22:03:33',165),
(473,262,'2024-06-24 22:03:33','2024-06-24 22:03:33',165),
(474,263,'2024-06-24 22:35:46','2024-06-24 22:35:46',165),
(474,264,'2024-06-24 22:35:46','2024-06-24 22:35:46',165),
(475,265,'2024-06-24 22:06:13','2024-06-24 22:06:13',165),
(475,266,'2024-06-24 22:06:13','2024-06-24 22:06:13',165);
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
INSERT INTO `au_reported` VALUES
(4,0,3,0,'2023-06-03 07:04:27','2023-06-03 07:04:27','this idea is scandalous',NULL),
(4,0,5,0,'2023-06-03 07:13:36','2023-06-03 07:13:36','this idea is scandalous',NULL);
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_rooms`
--

LOCK TABLES `au_rooms` WRITE;
/*!40000 ALTER TABLE `au_rooms` DISABLE KEYS */;
INSERT INTO `au_rooms` VALUES
(106,'The Innovation Hub','The Innovation Hub is a bustling room where creativity takes center stage. Students gather here to pitch their innovative ideas, which are then categorized into topics like sustainability, technology, arts, and community service.','',1,1,10,'2024-06-24 21:48:04','2024-06-24 21:48:04',165,'02a9374ae856c01ebb647c3b7570312d','$2y$10$6.gYwdO.NjuAt7su1Cvif.ajGv9eStGy6R1dZAgvJvbYAveE/4XF2','');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_system_current_state`
--

LOCK TABLES `au_system_current_state` WRITE;
/*!40000 ALTER TABLE `au_system_current_state` DISABLE KEYS */;
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
  `allow_registration` tinyint(1) DEFAULT NULL COMMENT '0=no 1=yes',
  `default_role_for_registration` int(11) DEFAULT NULL COMMENT 'role id for new self registered users',
  `default_email_address` varchar(1024) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL COMMENT 'default fallback e-mail adress',
  `last_update` datetime DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'last update',
  `updater_id` int(11) DEFAULT NULL COMMENT 'user id of updater',
  `archive_after` int(11) DEFAULT NULL COMMENT 'number of days after which content is automatically archived',
  `organisation_type` int(11) DEFAULT NULL COMMENT '0=school, 1=other organisation - for term set',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_system_global_config`
--

LOCK TABLES `au_system_global_config` WRITE;
/*!40000 ALTER TABLE `au_system_global_config` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=9461 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_systemlog`
--

LOCK TABLES `au_systemlog` WRITE;
/*!40000 ALTER TABLE `au_systemlog` DISABLE KEYS */;
INSERT INTO `au_systemlog` VALUES
(9166,0,'Edited user 84 by 0',0,'','2024-06-18 13:15:02','2024-06-18 13:15:02',0),
(9167,0,'Edited user 204 by 0',0,'','2024-06-18 13:16:26','2024-06-18 13:16:26',0),
(9168,0,'Added new room (#104) Default Class',0,'','2024-06-18 13:20:07','2024-06-18 13:20:07',0),
(9169,0,'Added new idea (#246) Some Wild Idea',0,'','2024-06-18 13:22:48','2024-06-18 13:22:48',0),
(9170,0,'Edited user 165 by 0',0,'','2024-06-18 13:36:08','2024-06-18 13:36:08',0),
(9171,0,'Successful login user 165',0,'','2024-06-18 15:31:25','2024-06-18 15:31:25',0),
(9172,0,'Added new topic (#465) First Box',0,'','2024-06-19 17:42:56','2024-06-19 17:42:56',0),
(9173,0,'Added new topic (#466) Second Box',0,'','2024-06-20 12:50:24','2024-06-20 12:50:24',0),
(9174,0,'Edited topic (#) Second Box',0,'','2024-06-20 20:00:05','2024-06-20 20:00:05',0),
(9175,0,'Added new topic (#467) Second Box',0,'','2024-06-20 20:00:56','2024-06-20 20:00:56',0),
(9176,0,'Topic deleted, id=467 by 0',0,'','2024-06-20 20:04:39','2024-06-20 20:04:39',0),
(9177,0,'Topic deleted, id=466 by 0',0,'','2024-06-20 20:04:58','2024-06-20 20:04:58',0),
(9178,0,'Added new topic (#468) Box on Approval Phase',0,'','2024-06-20 20:06:33','2024-06-20 20:06:33',0),
(9179,0,'Edited topic (#) First Box',0,'','2024-06-21 13:46:53','2024-06-21 13:46:53',0),
(9180,0,'Edited topic (#) Box on Approval Phase',0,'','2024-06-21 13:47:09','2024-06-21 13:47:09',0),
(9181,0,'Edited topic (#) Box on Approval Phase',0,'','2024-06-21 13:48:13','2024-06-21 13:48:13',0),
(9182,0,'Successful login user 165',0,'','2024-06-22 14:24:47','2024-06-22 14:24:47',0),
(9183,0,'Edited topic (#) First Box',0,'','2024-06-22 19:29:34','2024-06-22 19:29:34',0),
(9184,0,'Edited topic (#) Box on Approval Phase',0,'','2024-06-22 19:29:58','2024-06-22 19:29:58',0),
(9185,0,'Added new user 262',0,'','2024-06-22 20:49:34','2024-06-22 20:49:34',0),
(9186,0,'User delegation(s) deleted with id 262 for topic 0',0,'','2024-06-22 20:49:41','2024-06-22 20:49:41',0),
(9187,0,'User delegation(s) deleted with id 262 for topic 0',0,'','2024-06-22 20:49:41','2024-06-22 20:49:41',0),
(9188,0,'User deleted with id 262 by 0',0,'','2024-06-22 20:49:41','2024-06-22 20:49:41',0),
(9189,0,'Added new room (#105) Test Room',0,'','2024-06-22 20:50:03','2024-06-22 20:50:03',0),
(9190,0,'Room deleted with id 105 by 0',0,'','2024-06-22 20:50:06','2024-06-22 20:50:06',0),
(9191,0,'Edited topic (#) Box on Approval Phase',0,'','2024-06-22 21:17:17','2024-06-22 21:17:17',0),
(9192,0,'Added new text (#6) creator: 0',0,'','2024-06-22 21:41:02','2024-06-22 21:41:02',0),
(9193,0,'Consent values updated by value -1',0,'','2024-06-22 21:43:31','2024-06-22 21:43:31',0),
(9194,0,'Text deleted, id = 6 by 0',0,'','2024-06-22 21:43:31','2024-06-22 21:43:31',0),
(9195,0,'Added new text (#7) creator: 0',0,'','2024-06-22 21:43:55','2024-06-22 21:43:55',0),
(9196,0,'Consent values updated by value -1',0,'','2024-06-22 21:44:00','2024-06-22 21:44:00',0),
(9197,0,'Text deleted, id = 7 by 0',0,'','2024-06-22 21:44:00','2024-06-22 21:44:00',0),
(9198,0,'Added new user 263',0,'','2024-06-22 21:53:33','2024-06-22 21:53:33',0),
(9199,0,'User delegation(s) deleted with id 263 for topic 0',0,'','2024-06-22 21:53:38','2024-06-22 21:53:38',0),
(9200,0,'User delegation(s) deleted with id 263 for topic 0',0,'','2024-06-22 21:53:38','2024-06-22 21:53:38',0),
(9201,0,'User deleted with id 263 by 0',0,'','2024-06-22 21:53:38','2024-06-22 21:53:38',0),
(9202,0,'Added new idea (#247) test idea',0,'','2024-06-22 22:06:37','2024-06-22 22:06:37',0),
(9203,0,'Idea deleted, id=247 by 0',0,'','2024-06-22 22:06:47','2024-06-22 22:06:47',0),
(9204,0,'Edited topic (#468) Box on Approval Phase',0,'','2024-06-22 22:13:42','2024-06-22 22:13:42',0),
(9205,0,'Added new topic (#469) Box on Voting phase',0,'','2024-06-22 22:19:52','2024-06-22 22:19:52',0),
(9206,0,'Edited topic (#465) First Box',0,'','2024-06-22 22:22:30','2024-06-22 22:22:30',0),
(9207,0,'Topic deleted, id=465 by 0',0,'','2024-06-22 22:24:03','2024-06-22 22:24:03',0),
(9208,0,'Added new text (#8) creator: 0',0,'','2024-06-22 22:30:30','2024-06-22 22:30:30',0),
(9209,0,'Added new topic (#470) Box on Discussion Phase',0,'','2024-06-22 22:39:57','2024-06-22 22:39:57',0),
(9210,0,'Edited topic (#468) Box on Approval Phase',0,'','2024-06-22 22:40:05','2024-06-22 22:40:05',0),
(9211,0,'Edited topic (#469) Box on Voting phase',0,'','2024-06-22 22:40:11','2024-06-22 22:40:11',0),
(9212,0,'Added new topic (#471) Box on Results Phase',0,'','2024-06-22 22:40:33','2024-06-22 22:40:33',0),
(9213,0,'Added new text (#9) creator: 0',0,'','2024-06-23 12:27:27','2024-06-23 12:27:27',0),
(9214,0,'Consent values updated by value 1',0,'','2024-06-23 12:27:27','2024-06-23 12:27:27',0),
(9215,0,'Added new text (#10) creator: 0',0,'','2024-06-23 12:30:58','2024-06-23 12:30:58',0),
(9216,0,'Added consent for user 165 for text 9',0,'','2024-06-23 12:45:18','2024-06-23 12:45:18',0),
(9217,0,'Added new text (#11) creator: 0',0,'','2024-06-23 12:47:07','2024-06-23 12:47:07',0),
(9218,0,'Consent values updated by value 1',0,'','2024-06-23 12:47:07','2024-06-23 12:47:07',0),
(9219,0,'Added consent for user 165 for text 11',0,'','2024-06-23 12:47:20','2024-06-23 12:47:20',0),
(9220,0,'Edited idea 246 by 165',0,'','2024-06-24 10:04:44','2024-06-24 10:04:44',0),
(9221,0,'Added new idea (#248) Some wild idea to work with',0,'','2024-06-24 10:12:42','2024-06-24 10:12:42',0),
(9222,0,'Added new idea (#249) There is a need for a discussion idea for testing purposes.',0,'','2024-06-24 10:13:23','2024-06-24 10:13:23',0),
(9223,0,'Added new idea (#250) Is there anything to be discussed here? I don\'t think so. We should all agree.',0,'','2024-06-24 10:14:10','2024-06-24 10:14:10',0),
(9224,0,'Added new idea (#251) This idea is a mock up for the approval phase.',0,'','2024-06-24 10:18:24','2024-06-24 10:18:24',0),
(9225,0,'Added new idea (#252) Unfortunately, this was not approved',0,'','2024-06-24 10:19:13','2024-06-24 10:19:13',0),
(9226,0,'Added new idea (#253) Vote for this idea!',0,'','2024-06-24 10:19:34','2024-06-24 10:19:34',0),
(9227,0,'Added new idea (#254) We don\'t support nor reject this idea.',0,'','2024-06-24 10:20:13','2024-06-24 10:20:13',0),
(9228,0,'Added new idea (#255) People don\'t want this idea to be the selected one.',0,'','2024-06-24 10:21:17','2024-06-24 10:21:17',0),
(9229,0,'Added new idea (#256) This is a winner idea on the results phase.',0,'','2024-06-24 10:21:50','2024-06-24 10:21:50',0),
(9230,0,'Added new idea (#257) This idea was not approved by the voters.',0,'','2024-06-24 10:22:20','2024-06-24 10:22:20',0),
(9231,0,'Idea  257 incremented likes',0,'','2024-06-24 13:29:35','2024-06-24 13:29:35',0),
(9232,0,'Idea  257 decrementing likes',0,'','2024-06-24 13:29:37','2024-06-24 13:29:37',0),
(9233,0,'Idea  257 incremented likes',0,'','2024-06-24 13:29:38','2024-06-24 13:29:38',0),
(9234,0,'Idea  257 decrementing likes',0,'','2024-06-24 13:29:38','2024-06-24 13:29:38',0),
(9235,0,'Idea  257 incremented likes',0,'','2024-06-24 13:36:28','2024-06-24 13:36:28',0),
(9236,0,'Idea  257 decrementing likes',0,'','2024-06-24 13:36:29','2024-06-24 13:36:29',0),
(9237,0,'Added new comment (#12) user: 165',0,'','2024-06-24 13:40:13','2024-06-24 13:40:13',0),
(9238,0,'Idea  246 incremented likes',0,'','2024-06-24 13:40:34','2024-06-24 13:40:34',0),
(9239,0,'Idea  246 decrementing likes',0,'','2024-06-24 13:51:03','2024-06-24 13:51:03',0),
(9240,0,'Added idea 255 to topic 469',0,'','2024-06-24 14:12:42','2024-06-24 14:12:42',0),
(9241,0,'Added idea 253 to topic 469',0,'','2024-06-24 14:12:42','2024-06-24 14:12:42',0),
(9242,0,'Added idea 249 to topic 470',0,'','2024-06-24 14:13:06','2024-06-24 14:13:06',0),
(9243,0,'Added idea 250 to topic 470',0,'','2024-06-24 14:13:06','2024-06-24 14:13:06',0),
(9244,0,'Added idea 257 to topic 471',0,'','2024-06-24 14:16:33','2024-06-24 14:16:33',0),
(9245,0,'Added idea 256 to topic 471',0,'','2024-06-24 14:16:33','2024-06-24 14:16:33',0),
(9246,0,'Added idea 253 to topic 469',0,'','2024-06-24 14:16:42','2024-06-24 14:16:42',0),
(9247,0,'Added idea 255 to topic 469',0,'','2024-06-24 14:16:42','2024-06-24 14:16:42',0),
(9248,0,'Added idea 254 to topic 469',0,'','2024-06-24 14:16:42','2024-06-24 14:16:42',0),
(9249,0,'Added idea 251 to topic 468',0,'','2024-06-24 14:17:04','2024-06-24 14:17:04',0),
(9250,0,'Added idea 252 to topic 468',0,'','2024-06-24 14:17:04','2024-06-24 14:17:04',0),
(9251,0,'Added new comment (#0) user: 165',0,'','2024-06-24 14:58:16','2024-06-24 14:58:16',0),
(9252,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:00:17','2024-06-24 16:00:17',0),
(9253,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:00:17','2024-06-24 16:00:17',0),
(9254,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:00:18','2024-06-24 16:00:18',0),
(9255,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:00:18','2024-06-24 16:00:18',0),
(9256,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:00:18','2024-06-24 16:00:18',0),
(9257,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:00:19','2024-06-24 16:00:19',0),
(9258,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:00:19','2024-06-24 16:00:19',0),
(9259,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:00:19','2024-06-24 16:00:19',0),
(9260,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:00:23','2024-06-24 16:00:23',0),
(9261,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:00:23','2024-06-24 16:00:23',0),
(9262,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:00:23','2024-06-24 16:00:23',0),
(9263,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:00:24','2024-06-24 16:00:24',0),
(9264,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:00:24','2024-06-24 16:00:24',0),
(9265,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:00:24','2024-06-24 16:00:24',0),
(9266,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:00:24','2024-06-24 16:00:24',0),
(9267,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:00:24','2024-06-24 16:00:24',0),
(9268,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:00:24','2024-06-24 16:00:24',0),
(9269,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:00:58','2024-06-24 16:00:58',0),
(9270,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:00:58','2024-06-24 16:00:58',0),
(9271,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:00:58','2024-06-24 16:00:58',0),
(9272,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:00:59','2024-06-24 16:00:59',0),
(9273,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:00:59','2024-06-24 16:00:59',0),
(9274,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:00:59','2024-06-24 16:00:59',0),
(9275,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:02','2024-06-24 16:01:02',0),
(9276,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:02','2024-06-24 16:01:02',0),
(9277,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:01:02','2024-06-24 16:01:02',0),
(9278,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:03','2024-06-24 16:01:03',0),
(9279,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:03','2024-06-24 16:01:03',0),
(9280,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:01:03','2024-06-24 16:01:03',0),
(9281,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:04','2024-06-24 16:01:04',0),
(9282,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:04','2024-06-24 16:01:04',0),
(9283,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:01:04','2024-06-24 16:01:04',0),
(9284,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:05','2024-06-24 16:01:05',0),
(9285,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:05','2024-06-24 16:01:05',0),
(9286,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:01:05','2024-06-24 16:01:05',0),
(9287,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:06','2024-06-24 16:01:06',0),
(9288,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:06','2024-06-24 16:01:06',0),
(9289,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:01:06','2024-06-24 16:01:06',0),
(9290,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:07','2024-06-24 16:01:07',0),
(9291,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:07','2024-06-24 16:01:07',0),
(9292,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:01:07','2024-06-24 16:01:07',0),
(9293,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:07','2024-06-24 16:01:07',0),
(9294,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:07','2024-06-24 16:01:07',0),
(9295,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:01:07','2024-06-24 16:01:07',0),
(9296,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:09','2024-06-24 16:01:09',0),
(9297,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:09','2024-06-24 16:01:09',0),
(9298,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:01:09','2024-06-24 16:01:09',0),
(9299,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:12','2024-06-24 16:01:12',0),
(9300,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:12','2024-06-24 16:01:12',0),
(9301,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:01:12','2024-06-24 16:01:12',0),
(9302,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:12','2024-06-24 16:01:12',0),
(9303,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:12','2024-06-24 16:01:12',0),
(9304,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:01:12','2024-06-24 16:01:12',0),
(9305,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:16','2024-06-24 16:01:16',0),
(9306,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:16','2024-06-24 16:01:16',0),
(9307,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:01:16','2024-06-24 16:01:16',0),
(9308,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:17','2024-06-24 16:01:17',0),
(9309,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:17','2024-06-24 16:01:17',0),
(9310,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:01:17','2024-06-24 16:01:17',0),
(9311,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:01:18','2024-06-24 16:01:18',0),
(9312,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:01:18','2024-06-24 16:01:18',0),
(9313,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:01:18','2024-06-24 16:01:18',0),
(9314,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:07:06','2024-06-24 16:07:06',0),
(9315,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:07:06','2024-06-24 16:07:06',0),
(9316,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:07:06','2024-06-24 16:07:06',0),
(9317,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:07:07','2024-06-24 16:07:07',0),
(9318,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:07:07','2024-06-24 16:07:07',0),
(9319,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:07:07','2024-06-24 16:07:07',0),
(9320,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:07:08','2024-06-24 16:07:08',0),
(9321,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:07:08','2024-06-24 16:07:08',0),
(9322,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:07:08','2024-06-24 16:07:08',0),
(9323,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:06','2024-06-24 16:08:06',0),
(9324,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:06','2024-06-24 16:08:06',0),
(9325,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:08:06','2024-06-24 16:08:06',0),
(9326,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:07','2024-06-24 16:08:07',0),
(9327,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:07','2024-06-24 16:08:07',0),
(9328,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:08:07','2024-06-24 16:08:07',0),
(9329,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:08','2024-06-24 16:08:08',0),
(9330,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:08','2024-06-24 16:08:08',0),
(9331,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:08:08','2024-06-24 16:08:08',0),
(9332,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:09','2024-06-24 16:08:09',0),
(9333,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:09','2024-06-24 16:08:09',0),
(9334,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:08:09','2024-06-24 16:08:09',0),
(9335,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:10','2024-06-24 16:08:10',0),
(9336,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:10','2024-06-24 16:08:10',0),
(9337,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:08:10','2024-06-24 16:08:10',0),
(9338,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:11','2024-06-24 16:08:11',0),
(9339,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:11','2024-06-24 16:08:11',0),
(9340,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:08:11','2024-06-24 16:08:11',0),
(9341,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:13','2024-06-24 16:08:13',0),
(9342,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:13','2024-06-24 16:08:13',0),
(9343,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:08:13','2024-06-24 16:08:13',0),
(9344,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:13','2024-06-24 16:08:13',0),
(9345,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:13','2024-06-24 16:08:13',0),
(9346,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:08:13','2024-06-24 16:08:13',0),
(9347,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:19','2024-06-24 16:08:19',0),
(9348,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:19','2024-06-24 16:08:19',0),
(9349,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:08:19','2024-06-24 16:08:19',0),
(9350,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:20','2024-06-24 16:08:20',0),
(9351,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:20','2024-06-24 16:08:20',0),
(9352,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:08:20','2024-06-24 16:08:20',0),
(9353,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:32','2024-06-24 16:08:32',0),
(9354,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:32','2024-06-24 16:08:32',0),
(9355,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:08:32','2024-06-24 16:08:32',0),
(9356,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:37','2024-06-24 16:08:37',0),
(9357,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:37','2024-06-24 16:08:37',0),
(9358,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:08:37','2024-06-24 16:08:37',0),
(9359,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:38','2024-06-24 16:08:38',0),
(9360,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:38','2024-06-24 16:08:38',0),
(9361,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:08:38','2024-06-24 16:08:38',0),
(9362,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:08:39','2024-06-24 16:08:39',0),
(9363,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:08:39','2024-06-24 16:08:39',0),
(9364,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:08:39','2024-06-24 16:08:39',0),
(9365,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:24:07','2024-06-24 16:24:07',0),
(9366,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:24:07','2024-06-24 16:24:07',0),
(9367,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:24:07','2024-06-24 16:24:07',0),
(9368,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:24:09','2024-06-24 16:24:09',0),
(9369,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:24:09','2024-06-24 16:24:09',0),
(9370,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:24:09','2024-06-24 16:24:09',0),
(9371,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:24:17','2024-06-24 16:24:17',0),
(9372,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:24:17','2024-06-24 16:24:17',0),
(9373,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:24:17','2024-06-24 16:24:17',0),
(9374,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:24:19','2024-06-24 16:24:19',0),
(9375,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:24:19','2024-06-24 16:24:19',0),
(9376,0,'Idea (#255) added Vote - value: 0 by 165',0,'','2024-06-24 16:24:19','2024-06-24 16:24:19',0),
(9377,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:24:19','2024-06-24 16:24:19',0),
(9378,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:24:19','2024-06-24 16:24:19',0),
(9379,0,'Idea (#255) added Vote - value: 1 by 165',0,'','2024-06-24 16:24:19','2024-06-24 16:24:19',0),
(9380,0,'Idea  255 votes set to 0',0,'','2024-06-24 16:24:23','2024-06-24 16:24:23',0),
(9381,0,'Idea  255 number of votes given set to 1',0,'','2024-06-24 16:24:23','2024-06-24 16:24:23',0),
(9382,0,'Idea (#255) added Vote - value: -1 by 165',0,'','2024-06-24 16:24:23','2024-06-24 16:24:23',0),
(9383,0,'Topic deleted, id=470 by 0',0,'','2024-06-24 21:42:52','2024-06-24 21:42:52',0),
(9384,0,'Topic deleted, id=468 by 0',0,'','2024-06-24 21:42:52','2024-06-24 21:42:52',0),
(9385,0,'Topic deleted, id=471 by 0',0,'','2024-06-24 21:42:52','2024-06-24 21:42:52',0),
(9386,0,'Topic deleted, id=469 by 0',0,'','2024-06-24 21:42:52','2024-06-24 21:42:52',0),
(9387,0,'Idea deleted, id=252 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9388,0,'Idea deleted, id=255 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9389,0,'Idea deleted, id=256 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9390,0,'Idea deleted, id=246 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9391,0,'Idea deleted, id=250 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9392,0,'Idea deleted, id=257 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9393,0,'Idea deleted, id=249 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9394,0,'Idea deleted, id=254 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9395,0,'Idea deleted, id=253 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9396,0,'Idea deleted, id=251 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9397,0,'Idea deleted, id=248 by 0',0,'','2024-06-24 21:43:05','2024-06-24 21:43:05',0),
(9398,0,'Room deleted with id 104 by 0',0,'','2024-06-24 21:47:47','2024-06-24 21:47:47',0),
(9399,0,'Added new room (#106) The Innovation Hub',0,'','2024-06-24 21:48:04','2024-06-24 21:48:04',0),
(9400,0,'Added new topic (#472) Green Innovations Vault',0,'','2024-06-24 21:51:34','2024-06-24 21:51:34',0),
(9401,0,'Added new topic (#473) Tech Frontier',0,'','2024-06-24 21:52:11','2024-06-24 21:52:11',0),
(9402,0,'Added new topic (#474) Creative Canvas',0,'','2024-06-24 21:53:20','2024-06-24 21:53:20',0),
(9403,0,'Added new topic (#475) Service Heart',0,'','2024-06-24 21:54:04','2024-06-24 21:54:04',0),
(9404,0,'Added new idea (#258) Install solar-powered charging stations throughout the school campus. These stations would allow students to charge their devices using renewable energy, reducing the reliance on traditional electricity sources and promoting sustainable practices.',0,'','2024-06-24 22:00:54','2024-06-24 22:00:54',0),
(9405,0,'Added new idea (#259) Implement a comprehensive recycling program across the school. This initiative would include clear signage, designated recycling bins for paper, plastic, and glass, as well as educational campaigns to encourage students and staff to recycle effectively.',0,'','2024-06-24 22:01:35','2024-06-24 22:01:35',0),
(9406,0,'Added new idea (#260) Create vertical garden walls in unused spaces around the school. These walls would feature plants that improve air quality indoors, enhance aesthetic appeal, and provide educational opportunities about gardening and sustainable agriculture.',0,'','2024-06-24 22:01:53','2024-06-24 22:01:53',0),
(9407,0,'Added idea 258 to topic 473',0,'','2024-06-24 22:02:00','2024-06-24 22:02:00',0),
(9408,0,'Added idea 259 to topic 473',0,'','2024-06-24 22:02:00','2024-06-24 22:02:00',0),
(9409,0,'Added new idea (#261) Develop an augmented reality (AR) app that provides interactive campus tours for new students and visitors. Users can explore key campus locations, historical landmarks, and facilities by overlaying digital information and interactive elements through their mobile devices.',0,'','2024-06-24 22:02:38','2024-06-24 22:02:38',0),
(9410,0,'Added new idea (#262) Create a dedicated virtual learning lab equipped with high-speed internet, VR headsets, and interactive digital resources. This lab would offer students immersive learning experiences in subjects like science, history, and geography, enabling them to explore concepts in a virtual environment.',0,'','2024-06-24 22:02:54','2024-06-24 22:02:54',0),
(9411,0,'Added idea 262 to topic 474',0,'','2024-06-24 22:03:23','2024-06-24 22:03:23',0),
(9412,0,'Added idea 261 to topic 474',0,'','2024-06-24 22:03:23','2024-06-24 22:03:23',0),
(9413,0,'Added idea 262 to topic 473',0,'','2024-06-24 22:03:33','2024-06-24 22:03:33',0),
(9414,0,'Added idea 261 to topic 473',0,'','2024-06-24 22:03:33','2024-06-24 22:03:33',0),
(9415,0,'Added new idea (#263) Establish a student-run art gallery within the school where students can showcase their artworks, including paintings, sculptures, photographs, and digital art. This space would not only promote creativity but also provide a platform for students to express themselves artistically and share their work with the school community.',0,'','2024-06-24 22:04:12','2024-06-24 22:04:12',0),
(9416,0,'Added new idea (#264) Organize an annual performing arts festival featuring student performances in music, dance, theater, and spoken word. The festival could include workshops, masterclasses with professional artists, and culminate in a showcase event that celebrates the diverse talents and creativity of students.',0,'','2024-06-24 22:04:28','2024-06-24 22:04:28',0),
(9417,0,'Added idea 264 to topic 474',0,'','2024-06-24 22:04:46','2024-06-24 22:04:46',0),
(9418,0,'Added idea 263 to topic 474',0,'','2024-06-24 22:04:46','2024-06-24 22:04:46',0),
(9419,0,'Added new idea (#265) Create a school garden dedicated to growing fresh produce, which is then donated to local food banks or community organizations supporting food-insecure individuals and families. Students would be involved in all aspects of gardening, from planting to harvesting, promoting sustainability and community service simultaneously.',0,'','2024-06-24 22:05:21','2024-06-24 22:05:21',0),
(9420,0,'Added new idea (#266) Launch an adopt-a-neighbor program where students volunteer to assist elderly or disabled community members with tasks such as grocery shopping, yard work, or companionship visits. This program aims to foster intergenerational connections and provide valuable support to those in need within the local community.',0,'','2024-06-24 22:05:52','2024-06-24 22:05:52',0),
(9421,0,'Added idea 266 to topic 475',0,'','2024-06-24 22:06:13','2024-06-24 22:06:13',0),
(9422,0,'Added idea 265 to topic 475',0,'','2024-06-24 22:06:13','2024-06-24 22:06:13',0),
(9423,0,'Idea deleted, id=258 by 0',0,'','2024-06-24 22:07:15','2024-06-24 22:07:15',0),
(9424,0,'Idea deleted, id=259 by 0',0,'','2024-06-24 22:07:15','2024-06-24 22:07:15',0),
(9425,0,'Added new idea (#267) Install solar-powered charging stations throughout the school campus. These stations would allow students to charge their devices using renewable energy, reducing the reliance on traditional electricity sources and promoting sustainable practices.',0,'','2024-06-24 22:07:32','2024-06-24 22:07:32',0),
(9426,0,'Added new idea (#268) Implement a comprehensive recycling program across the school. This initiative would include clear signage, designated recycling bins for paper, plastic, and glass, as well as educational campaigns to encourage students and staff to recycle effectively.',0,'','2024-06-24 22:07:47','2024-06-24 22:07:47',0),
(9427,0,'Added idea 268 to topic 472',0,'','2024-06-24 22:08:15','2024-06-24 22:08:15',0),
(9428,0,'Added idea 267 to topic 472',0,'','2024-06-24 22:08:15','2024-06-24 22:08:15',0),
(9429,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:09:28','2024-06-24 22:09:28',0),
(9430,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:19:47','2024-06-24 22:19:47',0),
(9431,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:20:58','2024-06-24 22:20:58',0),
(9432,0,'Idea  267 incremented likes',0,'','2024-06-24 22:20:59','2024-06-24 22:20:59',0),
(9433,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:21:53','2024-06-24 22:21:53',0),
(9434,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:22:59','2024-06-24 22:22:59',0),
(9435,0,'Idea  268 incremented likes',0,'','2024-06-24 22:23:03','2024-06-24 22:23:03',0),
(9436,0,'Comment  18 incremented likes',0,'','2024-06-24 22:23:03','2024-06-24 22:23:03',0),
(9437,0,'Idea  268 decrementing likes',0,'','2024-06-24 22:23:05','2024-06-24 22:23:05',0),
(9438,0,'Idea  268 incremented likes',0,'','2024-06-24 22:23:06','2024-06-24 22:23:06',0),
(9439,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:23:20','2024-06-24 22:23:20',0),
(9440,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:24:51','2024-06-24 22:24:51',0),
(9441,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:26:07','2024-06-24 22:26:07',0),
(9442,0,'Idea  261 incremented likes',0,'','2024-06-24 22:26:09','2024-06-24 22:26:09',0),
(9443,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:28:12','2024-06-24 22:28:12',0),
(9444,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:28:25','2024-06-24 22:28:25',0),
(9445,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:28:34','2024-06-24 22:28:34',0),
(9446,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:29:30','2024-06-24 22:29:30',0),
(9447,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:29:46','2024-06-24 22:29:46',0),
(9448,0,'Idea  265 incremented likes',0,'','2024-06-24 22:29:47','2024-06-24 22:29:47',0),
(9449,0,'Comment  25 incremented likes',0,'','2024-06-24 22:29:49','2024-06-24 22:29:49',0),
(9450,0,'Added idea 263 to topic 474',0,'','2024-06-24 22:35:46','2024-06-24 22:35:46',0),
(9451,0,'Added idea 264 to topic 474',0,'','2024-06-24 22:35:46','2024-06-24 22:35:46',0),
(9452,0,'Added new comment (#0) user: 165',0,'','2024-06-24 22:39:05','2024-06-24 22:39:05',0),
(9453,0,'Comment  27 incremented likes',0,'','2024-06-24 22:39:20','2024-06-24 22:39:20',0),
(9454,0,'Edited idea 266 by 165',0,'','2024-06-25 00:51:18','2024-06-25 00:51:18',0),
(9455,0,'Edited idea 266 by 165',0,'','2024-06-25 00:52:09','2024-06-25 00:52:09',0),
(9456,0,'Edited idea 265 by 165',0,'','2024-06-25 00:53:34','2024-06-25 00:53:34',0),
(9457,0,'Edited idea 264 by 165',0,'','2024-06-25 00:55:37','2024-06-25 00:55:37',0),
(9458,0,'Edited idea 263 by 165',0,'','2024-06-25 00:56:25','2024-06-25 00:56:25',0),
(9459,0,'Edited idea 261 by 165',0,'','2024-06-25 00:58:44','2024-06-25 00:58:44',0),
(9460,0,'Edited idea 262 by 165',0,'','2024-06-25 00:59:49','2024-06-25 00:59:49',0);
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_texts`
--

LOCK TABLES `au_texts` WRITE;
/*!40000 ALTER TABLE `au_texts` DISABLE KEYS */;
INSERT INTO `au_texts` VALUES
(8,0,'Sample Text','test message',0,0,'Agree',0,NULL,'2024-06-22 22:30:30','2024-06-22 22:30:30',165,'3ce902fa6d5fc806c02017188a2e0daa',1),
(9,0,'Test Mandatory message','test this message',2,0,'Agree',0,NULL,'2024-06-23 12:27:27','2024-06-23 12:27:27',165,'0a4f7789ffe37e398393af1fa120f4d0',1),
(10,0,'Optional consent message','This message is not mandatory',1,0,'Agree',0,NULL,'2024-06-23 12:30:58','2024-06-23 12:30:58',165,'faca362395a9bf1f4add5370aa6ee67d',1),
(11,0,'Another consent','Mandatory consent',2,0,'Agree',0,NULL,'2024-06-23 12:47:07','2024-06-23 12:47:07',165,'da35ed97b7014ba1a8f456c47e0f54ba',1);
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
  `phase_duration_0` int(11) DEFAULT NULL COMMENT 'Duration of phase 1',
  `phase_duration_1` int(11) DEFAULT NULL COMMENT 'Duration of phase 1',
  `phase_duration_2` int(11) DEFAULT NULL COMMENT 'Duration of phase 1',
  `phase_duration_3` int(11) DEFAULT NULL COMMENT 'Duration of phase 1',
  `phase_duration_4` int(11) DEFAULT NULL COMMENT 'Duration of phase 1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=476 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_topics`
--

LOCK TABLES `au_topics` WRITE;
/*!40000 ALTER TABLE `au_topics` DISABLE KEYS */;
INSERT INTO `au_topics` VALUES
(472,'Green Innovations Vault','Ideas aimed at reducing our environmental footprint, promoting recycling initiatives, sustainable energy projects, and conservation efforts.','Internal description?',1,10,'2024-06-24 21:51:34','2024-06-24 21:51:34','7ba1cc8d79a7cb7457427a5254f69d41',165,106,10,1,NULL,14,14,14,14,14),
(473,'Tech Frontier','New apps, digital learning tools, robotics projects, smart solutions for the school, and advancements in virtual reality or augmented reality.','Internal description?',1,10,'2024-06-24 21:52:11','2024-06-24 21:52:11','ea45d51cb116ce44f5e69e992393146f',165,106,20,1,NULL,14,14,14,14,14),
(474,'Creative Canvas','Imaginative ideas spanning visual arts, music performances, theater productions, literary works and other art expression.','Internal description?',1,10,'2024-06-24 21:53:20','2024-06-24 21:53:20','d2c9fb9871033f36407ab0bcf0e676a3',165,106,30,1,NULL,14,14,14,14,14),
(475,'Service Heart','Proposals for volunteer programs, fundraising events, social justice initiatives, outreach campaigns, and projects aimed at improving the well-being of others.','Internal description?',1,10,'2024-06-24 21:54:04','2024-06-24 21:54:04','aacc93c0cdabb2bf4106f9f1ce3bf2b5',165,106,40,1,NULL,14,14,14,14,14);
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
INSERT INTO `au_user_levels` VALUES
(10,'Guest','Read only',1),
(20,'Basic','Read, Create Ideas',1),
(30,'Moderator','Read ',1),
(40,'Super Moderator','',1),
(50,'Admin',' ',1),
(60,'Tech admin','',1);
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
  `updater_id` int(11) DEFAULT NULL COMMENT 'user_id of the updater',
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=264 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_users_basedata`
--

LOCK TABLES `au_users_basedata` WRITE;
/*!40000 ALTER TABLE `au_users_basedata` DISABLE KEYS */;
INSERT INTO `au_users_basedata` VALUES
(165,'Admin User','Admin','aula','aula@aula.de','$2y$10$.IPqFlsIXv71/l2Chtopx.GnAuL55I75l.a5fxjn7BLlzPda71AbK','0','3ca2a93f5f309431f65c6770194d1dc6','0',NULL,1,'2023-06-17 14:58:43','2024-06-23 12:47:07',0,'21232f297a57a5a743894a0e4a801fc3',50,NULL,'2024-06-22 14:24:47',NULL,NULL,0,NULL,NULL,NULL,NULL,0,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `au_votes`
--

LOCK TABLES `au_votes` WRITE;
/*!40000 ALTER TABLE `au_votes` DISABLE KEYS */;
INSERT INTO `au_votes` VALUES
(111,165,255,-1,1,'2024-06-24 16:24:23','2024-06-24 16:24:23','2586d37b78ada0b8760e3a67a073db98',1,0,'');
/*!40000 ALTER TABLE `au_votes` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-06-25  1:11:12
