-- MySQL dump 10.13  Distrib 8.0.41, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: capstonestoredb
-- ------------------------------------------------------
-- Server version 8.0.41

-- Preserve current character set settings before changing them for the dump
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
-- Preserve current result character set settings
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
-- Preserve current collation settings
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
-- Ensure connection uses UTF-8 for this dump
/*!50503 SET NAMES utf8 */;
-- Save and override current time zone so timestamps are consistent
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
 /*!40103 SET TIME_ZONE='+00:00' */;
-- Temporarily disable unique checks to speed up import
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
-- Temporarily disable foreign key checks to avoid constraint issues during import
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
-- Save current SQL mode and set NO_AUTO_VALUE_ON_ZERO for consistent AUTO_INCREMENT behavior
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
-- Save current SQL notes setting and suppress notes during import
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `address`
--

-- Drop address table if it already exists, so it can be recreated cleanly
DROP TABLE IF EXISTS `address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for the table definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores multiple addresses per customer, including default flag and country reference
CREATE TABLE `address` (
  `address_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country_id` int DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`address_id`),
  KEY `customer_id` (`customer_id`),
  KEY `country_id` (`country_id`),
  -- Link each address to a customer; cascade delete when customer is removed
  CONSTRAINT `address_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  -- Link address to a country record
  CONSTRAINT `address_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `country` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `address`
--

-- Lock address table during data load to ensure consistency
LOCK TABLES `address` WRITE;
/*!40000 ALTER TABLE `address` DISABLE KEYS */;
-- No address rows were exported in this dump
/*!40000 ALTER TABLE `address` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin`
--

-- Drop admin table if it exists
DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for admin definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores administrative users and their roles
CREATE TABLE `admin` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('SUPER_ADMIN','ADMIN','MODERATOR') DEFAULT 'ADMIN',
  `mfa_secret` varchar(64) DEFAULT NULL,
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  -- Ensure each username is unique
  UNIQUE KEY `username` (`username`),
  -- Ensure each email is unique
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

-- Lock admin table during potential data load
LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
-- No admin rows were exported in this dump
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bodymeasurement`
--

-- Drop bodymeasurement table if it exists
DROP TABLE IF EXISTS `bodymeasurement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for bodymeasurement definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores height, weight, BMI, and body measurements per customer
CREATE TABLE `bodymeasurement` (
  `measurement_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  -- Generated column that automatically calculates BMI from height and weight
  `bmi_value` decimal(5,2) GENERATED ALWAYS AS ((`weight_kg` / ((`height_cm` / 100) * (`height_cm` / 100)))) STORED,
  `bodytype_id` int DEFAULT NULL,
  `chest_cm` decimal(5,2) DEFAULT NULL,
  `waist_cm` decimal(5,2) DEFAULT NULL,
  `hips_cm` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`measurement_id`),
  KEY `customer_id` (`customer_id`),
  KEY `bodytype_id` (`bodytype_id`),
  -- Connect measurements to a specific customer; cascade when customer is deleted
  CONSTRAINT `bodymeasurement_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  -- Link measurements to a body type classification
  CONSTRAINT `bodymeasurement_ibfk_2` FOREIGN KEY (`bodytype_id`) REFERENCES `bodytype` (`bodytype_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bodymeasurement`
--

-- Lock bodymeasurement table while inserting data
LOCK TABLES `bodymeasurement` WRITE;
/*!40000 ALTER TABLE `bodymeasurement` DISABLE KEYS */;
-- Initial measurement record for customer with ID 2
INSERT INTO `bodymeasurement` (`measurement_id`, `customer_id`, `height_cm`, `weight_kg`, `bodytype_id`, `chest_cm`, `waist_cm`, `hips_cm`, `created_at`) VALUES (1,2,175.00,70.00,NULL,NULL,NULL,NULL,'2025-12-17 20:53:23');
/*!40000 ALTER TABLE `bodymeasurement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bodytype`
--

-- Drop bodytype table if it exists
DROP TABLE IF EXISTS `bodytype`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for bodytype definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Defines body type categories (e.g., ectomorph, mesomorph)
CREATE TABLE `bodytype` (
  `bodytype_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`bodytype_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bodytype`
--

-- Lock bodytype table while inserting seed data
LOCK TABLES `bodytype` WRITE;
/*!40000 ALTER TABLE `bodytype` DISABLE KEYS */;
-- Seed body type reference data
INSERT INTO `bodytype` VALUES (1,'Ectomorph','Lean and long body type','2025-12-17 19:32:08'),(2,'Mesomorph','Athletic and muscular body type','2025-12-17 19:32:08'),(3,'Endomorph','Rounded and softer body type','2025-12-17 19:32:08');
/*!40000 ALTER TABLE `bodytype` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart`
--

-- Drop cart table if it exists
DROP TABLE IF EXISTS `cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for cart definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Represents a shopping cart per customer with status tracking
CREATE TABLE `cart` (
  `cart_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `status` enum('ACTIVE','COMPLETED','ABANDONED') DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_id`),
  KEY `customer_id` (`customer_id`),
  -- Associate each cart with a customer; delete cart when customer is removed
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart`
--

-- Lock cart table while inserting data
LOCK TABLES `cart` WRITE;
/*!40000 ALTER TABLE `cart` DISABLE KEYS */;
-- Example active cart for customer with ID 2
INSERT INTO `cart` VALUES (4,2,'ACTIVE','2025-12-17 20:52:16','2025-12-17 20:52:16');
/*!40000 ALTER TABLE `cart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cartitem`
--

-- Drop cartitem table if it exists
DROP TABLE IF EXISTS `cartitem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for cartitem definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores individual items and quantities in each cart
CREATE TABLE `cartitem` (
  `cart_item_id` int NOT NULL AUTO_INCREMENT,
  `cart_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_item_id`),
  KEY `cart_id` (`cart_id`),
  KEY `variant_id` (`variant_id`),
  -- Link cart item to its parent cart; cascade removal with cart
  CONSTRAINT `cartitem_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`cart_id`) ON DELETE CASCADE,
  -- Link cart item to a specific product variant; cascade on variant deletion
  CONSTRAINT `cartitem_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `productvariant` (`variant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cartitem`
--

-- Lock cartitem table while inserting data
LOCK TABLES `cartitem` WRITE;
/*!40000 ALTER TABLE `cartitem` DISABLE KEYS */;
-- Example cart line: one unit of variant 4 in cart 4
INSERT INTO `cartitem` VALUES (2,4,4,1,'2025-12-17 20:55:52');
/*!40000 ALTER TABLE `cartitem` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `category`
--

-- Drop category table if it exists
DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for category definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores product category definitions (e.g., T-Shirts, Watches)
CREATE TABLE `category` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

-- Lock category table while inserting seed categories
LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
-- Seed product categories used in the store
INSERT INTO `category` VALUES (1,'T-Shirts','Comfortable cotton t-shirts','2025-12-17 19:32:08'),(2,'Watches','Fashion watches and timepieces','2025-12-17 19:32:08'),(3,'Bags','Handbags and backpacks','2025-12-17 19:32:08'),(4,'Belts','Leather and fabric belts','2025-12-17 19:32:08'),(5,'Shoes','Footwear for all occasions','2025-12-17 19:32:08'),(6,'Accessories','Various fashion accessories','2025-12-17 19:32:08');
/*!40000 ALTER TABLE `category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `colour`
--

-- Drop colour table if it exists
DROP TABLE IF EXISTS `colour`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for colour definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores color options with optional hex codes
CREATE TABLE `colour` (
  `colour_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `hex_code` varchar(7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`colour_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `colour`
--

-- Lock colour table while inserting color data
LOCK TABLES `colour` WRITE;
/*!40000 ALTER TABLE `colour` DISABLE KEYS */;
-- Seed common colors and their hex values
INSERT INTO `colour` VALUES (1,'Black','#000000','2025-12-17 19:32:08'),(2,'White','#FFFFFF','2025-12-17 19:32:08'),(3,'Blue','#0000FF','2025-12-17 19:32:08'),(4,'Red','#FF0000','2025-12-17 19:32:08'),(5,'Green','#008000','2025-12-17 19:32:08'),(6,'Gray','#808080','2025-12-17 19:32:08'),(7,'Navy','#000080','2025-12-17 19:32:08'),(8,'Beige','#F5F5DC','2025-12-17 19:32:08');
/*!40000 ALTER TABLE `colour` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `country`
--

-- Drop country table if it exists
DROP TABLE IF EXISTS `country`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for country definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores supported countries for addresses and shipping
CREATE TABLE `country` (
  `country_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `iso_code` varchar(3) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`country_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `country`
--

-- Lock country table while inserting country data
LOCK TABLES `country` WRITE;
/*!40000 ALTER TABLE `country` DISABLE KEYS */;
-- Seed list of countries used by the store
INSERT INTO `country` VALUES (1,'United States','US','2025-12-17 19:32:08'),(2,'Canada','CA','2025-12-17 19:32:08'),(3,'United Kingdom','GB','2025-12-17 19:32:08'),(4,'Jamaica','JM','2025-12-17 19:32:08'),(5,'Barbados','BB','2025-12-17 19:32:08'),(6,'Trinidad and Tobago','TT','2025-12-17 19:32:08'),(7,'Bahamas','BS','2025-12-17 19:32:08'),(8,'Cayman Islands','KY','2025-12-17 19:32:08'),(9,'Antigua and Barbuda','AG','2025-12-17 19:32:08'),(10,'Saint Lucia','LC','2025-12-17 19:32:08'),(11,'Saint Vincent and the Grenadines','VC','2025-12-17 19:32:08'),(12,'Grenada','GD','2025-12-17 19:32:08'),(13,'Dominican Republic','DO','2025-12-17 19:32:08'),(14,'Haiti','HT','2025-12-17 19:32:08'),(15,'Mexico','MX','2025-12-17 19:32:08'),(16,'Australia','AU','2025-12-17 19:32:08'),(17,'Germany','DE','2025-12-17 19:32:08'),(18,'France','FR','2025-12-17 19:32:08');
/*!40000 ALTER TABLE `country` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer`
--

-- Drop customer table if it exists
DROP TABLE IF EXISTS `customer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for customer definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores customer account information and credentials
CREATE TABLE `customer` (
  `customer_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `failed_login_attempts` int NOT NULL DEFAULT '0',
  `account_locked` tinyint(1) NOT NULL DEFAULT '0',
  `account_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `password_reset_required` tinyint(1) NOT NULL DEFAULT '0',
  `theme_preference` varchar(32) NOT NULL DEFAULT 'default',
  `profile_image` varchar(255) DEFAULT NULL,
  `theme_custom_json` text,
  `google_sub` varchar(191) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verification_expires_at` datetime DEFAULT NULL,
  `terms_version` varchar(32) DEFAULT NULL,
  `terms_accepted_at` datetime DEFAULT NULL,
  `terms_accepted_ip` varchar(45) DEFAULT NULL,
  `terms_accepted_user_agent` varchar(255) DEFAULT NULL,
  `mfasecret` varchar(32) DEFAULT NULL,
  `mfaenabled` tinyint(1) DEFAULT '0',
  `mfabackupcodes` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`),
  -- Ensure each customer email is unique
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uniq_customer_google_sub` (`google_sub`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer`
--

-- Lock customer table while inserting customer records
LOCK TABLES `customer` WRITE;
/*!40000 ALTER TABLE `customer` DISABLE KEYS */;
-- Seed test customer accounts with hashed passwords
INSERT INTO `customer`
(`customer_id`,`first_name`,`last_name`,`email`,`phone`,`password_hash`,`failed_login_attempts`,`account_locked`,`account_blocked`,`password_reset_required`,`theme_preference`,`profile_image`,`theme_custom_json`,`mfasecret`,`mfaenabled`,`mfabackupcodes`,`created_at`,`updated_at`)
VALUES
(1,'Test','User','test@scanfit.com','+1234567890','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',0,0,0,0,'default',NULL,NULL,NULL,0,NULL,'2025-12-17 19:32:08','2025-12-17 19:32:08'),
(2,'jon','brown','jonbrown@gmail.com','18761234567','$2y$10$CZjQCDyexkvbw6ryds9u/e.s1z2Uf8sOHx2BbFCdQBEJbHQrXanwu',0,0,0,0,'default',NULL,NULL,NULL,0,NULL,'2025-12-17 20:23:01','2025-12-17 20:23:01');
/*!40000 ALTER TABLE `customer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pending_google_signup`
--

DROP TABLE IF EXISTS `pending_google_signup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pending_google_signup` (
  `pending_google_signup_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `google_sub` varchar(191) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_expires_at` datetime DEFAULT NULL,
  `terms_version` varchar(32) DEFAULT NULL,
  `terms_accepted_at` datetime DEFAULT NULL,
  `terms_accepted_ip` varchar(45) DEFAULT NULL,
  `terms_accepted_user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pending_google_signup_id`),
  UNIQUE KEY `uniq_pending_google_signup_email` (`email`),
  UNIQUE KEY `uniq_pending_google_signup_google_sub` (`google_sub`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pending_customer_signup`
--

DROP TABLE IF EXISTS `pending_customer_signup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pending_customer_signup` (
  `pending_customer_signup_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_expires_at` datetime DEFAULT NULL,
  `terms_version` varchar(32) DEFAULT NULL,
  `terms_accepted_at` datetime DEFAULT NULL,
  `terms_accepted_ip` varchar(45) DEFAULT NULL,
  `terms_accepted_user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pending_customer_signup_id`),
  UNIQUE KEY `uniq_pending_customer_signup_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gender`
--

-- Drop gender table if it exists
DROP TABLE IF EXISTS `gender`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for gender definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores gender labels used for product targeting
CREATE TABLE `gender` (
  `gender_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`gender_id`),
  -- Ensure gender names are unique (Male, Female, Unisex)
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gender`
--

-- Lock gender table while inserting seed gender values
LOCK TABLES `gender` WRITE;
/*!40000 ALTER TABLE `gender` DISABLE KEYS */;
-- Seed supported gender categories
INSERT INTO `gender` VALUES (1,'Male','2025-12-17 19:32:08'),(2,'Female','2025-12-17 19:32:08'),(3,'Unisex','2025-12-17 19:32:08');
/*!40000 ALTER TABLE `gender` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupon`
--

DROP TABLE IF EXISTS `coupon`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupon` (
  `coupon_id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('PERCENT','FIXED') NOT NULL DEFAULT 'PERCENT',
  `discount_value` decimal(10,2) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`coupon_id`),
  UNIQUE KEY `uniq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupon`
--

LOCK TABLES `coupon` WRITE;
/*!40000 ALTER TABLE `coupon` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupon` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order`
--

-- Drop order table if it exists
DROP TABLE IF EXISTS `order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for order definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Represents customer orders and their lifecycle status
CREATE TABLE `order` (
  `order_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `shipping_address_id` int DEFAULT NULL,
  `order_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('PENDING','PROCESSING','SHIPPED','DELIVERED','CANCELLED') DEFAULT 'PENDING',
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_carrier` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(191) DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  KEY `customer_id` (`customer_id`),
  KEY `shipping_address_id` (`shipping_address_id`),
  -- Link each order to the customer who placed it
  CONSTRAINT `order_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  CONSTRAINT `order_ibfk_2` FOREIGN KEY (`shipping_address_id`) REFERENCES `address` (`address_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order`
--

-- Lock order table during potential data insert
LOCK TABLES `order` WRITE;
/*!40000 ALTER TABLE `order` DISABLE KEYS */;
-- No order rows were exported in this dump
/*!40000 ALTER TABLE `order` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orderitem`
--

-- Drop orderitem table if it exists
DROP TABLE IF EXISTS `orderitem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for orderitem definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Line items for each order, with pricing snapshot
CREATE TABLE `orderitem` (
  `order_item_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `variant_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `variant_id` (`variant_id`),
  -- Link order items to their parent order; cascade on order deletion
  CONSTRAINT `orderitem_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE,
  -- Link order items to specific product variants
  CONSTRAINT `orderitem_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `productvariant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orderitem`
--

-- Lock orderitem table during potential data insert
LOCK TABLES `orderitem` WRITE;
/*!40000 ALTER TABLE `orderitem` DISABLE KEYS */;
-- No order item rows were exported in this dump
/*!40000 ALTER TABLE `orderitem` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_coupon`
--

DROP TABLE IF EXISTS `order_coupon`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_coupon` (
  `order_coupon_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `coupon_id` int DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_coupon_id`),
  KEY `order_id` (`order_id`),
  KEY `coupon_id` (`coupon_id`),
  CONSTRAINT `order_coupon_coupon_fk` FOREIGN KEY (`coupon_id`) REFERENCES `coupon` (`coupon_id`) ON DELETE SET NULL,
  CONSTRAINT `order_coupon_order_fk` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_coupon`
--

LOCK TABLES `order_coupon` WRITE;
/*!40000 ALTER TABLE `order_coupon` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_coupon` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_request`
--

DROP TABLE IF EXISTS `return_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_request` (
  `return_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `order_item_id` int DEFAULT NULL,
  `reason` varchar(1000) NOT NULL,
  `status` enum('REQUESTED','APPROVED','REJECTED','RECEIVED','REFUNDED') NOT NULL DEFAULT 'REQUESTED',
  `admin_notes` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`return_id`),
  KEY `order_id` (`order_id`),
  KEY `customer_id` (`customer_id`),
  KEY `order_item_id` (`order_item_id`),
  CONSTRAINT `return_request_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `return_request_order_fk` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `return_request_order_item_fk` FOREIGN KEY (`order_item_id`) REFERENCES `orderitem` (`order_item_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_request`
--

LOCK TABLES `return_request` WRITE;
/*!40000 ALTER TABLE `return_request` DISABLE KEYS */;
/*!40000 ALTER TABLE `return_request` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment`
--

-- Drop payment table if it exists
DROP TABLE IF EXISTS `payment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for payment definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores payment records and transaction status per order
CREATE TABLE `payment` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `method_name` varchar(50) DEFAULT NULL,
  `payment_status` enum('PENDING','COMPLETED','FAILED','REFUNDED') DEFAULT 'PENDING',
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `provider` varchar(50) DEFAULT NULL,
  `provider_payment_id` varchar(191) DEFAULT NULL,
  `stripe_checkout_session_id` varchar(191) DEFAULT NULL,
  `metadata_json` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `order_id` (`order_id`),
  UNIQUE KEY `uniq_payment_stripe_checkout_session` (`stripe_checkout_session_id`),
  -- Link payment to the associated order; cascade on order delete
  CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment`
--

-- Lock payment table during potential data insert
LOCK TABLES `payment` WRITE;
/*!40000 ALTER TABLE `payment` DISABLE KEYS */;
-- No payment rows were exported in this dump
/*!40000 ALTER TABLE `payment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paymentaudit`
--

-- Drop paymentaudit table if it exists
DROP TABLE IF EXISTS `paymentaudit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for paymentaudit definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Audit log tracking changes to payment status over time
CREATE TABLE `paymentaudit` (
  `audit_id` int NOT NULL AUTO_INCREMENT,
  `payment_id` int DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `audit_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  KEY `payment_id` (`payment_id`),
  -- Link audit entry back to a payment record; cascade on payment deletion
  CONSTRAINT `paymentaudit_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payment` (`payment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paymentaudit`
--

-- Lock paymentaudit table during potential data insert
LOCK TABLES `paymentaudit` WRITE;
/*!40000 ALTER TABLE `paymentaudit` DISABLE KEYS */;
-- No payment audit rows were exported in this dump
/*!40000 ALTER TABLE `paymentaudit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product`
--

-- Drop product table if it exists
DROP TABLE IF EXISTS `product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for product definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Core product catalog with base price and status
CREATE TABLE `product` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `description` text,
  `base_price` decimal(10,2) NOT NULL,
  `status` enum('ACTIVE','INACTIVE','OUT_OF_STOCK') DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  -- Enforce uniqueness of product-level SKU
  UNIQUE KEY `sku` (`sku`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product`
--

-- Lock product table while inserting product data
LOCK TABLES `product` WRITE;
/*!40000 ALTER TABLE `product` DISABLE KEYS */;
-- Seed catalog with base product records
INSERT INTO `product` VALUES (2,'Men Classic Tee','MEN-TEE-001','Soft cotton T-shirt for men',24.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08'),(3,'Men Slim Fit Jeans','MEN-JEAN-001','Slim fit denim jeans',49.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08'),(5,'Men Casual Hoodie','MEN-HOOD-001','Fleece-lined casual hoodie',39.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08'),(6,'Women Classic Tee','WOM-TEE-001','Soft cotton T-shirt for women',24.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08'),(7,'Women High Waist Jeans','WOM-JEAN-001','High waist skinny jeans',49.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08'),(9,'Women Cardigan','WOM-CARD-001','Lightweight knit cardigan',34.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08'),(10,'Leather Belt','ACC-BELT-001','Genuine leather belt',19.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08'),(11,'Classic Wrist Watch','ACC-WATCH-001','Minimalist wrist watch',79.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08'),(12,'Travel Backpack','ACC-BAG-001','Durable travel backpack',54.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08'),(13,'Beanie Hat','ACC-HAT-001','Warm knit beanie',14.99,'ACTIVE','2025-12-17 19:32:08','2025-12-17 19:32:08');
/*!40000 ALTER TABLE `product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productcategory`
--

-- Drop productcategory junction table if it exists
DROP TABLE IF EXISTS `productcategory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for productcategory definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Many-to-many relationship between products and categories
CREATE TABLE `productcategory` (
  `product_id` int NOT NULL,
  `category_id` int NOT NULL,
  PRIMARY KEY (`product_id`,`category_id`),
  KEY `category_id` (`category_id`),
  -- Link to product; cascade when product is deleted
  CONSTRAINT `productcategory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE,
  -- Link to category; cascade when category is deleted
  CONSTRAINT `productcategory_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productcategory`
--

-- Lock productcategory table while inserting mapping data
LOCK TABLES `productcategory` WRITE;
/*!40000 ALTER TABLE `productcategory` DISABLE KEYS */;
-- Map each product to one or more categories
INSERT INTO `productcategory` VALUES (2,1),(6,1),(11,2),(12,3),(10,4),(3,6),(5,6),(7,6),(9,6),(13,6);
/*!40000 ALTER TABLE `productcategory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productgender`
--

-- Drop productgender junction table if it exists
DROP TABLE IF EXISTS `productgender`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for productgender definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Many-to-many relationship between products and gender segments
CREATE TABLE `productgender` (
  `product_id` int NOT NULL,
  `gender_id` int NOT NULL,
  PRIMARY KEY (`product_id`,`gender_id`),
  KEY `gender_id` (`gender_id`),
  -- Link mapping entry to product; cascade on product deletion
  CONSTRAINT `productgender_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE,
  -- Link mapping entry to gender; cascade on gender deletion
  CONSTRAINT `productgender_ibfk_2` FOREIGN KEY (`gender_id`) REFERENCES `gender` (`gender_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;




--
-- Dumping data for table `productgender`
--

-- Lock productgender table while inserting mapping data
LOCK TABLES `productgender` WRITE;
/*!40000 ALTER TABLE `productgender` DISABLE KEYS */;
-- Map products to their intended gender or unisex usage
INSERT INTO `productgender` VALUES (2,1),(3,1),(5,1),(6,2),(7,2),(9,2),(10,3),(11,3),(12,3),(13,3);
/*!40000 ALTER TABLE `productgender` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productvariant`
--

-- Drop productvariant table if it exists
DROP TABLE IF EXISTS `productvariant`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for productvariant definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Holds size/color variants and stock levels for each product
CREATE TABLE `productvariant` (
  `variant_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `size_id` int DEFAULT NULL,
  `colour_id` int DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `stock_quantity` int DEFAULT '0',
  `price_adjustment` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`variant_id`),
  -- Enforce uniqueness for variant-level SKU
  UNIQUE KEY `sku` (`sku`),
  KEY `product_id` (`product_id`),
  KEY `size_id` (`size_id`),
  KEY `colour_id` (`colour_id`),
  -- Link variant to its base product; cascade on product deletion
  CONSTRAINT `productvariant_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE,
  -- Link variant size to size reference table
  CONSTRAINT `productvariant_ibfk_2` FOREIGN KEY (`size_id`) REFERENCES `size` (`size_id`),
  -- Link variant color to colour reference table
  CONSTRAINT `productvariant_ibfk_3` FOREIGN KEY (`colour_id`) REFERENCES `colour` (`colour_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productvariant`
--

-- Lock productvariant table while inserting variant data
LOCK TABLES `productvariant` WRITE;
/*!40000 ALTER TABLE `productvariant` DISABLE KEYS */;
-- Seed product variants with sizes, colors, and stock
INSERT INTO `productvariant` VALUES (4,2,3,1,'MEN-TEE-001-M-BLK',20,0.00,'2025-12-17 19:32:08'),(5,3,4,1,'MEN-JEAN-001-L-BLK',20,0.00,'2025-12-17 19:32:08'),(7,5,4,1,'MEN-HOOD-001-L-BLK',20,0.00,'2025-12-17 19:32:08'),(8,6,3,2,'WOM-TEE-001-M-WHT',20,0.00,'2025-12-17 19:32:08'),(9,7,3,2,'WOM-JEAN-001-M-WHT',20,0.00,'2025-12-17 19:32:08'),(11,9,3,2,'WOM-CARD-001-M-WHT',20,0.00,'2025-12-17 19:32:08'),(12,10,3,1,'ACC-BELT-001-M-BLK',20,0.00,'2025-12-17 19:32:08'),(13,11,NULL,1,'ACC-WATCH-001-STD',20,0.00,'2025-12-17 19:32:08'),(14,12,NULL,1,'ACC-BAG-001-STD',20,0.00,'2025-12-17 19:32:08'),(15,13,NULL,2,'ACC-HAT-001-STD',20,0.00,'2025-12-17 19:32:08');
/*!40000 ALTER TABLE `productvariant` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review`
--

-- Drop review table if it exists
DROP TABLE IF EXISTS `review`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for review definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores customer ratings and comments for products
CREATE TABLE `review` (
  `review_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `rating` int DEFAULT NULL,
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  KEY `product_id` (`product_id`),
  KEY `customer_id` (`customer_id`),
  UNIQUE KEY `uniq_review_customer_product` (`customer_id`,`product_id`),
  -- Link review to the reviewed product; cascade on product deletion
  CONSTRAINT `review_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`product_id`) ON DELETE CASCADE,
  -- Link review to the customer who wrote it; cascade on customer deletion
  CONSTRAINT `review_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  -- Ensure rating values are between 1 and 5
  CONSTRAINT `review_chk_1` CHECK ((`rating` between 1 and 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review`
--

-- Lock review table during potential data insert
LOCK TABLES `review` WRITE;
/*!40000 ALTER TABLE `review` DISABLE KEYS */;
-- No review rows were exported in this dump
/*!40000 ALTER TABLE `review` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `size`
--

-- Drop size table if it exists
DROP TABLE IF EXISTS `size`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for size definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores clothing sizes with sort order for display
CREATE TABLE `size` (
  `size_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `abbreviation` varchar(10) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`size_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `size`
--

-- Lock size table while inserting size values
LOCK TABLES `size` WRITE;
/*!40000 ALTER TABLE `size` DISABLE KEYS */;
-- Seed standard size scale (XS through XXL)
INSERT INTO `size` VALUES (1,'Extra Small','XS',1,'2025-12-17 19:32:08'),(2,'Small','S',2,'2025-12-17 19:32:08'),(3,'Medium','M',3,'2025-12-17 19:32:08'),(4,'Large','L',4,'2025-12-17 19:32:08'),(5,'Extra Large','XL',5,'2025-12-17 19:32:08'),(6,'Double Extra Large','XXL',6,'2025-12-17 19:32:08');
/*!40000 ALTER TABLE `size` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stockmovement`
--

-- Drop stockmovement table if it exists
DROP TABLE IF EXISTS `stockmovement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for stockmovement definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Tracks inventory changes (in, out, adjustments) per variant
CREATE TABLE `stockmovement` (
  `movement_id` int NOT NULL AUTO_INCREMENT,
  `variant_id` int NOT NULL,
  `movement_type` enum('IN','OUT','ADJUSTMENT') NOT NULL,
  `quantity` int NOT NULL,
  `reference_id` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movement_id`),
  KEY `variant_id` (`variant_id`),
  -- Link stock movement record to its variant; cascade on variant deletion
  CONSTRAINT `stockmovement_ibfk_1` FOREIGN KEY (`variant_id`) REFERENCES `productvariant` (`variant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stockmovement`
--

-- Lock stockmovement table during potential data insert
LOCK TABLES `stockmovement` WRITE;
/*!40000 ALTER TABLE `stockmovement` DISABLE KEYS */;
-- No stock movement rows were exported in this dump
/*!40000 ALTER TABLE `stockmovement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier`
--

-- Drop supplier table if it exists
DROP TABLE IF EXISTS `supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
-- Use utf8mb4 for supplier definition
/*!50503 SET character_set_client = utf8mb4 */;
-- Stores product supplier information and contact details
CREATE TABLE `supplier` (
  `supplier_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier`
--

-- Lock supplier table during potential data insert
LOCK TABLES `supplier` WRITE;
/*!40000 ALTER TABLE `supplier` DISABLE KEYS */;
-- No supplier rows were exported in this dump
/*!40000 ALTER TABLE `supplier` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

-- Restore previous SQL mode
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
-- Re-enable original foreign key checks
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
-- Re-enable original unique checks
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
-- Restore original character set settings
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
 /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
 /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
-- Restore original SQL notes setting
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-17 15:58:40
