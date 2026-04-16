-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2026 at 09:43 AM
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
-- Database: `ssb_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `nama_admin` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id_admin`, `nama_admin`, `email`, `password`, `user_id`) VALUES
(1, 'Admin_SSB1', 'adminssb@gmail.com', '$2y$10$7gp2WxRU4xKsN2FkZnvH6uPFtHedaB9dJzk.WFWPF.yWanvQek3W6', 7);

-- --------------------------------------------------------

--
-- Table structure for table `bukti_pembayaran`
--

CREATE TABLE `bukti_pembayaran` (
  `id_bukti_pembayaran` int(11) NOT NULL,
  `id_pembayaran` int(11) DEFAULT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `periode` varchar(50) DEFAULT NULL,
  `tanggal_bukti_bayar` date DEFAULT NULL,
  `status` enum('Menunggu validasi','diterima','ditolak') DEFAULT NULL,
  `bukti_bayar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bukti_pembayaran`
--

INSERT INTO `bukti_pembayaran` (`id_bukti_pembayaran`, `id_pembayaran`, `id_siswa`, `periode`, `tanggal_bukti_bayar`, `status`, `bukti_bayar`) VALUES
(1, 1, 1, '2026', '2026-02-28', 'diterima', 'bukti_pembayaran/AOkneAwIksl5gQtsrAC3E8SfSlBjTc76hhJ5qdww.png'),
(5, 3, 6, '2026', '2026-03-11', 'Menunggu validasi', 'bukti_pembayaran/KWETTetJ555HReE2TXzbhtzOWiYEa08BkltYvoU0.png'),
(7, 5, 8, '2026', '2026-03-11', 'Menunggu validasi', 'bukti_pembayaran/VMrhbJVyKgFOtF9TXUyk9WNTDJVEQB3nkAJ0Ha4x.png'),
(8, 7, 11, '2026', '2026-04-14', 'diterima', 'bukti_pembayaran/iTgumad28N1q5piD3oRD0CxE2yCLGin9W2ye81L9.png');

-- --------------------------------------------------------

--
-- Table structure for table `catatan_pelatih`
--

CREATE TABLE `catatan_pelatih` (
  `id_catatan` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_pelatih` int(11) NOT NULL,
  `catatan` text DEFAULT NULL,
  `tanggal_catatan` date DEFAULT curdate(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `catatan_pelatih`
--

INSERT INTO `catatan_pelatih` (`id_catatan`, `id_siswa`, `id_pelatih`, `catatan`, `tanggal_catatan`, `created_at`, `updated_at`) VALUES
(1, 9, 11, 'Latihan hari ini bagus Tignkatkan lagi', '2026-04-15', '2026-04-15 18:01:23', '2026-04-15 18:01:23'),
(2, 11, 11, 'Tes Catatan Update!', '2026-04-15', '2026-04-15 18:01:23', '2026-04-15 18:04:16');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id_feedback` int(11) NOT NULL,
  `id_pelatih` int(11) NOT NULL,
  `id_ortu` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `isi_feedback` varchar(500) DEFAULT NULL,
  `tanggal_feedback` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_latihan`
--

CREATE TABLE `jadwal_latihan` (
  `id_jadwal` int(11) NOT NULL,
  `id_pelatih` int(11) NOT NULL,
  `tanggal` date DEFAULT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `lokasi` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal_latihan`
--

INSERT INTO `jadwal_latihan` (`id_jadwal`, `id_pelatih`, `tanggal`, `jam_mulai`, `jam_selesai`, `lokasi`) VALUES
(1, 11, '2026-04-14', '00:00:09', '00:00:10', 'Lapangan Dakwah');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_siswa`
--

CREATE TABLE `jadwal_siswa` (
  `id_jadwal_siswa` int(11) NOT NULL,
  `id_jadwal` int(11) DEFAULT NULL,
  `id_siswa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal_siswa`
--

INSERT INTO `jadwal_siswa` (`id_jadwal_siswa`, `id_jadwal`, `id_siswa`) VALUES
(1, 1, 11),
(2, 1, 9),
(3, 1, 8);

-- --------------------------------------------------------

--
-- Table structure for table `master_badge`
--

CREATE TABLE `master_badge` (
  `id_badge` int(11) NOT NULL,
  `nama_badge` varchar(100) DEFAULT NULL,
  `deskripsi` varchar(500) DEFAULT NULL,
  `icon_badge` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id_notifikasi` int(11) NOT NULL,
  `judul` varchar(100) DEFAULT NULL,
  `isi` varchar(1000) DEFAULT NULL,
  `target_role` enum('orang_tua','pelatih','siswa','semua') DEFAULT NULL,
  `tanggal_kirim` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifikasi`
--

INSERT INTO `notifikasi` (`id_notifikasi`, `judul`, `isi`, `target_role`, `tanggal_kirim`) VALUES
(1, 'Test Notif Pelatih', 'Ini pesan Tes Notif Pelatih', 'pelatih', '2026-04-14 04:13:08'),
(2, 'Test Notif Siswa', 'Ini pesan Tes Notif Siswa', 'siswa', '2026-04-14 04:23:48'),
(3, 'Test Notif Pelatih', 'Ini pesan Tes Notif  Pelatih', 'pelatih', '2026-04-14 06:27:36'),
(4, 'Test Notif Siswa', 'Ini pesan Tes Notif  Siswa', 'siswa', '2026-04-14 06:30:49');

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi_terkirim`
--

CREATE TABLE `notifikasi_terkirim` (
  `id_notifikasi_terkirim` int(11) NOT NULL,
  `id_notifikasi` int(11) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `id_pelatih` int(11) DEFAULT NULL,
  `status_baca` enum('Belum Dibaca','Sudah Dibaca') NOT NULL DEFAULT 'Belum Dibaca',
  `tanggal_baca` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifikasi_terkirim`
--

INSERT INTO `notifikasi_terkirim` (`id_notifikasi_terkirim`, `id_notifikasi`, `user_id`, `id_siswa`, `id_admin`, `id_pelatih`, `status_baca`, `tanggal_baca`, `created_at`, `updated_at`) VALUES
(1, 1, 30, NULL, NULL, 12, 'Belum Dibaca', NULL, '2026-04-13 21:13:08', '2026-04-13 21:13:08'),
(2, 2, 16, 6, NULL, NULL, 'Belum Dibaca', NULL, '2026-04-13 21:23:48', '2026-04-13 21:23:48'),
(3, 3, 29, NULL, NULL, 11, 'Belum Dibaca', NULL, '2026-04-13 23:27:36', '2026-04-13 23:27:36'),
(4, 4, 32, 11, NULL, NULL, 'Belum Dibaca', NULL, '2026-04-13 23:30:49', '2026-04-13 23:30:49');

-- --------------------------------------------------------

--
-- Table structure for table `orang_tua`
--

CREATE TABLE `orang_tua` (
  `id_ortu` int(11) NOT NULL,
  `nama_ortu` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orang_tua`
--

INSERT INTO `orang_tua` (`id_ortu`, `nama_ortu`, `email`, `password`, `no_hp`, `user_id`) VALUES
(1, 'Heliyarti', 'Heliyarti@gmail.com', '$2y$10$Fs/5g.i7eaDAjjfQZ97ZjelcLJgjAsdPjkynb.gcl9pNmKQFCndDC', '089649209010', 4),
(10, 'Fakrul', 'fakrul@gmail.com', '$2y$10$C9oh9I4C.jGImA2KJlr3R.XcgUXY0AEkV.uU1t6YO8cpj67ek95Wm', '0085671295', 16),
(11, 'Suhatri', 'Suhartissb@gmail.com', '$2y$10$IjD/AYFCiuVPlFuz5kFH/.ZB5R1ofbN7pkbBB2AdiIV7oCGklEyFW', '089145607090', 31),
(12, 'Zulyfitriani', 'Zulyfitrianissb@gmail.com', '$2y$10$MkVDlC852V7xSyJmIh1X2eDIcp3v60zEIWvXITLkqyLrbJX1EYDeC', '08963929922', 32);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pelatih`
--

CREATE TABLE `pelatih` (
  `id_pelatih` int(11) NOT NULL,
  `nama_pelatih` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pelatih`
--

INSERT INTO `pelatih` (`id_pelatih`, `nama_pelatih`, `email`, `no_hp`, `user_id`) VALUES
(11, 'Bambang', 'Bambang@gmail.com', '+628964875667', 29),
(12, 'Zulfahmi', 'zulfahmi@gmail.com', '+6289649209010', 30);

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id_pembayaran` int(11) NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `periode` varchar(50) DEFAULT NULL,
  `jumlah` decimal(12,2) DEFAULT NULL,
  `tanggal_bayar` date DEFAULT NULL,
  `status` enum('Lunas','Belum') DEFAULT NULL,
  `jenis` enum('Pendaftaran','Harian','Bulanan') DEFAULT 'Bulanan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pembayaran`
--

INSERT INTO `pembayaran` (`id_pembayaran`, `id_siswa`, `periode`, `jumlah`, `tanggal_bayar`, `status`, `jenis`) VALUES
(1, 1, '2026', 200000.00, '2026-02-28', 'Lunas', 'Pendaftaran'),
(3, 6, '2026', 200000.00, '2026-03-11', 'Belum', 'Pendaftaran'),
(5, 8, '2026', 280000.00, '2026-03-11', 'Belum', 'Pendaftaran'),
(7, 11, '2026', 280000.00, '2026-04-14', 'Lunas', 'Pendaftaran');

-- --------------------------------------------------------

--
-- Table structure for table `pencapaian`
--

CREATE TABLE `pencapaian` (
  `id_pencapaian` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `id_badge` int(11) NOT NULL,
  `tanggal_diberikan` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pendaftaran`
--

CREATE TABLE `pendaftaran` (
  `id_pendaftaran` int(11) NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `tanggal_daftar` date DEFAULT NULL,
  `status_approval` enum('Menunggu','Disetujui','Ditolak','Revisi') DEFAULT NULL,
  `val_nama_siswa` varchar(20) DEFAULT NULL,
  `val_nama_ibu` varchar(20) DEFAULT NULL,
  `val_nama_ayah` varchar(20) DEFAULT NULL,
  `val_umur` varchar(20) DEFAULT NULL,
  `val_akta` varchar(20) DEFAULT NULL,
  `val_kk` varchar(20) DEFAULT NULL,
  `val_rapor` varchar(20) DEFAULT NULL,
  `val_foto` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pendaftaran`
--

INSERT INTO `pendaftaran` (`id_pendaftaran`, `id_siswa`, `tanggal_daftar`, `status_approval`, `val_nama_siswa`, `val_nama_ibu`, `val_nama_ayah`, `val_umur`, `val_akta`, `val_kk`, `val_rapor`, `val_foto`) VALUES
(1, 1, '2026-02-28', 'Disetujui', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid'),
(6, 6, '2026-03-07', 'Disetujui', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid'),
(8, 8, '2026-03-11', 'Disetujui', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid'),
(9, 9, '2026-04-12', 'Menunggu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 11, '2026-04-14', 'Disetujui', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid', 'valid');

-- --------------------------------------------------------

--
-- Table structure for table `performa_siswa`
--

CREATE TABLE `performa_siswa` (
  `id_performa` int(11) NOT NULL,
  `id_siswa` int(11) NOT NULL,
  `tanggal_penilaian` date DEFAULT NULL,
  `dribbling` int(11) DEFAULT NULL,
  `passing` int(11) DEFAULT NULL,
  `shooting` int(11) DEFAULT NULL,
  `rata_rata` decimal(5,2) DEFAULT NULL,
  `keterangan` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(2, 'App\\Models\\User', 31, 'api-token', 'bb2931b9fb081d56de21a83e367ca3abcdf7239dd8154c3efeb970ee6546aa89', '[\"*\"]', '2026-04-11 23:55:41', NULL, '2026-04-11 23:35:09', '2026-04-11 23:55:41'),
(11, 'App\\Models\\User', 16, 'auth_token', '0b80ef8232c8109feff47e5f8b21712a53eb1455fbf9a1f5c81cc0da7e5f6545', '[\"*\"]', '2026-04-13 21:35:58', NULL, '2026-04-13 21:22:17', '2026-04-13 21:35:58'),
(12, 'App\\Models\\User', 29, 'auth_token', '60190c130598c40b3d7691810f3833141ab70525857d2156c29c5f98f770709f', '[\"*\"]', '2026-04-13 23:29:08', NULL, '2026-04-13 21:32:05', '2026-04-13 23:29:08'),
(15, 'App\\Models\\User', 32, 'auth_token', 'd688ac5070e2efec22c0dbafde27069ad90cf519b3b72a458c8d959e196ff319', '[\"*\"]', '2026-04-14 11:50:36', NULL, '2026-04-14 00:18:32', '2026-04-14 11:50:36'),
(19, 'App\\Models\\User', 7, 'auth_token', '538cd3ee9bc06c0607324f36db0ba9714282e6c04b4f74a01727dbab051044c5', '[\"*\"]', NULL, NULL, '2026-04-15 17:45:55', '2026-04-15 17:45:55'),
(20, 'App\\Models\\User', 30, 'auth_token', 'c65936e9b9b9481c1ea8a47b4e48b2e1aab4a83f9a33bbfaa1a1148e80b6bf8b', '[\"*\"]', NULL, NULL, '2026-04-15 17:59:13', '2026-04-15 17:59:13');

-- --------------------------------------------------------

--
-- Table structure for table `presensi`
--

CREATE TABLE `presensi` (
  `id_presensi` int(11) NOT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `id_jadwal` int(11) DEFAULT NULL,
  `status_kehadiran` enum('Hadir','Sakit','Izin') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `presensi`
--

INSERT INTO `presensi` (`id_presensi`, `id_siswa`, `id_jadwal`, `status_kehadiran`, `created_at`, `updated_at`) VALUES
(2, 8, 1, 'Hadir', '2026-04-15 07:50:16', '2026-04-15 07:50:16'),
(3, 9, 1, 'Izin', '2026-04-15 07:50:16', '2026-04-15 07:50:16'),
(4, 11, 1, 'Sakit', '2026-04-15 07:54:45', '2026-04-15 07:54:45');

-- --------------------------------------------------------

--
-- Table structure for table `profil_siswa`
--

CREATE TABLE `profil_siswa` (
  `id_siswa` int(11) NOT NULL,
  `id_ortu` int(11) NOT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `tinggi_badan` int(11) DEFAULT NULL,
  `berat_badan` int(11) DEFAULT NULL,
  `prestasi` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promosi`
--

CREATE TABLE `promosi` (
  `id_promosi` int(11) NOT NULL,
  `judul` varchar(100) DEFAULT NULL,
  `isi_promosi` varchar(500) DEFAULT NULL,
  `tanggal_promosi` date DEFAULT NULL,
  `dibuat_oleh` int(11) DEFAULT NULL,
  `foto_promosi` varchar(255) DEFAULT NULL,
  `kategori` enum('Akun Sosial','Berita') DEFAULT 'Berita'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `respon_feedback`
--

CREATE TABLE `respon_feedback` (
  `id_respon` int(11) NOT NULL,
  `id_feedback` int(11) DEFAULT NULL,
  `id_ortu` int(11) DEFAULT NULL,
  `isi_respon` varchar(500) DEFAULT NULL,
  `tanggal_respon` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_promosi`
--

CREATE TABLE `riwayat_promosi` (
  `id_riwayat` int(11) NOT NULL,
  `id_promosi` int(11) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `role` enum('orang_tua','pelatih','siswa') DEFAULT NULL,
  `tanggal_dibaca` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id_siswa` int(11) NOT NULL,
  `nama_siswa` varchar(100) DEFAULT NULL,
  `nama_ibu` varchar(255) DEFAULT NULL,
  `nama_ayah` varchar(255) DEFAULT NULL,
  `umur` int(11) NOT NULL,
  `id_ortu` int(11) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `akta_kelahiran` varchar(255) DEFAULT NULL,
  `kartu_keluarga` varchar(255) DEFAULT NULL,
  `rapor` varchar(255) DEFAULT NULL,
  `pas_photo_3x4` varchar(255) DEFAULT NULL,
  `status` enum('Inactive','Active') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id_siswa`, `nama_siswa`, `nama_ibu`, `nama_ayah`, `umur`, `id_ortu`, `user_id`, `akta_kelahiran`, `kartu_keluarga`, `rapor`, `pas_photo_3x4`, `status`) VALUES
(1, 'Bagus Fachfirli', 'Heliyarti', 'Edison', 12, 1, 4, 'QhShHdqk0nad6gL3DBhMpo8ncCUIGAbdy7CmVxan.png', 'Pa4tJXxoYmOz7SM6h4ucZcTXBmLkCmv864x9Gxlm.png', 'TFZf5MaVenLw79kBzsmm5935YPrRuvHZqczuDw6N.png', 'nZHIpkaqXXR8NrpaZkKfH1YbJyiMrHYhXv8aayVZ.png', 'Active'),
(6, 'Zaky Fahreza', 'Nurisman', 'Fakrul', 10, 10, 16, '29xVlNjCKDDMhPmzgKC0J0JNJvYH0ZbLcLfIXkSI.png', 'KC8QKueWfVhczzwHHlQ82fRAIecIPYR2miAGFBxw.png', '2LQvoFRvxJcHjd36eghLNPmRL8AfsLmvtzNEymnw.png', 'UK1EfmNsepIxvPSfWPfvUoaY5zAOv7KlHtUyDYy2.png', 'Inactive'),
(8, 'Rinov Ramadhans', 'Nurisman', 'Fakrul', 10, 10, 16, 'T2jDZHOKqLLH2ZS5ComDIXxOzmE7dYfnB4I7LhKM.png', '4B0Yyjd4CAESPUNA7ZD6BbLNmHzBLurep7IKpTok.png', 'ok4qpizm9OtdHWovWq9CmLwRc23QV6fXXEmnFJ7t.png', 's2E7KcQcjIWIxyonmYdDj29gB9GEpwBbiS0VvqUM.png', 'Inactive'),
(9, 'Ibrahim Mufid Zaki', 'Suharti', 'Suharti', 16, 11, 31, 'akta/NxmUhMMUnEto6JyJ2USVLCPM9CpcfA2PqqGhpVTo.png', 'kk/HdKlHWJZP2Vh2FDPXMAzaWl7XjqsEsLsuGdaEVhh.png', 'rapor/kd87yeIq2FxpQxUc3lrGg5wHf9b8cqHvwdvOy5eT.png', 'foto/44RxOP51GZYBabry2K8VEiLt8Nrn7qjggiHXLoOc.png', 'Inactive'),
(11, 'Muhammad Lionell Shazwa', 'Zulyfitriani', 'Zulyfitriani', 16, 12, 32, 'qMe0N8YfQuaKrSwwjAAj9i1JNIudxu0uokXrnMTV.png', 'bO1JBmCOiMP9l0KgQn2GRSFVaCyuJ5NMT1MvqV9I.png', 'D96rDLz10aIMhIrquImkNQyQG0iWTsRF8XMS429M.png', 'EIMPXdLxZ1dwLbEaAHnHQLDpnU9MVEXCoedbUvCo.png', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'siswa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `role`) VALUES
(4, 'Heliyarti', 'Heliyarti@gmail.com', NULL, '$2y$10$O..ocw/0lV7G2O8bGh0sYuxO/7r8O.wXlxbdJZA8fcqcQiaA1cl6W', NULL, '2026-01-21 01:47:30', '2026-01-21 04:14:43', 'orang_tua'),
(7, 'Admin_SSB1', 'adminssb@gmail.com', NULL, '$2y$10$7gp2WxRU4xKsN2FkZnvH6uPFtHedaB9dJzk.WFWPF.yWanvQek3W6', NULL, '2026-01-25 02:02:36', '2026-01-25 02:02:36', 'admin'),
(16, 'Fakrul', 'fakrul@gmail.com', NULL, '$2y$10$P0.aYUkwKwE6vC3fsOOWs.x1IZCqvnLAUF9mj2qII4UxIunTo5fX6', NULL, '2026-03-02 03:44:07', '2026-03-02 03:44:07', 'orang_tua'),
(29, 'Bambang', 'Bambang@gmail.com', NULL, '$2y$10$j4h4U7OYdUni8dn.3KKpJO9bb1hhKff1dNm3ip7wV3xu9anZJM5Zm', NULL, '2026-04-02 06:49:55', '2026-04-02 06:49:55', 'pelatih'),
(30, 'Zulfahmi', 'zulfahmi@gmail.com', NULL, '$2y$10$t7NyR/p1xi/lr5XQpkEqIO4RVeIqyzy1f8pSeLj5sXD41yJJ/M8hi', NULL, '2026-04-09 12:32:22', '2026-04-09 12:32:22', 'pelatih'),
(31, 'Suhatri', 'Suhartissb@gmail.com', NULL, '$2y$10$MjjwPUVQWy0/zj.aUTZM8.mn1VbtktlygDt.H5EWDrtbire/ohd5m', NULL, '2026-04-11 09:06:13', '2026-04-11 09:06:13', 'orang_tua'),
(32, 'Zulyfitriani', 'Zulyfitrianissb@gmail.com', NULL, '$2y$10$d9tE9CkvZ5vNeHOJkU5g0e/1IFfjryBlyxyuWZOPeUKiqiDN7IKrK', NULL, '2026-04-13 16:36:47', '2026-04-13 16:36:47', 'orang_tua');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_admin_user` (`user_id`);

--
-- Indexes for table `bukti_pembayaran`
--
ALTER TABLE `bukti_pembayaran`
  ADD PRIMARY KEY (`id_bukti_pembayaran`),
  ADD KEY `id_pembayaran` (`id_pembayaran`),
  ADD KEY `id_siswa` (`id_siswa`);

--
-- Indexes for table `catatan_pelatih`
--
ALTER TABLE `catatan_pelatih`
  ADD PRIMARY KEY (`id_catatan`),
  ADD KEY `fk_cttn_siswa` (`id_siswa`),
  ADD KEY `fk_cttn_pelatih` (`id_pelatih`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id_feedback`),
  ADD KEY `id_pelatih` (`id_pelatih`),
  ADD KEY `id_ortu` (`id_ortu`),
  ADD KEY `id_siswa` (`id_siswa`);

--
-- Indexes for table `jadwal_latihan`
--
ALTER TABLE `jadwal_latihan`
  ADD PRIMARY KEY (`id_jadwal`),
  ADD KEY `fk_jadwal_pelatih` (`id_pelatih`);

--
-- Indexes for table `jadwal_siswa`
--
ALTER TABLE `jadwal_siswa`
  ADD PRIMARY KEY (`id_jadwal_siswa`),
  ADD KEY `fk_jadwal` (`id_jadwal`),
  ADD KEY `fk_siswa` (`id_siswa`);

--
-- Indexes for table `master_badge`
--
ALTER TABLE `master_badge`
  ADD PRIMARY KEY (`id_badge`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id_notifikasi`);

--
-- Indexes for table `notifikasi_terkirim`
--
ALTER TABLE `notifikasi_terkirim`
  ADD PRIMARY KEY (`id_notifikasi_terkirim`),
  ADD KEY `id_notifikasi` (`id_notifikasi`),
  ADD KEY `fk_notifikasi_siswa` (`id_siswa`),
  ADD KEY `fk_notifikasi_admin` (`id_admin`),
  ADD KEY `fk_notifikasi_pelatih` (`id_pelatih`),
  ADD KEY `fk_users` (`user_id`);

--
-- Indexes for table `orang_tua`
--
ALTER TABLE `orang_tua`
  ADD PRIMARY KEY (`id_ortu`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_orangtua_user` (`user_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `pelatih`
--
ALTER TABLE `pelatih`
  ADD PRIMARY KEY (`id_pelatih`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_pelatih_user` (`user_id`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD KEY `id_siswa` (`id_siswa`);

--
-- Indexes for table `pencapaian`
--
ALTER TABLE `pencapaian`
  ADD PRIMARY KEY (`id_pencapaian`),
  ADD KEY `id_siswa` (`id_siswa`),
  ADD KEY `id_badge` (`id_badge`);

--
-- Indexes for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  ADD PRIMARY KEY (`id_pendaftaran`),
  ADD KEY `id_siswa` (`id_siswa`);

--
-- Indexes for table `performa_siswa`
--
ALTER TABLE `performa_siswa`
  ADD PRIMARY KEY (`id_performa`),
  ADD KEY `id_siswa` (`id_siswa`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `presensi`
--
ALTER TABLE `presensi`
  ADD PRIMARY KEY (`id_presensi`),
  ADD KEY `id_siswa` (`id_siswa`),
  ADD KEY `id_jadwal` (`id_jadwal`);

--
-- Indexes for table `profil_siswa`
--
ALTER TABLE `profil_siswa`
  ADD PRIMARY KEY (`id_siswa`),
  ADD KEY `id_ortu` (`id_ortu`);

--
-- Indexes for table `promosi`
--
ALTER TABLE `promosi`
  ADD PRIMARY KEY (`id_promosi`),
  ADD KEY `dibuat_oleh` (`dibuat_oleh`);

--
-- Indexes for table `respon_feedback`
--
ALTER TABLE `respon_feedback`
  ADD PRIMARY KEY (`id_respon`),
  ADD KEY `id_feedback` (`id_feedback`),
  ADD KEY `id_ortu` (`id_ortu`);

--
-- Indexes for table `riwayat_promosi`
--
ALTER TABLE `riwayat_promosi`
  ADD PRIMARY KEY (`id_riwayat`),
  ADD KEY `id_promosi` (`id_promosi`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id_siswa`),
  ADD KEY `id_ortu` (`id_ortu`),
  ADD KEY `fk_siswa_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bukti_pembayaran`
--
ALTER TABLE `bukti_pembayaran`
  MODIFY `id_bukti_pembayaran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `catatan_pelatih`
--
ALTER TABLE `catatan_pelatih`
  MODIFY `id_catatan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id_feedback` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jadwal_latihan`
--
ALTER TABLE `jadwal_latihan`
  MODIFY `id_jadwal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jadwal_siswa`
--
ALTER TABLE `jadwal_siswa`
  MODIFY `id_jadwal_siswa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `master_badge`
--
ALTER TABLE `master_badge`
  MODIFY `id_badge` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id_notifikasi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifikasi_terkirim`
--
ALTER TABLE `notifikasi_terkirim`
  MODIFY `id_notifikasi_terkirim` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orang_tua`
--
ALTER TABLE `orang_tua`
  MODIFY `id_ortu` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `pelatih`
--
ALTER TABLE `pelatih`
  MODIFY `id_pelatih` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id_pembayaran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pencapaian`
--
ALTER TABLE `pencapaian`
  MODIFY `id_pencapaian` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  MODIFY `id_pendaftaran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `performa_siswa`
--
ALTER TABLE `performa_siswa`
  MODIFY `id_performa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `presensi`
--
ALTER TABLE `presensi`
  MODIFY `id_presensi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `promosi`
--
ALTER TABLE `promosi`
  MODIFY `id_promosi` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `respon_feedback`
--
ALTER TABLE `respon_feedback`
  MODIFY `id_respon` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `riwayat_promosi`
--
ALTER TABLE `riwayat_promosi`
  MODIFY `id_riwayat` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id_siswa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bukti_pembayaran`
--
ALTER TABLE `bukti_pembayaran`
  ADD CONSTRAINT `bukti_pembayaran_ibfk_1` FOREIGN KEY (`id_pembayaran`) REFERENCES `pembayaran` (`id_pembayaran`),
  ADD CONSTRAINT `bukti_pembayaran_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`);

--
-- Constraints for table `catatan_pelatih`
--
ALTER TABLE `catatan_pelatih`
  ADD CONSTRAINT `fk_cttn_pelatih` FOREIGN KEY (`id_pelatih`) REFERENCES `pelatih` (`id_pelatih`),
  ADD CONSTRAINT `fk_cttn_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`id_pelatih`) REFERENCES `pelatih` (`id_pelatih`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`id_ortu`) REFERENCES `orang_tua` (`id_ortu`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE;

--
-- Constraints for table `jadwal_latihan`
--
ALTER TABLE `jadwal_latihan`
  ADD CONSTRAINT `fk_jadwal_pelatih` FOREIGN KEY (`id_pelatih`) REFERENCES `pelatih` (`id_pelatih`);

--
-- Constraints for table `jadwal_siswa`
--
ALTER TABLE `jadwal_siswa`
  ADD CONSTRAINT `fk_jadwal` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal_latihan` (`id_jadwal`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE;

--
-- Constraints for table `notifikasi_terkirim`
--
ALTER TABLE `notifikasi_terkirim`
  ADD CONSTRAINT `fk_notifikasi_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`),
  ADD CONSTRAINT `fk_notifikasi_pelatih` FOREIGN KEY (`id_pelatih`) REFERENCES `pelatih` (`id_pelatih`),
  ADD CONSTRAINT `fk_notifikasi_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`),
  ADD CONSTRAINT `fk_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifikasi_terkirim_ibfk_1` FOREIGN KEY (`id_notifikasi`) REFERENCES `notifikasi` (`id_notifikasi`);

--
-- Constraints for table `orang_tua`
--
ALTER TABLE `orang_tua`
  ADD CONSTRAINT `fk_orangtua_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pelatih`
--
ALTER TABLE `pelatih`
  ADD CONSTRAINT `fk_pelatih_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`);

--
-- Constraints for table `pencapaian`
--
ALTER TABLE `pencapaian`
  ADD CONSTRAINT `pencapaian_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE,
  ADD CONSTRAINT `pencapaian_ibfk_2` FOREIGN KEY (`id_badge`) REFERENCES `master_badge` (`id_badge`) ON DELETE CASCADE;

--
-- Constraints for table `pendaftaran`
--
ALTER TABLE `pendaftaran`
  ADD CONSTRAINT `pendaftaran_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`);

--
-- Constraints for table `performa_siswa`
--
ALTER TABLE `performa_siswa`
  ADD CONSTRAINT `performa_siswa_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE;

--
-- Constraints for table `presensi`
--
ALTER TABLE `presensi`
  ADD CONSTRAINT `presensi_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`),
  ADD CONSTRAINT `presensi_ibfk_2` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal_latihan` (`id_jadwal`);

--
-- Constraints for table `profil_siswa`
--
ALTER TABLE `profil_siswa`
  ADD CONSTRAINT `profil_siswa_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE,
  ADD CONSTRAINT `profil_siswa_ibfk_2` FOREIGN KEY (`id_ortu`) REFERENCES `orang_tua` (`id_ortu`) ON DELETE CASCADE;

--
-- Constraints for table `promosi`
--
ALTER TABLE `promosi`
  ADD CONSTRAINT `promosi_ibfk_1` FOREIGN KEY (`dibuat_oleh`) REFERENCES `admin` (`id_admin`);

--
-- Constraints for table `respon_feedback`
--
ALTER TABLE `respon_feedback`
  ADD CONSTRAINT `respon_feedback_ibfk_1` FOREIGN KEY (`id_feedback`) REFERENCES `feedback` (`id_feedback`),
  ADD CONSTRAINT `respon_feedback_ibfk_2` FOREIGN KEY (`id_ortu`) REFERENCES `orang_tua` (`id_ortu`);

--
-- Constraints for table `riwayat_promosi`
--
ALTER TABLE `riwayat_promosi`
  ADD CONSTRAINT `riwayat_promosi_ibfk_1` FOREIGN KEY (`id_promosi`) REFERENCES `promosi` (`id_promosi`);

--
-- Constraints for table `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `fk_siswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`id_ortu`) REFERENCES `orang_tua` (`id_ortu`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
