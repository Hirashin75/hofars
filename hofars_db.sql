-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 16, 2025 at 05:13 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hofars_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assigned_jobs`
--

CREATE TABLE `assigned_jobs` (
  `id` int(11) NOT NULL,
  `report_id` int(11) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `name`) VALUES
(4, 'Kipas Siling/Dinding'),
(2, 'Kunci Bilik'),
(9, 'Lain-lain'),
(7, 'Lampu'),
(5, 'Langsir/Rel Langsir'),
(3, 'Pintu'),
(8, 'Rak/Meja/Almari'),
(6, 'Tingkap'),
(1, 'Tombol Pintu');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `matric_number` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `block_number` varchar(50) DEFAULT NULL,
  `facility` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Assigned','Resolved') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `user_id`, `date`, `time`, `student_name`, `matric_number`, `room_number`, `block_number`, `facility`, `description`, `photo`, `status`, `created_at`) VALUES
(1, 1, '2025-11-03', '10:33:37', 'Harish Safuan', '12231', 'TC215', 'T', 'Kunci Bilik', 'kunci bilik kena ragut', '', 'Assigned', '2025-11-03 02:33:37'),
(2, 7, '2025-11-03', '12:07:22', 'Liyana', '1234567', 'TC121', 'T', 'Pintu', 'aidand', 'uploads/1762142842_gambar_uniform.jpeg', 'Pending', '2025-11-03 04:07:22');

-- --------------------------------------------------------

--
-- Table structure for table `technician_reports`
--

CREATE TABLE `technician_reports` (
  `id` int(11) NOT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `progress_description` text DEFAULT NULL,
  `files` text DEFAULT NULL,
  `status` enum('Submitted','Approved') DEFAULT 'Submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technician_reports`
--

INSERT INTO `technician_reports` (`id`, `technician_id`, `job_description`, `progress_description`, `files`, `status`, `created_at`) VALUES
(1, 5, 'Assigned Job Update', 'gambar', '1762143175_0_gambar_uniform.jpeg', 'Submitted', '2025-11-03 04:12:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','technician','admin') DEFAULT 'student',
  `profile_picture` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `name`, `email`, `password`, `role`, `profile_picture`, `created_at`) VALUES
(1, NULL, 'Mohamad Harish Safuan Bin Noorul Azan', 'harish1@gmail.com', '$2y$10$8RIKBzg9o5obgy2kN7pJWuYsEN5lX1g63C2jqF8DhyclAefI/aBOK', 'student', NULL, '2025-11-03 02:19:07'),
(4, NULL, 'Admin User', 'admin@hofars.com', '$2y$10$kCycirJQgquqLuYAYtZk7.wLdTbkJtp.mzB3/p56JIEqjbif0fl5K', 'admin', NULL, '2025-11-03 02:30:55'),
(5, NULL, 'Technician User', 'tech@hofars.com', '$2y$10$RVSJjMZsj.rNG.pKDjvnJ.BmvhIEODIrOcRg9QuRcIUVPzCfNEnSS', 'technician', NULL, '2025-11-03 02:30:55'),
(6, NULL, 'Hazeeq', 'hazeeq123@gmail.com', '$2y$10$DeiEhPc267S1MFfLVOEdAOrSY/T1..Y1dh.uCZbCoLH/PHEM0Tufm', 'student', NULL, '2025-11-03 03:31:54'),
(7, NULL, 'Liyana', 'liyana@gmail.com', '$2y$10$JaGXL0E/KC3UzDvCFbrGV.L5oWwymnu9n6ATwaph4yPkOwc/xxjE2', 'student', NULL, '2025-11-03 04:04:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assigned_jobs`
--
ALTER TABLE `assigned_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `technician_reports`
--
ALTER TABLE `technician_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assigned_jobs`
--
ALTER TABLE `assigned_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `technician_reports`
--
ALTER TABLE `technician_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assigned_jobs`
--
ALTER TABLE `assigned_jobs`
  ADD CONSTRAINT `assigned_jobs_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`),
  ADD CONSTRAINT `assigned_jobs_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `technician_reports`
--
ALTER TABLE `technician_reports`
  ADD CONSTRAINT `technician_reports_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
