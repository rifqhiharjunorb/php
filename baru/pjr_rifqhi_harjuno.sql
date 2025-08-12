-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 27, 2025 at 07:33 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pjr_rifqhi_harjuno`
--

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman_ruangan`
--

CREATE TABLE `peminjaman_ruangan` (
  `peminjaman_id` int NOT NULL,
  `user_id` int NOT NULL,
  `ruangan_id` int NOT NULL,
  `tanggal` varchar(100) NOT NULL,
  `waktu_mulai` varchar(100) NOT NULL,
  `Durasi_pinjam` varchar(200) NOT NULL,
  `waktu_selesai` varchar(200) NOT NULL,
  `status` varchar(200) NOT NULL,
  `keterangan` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ruangan`
--

CREATE TABLE `ruangan` (
  `ruangan_id` int NOT NULL,
  `nama_ruangan` varchar(200) NOT NULL,
  `lokasi` varchar(200) NOT NULL,
  `kapasitas` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ruangan`
--

INSERT INTO `ruangan` (`ruangan_id`, `nama_ruangan`, `lokasi`, `kapasitas`) VALUES
(10, 'taman', 'belakang sekolah', 20),
(13, 'aula', 'aula sekolah', 100),
(16, 'lab komputer', 'laboratorium', 30),
(17, 'ruang kelas', 'ruang 9', 29),
(18, 'ruang kelas', 'ruang 23', 25),
(19, 'ruang kelas', 'ruang 10', 30);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `Id_card` varchar(200) NOT NULL,
  `username` varchar(200) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` varchar(100) NOT NULL,
  `Jenis_pengguna` varchar(200) NOT NULL,
  `nama_lengkap` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `Id_card`, `username`, `password`, `role`, `Jenis_pengguna`, `nama_lengkap`) VALUES
(1, '1983746532', 'Andi', 'admin_456', 'admin', 'guru', 'andianto'),
(2, '2319478123', 'budi', 'budi123', 'user', 'siswa', 'budi setiawan'),
(3, '2398472398	', 'citra', 'citra123', 'user', 'siswa', 'citra putri'),
(4, '9872316543	', 'deni', 'deni123', 'user', 'siswa', 'deni kusumo'),
(5, '5432198765', 'ayu', 'ayu123', 'user', 'siswa', 'ayu puspita'),
(7, 'IDUU12', 'Aru', 'Kirima', 'user', 'internal', 'Aru Kirima');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `peminjaman_ruangan`
--
ALTER TABLE `peminjaman_ruangan`
  ADD PRIMARY KEY (`peminjaman_id`),
  ADD KEY `fk_user` (`user_id`),
  ADD KEY `fk_ruangan` (`ruangan_id`);

--
-- Indexes for table `ruangan`
--
ALTER TABLE `ruangan`
  ADD PRIMARY KEY (`ruangan_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `peminjaman_ruangan`
--
ALTER TABLE `peminjaman_ruangan`
  MODIFY `peminjaman_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `ruangan`
--
ALTER TABLE `ruangan`
  MODIFY `ruangan_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `peminjaman_ruangan`
--
ALTER TABLE `peminjaman_ruangan`
  ADD CONSTRAINT `fk_ruangan` FOREIGN KEY (`ruangan_id`) REFERENCES `ruangan` (`ruangan_id`),
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
