-- Seed Data for Faculty Supervisor Portal (FSP) Module
USE `internship management system`;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `marks_evaluations`;
TRUNCATE TABLE `weekly_reports`;
TRUNCATE TABLE `internships`;
TRUNCATE TABLE `students`;
TRUNCATE TABLE `organizations`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Seed Users (Faculty Supervisors, Focal Persons, Site Supervisors, Students)
INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `role`, `designation`) VALUES
(1, 'Dr. Yousaf', 'yousaf@uoh.edu.pk', '0300-1234567', 'faculty_supervisor', 'Assistant Professor'),
(2, 'Dr. Ikramullah', 'ikramullah@uoh.edu.pk', '0300-7654321', 'faculty_supervisor', 'Associate Professor'),
(3, 'Focal Person IT', 'focal@uoh.edu.pk', '0300-2223334', 'focal_person', 'Lecturer / Internship Focal Person'),
(4, 'Engr. Tariq Mehmood', 'tariq@ptcl.com.pk', '0333-5551234', 'site_supervisor', 'Senior Network Operations Engineer'),
(5, 'Mr. Asim Khan', 'asim@netsol.com', '0345-9876543', 'site_supervisor', 'Principal Software Architect'),
(6, 'Mino Ki', 's23-1234@uoh.edu.pk', '0300-1112223', 'student', 'Student (BS IT)'),
(7, 'Alice Smith', 's23-1111@uoh.edu.pk', '0300-6667778', 'student', 'Student (BS CS)'),
(8, 'Sarah Miller', 's23-2001@uoh.edu.pk', '0300-2210160', 'student', 'Student (BS IT)'),
(9, 'David Jackson', 's23-2002@uoh.edu.pk', '0300-9456658', 'student', 'Student (BS SE)'),
(10, 'Charlotte Brown', 's23-2003@uoh.edu.pk', '0300-6491132', 'student', 'Student (BS CS)');

-- Also ensure user authentication table has FSP-0001 and FSP-0002 with standard password 'password'
INSERT INTO `user` (`u_id`, `u_name`, `u_pass`, `u_type`, `status`) VALUES
(3, 'FSP-0001', '$2y$10$tqeOaFx01f1ivbFVWeD0l.PS1/xQYl3i/Yac4.102RBO26MhwQKQy', 'FSP', 1)
ON DUPLICATE KEY UPDATE `u_name` = 'FSP-0001', `u_pass` = '$2y$10$tqeOaFx01f1ivbFVWeD0l.PS1/xQYl3i/Yac4.102RBO26MhwQKQy', `u_type` = 'FSP', `status` = 1;

INSERT INTO `user` (`u_id`, `u_name`, `u_pass`, `u_type`, `status`) VALUES
(101, 'FSP-0002', '$2y$10$tqeOaFx01f1ivbFVWeD0l.PS1/xQYl3i/Yac4.102RBO26MhwQKQy', 'FSP', 1)
ON DUPLICATE KEY UPDATE `u_name` = 'FSP-0002', `u_pass` = '$2y$10$tqeOaFx01f1ivbFVWeD0l.PS1/xQYl3i/Yac4.102RBO26MhwQKQy', `u_type` = 'FSP', `status` = 1;

-- 2. Seed Organizations
INSERT INTO `organizations` (`org_id`, `org_name`, `address`, `category`, `contact_person_name`, `contact_person_phone`, `contact_person_email`, `contact_person_designation`) VALUES
(1, 'PTCL (Pakistan Telecommunication Company Limited)', 'Haripur Central Exchange, Main G.T Road, Haripur', 'Telecom / Networking', 'Muhammad Bilal', '051-2223344', 'bilal@ptcl.com.pk', 'Regional HR Manager'),
(2, 'NetSol Technologies Ltd.', 'NetSol Avenue, Main IT Park, Islamabad', 'Software / IT Solutions', 'Ayesha Malik', '051-5556677', 'hr@netsol.com', 'Talent Acquisition Lead'),
(3, 'National Institute of Electronics (NIE)', 'Sector H-9, Islamabad', 'Embedded Systems / R&D', 'Kamran Shah', '051-9988776', 'contact@nie.gov.pk', 'Admin & Liaison Officer');

-- 3. Seed Students (Linked to Faculty Supervisors: Dr. Yousaf [user_id=1], Dr. Ikramullah [user_id=2])
INSERT INTO `students` (`student_id`, `user_id`, `roll_no`, `session`, `faculty_supervisor_id`) VALUES
(1, 6, 'S23-1234', 'Fall-2023', 1),
(2, 7, 'S23-1111', 'Fall-2023', 1),
(3, 8, 'S23-2001', 'Spring-2024', 1),
(4, 9, 'S23-2002', 'Fall-2023', 2),
(5, 10, 'S23-2003', 'Spring-2024', 2);

-- 4. Seed Internships
INSERT INTO `internships` (`internship_id`, `student_id`, `org_id`, `site_supervisor_id`, `internship_title`, `duration_weeks`, `start_date`, `end_date`) VALUES
(1, 1, 1, 4, 'Enterprise Network Infrastructure & Cyber Security', 8, '2024-07-01', '2024-08-25'),
(2, 2, 2, 5, 'Full Stack Web & Cloud Application Development', 8, '2024-07-01', '2024-08-25'),
(3, 3, 3, 4, 'IoT Telemetry & Embedded Microcontroller Systems', 6, '2024-07-15', '2024-08-30'),
(4, 4, 2, 5, 'Backend REST APIs & Database Optimization', 8, '2024-07-01', '2024-08-25'),
(5, 5, 1, 4, 'Optical Fiber Backbone & Core Routing Operations', 8, '2024-07-01', '2024-08-25');

-- 5. Seed Weekly Reports (with status, faculty remarks, and revision tracking)
INSERT INTO `weekly_reports` (`report_id`, `internship_id`, `week_number`, `task_description`, `weekly_targets`, `fp_remarks`, `faculty_remarks`, `revision_count`, `status`, `submitted_at`) VALUES
(1, 1, 1, 'Studied enterprise network topology, subnetting schemes, and configured test VLANs on Cisco Catalyst switches.', 'Understand routing protocols (OSPF) and configure interface trunking.', 'Verified and approved by Focal Person.', 'Good solid progress. Proceed to router configuration next week.', 0, 'approved', '2024-07-08 10:00:00'),
(2, 1, 2, 'Implemented Access Control Lists (ACLs) and stateful packet filtering policies on gateway firewalls.', 'Deploy ACL rules to test network segment and log dropped packets.', 'Informed site supervisor.', 'Please elaborate on specific port rules applied and include subnet diagram.', 1, 'needs_improvement', '2024-07-15 11:30:00'),
(3, 2, 1, 'Installed Linux server environment, configured Docker containers for PHP 8.2 and MySQL 8.', 'Build automated CI container scripts for backend test suites.', 'Verified attendance at NetSol office.', 'Clear and comprehensive setup. Approved.', 0, 'approved', '2024-07-08 14:15:00'),
(4, 2, 2, 'Designed normalized database relational schema (3NF) for e-commerce inventory management system.', 'Implement Eloquent ORM models and database migration scripts.', '', 'Awaiting faculty review.', 0, 'submitted', '2024-07-16 09:45:00'),
(5, 3, 1, 'Interfaced temperature and humidity sensors (DHT22) with ESP32 and published data via MQTT.', 'Calibrate sensor polling interval and power management sleep cycles.', '', '', 0, 'submitted', '2024-07-22 12:00:00'),
(6, 4, 1, 'Implemented JWT token authentication middleware and REST endpoints for user authentication.', 'Add rate limiting and refresh token rotation mechanism.', 'Confirmed with site team.', 'Well structured code. Approved.', 0, 'approved', '2024-07-08 15:00:00'),
(7, 5, 1, 'Observed fiber splicing, OTDR testing, and attenuation loss measurement on primary links.', 'Learn how to interpret OTDR event charts and pinpoint fiber breaks.', '', '', 0, 'submitted', '2024-07-08 16:30:00');

-- 6. Seed Marks Evaluations
INSERT INTO `marks_evaluations` (`evaluation_id`, `student_id`, `faculty_supervisor_id`, `session`, `total_marks`, `obtained_marks`, `evaluated_at`) VALUES
(1, 1, 1, 'Fall-2023', 100.00, 88.50, '2024-08-26 11:00:00'),
(2, 2, 1, 'Fall-2023', 100.00, 92.00, '2024-08-26 11:30:00');
