-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: boardinghouse
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_years` (
  `academic_year_id` int(11) NOT NULL AUTO_INCREMENT,
  `start_year` year(4) NOT NULL,
  `end_year` year(4) NOT NULL,
  `semester` enum('First','Second','Summer') DEFAULT NULL,
  PRIMARY KEY (`academic_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_years`
--

LOCK TABLES `academic_years` WRITE;
/*!40000 ALTER TABLE `academic_years` DISABLE KEYS */;
INSERT INTO `academic_years` VALUES (9,2024,2025,'First'),(21,2024,2025,'Second'),(22,2025,2026,'First');
/*!40000 ALTER TABLE `academic_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `posted_on` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`announcement_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,'GENERAL CLEAN UP','Clean up your room on or before May 12, 2025','2025-05-09 11:13:16'),(2,'CLEANUP','WHAT: General Cleanup\r\nWHEN: JAN-23','2025-05-11 14:44:11');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beds`
--

DROP TABLE IF EXISTS `beds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `beds` (
  `bed_id` int(11) NOT NULL AUTO_INCREMENT,
  `bed_no` int(11) NOT NULL,
  `status` enum('Vacant','Occupied') DEFAULT 'Vacant',
  `deck` enum('Upper','Lower') DEFAULT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `room_id` int(11) NOT NULL,
  `bed_type` enum('Single','Double') NOT NULL,
  PRIMARY KEY (`bed_id`),
  KEY `FK_rooms_TO_beds` (`room_id`),
  CONSTRAINT `FK_rooms_TO_beds` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=164 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `beds`
--

LOCK TABLES `beds` WRITE;
/*!40000 ALTER TABLE `beds` DISABLE KEYS */;
INSERT INTO `beds` VALUES (1,1,'Occupied','Lower',1100.00,1,'Single'),(2,2,'Occupied','Lower',1100.00,1,'Single'),(3,3,'Occupied','Lower',1100.00,1,'Single'),(4,4,'Occupied','Upper',1100.00,1,'Single'),(5,5,'Occupied','Upper',1100.00,1,'Single'),(6,6,'Occupied','Upper',1100.00,1,'Single'),(7,7,'Occupied','Lower',1000.00,2,'Single'),(8,8,'Occupied','Lower',1000.00,2,'Single'),(9,9,'Occupied','Lower',1000.00,2,'Single'),(10,10,'Occupied','Upper',1000.00,2,'Single'),(11,11,'Occupied','Upper',1000.00,2,'Single'),(12,12,'Occupied','Upper',1000.00,2,'Single'),(13,13,'Occupied','Lower',1000.00,3,'Single'),(14,14,'Occupied','Lower',1000.00,3,'Single'),(15,15,'Occupied','Lower',1000.00,3,'Single'),(16,16,'Occupied','Upper',1000.00,3,'Single'),(17,17,'Occupied','Upper',1000.00,3,'Single'),(18,18,'Occupied','Upper',1000.00,3,'Single'),(19,19,'Occupied','Lower',1000.00,4,'Single'),(20,20,'Occupied','Lower',1000.00,4,'Single'),(21,21,'Occupied','Lower',1000.00,4,'Single'),(22,22,'Occupied','Upper',1000.00,4,'Single'),(23,23,'Occupied','Upper',1000.00,4,'Single'),(24,24,'Occupied','Upper',1000.00,4,'Single'),(25,25,'Occupied','Lower',1000.00,5,'Single'),(26,26,'Occupied','Lower',1000.00,5,'Single'),(27,27,'Occupied','Lower',1000.00,5,'Single'),(28,28,'Occupied','Upper',1000.00,5,'Single'),(29,29,'Occupied','Upper',1000.00,5,'Single'),(30,30,'Occupied','Upper',1000.00,5,'Single'),(31,31,'Occupied','Lower',1000.00,6,'Single'),(32,32,'Occupied','Lower',1000.00,6,'Single'),(33,33,'Occupied','Lower',1000.00,6,'Single'),(34,34,'Occupied','Upper',1000.00,6,'Single'),(35,35,'Occupied','Upper',1000.00,6,'Single'),(36,36,'Occupied','Upper',1000.00,6,'Single'),(37,37,'Occupied','Lower',1000.00,7,'Single'),(38,38,'Occupied','Lower',1000.00,7,'Single'),(39,39,'Occupied','Lower',1000.00,7,'Single'),(40,40,'Occupied','Upper',1000.00,7,'Single'),(41,41,'Vacant','Upper',1000.00,7,'Single'),(42,42,'Vacant','Upper',1000.00,7,'Single'),(43,43,'Vacant','Lower',1000.00,8,'Single'),(44,44,'Vacant','Lower',1000.00,8,'Single'),(45,45,'Occupied','Lower',1000.00,8,'Single'),(46,46,'Occupied','Upper',1000.00,8,'Single'),(47,47,'Occupied','Upper',1000.00,8,'Single'),(48,48,'Vacant','Upper',1000.00,8,'Single'),(49,49,'Vacant','Lower',1000.00,9,'Single'),(50,50,'Vacant','Lower',1000.00,9,'Single'),(51,51,'Vacant','Lower',1000.00,9,'Single'),(52,52,'Vacant','Upper',1000.00,9,'Single'),(53,53,'Vacant','Upper',1000.00,9,'Single'),(54,54,'Vacant','Upper',1000.00,9,'Single'),(55,55,'Vacant','Lower',1000.00,10,'Single'),(56,56,'Vacant','Lower',1000.00,10,'Single'),(57,57,'Vacant','Lower',1000.00,10,'Single'),(58,58,'Vacant','Upper',1000.00,10,'Single'),(59,59,'Vacant','Upper',1000.00,10,'Single'),(60,60,'Vacant','Upper',1000.00,10,'Single'),(62,62,'Vacant','Lower',1000.00,11,'Single'),(63,63,'Vacant','Lower',1000.00,11,'Single'),(64,64,'Vacant','Upper',1000.00,11,'Single'),(65,65,'Vacant','Upper',1000.00,11,'Single'),(66,66,'Vacant','Upper',1000.00,11,'Single'),(67,67,'Vacant','Lower',1000.00,12,'Single'),(68,68,'Vacant','Lower',1000.00,12,'Single'),(69,69,'Vacant','Lower',1000.00,12,'Single'),(70,70,'Vacant','Upper',1000.00,12,'Single'),(71,71,'Vacant','Upper',1000.00,12,'Single'),(72,72,'Vacant','Upper',1000.00,12,'Single'),(73,73,'Vacant','Lower',1000.00,13,'Single'),(74,74,'Vacant','Lower',1000.00,13,'Single'),(75,75,'Vacant','Lower',1000.00,13,'Single'),(76,76,'Vacant','Upper',1000.00,13,'Single'),(77,77,'Vacant','Upper',1000.00,13,'Single'),(78,78,'Vacant','Upper',1000.00,13,'Single'),(79,79,'Vacant','Lower',1000.00,14,'Single'),(80,80,'Vacant','Lower',1000.00,14,'Single'),(81,81,'Vacant','Lower',1000.00,14,'Single'),(82,82,'Vacant','Upper',1000.00,14,'Single'),(83,83,'Vacant','Upper',1000.00,14,'Single'),(84,84,'Vacant','Upper',1000.00,14,'Single'),(85,85,'Vacant','Lower',1000.00,15,'Single'),(86,86,'Vacant','Lower',1000.00,15,'Single'),(87,87,'Vacant','Lower',1000.00,15,'Single'),(88,88,'Vacant','Upper',1000.00,15,'Single'),(89,89,'Vacant','Upper',1000.00,15,'Single'),(90,90,'Vacant','Upper',1000.00,15,'Single'),(145,91,'Vacant','Upper',1000.00,6,'Single'),(146,92,'Vacant','Upper',1000.00,6,'Single'),(147,93,'Vacant','Upper',1000.00,6,'Single'),(148,94,'Vacant','Lower',1000.00,6,'Single'),(149,95,'Vacant','Lower',1000.00,6,'Single'),(150,96,'Vacant','Lower',1000.00,6,'Single'),(156,7,'Vacant','Lower',1100.00,1,'Single'),(157,1,'Vacant','Upper',1100.00,21,'Double'),(158,1,'Vacant',NULL,1100.00,22,'Single'),(159,2,'Vacant',NULL,1100.00,22,'Single'),(160,3,'Vacant',NULL,1100.00,22,'Single'),(161,4,'Vacant',NULL,1100.00,22,'Single'),(162,5,'Vacant',NULL,1100.00,22,'Single'),(163,6,'Vacant',NULL,1100.00,22,'Single');
/*!40000 ALTER TABLE `beds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `boarding`
--

DROP TABLE IF EXISTS `boarding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `boarding` (
  `boarding_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `bed_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `due_date` date NOT NULL,
  PRIMARY KEY (`boarding_id`),
  KEY `FK_tenants_TO_boarding` (`tenant_id`),
  KEY `FK_beds_TO_boarding` (`bed_id`),
  CONSTRAINT `FK_beds_TO_boarding` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`bed_id`),
  CONSTRAINT `FK_tenants_TO_boarding` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=324 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `boarding`
--

LOCK TABLES `boarding` WRITE;
/*!40000 ALTER TABLE `boarding` DISABLE KEYS */;
INSERT INTO `boarding` VALUES (1,1,1,'0000-00-00','0000-00-00');
/*!40000 ALTER TABLE `boarding` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course`
--

DROP TABLE IF EXISTS `course`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course` (
  `course_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_code` varchar(10) NOT NULL,
  `course_description` varchar(250) NOT NULL,
  `major` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course`
--

LOCK TABLES `course` WRITE;
/*!40000 ALTER TABLE `course` DISABLE KEYS */;
INSERT INTO `course` VALUES (1,'BEEd','Bachelor of Elementary Education','General Education'),(2,'BIT','Bachelor of Industrial Technology','Automotive'),(3,'BIT','Bachelor of Industrial Technology','Drafting Technology'),(4,'BIT','Bachelor of Industrial Technology','Electrical Technology'),(5,'BIT','Bachelor of Industrial Technology','Electronics Technology'),(6,'BIT','Bachelor of Industrial Technology','Food Preparation and Services Technology'),(7,'BIT','Bachelor of Industrial Technology','Heating, Ventilation, Air-Conditioning and Refrigeration Technology'),(8,'BTLEd','Bachelor of Technology and Livelihood Education','Home Economics'),(9,'BTLEd','Bachelor of Technology and Livelihood Education','Industrial Arts'),(10,'BTLEd','Bachelor of Technology and Livelihood Education','Information and Communications Technology'),(11,'BSCE','Bachelor of Science in Civil Engineering',NULL),(12,'BSCpE','Bachelor of Science in Computer Engineering',NULL),(13,'BSCRIM','Bachelor of Science in Criminology',NULL),(14,'BSEE','Bachelor of Science in Electrical Engineering',NULL),(15,'BSME','Bachelor of Science in Mechanical Engineering',NULL),(16,'BSFT','Bachelor of Science in Food Technology',NULL),(17,'BSHM','Bachelor of Science in Hospitality Management',NULL),(18,'BSTM','Bachelor of Science in Tourism Management',NULL),(19,'BSInfoTech','Bachelor of Science in Information Technology','Programming'),(20,'BSInfoTech','Bachelor of Science in Information Technology','Networking');
/*!40000 ALTER TABLE `course` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(250) NOT NULL,
  PRIMARY KEY (`expense_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,'Electricity Bill'),(2,'Water Bill');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `floors`
--

DROP TABLE IF EXISTS `floors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `floors` (
  `floor_id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_no` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`floor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `floors`
--

LOCK TABLES `floors` WRITE;
/*!40000 ALTER TABLE `floors` DISABLE KEYS */;
INSERT INTO `floors` VALUES (1,'FLR-1'),(2,'FLR-2'),(3,'FLR-3'),(5,'FLR-4'),(6,'FLR-5');
/*!40000 ALTER TABLE `floors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guardians`
--

DROP TABLE IF EXISTS `guardians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guardians` (
  `guardian_id` int(11) NOT NULL AUTO_INCREMENT,
  `last_name` varchar(120) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `middle_name` varchar(120) DEFAULT NULL,
  `mobile_no` varchar(11) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  PRIMARY KEY (`guardian_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guardians`
--

LOCK TABLES `guardians` WRITE;
/*!40000 ALTER TABLE `guardians` DISABLE KEYS */;
INSERT INTO `guardians` VALUES (1,'Mercedes','Maria','M','09123456789','Mother'),(2,'Alvares','Jane',NULL,'09323457432','Father'),(3,'Samante','Melba',NULL,'09236278368','Silingan'),(4,'Geraldez','Ding',NULL,'09268058435','Auntie'),(5,'Smith','Fyang',NULL,'09127846211','Lola'),(6,'Merano','Mario','','09168314880',''),(7,'jade','mana','','1000','abngapsamnida');
/*!40000 ALTER TABLE `guardians` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `monthly_expenses`
--

DROP TABLE IF EXISTS `monthly_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `monthly_expenses` (
  `monthly_expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `academic_year_id` int(11) NOT NULL,
  `month` date NOT NULL,
  `expense_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`monthly_expense_id`),
  KEY `FK_academic_years_TO_monthly_expenses` (`academic_year_id`),
  KEY `FK_expenses_TO_monthly_expenses` (`expense_id`),
  CONSTRAINT `FK_academic_years_TO_monthly_expenses` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`academic_year_id`),
  CONSTRAINT `FK_expenses_TO_monthly_expenses` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`expense_id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monthly_expenses`
--

LOCK TABLES `monthly_expenses` WRITE;
/*!40000 ALTER TABLE `monthly_expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `monthly_expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `boarding_id` int(11) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` enum('Cash','Credit') NOT NULL,
  `tender` decimal(10,2) DEFAULT NULL,
  `change_payment` decimal(10,2) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `payment_for_month_of` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `FK_users_TO_payments` (`user_id`),
  KEY `FK_boarding_TO_payments` (`boarding_id`),
  KEY `academic_year_id` (`academic_year_id`),
  CONSTRAINT `FK_boarding_TO_payments` FOREIGN KEY (`boarding_id`) REFERENCES `boarding` (`boarding_id`),
  CONSTRAINT `FK_users_TO_payments` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `academic_year_id` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`academic_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL AUTO_INCREMENT,
  `room_no` varchar(10) DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `floor_id` int(11) NOT NULL,
  `room_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`room_id`),
  KEY `FK_floors_TO_rooms` (`floor_id`),
  CONSTRAINT `FK_floors_TO_rooms` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`floor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (1,'RM-1',6,1,NULL),(2,'RM-2',6,1,NULL),(3,'RM-3',6,1,NULL),(4,'RM-4',6,1,NULL),(5,'RM-5',6,1,NULL),(6,'RM-1',6,2,NULL),(7,'RM-2',6,2,NULL),(8,'RM-3',6,2,NULL),(9,'RM-4',6,2,NULL),(10,'RM-5',6,2,NULL),(11,'RM-1',6,3,NULL),(12,'RM-2',6,3,NULL),(13,'RM-3',6,3,NULL),(14,'RM-4',6,3,NULL),(15,'RM-5',6,3,NULL),(21,'RM-6',6,2,NULL),(22,'RM-1',0,6,'uploads/1746852868_king-rosales-profile-photo-square.jpg');
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suggestions`
--

DROP TABLE IF EXISTS `suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suggestions` (
  `suggestion_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `suggestion` text NOT NULL,
  `date_submitted` datetime DEFAULT current_timestamp(),
  `status` enum('Pending','Noted') NOT NULL DEFAULT 'Pending',
  PRIMARY KEY (`suggestion_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `suggestions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suggestions`
--

LOCK TABLES `suggestions` WRITE;
/*!40000 ALTER TABLE `suggestions` DISABLE KEYS */;
INSERT INTO `suggestions` VALUES (16,1,'fix the windows','2025-05-09 00:10:11','Pending');
/*!40000 ALTER TABLE `suggestions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `tenant_details`
--

DROP TABLE IF EXISTS `tenant_details`;
/*!50001 DROP VIEW IF EXISTS `tenant_details`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `tenant_details` AS SELECT 
 1 AS `tenant_id`,
 1 AS `first_name`,
 1 AS `last_name`,
 1 AS `middle_name`,
 1 AS `birthdate`,
 1 AS `address`,
 1 AS `gender`,
 1 AS `mobile_no`,
 1 AS `course_code`,
 1 AS `course_description`,
 1 AS `bed_no`,
 1 AS `deck`,
 1 AS `room_no`,
 1 AS `guardian_first_name`,
 1 AS `guardian_last_name`,
 1 AS `guardian_mobile`,
 1 AS `relationship`,
 1 AS `start_year`,
 1 AS `end_year`,
 1 AS `semester`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `tenant_history`
--

DROP TABLE IF EXISTS `tenant_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `bed_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Completed','Dropped','Migrated','Removed') DEFAULT 'Migrated',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `academic_year_id` (`academic_year_id`),
  KEY `bed_id` (`bed_id`),
  CONSTRAINT `tenant_history_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`),
  CONSTRAINT `tenant_history_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`academic_year_id`),
  CONSTRAINT `tenant_history_ibfk_3` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`bed_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_history`
--

LOCK TABLES `tenant_history` WRITE;
/*!40000 ALTER TABLE `tenant_history` DISABLE KEYS */;
INSERT INTO `tenant_history` VALUES (1,1,21,1,'0000-00-00','0000-00-00','Migrated',NULL,'2025-05-10 13:59:16');
/*!40000 ALTER TABLE `tenant_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `tenant_id` int(11) NOT NULL AUTO_INCREMENT,
  `academic_year_id` int(11) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `middle_name` varchar(120) DEFAULT NULL,
  `birthdate` date NOT NULL,
  `address` varchar(250) NOT NULL,
  `gender` enum('M','F') NOT NULL,
  `mobile_no` varchar(11) NOT NULL,
  `guardian_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `year_level` enum('1','2','3','4') NOT NULL,
  `tenant_type` enum('Student','Non-Student') DEFAULT NULL,
  `profile_picture` varchar(225) DEFAULT NULL,
  `student_id` varchar(20) NOT NULL DEFAULT 'Student',
  `is_student` tinyint(1) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `is_willing_to_continue` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`tenant_id`),
  KEY `FK_guardians_TO_tenants` (`guardian_id`),
  KEY `FK_course_TO_tenants` (`course_id`),
  KEY `FK_academic_years_TO_tenants` (`academic_year_id`),
  KEY `fk_tenants_user` (`user_id`),
  CONSTRAINT `FK_academic_years_TO_tenants` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`academic_year_id`),
  CONSTRAINT `FK_course_TO_tenants` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`),
  CONSTRAINT `FK_guardians_TO_tenants` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`guardian_id`),
  CONSTRAINT `fk_tenants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,21,'Juan','Dela Cruz','Gera','2005-06-22','Anahawan','M','09855872902',1,1,'2','Student','king-rosales-profile-photo-square.jpg','2310074',1,20,0);
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(120) NOT NULL,
  `role` enum('Admin','Tenant') NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin','Admin'),(20,'juan','$2y$10$zstOikDNz1AuwpqgNMNny.uvm0ItJP3p18X2cUB71.b4LTRHKBao2','Tenant'),(21,'administrator','$2y$10$zstOikDNz1AuwpqgNMNny.uvm0ItJP3p18X2cUB71.b4LTRHKBao2','Admin');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'boardinghouse'
--

--
-- Final view structure for view `tenant_details`
--

/*!50001 DROP VIEW IF EXISTS `tenant_details`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `tenant_details` AS select `t`.`tenant_id` AS `tenant_id`,`t`.`first_name` AS `first_name`,`t`.`last_name` AS `last_name`,`t`.`middle_name` AS `middle_name`,`t`.`birthdate` AS `birthdate`,`t`.`address` AS `address`,`t`.`gender` AS `gender`,`t`.`mobile_no` AS `mobile_no`,`c`.`course_code` AS `course_code`,`c`.`course_description` AS `course_description`,`b`.`bed_no` AS `bed_no`,`b`.`deck` AS `deck`,`r`.`room_no` AS `room_no`,`g`.`first_name` AS `guardian_first_name`,`g`.`last_name` AS `guardian_last_name`,`g`.`mobile_no` AS `guardian_mobile`,`g`.`relationship` AS `relationship`,`ay`.`start_year` AS `start_year`,`ay`.`end_year` AS `end_year`,`ay`.`semester` AS `semester` from ((`guardians` `g` left join ((((`tenants` `t` join `course` `c` on(`t`.`course_id` = `c`.`course_id`)) join `boarding` `bo` on(`t`.`tenant_id` = `bo`.`tenant_id`)) join `beds` `b` on(`bo`.`bed_id` = `b`.`bed_id`)) join `rooms` `r` on(`b`.`room_id` = `r`.`room_id`)) on(`t`.`guardian_id` = `g`.`guardian_id`)) join `academic_years` `ay` on(`t`.`academic_year_id` = `ay`.`academic_year_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-05-11 15:09:08
