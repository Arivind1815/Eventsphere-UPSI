-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Jul 03, 2025 at 03:07 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eventsphere_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attendance_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `method` enum('qr','captcha','manual') NOT NULL DEFAULT 'qr',
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_time` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `event_date` date NOT NULL,
  `event_end_date` date DEFAULT NULL,
  `event_time` time NOT NULL,
  `event_end_time` time DEFAULT NULL,
  `venue` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `organizer` varchar(100) NOT NULL,
  `contact_info` varchar(100) NOT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `registration_deadline` date DEFAULT NULL,
  `poster_url` varchar(255) DEFAULT NULL,
  `brochure_url` varchar(255) DEFAULT NULL,
  `rules` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `event_date`, `event_end_date`, `event_time`, `event_end_time`, `venue`, `category_id`, `organizer`, `contact_info`, `max_participants`, `is_featured`, `registration_deadline`, `poster_url`, `brochure_url`, `rules`, `created_at`, `updated_at`) VALUES
(20, 'TEST', 'joinlah oii best niii', '2025-06-28', '2025-06-29', '20:00:00', '13:00:00', 'sitc', 1, 'fkmt', '0192939398', NULL, 0, '2025-06-28', NULL, NULL, NULL, '2025-06-23 06:50:43', '2025-06-23 06:50:43'),
(21, '123', '123', '2025-07-03', '2025-07-03', '21:00:00', '22:00:00', '123', 2, '123', '123', 50, 1, '2025-07-03', 'uploads/posters/1751546077_about-img.jpg', NULL, NULL, '2025-07-03 12:34:37', '2025-07-03 12:34:37');

-- --------------------------------------------------------

--
-- Table structure for table `event_categories`
--

CREATE TABLE `event_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_categories`
--

INSERT INTO `event_categories` (`id`, `name`, `description`) VALUES
(1, 'Faculty', 'Events organized by faculty departments'),
(2, 'Club', 'Events organized by student clubs and societies'),
(3, 'College', 'Events organized by residential colleges'),
(4, 'International', 'Events with international scope or participation'),
(5, 'National', 'Events with national scope or participation'),
(6, 'Local', 'Events with local or campus-wide scope');

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('registered','cancelled') NOT NULL DEFAULT 'registered'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_tags`
--

CREATE TABLE `event_tags` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `tag` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_tags`
--

INSERT INTO `event_tags` (`id`, `event_id`, `tag`) VALUES
(31, 21, 'Seminar');

-- --------------------------------------------------------

--
-- Table structure for table `faq`
--

CREATE TABLE `faq` (
  `id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `order_num` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faq`
--

INSERT INTO `faq` (`id`, `question`, `answer`, `category`, `order_num`) VALUES
(1, 'How do I register for an event?', 'You can register for events by browsing the events page, selecting an event of interest, and clicking the \"Register\" button on the event details page.', 'General', 1),
(2, 'How do I earn Champ Points?', 'You earn Champ Points by attending events. Different event categories award different point values. Points are added after an admin verifies your attendance.', 'Points', 5),
(8, 'Can I cancel my event registration?', 'Yes, you can cancel your registration by going to \"My Events\" and clicking the \"Cancel Registration\" button for the specific event.', 'General', 3),
(9, 'do i need to fill up registration form?', 'No automatically it will fill up by your profile details', 'General', 2),
(10, 'What happens if I forget to scan the QR code before the event ends?', 'If you do not scan the QR code before 15 minutes of the event’s end time, your attendance will be marked as absent. In this case, you will not receive any participation points for the event. To ensure your attendance is recorded, please make sure to scan the QR code promptly during the event period.', 'Attendance', 4);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comments` text DEFAULT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('event_reminder','registration_confirmation','attendance_confirmation','points_added','feedback_reminder','system') NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `matric_id` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student') NOT NULL DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `matric_id`, `email`, `phone`, `password`, `role`, `created_at`) VALUES
(1, 'Admin User', 'ADMIN001', 'admin@upsi.edu.my', '0123456789', '123', 'admin', '2025-05-11 05:11:17'),
(9, 'Aravind Raj Al Kumrun', 'D20221101832', 'd20221101832@siswa.upsi.edu.my', '0142709793', 'Ara123', 'student', '2025-05-18 20:59:19'),
(10, 'Kumar', 'D20221101816', 'd20221101816@siswa.upsi.edu.my', '0182991403', 'Kumar1816@', 'student', '2025-05-22 17:41:03'),
(11, 'Arivindhaan', 'D20221101815', 'd20221101815@siswa.upsi.edu.my', '0182991402', 'Arivind1815@', 'student', '2025-07-03 09:59:56');

-- --------------------------------------------------------

--
-- Table structure for table `user_goals`
--

CREATE TABLE `user_goals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `points_goal` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_goals`
--

INSERT INTO `user_goals` (`id`, `user_id`, `semester`, `points_goal`, `created_at`, `updated_at`) VALUES
(6, 11, '2025 Fall', 200, '2025-07-03 12:44:53', '2025-07-03 12:44:53');

-- --------------------------------------------------------

--
-- Table structure for table `user_interests`
--

CREATE TABLE `user_interests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `interest` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_interests`
--

INSERT INTO `user_interests` (`id`, `user_id`, `interest`) VALUES
(12, 11, 'Seminar');

-- --------------------------------------------------------

--
-- Table structure for table `user_points`
--

CREATE TABLE `user_points` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `awarded_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `awarded_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_user` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `event_categories`
--
ALTER TABLE `event_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_user` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `event_tags`
--
ALTER TABLE `event_tags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_user` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matric_id` (`matric_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_goals`
--
ALTER TABLE `user_goals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_semester` (`user_id`,`semester`);

--
-- Indexes for table `user_interests`
--
ALTER TABLE `user_interests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_interest` (`user_id`,`interest`);

--
-- Indexes for table `user_points`
--
ALTER TABLE `user_points`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_event` (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `awarded_by` (`awarded_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `event_categories`
--
ALTER TABLE `event_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `event_tags`
--
ALTER TABLE `event_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_goals`
--
ALTER TABLE `user_goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_interests`
--
ALTER TABLE `user_interests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_points`
--
ALTER TABLE `user_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_tags`
--
ALTER TABLE `event_tags`
  ADD CONSTRAINT `event_tags_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_goals`
--
ALTER TABLE `user_goals`
  ADD CONSTRAINT `user_goals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_interests`
--
ALTER TABLE `user_interests`
  ADD CONSTRAINT `user_interests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_points`
--
ALTER TABLE `user_points`
  ADD CONSTRAINT `user_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_points_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_points_ibfk_3` FOREIGN KEY (`awarded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
