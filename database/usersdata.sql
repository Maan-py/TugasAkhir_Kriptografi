-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 03, 2025 at 04:52 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ta_kripto`
--

-- --------------------------------------------------------

--
-- Table structure for table `usersdata`
--

CREATE TABLE `usersdata` (
  `data_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `data_label` varchar(255) NOT NULL,
  `enc_nama` text DEFAULT NULL,
  `enc_telepon` text DEFAULT NULL,
  `enc_tempat_lahir` text DEFAULT NULL,
  `enc_tanggal_lahir` text DEFAULT NULL,
  `enc_alamat` text DEFAULT NULL,
  `enc_pesan_bebas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usersdata`
--

INSERT INTO `usersdata` (`data_id`, `username`, `data_label`, `enc_nama`, `enc_telepon`, `enc_tempat_lahir`, `enc_tanggal_lahir`, `enc_alamat`, `enc_pesan_bebas`, `created_at`) VALUES
(2, '123', 'data rara', '11489f9f', '5311dccc9953abb97f21', '2866b9bf8a2cdbcb014bd1', '5119ddca8756aea27a29', '134c9f8bc709fffc', '0bf91c4fa633e6927b9c4b29718351f1', '2025-11-03 15:27:12'),
(3, '123', 'data luqmaan', '26edf0b9f10bcc', '7aa0b0e6a35e9794e4ef', '01d7d595b021e7e69a85cb', '78a8b1e0bd5b928fe1e7', '29f7efb7f11e', 'afdfaaf256111d91', '2025-11-03 15:31:16'),
(4, '123', 'data raraa', '0d48988ccb0f', '5311dccc9953abb97f21a13f', '2866b9bf8a2cdbcb014bd1', '5119ddca8756aea27a29', '134c9f8bc709fffc', '0eb1ed0d31a48dc1', '2025-11-03 15:32:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `usersdata`
--
ALTER TABLE `usersdata`
  ADD PRIMARY KEY (`data_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `usersdata`
--
ALTER TABLE `usersdata`
  MODIFY `data_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
