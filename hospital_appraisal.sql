-- Hospital Appraisal System Database Export
-- Database: hospital_appraisal
-- Generated on: 2024

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Create database
CREATE DATABASE IF NOT EXISTS `hospital_appraisal` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hospital_appraisal`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','appraiser','appraisee') NOT NULL,
  `title` varchar(10) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `other_names` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `grade_salary` varchar(100) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `staff_id`, `password`, `role`, `title`, `first_name`, `last_name`, `other_names`, `gender`, `grade_salary`, `job_title`, `department`, `appointment_date`, `is_approved`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'ADMIN001', '0192023a7bbd73250516f069df18b500', 'admin', 'Mr.', 'System', 'Administrator', '', 'Male', 'Grade A - 5000', 'System Administrator', 'IT Department', '2020-01-01', 1, NOW(), NOW()),
(2, 'dr.smith', 'DOC001', '482c811da5d5b4bc6d497ffa98491e38', 'appraiser', 'Dr.', 'John', 'Smith', '', 'Male', 'Grade B - 8000', 'Chief Medical Officer', 'Medical Department', '2018-03-15', 1, NOW(), NOW()),
(3, 'dr.johnson', 'DOC002', '482c811da5d5b4bc6d497ffa98491e38', 'appraiser', 'Dr.', 'Mary', 'Johnson', '', 'Female', 'Grade B - 7500', 'Head of Nursing', 'Nursing Department', '2019-06-01', 1, NOW(), NOW()),
(4, 'nurse.jane', 'NURSE001', '482c811da5d5b4bc6d497ffa98491e38', 'appraisee', 'Ms.', 'Jane', 'Doe', '', 'Female', 'Grade C - 4500', 'Senior Nurse', 'Nursing Department', '2021-02-10', 1, NOW(), NOW()),
(5, 'nurse.mike', 'NURSE002', '482c811da5d5b4bc6d497ffa98491e38', 'appraisee', 'Mr.', 'Michael', 'Brown', '', 'Male', 'Grade C - 4000', 'Staff Nurse', 'Emergency Department', '2022-01-20', 1, NOW(), NOW()),
(6, 'tech.sarah', 'TECH001', '482c811da5d5b4bc6d497ffa98491e38', 'appraisee', 'Ms.', 'Sarah', 'Wilson', '', 'Female', 'Grade D - 3500', 'Medical Technician', 'Laboratory', '2021-08-15', 1, NOW(), NOW()),
(7, 'admin.peter', 'ADMIN002', '482c811da5d5b4bc6d497ffa98491e38', 'appraisee', 'Mr.', 'Peter', 'Jones', '', 'Male', 'Grade D - 3000', 'Administrative Assistant', 'Administration', '2020-11-01', 1, NOW(), NOW()),
(8, 'dr.emily', 'DOC003', '482c811da5d5b4bc6d497ffa98491e38', 'appraisee', 'Dr.', 'Emily', 'Davis', '', 'Female', 'Grade C - 5500', 'Junior Doctor', 'Medical Department', '2023-01-05', 1, NOW(), NOW());

-- --------------------------------------------------------

--
-- Table structure for table `appraisals`
--

CREATE TABLE `appraisals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appraisee_id` int(11) NOT NULL,
  `appraiser_id` int(11) NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `status` enum('draft','planning','mid_review','final_review','completed') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appraisee_id` (`appraisee_id`),
  KEY `appraiser_id` (`appraiser_id`),
  CONSTRAINT `appraisals_ibfk_1` FOREIGN KEY (`appraisee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appraisals_ibfk_2` FOREIGN KEY (`appraiser_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `performance_planning`
--

CREATE TABLE `performance_planning` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appraisal_id` int(11) NOT NULL,
  `key_result_area` text NOT NULL,
  `target` text NOT NULL,
  `resources_required` text DEFAULT NULL,
  `competencies_required` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `performance_planning_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `training_records`
--

CREATE TABLE `training_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `institution` varchar(100) NOT NULL,
  `programme` varchar(200) NOT NULL,
  `date_completed` date NOT NULL,
  `appraisal_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `training_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_records_ibfk_2` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `training_records`
--

INSERT INTO `training_records` (`id`, `user_id`, `institution`, `programme`, `date_completed`, `appraisal_id`, `created_at`) VALUES
(1, 4, 'Ghana Health Service Training Institute', 'Advanced Nursing Care', '2023-05-15', NULL, NOW()),
(2, 4, 'University of Ghana Medical School', 'Emergency Response Training', '2023-08-20', NULL, NOW()),
(3, 5, 'National Ambulance Service', 'First Aid Certification', '2023-03-10', NULL, NOW()),
(4, 6, 'Ghana Institute of Management', 'Laboratory Quality Control', '2023-07-12', NULL, NOW()),
(5, 8, 'West African College of Physicians', 'Clinical Research Methods', '2023-09-05', NULL, NOW());

-- --------------------------------------------------------

--
-- Table structure for table `mid_year_reviews`
--

CREATE TABLE `mid_year_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appraisal_id` int(11) NOT NULL,
  `target` text NOT NULL,
  `progress_review` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `competency` varchar(100) DEFAULT NULL,
  `competency_progress` text DEFAULT NULL,
  `competency_remarks` text DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `mid_year_reviews_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `end_year_reviews`
--

CREATE TABLE `end_year_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appraisal_id` int(11) NOT NULL,
  `target` text NOT NULL,
  `performance_assessment` text DEFAULT NULL,
  `weight_of_target` decimal(3,2) DEFAULT 5.00,
  `score` int(11) DEFAULT NULL CHECK (`score` >= 1 and `score` <= 5),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `end_year_reviews_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `core_competencies`
--

CREATE TABLE `core_competencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appraisal_id` int(11) NOT NULL,
  `competency_category` varchar(100) NOT NULL,
  `competency_item` varchar(200) NOT NULL,
  `weight` decimal(3,2) NOT NULL DEFAULT 0.30,
  `score` int(11) NOT NULL CHECK (`score` >= 1 and `score` <= 5),
  `weighted_score` decimal(5,2) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `core_competencies_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `non_core_competencies`
--

CREATE TABLE `non_core_competencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appraisal_id` int(11) NOT NULL,
  `competency_category` varchar(100) NOT NULL,
  `competency_item` varchar(200) NOT NULL,
  `weight` decimal(3,2) NOT NULL DEFAULT 0.10,
  `score` int(11) NOT NULL CHECK (`score` >= 1 and `score` <= 5),
  `weighted_score` decimal(5,2) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `non_core_competencies_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `overall_assessments`
--

CREATE TABLE `overall_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appraisal_id` int(11) NOT NULL,
  `performance_assessment_score` decimal(5,2) DEFAULT NULL,
  `core_competencies_score` decimal(5,2) DEFAULT NULL,
  `non_core_competencies_score` decimal(5,2) DEFAULT NULL,
  `overall_total` decimal(5,2) DEFAULT NULL,
  `overall_percentage` decimal(5,2) DEFAULT NULL,
  `overall_rating` int(11) DEFAULT NULL CHECK (`overall_rating` >= 1 and `overall_rating` <= 5),
  `rating_description` varchar(100) DEFAULT NULL,
  `appraiser_comments` text DEFAULT NULL,
  `career_development_plan` text DEFAULT NULL,
  `promotion_assessment` enum('outstanding','suitable','likely_2_3_years','not_ready_3_years','unlikely') DEFAULT 'suitable',
  `appraisee_comments` text DEFAULT NULL,
  `hod_comments` text DEFAULT NULL,
  `hod_signature_date` date DEFAULT NULL,
  `appraiser_signature_date` date DEFAULT NULL,
  `appraisee_signature_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `appraisal_id` (`appraisal_id`),
  CONSTRAINT `overall_assessments_ibfk_1` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Auto-increment values for tables
--

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `appraisals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `performance_planning`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `training_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `mid_year_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `end_year_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `core_competencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `non_core_competencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `overall_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------
-- Default Login Credentials
-- --------------------------------------------------------
-- Admin: Username: admin, Staff ID: ADMIN001, Password: admin123
-- Appraiser: Username: dr.smith, Staff ID: DOC001, Password: password123
-- Appraisee: Username: nurse.jane, Staff ID: NURSE001, Password: password123
-- --------------------------------------------------------