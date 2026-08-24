-- Database Schema for Internship Management System (IMS)
-- University of Haripur (UOH)

CREATE DATABASE IF NOT EXISTS `internship management system` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `internship management system`;

-- Disable foreign keys temporarily during drop/creation
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Table `user` (Authentication & Basic User Info)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `u_id` INT AUTO_INCREMENT PRIMARY KEY,
  `u_name` VARCHAR(50) NOT NULL UNIQUE,
  `u_pass` VARCHAR(255) NOT NULL,
  `u_type` VARCHAR(10) NOT NULL, -- 'STD' (Student), 'FP' (Focal Person), 'FSP' (Faculty Supervisor)
  `status` TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Table `user_profile` (Student/User Personal Details)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_profile`;
CREATE TABLE `user_profile` (
  `u_p_id` INT AUTO_INCREMENT PRIMARY KEY,
  `u_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `fname` VARCHAR(100) NOT NULL,
  `cnic` VARCHAR(20) NOT NULL,
  `cell_no` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `rollno_Empno` VARCHAR(50) NOT NULL,
  `address` TEXT,
  `city` VARCHAR(100),
  CONSTRAINT fk_user_profile_user FOREIGN KEY (`u_id`) REFERENCES `user` (`u_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Table `user_semester_detail` (Student Academic Info)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_semester_detail`;
CREATE TABLE `user_semester_detail` (
  `u_s_d_id` INT AUTO_INCREMENT PRIMARY KEY,
  `rollno` VARCHAR(50) NOT NULL,
  `session` VARCHAR(50) NOT NULL,
  `semester` VARCHAR(50) NOT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `program` VARCHAR(100) DEFAULT NULL,
  `letter_approved` TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Table `user_intern_report` (Student Submitted Internship Reports)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_intern_report`;
CREATE TABLE `user_intern_report` (
  `u_in_r_id` INT AUTO_INCREMENT PRIMARY KEY,
  `rollno` VARCHAR(50) NOT NULL,
  `session` VARCHAR(50) NOT NULL,
  `semester` VARCHAR(50) NOT NULL,
  `report_detail` TEXT NOT NULL,
  `report_ref_img` VARCHAR(255) DEFAULT NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Table `user_internship_marks` (Final/Assigned Internship Grades)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_internship_marks`;
CREATE TABLE `user_internship_marks` (
  `u_i_id` INT AUTO_INCREMENT PRIMARY KEY,
  `rollno` VARCHAR(50) NOT NULL,
  `intern_total_obt_marks` DECIMAL(5,2) DEFAULT 0.00,
  `total_marks` DECIMAL(5,2) DEFAULT 0.00,
  `remarks` TEXT DEFAULT NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Table `assign_faculty_supervisor` (Focal Person mappings for Student -> Supervisor)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `assign_faculty_supervisor`;
CREATE TABLE `assign_faculty_supervisor` (
  `a_f_s_id` INT AUTO_INCREMENT PRIMARY KEY,
  `rollno` VARCHAR(50) NOT NULL,
  `u_id` INT NOT NULL, -- Supervisor ID
  `status` TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_assign_sup_user FOREIGN KEY (`u_id`) REFERENCES `user` (`u_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ========================================================
-- TABLES BELOW CORRESPOND TO THE FACULTY SUPERVISOR PORTAL
-- ========================================================

-- --------------------------------------------------------
-- 7. Table `users` (Faculty-side Users)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL,
  `role` VARCHAR(50) NOT NULL, -- 'faculty_supervisor', 'focal_person', 'site_supervisor', 'student'
  `designation` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 8. Table `organizations` (Companies offering Internships)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations` (
  `org_id` INT AUTO_INCREMENT PRIMARY KEY,
  `org_name` VARCHAR(150) NOT NULL,
  `address` TEXT,
  `category` VARCHAR(100) DEFAULT NULL,
  `contact_person_name` VARCHAR(100) DEFAULT NULL,
  `contact_person_phone` VARCHAR(20) DEFAULT NULL,
  `contact_person_email` VARCHAR(100) DEFAULT NULL,
  `contact_person_designation` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 9. Table `students` (Faculty-side Student Mappings)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `student_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `roll_no` VARCHAR(50) NOT NULL UNIQUE,
  `session` VARCHAR(50) NOT NULL,
  `faculty_supervisor_id` INT DEFAULT NULL,
  CONSTRAINT fk_students_users FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT fk_students_supervisor FOREIGN KEY (`faculty_supervisor_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 10. Table `internships` (Student Internships details)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `internships`;
CREATE TABLE `internships` (
  `internship_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `org_id` INT NOT NULL,
  `site_supervisor_id` INT DEFAULT NULL,
  `internship_title` VARCHAR(150) DEFAULT NULL,
  `duration_weeks` INT DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  CONSTRAINT fk_internships_student FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT fk_internships_org FOREIGN KEY (`org_id`) REFERENCES `organizations` (`org_id`) ON DELETE CASCADE,
  CONSTRAINT fk_internships_site FOREIGN KEY (`site_supervisor_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 11. Table `weekly_reports` (Faculty-side Weekly report reviews)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `weekly_reports`;
CREATE TABLE `weekly_reports` (
  `report_id` INT AUTO_INCREMENT PRIMARY KEY,
  `internship_id` INT NOT NULL,
  `week_number` INT NOT NULL,
  `task_description` TEXT DEFAULT NULL,
  `weekly_targets` TEXT DEFAULT NULL,
  `fp_remarks` TEXT DEFAULT NULL,
  `faculty_remarks` TEXT DEFAULT NULL,
  `revision_count` INT DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'submitted', -- 'submitted', 'approved', 'rejected', 'needs_improvement'
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_weekly_reports_internship FOREIGN KEY (`internship_id`) REFERENCES `internships` (`internship_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 12. Table `marks_evaluations` (Faculty-side detailed evaluations)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `marks_evaluations`;
CREATE TABLE `marks_evaluations` (
  `evaluation_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `faculty_supervisor_id` INT NOT NULL,
  `session` VARCHAR(50) NOT NULL,
  `total_marks` DECIMAL(5,2) DEFAULT 100.00,
  `obtained_marks` DECIMAL(5,2) DEFAULT 0.00,
  `evaluated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_marks_eval_student FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT fk_marks_eval_sup FOREIGN KEY (`faculty_supervisor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Re-enable foreign keys
SET FOREIGN_KEY_CHECKS = 1;
