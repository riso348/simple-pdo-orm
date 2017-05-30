SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

DROP TABLE IF EXISTS `car`;
CREATE TABLE IF NOT EXISTS `car` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model` varchar(50) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `year_of_production` int(4) DEFAULT NULL,
  `price` float DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

INSERT INTO `car` (`id`, `model`, `brand`, `year_of_production`, `price`) VALUES
  (1, '500', 'Fiat', 1950, 50000),
  (2, 'Punto', 'Fiat', 1997, 45000),
  (3, 'Octavia', 'Skoda', 2001, 25000);
COMMIT;