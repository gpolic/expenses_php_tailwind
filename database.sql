
DROP TABLE IF EXISTS `expense`;
CREATE TABLE `expense` (
  `ExpenseID` int NOT NULL AUTO_INCREMENT,
  `CategID` int NOT NULL DEFAULT '0',
  `ExpenseAmount` decimal(19,4) NOT NULL DEFAULT '0.0000',
  `ExpenseDate` datetime NOT NULL,
  `ExpenseDescr` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ExpenseID`),
  KEY `CategID` (`CategID`),
  KEY `ExpenseID` (`ExpenseID`),
  KEY `ExpenseDate` (`ExpenseDate`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `expensetype`;
CREATE TABLE `expensetype` (
  `categID` int NOT NULL AUTO_INCREMENT,
  `categDescr` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`categID`),
  UNIQUE KEY `categID` (`categID`)
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


