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
INSERT INTO `branches` VALUES (1,'ECA','Kaldis Coffee - ECA Branch','ካልዲስ ቡና - ኢሲኤ ቅርንጫፍ','UNECA Compound','UNECA Compound, Menelik II Ave, Addis Ababa','0992098459',NULL,NULL,'Exclusive office ordering & delivery service for UNECA compound staff and offices.','active',1,'2026-09-24 15:44:09','2026-09-24 15:44:09');
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
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'coffee',
  `variety` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cup',
  `unit_price` decimal(10,2) NOT NULL,
  `walkin_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_am` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `stock_quantity` int DEFAULT '100',
  `shelf_life_days` int DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_product_code` (`product_code`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dairy_products`
--

LOCK TABLES `dairy_products` WRITE;
/*!40000 ALTER TABLE `dairy_products` DISABLE KEYS */;
INSERT INTO `dairy_products` (`id`, `product_code`, `product_name`, `product_name_am`, `category`, `variety`, `unit`, `unit_price`, `walkin_price`, `image`, `description`, `description_am`, `stock_quantity`, `shelf_life_days`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1,'KLD-COF-001','Kaldis Macchiato','ካልዲስ ማኪያቶ','coffee','','cup',95,0,'prod_6abc0be46e1056.52287604.jpg','Our signature rich Ethiopian espresso with velvety steamed milk foam.','',100,1,1,1,'2026-09-24 18:44:09','2026-09-29 22:05:08'),
(2,'KLD-COF-002','Double Macchiato','ድርብ ማኪያቶ','milk','','cup',130,0,'','Double shot espresso marked with warm milk froth for an extra energy boost.','',10,1,1,2,'2026-09-24 18:44:09','2026-09-29 21:30:15'),
(3,'KLD-COF-003','Single Espresso','ነጠላ ኤስፕሬሶ','coffee',NULL,'cup',85,NULL,NULL,'Pure, aromatic Ethiopian highland espresso with thick golden crema.',NULL,100,1,1,3,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(4,'KLD-COF-004','Double Espresso','ድርብ ኤስፕሬሶ','coffee',NULL,'cup',120,NULL,NULL,'Intense double shot of single-origin Kaldis espresso.',NULL,100,1,1,4,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(5,'KLD-COF-005','Cafe Latte','ካፌ ላቴ','coffee',NULL,'cup',140,NULL,NULL,'Silky steamed milk poured over a shot of fresh espresso.',NULL,100,1,1,5,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(6,'KLD-COF-006','Cappuccino','ካፑቺኖ','coffee',NULL,'cup',140,NULL,NULL,'Equal parts espresso, steamed milk, and airy microfoam with a dusting of cocoa.',NULL,100,1,1,6,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(7,'KLD-COF-007','Americano','አሜሪካኖ','coffee',NULL,'cup',95,NULL,NULL,'Fresh espresso diluted with hot water for a smooth, deep coffee cup.',NULL,100,1,1,7,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(8,'KLD-COF-008','Caffe Mocha','ካፌ ሞካ','coffee',NULL,'cup',160,NULL,NULL,'Rich dark chocolate syrup blended with fresh espresso and hot steamed milk.',NULL,100,1,1,8,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(9,'KLD-COF-009','Special Ethiopian Spiced Tea','የቅመም ሻይ','coffee',NULL,'cup',70,NULL,NULL,'Brewed black tea infused with cinnamon, cardamom, and cloves.',NULL,100,1,1,9,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(10,'KLD-COF-010','Hot Chocolate','ትኩስ ቸኮሌት','coffee',NULL,'cup',150,NULL,NULL,'Creamy, sweet Belgian-style hot cocoa topped with milk foam.',NULL,100,1,1,10,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(11,'KLD-CLD-001','Iced Caramel Macchiato','አይስድ ካራሜል ማኪያቶ','cold_drinks',NULL,'cup',180,NULL,NULL,'Chilled milk, vanilla syrup, fresh espresso, and sweet caramel drizzle over ice.',NULL,100,1,1,11,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(12,'KLD-CLD-002','Iced Caffe Latte','አይስድ ካፌ ላቴ','cold_drinks',NULL,'cup',160,NULL,NULL,'Refreshing chilled espresso combined with cold milk over ice cubes.',NULL,100,1,1,12,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(13,'KLD-CLD-003','Kaldis Mocha Frappuccino','ካልዲስ ሞካ ፍራፑቺኖ','cold_drinks',NULL,'cup',220,NULL,NULL,'Blended iced coffee with chocolate fudge, milk, and whipped cream topping.',NULL,100,1,1,13,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(14,'KLD-CLD-004','Fresh Mango Smoothie','ትኩስ ማንጎ ስሙዚ','cold_drinks',NULL,'cup',190,NULL,NULL,'100% real ripe mango blended smooth and cold.',NULL,100,1,1,14,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(15,'KLD-CLD-005','Fresh Strawberry Smoothie','ትኩስ ስትሮውበሪ ስሙዚ','cold_drinks',NULL,'cup',190,NULL,NULL,'Sweet natural strawberries blended with crushed ice and yogurt.',NULL,100,1,1,15,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(16,'KLD-CLD-006','Fresh Mixed Juice','ትኩስ ቅልቅል ጁስ','cold_drinks',NULL,'cup',170,NULL,NULL,'Layered avocado, mango, and papaya with a squeeze of fresh lime.',NULL,100,1,1,16,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(17,'KLD-CLD-007','Mineral Water 500ml','የታሸገ ውሃ 500ሚሊ','cold_drinks',NULL,'bottle',40,NULL,NULL,'Cold refreshing spring bottled water.',NULL,100,1,1,17,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(18,'KLD-PAS-001','Butter Croissant','ቅቤ ክሩዋሳን','pastry',NULL,'piece',130,NULL,NULL,'Golden flaky, freshly baked buttery French-style croissant.',NULL,100,1,1,18,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(19,'KLD-PAS-002','Chocolate Croissant (Pain au Chocolat)','ቸኮሌት ክሩዋሳን','pastry',NULL,'piece',160,NULL,NULL,'Warm buttery pastry filled with decadent dark chocolate baton.',NULL,100,1,1,19,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(20,'KLD-PAS-003','Cinnamon Roll','ሲናሞን ሮል','pastry',NULL,'piece',140,NULL,NULL,'Swirled dough baked with aromatic cinnamon brown sugar and glazed on top.',NULL,100,1,1,20,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(21,'KLD-PAS-004','Blueberry Muffin','ብሉቤሪ ማፊን','pastry',NULL,'piece',120,NULL,NULL,'Moist, fluffy muffin bursting with sweet blueberries.',NULL,100,1,1,21,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(22,'KLD-PAS-005','Chocolate Chip Muffin','ቸኮሌት ቺፕ ማፊን','pastry',NULL,'piece',120,NULL,NULL,'Rich chocolate muffin loaded with semi-sweet chocolate morsels.',NULL,100,1,1,22,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(23,'KLD-PAS-006','Glazed Ring Donut','ዶናት','pastry',NULL,'piece',90,NULL,NULL,'Light and fluffy ring donut with classic sweet vanilla glaze.',NULL,100,1,1,23,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(24,'KLD-FOD-001','Kaldis Club Sandwich with Fries','ካልዲስ ክለብ ሳንድዊች','food',NULL,'plate',380,NULL,NULL,'Triple-decker sandwich with chicken breast, boiled egg, cheese, tomato, and fries.',NULL,100,1,1,24,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(25,'KLD-FOD-002','Grilled Chicken Breast Sandwich','የዶሮ ሳንድዊች','food',NULL,'piece',340,NULL,NULL,'Tender marinated grilled chicken breast, crisp lettuce, mayo on toasted baguette.',NULL,100,1,1,25,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(26,'KLD-FOD-003','Tuna & Sweetcorn Wrap','የቱና ራፕ','food',NULL,'piece',310,NULL,NULL,'Seasoned flaked tuna, sweet corn, greens wrapped in a warm tortilla.',NULL,100,1,1,26,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(27,'KLD-FOD-004','Crispy Golden French Fries','የድንች ጥብስ','food',NULL,'portion',150,NULL,NULL,'Freshly fried hot crispy potato chips with ketchup and mayo dipping sauces.',NULL,100,1,1,27,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(28,'KLD-FOD-005','Cheese & Tomato Toast','የአይብ እና ቲማቲም ቶስት','food',NULL,'piece',220,NULL,NULL,'Toasted artisan bread with melted gouda cheese and sliced ripe tomato.',NULL,100,1,1,28,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(29,'KLD-CAK-001','Black Forest Cake Slice','ብላክ ፎረስት ቁራጭ','cakes',NULL,'slice',220,NULL,NULL,'Layers of chocolate sponge, whipped cream, and tart cherries.',NULL,100,1,1,29,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(30,'KLD-CAK-002','Chocolate Torta Slice','ቸኮሌት ቶርታ ቁራጭ','cakes',NULL,'slice',240,NULL,NULL,'Decadent, rich chocolate fudge cake slice perfect with coffee.',NULL,100,1,1,30,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(31,'KLD-CAK-003','New York Strawberry Cheesecake','ስትሮውበሪ ቺዝኬክ ቁራጭ','cakes',NULL,'slice',260,NULL,NULL,'Velvety baked cream cheese on graham crust with strawberry compote.',NULL,100,1,1,31,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(32,'KLD-CAK-004','Carrot Cake with Cream Cheese Frosting','ካሮት ኬክ ቁራጭ','cakes',NULL,'slice',210,NULL,NULL,'Spiced moist carrot cake filled with walnuts and cream cheese.',NULL,100,1,1,32,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(33,'KLD-CAK-005','Full Black Forest Torta (Celebration)','ሙሉ ብላክ ፎረስት ቶርታ','cakes',NULL,'torta',1750,NULL,NULL,'Full round cake for office birthday or celebration (Serves 10-12).',NULL,100,1,1,33,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(34,'KLD-CAK-006','Full Chocolate Torta (Celebration)','ሙሉ ቸኮሌት ቶርታ','cakes',NULL,'torta',2000,NULL,NULL,'Full premium chocolate torta for team events and birthdays (Serves 12-15).',NULL,100,1,1,34,'2026-09-24 18:44:09','2026-09-24 18:44:09'),
(35,'1001','Ambo','አምቦ','beverage',NULL,'bottle',50,NULL,NULL,NULL,NULL,100,1,1,1,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(36,'1002','Soft Drink','Soft Drink','beverage',NULL,'can',50,NULL,NULL,NULL,NULL,100,1,1,2,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(37,'1003','Bottled Water 600ml','Bottled Water 600ml','beverage',NULL,'bottle',35,NULL,NULL,NULL,NULL,100,1,1,3,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(38,'2001','Banana Cake','Banana Cake','pastry',NULL,'slice',110,NULL,NULL,NULL,NULL,100,1,1,4,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(39,'2002','Bigne 3PCS','Bigne 3PCS','pastry',NULL,'portion',90,NULL,NULL,NULL,NULL,100,1,1,5,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(40,'2003','Chocolate Millefoglie','Chocolate Millefoglie','pastry',NULL,'piece',120,NULL,NULL,NULL,NULL,100,1,1,6,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(41,'2006','Fasting Millefoglie','Fasting Millefoglie','pastry',NULL,'piece',110,NULL,NULL,NULL,NULL,100,1,1,7,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(42,'2007','Millefoglie','Millefoglie','pastry',NULL,'piece',115,NULL,NULL,NULL,NULL,100,1,1,8,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(43,'2015','Assorted Cake','Assorted Cake','pastry',NULL,'slice',130,NULL,NULL,NULL,NULL,100,1,1,9,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(44,'2016','Soft Cake','Soft Cake','pastry',NULL,'slice',115,NULL,NULL,NULL,NULL,100,1,1,10,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(45,'2020','Anebabero Slice','Anebabero Slice','pastry',NULL,'slice',100,NULL,NULL,NULL,NULL,100,1,1,11,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(46,'2021','Cookies Big','Cookies Big','pastry',NULL,'piece',60,NULL,NULL,NULL,NULL,100,1,1,12,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(47,'2022','Cookies Small','Cookies Small','pastry',NULL,'piece',35,NULL,NULL,NULL,NULL,100,1,1,13,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(48,'2044','Fasting Baklava','Fasting Baklava','pastry',NULL,'piece',95,NULL,NULL,NULL,NULL,100,1,1,14,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(49,'3001','Cold Cup','Cold Cup','packaging',NULL,'piece',15,NULL,NULL,NULL,NULL,100,1,1,15,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(50,'3003','Torta Box','Torta Box','packaging',NULL,'piece',25,NULL,NULL,NULL,NULL,100,1,1,16,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(51,'3004','Hot Cup 4oz','Hot Cup 4oz','packaging',NULL,'piece',10,NULL,NULL,NULL,NULL,100,1,1,17,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(52,'4001','French Toast Served With Syrup','French Toast Served With Syrup','food',NULL,'plate',180,NULL,NULL,NULL,NULL,100,1,1,18,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(53,'4002','Omelette','Omelette','food',NULL,'plate',150,NULL,NULL,NULL,NULL,100,1,1,19,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(54,'4003','Omelette With Cheese','Omelette With Cheese','food',NULL,'plate',180,NULL,NULL,NULL,NULL,100,1,1,20,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(55,'4004','Scrambled Eggs','Scrambled Eggs','food',NULL,'plate',140,NULL,NULL,NULL,NULL,100,1,1,21,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(56,'4005','Cheese Burger','Cheese Burger','food',NULL,'portion',260,NULL,NULL,NULL,NULL,100,1,1,22,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(57,'4008','Lentil Sambusa','Lentil Sambusa','food',NULL,'piece',30,NULL,NULL,NULL,NULL,100,1,1,23,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(58,'4009','Meat Sambusa','Meat Sambusa','food',NULL,'piece',40,NULL,NULL,NULL,NULL,100,1,1,24,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(59,'4010','Tuna Sandwich','Tuna Sandwich','food',NULL,'portion',220,NULL,NULL,NULL,NULL,100,1,1,25,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(60,'4011','Steak Sandwich','Steak Sandwich','food',NULL,'portion',280,NULL,NULL,NULL,NULL,100,1,1,26,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(61,'4012','Club Sandwich','Club Sandwich','food',NULL,'portion',290,NULL,NULL,NULL,NULL,100,1,1,27,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(62,'4015','Kaldis Special Burger','Kaldis Special Burger','food',NULL,'portion',320,NULL,NULL,NULL,NULL,100,1,1,28,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(63,'4017','Egg Sandwich With Cheese','Egg Sandwich With Cheese','food',NULL,'portion',190,NULL,NULL,NULL,NULL,100,1,1,29,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(64,'4018','Cheese Burger With Egg','Cheese Burger With Egg','food',NULL,'portion',290,NULL,NULL,NULL,NULL,100,1,1,30,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(65,'4023','Chicken Pesto Wrap','Chicken Pesto Wrap','food',NULL,'portion',270,NULL,NULL,NULL,NULL,100,1,1,31,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(66,'4027','Veggie Wrap','Veggie Wrap','food',NULL,'portion',210,NULL,NULL,NULL,NULL,100,1,1,32,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(67,'4140','Egg Sandwich','Egg Sandwich','food',NULL,'portion',160,NULL,NULL,NULL,NULL,100,1,1,33,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(68,'4510','Take Away Fast Food','Take Away Fast Food','food',NULL,'portion',200,NULL,NULL,NULL,NULL,100,1,1,34,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(69,'5001','Cafe Latte Short','Cafe Latte Short','coffee',NULL,'cup',75,NULL,NULL,NULL,NULL,100,1,1,35,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(70,'5002','Cafe Latte Tall','Cafe Latte Tall','coffee',NULL,'cup',85,NULL,NULL,NULL,NULL,100,1,1,36,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(71,'5003','Cappuccino Short','Cappuccino Short','coffee',NULL,'cup',75,NULL,NULL,NULL,NULL,100,1,1,37,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(72,'5004','Cappuccino Tall','Cappuccino Tall','coffee',NULL,'cup',85,NULL,NULL,NULL,NULL,100,1,1,38,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(73,'5005','Caramel Macchiato Short','Caramel Macchiato Short','coffee',NULL,'cup',85,NULL,NULL,NULL,NULL,100,1,1,39,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(74,'5006','Caramel Macchiato Tall','Caramel Macchiato Tall','coffee',NULL,'cup',95,NULL,NULL,NULL,NULL,100,1,1,40,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(75,'5007','Coffee Americano Short','Coffee Americano Short','coffee',NULL,'cup',60,NULL,NULL,NULL,NULL,100,1,1,41,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(76,'5009','Coffee Short','Coffee Short','coffee',NULL,'cup',50,NULL,NULL,NULL,NULL,100,1,1,42,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(77,'5010','Coffee Tall','Coffee Tall','coffee',NULL,'cup',60,NULL,NULL,NULL,NULL,100,1,1,43,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(78,'5011','Espresso Short','Espresso Short','coffee',NULL,'cup',65,NULL,NULL,NULL,NULL,100,1,1,44,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(79,'5012','Espresso Tall','Espresso Tall','coffee',NULL,'cup',75,NULL,NULL,NULL,NULL,100,1,1,45,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(80,'5014','Coffee Mate Cappuccino Short','Coffee Mate Cappuccino Short','coffee',NULL,'cup',80,NULL,NULL,NULL,NULL,100,1,1,46,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(81,'5015','Coffee Mate Cappuccino Tall','Coffee Mate Cappuccino Tall','coffee',NULL,'cup',90,NULL,NULL,NULL,NULL,100,1,1,47,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(82,'5018','Coffee Mate Cafe Latte Short','Coffee Mate Cafe Latte Short','coffee',NULL,'cup',80,NULL,NULL,NULL,NULL,100,1,1,48,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(83,'5020','Coffee Mate Macchiato Short','Coffee Mate Macchiato Short','coffee',NULL,'cup',80,NULL,NULL,NULL,NULL,100,1,1,49,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(84,'5021','Coffee Mate Macchiato Tall','Coffee Mate Macchiato Tall','coffee',NULL,'cup',90,NULL,NULL,NULL,NULL,100,1,1,50,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(85,'5023','Hot Chocolate Short','Hot Chocolate Short','coffee',NULL,'cup',85,NULL,NULL,NULL,NULL,100,1,1,51,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(86,'5025','Macchiato Short','Macchiato Short','coffee',NULL,'cup',75,NULL,NULL,NULL,NULL,100,1,1,52,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(87,'5026','Macchiato Tall','Macchiato Tall','coffee',NULL,'cup',85,NULL,NULL,NULL,NULL,100,1,1,53,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(88,'5028','Steamed Milk Short','Steamed Milk Short','coffee',NULL,'cup',60,NULL,NULL,NULL,NULL,100,1,1,54,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(89,'5029','Steamed Milk Tall','Steamed Milk Tall','coffee',NULL,'cup',70,NULL,NULL,NULL,NULL,100,1,1,55,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(90,'5037','Maslati Tea','Maslati Tea','tea',NULL,'cup',65,NULL,NULL,NULL,NULL,100,1,1,56,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(91,'5038','Tea','Tea','tea',NULL,'cup',40,NULL,NULL,NULL,NULL,100,1,1,57,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(92,'5041','Tea Espresso','Tea Espresso','tea',NULL,'cup',50,NULL,NULL,NULL,NULL,100,1,1,58,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(93,'5042','Steamed Milk With Tea Short','Steamed Milk With Tea Short','coffee',NULL,'cup',65,NULL,NULL,NULL,NULL,100,1,1,59,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(94,'5043','Steamed Milk With Tea Tall','Steamed Milk With Tea Tall','coffee',NULL,'cup',75,NULL,NULL,NULL,NULL,100,1,1,60,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(95,'5045','Yalekelet Tea','Yalekelet Tea','tea',NULL,'cup',60,NULL,NULL,NULL,NULL,100,1,1,61,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(96,'5052','Lemon Tea','Lemon Tea','tea',NULL,'cup',55,NULL,NULL,NULL,NULL,100,1,1,62,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(97,'5053','Jigger Tea','Jigger Tea','tea',NULL,'cup',60,NULL,NULL,NULL,NULL,100,1,1,63,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(98,'5054','Green Tea','Green Tea','tea',NULL,'cup',60,NULL,NULL,NULL,NULL,100,1,1,64,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(99,'7002','Kaldi\'s Tea','Kaldi\'s Tea','tea',NULL,'cup',70,NULL,NULL,NULL,NULL,100,1,1,65,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(100,'7003','Extra Honey','Extra Honey','extra',NULL,'portion',25,NULL,NULL,NULL,NULL,100,1,1,66,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(101,'7004','Mixed Juice','Mixed Juice','beverage',NULL,'glass',120,NULL,NULL,NULL,NULL,100,1,1,67,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(102,'7008','Strawberry Special With Milk','Strawberry Special With Milk','beverage',NULL,'glass',140,NULL,NULL,NULL,NULL,100,1,1,68,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(103,'7010','Pineapple Juice','Pineapple Juice','beverage',NULL,'glass',110,NULL,NULL,NULL,NULL,100,1,1,69,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(104,'7011','Avocado Juice','Avocado Juice','beverage',NULL,'glass',110,NULL,NULL,NULL,NULL,100,1,1,70,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(105,'7017','Mango Juice','Mango Juice','beverage',NULL,'glass',110,NULL,NULL,NULL,NULL,100,1,1,71,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(106,'7019','Take Away Pastry','Take Away Pastry','pastry',NULL,'piece',100,NULL,NULL,NULL,NULL,100,1,1,72,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(107,'7034','Papaya Juice','Papaya Juice','beverage',NULL,'glass',110,NULL,NULL,NULL,NULL,100,1,1,73,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(108,'9348','Guava Juice','Guava Juice','beverage',NULL,'glass',110,NULL,NULL,NULL,NULL,100,1,1,74,'2026-10-01 14:58:29','2026-10-01 14:58:29'),
(109,'9374','Hot Cup 7oz','Hot Cup 7oz','packaging',NULL,'piece',12,NULL,NULL,NULL,NULL,100,1,1,75,'2026-10-01 14:58:29','2026-10-01 14:58:29');
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
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chat_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `idx_order_number` (`order_number`),
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
INSERT INTO `payment_methods` VALUES (1,'Telebirr','ቴሌብር','Kaldis Coffee ECA','0992098459','Pay via Telebirr to 0992098459 and upload screenshot or reference number.',NULL,1,1,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(2,'CBE Birr','ሲቢኢ ብር','Kaldis Coffee ECA','1000123456789','Pay via CBE Birr to 1000123456789 and enter transaction reference.',NULL,1,2,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(3,'Bank of Abyssinia','አቢሲኒያ ባንክ','Kaldis Coffee','88776655','Transfer to BoA account 88776655.',NULL,1,3,'2026-09-24 15:44:09','2026-09-24 15:44:09'),(4,'Pay at Office Desk','በቢሮ ሲደርስ በጥሬ ገንዘብ','Cash on Delivery','Cash','Pay cash to our delivery runner when your order reaches your office desk.',NULL,1,4,'2026-09-24 15:44:09','2026-09-24 15:44:09');
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
  `rejection_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
INSERT INTO `settings` VALUES (1,'bot_token','8575284682:AAGbp6ZDaj1T2vPk1GSRrFC7rXCPbO8vyX8','Telegram Bot Token for Kaldis Coffee ECA Pre-Order Bot','2026-09-24 15:44:09','2026-09-24 15:44:09'),(2,'admin_chat_id','814523272','Telegram Admin Chat ID for notifications','2026-09-24 15:44:09','2026-09-24 15:44:09'),(3,'mini_app_url','https://ordering.kaldisbunnaet.com/eca/miniapp/app.html','Kaldis Mini App URL','2026-09-24 15:44:09','2026-09-24 15:44:09'),(4,'support_phone','0992098459','Kaldis Coffee ECA Branch Customer Support Phone','2026-09-24 15:44:09','2026-09-24 15:44:09'),(5,'telegram_channel','https://t.me/ECAKB','Kaldis Coffee Telegram Channel','2026-09-24 15:44:09','2026-09-24 15:44:09'),(6,'maintenance_mode','false','Enable/disable ordering system','2026-09-24 15:44:09','2026-09-24 15:44:09'),(7,'min_order_amount','50','Minimum order amount in ETB','2026-09-24 15:44:09','2026-09-24 15:44:09'),(8,'delivery_fee','0','Office delivery fee amount (Free inside ECA compound)','2026-09-24 15:44:09','2026-09-24 15:44:09'),(9,'site_name','Kaldis Coffee - ECA Branch','Brand & Site Name','2026-09-24 15:44:09','2026-09-24 15:44:09'),(10,'site_name_am','ካልዲስ ቡና - ኢሲኤ ቅርንጫፍ','Brand Name in Amharic','2026-09-24 15:44:09','2026-09-24 15:44:09'),(11,'branch_name','ECA Branch','Branch Name','2026-09-24 15:44:09','2026-09-24 15:44:09'),(12,'branch_address','UNECA Compound, Menelik II Ave, Addis Ababa','Branch Physical Location','2026-09-24 15:44:09','2026-09-24 15:44:09'),(13,'refrigeration_warning','0','Refrigeration notice (disabled for hot coffee/fresh food)','2026-09-24 15:44:09','2026-09-24 15:44:09'),(14,'auto_respond','true','Auto respond to customer queries','2026-09-24 15:44:09','2026-09-24 15:44:09'),(15,'notification_enabled','true','Send Telegram notifications on new orders','2026-09-24 15:44:09','2026-09-24 15:44:09'),(16,'max_orders_per_user','50','Max orders per user','2026-09-24 15:44:09','2026-09-24 15:44:09'),(17,'allow_cancellations','true','Allow customer cancellations within window','2026-09-24 15:44:09','2026-09-24 15:44:09'),(18,'cancellation_window','30','Cancellation window in minutes','2026-09-24 15:44:09','2026-09-24 15:44:09'),(19,'enable_complaints','true','Enable customer feedback and complaints','2026-09-24 15:44:09','2026-09-24 15:44:09'),(20,'enable_subscriptions','true','Enable notification subscriptions','2026-09-24 15:44:09','2026-09-24 15:44:09'),(21,'feedback_photos','true','Allow photo uploads in feedback','2026-09-24 15:44:09','2026-09-24 15:44:09');
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
