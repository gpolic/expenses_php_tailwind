
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `expense_id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL DEFAULT '0',
  `expense_amount` decimal(19,4) NOT NULL DEFAULT '0.0000',
  `created_at` datetime NOT NULL,
  `expense_description` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`expense_id`),
  KEY `category_id` (`category_id`),
  KEY `created_at` (`created_at`),
  KEY "updated_at" ("updated_at")  
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `category_id` int NOT NULL AUTO_INCREMENT,
  `category_name` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `password` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- Insert default admin user (username: admin, password: admin)
INSERT INTO `users` (`username`, `password`) VALUES ('admin', '$2y$10$6oivHQdwPE/09uTnjU9z5O1WAP2IijiKGPePh9NHU2vTX2GHz4C26');


