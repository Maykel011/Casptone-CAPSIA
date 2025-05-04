-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 04, 2025 at 05:45 PM
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
-- Database: `u450075158_ucgs`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borrowed_items`
--

CREATE TABLE `borrowed_items` (
  `borrow_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `actual_return_date` date DEFAULT NULL,
  `item_condition` enum('Good','Damaged','Lost') DEFAULT NULL,
  `return_notes` text DEFAULT NULL,
  `status` enum('Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
  `user_id` int(11) NOT NULL,
  `borrow_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borrow_requests`
--

CREATE TABLE `borrow_requests` (
  `borrow_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `date_needed` date NOT NULL,
  `return_date` date NOT NULL,
  `purpose` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Returned','For Checking','Processed','Not Accepted') NOT NULL,
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `transaction_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `return_status` varchar(20) DEFAULT NULL,
  `return_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `item_no` varchar(50) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `availability` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(10) NOT NULL DEFAULT 'pcs',
  `status` enum('Available','Out of Stock','Low Stock') NOT NULL,
  `model_no` varchar(50) DEFAULT NULL,
  `item_category` varchar(50) NOT NULL DEFAULT '',
  `item_location` varchar(50) DEFAULT NULL,
  `expiration` date DEFAULT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `created_by`, `item_no`, `item_name`, `description`, `quantity`, `availability`, `unit`, `status`, `model_no`, `item_category`, `item_location`, `expiration`, `brand`, `deleted_at`, `last_updated`, `created_at`) VALUES
(1, 1, 'ITEM-67fa9921bc165', 'ESV Bible', 'ESV Bible', 200, 200, 'pcs', 'Available', 'ITEM-257280217', 'stationary', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:56', '2025-04-12 16:47:29'),
(2, 1, 'ITEM-67fa995d32e2e', 'KJV Bible', 'KJV Bible', 100, 100, 'pcs', 'Available', 'ITEM-377690816', 'stationary', 'Storage Room', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 16:48:29'),
(3, 1, 'ITEM-67fa99aa78f13', 'NIV Bible', 'NIV Bible', 150, 150, 'pcs', 'Available', 'ITEM-302507392', 'stationary', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 16:49:46'),
(4, 1, 'ITEM-67fa9a0349d71', 'Projector', 'Projector', 5, 5, 'pcs', 'Available', 'ITEM-751601536', 'electronics', 'Storage Room', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 16:51:15'),
(5, 1, 'ITEM-67fa9ae1c1337', 'Brown Envelope', 'Brown Envelope', 1000, 1000, 'pcs', 'Available', 'ITEM-102478715', 'stationary', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 16:54:57'),
(6, 1, 'ITEM-67fa9b10312a0', 'Cardboard', 'Cardboard', 124, 124, 'pcs', 'Available', 'ITEM-972015773', 'consumables', 'Storage Room', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 16:55:44'),
(7, 1, 'ITEM-67fa9b60744bc', 'First Aid Kit', 'First Aid Kit', 20, 16, 'pcs', 'Available', 'ITEM-477741107', 'consumables', 'Admin Office', NULL, NULL, NULL, '2025-05-04 23:14:03', '2025-04-12 16:57:04'),
(8, 1, 'ITEM-67fa9c5f18095', 'A4 Bond Paper', 'A4 Bond Paper', 10, 10, 'bdl', 'Available', 'ITEM-324759139', 'stationary', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:01:19'),
(9, 1, 'ITEM-67fa9c8f1f5be', 'Long Bond Paper', 'Long Bond Paper', 11, 11, 'bdl', 'Available', 'ITEM-932141824', 'stationary', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:02:07'),
(10, 1, 'ITEM-67fa9d6f220b6', 'Office Chair', 'Office Chair', 10, 10, 'pcs', 'Available', 'ITEM-166943995', 'furniture', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:05:51'),
(11, 1, 'ITEM-67fa9e6da1407', 'Laptop', 'Galaxy Book5 Pro 360', 6, 6, 'pcs', 'Available', 'ITEM-476850205', 'electronics', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:10:05'),
(12, 1, 'ITEM-67fa9f0361d7a', 'Mini Trash Bin', 'Mini Trash Bin', 4, 4, 'pcs', 'Available', 'ITEM-381597685', 'accessories', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:12:35'),
(13, 1, 'ITEM-67faa018c3a67', 'Plastic Table', 'Plastic Table', 12, 12, 'pcs', 'Available', 'ITEM-972015773', 'furniture', 'Storage Room', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:17:12'),
(14, 1, 'ITEM-67faa0b58d5b7', 'Crayons', 'Crayola', 10, 10, 'bx', 'Available', 'ITEM-106819365', 'stationary', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:19:49'),
(15, 1, 'ITEM-67faa0fb20d1f', 'White Envelope', 'White Envelope', 500, 500, 'pcs', 'Available', 'ITEM-644439902', 'stationary', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:20:59'),
(16, 1, 'ITEM-67faa154f0150', 'Broom', 'Broom', 10, 10, 'pcs', 'Available', 'ITEM-519293501', 'stationary', 'Janitorial Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:22:28'),
(17, 1, 'ITEM-67faa2ea64404', 'Disposable cups', 'Disposable cups', 5, 5, 'bx', 'Available', 'ITEM-381597685', 'consumables', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:29:14'),
(18, 1, 'ITEM-67faa3ce3c8e1', 'Communion Wine', 'Communion Wine', 14, 14, 'pcs', 'Available', 'ITEM-922704471', 'consumables', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:33:02'),
(19, 1, 'ITEM-67faa3f12194b', 'Candles', 'White Candles', 4, 4, 'bdl', 'Low Stock', 'ITEM-328217189', 'consumables', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:33:37'),
(20, 1, 'ITEM-67faa548bf415', 'Paper Plates', 'Paper Plates', 234, 234, 'pcs', 'Available', 'ITEM-166943995', 'consumables', 'Storage Room', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:39:20'),
(21, 1, 'ITEM-67faa59c68191', 'Microphone', 'Microphone', 18, 18, 'pcs', 'Available', 'ITEM-163744751', 'electronics', 'Sanctuary', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:40:44'),
(22, 1, 'ITEM-67faa6160c955', 'Music Stand', 'Black Music Stand', 8, 8, 'pcs', 'Available', 'ITEM-552270458', 'furniture', 'Sanctuary', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:42:46'),
(23, 1, 'ITEM-67faa677a23e4', 'Camera', 'Canon EOS 3000D with 18-55 III Non-IS Black', 6, 6, 'pcs', 'Available', 'ITEM-328217189', 'electronics', 'Sanctuary', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:44:23'),
(24, 1, 'ITEM-67faa6d92e780', 'Flatscreen TV', 'Daewoo Electronic Appliances LED Google TV 32 Inches', 6, 6, 'pcs', 'Available', 'ITEM-106819365', 'electronics', 'Sanctuary', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:46:01'),
(25, 1, 'ITEM-67faa7d447e99', 'Erasers', 'Erasers', 35, 35, 'pcs', 'Available', 'ITEM-106819365', 'stationary', 'Admin Office', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-12 17:50:12'),
(26, 1, 'ITEM-67fbaa271459f', 'Wooden Table', 'Wooden Table', 8, 8, 'pcs', 'Available', 'ITEM-654719365', 'furniture', 'Sanctuary', NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-13 12:12:23'),
(27, NULL, 'ITEM-67FBB707C3EAB', 'Ceiling Fan', NULL, 3, 3, 'pcs', 'Available', NULL, 'electronics', NULL, NULL, NULL, NULL, '2025-05-04 18:02:07', '2025-04-13 13:07:19');

--
-- Triggers `items`
--
DELIMITER $$
CREATE TRIGGER `prevent_negative_quantity` BEFORE UPDATE ON `items` FOR EACH ROW BEGIN
    IF NEW.quantity < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Item quantity cannot be negative.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `item_returns`
--

CREATE TABLE `item_returns` (
  `return_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `item_condition` enum('Good','Damaged','Lost') NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `new_item_requests`
--

CREATE TABLE `new_item_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_category` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `item_unit` varchar(50) NOT NULL,
  `purpose` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `ministry` enum('UCM','CWA','CHOIR','PWT','CYF') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `type` enum('Info','Warning','Error') DEFAULT 'Info'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `item_no` varchar(50) NOT NULL,
  `last_updated` datetime NOT NULL,
  `model_no` varchar(50) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `item_category` varchar(50) DEFAULT NULL,
  `item_location` varchar(50) DEFAULT NULL,
  `expiration` date DEFAULT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `supplier` varchar(50) DEFAULT NULL,
  `price_per_item` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `status` enum('Available','Out of Stock','Low Stock') DEFAULT NULL,
  `reorder_point` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `request_date` datetime DEFAULT current_timestamp(),
  `request_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `returned_items`
--

CREATE TABLE `returned_items` (
  `return_id` int(11) NOT NULL,
  `borrow_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `item_condition` enum('Good','Damaged','Lost') NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_requests`
--

CREATE TABLE `return_requests` (
  `return_id` int(11) NOT NULL,
  `borrow_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `item_condition` enum('Good','Damaged','Lost') NOT NULL,
  `quantity` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `admin_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `item_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `status` enum('Pending','Completed','Failed') DEFAULT 'Pending',
  `item_id` int(11) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('User','Administrator') NOT NULL,
  `ministry` enum('UCM','CWA','CHOIR','PWT','CYF') NOT NULL,
  `status` enum('Active','Deactivated') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deactivation_end` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `ministry`, `status`, `created_at`, `updated_at`, `deactivation_end`, `reset_token`, `reset_expires`) VALUES
(1, 'Jay Neri Anunuevo', 'jnanunuevo@gmail.com', '$2y$10$JbOjtTvo3X9j3vKe1Gux1O48EUWeLSQhXksz6YWjewqG.UXiD3t.S', 'Administrator', 'CHOIR', 'Active', '2025-04-12 05:28:52', '2025-04-12 15:41:50', NULL, NULL, NULL),
(19, 'Susan Gasilao', 'susangasilao@yahoo.com', '$2y$10$50P9Nj/mNHsXhpWYkR/v.eppKJE7NdEmKnpjv2HMIUsV9uhmird4y', 'User', 'CHOIR', 'Active', '2025-04-12 17:50:39', '2025-04-13 13:02:36', NULL, NULL, NULL),
(21, 'Nerizza Joy Mabazza', 'jamaicamabazza0809@gmail.com', '$2y$10$dq/ruk3KWEf/LQIoHFbi5exO85oQXXjkEevKNfhc4ZOL7PKJTDxmC', 'User', 'CYF', 'Deactivated', '2025-04-13 11:25:09', '2025-04-13 13:10:47', '2025-04-14 13:10:47', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_requests`
--

CREATE TABLE `user_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `admin_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_audit_logs_user_id` (`user_id`);

--
-- Indexes for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD PRIMARY KEY (`borrow_id`),
  ADD KEY `fk_borrow_requests_item_id` (`item_id`),
  ADD KEY `idx_borrow_requests_status` (`status`),
  ADD KEY `fk_borrow_requests_user_id` (`user_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `item_no` (`item_no`),
  ADD KEY `idx_items_item_name` (`item_name`),
  ADD KEY `idx_items_status` (`status`),
  ADD KEY `idx_items_item_category` (`item_category`),
  ADD KEY `idx_items_item_location` (`item_location`);

--
-- Indexes for table `item_returns`
--
ALTER TABLE `item_returns`
  ADD PRIMARY KEY (`return_id`),
  ADD KEY `fk_item_returns_user_id` (`user_id`),
  ADD KEY `fk_item_returns_item_id` (`item_id`);

--
-- Indexes for table `new_item_requests`
--
ALTER TABLE `new_item_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_new_item_requests_user_id` (`user_id`),
  ADD KEY `idx_new_item_requests_status` (`status`),
  ADD KEY `idx_new_item_requests_ministry` (`ministry`),
  ADD KEY `idx_new_item_requests_request_date` (`request_date`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notifications_user_id` (`user_id`),
  ADD KEY `idx_notifications_is_read` (`is_read`),
  ADD KEY `idx_notifications_type` (`type`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `fk_reports_item_no` (`item_no`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `returned_items`
--
ALTER TABLE `returned_items`
  ADD PRIMARY KEY (`return_id`),
  ADD KEY `fk_returned_items_borrow_id` (`borrow_id`),
  ADD KEY `fk_returned_items_processed_by` (`processed_by`);

--
-- Indexes for table `return_requests`
--
ALTER TABLE `return_requests`
  ADD PRIMARY KEY (`return_id`),
  ADD UNIQUE KEY `borrow_id` (`borrow_id`),
  ADD KEY `idx_return_requests_status` (`status`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `fk_transactions_user_id` (`user_id`),
  ADD KEY `fk_transactions_item_id` (`item_id`),
  ADD KEY `fk_transactions_request_id` (`request_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_requests`
--
ALTER TABLE `user_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_user_requests_user_id` (`user_id`),
  ADD KEY `idx_user_requests_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  MODIFY `borrow_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `item_returns`
--
ALTER TABLE `item_returns`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `new_item_requests`
--
ALTER TABLE `new_item_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `returned_items`
--
ALTER TABLE `returned_items`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_requests`
--
ALTER TABLE `return_requests`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `user_requests`
--
ALTER TABLE `user_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD CONSTRAINT `fk_borrow_requests_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  ADD CONSTRAINT `fk_borrow_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `item_returns`
--
ALTER TABLE `item_returns`
  ADD CONSTRAINT `fk_item_returns_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  ADD CONSTRAINT `fk_item_returns_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `new_item_requests`
--
ALTER TABLE `new_item_requests`
  ADD CONSTRAINT `fk_new_item_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_item_no` FOREIGN KEY (`item_no`) REFERENCES `items` (`item_no`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `returned_items`
--
ALTER TABLE `returned_items`
  ADD CONSTRAINT `fk_returned_items_borrow_id` FOREIGN KEY (`borrow_id`) REFERENCES `borrow_requests` (`borrow_id`),
  ADD CONSTRAINT `fk_returned_items_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `return_requests`
--
ALTER TABLE `return_requests`
  ADD CONSTRAINT `fk_return_requests_borrow_id` FOREIGN KEY (`borrow_id`) REFERENCES `borrow_requests` (`borrow_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  ADD CONSTRAINT `fk_transactions_request_id` FOREIGN KEY (`request_id`) REFERENCES `new_item_requests` (`request_id`),
  ADD CONSTRAINT `fk_transactions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `user_requests`
--
ALTER TABLE `user_requests`
  ADD CONSTRAINT `fk_user_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
