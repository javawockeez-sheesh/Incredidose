-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 08, 2025 at 12:39 PM
-- Server version: 9.1.0
-- PHP Version: 8.4.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mydatabase`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
CREATE TABLE IF NOT EXISTS `admin` (
  `userid` int NOT NULL,
  `manage_system` tinyint(1) NOT NULL,
  `manage_doctor` tinyint(1) NOT NULL,
  `manage_pharmacist` tinyint(1) NOT NULL,
  PRIMARY KEY (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `card`
--

DROP TABLE IF EXISTS `card`;
CREATE TABLE IF NOT EXISTS `card` (
  `paymentid` int NOT NULL,
  `referenceno` int NOT NULL,
  `paymentmethod` varchar(35) NOT NULL,
  `cardholdername` varchar(35) NOT NULL,
  `last4digits` int NOT NULL,
  `processor` varchar(35) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log`
--

DROP TABLE IF EXISTS `log`;
CREATE TABLE IF NOT EXISTS `log` (
  `logid` int NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL,
  `targetentitytype` varchar(8) NOT NULL,
  `targetid` int NOT NULL,
  `userid` int NOT NULL,
  PRIMARY KEY (`logid`),
  KEY `logactinguser` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS `payment`;
CREATE TABLE IF NOT EXISTS `payment` (
  `paymentid` int NOT NULL AUTO_INCREMENT,
  `amount` int NOT NULL,
  `paymentdate` timestamp NOT NULL,
  `paymentmethod` varchar(3) NOT NULL,
  `status` varchar(10) NOT NULL,
  `purchaseid` int NOT NULL,
  PRIMARY KEY (`paymentid`),
  KEY `purchase` (`purchaseid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `practitioner`
--

DROP TABLE IF EXISTS `practitioner`;
CREATE TABLE IF NOT EXISTS `practitioner` (
  `userid` int NOT NULL,
  `type` varchar(15) NOT NULL,
  `licensenum` varchar(10) NOT NULL,
  `specialization` varchar(35) NOT NULL,
  `affiliation` varchar(50) NOT NULL,
  PRIMARY KEY (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `practitioner`
--

INSERT INTO `practitioner` (`userid`, `type`, `licensenum`, `specialization`, `affiliation`) VALUES
(41, 'doctor', 'MD-4100001', 'General Medicine', 'St. Luke\'s Medical Center'),
(42, 'doctor', 'MD-4100002', 'Pediatrics', 'Makati Medical Center'),
(43, 'doctor', 'MD-4100003', 'Dermatology', 'Asian Hospital & Medical Center'),
(44, 'doctor', 'MD-4100004', 'Cardiology', 'The Medical City'),
(45, 'pharmacist', 'PH-4500001', 'Community Pharmacy', 'Mercury Drug'),
(46, 'pharmacist', 'PH-4500002', 'Clinical Pharmacy', 'Watsons Pharmacy'),
(47, 'pharmacist', 'PH-4500003', 'Hospital Pharmacy', 'Generika Drugstore'),
(48, 'pharmacist', 'PH-4500004', 'Compounding Pharmacy', 'South Star Drug'),
(49, 'doctor', 'MD-4900005', 'Internal Medicine', 'Perpetual Help Medical Center'),
(50, 'doctor', 'MD-4900006', 'OB-GYN', 'Mary Johnston Hospital'),
(51, 'doctor', 'MD-4900007', 'Neurology', 'University of the Philippines PGH'),
(52, 'doctor', 'MD-4900008', 'Orthopedics', 'Manila Doctors Hospital'),
(53, 'doctor', 'MD-4900009', 'Surgery', 'Cardinal Santos Medical Center'),
(54, 'doctor', 'MD-4900010', 'Family Medicine', 'Feu-Nrmf Medical Center');

-- --------------------------------------------------------

--
-- Table structure for table `prescription`
--

DROP TABLE IF EXISTS `prescription`;
CREATE TABLE IF NOT EXISTS `prescription` (
  `prescriptionid` int NOT NULL AUTO_INCREMENT,
  `dateprescribed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `validperiod` date DEFAULT NULL,
  `patientid` int NOT NULL,
  `doctorid` int NOT NULL,
  PRIMARY KEY (`prescriptionid`),
  KEY `prescriptionpatient` (`patientid`),
  KEY `prescriptiondoctor` (`doctorid`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `prescription`
--

INSERT INTO `prescription` (`prescriptionid`, `dateprescribed`, `validperiod`, `patientid`, `doctorid`) VALUES
(22, '2025-01-05 10:15:00', '2025-01-30', 21, 41),
(23, '2025-01-12 09:45:00', '2025-01-28', 22, 42),
(24, '2025-02-03 14:20:00', '2025-02-18', 23, 43),
(25, '2025-02-11 11:05:00', '2025-03-08', 24, 44),
(26, '2025-02-25 16:30:00', '2025-03-15', 25, 49),
(27, '2025-03-04 08:50:00', '2025-03-26', 26, 50),
(28, '2025-03-18 13:42:00', '2025-04-07', 27, 51),
(29, '2025-04-02 15:55:00', '2025-04-12', 28, 52),
(30, '2025-04-10 10:30:00', '2025-04-30', 29, 53),
(31, '2025-04-22 09:12:00', '2025-05-14', 30, 54),
(32, '2025-05-03 14:40:00', '2025-05-12', 31, 41),
(33, '2025-05-19 11:28:00', '2025-06-10', 32, 42),
(34, '2025-06-07 08:15:00', '2025-06-27', 33, 43),
(35, '2025-06-21 12:55:00', '2025-07-14', 34, 44),
(36, '2025-07-02 16:05:00', '2025-07-27', 35, 49),
(37, '2025-07-15 10:48:00', '2025-08-14', 36, 50),
(38, '2025-08-01 09:30:00', '2025-08-21', 37, 51),
(39, '2025-08-18 14:20:00', '2025-09-08', 38, 52),
(40, '2025-09-05 11:10:00', '2025-09-25', 39, 53),
(41, '2025-09-18 15:00:00', '2025-10-08', 40, 54);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptionitem`
--

DROP TABLE IF EXISTS `prescriptionitem`;
CREATE TABLE IF NOT EXISTS `prescriptionitem` (
  `prescriptionitemid` int NOT NULL AUTO_INCREMENT,
  `prescriptionid` int NOT NULL,
  `name` varchar(35) NOT NULL,
  `brand` varchar(35) NOT NULL,
  `quantity` int NOT NULL,
  `dosage` varchar(10) NOT NULL,
  `frequency` int NOT NULL,
  `description` varchar(255) NOT NULL,
  `substitutions` tinyint(1) NOT NULL,
  PRIMARY KEY (`prescriptionitemid`,`prescriptionid`),
  KEY `prescprescitem` (`prescriptionid`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `prescriptionitem`
--

INSERT INTO `prescriptionitem` (`prescriptionitemid`, `prescriptionid`, `name`, `brand`, `quantity`, `dosage`, `frequency`, `description`, `substitutions`) VALUES
(1, 22, 'Amoxicillin', 'Amoxil', 30, '500mg', 3, 'Take one capsule three times daily after meals', 0),
(2, 22, 'Paracetamol', 'Generic', 0, '500mg', 4, 'Take as needed for fever or pain, maximum 4 times daily', 1),
(3, 23, 'Cetirizine', 'Zyrtec', 15, '10mg', 1, 'Take one tablet daily at bedtime', 1),
(4, 23, 'Salbutamol', 'Ventolin', 1, '100mcg', 4, 'Inhaler - Use as needed for asthma symptoms', 0),
(5, 24, 'Clobetasol', 'Clobex', 1, '0.05%', 2, 'Topical cream - Apply thin layer to affected area twice daily', 0),
(6, 25, 'Atorvastatin', 'Lipitor', 30, '20mg', 1, 'Take one tablet daily at bedtime', 0),
(7, 25, 'Aspirin', 'Ecotrin', 30, '81mg', 1, 'Take one tablet daily in the morning', 1),
(8, 26, 'Metformin', 'Glucophage', 60, '500mg', 2, 'Take one tablet twice daily with meals', 1),
(9, 27, 'Prenatal Vitamins', 'Pregna', 30, 'Once daily', 1, 'Take one capsule daily with breakfast', 0),
(10, 27, 'Ferrous Sulfate', 'FeroSul', 30, '325mg', 1, 'Take one tablet daily', 1),
(11, 28, 'Gabapentin', 'Neurontin', 90, '300mg', 3, 'Take one capsule three times daily', 0),
(12, 29, 'Ibuprofen', 'Advil', 30, '400mg', 3, 'Take one tablet every 6 hours as needed for pain', 1),
(13, 29, 'Acetaminophen', 'Tylenol', 20, '500mg', 4, 'Take as needed for pain relief', 1),
(14, 30, 'Cephalexin', 'Keflex', 40, '500mg', 4, 'Take one capsule four times daily', 0),
(15, 31, 'Lisinopril', 'Generic', 30, '10mg', 1, 'Take one tablet daily in the morning', 0),
(16, 32, 'Azithromycin', 'Zithromax', 6, '250mg', 1, 'Take two tablets on first day, then one daily for 4 days', 0),
(17, 33, 'Loratadine', 'Claritin', 20, '10mg', 1, 'Take one tablet daily', 1),
(18, 33, 'Dextromethorphan', 'Robitussin', 1, '15mg/5ml', 4, 'Syrup - Take 10ml every 6 hours as needed for cough', 1),
(19, 34, 'Hydrocortisone', 'Cortizone', 1, '1%', 3, 'Cream - Apply to affected area three times daily', 1),
(20, 35, 'Simvastatin', 'Zocor', 30, '20mg', 1, 'Take one tablet daily at bedtime', 0),
(21, 36, 'Metoprolol', 'Lopressor', 60, '50mg', 2, 'Take one tablet twice daily', 0),
(22, 36, 'Furosemide', 'Lasix', 30, '40mg', 1, 'Take one tablet daily in the morning', 0),
(23, 37, 'Prenatal Calcium', 'Caltrate', 60, '600mg', 2, 'Take one tablet twice daily', 1),
(24, 38, 'Pregabalin', 'Lyrica', 60, '75mg', 2, 'Take one capsule twice daily', 0),
(25, 39, 'Naproxen', 'Aleve', 30, '500mg', 2, 'Take one tablet twice daily with food', 1),
(26, 40, 'Ciprofloxacin', 'Cipro', 20, '500mg', 2, 'Take one tablet twice daily', 0),
(27, 41, 'Omeprazole', 'Prilosec', 30, '20mg', 1, 'Take one capsule daily before breakfast', 1),
(28, 41, 'Multivitamin', 'Centrum', 30, 'Once daily', 1, 'Take one tablet daily', 1),
(30, 22, 'Fent', 'Generic', 4, '10mg', 5, 'Bringing Japanese Culture to the Streets of Vancouver', 1);

-- --------------------------------------------------------

--
-- Table structure for table `purchase`
--

DROP TABLE IF EXISTS `purchase`;
CREATE TABLE IF NOT EXISTS `purchase` (
  `purchaseid` int NOT NULL AUTO_INCREMENT,
  `purchasetimestamp` timestamp NOT NULL,
  `patientid` int NOT NULL,
  `pharmacistid` int NOT NULL,
  `prescriptionid` int NOT NULL,
  PRIMARY KEY (`purchaseid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchaseitem`
--

DROP TABLE IF EXISTS `purchaseitem`;
CREATE TABLE IF NOT EXISTS `purchaseitem` (
  `purchaseitemid` int NOT NULL AUTO_INCREMENT,
  `purchaseid` int NOT NULL,
  `unitprice` int NOT NULL,
  `quantity` int NOT NULL,
  `totalprice` int NOT NULL,
  `precriptionitemid` int NOT NULL,
  PRIMARY KEY (`purchaseitemid`,`purchaseid`),
  KEY `purchasedprescriptionitem` (`precriptionitemid`),
  KEY `purchasedid` (`purchaseid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `userid` int NOT NULL AUTO_INCREMENT,
  `firstname` varchar(35) NOT NULL,
  `lastname` varchar(35) NOT NULL,
  `contactnum` int NOT NULL,
  `email` varchar(35) NOT NULL,
  `password` varchar(255) NOT NULL,
  `birthdate` datetime NOT NULL,
  `createdat` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('pcr','admn','ptnt') NOT NULL,
  `gender` varchar(11) NOT NULL,
  PRIMARY KEY (`userid`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`userid`, `firstname`, `lastname`, `contactnum`, `email`, `password`, `birthdate`, `createdat`, `role`, `gender`) VALUES
(21, 'Juan', 'Cruz', 2147483647, 'juan.cruz@gmail.com', 'Juan123', '1998-03-12 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(22, 'Maria', 'Santos', 2147483647, 'maria.santos@yahoo.com', 'Maria123', '1995-07-09 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(23, 'Alex', 'Reyes', 2147483647, 'alex.reyes@gmail.com', 'Alex123', '1993-11-21 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(24, 'Sophia', 'Lopez', 2147483647, 'sophia.lopez@yahoo.com', 'Sophia123', '1997-02-10 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(25, 'Daniel', 'Garcia', 2147483647, 'daniel.garcia@gmail.com', 'Daniel123', '1992-09-25 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(26, 'Emily', 'Torres', 2147483647, 'emily.torres@hotmail.com', 'Emily123', '1999-05-15 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(27, 'Michael', 'Tan', 2147483647, 'michael.tan@gmail.com', 'Michael123', '1991-08-19 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(28, 'Hannah', 'Flores', 2147483647, 'hannah.flores@yahoo.com', 'Hannah123', '1996-12-01 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(29, 'Joshua', 'Ramos', 2147483647, 'joshua.ramos@gmail.com', 'Joshua123', '1994-06-23 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(30, 'Angelica', 'Bautista', 2147483647, 'angelica.bautista@yahoo.com', 'Angelica123', '1998-04-11 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(31, 'Patrick', 'Castro', 2147483647, 'patrick.castro@gmail.com', 'Patrick123', '1997-03-08 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(32, 'Christine', 'Navarro', 2147483647, 'christine.navarro@hotmail.com', 'Christine123', '1995-10-17 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(33, 'Ryan', 'Lim', 2147483647, 'ryan.lim@gmail.com', 'Ryan123', '1990-01-28 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(34, 'Isabella', 'Mendoza', 2147483647, 'isabella.mendoza@yahoo.com', 'Isabella123', '1999-07-13 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(35, 'Nathan', 'Villanueva', 2147483647, 'nathan.villanueva@gmail.com', 'Nathan123', '1992-02-02 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(36, 'Alyssa', 'Rivera', 2147483647, 'alyssa.rivera@gmail.com', 'Alyssa123', '1993-10-20 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(37, 'Jacob', 'Santiago', 2147483647, 'jacob.santiago@yahoo.com', 'Jacob123', '1996-05-09 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(38, 'Ella', 'Diaz', 2147483647, 'ella.diaz@gmail.com', 'Ella123', '1994-09-18 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(39, 'Gabriel', 'Morales', 2147483647, 'gabriel.morales@gmail.com', 'Gabriel123', '1991-12-22 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Male'),
(40, 'Nicole', 'Fernandez', 2147483647, 'nicole.fernandez@yahoo.com', 'Nicole123', '1997-11-30 00:00:00', '2025-10-30 15:53:04', 'ptnt', 'Female'),
(41, 'Drake', 'Mitchell', 2147483647, 'drake.mitchell@gmail.com', 'Drake123', '1985-03-19 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Male'),
(42, 'Olivia', 'Johnson', 2147483647, 'olivia.johnson@gmail.com', 'Olivia123', '1988-06-25 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Female'),
(43, 'Ethan', 'Parker', 2147483647, 'ethan.parker@yahoo.com', 'Ethan123', '1983-12-04 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Male'),
(44, 'Chloe', 'Anderson', 2147483647, 'chloe.anderson@gmail.com', 'Chloe123', '1989-09-17 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Female'),
(45, 'Lucas', 'Thompson', 2147483647, 'lucas.thompson@gmail.com', 'Lucas123', '1986-04-07 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Male'),
(46, 'Mia', 'White', 2147483647, 'mia.white@yahoo.com', 'Mia123', '1990-08-14 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Female'),
(47, 'Noah', 'Hall', 2147483647, 'noah.hall@gmail.com', 'Noah123', '1987-10-29 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Male'),
(48, 'Grace', 'Clark', 2147483647, 'grace.clark@hotmail.com', 'Grace123', '1991-11-06 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Female'),
(49, 'Henry', 'Lewis', 2147483647, 'henry.lewis@gmail.com', 'Henry123', '1984-02-23 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Male'),
(50, 'Zoe', 'Scott', 2147483647, 'zoe.scott@gmail.com', 'Zoe123', '1992-01-03 00:00:00', '2025-10-30 15:53:04', 'pcr', 'Female'),
(51, 'Admin', 'Delos Santos', 2147483647, 'admin1@incredidose.com', 'Admin123', '1980-05-01 00:00:00', '2025-10-30 15:53:04', 'admn', 'Male'),
(52, 'Carla', 'Hernandez', 2147483647, 'carla.hernandez@incredidose.com', 'Carla123', '1983-07-12 00:00:00', '2025-10-30 15:53:04', 'admn', 'Female'),
(53, 'Rafael', 'Torralba', 2147483647, 'rafael.torralba@incredidose.com', 'Rafael123', '1978-09-27 00:00:00', '2025-10-30 15:53:04', 'admn', 'Male'),
(54, 'Bea', 'Domingo', 2147483647, 'bea.domingo@incredidose.com', 'Bea123', '1985-11-18 00:00:00', '2025-10-30 15:53:04', 'admn', 'Female');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `useradmin` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `log`
--
ALTER TABLE `log`
  ADD CONSTRAINT `logactinguser` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `purchase` FOREIGN KEY (`purchaseid`) REFERENCES `purchase` (`purchaseid`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `practitioner`
--
ALTER TABLE `practitioner`
  ADD CONSTRAINT `practitioneruser` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `prescription`
--
ALTER TABLE `prescription`
  ADD CONSTRAINT `prescriptiondoctor` FOREIGN KEY (`doctorid`) REFERENCES `practitioner` (`userid`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `prescriptionpatient` FOREIGN KEY (`patientid`) REFERENCES `user` (`userid`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `prescriptionitem`
--
ALTER TABLE `prescriptionitem`
  ADD CONSTRAINT `prescprescitem` FOREIGN KEY (`prescriptionid`) REFERENCES `prescription` (`prescriptionid`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
