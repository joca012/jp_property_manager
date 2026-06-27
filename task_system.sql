-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 26, 2026 at 07:03 PM
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
-- Database: `task_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `naziv` varchar(100) NOT NULL,
  `boja` varchar(20) DEFAULT '#dc3545',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `user_id`, `naziv`, `boja`, `created_at`) VALUES
(2, 1, 'JA', '#dc3545', '2026-06-19 00:05:31');

-- --------------------------------------------------------

--
-- Table structure for table `ciklusi`
--

CREATE TABLE `ciklusi` (
  `id` int(11) NOT NULL,
  `naziv` varchar(255) NOT NULL,
  `opis` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ciklusi`
--

INSERT INTO `ciklusi` (`id`, `naziv`, `opis`, `created_at`) VALUES
(1, '2-2-2-4', 'Dve prve, dve druge, dve treće, četiri dana slobodno', '2026-06-17 16:38:44');

-- --------------------------------------------------------

--
-- Table structure for table `ciklus_stavke`
--

CREATE TABLE `ciklus_stavke` (
  `id` int(11) NOT NULL,
  `ciklus_id` int(11) NOT NULL,
  `sablon_id` int(11) DEFAULT NULL,
  `redosled` int(11) NOT NULL,
  `broj_dana` int(11) NOT NULL DEFAULT 1,
  `tip` enum('smena','slobodno') NOT NULL DEFAULT 'smena'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ciklus_stavke`
--

INSERT INTO `ciklus_stavke` (`id`, `ciklus_id`, `sablon_id`, `redosled`, `broj_dana`, `tip`) VALUES
(1, 1, 1, 1, 2, 'smena'),
(2, 1, 2, 2, 2, 'smena'),
(3, 1, 3, 3, 2, 'smena'),
(4, 1, NULL, 4, 4, 'slobodno');

-- --------------------------------------------------------

--
-- Table structure for table `sabloni`
--

CREATE TABLE `sabloni` (
  `id` int(11) NOT NULL,
  `naziv` varchar(255) NOT NULL,
  `kategorija` enum('JA','EPS','PIDRA','PLAC','SAFE_LIFE') NOT NULL,
  `opis1` varchar(255) NOT NULL,
  `opis2` text DEFAULT NULL,
  `vreme` time DEFAULT NULL,
  `trajanje` int(11) NOT NULL DEFAULT 30,
  `tip` enum('obaveza','smena') NOT NULL DEFAULT 'obaveza',
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sabloni`
--

INSERT INTO `sabloni` (`id`, `naziv`, `kategorija`, `opis1`, `opis2`, `vreme`, `trajanje`, `tip`, `user_id`) VALUES
(1, 'I smena 8h', 'EPS', 'I smena 8h', '07-15', '07:00:00', 480, 'smena', 1),
(2, 'II smena 8h', 'EPS', 'II smena 8h', '15-23', '15:00:00', 480, 'smena', 1),
(3, 'III smena 8h', 'EPS', 'III smena 8h', '23-07', '23:00:00', 480, 'smena', 1),
(4, 'Sednica', 'PIDRA', '', '', NULL, 60, 'obaveza', 1),
(5, 'ST', 'SAFE_LIFE', 'Sales Trening', 'Osnovni seminar u Hajatu', '08:00:00', 480, 'obaveza', 1),
(6, 'KT', 'SAFE_LIFE', 'Kondicioni trening', '', NULL, 90, 'obaveza', 1),
(7, 'SM1', 'SAFE_LIFE', 'Sales Meeting 1', '', NULL, 90, 'obaveza', 1),
(8, 'SM2', 'SAFE_LIFE', 'Sales Meeting 2', '', NULL, 90, 'obaveza', 1),
(9, 'SM3', 'SAFE_LIFE', 'Sales Meeting 3', '', NULL, 90, 'obaveza', 1),
(10, 'Termin sa klijentom/saradnikom', 'SAFE_LIFE', 'Pristupni/prodajni razgovor', '', NULL, 45, 'obaveza', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `kategorija` enum('JA','EPS','PIDRA','PLAC','SAFE_LIFE') NOT NULL,
  `datum` date DEFAULT NULL,
  `vreme` time DEFAULT NULL,
  `opis1` varchar(255) NOT NULL,
  `opis2` text DEFAULT NULL,
  `trajanje` int(11) NOT NULL DEFAULT 30,
  `status` enum('todo','zakazano','zavrseno','propusteno','obrisano') NOT NULL DEFAULT 'todo',
  `sablon_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `kategorija`, `datum`, `vreme`, `opis1`, `opis2`, `trajanje`, `status`, `sablon_id`, `created_at`, `user_id`) VALUES
(13, 'EPS', '2026-06-14', '07:00:00', 'I smena 8h', '07-15', 480, 'zavrseno', NULL, '2026-06-17 16:54:20', 1),
(14, 'EPS', '2026-06-15', '07:00:00', 'I smena 8h', '07-15', 480, 'zavrseno', NULL, '2026-06-17 16:54:20', 1),
(15, 'EPS', '2026-06-16', '15:00:00', 'II smena 8h', '15-23', 480, 'zavrseno', NULL, '2026-06-17 16:54:20', 1),
(16, 'EPS', '2026-06-17', '15:00:00', 'II smena 8h', '15-23', 480, 'zavrseno', NULL, '2026-06-17 16:54:20', 1),
(17, 'EPS', '2026-06-18', '23:00:00', 'III smena 8h', '23-07', 480, 'zavrseno', NULL, '2026-06-17 16:54:20', 1),
(18, 'EPS', '2026-06-19', '23:00:00', 'III smena 8h', '23-07', 480, 'zavrseno', NULL, '2026-06-17 16:54:20', 1),
(19, 'EPS', '2026-06-24', '07:00:00', 'I smena 8h', '07-15', 480, 'zavrseno', NULL, '2026-06-17 16:54:20', 1),
(20, 'EPS', '2026-06-25', '07:00:00', 'I smena 8h', '07-15', 480, 'zavrseno', NULL, '2026-06-17 16:54:20', 1),
(21, 'EPS', '2026-06-26', '15:00:00', 'II smena 8h', '15-23', 480, 'zakazano', NULL, '2026-06-17 16:54:20', 1),
(22, 'EPS', '2026-06-27', '15:00:00', 'II smena 8h', '15-23', 480, 'zakazano', NULL, '2026-06-17 16:54:20', 1),
(23, 'EPS', '2026-06-28', '23:00:00', 'III smena 8h', '23-07', 480, 'zakazano', NULL, '2026-06-17 16:54:20', 1),
(24, 'EPS', '2026-06-29', '23:00:00', 'III smena 8h', '23-07', 480, 'zakazano', NULL, '2026-06-17 16:54:20', 1),
(25, 'JA', '2026-06-23', '12:13:00', 'Nina pasoš', '', 30, 'zavrseno', NULL, '2026-06-17 17:27:27', 1),
(26, 'PIDRA', '2026-06-22', '18:00:00', 'RM 13 Petrovac', '', 60, 'zavrseno', 4, '2026-06-17 17:28:50', 1),
(27, 'PIDRA', '2026-06-22', '19:00:00', 'RM 15 Petrovac', '', 60, 'zavrseno', 4, '2026-06-17 17:29:02', 1),
(28, 'PIDRA', '2026-06-19', '14:00:00', 'Zakazivanje ponovljenih sednica', '', 60, 'zavrseno', NULL, '2026-06-17 18:34:49', 1),
(29, 'PIDRA', '2026-06-19', '16:00:00', 'Računi PO', 'Pakovanje i podela računa u PO', 60, 'zavrseno', NULL, '2026-06-17 18:35:30', 1),
(30, 'PIDRA', '2026-06-19', '15:00:00', 'Računi PT', 'Podela preostalih računa PT', 60, 'zavrseno', NULL, '2026-06-17 18:36:03', 1),
(31, 'PIDRA', '2026-06-18', '09:00:00', 'Zapisnici PO', 'kompletiranje zapisnika, isticanje na ogl. tablu i predaja registratoru.', 180, 'zavrseno', NULL, '2026-06-17 18:36:59', 1),
(32, 'PIDRA', '2026-06-24', '18:00:00', 'Srpsih vladara 242', '', 60, 'zavrseno', 4, '2026-06-17 18:38:54', 1),
(33, 'PIDRA', '2026-06-23', '18:00:00', 'Srpsih vladara 129', '', 60, 'zavrseno', 4, '2026-06-17 18:39:05', 1),
(34, 'PIDRA', '2026-06-24', '19:30:00', 'Srpsih vladara 324, 328', '', 60, 'zavrseno', 4, '2026-06-17 18:39:18', 1),
(35, 'PIDRA', '2026-06-25', '18:00:00', 'Dr R.Ž. Race 2', '', 60, 'zavrseno', 4, '2026-06-17 18:39:45', 1),
(36, 'PIDRA', '2026-06-25', '19:30:00', 'Slobodana Braunovića 15A', '', 60, 'zavrseno', 4, '2026-06-17 18:40:32', 1),
(37, 'PIDRA', '2026-06-23', '19:30:00', 'Petra Dobrnjca 82', '', 60, 'zavrseno', 4, '2026-06-17 18:40:59', 1),
(38, 'PIDRA', '2026-06-27', '07:30:00', 'Pakovanje dokumentacije', '', 60, 'zakazano', NULL, '2026-06-17 18:41:23', 1),
(39, 'PIDRA', NULL, NULL, 'Banka', 'Predaja dokumentacije', 60, 'todo', NULL, '2026-06-17 18:44:26', 1),
(40, 'JA', NULL, NULL, 'Branje trešanja', '', 180, 'obrisano', NULL, '2026-06-17 18:45:03', 1),
(41, 'PLAC', '2026-06-18', '16:00:00', 'Braanje trešanja', '', 180, 'zavrseno', NULL, '2026-06-17 18:45:39', 1),
(42, 'PLAC', NULL, NULL, 'Košenje trave 1. faza', '', 240, 'todo', NULL, '2026-06-17 18:46:33', 1),
(43, 'PLAC', '2026-06-21', '11:00:00', 'Košenje placa 2. faza', '', 240, 'zavrseno', NULL, '2026-06-17 18:46:48', 1),
(44, 'PLAC', '2026-06-26', '09:00:00', 'Završavanje krova šupe', '', 240, 'zakazano', NULL, '2026-06-17 18:47:27', 1),
(45, 'PLAC', NULL, NULL, 'Oblaganje zidova šupe', '', 240, 'todo', NULL, '2026-06-17 18:47:45', 1),
(46, 'PLAC', NULL, NULL, 'Šupa - završni radovi', 'Nasipanje poda, montaža vrata, postavljanje prostirke od linoleuma i prozora i vrata.', 240, 'todo', NULL, '2026-06-17 18:48:47', 1),
(47, 'PLAC', NULL, NULL, 'Izrada polica', '', 240, 'todo', NULL, '2026-06-17 18:49:17', 1),
(48, 'PLAC', NULL, NULL, 'Izmeštanje stvari u šupu', '', 240, 'todo', NULL, '2026-06-17 18:49:45', 1),
(49, 'PLAC', NULL, NULL, 'Izrada boksa za vredan alat', '', 240, 'todo', NULL, '2026-06-17 18:50:16', 1),
(50, 'JA', '2026-07-10', '06:00:00', 'MORE', 'Godišnji odmor sa porodicom - Petrovac na moru CG', 12960, 'zakazano', NULL, '2026-06-17 19:31:15', 1),
(51, 'PLAC', '2026-06-20', '15:00:00', 'Obeležavanje košnica', 'Pronalaženje svih 20 pločica i postavljanje na postojeće.', 90, 'zavrseno', NULL, '2026-06-19 00:25:17', NULL),
(52, 'PLAC', '2026-06-27', '08:30:00', 'Pregled pčela', 'Rasturiti neuspeli roj; proveriti matice i legla svuda; prepakovati ramove', 120, 'zakazano', NULL, '2026-06-19 00:26:22', NULL),
(53, 'PIDRA', '2026-06-20', '10:00:00', 'Kosančićeva 7 - plafonjera', 'Prijaviti Draganu da sredi. Visok prioritet.', 15, 'zavrseno', NULL, '2026-06-19 21:39:07', NULL),
(54, 'PIDRA', '2026-06-20', '10:30:00', 'Draško osiguranje', 'POtpisivanje polisa', 30, 'zavrseno', NULL, '2026-06-19 21:40:01', NULL),
(55, 'PIDRA', NULL, NULL, 'Priprema sednica', '', 210, 'obrisano', NULL, '2026-06-21 11:15:46', NULL),
(56, 'PIDRA', '2026-07-06', '09:00:00', 'Konačnost odluka', 'kosančićeva 7  i T. Čaršija 10', 30, 'zakazano', NULL, '2026-06-23 19:07:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `ime` varchar(100) NOT NULL,
  `role` enum('admin','user','readonly') DEFAULT 'user',
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `ime`, `role`, `active`, `created_at`) VALUES
(1, 'admin', '$2y$10$1Y6CUZOioCED8/6e.X30jeY1wIOzuS4DShC58s0CRkVsD.uNzTXxm', 'Jovica Perić', 'admin', 1, '2026-06-19 00:04:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ciklusi`
--
ALTER TABLE `ciklusi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ciklus_stavke`
--
ALTER TABLE `ciklus_stavke`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ciklus_stavke_ciklus` (`ciklus_id`),
  ADD KEY `fk_ciklus_stavke_sablon` (`sablon_id`);

--
-- Indexes for table `sabloni`
--
ALTER TABLE `sabloni`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `datum` (`datum`),
  ADD KEY `status` (`status`),
  ADD KEY `kategorija` (`kategorija`),
  ADD KEY `fk_tasks_sablon` (`sablon_id`),
  ADD KEY `idx_tasks_user` (`user_id`),
  ADD KEY `idx_tasks_user_status` (`user_id`,`status`),
  ADD KEY `idx_tasks_user_datum` (`user_id`,`datum`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ciklusi`
--
ALTER TABLE `ciklusi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ciklus_stavke`
--
ALTER TABLE `ciklus_stavke`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sabloni`
--
ALTER TABLE `sabloni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ciklus_stavke`
--
ALTER TABLE `ciklus_stavke`
  ADD CONSTRAINT `fk_ciklus_stavke_ciklus` FOREIGN KEY (`ciklus_id`) REFERENCES `ciklusi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ciklus_stavke_sablon` FOREIGN KEY (`sablon_id`) REFERENCES `sabloni` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_sablon` FOREIGN KEY (`sablon_id`) REFERENCES `sabloni` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
