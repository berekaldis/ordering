-- MySQL dump 10.13  Distrib 8.4.7, for Win64 (x86_64)
--
-- Host: localhost    Database: kaldis_ordering
-- ------------------------------------------------------
-- Server version	8.4.7

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chat_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `step_number` int DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TELEGRAM',
  `entity_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_chat_id` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES ('3cb04c3a8d53c8ace24b29a6b5137428',NULL,'1','ADMIN','1','\"LOGIN\"','::1',NULL,NULL,NULL,NULL,'en','2026-09-24 19:32:05','TELEGRAM',''),('b6d48c50dc032452810bb1fb1fa2f514',NULL,'1','ADMIN','1','\"LOGIN\"','::1',NULL,NULL,NULL,NULL,'en','2026-09-24 19:30:17','TELEGRAM',''),('ce0ede659aa0a40683541bc3003ab4f2',NULL,'ORDER_CREATED','APP','USER','{\"order_id\":\"1\",\"order_number\":\"KLD-00001\",\"total\":700}','::1',NULL,NULL,NULL,NULL,'en','2026-09-24 19:28:50','TELEGRAM',''),('e62ec5f8a2ab1553408015f234763d5b',NULL,'1','ADMIN','1','\"LOGIN\"','::1',NULL,NULL,NULL,NULL,'en','2026-09-24 19:34:27','TELEGRAM','');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','superadmin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `status` tinyint(1) DEFAULT '1',
  `remember_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token_expiry` datetime DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`),
  UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','$2y$10$hHuItOp3yk.w57xfomZkD.JBjwddyqRH7vZUjf.1H.Dpv5OhPygMW','admin@kaldiscoffee.com','Kaldis ECA Administrator','superadmin',1,NULL,NULL,'2026-09-24 19:34:27','2026-09-24 15:44:09','2026-09-24 19:34:27');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `branch_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_am` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `contact_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_branch_code` (`branch_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'ECA','Kaldis Coffee - ECA Branch','ካልዲስ ቡና - ኢሲኤ ቅርንጫፍ','UNECA Compound','UNECA Compound, Menelik II Ave, Addis Ababa','0930332185',NULL,NULL,'Exclusive office ordering & delivery service for UNECA compound staff and offices.','active',1,'2026-09-24 15:44:09','2026-09-24 15:44:09');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `collection_days`
--

DROP TABLE IF EXISTS `collection_days`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `collection_days` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `max_orders` int DEFAULT '200',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date` (`date`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `collection_days`
--

LOCK TABLES `collection_days` WRITE;
/*!40000 ALTER TABLE `collection_days` DISABLE KEYS */;
INSERT INTO `collection_days` VALUES (1,'2026-09-24',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(2,'2026-09-25',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(3,'2026-09-26',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(4,'2026-09-27',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(5,'2026-09-28',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(6,'2026-09-29',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(7,'2026-09-30',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(8,'2026-10-01',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(9,'2026-10-02',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(10,'2026-10-03',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(11,'2026-10-04',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(12,'2026-10-05',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(13,'2026-10-06',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(14,'2026-10-07',1,300,NULL,'2026-09-24 15:44:09','2026-09-24 15:44:09');
/*!40000 ALTER TABLE `collection_days` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_states`
--

DROP TABLE IF EXISTS `customer_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_states` (
  `chat_id` bigint NOT NULL,
  `state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temp_data` json DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_states`
--

LOCK TABLES `customer_states` WRITE;
/*!40000 ALTER TABLE `customer_states` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_states` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_sales_summary`
--

DROP TABLE IF EXISTS `daily_sales_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_sales_summary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `total_orders` int DEFAULT '0',
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `total_items` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_sales_summary`
--

LOCK TABLES `daily_sales_summary` WRITE;
/*!40000 ALTER TABLE `daily_sales_summary` DISABLE KEYS */;
/*!40000 ALTER TABLE `daily_sales_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dairy_products`
--

DROP TABLE IF EXISTS `dairy_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dairy_products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name_am` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'coffee',
  `variety` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cup',
  `unit_price` decimal(10,2) NOT NULL,
  `walkin_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_am` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nutrition_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `storage_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `shelf_life_days` int DEFAULT '1',
  `stock_quantity` int DEFAULT '100',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_product_code` (`product_code`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dairy_products`
--

LOCK TABLES `dairy_products` WRITE;
/*!40000 ALTER TABLE `dairy_products` DISABLE KEYS */;
INSERT INTO `dairy_products` VALUES (1,'KLD-COF-001','Kaldis Macchiato','ካልዲስ ማኪያቶ',NULL,'coffee',NULL,'cup',95.00,NULL,NULL,'Our signature rich Ethiopian espresso with velvety steamed milk foam.',NULL,NULL,NULL,1,100,1,1,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(2,'KLD-COF-002','Double Macchiato','ድርብ ማኪያቶ',NULL,'coffee',NULL,'cup',130.00,NULL,NULL,'Double shot espresso marked with warm milk froth for an extra energy boost.',NULL,NULL,NULL,1,100,1,2,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(3,'KLD-COF-003','Single Espresso','ነጠላ ኤስፕሬሶ',NULL,'coffee',NULL,'cup',85.00,NULL,NULL,'Pure, aromatic Ethiopian highland espresso with thick golden crema.',NULL,NULL,NULL,1,100,1,3,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(4,'KLD-COF-004','Double Espresso','ድርብ ኤስፕሬሶ',NULL,'coffee',NULL,'cup',120.00,NULL,NULL,'Intense double shot of single-origin Kaldis espresso.',NULL,NULL,NULL,1,100,1,4,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(5,'KLD-COF-005','Cafe Latte','ካፌ ላቴ',NULL,'coffee',NULL,'cup',140.00,NULL,NULL,'Silky steamed milk poured over a shot of fresh espresso.',NULL,NULL,NULL,1,100,1,5,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(6,'KLD-COF-006','Cappuccino','ካፑቺኖ',NULL,'coffee',NULL,'cup',140.00,NULL,NULL,'Equal parts espresso, steamed milk, and airy microfoam with a dusting of cocoa.',NULL,NULL,NULL,1,100,1,6,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(7,'KLD-COF-007','Americano','አሜሪካኖ',NULL,'coffee',NULL,'cup',95.00,NULL,NULL,'Fresh espresso diluted with hot water for a smooth, deep coffee cup.',NULL,NULL,NULL,1,100,1,7,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(8,'KLD-COF-008','Caffe Mocha','ካፌ ሞካ',NULL,'coffee',NULL,'cup',160.00,NULL,NULL,'Rich dark chocolate syrup blended with fresh espresso and hot steamed milk.',NULL,NULL,NULL,1,100,1,8,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(9,'KLD-COF-009','Special Ethiopian Spiced Tea','የቅመም ሻይ',NULL,'coffee',NULL,'cup',70.00,NULL,NULL,'Brewed black tea infused with cinnamon, cardamom, and cloves.',NULL,NULL,NULL,1,100,1,9,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(10,'KLD-COF-010','Hot Chocolate','ትኩስ ቸኮሌት',NULL,'coffee',NULL,'cup',150.00,NULL,NULL,'Creamy, sweet Belgian-style hot cocoa topped with milk foam.',NULL,NULL,NULL,1,100,1,10,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(11,'KLD-CLD-001','Iced Caramel Macchiato','አይስድ ካራሜል ማኪያቶ',NULL,'cold_drinks',NULL,'cup',180.00,NULL,NULL,'Chilled milk, vanilla syrup, fresh espresso, and sweet caramel drizzle over ice.',NULL,NULL,NULL,1,100,1,11,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(12,'KLD-CLD-002','Iced Caffe Latte','አይስድ ካፌ ላቴ',NULL,'cold_drinks',NULL,'cup',160.00,NULL,NULL,'Refreshing chilled espresso combined with cold milk over ice cubes.',NULL,NULL,NULL,1,100,1,12,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(13,'KLD-CLD-003','Kaldis Mocha Frappuccino','ካልዲስ ሞካ ፍራፑቺኖ',NULL,'cold_drinks',NULL,'cup',220.00,NULL,NULL,'Blended iced coffee with chocolate fudge, milk, and whipped cream topping.',NULL,NULL,NULL,1,100,1,13,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(14,'KLD-CLD-004','Fresh Mango Smoothie','ትኩስ ማንጎ ስሙዚ',NULL,'cold_drinks',NULL,'cup',190.00,NULL,NULL,'100% real ripe mango blended smooth and cold.',NULL,NULL,NULL,1,100,1,14,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(15,'KLD-CLD-005','Fresh Strawberry Smoothie','ትኩስ ስትሮውበሪ ስሙዚ',NULL,'cold_drinks',NULL,'cup',190.00,NULL,NULL,'Sweet natural strawberries blended with crushed ice and yogurt.',NULL,NULL,NULL,1,100,1,15,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(16,'KLD-CLD-006','Fresh Mixed Juice','ትኩስ ቅልቅል ጁስ',NULL,'cold_drinks',NULL,'cup',170.00,NULL,NULL,'Layered avocado, mango, and papaya with a squeeze of fresh lime.',NULL,NULL,NULL,1,100,1,16,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(17,'KLD-CLD-007','Mineral Water 500ml','የታሸገ ውሃ 500ሚሊ',NULL,'cold_drinks',NULL,'bottle',40.00,NULL,NULL,'Cold refreshing spring bottled water.',NULL,NULL,NULL,1,100,1,17,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(18,'KLD-PAS-001','Butter Croissant','ቅቤ ክሩዋሳን',NULL,'pastry',NULL,'piece',130.00,NULL,NULL,'Golden flaky, freshly baked buttery French-style croissant.',NULL,NULL,NULL,1,100,1,18,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(19,'KLD-PAS-002','Chocolate Croissant (Pain au Chocolat)','ቸኮሌት ክሩዋሳን',NULL,'pastry',NULL,'piece',160.00,NULL,NULL,'Warm buttery pastry filled with decadent dark chocolate baton.',NULL,NULL,NULL,1,100,1,19,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(20,'KLD-PAS-003','Cinnamon Roll','ሲናሞን ሮል',NULL,'pastry',NULL,'piece',140.00,NULL,NULL,'Swirled dough baked with aromatic cinnamon brown sugar and glazed on top.',NULL,NULL,NULL,1,100,1,20,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(21,'KLD-PAS-004','Blueberry Muffin','ብሉቤሪ ማፊን',NULL,'pastry',NULL,'piece',120.00,NULL,NULL,'Moist, fluffy muffin bursting with sweet blueberries.',NULL,NULL,NULL,1,100,1,21,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(22,'KLD-PAS-005','Chocolate Chip Muffin','ቸኮሌት ቺፕ ማፊን',NULL,'pastry',NULL,'piece',120.00,NULL,NULL,'Rich chocolate muffin loaded with semi-sweet chocolate morsels.',NULL,NULL,NULL,1,100,1,22,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(23,'KLD-PAS-006','Glazed Ring Donut','ዶናት',NULL,'pastry',NULL,'piece',90.00,NULL,NULL,'Light and fluffy ring donut with classic sweet vanilla glaze.',NULL,NULL,NULL,1,100,1,23,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(24,'KLD-FOD-001','Kaldis Club Sandwich with Fries','ካልዲስ ክለብ ሳንድዊች',NULL,'food',NULL,'plate',380.00,NULL,NULL,'Triple-decker sandwich with chicken breast, boiled egg, cheese, tomato, and fries.',NULL,NULL,NULL,1,100,1,24,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(25,'KLD-FOD-002','Grilled Chicken Breast Sandwich','የዶሮ ሳንድዊች',NULL,'food',NULL,'piece',340.00,NULL,NULL,'Tender marinated grilled chicken breast, crisp lettuce, mayo on toasted baguette.',NULL,NULL,NULL,1,100,1,25,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(26,'KLD-FOD-003','Tuna & Sweetcorn Wrap','የቱና ራፕ',NULL,'food',NULL,'piece',310.00,NULL,NULL,'Seasoned flaked tuna, sweet corn, greens wrapped in a warm tortilla.',NULL,NULL,NULL,1,100,1,26,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(27,'KLD-FOD-004','Crispy Golden French Fries','የድንች ጥብስ',NULL,'food',NULL,'portion',150.00,NULL,NULL,'Freshly fried hot crispy potato chips with ketchup and mayo dipping sauces.',NULL,NULL,NULL,1,100,1,27,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(28,'KLD-FOD-005','Cheese & Tomato Toast','የአይብ እና ቲማቲም ቶስት',NULL,'food',NULL,'piece',220.00,NULL,NULL,'Toasted artisan bread with melted gouda cheese and sliced ripe tomato.',NULL,NULL,NULL,1,100,1,28,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(29,'KLD-CAK-001','Black Forest Cake Slice','ብላክ ፎረስት ቁራጭ',NULL,'cakes',NULL,'slice',220.00,NULL,NULL,'Layers of chocolate sponge, whipped cream, and tart cherries.',NULL,NULL,NULL,1,100,1,29,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(30,'KLD-CAK-002','Chocolate Torta Slice','ቸኮሌት ቶርታ ቁራጭ',NULL,'cakes',NULL,'slice',240.00,NULL,NULL,'Decadent, rich chocolate fudge cake slice perfect with coffee.',NULL,NULL,NULL,1,100,1,30,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(31,'KLD-CAK-003','New York Strawberry Cheesecake','ስትሮውበሪ ቺዝኬክ ቁራጭ',NULL,'cakes',NULL,'slice',260.00,NULL,NULL,'Velvety baked cream cheese on graham crust with strawberry compote.',NULL,NULL,NULL,1,100,1,31,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(32,'KLD-CAK-004','Carrot Cake with Cream Cheese Frosting','ካሮት ኬክ ቁራጭ',NULL,'cakes',NULL,'slice',210.00,NULL,NULL,'Spiced moist carrot cake filled with walnuts and cream cheese.',NULL,NULL,NULL,1,100,1,32,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(33,'KLD-CAK-005','Full Black Forest Torta (Celebration)','ሙሉ ብላክ ፎረስት ቶርታ',NULL,'cakes',NULL,'torta',1750.00,NULL,NULL,'Full round cake for office birthday or celebration (Serves 10-12).',NULL,NULL,NULL,1,100,1,33,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(34,'KLD-CAK-006','Full Chocolate Torta (Celebration)','ሙሉ ቸኮሌት ቶርታ',NULL,'cakes',NULL,'torta',2000.00,NULL,NULL,'Full premium chocolate torta for team events and birthdays (Serves 12-15).',NULL,NULL,NULL,1,100,1,34,'2026-09-24 15:44:09','2026-09-24 15:44:09');
/*!40000 ALTER TABLE `dairy_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_locations`
--

DROP TABLE IF EXISTS `delivery_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_am` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 0xF09F8FA2,
  `area` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'UNECA Complex',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `contact_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_location_code` (`location_code`),
  KEY `idx_status` (`status`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_locations`
--

LOCK TABLES `delivery_locations` WRITE;
/*!40000 ALTER TABLE `delivery_locations` DISABLE KEYS */;
INSERT INTO `delivery_locations` VALUES (1,'ECA-AFRICA','Africa Hall','አፍሪካ ሆል','🏛️','UNECA Complex','Africa Hall Building, UNECA Compound',NULL,NULL,NULL,NULL,'active',1,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(2,'ECA-MAIN','UNECA Main Secretariat','ዋናው ጽሕፈት ቤት ሕንፃ','🏢','UNECA Complex','Secretariat Building, UNECA Compound',NULL,NULL,NULL,NULL,'active',2,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(3,'ECA-CONGO','Congo Building','ኮንጎ ሕንፃ','🏢','UNECA Complex','Congo Building, UNECA Compound',NULL,NULL,NULL,NULL,'active',3,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(4,'ECA-NIGER','Niger Building','ኒጀር ሕንፃ','🏢','UNECA Complex','Niger Building, UNECA Compound',NULL,NULL,NULL,NULL,'active',4,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(5,'ECA-ZAMBEZI','Zambezi Building','ዛምቤዚ ሕንፃ','🏢','UNECA Complex','Zambezi Building, UNECA Compound',NULL,NULL,NULL,NULL,'active',5,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(6,'ECA-LIMPOPO','Limpopo Building','ሊምፖፖ ሕንፃ','🏢','UNECA Complex','Limpopo Building, UNECA Compound',NULL,NULL,NULL,NULL,'active',6,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(7,'ECA-NILE','Nile Building','ናይል ሕንፃ','🏢','UNECA Complex','Nile Building, UNECA Compound',NULL,NULL,NULL,NULL,'active',7,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(8,'ECA-MENELIK','Menelik II Conference Center','ዳግማዊ ምኒልክ የስብሰባ ማዕከል','🏛️','UNECA Complex','Conference Centre, UNECA Compound',NULL,NULL,NULL,NULL,'active',8,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(9,'ECA-AGENCIES','UN Agencies Wing (UNDP/UNICEF)','የተመድ ድርጅቶች ክንፍ','🏢','UNECA Complex','UN Agencies Wing, UNECA Compound',NULL,NULL,NULL,NULL,'active',9,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(10,'ECA-CLINIC','ECA Clinic & Services','ክሊኒክ እና አገልግሎቶች','🏥','UNECA Complex','Services Wing, UNECA Compound',NULL,NULL,NULL,NULL,'active',10,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(11,'ECA-OTHER','Other ECA / Nearby Office','ሌላ የኢሲኤ ወይም አቅራቢያ ቢሮ','📍','UNECA & Environs','Around UNECA Compound, Addis Ababa',NULL,NULL,NULL,NULL,'active',11,'2026-09-24 15:44:09','2026-09-24 15:44:09');
/*!40000 ALTER TABLE `delivery_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feedback` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chat_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int DEFAULT '1',
  `delivery_rating` tinyint DEFAULT NULL,
  `product_rating` tinyint DEFAULT NULL,
  `service_rating` tinyint DEFAULT NULL,
  `written_feedback` text COLLATE utf8mb4_unicode_ci,
  `complaint_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complaint_description` text COLLATE utf8mb4_unicode_ci,
  `complaint_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `complaint_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feedback`
--

LOCK TABLES `feedback` WRITE;
/*!40000 ALTER TABLE `feedback` DISABLE KEYS */;
/*!40000 ALTER TABLE `feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_am` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `instructions_am` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_methods`
--

LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
INSERT INTO `payment_methods` VALUES (1,'Telebirr','ቴሌብር','Kaldis Coffee ECA','0930332185','Pay via Telebirr to 0930332185 and upload screenshot or reference number.',NULL,1,1,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(2,'CBE Birr','ሲቢኢ ብር','Kaldis Coffee ECA','1000123456789','Pay via CBE Birr to 1000123456789 and enter transaction reference.',NULL,1,2,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(3,'Bank of Abyssinia','አቢሲኒያ ባንክ','Kaldis Coffee','88776655','Transfer to BoA account 88776655.',NULL,1,3,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(4,'Pay at Office Desk','በቢሮ ሲደርስ በጥሬ ገንዘብ','Cash on Delivery','Cash','Pay cash to our delivery runner when your order reaches your office desk.',NULL,1,4,'2026-09-24 15:44:09','2026-09-24 15:44:09');
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pre_order_items`
--

DROP TABLE IF EXISTS `pre_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pre_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pre_order_id` int NOT NULL,
  `pre_order_product_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pre_order` (`pre_order_id`),
  KEY `idx_product` (`pre_order_product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pre_order_items`
--

LOCK TABLES `pre_order_items` WRITE;
/*!40000 ALTER TABLE `pre_order_items` DISABLE KEYS */;
INSERT INTO `pre_order_items` VALUES (1,1,1,2,95.00,190.00,NULL,'2026-09-24 19:28:50','2026-09-24 19:28:50'),(2,1,18,1,130.00,130.00,NULL,'2026-09-24 19:28:50','2026-09-24 19:28:50'),(3,1,24,1,380.00,380.00,NULL,'2026-09-24 19:28:50','2026-09-24 19:28:50');
/*!40000 ALTER TABLE `pre_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pre_orders`
--

DROP TABLE IF EXISTS `pre_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pre_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `chat_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_location_id` int DEFAULT NULL,
  `building_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apartment_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `floor_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `delivery_date` date DEFAULT NULL,
  `delivery_time_slot` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ASAP',
  `status` enum('Pending','Paid','Confirmed','Processing','Out for Delivery','Delivered','Cancelled','Rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_slip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `refrigeration_acknowledged` tinyint(1) DEFAULT '1',
  `language` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_order_number` (`order_number`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_status` (`status`),
  KEY `idx_delivery_location` (`delivery_location_id`),
  KEY `idx_delivery_date` (`delivery_date`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pre_orders`
--

LOCK TABLES `pre_orders` WRITE;
/*!40000 ALTER TABLE `pre_orders` DISABLE KEYS */;
INSERT INTO `pre_orders` VALUES (1,'KLD-00001','Dr. Abebe Bekele','+251911223344','123456789',1,'Africa Hall','Office 314','3rd Floor','UN Economic Commission / ICT Division','Africa Hall, Floor 3rd Floor, Office/Room Office 314, Dept: UN Economic Commission / ICT Division','2026-09-24','10:30 AM (Morning Coffee)','Pending',700.00,'Telebirr','TEST-1790278130',NULL,'Please bring extra brown sugar, deliver directly to desk',1,'en',NULL,NULL,NULL,'2026-09-24 19:28:50','2026-09-24 19:28:50');
/*!40000 ALTER TABLE `pre_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_sales_summary`
--

DROP TABLE IF EXISTS `product_sales_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_sales_summary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int DEFAULT '0',
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date_product` (`date`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_sales_summary`
--

LOCK TABLES `product_sales_summary` WRITE;
/*!40000 ALTER TABLE `product_sales_summary` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_sales_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'bot_token','8691959077:AAGzEznvqAyjTX27XJPX9IPYj9Ix2TIjFMw','Telegram Bot Token for Kaldis Coffee ECA Pre-Order Bot','2026-09-24 15:44:09','2026-09-24 15:44:09'),(2,'admin_chat_id','814523272','Telegram Admin Chat ID for notifications','2026-09-24 15:44:09','2026-09-24 15:44:09'),(3,'mini_app_url','http://localhost/ordering/miniapp/app.html','Kaldis Mini App URL','2026-09-24 15:44:09','2026-09-24 15:44:09'),(4,'support_phone','0930332185','Kaldis Coffee ECA Branch Customer Support Phone','2026-09-24 15:44:09','2026-09-24 15:44:09'),(5,'telegram_channel','https://t.me/KaldisCoffeeEthiopia','Kaldis Coffee Telegram Channel','2026-09-24 15:44:09','2026-09-24 15:44:09'),(6,'maintenance_mode','false','Enable/disable ordering system','2026-09-24 15:44:09','2026-09-24 15:44:09'),(7,'min_order_amount','50','Minimum order amount in ETB','2026-09-24 15:44:09','2026-09-24 15:44:09'),(8,'delivery_fee','0','Office delivery fee amount (Free inside ECA compound)','2026-09-24 15:44:09','2026-09-24 15:44:09'),(9,'site_name','Kaldis Coffee - ECA Branch','Brand & Site Name','2026-09-24 15:44:09','2026-09-24 15:44:09'),(10,'site_name_am','ካልዲስ ቡና - ኢሲኤ ቅርንጫፍ','Brand Name in Amharic','2026-09-24 15:44:09','2026-09-24 15:44:09'),(11,'branch_name','ECA Branch','Branch Name','2026-09-24 15:44:09','2026-09-24 15:44:09'),(12,'branch_address','UNECA Compound, Menelik II Ave, Addis Ababa','Branch Physical Location','2026-09-24 15:44:09','2026-09-24 15:44:09'),(13,'refrigeration_warning','0','Refrigeration notice (disabled for hot coffee/fresh food)','2026-09-24 15:44:09','2026-09-24 15:44:09'),(14,'auto_respond','true','Auto respond to customer queries','2026-09-24 15:44:09','2026-09-24 15:44:09'),(15,'notification_enabled','true','Send Telegram notifications on new orders','2026-09-24 15:44:09','2026-09-24 15:44:09'),(16,'max_orders_per_user','50','Max orders per user','2026-09-24 15:44:09','2026-09-24 15:44:09'),(17,'allow_cancellations','true','Allow customer cancellations within window','2026-09-24 15:44:09','2026-09-24 15:44:09'),(18,'cancellation_window','30','Cancellation window in minutes','2026-09-24 15:44:09','2026-09-24 15:44:09'),(19,'enable_complaints','true','Enable customer feedback and complaints','2026-09-24 15:44:09','2026-09-24 15:44:09'),(20,'enable_subscriptions','true','Enable notification subscriptions','2026-09-24 15:44:09','2026-09-24 15:44:09'),(21,'feedback_photos','true','Allow photo uploads in feedback','2026-09-24 15:44:09','2026-09-24 15:44:09');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chat_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notification_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_id` (`chat_id`,`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `telegram_users`
--

DROP TABLE IF EXISTS `telegram_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_users` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chat_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'idle',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_id` (`chat_id`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-24 22:34:49
