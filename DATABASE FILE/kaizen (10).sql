-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 19, 2025 at 11:35 AM
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
-- Database: `kaizen`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `resource_type` varchar(50) DEFAULT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `module`, `resource_type`, `resource_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'user_registered', 'security', NULL, NULL, NULL, '{\"user_id\":4,\"email\":\"Shadirmazeen167@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 09:05:04'),
(2, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":4,\"email\":\"Shadirmazeen167@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 09:05:27'),
(3, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":2,\"email\":\"christine\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 09:17:38'),
(4, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":4,\"email\":\"Shadirmazeen167@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 09:19:30'),
(5, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":2,\"email\":\"christine\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-22 09:59:46'),
(6, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":4,\"email\":\"Shadirmazeen167@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 07:28:43'),
(7, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":2,\"email\":\"christine\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:13:58'),
(8, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":2,\"email\":\"christine\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:21:54'),
(9, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":2,\"email\":\"christine\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:45:14'),
(10, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"superadmin\",\"reason\":\"user_not_found\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:47:29'),
(11, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"superadmin\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:48:28'),
(12, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"superadmin\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:48:46'),
(13, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"superadmin@hotel.local\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:49:23'),
(14, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"superadmin\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:50:32'),
(15, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"superadmin\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:50:55'),
(16, NULL, 'login_attempt_locked_account', 'security', NULL, NULL, NULL, '{\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:52:42'),
(17, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"superadmin\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:54:44'),
(18, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":2,\"email\":\"christine\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:54:50'),
(19, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":2,\"email\":\"christine\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:54:51'),
(20, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":2,\"email\":\"christine\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:54:52'),
(21, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"superadmin\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:56:17'),
(22, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":2,\"email\":\"christine\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 08:59:59'),
(23, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":3,\"email\":\"harryden\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 10:37:00'),
(24, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 10:44:29'),
(25, 6, 'employee_user_created', 'users', 'user', 7, NULL, '{\"emp_id\":14,\"staff_type\":2,\"username\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:09:32'),
(26, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:10:59'),
(27, 7, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"Shadirmazeen167@gmail.com\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:11:55'),
(28, 7, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"shadirmazeen167@gmail.com\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:12:11'),
(29, 7, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"Shadirmazeen167\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:12:39'),
(30, 7, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"Shadirmazeen167\",\"reason\":\"invalid_password\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:12:47'),
(31, 7, 'user_registered', 'security', NULL, NULL, NULL, '{\"user_id\":8,\"email\":\"Shadirmazeen167@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:13:15'),
(32, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":8,\"email\":\"Shadirmazeen167\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:13:25'),
(33, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:16:00'),
(34, 6, 'employee_user_created', 'users', 'user', 9, NULL, '{\"emp_id\":15,\"staff_type\":1,\"username\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:21:44'),
(35, 6, 'employee_user_created', 'users', 'user', 10, NULL, '{\"emp_id\":16,\"staff_type\":3,\"username\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:23:40'),
(36, 6, 'employee_user_created', 'users', 'user', 11, NULL, '{\"emp_id\":17,\"staff_type\":4,\"username\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-23 11:25:07'),
(37, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:13:21'),
(38, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:14:51'),
(39, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:17:21'),
(40, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:18:01'),
(41, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:18:26'),
(42, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:19:14'),
(43, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":8,\"email\":\"shadirmazeen167\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:20:00'),
(44, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:21:36'),
(45, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:21:56'),
(46, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:24:17'),
(47, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:24:34'),
(48, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:27:00'),
(49, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:27:52'),
(50, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:30:46'),
(51, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:33:38'),
(52, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:33:51'),
(53, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:34:02'),
(54, 10, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"admin\",\"reason\":\"user_not_found\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:36:28'),
(55, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:36:48'),
(56, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:36:57'),
(57, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:37:06'),
(58, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:37:16'),
(59, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:38:25'),
(60, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:38:48'),
(61, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:40:07'),
(62, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:41:41'),
(63, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:42:36'),
(64, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:45:58'),
(65, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:45:59'),
(66, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:46:07'),
(67, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:46:23'),
(68, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:47:44'),
(69, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:48:39'),
(70, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:50:43'),
(71, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:50:49'),
(72, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:51:56'),
(73, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:52:18'),
(74, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:52:35'),
(75, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:52:36'),
(76, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:52:36'),
(77, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:52:53'),
(78, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 07:54:23'),
(79, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 08:11:17'),
(80, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 08:12:41'),
(81, 6, 'branch.created', 'branches', '6', NULL, '2', '{\"branch_name\":\"Kandy\",\"branch_code\":\"002\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 08:18:51'),
(82, 6, 'branch.created', 'branches', '6', NULL, '3', '{\"branch_name\":\"cmb\",\"branch_code\":\"003\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 08:48:52'),
(83, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:24:35'),
(84, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:24:52'),
(85, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:25:03'),
(86, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:25:46'),
(87, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:29:10'),
(88, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:31:09'),
(89, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:32:00'),
(90, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:35:54'),
(91, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:35:55'),
(92, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:35:56'),
(93, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:38:37'),
(94, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:42:08'),
(95, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 09:48:44'),
(96, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 10:12:07'),
(97, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 10:16:15'),
(98, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 10:19:32'),
(99, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-13 10:19:47'),
(100, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:00:40'),
(101, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:04:30'),
(102, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:04:44'),
(103, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:04:55'),
(104, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:05:06'),
(105, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":8,\"email\":\"Shadirmazeen167\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:05:28'),
(106, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:05:44'),
(107, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:06:22'),
(108, 6, 'branch.created', 'branches', 'branch', 2, NULL, '{\"branch_name\":\"Kandy\",\"branch_code\":\"KAN\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:24:38'),
(109, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:32:09'),
(110, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:32:57'),
(111, 6, 'room.created_for_branch', 'branches', 'room', 28, NULL, '{\"branch_id\":2,\"room_no\":\"K-001\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 13:39:27'),
(112, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 14:00:57'),
(113, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 14:05:15'),
(114, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 14:12:57'),
(115, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 14:13:12'),
(116, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 14:13:37'),
(117, 6, 'branch.updated', 'branches', 'branch', 1, '{\"branch_id\":1,\"branch_name\":\"Main Branch\",\"branch_code\":\"MAIN\",\"address\":\"Main Hotel Address\",\"city\":\"City\",\"state\":\"State\",\"country\":\"Country\",\"postal_code\":\"00000\",\"phone\":null,\"email\":null,\"manager_name\":null,\"manager_contact\":null,\"total_rooms\":0,\"status\":\"active\",\"created_at\":\"2025-12-13 18:53:51\",\"updated_at\":\"2025-12-13 18:53:51\"}', '{\"branch_name\":\"Colombo Branch - Head Branch\",\"branch_code\":\"CMBH\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-13 14:33:08'),
(118, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 04:11:24'),
(119, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 04:19:50'),
(120, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 04:40:41'),
(121, 6, 'role_permissions_updated', 'roles', 'role', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 04:42:34'),
(122, 6, 'role_permissions_updated', 'roles', 'role', 7, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 04:45:35'),
(123, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 04:45:44'),
(124, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 04:46:02'),
(125, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 04:57:31'),
(126, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 04:58:32'),
(127, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:18:10'),
(128, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:18:21'),
(129, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:47:41'),
(130, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:47:54'),
(131, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:48:05'),
(132, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:48:18'),
(133, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:48:33'),
(134, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:52:04'),
(135, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:54:20'),
(136, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 05:54:36'),
(137, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 06:02:35'),
(138, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 06:02:44'),
(139, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 06:02:58'),
(140, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 06:14:39'),
(141, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 06:26:07'),
(142, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 06:26:40'),
(143, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 06:46:07'),
(144, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 06:49:25'),
(145, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 06:50:56'),
(146, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":8,\"email\":\"Shadirmazeen167\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:02:18'),
(147, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":8,\"email\":\"Shadirmazeen167\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:03:10'),
(148, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:08:36'),
(149, NULL, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":8,\"email\":\"Shadirmazeen167\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:17:59'),
(150, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:18:12'),
(151, 6, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"Shadirmazeen167\",\"reason\":\"user_not_found\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:19:43'),
(152, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:19:47'),
(153, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:25:17'),
(154, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:25:25'),
(155, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:25:32'),
(156, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:25:40'),
(157, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:26:38'),
(158, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:26:45'),
(159, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:26:56'),
(160, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:31:31'),
(161, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:31:36'),
(162, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"lishanid\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:31:41'),
(163, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:32:57'),
(164, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:33:02'),
(165, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 07:46:50'),
(166, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 08:01:15'),
(167, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"user_id\":12,\"email\":\"rishahmd\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 08:04:48'),
(168, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"user_id\":12,\"email\":\"rishahmd\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 08:05:56'),
(169, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"user_id\":12,\"email\":\"rishahmd\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 08:09:33'),
(170, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 08:49:53'),
(171, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 08:50:45'),
(172, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 08:58:46'),
(173, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 08:58:57'),
(174, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"harisha\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 10:05:00'),
(175, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 10:05:32'),
(176, 6, 'guest_registered', 'security', NULL, NULL, NULL, '{\"user_id\":13,\"email\":\"harishahamed2607@gmail.com\",\"guest_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 10:25:36'),
(177, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"user_id\":13,\"email\":\"harishahamed2607@gmail.com\",\"auto_login\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 10:25:36'),
(178, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 10:30:00'),
(179, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 10:30:29'),
(180, NULL, 'guest_registered', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"harishahamed2607@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 10:56:18'),
(181, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"harishahamed2607@gmail.com\",\"auto_login\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 10:56:18'),
(182, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 11:00:49'),
(183, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 11:01:14'),
(184, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-14 11:07:30'),
(185, 9, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 14:26:56'),
(186, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 14:28:30'),
(187, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 14:31:30'),
(188, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 14:31:44'),
(189, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 14:32:44'),
(190, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 14:34:09'),
(191, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 14:47:43'),
(192, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 14:58:33'),
(193, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 15:05:13'),
(194, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 15:57:05'),
(195, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 15:58:39'),
(196, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 16:21:21'),
(197, 6, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 16:41:15'),
(198, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 16:53:18'),
(199, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 16:53:39'),
(200, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 17:02:49');
INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `module`, `resource_type`, `resource_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(201, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 17:42:29'),
(202, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 17:53:20'),
(203, 6, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 17:56:27'),
(204, 6, 'service_request_created', 'security', NULL, NULL, NULL, '{\"request_id\":1,\"user_id\":6,\"service_type_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 18:01:25'),
(205, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-15 18:35:22'),
(206, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 13:10:19'),
(207, 6, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 13:55:46'),
(208, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 13:56:14'),
(209, 6, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 13:56:37'),
(210, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 14:41:50'),
(211, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 14:43:20'),
(212, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 14:43:37'),
(213, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 14:52:02'),
(214, 6, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 14:52:10'),
(215, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:02:37'),
(216, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:03:04'),
(217, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:03:56'),
(218, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"branchk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:14:09'),
(219, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:16:39'),
(220, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:23:31'),
(221, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:25:23'),
(222, 6, 'role_permissions_updated', 'roles', 'role', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:30:50'),
(223, 6, 'role_permissions_updated', 'roles', 'role', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:32:13'),
(224, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:33:10'),
(225, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:42:38'),
(226, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 15:44:51'),
(227, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 16:15:47'),
(228, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 16:19:19'),
(229, NULL, 'guest_registered', 'security', NULL, NULL, NULL, '{\"guest_id\":2,\"email\":\"test@mail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 16:48:12'),
(230, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":2,\"email\":\"test@mail.com\",\"auto_login\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 16:48:12'),
(231, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 16:51:19'),
(232, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 16:51:49'),
(233, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 16:52:38'),
(234, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":2,\"email\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 16:53:17'),
(235, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:08:58'),
(236, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":2,\"email\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:15:09'),
(237, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:23:48'),
(238, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:34:19'),
(239, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:36:10'),
(240, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:41:49'),
(241, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:42:56'),
(242, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:48:59'),
(243, 6, 'service_request_completed', 'security', NULL, NULL, NULL, '{\"request_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:49:22'),
(244, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 17:50:19'),
(245, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:04:01'),
(246, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:09:34'),
(247, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:12:36'),
(248, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:15:03'),
(249, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:16:41'),
(250, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:21:57'),
(251, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:23:57'),
(252, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:37:21'),
(253, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:38:30'),
(254, 6, 'service_request_created', 'security', NULL, NULL, NULL, '{\"request_id\":2,\"user_id\":6,\"service_type_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:38:57'),
(255, 6, 'service_request_assigned', 'security', NULL, NULL, NULL, '{\"request_id\":2,\"staff_id\":16}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:39:29'),
(256, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"zaido\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:40:02'),
(257, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:40:43'),
(258, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:48:55'),
(259, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:49:29'),
(260, 6, 'service_request_updated', 'security', NULL, NULL, NULL, '{\"request_id\":2,\"status\":\"in_progress\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:49:44'),
(261, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:50:06'),
(262, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 04:52:00'),
(263, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 05:04:57'),
(264, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 05:09:14'),
(265, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 05:53:01'),
(266, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 05:53:42'),
(267, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 06:26:59'),
(268, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 06:27:29'),
(269, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 06:32:33'),
(270, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 06:33:13'),
(271, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 06:34:50'),
(272, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 06:36:52'),
(273, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 07:01:25'),
(274, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 07:38:13'),
(275, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 07:38:45'),
(276, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 07:42:19'),
(277, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 07:43:25'),
(278, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 07:49:45'),
(279, 6, 'role_permissions_updated', 'roles', 'role', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 07:59:25'),
(280, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 08:06:26'),
(281, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 08:08:33'),
(282, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 08:17:16'),
(283, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 08:33:17'),
(284, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 08:39:11'),
(285, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 08:52:21'),
(286, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:00:26'),
(287, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:01:15'),
(288, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:02:46'),
(289, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:11:42'),
(290, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:13:59'),
(291, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"rish\",\"reason\":\"user_not_found\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:20:41'),
(292, NULL, 'login_failed', 'security', NULL, NULL, NULL, '{\"email\":\"rish\",\"reason\":\"user_not_found\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:20:41'),
(293, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:20:47'),
(294, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:28:07'),
(295, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:29:55'),
(296, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:29:57'),
(297, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:31:20'),
(298, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:34:03'),
(299, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:35:44'),
(300, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 13:48:19'),
(301, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 14:04:34'),
(302, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 14:04:34'),
(303, 6, 'branch.updated', 'branches', 'branch', 1, '{\"branch_id\":1,\"branch_name\":\"Colombo Branch - Head Branch\",\"branch_code\":\"CMBH\",\"address\":\"Marine Drive\",\"city\":\"Colombo\",\"state\":\"Western\",\"country\":\"Sri Lanka\",\"postal_code\":\"20000\",\"phone\":\"0761321604\",\"email\":\"cmbheadoffice@gmail.com\",\"manager_name\":\"Shadir\",\"manager_contact\":\"0772118900\",\"total_rooms\":0,\"status\":\"active\",\"created_at\":\"2025-12-13 18:53:51\",\"updated_at\":\"2025-12-13 20:03:08\"}', '{\"branch_name\":\"Colombo Branch - Head Branch\",\"branch_code\":\"CMB\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 14:05:12'),
(304, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 14:13:02'),
(305, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 14:13:12'),
(306, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"admincmb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 14:19:12'),
(307, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 14:26:07'),
(308, 9, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":9,\"email\":\"admincmb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 14:55:43'),
(309, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 14:56:20'),
(310, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:30:26'),
(311, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:32:41'),
(312, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:35:06'),
(313, NULL, 'service_request_created', 'security', NULL, NULL, NULL, '{\"request_id\":3,\"user_id\":null,\"service_type_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:47:00'),
(314, NULL, 'service_request_created', 'security', NULL, NULL, NULL, '{\"request_id\":4,\"guest_id\":1,\"service_type_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:48:52'),
(315, NULL, 'service_request_created', 'security', NULL, NULL, NULL, '{\"request_id\":5,\"guest_id\":1,\"service_type_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:53:59'),
(316, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:54:18'),
(317, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"cmbhousekeeping\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:54:41'),
(318, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"cmbreceptionist\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:54:59'),
(319, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:55:39'),
(320, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 15:56:36'),
(321, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:02:27'),
(322, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:02:32'),
(323, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:02:44'),
(324, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:03:24'),
(325, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:15:33'),
(326, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:15:51'),
(327, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:17:51'),
(328, 6, 'role_permissions_updated', 'roles', 'role', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:20:52'),
(329, 6, 'role_permissions_updated', 'roles', 'role', 7, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:21:44'),
(330, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:27:14'),
(331, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:29:07'),
(332, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:52:11'),
(333, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 16:53:07'),
(334, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 17:28:38'),
(335, 6, 'guest_registered', 'security', NULL, NULL, NULL, '{\"guest_id\":3,\"email\":\"shadirmazeen167@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 17:49:18'),
(336, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":3,\"email\":\"shadirmazeen167@gmail.com\",\"auto_login\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 17:49:18'),
(337, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 17:51:22'),
(338, 6, 'promotion.created', 'promotions', 'promotion', 7, NULL, '{\"branch_id\":1,\"promotion_code\":\"NEWYEAR30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 17:52:22'),
(339, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:15:29'),
(340, 6, 'guest_registered', 'security', NULL, NULL, NULL, '{\"guest_id\":4,\"email\":\"shadirmazeen167@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:20:35'),
(341, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":4,\"email\":\"shadirmazeen167@gmail.com\",\"auto_login\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:20:35'),
(342, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:22:14'),
(343, NULL, 'guest_registered', 'security', NULL, NULL, NULL, '{\"guest_id\":5,\"email\":\"shadirmazeen167@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:27:12'),
(344, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:30:08'),
(345, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:32:21'),
(346, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:37:59'),
(347, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:40:42'),
(348, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:41:49'),
(349, NULL, 'service_request_created', 'security', NULL, NULL, NULL, '{\"request_id\":6,\"guest_id\":1,\"service_type_id\":18}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:54:12'),
(350, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:55:16'),
(351, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"cmbhousekeeping\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:56:48'),
(352, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"cmbhousekeeping\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:56:55'),
(353, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"cmbhousekeeping\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:56:56'),
(354, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:57:07'),
(355, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 18:58:06'),
(356, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:01:08'),
(357, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:01:54'),
(358, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:07:36'),
(359, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:09:52'),
(360, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:15:28'),
(361, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:16:20'),
(362, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:17:18'),
(363, NULL, 'service_request_created', 'security', NULL, NULL, NULL, '{\"request_id\":7,\"guest_id\":1,\"service_type_id\":14}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:18:07'),
(364, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:18:24'),
(365, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"cmbhousekeeping\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:20:49'),
(366, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:25:51'),
(367, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:27:18'),
(368, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"cmbhousekeeping\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:27:35'),
(369, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 19:27:50'),
(370, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 05:55:43'),
(371, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 05:58:01'),
(372, 6, 'service_type_deleted', 'security', NULL, NULL, NULL, '{\"service_type_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 05:58:34'),
(373, 6, 'service_type_created', 'security', NULL, NULL, NULL, '{\"service_type_id\":23,\"service_name\":\"Room Cleaning\",\"branch_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 05:59:05'),
(374, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 05:59:26'),
(375, NULL, 'service_request_created', 'security', NULL, NULL, NULL, '{\"request_id\":8,\"guest_id\":1,\"service_type_id\":23}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:10:28'),
(376, NULL, 'service_request_auto_assigned', 'security', NULL, NULL, NULL, '{\"request_id\":8,\"staff_id\":16,\"category\":\"housekeeping\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:10:28'),
(377, 10, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":10,\"email\":\"cmbhousekeeping\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:10:36'),
(378, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:10:54'),
(379, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:15:46'),
(380, NULL, 'service_request_created', 'security', NULL, NULL, NULL, '{\"request_id\":9,\"guest_id\":1,\"service_type_id\":14}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:16:39'),
(381, NULL, 'service_request_auto_assigned', 'security', NULL, NULL, NULL, '{\"request_id\":9,\"staff_id\":17,\"category\":\"transport\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:16:39'),
(382, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:17:23'),
(383, 11, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":11,\"email\":\"cmbconcierge\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:17:24'),
(384, 7, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":7,\"email\":\"cmbreceptionist\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 06:20:00'),
(385, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 07:50:49'),
(386, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 07:58:04'),
(387, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 08:04:52'),
(388, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 08:08:48'),
(389, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 08:15:59'),
(390, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 08:18:59'),
(391, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 09:50:10'),
(392, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 09:51:25'),
(393, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 09:56:42'),
(394, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 09:57:22'),
(395, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 09:57:32'),
(396, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 10:03:23'),
(397, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 10:09:47'),
(398, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 10:10:53');
INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `module`, `resource_type`, `resource_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(399, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 10:12:26'),
(400, NULL, 'guest_login_success', 'security', NULL, NULL, NULL, '{\"guest_id\":1,\"email\":\"rish\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 10:28:29'),
(401, 6, 'login_success', 'security', NULL, NULL, NULL, '{\"user_id\":6,\"email\":\"superadmin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-19 10:30:11');

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `booking_id` int(10) NOT NULL,
  `guest_id` int(10) NOT NULL,
  `room_id` int(10) NOT NULL,
  `meal_package_id` int(11) DEFAULT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `check_in` date DEFAULT NULL,
  `check_out` date NOT NULL,
  `total_price` int(10) NOT NULL,
  `remaining_price` int(10) NOT NULL,
  `advance_payment` decimal(10,2) DEFAULT 0.00,
  `advance_payment_method` enum('cash','card','none') DEFAULT 'none',
  `balance_payment_method` enum('cash','card') DEFAULT NULL,
  `payment_method` enum('cash','card','pending') DEFAULT 'pending',
  `payment_status` tinyint(1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `status` enum('pending','confirmed','checked_in','checked_out','cancelled') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`booking_id`, `guest_id`, `room_id`, `meal_package_id`, `booking_date`, `check_in`, `check_out`, `total_price`, `remaining_price`, `advance_payment`, `advance_payment_method`, `balance_payment_method`, `payment_method`, `payment_status`, `created_at`, `updated_at`, `created_by`, `modified_by`, `status`) VALUES
(11, 1, 28, NULL, '2025-12-16 16:30:37', '2025-12-17', '2025-12-18', 8000, 0, 8000.00, 'none', NULL, 'pending', 1, '2025-12-17 05:52:32', '2025-12-19 10:27:47', NULL, NULL, 'checked_out'),
(12, 1, 29, NULL, '2025-12-17 04:16:07', '2025-12-17', '2025-12-18', 1000, 0, 1000.00, 'none', NULL, 'pending', 1, '2025-12-17 05:52:32', '2025-12-19 10:27:47', NULL, NULL, 'checked_out'),
(14, 1, 2, NULL, '2025-12-17 05:53:32', '2025-12-17', '2025-12-18', 1500, 0, 1500.00, 'none', NULL, 'pending', 1, '2025-12-17 05:53:32', '2025-12-19 10:27:47', NULL, NULL, 'checked_out'),
(15, 1, 5, 5, '2025-12-18 08:32:43', '2025-12-18', '2025-12-19', 2500, 2000, 500.00, 'none', NULL, 'pending', 0, '2025-12-18 08:32:43', '2025-12-19 10:27:47', NULL, NULL, 'pending'),
(16, 1, 7, 5, '2025-12-18 08:39:45', '2025-12-18', '2025-12-19', 3500, 3000, 500.00, 'none', NULL, 'pending', 0, '2025-12-18 08:39:45', '2025-12-19 10:27:47', NULL, NULL, 'pending'),
(17, 1, 7, 6, '2025-12-18 18:38:55', '2025-12-19', '2025-12-20', 5500, 2500, 3000.00, 'none', NULL, 'pending', 0, '2025-12-18 18:38:55', '2025-12-19 10:27:47', NULL, NULL, 'pending'),
(18, 1, 14, 7, '2025-12-18 18:42:34', '2025-12-29', '2025-12-31', 22000, 12000, 10000.00, 'none', NULL, 'pending', 0, '2025-12-18 18:42:34', '2025-12-19 10:27:47', NULL, NULL, 'cancelled'),
(19, 1, 2, 7, '2025-12-18 19:10:41', '2025-12-25', '2025-12-31', 51000, 31000, 20000.00, 'none', NULL, 'pending', 0, '2025-12-18 19:10:41', '2025-12-19 10:27:47', NULL, NULL, 'pending'),
(20, 1, 5, 5, '2025-12-19 08:27:22', '2025-12-19', '2025-12-20', 6650, 0, 6650.00, 'none', NULL, 'pending', 1, '2025-12-19 08:27:22', '2025-12-19 10:27:47', NULL, NULL, 'checked_out'),
(21, 1, 2, 6, '2025-12-19 10:12:04', '2025-12-20', '2025-12-21', 10850, 10850, 0.00, 'none', NULL, 'pending', 0, '2025-12-19 10:12:04', '2025-12-19 10:12:38', NULL, NULL, 'checked_in'),
(22, 1, 5, 6, '2025-12-19 10:29:49', '2025-12-20', '2025-12-21', 8050, 0, 3000.00, 'card', 'cash', 'card', 1, '2025-12-19 10:29:49', '2025-12-19 10:31:18', NULL, NULL, 'checked_out');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `branch_id` int(11) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `branch_code` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `manager_contact` varchar(20) DEFAULT NULL,
  `total_rooms` int(11) DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`branch_id`, `branch_name`, `branch_code`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `email`, `manager_name`, `manager_contact`, `total_rooms`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Colombo Branch - Head Branch', 'CMB', 'Marine Drive', 'Colombo', 'Western', 'Sri Lanka', '20000', '0761321604', 'cmbheadoffice@gmail.com', 'Shadir', '0772118900', 0, 'active', '2025-12-13 13:23:51', '2025-12-18 14:05:12'),
(2, 'Kandy', 'KAN', '300/A,\r\nDehipagoda, Muruthagahamula', 'Kandy', 'Central Province', 'Sri Lanka', '20526', '0761321604', 'harishahamed2607@gmail.com', 'Harish', '0775871221', 0, 'active', '2025-12-13 13:24:38', '2025-12-13 13:24:38');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `log_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_type` enum('guest','user','admin','staff') DEFAULT 'guest',
  `subject` varchar(500) DEFAULT NULL,
  `template_code` varchar(50) DEFAULT NULL,
  `email_type` enum('booking_confirmation','booking_cancellation','checkin_reminder','checkout_reminder','promotion','payment_confirmation','general') DEFAULT 'general',
  `status` enum('pending','sent','failed','bounced') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'booking, promotion, etc.',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID of related record',
  `guest_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `promotion_id` int(11) DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `metadata` text DEFAULT NULL COMMENT 'JSON data for additional info'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `facility_id` int(11) NOT NULL,
  `facility_name` varchar(100) NOT NULL,
  `facility_type` enum('event_hall','conference_room','meeting_room','banquet_hall','pool','gym','spa','business_center','other') NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `floor_level` varchar(20) DEFAULT NULL,
  `area_sqm` decimal(10,2) DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT 0.00,
  `full_day_rate` decimal(10,2) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`facility_id`, `facility_name`, `facility_type`, `capacity`, `description`, `branch_id`, `floor_level`, `area_sqm`, `hourly_rate`, `full_day_rate`, `features`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Grand Ballroom', 'banquet_hall', 500, 'Main event hall with crystal chandeliers and stage', NULL, 'Ground Floor', 600.00, 500.00, 3500.00, 'Stage, Sound System, Lighting, Dance Floor, Catering Kitchen Access, Air Conditioning, WiFi', 'active', '2025-12-18 07:34:29', '2025-12-18 07:34:29'),
(2, 'Executive Conference Room A', 'conference_room', 50, 'Premium boardroom with AV equipment', NULL, '1st Floor', 80.00, 100.00, 700.00, 'Projector, Screen, Video Conference, Whiteboard, WiFi, Air Conditioning', 'active', '2025-12-18 07:34:29', '2025-12-18 07:34:29'),
(3, 'Meeting Room B', 'meeting_room', 20, 'Small meeting room for team discussions', NULL, '1st Floor', 40.00, 50.00, 350.00, 'TV Screen, Whiteboard, WiFi, Air Conditioning', 'active', '2025-12-18 07:34:29', '2025-12-18 07:34:29');

-- --------------------------------------------------------

--
-- Table structure for table `facility_bookings`
--

CREATE TABLE `facility_bookings` (
  `booking_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `booking_reference` varchar(50) NOT NULL,
  `event_name` varchar(200) NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `number_of_guests` int(11) DEFAULT NULL,
  `special_requirements` text DEFAULT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') DEFAULT 'pending',
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `booked_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `facility_bookings`
--

INSERT INTO `facility_bookings` (`booking_id`, `facility_id`, `booking_reference`, `event_name`, `booking_date`, `start_time`, `end_time`, `customer_name`, `customer_email`, `customer_phone`, `number_of_guests`, `special_requirements`, `status`, `total_cost`, `booked_by`, `created_at`, `updated_at`) VALUES
(2, 1, 'FH-2025-00001', 'Award Ceremony', '2025-12-20', '13:00:00', '17:00:00', 'Harish Ahamed', 'harishahamed2607@gmail.com', '0761321604', 450, 'Seating', 'confirmed', 2000.00, 7, '2025-12-19 06:55:58', '2025-12-19 06:55:58');

-- --------------------------------------------------------

--
-- Table structure for table `guests`
--

CREATE TABLE `guests` (
  `guest_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(100) NOT NULL,
  `contact_no` bigint(20) DEFAULT NULL,
  `id_card_type` varchar(100) DEFAULT NULL,
  `id_card_no` varchar(20) DEFAULT NULL,
  `address` varchar(100) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `failed_login_attempts` int(11) DEFAULT 0,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `guests`
--

INSERT INTO `guests` (`guest_id`, `name`, `username`, `email`, `phone`, `password`, `contact_no`, `id_card_type`, `id_card_no`, `address`, `email_verified`, `phone_verified`, `status`, `failed_login_attempts`, `account_locked_until`, `created_at`, `last_login`) VALUES
(1, 'Harish Ahamed', 'rish', 'harishahamed2607@gmail.com', '+94761321604', '$2y$12$BXSptUM6Z6M6dWFhqe38k.CPVG3tmAhFFQLn7pN.WHAVZquDiOMQi', 94761321604, 'National Identity Card', '200208900124', 'Kandy', 0, 0, 'active', 0, NULL, '2025-12-14 10:56:18', '2025-12-19 10:28:29'),
(5, 'Shadir Mazeen', 'shad', 'shadirmazeen167@gmail.com', '0774686066', '$2y$12$b.aUBoBj1eyFV76HhpIp3eXDd0V.55sNla68X.qee2OJ.fXsiwR46', 774686066, NULL, NULL, NULL, 0, 0, 'active', 0, NULL, '2025-12-18 18:27:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `id_card_type`
--

CREATE TABLE `id_card_type` (
  `id_card_type_id` int(11) NOT NULL,
  `id_card_type` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `id_card_type`
--

INSERT INTO `id_card_type` (`id_card_type_id`, `id_card_type`, `is_active`, `created_at`) VALUES
(1, 'National Identity Card', 1, '2025-12-15 14:36:18'),
(2, 'Passport', 1, '2025-12-15 14:36:18'),
(3, 'Driving License', 1, '2025-12-15 14:36:18'),
(4, 'Birth Certificate', 1, '2025-12-15 14:36:18'),
(5, 'Other', 1, '2025-12-15 14:36:18');

-- --------------------------------------------------------

--
-- Table structure for table `meal_packages`
--

CREATE TABLE `meal_packages` (
  `package_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `package_name` varchar(200) NOT NULL,
  `package_description` text DEFAULT NULL,
  `meal_type` enum('breakfast','lunch','dinner','breakfast_dinner','breakfast_lunch_dinner','all_day','custom') DEFAULT 'custom',
  `number_of_meals` int(10) NOT NULL DEFAULT 1,
  `package_price` decimal(10,2) NOT NULL,
  `included_items` text DEFAULT NULL,
  `dietary_options` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meal_packages`
--

INSERT INTO `meal_packages` (`package_id`, `branch_id`, `package_name`, `package_description`, `meal_type`, `number_of_meals`, `package_price`, `included_items`, `dietary_options`, `status`, `created_at`, `updated_at`) VALUES
(5, NULL, 'Bed & Breakfast (BnB)', 'A simple meal package that includes a daily breakfast, suitable for guests who prefer flexible dining arrangements for the rest of the day.', 'breakfast', 1, 1500.00, 'Breakfast buffet or set menu, Tea, coffee, and fresh fruit juice, Bread, pastries, and spreads, Sri Lankan and Continental breakfast items', 'Vegetarian, Vegan, Gluten-free (on request)', 'active', '2025-12-18 08:03:57', '2025-12-18 13:34:14'),
(6, NULL, 'Half Board', 'A meal package that includes two main meals per day, offering convenience and balanced dining for guests staying at the hotel.', 'breakfast_dinner', 2, 3500.00, 'Breakfast buffet or set menu, Dinner buffet or set menu, Tea, coffee, and soft drinks during meals', 'Vegetarian, Vegan, Gluten-free, Halal (on request)', 'active', '2025-12-18 08:03:57', '2025-12-18 13:34:14'),
(7, NULL, 'Full Board', 'A complete meal package that includes all three main meals, providing a full dining experience throughout the day.', 'breakfast_lunch_dinner', 3, 5500.00, 'Breakfast buffet or set menu, Lunch buffet or set menu, Dinner buffet or set menu, Tea, coffee, and selected beverages during meals', 'Vegetarian, Vegan, Gluten-free, Halal, Diabetic-friendly (on request), Allergy-specific meals (with prior notice)', 'active', '2025-12-18 08:03:57', '2025-12-18 13:34:14');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `type` enum('email','push','in_app') NOT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `guest_id`, `type`, `subject`, `message`, `status`, `sent_at`, `created_at`) VALUES
(1, NULL, 1, 'email', 'Service Request Created - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Your service request has been received and is being processed.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #1</p>\r\n                    <p><strong>Service:</strong> Room cleaning</p>\r\n                    <p><strong>Title:</strong> test</p>\r\n                    <p><strong>Status:</strong> Pending</p>\r\n                    <p><strong>Priority:</strong> Normal</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-15 18:01:25'),
(2, NULL, 1, 'email', 'Service Request Completed - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Your service request has been completed.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #1</p>\r\n                    <p><strong>Service:</strong> Room cleaning</p>\r\n                    <p><strong>Title:</strong> test</p>\r\n                    <p><strong>Status:</strong> Completed</p>\r\n                    <p><strong>Priority:</strong> Normal</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-16 17:49:22'),
(3, NULL, 1, 'email', 'Service Request Created - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Your service request has been received and is being processed.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #2</p>\r\n                    <p><strong>Service:</strong> Room cleaning</p>\r\n                    <p><strong>Title:</strong> test</p>\r\n                    <p><strong>Status:</strong> Pending</p>\r\n                    <p><strong>Priority:</strong> Low</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-17 04:38:57'),
(4, NULL, 1, 'email', 'Service Request Assigned - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Your service request has been assigned to our staff.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #2</p>\r\n                    <p><strong>Service:</strong> Room cleaning</p>\r\n                    <p><strong>Title:</strong> test</p>\r\n                    <p><strong>Status:</strong> Assigned</p>\r\n                    <p><strong>Priority:</strong> Low</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-17 04:39:29'),
(5, NULL, 1, 'email', 'Service Request In_progress - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Our staff is currently working on your service request.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #2</p>\r\n                    <p><strong>Service:</strong> Room cleaning</p>\r\n                    <p><strong>Title:</strong> test</p>\r\n                    <p><strong>Status:</strong> In_progress</p>\r\n                    <p><strong>Priority:</strong> Low</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-17 04:49:44'),
(6, NULL, 1, 'email', 'Service Request Created - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Your service request has been received and is being processed.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #5</p>\r\n                    <p><strong>Service:</strong> Room cleaning</p>\r\n                    <p><strong>Title:</strong> Room cleaning</p>\r\n                    <p><strong>Status:</strong> Pending</p>\r\n                    <p><strong>Priority:</strong> High</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-18 15:53:59'),
(7, NULL, 1, 'email', 'Service Request Created - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Your service request has been received and is being processed.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #6</p>\r\n                    <p><strong>Service:</strong> Restaurant Reservation</p>\r\n                    <p><strong>Title:</strong> Dining</p>\r\n                    <p><strong>Status:</strong> Pending</p>\r\n                    <p><strong>Priority:</strong> Low</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-18 18:54:12'),
(8, NULL, 1, 'email', 'Service Request Created - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Your service request has been received and is being processed.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #7</p>\r\n                    <p><strong>Service:</strong> Taxi Service</p>\r\n                    <p><strong>Title:</strong> Pickup taxi</p>\r\n                    <p><strong>Status:</strong> Pending</p>\r\n                    <p><strong>Priority:</strong> Normal</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-18 19:18:07'),
(9, NULL, 1, 'email', 'Service Request Created - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Your service request has been received and is being processed.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #8</p>\r\n                    <p><strong>Service:</strong> Room Cleaning</p>\r\n                    <p><strong>Title:</strong> Room cleaning</p>\r\n                    <p><strong>Status:</strong> Assigned</p>\r\n                    <p><strong>Priority:</strong> Normal</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-19 06:10:28'),
(10, NULL, 1, 'email', 'Service Request Created - Smart Hotel Management System', '<!DOCTYPE html>\r\n    <html>\r\n    <head>\r\n        <meta charset=\"UTF-8\">\r\n        <style>\r\n            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }\r\n            .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }\r\n            .content { background: #F5F5F5; padding: 20px; }\r\n            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }\r\n            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }\r\n        </style>\r\n    </head>\r\n    <body>\r\n        <div class=\"container\">\r\n            <div class=\"header\">\r\n                <h1>Smart Hotel Management System</h1>\r\n                <h2>Service Request Update</h2>\r\n            </div>\r\n            <div class=\"content\">\r\n                <p>Dear Harish Ahamed,</p>\r\n                <p>Your service request has been received and is being processed.</p>\r\n                \r\n                <div class=\"request-details\">\r\n                    <h3>Request Details</h3>\r\n                    <p><strong>Request ID:</strong> #9</p>\r\n                    <p><strong>Service:</strong> Taxi Service</p>\r\n                    <p><strong>Title:</strong> Pickup taxi</p>\r\n                    <p><strong>Status:</strong> Assigned</p>\r\n                    <p><strong>Priority:</strong> Normal</p>\r\n                </div>\r\n                \r\n                <p>Thank you for choosing our hotel!</p>\r\n            </div>\r\n            <div class=\"footer\">\r\n                <p>&copy; 2025 Smart Hotel Management System. All rights reserved.</p>\r\n            </div>\r\n        </div>\r\n    </body>\r\n    </html>', 'pending', NULL, '2025-12-19 06:16:39');

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `preference_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `email_enabled` tinyint(1) DEFAULT 1,
  `push_enabled` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('smtp','email','notification','general') DEFAULT 'general',
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_settings`
--

INSERT INTO `notification_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `is_active`, `updated_at`, `updated_by`) VALUES
(1, 'smtp_host', 'smtp.gmail.com', 'smtp', 1, '2025-12-18 08:36:19', NULL),
(2, 'smtp_port', '587', 'smtp', 1, '2025-12-18 08:36:19', NULL),
(3, 'smtp_username', 'kaizenhotelmanagementsystem@gmail.com', 'smtp', 1, '2025-12-18 08:38:51', NULL),
(4, 'smtp_password', 'upzj dfmx poiw dczk', 'smtp', 1, '2025-12-18 08:38:52', NULL),
(5, 'smtp_encryption', 'tls', 'smtp', 1, '2025-12-18 08:36:19', NULL),
(6, 'smtp_from_email', 'kaizenhotelmanagementsystem@gmail.com', 'smtp', 1, '2025-12-18 08:38:52', NULL),
(7, 'smtp_from_name', 'Kaizen Hotel Management', 'smtp', 1, '2025-12-18 08:36:19', NULL),
(8, 'booking_confirmation_enabled', '1', 'notification', 1, '2025-12-18 08:36:19', NULL),
(9, 'booking_cancellation_enabled', '1', 'notification', 1, '2025-12-18 08:36:19', NULL),
(10, 'checkin_reminder_enabled', '1', 'notification', 1, '2025-12-18 08:36:19', NULL),
(11, 'checkout_reminder_enabled', '1', 'notification', 1, '2025-12-18 08:36:19', NULL),
(12, 'promotion_email_enabled', '1', 'notification', 1, '2025-12-18 08:36:19', NULL),
(13, 'payment_confirmation_enabled', '1', 'notification', 1, '2025-12-18 08:36:19', NULL),
(14, 'checkin_reminder_hours', '24', 'notification', 1, '2025-12-18 08:36:19', NULL),
(15, 'checkout_reminder_hours', '6', 'notification', 1, '2025-12-18 08:36:19', NULL),
(16, 'admin_notification_emails', 'admin@kaizenhotel.com', 'email', 1, '2025-12-18 08:36:19', NULL),
(17, 'booking_notification_copy_admin', '1', 'notification', 1, '2025-12-18 08:36:19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notification_templates`
--

CREATE TABLE `notification_templates` (
  `template_id` int(11) NOT NULL,
  `template_code` varchar(50) NOT NULL,
  `template_name` varchar(200) NOT NULL,
  `template_subject` varchar(500) NOT NULL,
  `template_body` text NOT NULL,
  `template_type` enum('booking','reminder','promotion','payment','general') DEFAULT 'general',
  `variables` text DEFAULT NULL COMMENT 'JSON array of available variables',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_templates`
--

INSERT INTO `notification_templates` (`template_id`, `template_code`, `template_name`, `template_subject`, `template_body`, `template_type`, `variables`, `is_active`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'booking_confirmation', 'Booking Confirmation Email', 'Booking Confirmation - {booking_reference}', '<!DOCTYPE html>\n<html>\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\n    <div style=\"max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;\">\n        <div style=\"text-align: center; padding: 20px 0; background: #4CAF50; color: white; border-radius: 5px 5px 0 0;\">\n            <h1 style=\"margin: 0;\">Kaizen Hotel</h1>\n            <p style=\"margin: 5px 0 0 0;\">Booking Confirmation</p>\n        </div>\n        \n        <div style=\"padding: 20px; background: #f9f9f9;\">\n            <p>Dear <strong>{guest_name}</strong>,</p>\n            \n            <p>Thank you for choosing Kaizen Hotel! Your booking has been confirmed.</p>\n            \n            <div style=\"background: white; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0;\">\n                <h3 style=\"margin-top: 0; color: #4CAF50;\">Booking Details</h3>\n                <p><strong>Booking Reference:</strong> {booking_reference}</p>\n                <p><strong>Room Type:</strong> {room_type}</p>\n                <p><strong>Room Number:</strong> {room_number}</p>\n                <p><strong>Check-in Date:</strong> {check_in_date}</p>\n                <p><strong>Check-out Date:</strong> {check_out_date}</p>\n                <p><strong>Number of Guests:</strong> {num_guests}</p>\n                <p><strong>Total Price:</strong> LKR {total_price}</p>\n                <p><strong>Payment Status:</strong> {payment_status}</p>\n            </div>\n            \n            <p><strong>Important Information:</strong></p>\n            <ul>\n                <li>Check-in time: 2:00 PM</li>\n                <li>Check-out time: 12:00 PM</li>\n                <li>Please bring a valid ID for verification</li>\n            </ul>\n            \n            <p>If you have any questions or need to make changes, please contact us.</p>\n            \n            <p>We look forward to welcoming you!</p>\n            \n            <p>Best regards,<br><strong>Kaizen Hotel Team</strong></p>\n        </div>\n        \n        <div style=\"text-align: center; padding: 15px; background: #f0f0f0; font-size: 12px; color: #666; border-radius: 0 0 5px 5px;\">\n            <p>This is an automated message. Please do not reply to this email.</p>\n            <p>&copy; 2025 Kaizen Hotel. All rights reserved.</p>\n        </div>\n    </div>\n</body>\n</html>', 'booking', NULL, 1, '2025-12-18 08:36:20', '2025-12-18 08:36:20', NULL),
(2, 'checkin_reminder', 'Check-in Reminder Email', 'Check-in Reminder - {booking_reference}', '<!DOCTYPE html>\n<html>\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\n    <div style=\"max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;\">\n        <div style=\"text-align: center; padding: 20px 0; background: #2196F3; color: white; border-radius: 5px 5px 0 0;\">\n            <h1 style=\"margin: 0;\">Kaizen Hotel</h1>\n            <p style=\"margin: 5px 0 0 0;\">Check-in Reminder</p>\n        </div>\n        \n        <div style=\"padding: 20px; background: #f9f9f9;\">\n            <p>Dear <strong>{guest_name}</strong>,</p>\n            \n            <p>This is a friendly reminder that your check-in at Kaizen Hotel is approaching!</p>\n            \n            <div style=\"background: white; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;\">\n                <h3 style=\"margin-top: 0; color: #2196F3;\">Your Booking Details</h3>\n                <p><strong>Booking Reference:</strong> {booking_reference}</p>\n                <p><strong>Check-in Date:</strong> {check_in_date}</p>\n                <p><strong>Check-in Time:</strong> 2:00 PM</p>\n                <p><strong>Room Type:</strong> {room_type}</p>\n                <p><strong>Room Number:</strong> {room_number}</p>\n            </div>\n            \n            <p><strong>What to Bring:</strong></p>\n            <ul>\n                <li>Valid photo ID (passport, driver\'s license, or national ID)</li>\n                <li>Booking confirmation (this email)</li>\n                <li>Payment method (if balance is pending)</li>\n            </ul>\n            \n            <p>We\'re excited to welcome you soon!</p>\n            \n            <p>Best regards,<br><strong>Kaizen Hotel Team</strong></p>\n        </div>\n        \n        <div style=\"text-align: center; padding: 15px; background: #f0f0f0; font-size: 12px; color: #666; border-radius: 0 0 5px 5px;\">\n            <p>&copy; 2025 Kaizen Hotel. All rights reserved.</p>\n        </div>\n    </div>\n</body>\n</html>', 'reminder', NULL, 1, '2025-12-18 08:36:20', '2025-12-18 08:36:20', NULL),
(3, 'checkout_reminder', 'Check-out Reminder Email', 'Check-out Reminder - {booking_reference}', '<!DOCTYPE html>\n<html>\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\n    <div style=\"max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;\">\n        <div style=\"text-align: center; padding: 20px 0; background: #FF9800; color: white; border-radius: 5px 5px 0 0;\">\n            <h1 style=\"margin: 0;\">Kaizen Hotel</h1>\n            <p style=\"margin: 5px 0 0 0;\">Check-out Reminder</p>\n        </div>\n        \n        <div style=\"padding: 20px; background: #f9f9f9;\">\n            <p>Dear <strong>{guest_name}</strong>,</p>\n            \n            <p>We hope you\'ve enjoyed your stay at Kaizen Hotel! This is a reminder about your upcoming check-out.</p>\n            \n            <div style=\"background: white; padding: 15px; border-left: 4px solid #FF9800; margin: 20px 0;\">\n                <h3 style=\"margin-top: 0; color: #FF9800;\">Check-out Information</h3>\n                <p><strong>Booking Reference:</strong> {booking_reference}</p>\n                <p><strong>Check-out Date:</strong> {check_out_date}</p>\n                <p><strong>Check-out Time:</strong> 12:00 PM (Noon)</p>\n                <p><strong>Room Number:</strong> {room_number}</p>\n            </div>\n            \n            <p><strong>Before You Leave:</strong></p>\n            <ul>\n                <li>Please return all room keys at the reception</li>\n                <li>Check your room for any personal belongings</li>\n                <li>Settle any outstanding bills</li>\n                <li>Share your feedback - we\'d love to hear from you!</li>\n            </ul>\n            \n            <p>Thank you for choosing Kaizen Hotel. We hope to see you again soon!</p>\n            \n            <p>Best regards,<br><strong>Kaizen Hotel Team</strong></p>\n        </div>\n        \n        <div style=\"text-align: center; padding: 15px; background: #f0f0f0; font-size: 12px; color: #666; border-radius: 0 0 5px 5px;\">\n            <p>&copy; 2025 Kaizen Hotel. All rights reserved.</p>\n        </div>\n    </div>\n</body>\n</html>', 'reminder', NULL, 1, '2025-12-18 08:36:20', '2025-12-18 08:36:20', NULL),
(4, 'promotion_email', 'Promotion Email', '{promotion_title} - Special Offer at Kaizen Hotel!', '<!DOCTYPE html>\n<html>\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #333;\">\n    <div style=\"max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;\">\n        <div style=\"text-align: center; padding: 20px 0; background: #E91E63; color: white; border-radius: 5px 5px 0 0;\">\n            <h1 style=\"margin: 0;\">Kaizen Hotel</h1>\n            <p style=\"margin: 5px 0 0 0; font-size: 18px;\">🎉 Special Promotion!</p>\n        </div>\n        \n        <div style=\"padding: 20px; background: #f9f9f9;\">\n            <p>Dear Valued Guest,</p>\n            \n            <div style=\"background: white; padding: 20px; border-left: 4px solid #E91E63; margin: 20px 0; text-align: center;\">\n                <h2 style=\"margin-top: 0; color: #E91E63;\">{promotion_title}</h2>\n                <p style=\"font-size: 18px; color: #666;\">{promotion_description}</p>\n                \n                <div style=\"background: #E91E63; color: white; padding: 15px; margin: 20px 0; border-radius: 5px;\">\n                    <p style=\"font-size: 24px; font-weight: bold; margin: 0;\">{discount_display}</p>\n                    <p style=\"margin: 5px 0 0 0;\">Use Code: <strong>{promotion_code}</strong></p>\n                </div>\n                \n                <p><strong>Valid Period:</strong><br>{valid_from} to {valid_to}</p>\n            </div>\n            \n            <p><strong>How to Redeem:</strong></p>\n            <ul>\n                <li>Book your stay through our website or contact us directly</li>\n                <li>Enter promo code: <strong>{promotion_code}</strong></li>\n                <li>Enjoy your special discount!</li>\n            </ul>\n            \n            <p style=\"font-size: 12px; color: #666;\"><em>*Terms and conditions apply. Subject to availability.</em></p>\n            \n            <div style=\"text-align: center; margin: 20px 0;\">\n                <a href=\"{booking_url}\" style=\"background: #E91E63; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;\">Book Now</a>\n            </div>\n            \n            <p>Don\'t miss this exclusive offer!</p>\n            \n            <p>Best regards,<br><strong>Kaizen Hotel Team</strong></p>\n        </div>\n        \n        <div style=\"text-align: center; padding: 15px; background: #f0f0f0; font-size: 12px; color: #666; border-radius: 0 0 5px 5px;\">\n            <p>To unsubscribe from promotional emails, <a href=\"{unsubscribe_url}\">click here</a>.</p>\n            <p>&copy; 2025 Kaizen Hotel. All rights reserved.</p>\n        </div>\n    </div>\n</body>\n</html>', 'promotion', NULL, 1, '2025-12-18 08:36:20', '2025-12-18 08:36:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  `permission_description` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permission_id`, `permission_name`, `permission_description`, `module`, `created_at`) VALUES
(1, 'user.create', 'Create new users', 'users', '2025-11-22 09:00:18'),
(2, 'user.read', 'View users', 'users', '2025-11-22 09:00:18'),
(3, 'user.update', 'Update user information', 'users', '2025-11-22 09:00:18'),
(4, 'user.delete', 'Delete users', 'users', '2025-11-22 09:00:18'),
(5, 'user.manage_roles', 'Assign/remove user roles', 'users', '2025-11-22 09:00:18'),
(6, 'room.create', 'Add new rooms', 'rooms', '2025-11-22 09:00:18'),
(7, 'room.read', 'View rooms', 'rooms', '2025-11-22 09:00:18'),
(8, 'room.update', 'Edit room information', 'rooms', '2025-11-22 09:00:18'),
(9, 'room.delete', 'Delete rooms', 'rooms', '2025-11-22 09:00:18'),
(10, 'booking.create', 'Create bookings', 'bookings', '2025-11-22 09:00:18'),
(11, 'booking.read', 'View bookings', 'bookings', '2025-11-22 09:00:18'),
(12, 'booking.update', 'Modify bookings', 'bookings', '2025-11-22 09:00:18'),
(13, 'booking.cancel', 'Cancel bookings', 'bookings', '2025-11-22 09:00:18'),
(14, 'booking.checkin', 'Process check-in', 'bookings', '2025-11-22 09:00:18'),
(15, 'booking.checkout', 'Process check-out', 'bookings', '2025-11-22 09:00:18'),
(16, 'staff.create', 'Add staff members', 'staff', '2025-11-22 09:00:18'),
(17, 'staff.read', 'View staff information', 'staff', '2025-11-22 09:00:18'),
(18, 'staff.update', 'Update staff information', 'staff', '2025-11-22 09:00:18'),
(19, 'staff.delete', 'Remove staff members', 'staff', '2025-11-22 09:00:18'),
(20, 'staff.manage_schedules', 'Manage staff schedules', 'staff', '2025-11-22 09:00:18'),
(21, 'service.create', 'Create service requests', 'services', '2025-11-22 09:00:18'),
(22, 'service.read', 'View service requests', 'services', '2025-11-22 09:00:18'),
(23, 'service.update', 'Update service requests', 'services', '2025-11-22 09:00:18'),
(24, 'service.assign', 'Assign service requests to staff', 'services', '2025-11-22 09:00:18'),
(25, 'service.complete', 'Mark services as complete', 'services', '2025-11-22 09:00:18'),
(26, 'complaint.create', 'Create complaints', 'complaints', '2025-11-22 09:00:18'),
(27, 'complaint.read', 'View complaints', 'complaints', '2025-11-22 09:00:18'),
(28, 'complaint.update', 'Update complaints', 'complaints', '2025-11-22 09:00:18'),
(29, 'complaint.resolve', 'Resolve complaints', 'complaints', '2025-11-22 09:00:18'),
(30, 'reports.view', 'View reports and analytics', 'reports', '2025-11-22 09:00:18'),
(31, 'reports.export', 'Export reports', 'reports', '2025-11-22 09:00:18'),
(32, 'system.settings', 'Manage system settings', 'system', '2025-11-22 09:00:18'),
(33, 'system.audit', 'View audit logs', 'system', '2025-11-22 09:00:18'),
(34, 'package.create', 'Create meal/room packages', 'packages', '2025-12-16 15:30:11'),
(35, 'package.read', 'View meal/room packages', 'packages', '2025-12-16 15:30:11'),
(36, 'package.update', 'Update meal/room packages', 'packages', '2025-12-16 15:30:11'),
(37, 'package.delete', 'Delete meal/room packages', 'packages', '2025-12-16 15:30:11'),
(38, 'promotion.create', 'Create promotions', 'promotions', '2025-12-16 15:30:11'),
(39, 'promotion.read', 'View promotions', 'promotions', '2025-12-16 15:30:11'),
(40, 'promotion.update', 'Update promotions', 'promotions', '2025-12-16 15:30:11'),
(41, 'promotion.delete', 'Delete promotions', 'promotions', '2025-12-16 15:30:11'),
(42, 'branch.create', 'Create branches', 'branches', '2025-12-16 15:30:11'),
(43, 'branch.read', 'View branches', 'branches', '2025-12-16 15:30:11'),
(44, 'branch.update', 'Update branches', 'branches', '2025-12-16 15:30:11'),
(45, 'branch.delete', 'Delete branches', 'branches', '2025-12-16 15:30:11'),
(46, 'service_types.create', 'Create service types', 'service_types', '2025-12-16 15:30:11'),
(47, 'service_types.read', 'View service types', 'service_types', '2025-12-16 15:30:11'),
(48, 'service_types.update', 'Update service types', 'service_types', '2025-12-16 15:30:11'),
(49, 'service_types.delete', 'Delete service types', 'service_types', '2025-12-16 15:30:11'),
(50, 'guest.create', 'Create guest accounts', 'guests', '2025-12-16 15:30:11'),
(51, 'guest.read', 'View guest information', 'guests', '2025-12-16 15:30:11'),
(52, 'guest.update', 'Update guest information', 'guests', '2025-12-16 15:30:11'),
(53, 'guest.delete', 'Delete guest accounts', 'guests', '2025-12-16 15:30:11'),
(54, 'facility.read', 'View facilities and bookings', 'facilities', '2025-12-18 07:58:00'),
(55, 'facility.create', 'Create facilities and bookings', 'facilities', '2025-12-18 07:58:00'),
(56, 'facility.update', 'Update facilities and bookings', 'facilities', '2025-12-18 07:58:00'),
(57, 'facility.delete', 'Delete facilities and bookings', 'facilities', '2025-12-18 07:58:00'),
(58, 'analytics.view', 'View analytics dashboard', 'analytics', '2025-12-18 07:58:00'),
(59, 'analytics.export', 'Export analytics reports', 'analytics', '2025-12-18 07:58:00');

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

CREATE TABLE `promotions` (
  `promotion_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `promotion_code` varchar(50) NOT NULL,
  `promotion_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed_amount') NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_purchase_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `usage_limit` int(11) DEFAULT NULL COMMENT 'Total number of times this promotion can be used',
  `usage_count` int(11) DEFAULT 0 COMMENT 'Number of times this promotion has been used',
  `applicable_to` enum('all','room_booking','meal_package','room_package','service') DEFAULT 'all',
  `room_type_id` int(11) DEFAULT NULL COMMENT 'If applicable_to is room_booking, can specify room type',
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promotions`
--

INSERT INTO `promotions` (`promotion_id`, `branch_id`, `promotion_code`, `promotion_name`, `description`, `discount_type`, `discount_value`, `min_purchase_amount`, `max_discount_amount`, `start_date`, `end_date`, `usage_limit`, `usage_count`, `applicable_to`, `room_type_id`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 1, 'EARLYBIRD', 'Early Bird Special - 20% Off', 'Book your stay 30 days in advance and enjoy 20% discount! Perfect for planning ahead and saving money.', 'percentage', 20.00, 0.00, NULL, '2025-12-18', '2026-03-18', NULL, 0, 'all', NULL, 'inactive', 1, '2025-12-18 08:48:12', '2025-12-19 08:15:22'),
(4, 1, 'FIXED5000', 'Flat LKR 5,000 Off', 'Get a flat discount of LKR 5,000 on bookings over LKR 20,000. Great for extended stays!', 'fixed_amount', 5000.00, 20000.00, 5000.00, '2025-12-18', '2026-02-01', 75, 0, 'room_booking', NULL, 'active', 1, '2025-12-18 08:48:12', '2025-12-18 08:49:27'),
(5, 1, 'MEALPACK', 'Meal Package Deal - 10% Off', 'Special discount on meal packages! Book any meal package and save 10%. Combine with room booking for maximum savings.', 'percentage', 10.00, 0.00, 2000.00, '2025-12-18', '2026-01-07', 200, 0, 'meal_package', NULL, 'inactive', 1, '2025-12-18 08:48:12', '2025-12-19 08:15:04'),
(7, 1, 'NEWYEAR30', 'New Year Celebration - 30% Off', 'New Year Celebration - 30% Off', 'percentage', 30.00, 5000.00, 8000.00, '0000-00-00', '2025-12-31', NULL, 0, 'all', NULL, 'active', 6, '2025-12-18 17:52:22', '2025-12-18 17:52:22');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_description`, `is_active`, `created_at`) VALUES
(1, 'super_admin', 'Super Administrator - Full system access', 1, '2025-11-22 09:00:18'),
(2, 'administrator', 'Hotel Administrator - Hotel management access', 1, '2025-11-22 09:00:18'),
(3, 'receptionist', 'Front Desk Receptionist - Booking and guest management', 1, '2025-11-22 09:00:18'),
(5, 'housekeeping_staff', 'Housekeeping Staff - Service execution', 1, '2025-11-22 09:00:18'),
(7, 'concierge', 'Concierge - Guest services', 1, '2025-11-22 09:00:18');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `granted_at`) VALUES
(1, 1, '2025-12-18 07:59:25'),
(1, 2, '2025-12-18 07:59:25'),
(1, 3, '2025-12-18 07:59:25'),
(1, 4, '2025-12-18 07:59:25'),
(1, 5, '2025-12-18 07:59:25'),
(1, 6, '2025-12-18 07:59:25'),
(1, 7, '2025-12-18 07:59:25'),
(1, 8, '2025-12-18 07:59:25'),
(1, 9, '2025-12-18 07:59:25'),
(1, 10, '2025-12-18 07:59:25'),
(1, 11, '2025-12-18 07:59:25'),
(1, 12, '2025-12-18 07:59:25'),
(1, 13, '2025-12-18 07:59:25'),
(1, 14, '2025-12-18 07:59:25'),
(1, 15, '2025-12-18 07:59:25'),
(1, 16, '2025-12-18 07:59:25'),
(1, 17, '2025-12-18 07:59:25'),
(1, 18, '2025-12-18 07:59:25'),
(1, 19, '2025-12-18 07:59:25'),
(1, 20, '2025-12-18 07:59:25'),
(1, 21, '2025-12-18 07:59:25'),
(1, 22, '2025-12-18 07:59:25'),
(1, 23, '2025-12-18 07:59:25'),
(1, 24, '2025-12-18 07:59:25'),
(1, 25, '2025-12-18 07:59:25'),
(1, 26, '2025-12-18 07:59:25'),
(1, 27, '2025-12-18 07:59:25'),
(1, 28, '2025-12-18 07:59:25'),
(1, 29, '2025-12-18 07:59:25'),
(1, 30, '2025-12-18 07:59:25'),
(1, 31, '2025-12-18 07:59:25'),
(1, 32, '2025-12-18 07:59:25'),
(1, 33, '2025-12-18 07:59:25'),
(1, 34, '2025-12-18 07:59:25'),
(1, 35, '2025-12-18 07:59:25'),
(1, 36, '2025-12-18 07:59:25'),
(1, 37, '2025-12-18 07:59:25'),
(1, 38, '2025-12-18 07:59:25'),
(1, 39, '2025-12-18 07:59:25'),
(1, 40, '2025-12-18 07:59:25'),
(1, 41, '2025-12-18 07:59:25'),
(1, 42, '2025-12-18 07:59:25'),
(1, 43, '2025-12-18 07:59:25'),
(1, 44, '2025-12-18 07:59:25'),
(1, 45, '2025-12-18 07:59:25'),
(1, 46, '2025-12-18 07:59:25'),
(1, 47, '2025-12-18 07:59:25'),
(1, 48, '2025-12-18 07:59:25'),
(1, 49, '2025-12-18 07:59:25'),
(1, 50, '2025-12-18 07:59:25'),
(1, 51, '2025-12-18 07:59:25'),
(1, 52, '2025-12-18 07:59:25'),
(1, 53, '2025-12-18 07:59:25'),
(1, 54, '2025-12-18 07:59:25'),
(1, 55, '2025-12-18 07:59:25'),
(1, 56, '2025-12-18 07:59:25'),
(1, 57, '2025-12-18 07:59:25'),
(1, 58, '2025-12-18 07:59:25'),
(1, 59, '2025-12-18 07:59:25'),
(2, 10, '2025-12-16 15:32:13'),
(2, 11, '2025-12-16 15:32:13'),
(2, 12, '2025-12-16 15:32:13'),
(2, 13, '2025-12-16 15:32:13'),
(2, 14, '2025-12-16 15:32:13'),
(2, 15, '2025-12-16 15:32:13'),
(2, 16, '2025-12-16 15:32:13'),
(2, 17, '2025-12-16 15:32:13'),
(2, 18, '2025-12-16 15:32:13'),
(2, 19, '2025-12-16 15:32:13'),
(2, 20, '2025-12-16 15:32:13'),
(2, 21, '2025-12-16 15:32:13'),
(2, 22, '2025-12-16 15:32:13'),
(2, 23, '2025-12-16 15:32:13'),
(2, 24, '2025-12-16 15:32:13'),
(2, 25, '2025-12-16 15:32:13'),
(2, 26, '2025-12-16 15:32:13'),
(2, 27, '2025-12-16 15:32:13'),
(2, 28, '2025-12-16 15:32:13'),
(2, 29, '2025-12-16 15:32:13'),
(2, 30, '2025-12-16 15:32:13'),
(2, 31, '2025-12-16 15:32:13'),
(2, 32, '2025-12-16 15:32:13'),
(2, 33, '2025-12-16 15:32:13'),
(2, 50, '2025-12-16 15:32:13'),
(2, 51, '2025-12-16 15:32:13'),
(2, 52, '2025-12-16 15:32:13'),
(2, 53, '2025-12-16 15:32:13'),
(3, 2, '2025-12-18 16:20:52'),
(3, 7, '2025-12-18 16:20:52'),
(3, 10, '2025-12-18 16:20:52'),
(3, 11, '2025-12-18 16:20:52'),
(3, 12, '2025-12-18 16:20:52'),
(3, 13, '2025-12-18 16:20:52'),
(3, 14, '2025-12-18 16:20:52'),
(3, 15, '2025-12-18 16:20:52'),
(3, 50, '2025-12-18 16:20:52'),
(3, 51, '2025-12-18 16:20:52'),
(3, 52, '2025-12-18 16:20:52'),
(3, 53, '2025-12-18 16:20:52'),
(3, 54, '2025-12-18 16:20:52'),
(3, 55, '2025-12-18 16:20:52'),
(3, 56, '2025-12-18 16:20:52'),
(3, 57, '2025-12-18 16:20:52'),
(5, 7, '2025-11-22 09:00:18'),
(5, 22, '2025-11-22 09:00:18'),
(5, 23, '2025-11-22 09:00:18'),
(5, 25, '2025-11-22 09:00:18'),
(7, 7, '2025-12-18 16:21:44'),
(7, 17, '2025-12-18 16:21:44'),
(7, 21, '2025-12-18 16:21:44'),
(7, 22, '2025-12-18 16:21:44'),
(7, 23, '2025-12-18 16:21:44'),
(7, 24, '2025-12-18 16:21:44'),
(7, 25, '2025-12-18 16:21:44'),
(7, 46, '2025-12-18 16:21:44'),
(7, 47, '2025-12-18 16:21:44'),
(7, 48, '2025-12-18 16:21:44'),
(7, 49, '2025-12-18 16:21:44');

-- --------------------------------------------------------

--
-- Table structure for table `room`
--

CREATE TABLE `room` (
  `room_id` int(10) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `room_type_id` int(11) DEFAULT NULL,
  `price` int(10) NOT NULL,
  `max_person` int(10) NOT NULL,
  `room_no` varchar(10) NOT NULL,
  `status` tinyint(1) DEFAULT 0 COMMENT '0=Available, 1=Occupied',
  `is_deleted` tinyint(1) DEFAULT 0 COMMENT 'Soft delete flag'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `room`
--

INSERT INTO `room` (`room_id`, `branch_id`, `room_type_id`, `price`, `max_person`, `room_no`, `status`, `is_deleted`) VALUES
(2, 1, 2, 1500, 2, 'CMB-102', 1, 0),
(5, 1, 6, 1000, 1, 'CMB-104', 0, 0),
(7, 1, 2, 2000, 3, 'CMB-103', 1, 0),
(9, 1, 6, 1000, 1, 'CMB-101', 0, 0),
(14, 1, 4, 5500, 4, 'CMB-105', 0, 0),
(22, 2, 2, 6900, 3, 'KAN-104', 0, 0),
(26, 2, 4, 6500, 6, 'KAN-103', 0, 0),
(27, 2, 6, 1000, 1, 'KAN-105', 0, 0),
(28, 2, 2, 8000, 6, 'KAN-101', 0, 0),
(29, 2, 6, 1000, 1, 'KAN-102', 0, 0),
(30, 1, 4, 18000, 6, 'KAN - 106', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `room_type`
--

CREATE TABLE `room_type` (
  `room_type_id` int(11) NOT NULL,
  `room_type` varchar(100) NOT NULL,
  `price` int(10) NOT NULL,
  `max_person` int(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_type`
--

INSERT INTO `room_type` (`room_type_id`, `room_type`, `price`, `max_person`, `created_at`, `updated_at`) VALUES
(2, 'Double Room', 12000, 2, '2025-12-15 16:56:25', '2025-12-18 13:08:52'),
(4, 'Deluxe Room', 18000, 6, '2025-12-15 16:56:25', '2025-12-18 13:10:24'),
(6, 'Single Room', 8000, 1, '2025-12-15 16:56:25', '2025-12-18 13:08:24');

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `request_id` int(11) NOT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `service_type_id` int(11) NOT NULL,
  `request_title` varchar(200) NOT NULL,
  `request_description` text DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('pending','assigned','in_progress','completed','cancelled') DEFAULT 'pending',
  `assigned_to` int(11) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`request_id`, `guest_id`, `booking_id`, `service_type_id`, `request_title`, `request_description`, `priority`, `status`, `assigned_to`, `requested_at`, `assigned_at`, `completed_at`, `notes`) VALUES
(8, 1, 18, 23, 'Room cleaning', 'Need a room cleaning', 'normal', 'assigned', 16, '2025-12-19 06:10:28', '2025-12-19 06:10:28', NULL, NULL),
(9, 1, 18, 14, 'Pickup taxi', 'Need a pickup taxi', 'normal', 'assigned', 17, '2025-12-19 06:16:39', '2025-12-19 06:16:39', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `service_types`
--

CREATE TABLE `service_types` (
  `service_type_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `service_name` varchar(100) NOT NULL,
  `service_description` text DEFAULT NULL,
  `category` enum('room_service','housekeeping','maintenance','dining','transport','concierge','other') DEFAULT 'other',
  `default_priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_types`
--

INSERT INTO `service_types` (`service_type_id`, `branch_id`, `service_name`, `service_description`, `category`, `default_priority`, `is_active`, `created_at`) VALUES
(14, NULL, 'Taxi Service', 'Hotel taxi booking service', 'transport', 'normal', 1, '2025-12-18 16:00:11'),
(18, NULL, 'Restaurant Reservation', 'Assistance with restaurant reservations', 'dining', 'normal', 1, '2025-12-18 16:00:11'),
(23, NULL, 'Room Cleaning', 'Professional room cleaning and housekeeping service', 'housekeeping', 'normal', 1, '2025-12-19 05:59:05');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `staff_name` varchar(100) NOT NULL,
  `staff_type_id` int(11) DEFAULT NULL,
  `shift` varchar(100) NOT NULL,
  `shift_timing` varchar(100) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `id_card_type` varchar(100) NOT NULL,
  `id_card_no` varchar(20) NOT NULL,
  `address` varchar(100) NOT NULL,
  `contact_no` bigint(20) NOT NULL,
  `salary` bigint(20) NOT NULL,
  `joining_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `branch_id`, `user_id`, `staff_name`, `staff_type_id`, `shift`, `shift_timing`, `role_id`, `id_card_type`, `id_card_no`, `address`, `contact_no`, `salary`, `joining_date`, `updated_at`) VALUES
(13, NULL, 6, 'Super Administrator', 1, 'Morning/Day', '7 AM-4 PM', 1, 'National Identity Card', '000000000000', 'Head Office', 0, 0, '2025-11-23 10:43:57', '2025-12-18 13:51:47'),
(14, 1, 7, 'Lishani Dassanayake', 2, 'Morning/Day', '7 AM-4 PM', 3, 'National Identity Card', '200508900222', 'Kandy', 775761221, 40000, '2025-11-23 11:08:17', '2025-12-18 13:51:47'),
(15, 1, 9, 'Branch Admin - CMB', 3, 'Morning/Day', '7 AM-4 PM', 2, 'National Identity Card', '123456789012', 'Kandy', 789023567, 50000, '2025-11-23 11:20:52', '2025-12-18 14:18:02'),
(16, 1, 10, 'Zaid', 4, 'Morning/Day', '7 AM-4 PM', 5, 'National Identity Card', '245689012334', 'Gelioya', 775871223, 25000, '2025-11-23 11:23:07', '2025-12-18 13:51:47'),
(17, 1, 11, 'Harish Ahamed', 5, 'Night/Graveyard', '11 PM-8 AM', 7, 'National Identity Card', '200108900121', 'Gelioya', 761321606, 150000, '2025-11-23 11:24:41', '2025-12-18 13:51:47'),
(18, 2, NULL, 'Lanka', 2, 'Morning/Day', '7 AM-4 PM', 3, 'National Identity Card', '000000000001', 'Kandy', 770000001, 50000, '2025-12-18 14:16:53', '2025-12-18 14:16:53'),
(19, 2, NULL, 'Pradeep', 5, 'Afternoon/Swing', '3 PM-11 PM', 7, 'National Identity Card', '000000000002', 'Kandy', 770000002, 45000, '2025-12-18 14:16:53', '2025-12-18 14:16:53'),
(20, 2, NULL, 'Amali', 4, 'Morning/Day', '7 AM-4 PM', 5, 'National Identity Card', '000000000003', 'Kandy', 770000003, 40000, '2025-12-18 14:16:53', '2025-12-18 14:16:53'),
(21, 2, NULL, 'Branch Admin - KAN', 3, 'Morning/Day', '7 AM-4 PM', 2, 'National Identity Card', '000000000004', 'Kandy', 770000004, 60000, '2025-12-18 14:16:53', '2025-12-18 14:18:20');

-- --------------------------------------------------------

--
-- Table structure for table `staff_type`
--

CREATE TABLE `staff_type` (
  `staff_type_id` int(11) NOT NULL,
  `staff_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_type`
--

INSERT INTO `staff_type` (`staff_type_id`, `staff_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', NULL, 1, '2025-12-16 13:11:57', '2025-12-16 13:11:57'),
(2, 'Receptionist', NULL, 1, '2025-12-16 13:11:57', '2025-12-16 13:11:57'),
(3, 'Branch Admin', NULL, 1, '2025-12-16 13:11:57', '2025-12-16 13:11:57'),
(4, 'Housekeeping Attendant', NULL, 1, '2025-12-16 13:11:57', '2025-12-16 13:11:57'),
(5, 'Concierge', NULL, 1, '2025-12-16 13:11:57', '2025-12-16 13:11:57');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone` varchar(20) DEFAULT NULL,
  `phone_verified` tinyint(1) DEFAULT 0,
  `password` varchar(100) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `failed_login_attempts` int(11) DEFAULT 0,
  `account_locked_until` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `name`, `username`, `email`, `email_verified`, `phone`, `phone_verified`, `password`, `role_id`, `created_at`, `last_login`, `status`, `failed_login_attempts`, `account_locked_until`) VALUES
(6, 'Super Administrator', 'superadmin', 'superadmin@hotel.local', 0, NULL, 0, '$2y$12$meUSzxFXrkLpa7qzPO1OfOTKG1xOOH5S.imEflexWYAPuaqRdZMo.', NULL, '2025-11-23 10:43:57', '2025-12-19 10:30:11', 'active', 0, NULL),
(7, 'Lishani Dassanayake', 'cmbreceptionist', 'lishid2002@gmail.com', 0, '775761221', 0, '$2y$12$7gDdcrvoJTfwjOOcdyUH1u7Gk4E9l.p8ufMdDBF6NraXZKWJaW4m6', NULL, '2025-11-23 11:09:32', '2025-12-19 06:20:00', 'active', 0, NULL),
(9, 'Branch Admin  Colombo', 'admincmb', 'admincmb@hotel.local', 0, '789023567', 0, '$2y$12$B9jJKRKB9fdBC4DMQ56VMeFXa8J/4NaEY7u8rZY/88Hvq/bbLDkoq', NULL, '2025-11-23 11:21:44', '2025-12-18 14:55:43', 'active', 0, NULL),
(10, 'Zaid  Omar', 'cmbhousekeeping', 'zaidftz35@gmail.com', 0, '775871223', 0, '$2y$12$ss2JSS1lrOQEea3L0Zp8.uOi0uqeDnQJLXCSVhKLLx1C6v.P8.sCO', NULL, '2025-11-23 11:23:39', '2025-12-19 06:10:36', 'active', 0, NULL),
(11, 'Harish Ahamed', 'cmbconcierge', 'harishahamed2607@gmail.com', 0, '761321606', 0, '$2y$12$IV6dS.8BOApKTiJY37lFcuwYkGla/6rL8AOTAnYF0RbcnWk.3dvmG', NULL, '2025-11-23 11:25:07', '2025-12-19 06:17:24', 'active', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`, `assigned_at`, `assigned_by`) VALUES
(6, 1, '2025-11-23 10:43:57', 6),
(7, 3, '2025-11-23 11:09:32', 6),
(9, 2, '2025-11-23 11:21:44', 6),
(10, 5, '2025-11-23 11:23:40', 6),
(11, 7, '2025-11-23 11:25:07', 6);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`),
  ADD KEY `module` (`module`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `idx_booking_status` (`status`),
  ADD KEY `fk_booking_created_by` (`created_by`),
  ADD KEY `fk_booking_modified_by` (`modified_by`),
  ADD KEY `idx_booking_created_at` (`created_at`),
  ADD KEY `idx_booking_updated_at` (`updated_at`),
  ADD KEY `idx_meal_package_id` (`meal_package_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`branch_id`),
  ADD UNIQUE KEY `branch_code` (`branch_code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_recipient_email` (`recipient_email`),
  ADD KEY `idx_email_type` (`email_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sent_at` (`sent_at`),
  ADD KEY `idx_guest_id` (`guest_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_promotion_id` (`promotion_id`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_template_code` (`template_code`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`facility_id`),
  ADD KEY `idx_facility_type` (`facility_type`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `facility_bookings`
--
ALTER TABLE `facility_bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `booked_by` (`booked_by`),
  ADD KEY `idx_facility_id` (`facility_id`),
  ADD KEY `idx_booking_date` (`booking_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_range` (`booking_date`,`start_time`,`end_time`);

--
-- Indexes for table `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`guest_id`),
  ADD UNIQUE KEY `idx_username` (`username`),
  ADD UNIQUE KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `id_card_type`
--
ALTER TABLE `id_card_type`
  ADD PRIMARY KEY (`id_card_type_id`),
  ADD UNIQUE KEY `idx_id_card_type` (`id_card_type`);

--
-- Indexes for table `meal_packages`
--
ALTER TABLE `meal_packages`
  ADD PRIMARY KEY (`package_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_meal_type` (`meal_type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`preference_id`),
  ADD UNIQUE KEY `user_notification` (`user_id`,`notification_type`),
  ADD UNIQUE KEY `guest_notification` (`guest_id`,`notification_type`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`),
  ADD KEY `idx_setting_type` (`setting_type`);

--
-- Indexes for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`template_id`),
  ADD UNIQUE KEY `template_code` (`template_code`),
  ADD KEY `idx_template_code` (`template_code`),
  ADD KEY `idx_template_type` (`template_type`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`);

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`promotion_id`),
  ADD UNIQUE KEY `unique_branch_code` (`branch_id`,`promotion_code`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_promotion_code` (`promotion_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_applicable_to` (`applicable_to`),
  ADD KEY `idx_room_type_id` (`room_type_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `room`
--
ALTER TABLE `room`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_room_type_id` (`room_type_id`),
  ADD KEY `idx_room_status` (`status`),
  ADD KEY `idx_room_deleted` (`is_deleted`);

--
-- Indexes for table `room_type`
--
ALTER TABLE `room_type`
  ADD PRIMARY KEY (`room_type_id`),
  ADD UNIQUE KEY `unique_room_type` (`room_type`),
  ADD KEY `idx_price` (`price`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `guest_id` (`guest_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `service_type_id` (`service_type_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `service_types`
--
ALTER TABLE `service_types`
  ADD PRIMARY KEY (`service_type_id`),
  ADD UNIQUE KEY `unique_service_branch` (`service_name`,`branch_id`),
  ADD KEY `idx_branch_id` (`branch_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_role_id` (`role_id`),
  ADD KEY `staff_ibfk_staff_type` (`staff_type_id`);

--
-- Indexes for table `staff_type`
--
ALTER TABLE `staff_type`
  ADD PRIMARY KEY (`staff_type_id`),
  ADD UNIQUE KEY `unique_staff_type` (`staff_type`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=402;

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `booking_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `branch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `facility_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `facility_bookings`
--
ALTER TABLE `facility_bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guests`
--
ALTER TABLE `guests`
  MODIFY `guest_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `id_card_type`
--
ALTER TABLE `id_card_type`
  MODIFY `id_card_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `meal_packages`
--
ALTER TABLE `meal_packages`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  MODIFY `preference_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `notification_templates`
--
ALTER TABLE `notification_templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `promotion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `room`
--
ALTER TABLE `room`
  MODIFY `room_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `room_type`
--
ALTER TABLE `room_type`
  MODIFY `room_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `service_types`
--
ALTER TABLE `service_types`
  MODIFY `service_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `staff_type`
--
ALTER TABLE `staff_type`
  MODIFY `staff_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`),
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`),
  ADD CONSTRAINT `booking_ibfk_3` FOREIGN KEY (`meal_package_id`) REFERENCES `meal_packages` (`package_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_booking_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_booking_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `user` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `facilities_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL;

--
-- Constraints for table `facility_bookings`
--
ALTER TABLE `facility_bookings`
  ADD CONSTRAINT `facility_bookings_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `facility_bookings_ibfk_3` FOREIGN KEY (`booked_by`) REFERENCES `user` (`id`);

--
-- Constraints for table `meal_packages`
--
ALTER TABLE `meal_packages`
  ADD CONSTRAINT `fk_meal_package_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `notification_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_preferences_ibfk_2` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`) ON DELETE CASCADE;

--
-- Constraints for table `room`
--
ALTER TABLE `room`
  ADD CONSTRAINT `fk_room_room_type` FOREIGN KEY (`room_type_id`) REFERENCES `room_type` (`room_type_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `room_ibfk_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL;

--
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`guest_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `service_requests_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`booking_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `service_requests_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `service_types`
--
ALTER TABLE `service_types`
  ADD CONSTRAINT `fk_service_types_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `staff_ibfk_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `staff_ibfk_staff_type` FOREIGN KEY (`staff_type_id`) REFERENCES `staff_type` (`staff_type_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `staff_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
