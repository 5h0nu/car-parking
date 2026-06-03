-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: car_parking_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `key_name` varchar(50) NOT NULL,
  `val_value` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('capacity_limit','20','Total slots capacity limit'),('rate_2_wheel','50','Hourly rate for 2-Wheelers (Bikes/Scooters)'),('rate_4_wheel','100','Hourly rate for 4-Wheelers (Cars/SUVs)'),('rate_truck_heavy','150','Hourly rate for Trucks and Heavy Vehicles'),('terminal_name','ParkMaster East Gate Terminal','Terminal gate descriptor');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slots`
--

DROP TABLE IF EXISTS `slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_number` varchar(10) NOT NULL,
  `floor` varchar(30) NOT NULL DEFAULT 'Ground Floor',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slot_number` (`slot_number`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slots`
--

LOCK TABLES `slots` WRITE;
/*!40000 ALTER TABLE `slots` DISABLE KEYS */;
INSERT INTO `slots` VALUES (1,'A101','Ground Floor','2026-06-03 11:19:02'),(2,'A102','Ground Floor','2026-06-03 11:19:02'),(3,'A103','Ground Floor','2026-06-03 11:19:02'),(4,'A104','Ground Floor','2026-06-03 11:19:02'),(5,'A105','Ground Floor','2026-06-03 11:19:02'),(6,'A106','Ground Floor','2026-06-03 11:19:02'),(7,'A107','Ground Floor','2026-06-03 11:19:02'),(8,'A108','Ground Floor','2026-06-03 11:19:02'),(9,'A109','Ground Floor','2026-06-03 11:19:02'),(10,'A110','Ground Floor','2026-06-03 11:19:02'),(11,'A111','Ground Floor','2026-06-03 11:19:02'),(12,'A112','Ground Floor','2026-06-03 11:19:02'),(13,'A113','Ground Floor','2026-06-03 11:19:02'),(14,'A114','Ground Floor','2026-06-03 11:19:02'),(15,'A115','Ground Floor','2026-06-03 11:19:02'),(16,'B01','1st Floor','2026-06-03 11:21:48');
/*!40000 ALTER TABLE `slots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_name` varchar(100) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `slot_number` varchar(10) NOT NULL,
  `check_in_time` datetime NOT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(30) DEFAULT 'CASH',
  `status` varchar(20) NOT NULL DEFAULT 'parked',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES (1,'jay','DL 14 5678','4-Wheel','A101','2026-06-03 16:41:58','2026-06-03 17:07:08',100.00,'UPI','checked_out'),(3,'dsffdfds','DL33332DF','4-Wheel','A113','2026-06-03 17:06:23','2026-06-03 17:10:12',100.00,'CARD','checked_out'),(4,'fdssdsfdsdf','FFDSDFD32322F','2-Wheel','A107','2026-06-03 17:06:38',NULL,0.00,'CASH','parked'),(5,'fdsffdssfd','2343223443','Truck/Heavy','A104','2026-06-03 17:06:56',NULL,0.00,'CASH','parked'),(6,'test','DL378 22 44','4-Wheel','A102','2026-06-03 17:09:15',NULL,0.00,'CASH','parked'),(7,'tesing 3','KA 27 T 7799','4-Wheel','B01','2026-06-03 17:09:34',NULL,0.00,'CASH','parked');
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$uG0T.r/gQfNaTadjAUTGE.rIZXsSzp8XrYMSONzZ2a.C2bSN09zSm','admin@carpark.com','admin','2026-06-03 11:00:47'),(2,'shanu','$2y$10$3dzfm4HR01EuzR8gXNie7OvVDW9hUvcFLtM554YpPqNekPNhcvUcO','test@gmail.com','staff','2026-06-03 11:38:37');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-03 17:25:06
