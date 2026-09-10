-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Сен 10 2026 г., 23:10
-- Версия сервера: 10.6.27-MariaDB-cll-lve
-- Версия PHP: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `p-318444_theater_db`
--

-- --------------------------------------------------------

--
-- Структура таблицы `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `staff_id` int(10) UNSIGNED DEFAULT NULL,
  `performed_by_type` enum('admin','staff','system','api') NOT NULL DEFAULT 'admin',
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_data`)),
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `cash_audit_log`
--

CREATE TABLE `cash_audit_log` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(64) NOT NULL,
  `target_type` varchar(32) DEFAULT NULL,
  `target_id` varchar(64) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `cash_audit_log`
--

INSERT INTO `cash_audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `created_at`) VALUES
(238, 0, 'sale:create', 'session', '67', '{\"seats\":[\"16-10\"],\"tx_id\":\"181\",\"amount\":500000,\"uids\":[\"d6097f8ea8170d77\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-08-27 19:59:13'),
(239, 0, 'sale:create', 'session', '64', '{\"seats\":[\"7-10\",\"7-11\",\"8-1\"],\"tx_id\":\"182\",\"amount\":1600000,\"uids\":[\"811524d93ea1e0a9\",\"d4c795ca4fa21bc9\",\"a265f285d4c67c2f\"],\"customer_id\":21,\"discount\":{\"final_total\":20000,\"applied\":[]}}', '2026-08-28 10:27:33'),
(240, 0, 'sale:create', 'session', '64', '{\"seats\":[\"8-1\",\"7-10\"],\"tx_id\":\"183\",\"amount\":1200000,\"uids\":[\"e07b9f1621343359\",\"5d130a9dcb337040\"],\"customer_id\":21,\"discount\":{\"final_total\":15000,\"applied\":[]}}', '2026-08-28 10:29:13'),
(241, 0, 'sale:create', 'session', '67', '{\"seats\":[\"16-20\",\"16-21\"],\"tx_id\":\"184\",\"amount\":800000,\"uids\":[\"1b4d4851010d6cc3\",\"78b2dfab43d3ecae\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-08-28 11:41:23'),
(242, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-20\",\"14-21\",\"14-22\"],\"tx_id\":\"185\",\"amount\":750000,\"uids\":[\"9c464ccf1ae74840\",\"65eddf40ff652d8f\",\"7c96a3de39389a2e\"],\"customer_id\":21,\"discount\":{\"final_total\":15000,\"applied\":[]}}', '2026-08-31 17:41:07'),
(243, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-20\",\"14-21\"],\"tx_id\":\"186\",\"amount\":500000,\"uids\":[\"5e83fbee47dc01e3\",\"8d42b99862d113d0\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-08-31 17:46:09'),
(244, 0, 'sale:create', 'session', '67', '{\"seats\":[\"13-20\",\"13-21\"],\"tx_id\":\"187\",\"amount\":800000,\"uids\":[\"f05e1f0ad4c8ab54\",\"d0eea4309286d4be\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-08-31 18:03:35'),
(245, 0, 'sale:create', 'session', '67', '{\"seats\":[\"15-20\",\"15-21\"],\"tx_id\":\"188\",\"amount\":800000,\"uids\":[\"5f083f6103537260\",\"81fe9f6579ece21f\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-01 10:56:24'),
(246, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-20\",\"14-21\"],\"tx_id\":\"189\",\"amount\":800000,\"uids\":[\"a4cd081d9490c44e\",\"192af95b1f64967e\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-01 11:00:19'),
(247, 0, 'sale:create', 'session', '67', '{\"seats\":[\"12-20\",\"12-21\"],\"tx_id\":\"190\",\"amount\":800000,\"uids\":[\"e94765f4408f7f61\",\"bfeeca1c673d1b9d\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-01 12:08:45'),
(248, 0, 'sale:create', 'session', '67', '{\"seats\":[\"16-10\",\"16-20\"],\"tx_id\":\"191\",\"amount\":1200000,\"uids\":[\"b21ead03857edd1a\",\"f69c60b0ac76aa55\"],\"customer_id\":21,\"discount\":{\"final_total\":15000,\"applied\":[]}}', '2026-09-01 13:22:57'),
(249, 0, 'sale:create', 'session', '67', '{\"seats\":[\"12-21\",\"12-22\"],\"tx_id\":\"192\",\"amount\":600000,\"uids\":[\"cb184b4490feb40d\",\"6ba03511f1206203\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-01 17:39:01'),
(250, 0, 'sale:create', 'session', '64', '{\"seats\":[\"5-17\",\"5-18\",\"8-1\"],\"tx_id\":\"193\",\"amount\":2000000,\"uids\":[\"c73b9b17c3c176b4\",\"22a0e6ad68f0f26e\",\"d1dfb2884df1d188\"],\"customer_id\":21,\"discount\":{\"final_total\":20000,\"applied\":[]}}', '2026-09-01 17:39:59'),
(251, 0, 'sale:create', 'session', '64', '{\"seats\":[\"7-10\"],\"tx_id\":\"194\",\"amount\":250000,\"uids\":[\"e00d9d8f33067298\"],\"customer_id\":21,\"discount\":{\"final_total\":5000,\"applied\":[]}}', '2026-09-01 17:52:55'),
(252, 0, 'sale:create', 'session', '67', '{\"seats\":[\"12-25\",\"12-24\"],\"tx_id\":\"195\",\"amount\":500000,\"uids\":[\"7a339f845f1cbd36\",\"87ba6b4b0227eade\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-01 18:02:42'),
(253, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-24\",\"14-25\"],\"tx_id\":\"196\",\"amount\":800000,\"uids\":[\"569f844f8e7807da\",\"59f39d44d0d13b3d\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-01 18:11:11'),
(254, 0, 'sale:create', 'session', '67', '{\"seats\":[\"15-24\",\"15-25\"],\"tx_id\":\"197\",\"amount\":600000,\"uids\":[\"6141d1df65196a6b\",\"315bd62343c5fc1c\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-01 18:13:07'),
(255, 0, 'sale:create', 'session', '67', '{\"seats\":[\"12-20\"],\"tx_id\":\"198\",\"amount\":400000,\"uids\":[\"54d4033973ec68eb\"],\"customer_id\":21,\"discount\":{\"final_total\":5000,\"applied\":[]}}', '2026-09-01 18:21:06'),
(256, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-20\",\"14-21\"],\"tx_id\":\"199\",\"amount\":800000,\"uids\":[\"1a8e5ef9ac878b8c\",\"349699ef83e5d5e2\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-02 10:55:08'),
(257, 0, 'sale:create', 'session', '67', '{\"seats\":[\"15-20\",\"15-21\"],\"tx_id\":\"200\",\"amount\":800000,\"uids\":[\"d71bab0018a81e69\",\"95fbe4f65406328c\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-02 10:55:39'),
(258, 0, 'sale:create', 'session', '67', '{\"seats\":[\"15-22\"],\"tx_id\":\"201\",\"amount\":400000,\"uids\":[\"a2e85687b6c859e5\"],\"customer_id\":21,\"discount\":{\"final_total\":5000,\"applied\":[]}}', '2026-09-02 11:39:21'),
(259, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-22\"],\"tx_id\":\"202\",\"amount\":400000,\"uids\":[\"d5e904b28a6297f0\"],\"customer_id\":21,\"discount\":{\"final_total\":5000,\"applied\":[]}}', '2026-09-02 11:39:58'),
(260, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-23\"],\"tx_id\":\"203\",\"amount\":400000,\"uids\":[\"28d8163f10455550\"],\"customer_id\":21,\"discount\":{\"final_total\":5000,\"applied\":[]}}', '2026-09-02 11:41:04'),
(261, 0, 'sale:create', 'session', '67', '{\"seats\":[\"15-23\"],\"tx_id\":\"204\",\"amount\":400000,\"uids\":[\"8d55f2040e3706ce\"],\"customer_id\":21,\"discount\":{\"final_total\":5000,\"applied\":[]}}', '2026-09-02 11:46:21'),
(262, 0, 'sale:create', 'session', '67', '{\"seats\":[\"12-20\",\"12-21\"],\"tx_id\":\"205\",\"amount\":800000,\"uids\":[\"a5e0c73132cedd7c\",\"0422d1a4be12516e\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-02 11:56:53'),
(263, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-20\",\"14-21\"],\"tx_id\":\"206\",\"amount\":800000,\"uids\":[\"af7b1cb850785240\",\"ce23e1b92c56a1e3\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-02 12:07:29'),
(264, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-22\"],\"tx_id\":\"207\",\"amount\":400000,\"uids\":[\"f68778db93899a74\"],\"customer_id\":21,\"discount\":{\"final_total\":5000,\"applied\":[]}}', '2026-09-02 12:12:22'),
(265, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-23\",\"14-24\"],\"tx_id\":\"208\",\"amount\":800000,\"uids\":[\"8cb8c068f1ad72c7\",\"1a9c17ab383e6ac1\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":[]}}', '2026-09-02 12:14:34'),
(266, 0, 'sale:create', 'session', '67', '{\"seats\":[\"16-10\",\"13-20\"],\"tx_id\":\"209\",\"amount\":1200000,\"uids\":[\"242c6ba6a0809daf\",\"54d1f82e2fffca14\"],\"customer_id\":21,\"discount\":{\"final_total\":15000,\"applied\":[]}}', '2026-09-02 12:19:34'),
(267, 0, 'sale:create', 'session', '67', '{\"seats\":[\"16-10\",\"16-20\"],\"tx_id\":\"210\",\"amount\":900000,\"uids\":[\"0d8bebd1c7dba490\",\"f61f5280b6e668e4\"],\"customer_id\":21,\"discount\":{\"final_total\":15000,\"applied\":[]}}', '2026-09-02 12:21:24'),
(268, 0, 'hold:create', 'session', '67', '{\"seats\":[\"15-21\"],\"ttl\":10}', '2026-09-02 13:35:28'),
(269, 0, 'hold:create', 'session', '67', '{\"seats\":[\"14-23\"],\"ttl\":10}', '2026-09-02 13:40:53'),
(270, 0, 'hold:create', 'session', '67', '{\"seats\":[\"16-21\",\"14-22\",\"14-23\",\"12-21\"],\"ttl\":10}', '2026-09-02 14:49:16'),
(271, 0, 'hold:create', 'session', '67', '{\"seats\":[\"13-25\"],\"ttl\":10}', '2026-09-02 14:50:26'),
(272, 0, 'hold:create', 'session', '67', '{\"seats\":[\"13-23\"],\"ttl\":10}', '2026-09-02 14:54:32'),
(273, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-23\"],\"tx_id\":\"211\",\"amount\":2500,\"uids\":[\"7064c679a066f3b4\"],\"customer_id\":21,\"discount\":{\"final_total\":2500,\"applied\":{\"segment\":\"child\",\"auto_percent\":0,\"auto_amount\":0,\"custom_type\":\"none\",\"custom_value\":0,\"custom_amount\":0,\"total_discount\":0}}}', '2026-09-02 15:28:37'),
(274, 0, 'hold:create', 'session', '67', '{\"seats\":[\"14-24\"],\"ttl\":60}', '2026-09-02 16:36:56'),
(275, 0, 'sale:create', 'session', '67', '{\"seats\":[\"14-24\"],\"tx_id\":\"212\",\"amount\":3500,\"uids\":[\"fa62acd86e709c42\"],\"customer_id\":21,\"discount\":{\"final_total\":2000,\"applied\":{\"segment\":\"manual\",\"auto_percent\":0,\"auto_amount\":0,\"custom_type\":\"fixed\",\"custom_value\":1500,\"custom_amount\":1500,\"total_discount\":1500}}}', '2026-09-02 16:37:44'),
(276, 0, 'sale:create', 'session', '67', '{\"seats\":[\"1-5\"],\"tx_id\":\"213\",\"amount\":10000,\"uids\":[\"5e1f4cf9a0c51b2e\"],\"customer_id\":21,\"discount\":{\"final_total\":10000,\"applied\":{\"segment\":\"child\",\"auto_percent\":0,\"auto_amount\":0,\"custom_type\":\"none\",\"custom_value\":0,\"custom_amount\":0,\"total_discount\":0}}}', '2026-09-02 19:39:08'),
(277, 0, 'sale:create', 'session', '64', '{\"seats\":[\"8-1\",\"7-18\",\"3-17\"],\"tx_id\":\"214\",\"amount\":16000,\"uids\":[\"63e4bb78714eabfb\",\"30697ebca5358f22\",\"aa1ec26e7b928be2\"],\"customer_id\":21,\"discount\":{\"final_total\":16000,\"applied\":{\"segment\":\"student\",\"auto_percent\":0,\"auto_amount\":0,\"custom_type\":\"none\",\"custom_value\":0,\"custom_amount\":0,\"total_discount\":0}}}', '2026-09-05 13:55:10'),
(278, 0, 'hold:create', 'session', '67', '{\"seats\":[\"15-22\"],\"ttl\":10}', '2026-09-08 16:47:52'),
(279, 0, 'hold:create', 'session', '67', '{\"seats\":[\"12-23\"],\"ttl\":10}', '2026-09-08 17:03:50'),
(280, 0, 'hold:create', 'session', '67', '{\"seats\":[\"13-23\"],\"ttl\":10}', '2026-09-08 17:20:01'),
(281, 0, 'hold:create', 'session', '67', '{\"seats\":[\"13-23\"],\"ttl\":10}', '2026-09-08 17:20:18');

-- --------------------------------------------------------

--
-- Структура таблицы `cash_holds`
--

CREATE TABLE `cash_holds` (
  `id` bigint(20) NOT NULL,
  `session_id` int(11) NOT NULL,
  `seat_key` varchar(32) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `cash_transactions`
--

CREATE TABLE `cash_transactions` (
  `id` bigint(20) NOT NULL,
  `type` enum('sale','refund','correction') NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `amount_cents` int(11) NOT NULL,
  `currency` varchar(8) DEFAULT 'KZT',
  `payment_method` varchar(32) DEFAULT 'cash',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `ticket_uids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ticket_uids`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `cash_transactions`
--

INSERT INTO `cash_transactions` (`id`, `type`, `session_id`, `user_id`, `customer_id`, `amount_cents`, `currency`, `payment_method`, `payload`, `ticket_uids`, `created_at`) VALUES
(191, 'sale', 67, 0, 21, 1200000, 'KZT', 'card', '{\"action\":\"sale\",\"seats\":[{\"id\":null,\"identifier\":\"16-10\",\"price\":10000,\"customer_segment\":\"student\"},{\"id\":null,\"identifier\":\"16-20\",\"price\":5000,\"customer_segment\":\"student\"}],\"seats_final\":[{\"identifier\":\"16-10\",\"original_price\":10000,\"final_price\":10000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":1000000,\"final_price_cents\":1000000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null},{\"identifier\":\"16-20\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"discount\":{\"final_total\":15000,\"applied\":[]},\"base_total\":15000,\"final_total\":15000,\"base_total_cents\":1500000,\"final_total_cents\":1200000}', '[\"b21ead03857edd1a\",\"f69c60b0ac76aa55\"]', '2026-09-01 13:22:56'),
(192, 'sale', 67, 0, 21, 600000, 'KZT', 'cash', '{\"action\":\"sale\",\"seats\":[{\"id\":null,\"identifier\":\"12-21\",\"price\":5000,\"customer_segment\":\"senior\"},{\"id\":null,\"identifier\":\"12-22\",\"price\":5000,\"customer_segment\":\"senior\"}],\"seats_final\":[{\"identifier\":\"12-21\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"senior\",\"auto_discount\":null,\"custom_discount\":null},{\"identifier\":\"12-22\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"senior\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"senior\",\"discount\":{\"final_total\":10000,\"applied\":[]},\"base_total\":10000,\"final_total\":10000,\"base_total_cents\":1000000,\"final_total_cents\":600000}', '[\"cb184b4490feb40d\",\"6ba03511f1206203\"]', '2026-09-01 17:39:01'),
(193, 'sale', 64, 0, 21, 2000000, 'KZT', 'cash', '{\"action\":\"sale\",\"seats\":[{\"id\":null,\"identifier\":\"5-17\",\"price\":5000,\"customer_segment\":\"adult\"},{\"id\":null,\"identifier\":\"5-18\",\"price\":5000,\"customer_segment\":\"adult\"},{\"id\":null,\"identifier\":\"8-1\",\"price\":10000,\"customer_segment\":\"adult\"}],\"seats_final\":[{\"identifier\":\"5-17\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"adult\",\"auto_discount\":null,\"custom_discount\":null},{\"identifier\":\"5-18\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"adult\",\"auto_discount\":null,\"custom_discount\":null},{\"identifier\":\"8-1\",\"original_price\":10000,\"final_price\":10000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":1000000,\"final_price_cents\":1000000,\"customer_segment\":\"adult\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"adult\",\"discount\":{\"final_total\":20000,\"applied\":[]},\"base_total\":20000,\"final_total\":20000,\"base_total_cents\":2000000,\"final_total_cents\":2000000}', '[\"c73b9b17c3c176b4\",\"22a0e6ad68f0f26e\",\"d1dfb2884df1d188\"]', '2026-09-01 17:39:58'),
(194, 'sale', 64, 0, 21, 250000, 'KZT', 'card', '{\"action\":\"sale\",\"seats\":[{\"id\":null,\"identifier\":\"7-10\",\"price\":5000,\"customer_segment\":\"child\"}],\"seats_final\":[{\"identifier\":\"7-10\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"child\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"child\",\"discount\":{\"final_total\":5000,\"applied\":[]},\"base_total\":5000,\"final_total\":5000,\"base_total_cents\":500000,\"final_total_cents\":250000}', '[\"e00d9d8f33067298\"]', '2026-09-01 17:52:55'),
(195, 'sale', 67, 0, 21, 500000, 'KZT', 'card', '{\"action\":\"sale\",\"seats\":[{\"id\":null,\"identifier\":\"12-25\",\"price\":5000,\"customer_segment\":\"child\"},{\"id\":null,\"identifier\":\"12-24\",\"price\":5000,\"customer_segment\":\"child\"}],\"seats_final\":[{\"identifier\":\"12-25\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"child\",\"auto_discount\":null,\"custom_discount\":null},{\"identifier\":\"12-24\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"child\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"child\",\"discount\":{\"final_total\":10000,\"applied\":[]},\"base_total\":10000,\"final_total\":10000,\"base_total_cents\":1000000,\"final_total_cents\":500000}', '[\"7a339f845f1cbd36\",\"87ba6b4b0227eade\"]', '2026-09-01 18:02:41'),
(196, 'sale', 67, 0, 21, 800000, 'KZT', 'cash', '{\"action\":\"sale\",\"seats\":[{\"id\":null,\"identifier\":\"14-24\",\"price\":5000,\"customer_segment\":\"student\"},{\"id\":null,\"identifier\":\"14-25\",\"price\":5000,\"customer_segment\":\"student\"}],\"seats_final\":[{\"identifier\":\"14-24\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null},{\"identifier\":\"14-25\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"discount\":{\"final_total\":10000,\"applied\":[]},\"base_total\":10000,\"final_total\":10000,\"base_total_cents\":1000000,\"final_total_cents\":800000}', '[\"569f844f8e7807da\",\"59f39d44d0d13b3d\"]', '2026-09-01 18:11:11'),
(197, 'sale', 67, 0, 21, 600000, 'KZT', 'card', '{\"action\":\"sale\",\"seats\":[{\"id\":null,\"identifier\":\"15-24\",\"price\":5000,\"customer_segment\":\"senior\"},{\"id\":null,\"identifier\":\"15-25\",\"price\":5000,\"customer_segment\":\"senior\"}],\"seats_final\":[{\"identifier\":\"15-24\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"senior\",\"auto_discount\":null,\"custom_discount\":null},{\"identifier\":\"15-25\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"senior\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"senior\",\"discount\":{\"final_total\":10000,\"applied\":[]},\"base_total\":10000,\"final_total\":10000,\"base_total_cents\":1000000,\"final_total_cents\":600000}', '[\"6141d1df65196a6b\",\"315bd62343c5fc1c\"]', '2026-09-01 18:13:06'),
(198, 'sale', 67, 0, 21, 400000, 'KZT', 'cash', '{\"seats_final\":[{\"identifier\":\"12-20\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\"}', '[\"54d4033973ec68eb\"]', '2026-09-01 18:21:05'),
(199, 'sale', 67, 0, 21, 800000, 'KZT', 'cash', '{\"seats_final\":[{\"identifier\":\"14-20\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null},{\"identifier\":\"14-21\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"discount\":20}', '[\"1a8e5ef9ac878b8c\",\"349699ef83e5d5e2\"]', '2026-09-02 10:55:07'),
(200, 'sale', 67, 0, 21, 800000, 'KZT', 'cash', '{\"seats_final\":[{\"identifier\":\"15-20\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null},{\"identifier\":\"15-21\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"discount\":20}', '[\"d71bab0018a81e69\",\"95fbe4f65406328c\"]', '2026-09-02 10:55:38'),
(201, 'sale', 67, 0, 21, 400000, 'KZT', 'cash', '{\"seats_final\":[{\"identifier\":\"15-22\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"discount\":[\"15-22\"]}', '[\"a2e85687b6c859e5\"]', '2026-09-02 11:39:21'),
(202, 'sale', 67, 0, 21, 400000, 'KZT', 'cash', '{\"seats\":[{\"id\":null,\"identifier\":\"14-22\",\"price\":5000,\"customer_segment\":\"student\"}],\"seats_final\":[{\"identifier\":\"14-22\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"discount\":[\"14-22\"]}', '[\"d5e904b28a6297f0\"]', '2026-09-02 11:39:57'),
(203, 'sale', 67, 0, 21, 400000, 'KZT', 'cash', '{\"seats_final\":[{\"identifier\":\"14-23\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"discount\":null}', '[\"28d8163f10455550\"]', '2026-09-02 11:41:04'),
(204, 'sale', 67, 0, 21, 400000, 'KZT', 'cash', '{\"seats_final\":[{\"identifier\":\"15-23\",\"original_price\":5000,\"final_price\":5000,\"discount\":0,\"discount_amount\":0,\"discount_cents\":0,\"discount_percent\":0,\"original_price_cents\":500000,\"final_price_cents\":500000,\"customer_segment\":\"student\",\"auto_discount\":null,\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"discount\":null,\"final_total_cents\":400000}', '[\"8d55f2040e3706ce\"]', '2026-09-02 11:46:20'),
(205, 'sale', 67, 0, 21, 800000, 'KZT', 'card', '{\"seats_final\":[{\"identifier\":\"12-20\",\"original_price\":5000,\"final_price\":8000,\"discount_amount\":-3000,\"discount_percent\":16000,\"customer_segment\":\"student\",\"custom_discount\":null},{\"identifier\":\"12-21\",\"original_price\":5000,\"final_price\":8000,\"discount_amount\":-3000,\"discount_percent\":16000,\"customer_segment\":\"student\",\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\"}', '[\"a5e0c73132cedd7c\",\"0422d1a4be12516e\"]', '2026-09-02 11:56:52'),
(206, 'sale', 67, 0, 21, 800000, 'KZT', 'card', '{\"seats_final\":[{\"identifier\":\"14-20\",\"original_price\":5000,\"final_price\":8000,\"discount_amount\":-3000,\"discount_percent\":16000,\"customer_segment\":\"student\",\"custom_discount\":null},{\"identifier\":\"14-21\",\"original_price\":5000,\"final_price\":8000,\"discount_amount\":-3000,\"discount_percent\":16000,\"customer_segment\":\"student\",\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"final_total_cents\":800000}', '[\"af7b1cb850785240\",\"ce23e1b92c56a1e3\"]', '2026-09-02 12:07:27'),
(207, 'sale', 67, 0, 21, 400000, 'KZT', 'card', '{\"seats_final\":[{\"identifier\":\"14-22\",\"original_price\":5000,\"input\":{\"segment\":\"student\",\"auto_percent\":20,\"custom_type\":\"none\",\"custom_value\":0,\"base_total_cents\":500000,\"final_total_cents\":400000,\"base_total\":5000,\"final_total\":4000},\"final_price\":4000,\"discount_amount\":1000,\"discount_percent\":8000,\"customer_segment\":\"student\",\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"final_total\":4000}', '[\"f68778db93899a74\"]', '2026-09-02 12:12:21'),
(208, 'sale', 67, 0, 21, 800000, 'KZT', 'card', '{\"seats_final\":[{\"identifier\":\"14-23\",\"original_price\":5000,\"input\":{\"segment\":\"student\",\"auto_percent\":20,\"custom_type\":\"none\",\"custom_value\":0,\"base_total_cents\":1000000,\"final_total_cents\":800000,\"base_total\":10000,\"final_total\":8000},\"final_price\":8000,\"discount_amount\":-3000,\"discount_percent\":16000,\"customer_segment\":\"student\",\"custom_discount\":null},{\"identifier\":\"14-24\",\"original_price\":5000,\"input\":{\"segment\":\"student\",\"auto_percent\":20,\"custom_type\":\"none\",\"custom_value\":0,\"base_total_cents\":1000000,\"final_total_cents\":800000,\"base_total\":10000,\"final_total\":8000},\"final_price\":8000,\"discount_amount\":-3000,\"discount_percent\":16000,\"customer_segment\":\"student\",\"custom_discount\":null}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"final_total\":8000}', '[\"8cb8c068f1ad72c7\",\"1a9c17ab383e6ac1\"]', '2026-09-02 12:14:33'),
(209, 'sale', 67, 0, 21, 1200000, 'KZT', 'cash', '{\"action\":\"sale\",\"seats_final\":[{\"identifier\":\"16-10\",\"original_price\":10000,\"final_price\":2000,\"discount_amount\":8000,\"discount_percent\":20,\"custom_discount\":0,\"customer_segment\":\"student\"},{\"identifier\":\"13-20\",\"original_price\":5000,\"final_price\":1000,\"discount_amount\":4000,\"discount_percent\":20,\"custom_discount\":0,\"customer_segment\":\"student\"}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"final_total\":12000}', '[\"242c6ba6a0809daf\",\"54d1f82e2fffca14\"]', '2026-09-02 12:19:33'),
(210, 'sale', 67, 0, 21, 900000, 'KZT', 'cash', '{\"action\":\"sale\",\"seats_final\":[{\"identifier\":\"16-10\",\"original_price\":10000,\"final_price\":6000,\"discount_amount\":4000,\"discount_percent\":40,\"custom_discount\":0,\"customer_segment\":\"senior\"},{\"identifier\":\"16-20\",\"original_price\":5000,\"final_price\":3000,\"discount_amount\":2000,\"discount_percent\":40,\"custom_discount\":0,\"customer_segment\":\"senior\"}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"senior\",\"final_total\":9000}', '[\"0d8bebd1c7dba490\",\"f61f5280b6e668e4\"]', '2026-09-02 12:21:23'),
(211, 'sale', 67, 0, 21, 2500, 'KZT', 'card', '{\"action\":\"sale\",\"seats_final\":[{\"identifier\":\"14-23\",\"original_price\":2500,\"final_price\":2500,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0,\"customer_segment\":\"child\"}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"male\",\"city\":\"Алматы\"},\"customer_segment\":\"child\",\"manual_discount_amount\":0,\"final_total\":2500,\"total_discount\":0}', '[\"7064c679a066f3b4\"]', '2026-09-02 15:28:36'),
(212, 'sale', 67, 0, 21, 3500, 'KZT', 'cash', '{\"action\":\"sale\",\"seats_final\":[{\"identifier\":\"14-24\",\"original_price\":3500,\"final_price\":2000,\"discount_amount\":1500,\"discount_percent\":43,\"custom_discount\":1500,\"customer_segment\":\"manual\"}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"manual\",\"manual_discount_amount\":1500,\"final_total\":2000,\"total_discount\":1500}', '[\"fa62acd86e709c42\"]', '2026-09-02 16:37:44'),
(213, 'sale', 67, 0, 21, 10000, 'KZT', 'cash', '{\"action\":\"sale\",\"seats_final\":[{\"identifier\":\"1-5\",\"original_price\":10000,\"final_price\":10000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0,\"customer_segment\":\"child\"}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"child\",\"manual_discount_amount\":0,\"final_total\":10000,\"total_discount\":0}', '[\"5e1f4cf9a0c51b2e\"]', '2026-09-02 19:39:08'),
(214, 'sale', 64, 0, 21, 16000, 'KZT', 'card', '{\"action\":\"sale\",\"seats_final\":[{\"identifier\":\"8-1\",\"original_price\":8000,\"final_price\":8000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0,\"customer_segment\":\"student\"},{\"identifier\":\"7-18\",\"original_price\":4000,\"final_price\":4000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0,\"customer_segment\":\"student\"},{\"identifier\":\"3-17\",\"original_price\":4000,\"final_price\":4000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0,\"customer_segment\":\"student\"}],\"customer\":{\"full_name\":\"Теплов Федор Евгеньеич\",\"phone\":\"77772979723\",\"email\":\"ch011tfe@mail.ru\",\"gender\":\"\",\"city\":\"Алматы\"},\"customer_segment\":\"student\",\"manual_discount_amount\":0,\"final_total\":16000,\"total_discount\":0}', '[\"63e4bb78714eabfb\",\"30697ebca5358f22\",\"aa1ec26e7b928be2\"]', '2026-09-05 13:55:08'),
(215, 'sale', 67, 0, 21, 0, 'KZT', 'bcc', '{\"order\":\"202609081334085028\",\"source\":\"bcc_notify\"}', NULL, '2026-09-08 13:37:28'),
(216, 'sale', 67, 0, 22, 1000000, 'KZT', 'card', '{\"action\":\"sale\",\"source\":\"bcc_backref\",\"provider\":\"bcc\",\"order\":\"202609081811257784\",\"rrn\":\"625122120392\",\"int_ref\":\"AF8A61B17B2203D5\",\"seats_final\":[{\"identifier\":\"16-21\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0},{\"identifier\":\"16-22\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0}],\"customer\":{\"full_name\":\"\",\"phone\":\"77771111177\",\"email\":\"\"},\"customer_segment\":\"adult\",\"discount\":{\"total_discount\":0,\"final_total\":10000},\"base_total\":10000,\"final_total\":10000,\"ticket_uids\":[\"3d8f3345d17fcdf9\",\"0560af5a20536536\"]}', '[\"3d8f3345d17fcdf9\",\"0560af5a20536536\"]', '2026-09-08 18:11:58'),
(217, 'sale', 64, 0, 23, 1000000, 'KZT', 'card', '{\"action\":\"sale\",\"source\":\"bcc_backref\",\"provider\":\"bcc\",\"order\":\"202609081818286246\",\"rrn\":\"625121299393\",\"int_ref\":\"0A3D4A1EAFC662AB\",\"seats_final\":[{\"identifier\":\"6-21\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0},{\"identifier\":\"5-20\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0}],\"customer\":{\"full_name\":\"Коля\",\"phone\":\"77777777777\",\"email\":\"\"},\"customer_segment\":\"adult\",\"discount\":{\"total_discount\":0,\"final_total\":10000},\"base_total\":10000,\"final_total\":10000,\"ticket_uids\":[\"e6bab31940e1fd34\",\"5e1d55f414597e1a\"]}', '[\"e6bab31940e1fd34\",\"5e1d55f414597e1a\"]', '2026-09-08 18:21:55'),
(218, 'sale', 64, 0, 24, 500000, 'KZT', 'card', '{\"action\":\"sale\",\"source\":\"bcc_backref\",\"provider\":\"bcc\",\"order\":\"202609091033256680\",\"rrn\":\"625221299403\",\"int_ref\":\"91F88A50B69C8AAA\",\"seats_final\":[{\"identifier\":\"7-17\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0}],\"customer\":{\"full_name\":\"Дядя Вас\",\"phone\":\"77773000000\",\"email\":\"pochta@pochta.com\"},\"customer_segment\":\"adult\",\"discount\":{\"total_discount\":0,\"final_total\":5000},\"base_total\":5000,\"final_total\":5000,\"ticket_uids\":[\"8639fc719be0f99c\"]}', '[\"8639fc719be0f99c\"]', '2026-09-09 10:34:07'),
(219, 'sale', 67, 0, 25, 500000, 'KZT', 'card', '{\"action\":\"sale\",\"source\":\"bcc_backref\",\"provider\":\"bcc\",\"order\":\"202609091049014102\",\"rrn\":\"625222120403\",\"int_ref\":\"18A4C361E9E872FB\",\"seats_final\":[{\"identifier\":\"12-24\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0}],\"customer\":{\"full_name\":\"Васёк\",\"phone\":\"77001001010\",\"email\":\"\"},\"customer_segment\":\"adult\",\"discount\":{\"total_discount\":0,\"final_total\":5000},\"base_total\":5000,\"final_total\":5000,\"ticket_uids\":[\"48120f169cd7f7cf\"]}', '[\"48120f169cd7f7cf\"]', '2026-09-09 10:49:38'),
(220, 'sale', 67, 0, 26, 500000, 'KZT', 'card', '{\"action\":\"sale\",\"source\":\"bcc_backref\",\"provider\":\"bcc\",\"order\":\"202609101350121043\",\"rrn\":\"625323121117\",\"int_ref\":\"6E8ADF0E37D7E27C\",\"seats_final\":[{\"identifier\":\"12-23\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0}],\"customer\":{\"full_name\":\"Дядя Лёша\",\"phone\":\"+77774000000\",\"email\":\"pochta@pochta.kz\"},\"customer_segment\":\"adult\",\"discount\":{\"total_discount\":0,\"final_total\":5000},\"base_total\":5000,\"final_total\":5000,\"ticket_uids\":[\"ba5d80113735b2e0\"]}', '[\"ba5d80113735b2e0\"]', '2026-09-10 14:13:23'),
(221, 'sale', 67, 0, 27, 1000000, 'KZT', 'card', '{\"action\":\"sale\",\"source\":\"bcc_backref\",\"provider\":\"bcc\",\"order\":\"202609101952287718\",\"rrn\":\"625322121129\",\"int_ref\":\"AFDCC77932638A66\",\"seats_final\":[{\"identifier\":\"15-21\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0},{\"identifier\":\"15-22\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0}],\"customer\":{\"full_name\":\"Тимур и его команда\",\"phone\":\"+75555555555\",\"email\":\"\"},\"customer_segment\":\"adult\",\"discount\":{\"total_discount\":0,\"final_total\":10000},\"base_total\":10000,\"final_total\":10000,\"ticket_uids\":[\"033a74a421a6f50e\",\"230841b9be13361f\"]}', '[\"033a74a421a6f50e\",\"230841b9be13361f\"]', '2026-09-10 19:54:07'),
(222, 'sale', 64, 0, 28, 500000, 'KZT', 'card', '{\"action\":\"sale\",\"source\":\"bcc_backref\",\"provider\":\"bcc\",\"order\":\"202609102010228834\",\"rrn\":\"625321300130\",\"int_ref\":\"4841F9954A4FF070\",\"seats_final\":[{\"identifier\":\"3-20\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0}],\"customer\":{\"full_name\":\"Дядя\",\"phone\":\"+77776000000\",\"email\":\"pochta@pochta.com\"},\"customer_segment\":\"adult\",\"discount\":{\"total_discount\":0,\"final_total\":5000},\"base_total\":5000,\"final_total\":5000,\"ticket_uids\":[\"8c64c404142b8272\"]}', '[\"8c64c404142b8272\"]', '2026-09-10 20:17:49'),
(223, 'sale', 64, 0, 29, 500000, 'KZT', 'card', '{\"action\":\"sale\",\"source\":\"bcc_backref\",\"provider\":\"bcc\",\"order\":\"202609102030096974\",\"rrn\":\"625323121130\",\"int_ref\":\"672E898E9D69AE05\",\"seats_final\":[{\"identifier\":\"6-23\",\"original_price\":5000,\"final_price\":5000,\"discount_amount\":0,\"discount_percent\":0,\"custom_discount\":0}],\"customer\":{\"full_name\":\"Дядя Кекс\",\"phone\":\"+77777000000\",\"email\":\"pochta@pochta.com\"},\"customer_segment\":\"adult\",\"discount\":{\"total_discount\":0,\"final_total\":5000},\"base_total\":5000,\"final_total\":5000,\"ticket_uids\":[\"aebd92d5295ae407\"]}', '[\"aebd92d5295ae407\"]', '2026-09-10 20:33:13');

-- --------------------------------------------------------

--
-- Структура таблицы `checkins`
--

CREATE TABLE `checkins` (
  `id` int(10) UNSIGNED NOT NULL,
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `scanned_at` datetime NOT NULL,
  `scanner_id` int(10) UNSIGNED DEFAULT NULL,
  `scan_source` enum('mobile','turnstile','manual','admin') NOT NULL DEFAULT 'mobile',
  `device_uid` varchar(100) DEFAULT NULL,
  `status` enum('success','duplicate','invalid','manual') NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `customer_uid` varchar(100) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `is_vip` tinyint(1) NOT NULL DEFAULT 0,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `last_purchase_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `customers`
--

INSERT INTO `customers` (`id`, `customer_uid`, `full_name`, `birth_date`, `gender`, `email`, `phone`, `city`, `is_vip`, `tags`, `last_purchase_at`, `note`, `created_at`, `updated_at`) VALUES
(21, '43ab301d5d4d9588', 'Теплов Федор Евгеньеич', NULL, '', 'ch011tfe@mail.ru', '77772979723', 'Алматы', 0, NULL, NULL, NULL, '2026-08-21 13:28:06', '2026-08-21 13:28:06'),
(22, '784303a9f550861f', 'Гость', NULL, NULL, NULL, '77771111177', NULL, 0, NULL, NULL, NULL, '2026-09-08 13:11:58', '2026-09-08 13:11:58'),
(23, '446717eb5810d778', 'Коля', NULL, NULL, NULL, '77777777777', NULL, 0, NULL, NULL, NULL, '2026-09-08 13:21:55', '2026-09-08 13:21:55'),
(24, 'd46c9f2eb97b17ff', 'Дядя Вас', NULL, NULL, 'pochta@pochta.com', '77773000000', NULL, 0, NULL, NULL, NULL, '2026-09-09 05:34:07', '2026-09-09 05:34:07'),
(25, 'de6ef9635b869442', 'Васёк', NULL, NULL, '', '+77001001010', '', 0, NULL, NULL, '', '2026-09-09 05:49:38', '2026-09-10 08:06:26'),
(26, '3a06699368f9c385', 'Дядя Лёша', NULL, NULL, 'pochta@pochta.kz', '+77774000000', NULL, 0, NULL, NULL, NULL, '2026-09-10 09:13:23', '2026-09-10 09:13:23'),
(27, '15f6bb8e11dcff73', 'Тимур и его команда', NULL, NULL, NULL, '+75555555555', NULL, 0, NULL, NULL, NULL, '2026-09-10 14:54:07', '2026-09-10 14:54:07'),
(28, '0f2e7b9d768bfa4b', 'Дядя', NULL, NULL, 'pochta@pochta.com', '+77776000000', NULL, 0, NULL, NULL, NULL, '2026-09-10 15:17:49', '2026-09-10 15:17:49'),
(29, '4d2086208e882059', 'Дядя Кекс', NULL, NULL, 'pochta@pochta.com', '+77777000000', NULL, 0, NULL, NULL, NULL, '2026-09-10 15:33:13', '2026-09-10 15:33:13');

-- --------------------------------------------------------

--
-- Структура таблицы `events`
--

CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `page_url` varchar(255) DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `full_description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `gallery` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery`)),
  `video_url` varchar(255) DEFAULT NULL,
  `duration_minutes` int(10) UNSIGNED DEFAULT 120,
  `duration_intermission_minutes` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'published',
  `is_premiere` tinyint(1) NOT NULL DEFAULT 0,
  `director` varchar(100) DEFAULT NULL,
  `producer` varchar(100) DEFAULT NULL,
  `choreographer` varchar(100) DEFAULT NULL,
  `sound_director` varchar(100) DEFAULT NULL,
  `lighting_director` varchar(100) DEFAULT NULL,
  `costume_designer` varchar(100) DEFAULT NULL,
  `age_limit` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `language_id` int(10) UNSIGNED DEFAULT NULL,
  `other_details` text DEFAULT NULL,
  `cast_list` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cast_list`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `genre_id` int(10) UNSIGNED DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `hall_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `events`
--

INSERT INTO `events` (`id`, `title`, `slug`, `page_url`, `short_description`, `full_description`, `image`, `gallery`, `video_url`, `duration_minutes`, `duration_intermission_minutes`, `status`, `is_premiere`, `director`, `producer`, `choreographer`, `sound_director`, `lighting_director`, `costume_designer`, `age_limit`, `language_id`, `other_details`, `cast_list`, `created_at`, `updated_at`, `category_id`, `genre_id`, `tags`, `hall_id`) VALUES
(24, '10 Негритят', NULL, 'https://zhassahna.kz/10_negrityat', 'вмпфывп', 'вапвп', 'https://cabinet.zhassahna.kz/uploads/images/events/1782198905_3f0d1cf8b7d1_10_negrityat_poster.jpg', NULL, NULL, 100, NULL, 'published', 0, 'куц', 'цкуецу', 'куце', 'укц', 'уцке', 'куе', 5, 1, 'куекцуецу', '[\"Айсулу\",\"Алексей Шемес\",\"Аяулым Қанатқали\",\"Дәмелі Тастан\"]', '2026-06-22 12:26:26', '2026-09-03 08:28:59', 2, 9, NULL, NULL),
(26, 'Зурико', NULL, NULL, 'Краткое описание', 'Полное описание', 'https://cabinet.zhassahna.kz/uploads/images/events/1782193310_7627e8fe9382_Zuriko_sayt.png', NULL, NULL, 100, NULL, 'published', 0, 'Сарафан', 'Сарафан', 'Сарафан', 'фыва', 'фыва', 'фываhsdf', 12, 1, 'Другие участники', '[\"Алексей Шемес\",\"Дәмелі Тастан\"]', '2026-06-23 05:57:08', '2026-07-16 10:52:40', 1, 1, NULL, NULL),
(29, 'Eden', NULL, NULL, 'вмпфывп', 'вапвп', 'https://cabinet.zhassahna.kz/uploads/images/events/1782131159_78c09fc61adf_10_negrityat_poster.jpg', NULL, NULL, 100, NULL, 'draft', 0, 'куц', 'цкуецу', 'куце', 'укц', 'уцке', 'куе', 5, 2, 'куекцуецу', '[\"Айсулу\",\"Алексей Шемес\",\"Аяулым Қанатқали\",\"Дәмелі Тастан\"]', '2026-06-23 06:12:53', NULL, 2, 9, NULL, NULL),
(30, 'Перекрёсток', NULL, NULL, 'ываф', 'выфа', 'https://cabinet.zhassahna.kz/uploads/images/events/1782198064_316ca4423647_perekryostok_new.jpg', NULL, NULL, 70, NULL, 'published', 0, 'куц', 'вафы', 'фываы', 'ывафыф', 'аыфва', 'ыфва', 12, 1, 'ыфва', '[\"Айсулу\",\"Алексей Шемес\",\"Аяулым Қанатқали\",\"Дәмелі Тастан\"]', '2026-06-23 07:01:54', '2026-08-25 09:04:16', 1, 1, NULL, NULL),
(31, 'Жас сахна проба', NULL, 'https://zhassahna.kz/10_negrityat', 'вфыафыва', 'фывафывафыва', 'https://cabinet.zhassahna.kz/uploads/images/events/1788424361_fd13709c8cbe_logo.png', NULL, NULL, 100, NULL, 'archived', 0, 'куц', 'цкуецу', 'куце', 'укц', 'уцке', 'фыва', 50, 1, NULL, '[\"Алексей Шемес\",\"Аяулым Қанатқали\",\"Дәмелі Тастан\"]', '2026-09-03 08:33:04', '2026-09-03 08:33:48', 4, 5, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `event_actors`
--

CREATE TABLE `event_actors` (
  `id` int(10) UNSIGNED NOT NULL,
  `actor_uid` varchar(100) DEFAULT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `actor_name` varchar(255) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `role_name` varchar(255) DEFAULT NULL,
  `character_name` varchar(255) DEFAULT NULL,
  `is_main_cast` tinyint(1) NOT NULL DEFAULT 1,
  `is_guest` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) UNSIGNED DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `social_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_links`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `event_actors`
--

INSERT INTO `event_actors` (`id`, `actor_uid`, `event_id`, `actor_name`, `birth_date`, `email`, `phone`, `photo_url`, `role_name`, `character_name`, `is_main_cast`, `is_guest`, `sort_order`, `bio`, `social_links`, `created_at`, `updated_at`) VALUES
(19, '52f5e964ce694286', NULL, 'Айсулу', '1948-02-08', 'info@zhassahna.kz', '+77767117878', '/uploads/images/actors/1782116497_7a5f2c8183f7_01_MASTER_FACE.png', NULL, NULL, 1, 0, NULL, 'hdfghdf', '{\"email\":\"info@zhassahna.kz\",\"phone\":\"+77767117878\",\"instagram\":\"gfhf\",\"facebook\":\"fgh\"}', '2026-06-13 10:25:47', '2026-06-22 12:08:10'),
(22, NULL, NULL, 'Дәмелі Тастан', '2021-09-04', 'info@zhassahna.kz', '+77767117878', '/uploads/images/actors/1782116511_8832ef848659_dameli_tastan.jpg', NULL, NULL, 1, 0, NULL, 'акыфкуфцкфц', '{\"email\":\"info@zhassahna.kz\",\"phone\":\"+77767117878\",\"instagram\":\"ваорвпр\",\"facebook\":\"выфафыва\"}', '2026-06-18 11:53:31', '2026-07-16 07:18:04'),
(23, NULL, NULL, 'Аяулым Қанатқали', NULL, 'info@zhassahna.kz', '+77767117878', '/uploads/images/actors/1782116641_97d95b14c676_Ayaulym_Qanatqali.jpg', NULL, NULL, 1, 0, 0, NULL, '{\"email\":\"info@zhassahna.kz\",\"phone\":\"+77767117878\"}', '2026-06-22 08:22:15', '2026-06-22 08:24:48'),
(24, NULL, NULL, 'Алексей Шемес', '2015-09-09', 'info@zhassahna.kz', '+77767117878', '/uploads/images/actors/1782124259_16e449118ba7_shemes_new.jpeg', NULL, NULL, 1, 0, 0, 'ппрыарыр', '{\"email\":\"info@zhassahna.kz\",\"phone\":\"+77767117878\",\"instagram\":\"dsfsfad\",\"facebook\":\"dasfdg\"}', '2026-06-22 08:37:29', '2026-06-22 11:34:59');

-- --------------------------------------------------------

--
-- Структура таблицы `event_categories`
--

CREATE TABLE `event_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `event_categories`
--

INSERT INTO `event_categories` (`id`, `name`, `slug`, `description`, `sort_order`) VALUES
(1, 'Спектакль', NULL, NULL, 0),
(2, 'Концерт', NULL, NULL, 0),
(3, 'Аренда', NULL, NULL, 0),
(4, 'Мастер-класс', NULL, NULL, 0),
(5, 'Стендап', NULL, NULL, 0),
(6, 'Детское мероприятие', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `event_genres`
--

CREATE TABLE `event_genres` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `event_genres`
--

INSERT INTO `event_genres` (`id`, `name`, `slug`, `description`, `sort_order`) VALUES
(1, 'Комедия', NULL, NULL, 0),
(2, 'Драма', NULL, NULL, 0),
(3, 'Трагедия', NULL, NULL, 0),
(4, 'Мюзикл', NULL, NULL, 0),
(5, 'Опера', NULL, NULL, 0),
(6, 'Сказка', NULL, NULL, 0),
(7, 'Фантазия', NULL, NULL, 0),
(8, 'Концертная программа', NULL, NULL, 0),
(9, 'Стендап', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `event_languages`
--

CREATE TABLE `event_languages` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(5) NOT NULL,
  `label` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `event_languages`
--

INSERT INTO `event_languages` (`id`, `code`, `label`) VALUES
(1, 'ru', 'Русский'),
(2, 'kz', 'Қазақша'),
(3, 'en', 'English');

-- --------------------------------------------------------

--
-- Структура таблицы `event_statuses`
--

CREATE TABLE `event_statuses` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `sort_order` int(10) UNSIGNED DEFAULT NULL,
  `color_hex` varchar(20) DEFAULT NULL,
  `badge_style` enum('solid','outline','dashed','gradient') DEFAULT 'solid',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `event_statuses`
--

INSERT INTO `event_statuses` (`id`, `code`, `label`, `sort_order`, `color_hex`, `badge_style`, `is_active`, `is_system`, `description`, `created_at`, `updated_at`) VALUES
(6, 'published', 'Опубликовано', 1, '#28a745', 'solid', 1, 0, 'Мероприятие опубликовано', '2026-06-08 04:19:36', '2026-06-09 03:35:48'),
(7, 'draft', 'Черновик', 2, '#dc3545', 'solid', 1, 0, 'Мероприятие на редактировании', '2026-06-08 04:19:42', '2026-06-09 03:34:31'),
(9, 'archived', 'В архиве', 4, '#343a40', 'solid', 1, 0, 'Мероприятие перенесено в архив', '2026-06-08 04:19:54', '2026-06-09 03:33:53');

-- --------------------------------------------------------

--
-- Структура таблицы `halls`
--

CREATE TABLE `halls` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `rows_count` int(11) NOT NULL,
  `cols_count` int(11) NOT NULL,
  `seat_map` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`seat_map`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `halls`
--

INSERT INTO `halls` (`id`, `name`, `code`, `rows_count`, `cols_count`, `seat_map`, `created_at`) VALUES
(8, 'Большая сцена', 'большая_сцена_326980', 16, 25, '{\"meta\":{\"baseSeatSize\":22,\"gapX\":8,\"gapY\":12},\"anchors\":{\"layout_origin\":{\"x\":0,\"y\":0,\"_px\":{\"x\":80,\"y\":40}}},\"rows\":[{\"number\":1,\"segments\":[{\"id\":\"seg_505007\",\"start\":1,\"end\":16,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.5,\"y\":16.2353}}]},{\"number\":2,\"segments\":[{\"id\":\"seg_528935\",\"start\":1,\"end\":17,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.9,\"y\":15.3529}}]},{\"number\":3,\"segments\":[{\"id\":\"seg_539893\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":14.4706}},{\"id\":\"seg_585741\",\"start\":3,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.4,\"y\":14.4706}},{\"id\":\"seg_601124\",\"start\":21,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.6,\"y\":14.4706}}]},{\"number\":4,\"segments\":[{\"id\":\"seg_634636\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":13.5882}},{\"id\":\"seg_642964\",\"start\":3,\"end\":21,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.9,\"y\":13.5882}},{\"id\":\"seg_658156\",\"start\":22,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.6,\"y\":13.5882}}]},{\"number\":5,\"segments\":[{\"id\":\"seg_678812\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":12.7059}},{\"id\":\"seg_908012\",\"start\":3,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.4,\"y\":12.7058}},{\"id\":\"seg_935252\",\"start\":23,\"end\":24,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.6,\"y\":12.7059}}]},{\"number\":6,\"segments\":[{\"id\":\"seg_005300\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":11.8235}},{\"id\":\"seg_040628\",\"start\":3,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.9,\"y\":11.8235}},{\"id\":\"seg_067980\",\"start\":24,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.6,\"y\":11.8235}}]},{\"number\":7,\"segments\":[{\"id\":\"seg_107228\",\"start\":1,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.3,\"y\":10.941}}]},{\"number\":8,\"segments\":[{\"id\":\"seg_169980\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.6,\"y\":8.7353}}]},{\"number\":9,\"segments\":[{\"id\":\"seg_188773\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.6,\"y\":7.8529}}]},{\"number\":10,\"segments\":[{\"id\":\"seg_196604\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.6,\"y\":6.9706}}]},{\"number\":11,\"segments\":[{\"id\":\"seg_204268\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.6,\"y\":6.0883}}]},{\"number\":12,\"segments\":[{\"id\":\"seg_215092\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.6,\"y\":5.2059}}]},{\"number\":13,\"segments\":[{\"id\":\"seg_222460\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.6,\"y\":4.3235}}]},{\"number\":14,\"segments\":[{\"id\":\"seg_228892\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.6,\"y\":3.4412}}]},{\"number\":15,\"segments\":[{\"id\":\"seg_231084\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.6,\"y\":2.5589}}]},{\"number\":16,\"segments\":[{\"id\":\"seg_322420\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.6,\"y\":1.6765}}]}],\"elements\":[{\"id\":\"el_485535\",\"type\":\"rect\",\"name\":\"Сцена\",\"text\":\"Сцена\",\"x\":246,\"y\":642,\"width\":810,\"height\":54,\"fill\":\"#ffffff\",\"stroke\":\"#2f9b6f\",\"textColor\":\"#0b3b4a\"}],\"seats\":{}}', '2026-08-17 06:15:27'),
(11, 'Пробный зал', 'пробный_зал_813791', 3, 30, '{\"meta\":{\"baseSeatSize\":28,\"gapX\":8,\"gapY\":12},\"anchors\":{\"layout_origin\":{\"x\":0,\"y\":0,\"_px\":{\"x\":80,\"y\":40}}},\"rows\":[{\"number\":1,\"segments\":[{\"id\":\"seg_699488\",\"start\":11,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.1112,\"y\":12.2}},{\"id\":\"seg_705440\",\"start\":1,\"end\":10,\"anchor\":\"layout_origin\",\"offset\":{\"x\":-0.111,\"y\":12.2}},{\"id\":\"seg_719200\",\"start\":21,\"end\":30,\"anchor\":\"layout_origin\",\"offset\":{\"x\":22.2223,\"y\":12.2}}]},{\"number\":2,\"segments\":[{\"id\":\"seg_747975\",\"start\":21,\"end\":30,\"anchor\":\"layout_origin\",\"offset\":{\"x\":22.2222,\"y\":11.3}},{\"id\":\"seg_758287\",\"start\":11,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.1111,\"y\":11.3}},{\"id\":\"seg_765527\",\"start\":1,\"end\":10,\"anchor\":\"layout_origin\",\"offset\":{\"x\":-0.1111,\"y\":11.3}}]},{\"number\":3,\"segments\":[{\"id\":\"seg_776551\",\"start\":1,\"end\":10,\"anchor\":\"layout_origin\",\"offset\":{\"x\":-0.1111,\"y\":10.4}},{\"id\":\"seg_796127\",\"start\":11,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.1111,\"y\":10.4}},{\"id\":\"seg_803575\",\"start\":21,\"end\":30,\"anchor\":\"layout_origin\",\"offset\":{\"x\":22.2222,\"y\":10.4}}]}],\"elements\":[{\"id\":\"el_682336\",\"type\":\"rect\",\"name\":\"Сцена\",\"text\":\"Сцена\",\"x\":483,\"y\":581,\"width\":350,\"height\":112,\"fill\":\"#ffffff\",\"stroke\":\"#2f9b6f\",\"textColor\":\"#0b3b4a\"}],\"seats\":{}}', '2026-08-17 07:13:34');

-- --------------------------------------------------------

--
-- Структура таблицы `payment_sessions`
--

CREATE TABLE `payment_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL COMMENT 'schedule_id',
  `event_id` int(10) UNSIGNED NOT NULL,
  `hall_id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(32) NOT NULL COMMENT 'ORDER для BCC',
  `merch_rn_id` varchar(32) NOT NULL COMMENT 'MERCH_RN_ID для BCC',
  `amount_cents` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(8) NOT NULL DEFAULT 'KZT',
  `status` enum('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  `provider` varchar(100) NOT NULL DEFAULT 'bcc',
  `provider_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`provider_response`)),
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `seats_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`seats_payload`)),
  `ticket_uids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ticket_uids`)),
  `cash_transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notify_received_at` datetime DEFAULT NULL,
  `return_visited_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `payment_sessions`
--

INSERT INTO `payment_sessions` (`id`, `session_id`, `event_id`, `hall_id`, `order_number`, `merch_rn_id`, `amount_cents`, `currency`, `status`, `provider`, `provider_response`, `customer_id`, `customer_name`, `customer_phone`, `customer_email`, `seats_payload`, `ticket_uids`, `cash_transaction_id`, `notify_received_at`, `return_visited_at`, `created_at`, `updated_at`) VALUES
(1, 64, 26, 8, '202609051509353391', '05FC063A1AB37847', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"7-14\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 10:09:35', '2026-09-09 07:35:02'),
(2, 64, 26, 8, '202609051512147631', '85CDFE00D326602E', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"6-20\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 10:12:14', '2026-09-09 07:35:02'),
(3, 64, 26, 8, '202609051518157951', '6EE4F0EEE13DA314', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"5-19\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 10:18:15', '2026-09-09 07:35:02'),
(4, 64, 26, 8, '202609051530225508', '6F76BB9669CFF57B', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Фёдор', '77772979723', NULL, '[{\"identifier\":\"5-17\",\"price\":5000},{\"identifier\":\"5-21\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 10:30:22', '2026-09-09 07:35:02'),
(5, 67, 24, 8, '202609051544427048', 'A10851A1391A6619', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"14-21\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 10:44:42', '2026-09-09 07:35:02'),
(6, 64, 26, 8, '202609051553553713', '1AF9F102D5475B74', 500000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"641853\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609051553553713\",\"RRN\":\"624823120161\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"CH011TFE@MAIL.RU\",\"NAME\":\"fyodor teplov\",\"P_SIGN\":\"D6E73AD92036277CC8551F61589B047488AB7018\",\"TIMESTAMP\":\"20260905105443\",\"CVC2_RC\":\"\",\"INT_REF\":\"CD91353DD9513F42\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"DCFB8FB63ABE2C80A1C1ED436ABB4591\",\"MERCH_RN_ID\":\"1AF9F102D5475B74\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"436C96CDE9DB49CD2C8B6D056A525054\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"5-17\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-05 15:54:47', '2026-09-05 10:53:55', '2026-09-05 10:54:47'),
(7, 64, 26, 8, '202609051856051959', 'D0618D3B821EAA3E', 500000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"641854\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609051856051959\",\"RRN\":\"624821299164\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"BED71E67E37D8F60769E7EBE69DACC4600487389\",\"TIMESTAMP\":\"20260905135745\",\"CVC2_RC\":\"\",\"INT_REF\":\"440891BABBE3ED99\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"1B8F6E2A548575B202BC53A0258D05E3\",\"MERCH_RN_ID\":\"D0618D3B821EAA3E\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"436C96CDE9DB49CD2C8B6D056A525054\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"5-17\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-05 18:58:01', '2026-09-05 13:56:05', '2026-09-05 13:58:01'),
(8, 64, 26, 8, '202609052057109174', 'BC1D8357E4D94E44', 500000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"641855\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609052057109174\",\"RRN\":\"624822120165\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"6E638592136C924630BFD566DCE00E5A68FA01EB\",\"TIMESTAMP\":\"20260905155838\",\"CVC2_RC\":\"\",\"INT_REF\":\"5E2A5F83D4BB8FBB\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"5B9B47556C55FCD455E2F586ADC6EB8E\",\"MERCH_RN_ID\":\"BC1D8357E4D94E44\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"436C96CDE9DB49CD2C8B6D056A525054\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"5-16\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-05 20:58:46', '2026-09-05 15:57:10', '2026-09-09 07:35:02'),
(9, 62, 24, 11, '202609052117005782', 'B50CE8C00A4F4458', 300000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Тест Тестов', '7777777777', 'test@example.com', '[{\"identifier\":\"3-12\",\"price\":3000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:17:00', '2026-09-09 07:35:02'),
(10, 62, 24, 11, '202609052137249496', '2542DC98793B31D6', 300000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"2-15\",\"price\":3000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:37:24', '2026-09-09 07:35:02'),
(11, 62, 24, 11, '202609052137346509', '06162FA0DC48E41D', 300000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"3-15\",\"price\":3000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:37:34', '2026-09-09 07:35:02'),
(12, 64, 26, 8, '202609052145202611', 'FEFBC1175D06479A', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"7-16\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:45:20', '2026-09-09 07:35:02'),
(13, 64, 26, 8, '202609052145275603', '08CB1D297AAE3412', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"7-16\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:45:27', '2026-09-09 07:35:02'),
(14, 62, 24, 11, '202609052147115171', 'BA12228601B5F796', 300000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"3-14\",\"price\":3000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:47:11', '2026-09-09 07:35:02'),
(15, 67, 24, 8, '202609052148281041', '984F6457771D08FC', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"13-21\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:48:28', '2026-09-09 07:35:02'),
(16, 64, 26, 8, '202609052149155658', '2AFA3E0A5B2BC57A', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"5-15\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:49:15', '2026-09-09 07:35:02'),
(17, 64, 26, 8, '202609052151178835', 'E353AB8B67C3A3E5', 500000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"641856\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609052151178835\",\"RRN\":\"624821299166\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"9E99A848CCE484B9CC884E5C1A1E0A193F9FA8D5\",\"TIMESTAMP\":\"20260905165152\",\"CVC2_RC\":\"\",\"INT_REF\":\"282624838FAB0039\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"D2DF985B63F511F9F850C57EFAA26E39\",\"MERCH_RN_ID\":\"E353AB8B67C3A3E5\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"436C96CDE9DB49CD2C8B6D056A525054\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"6-20\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-05 21:51:55', '2026-09-05 16:51:17', '2026-09-09 07:35:02'),
(18, 64, 26, 8, '202609052153187128', 'D74805623CE60B6D', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"3-19\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:53:18', '2026-09-09 07:35:02'),
(19, 64, 26, 8, '202609052156453539', 'F504910FA1E8CC2D', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"3-19\",\"price\":5000},{\"identifier\":\"5-19\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:56:45', '2026-09-09 07:35:02'),
(20, 64, 26, 8, '202609052157053227', '108ABD1FC422ECF5', 500000, 'KZT', 'failed', 'bcc', '{\"ORDER\":\"202609052157053227\",\"RC\":\"00\",\"RRN\":\"123\",\"INT_REF\":\"456\",\"AUTHCODE\":\"789\",\"ACTION\":\"0\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"4-20\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-06 17:20:55', '2026-09-05 16:57:05', '2026-09-06 12:20:55'),
(21, 64, 26, 8, '202609052159334803', 'C67747BA4086FBA6', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Test', '77772979723', NULL, '[{\"identifier\":\"3-19\",\"price\":5000},{\"identifier\":\"5-19\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 16:59:33', '2026-09-09 07:35:02'),
(22, 64, 26, 8, '202609052200008700', 'F82ED5F8B34C49CA', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Test', '77772979723', NULL, '[{\"identifier\":\"3-19\",\"price\":5000},{\"identifier\":\"5-19\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 17:00:00', '2026-09-09 07:35:02'),
(23, 64, 26, 8, '202609052201299979', '10B993D1C23D1488', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Test', '77772979723', NULL, '[{\"identifier\":\"3-19\",\"price\":5000},{\"identifier\":\"5-19\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 17:01:29', '2026-09-09 07:35:02'),
(24, 64, 26, 8, '202609052203457738', '480CAB99D561A36E', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"6-17\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-05 17:03:45', '2026-09-09 07:35:02'),
(25, 64, 26, 8, '202609052213298826', 'FF35E7ADE7E94C0B', 500000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"641859\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609052213298826\",\"RRN\":\"624821299167\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"\",\"NAME\":\"lkhj\",\"P_SIGN\":\"4C7989D53ED98F421BB8D95441AFE374422466A7\",\"TIMESTAMP\":\"20260905171408\",\"CVC2_RC\":\"\",\"INT_REF\":\"EA22539C32B53E6F\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"E987796EE99A1DA5BB03451B20BB3AC7\",\"MERCH_RN_ID\":\"FF35E7ADE7E94C0B\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"436C96CDE9DB49CD2C8B6D056A525054\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"6-18\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-05 22:14:11', '2026-09-05 17:13:29', '2026-09-09 07:35:02'),
(26, 67, 24, 8, '202609061647323478', '8E73A22F02B3FD34', 500000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"641860\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609061647323478\",\"RRN\":\"624922120167\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"ABE52EFD4335EA5483089613B534D945F424B42A\",\"TIMESTAMP\":\"20260906114904\",\"CVC2_RC\":\"\",\"INT_REF\":\"76E8E3A5A523FA56\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"EE799174077E285E5AE0A88E39333CAB\",\"MERCH_RN_ID\":\"8E73A22F02B3FD34\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"436C96CDE9DB49CD2C8B6D056A525054\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-23\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-06 16:49:08', '2026-09-06 11:47:32', '2026-09-09 07:35:02'),
(27, 67, 24, 8, '202609071303483215', '07C3245C2C198292', 500000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"641911\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609071303483215\",\"RRN\":\"625021299239\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"66851B708AE77374C82B418382E650F37FD68851\",\"TIMESTAMP\":\"20260907080420\",\"CVC2_RC\":\"\",\"INT_REF\":\"CF4B9AB1B2F0B673\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"A14F2818BF75F01DCF6E08EF73715406\",\"MERCH_RN_ID\":\"07C3245C2C198292\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"96A0F126FB3E4AE676BD556F977728FF\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-23\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-07 13:04:24', '2026-09-07 08:03:48', '2026-09-09 07:35:02'),
(28, 67, 24, 8, '202609071337248249', '467B23364F150FE7', 500000, 'KZT', 'failed', 'bcc', '{\"ACTION\":\"2\",\"RC\":\"96\",\"RC_TEXT\":\"Неисправность в системе\",\"APPROVAL\":\"\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"DIAG_CODE\":\"NONE\",\"DIAG_CODE_TEXT\":\"No errors\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609071337248249\",\"RRN\":\"625022120316\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"NAME\":\"fyodor teplov\",\"P_SIGN\":\"ACCD463DEB679952249B313D28FC78F1B5CD78FB\",\"TIMESTAMP\":\"20260907083814\",\"CVC2_RC\":\"\",\"INT_REF\":\"087271517EC89F0C\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"034F4F762D499859C151B2328AC5C937\",\"MERCH_RN_ID\":\"467B23364F150FE7\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-07 13:39:14', '2026-09-07 08:37:24', '2026-09-07 08:39:14'),
(29, 67, 24, 8, '202609071339321234', 'EE306B849BC7906E', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:39:32', '2026-09-09 07:35:02'),
(30, 67, 24, 8, '202609071339375858', '6DA3FC33B1772CC7', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:39:37', '2026-09-09 07:35:02'),
(31, 67, 24, 8, '202609071340119196', '81E8E239C0846053', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:40:11', '2026-09-09 07:35:02'),
(32, 67, 24, 8, '202609071340164148', '46D06A13F24070C0', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:40:16', '2026-09-09 07:35:02'),
(33, 67, 24, 8, '202609071340347648', 'BEB25559BBC6523B', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:40:34', '2026-09-09 07:35:02'),
(34, 67, 24, 8, '202609071341196110', 'B3533F35DAC22C26', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:41:19', '2026-09-09 07:35:02'),
(35, 67, 24, 8, '202609071341258363', 'C43C0D768AA729BC', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:41:25', '2026-09-09 07:35:02'),
(36, 67, 24, 8, '202609071342109710', '67C199A065A9C07B', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:42:10', '2026-09-09 07:35:02'),
(37, 67, 24, 8, '202609071342258134', '900CE2955822663E', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:42:25', '2026-09-09 07:35:02'),
(38, 67, 24, 8, '202609071344232055', 'D293158B04E00E31', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 08:44:23', '2026-09-09 07:35:02'),
(39, 64, 26, 8, '202609071406279819', 'C62019994FA0B38A', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"5-20\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:06:27', '2026-09-09 07:35:02'),
(40, 67, 24, 8, '202609071409567699', '131D09DDFFE4841C', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"13-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:09:56', '2026-09-09 07:35:02'),
(41, 67, 24, 8, '202609071410571453', '1108EEDADA9BEB44', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"13-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:10:57', '2026-09-09 07:35:02'),
(42, 67, 24, 8, '202609071412567911', '9C9C5B1DCA0B9AE0', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:12:56', '2026-09-09 07:35:02'),
(43, 67, 24, 8, '202609071413057793', '3DC131ECF136EDB8', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:13:05', '2026-09-09 07:35:02'),
(44, 67, 24, 8, '202609071413519495', 'C094E1AE8CB43110', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":10000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:13:51', '2026-09-09 07:35:02'),
(45, 67, 24, 8, '202609071413599945', '70E2E1C994B40DAC', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":10000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:13:59', '2026-09-09 07:35:02'),
(46, 67, 24, 8, '202609071414353796', '47613DD7A687E162', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":10000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:14:35', '2026-09-09 07:35:02'),
(47, 67, 24, 8, '202609071414415818', '94B6D7D147A88805', 10000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":100}]', NULL, NULL, NULL, NULL, '2026-09-07 09:14:41', '2026-09-09 07:35:02'),
(48, 67, 24, 8, '202609071415069430', '2FD9297E8953E195', 100000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":1000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:15:06', '2026-09-09 07:35:02'),
(49, 67, 24, 8, '202609071415128042', 'BF3FAAC3D29409BA', 100000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-22\",\"price\":1000}]', NULL, NULL, NULL, NULL, '2026-09-07 09:15:12', '2026-09-09 07:35:02'),
(50, 64, 26, 8, '202609071613367349', '2ABA7FEF260769D6', 500000, 'KZT', 'failed', 'bcc', '{\"ACTION\":\"2\",\"RC\":\"65\",\"RC_TEXT\":\"Превышен лимит операций\",\"APPROVAL\":\"\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"DIAG_CODE\":\"NONE\",\"DIAG_CODE_TEXT\":\"No errors\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609071613367349\",\"RRN\":\"625022120325\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"NAME\":\"fyodor teplov\",\"P_SIGN\":\"8CAB1F69B09AF519FA7005683344F6D0B013EBFD\",\"TIMESTAMP\":\"20260907111514\",\"CVC2_RC\":\"\",\"INT_REF\":\"86795A8A977CAD0C\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"8B743971DC8FE8ABC78109938569B65F\",\"MERCH_RN_ID\":\"2ABA7FEF260769D6\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"7-20\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-07 16:15:20', '2026-09-07 11:13:36', '2026-09-07 11:15:20'),
(51, 64, 26, 8, '202609071628481984', 'C06A47253FD53ED7', 500000, 'KZT', 'failed', 'bcc', '{\"ACTION\":\"2\",\"RC\":\"65\",\"RC_TEXT\":\"Превышен лимит операций\",\"APPROVAL\":\"\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"DIAG_CODE\":\"NONE\",\"DIAG_CODE_TEXT\":\"No errors\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609071628481984\",\"RRN\":\"625023120325\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"NAME\":\"fyodor\",\"P_SIGN\":\"FE5BD56BFCFE60776BCD9687002634CD61EC93EF\",\"TIMESTAMP\":\"20260907112934\",\"CVC2_RC\":\"\",\"INT_REF\":\"5396786D0A4D6CF9\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"A91742154631CAC74A6FD8885E7F28F3\",\"MERCH_RN_ID\":\"C06A47253FD53ED7\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"6-22\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-07 16:29:39', '2026-09-07 11:28:48', '2026-09-07 11:29:39'),
(52, 64, 26, 8, '202609071632333944', '9A0BB747C930E7A6', 500000, 'KZT', 'failed', 'bcc', '{\"ACTION\":\"2\",\"RC\":\"65\",\"RC_TEXT\":\"Превышен лимит операций\",\"APPROVAL\":\"\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"DIAG_CODE\":\"NONE\",\"DIAG_CODE_TEXT\":\"No errors\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609071632333944\",\"RRN\":\"625021299326\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"NAME\":\"fyodor\",\"P_SIGN\":\"786E995C76A838E5FD754982B42E26ADD43F9BB6\",\"TIMESTAMP\":\"20260907113328\",\"CVC2_RC\":\"\",\"INT_REF\":\"513270FABCFABBF7\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"D854F29B8B841AA5AB89DFF5B573D1FD\",\"MERCH_RN_ID\":\"9A0BB747C930E7A6\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"6-17\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-07 16:33:31', '2026-09-07 11:32:33', '2026-09-07 11:33:31'),
(53, 67, 24, 8, '202609071729375183', '18FFBD07BC538B63', 500000, 'KZT', 'failed', 'bcc', '{\"ACTION\":\"2\",\"RC\":\"65\",\"RC_TEXT\":\"Превышен лимит операций\",\"APPROVAL\":\"\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"DIAG_CODE\":\"NONE\",\"DIAG_CODE_TEXT\":\"No errors\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609071729375183\",\"RRN\":\"625022120329\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"NAME\":\"Fyodor\",\"P_SIGN\":\"38486925FACA30D67A973AAC4B3BC5F3EE248D87\",\"TIMESTAMP\":\"20260907123016\",\"CVC2_RC\":\"\",\"INT_REF\":\"69EFBE304BFA0246\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"2FA53C1CC4249D04F265EA171D8D3C16\",\"MERCH_RN_ID\":\"18FFBD07BC538B63\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"13-23\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-07 17:39:43', '2026-09-07 12:29:37', '2026-09-07 12:39:43'),
(54, 67, 24, 8, '202609071753274952', 'C9B0809589104C1A', 500000, 'KZT', 'failed', 'bcc', '{\"ACTION\":\"2\",\"RC\":\"65\",\"RC_TEXT\":\"Превышен лимит операций\",\"APPROVAL\":\"\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"DIAG_CODE\":\"NONE\",\"DIAG_CODE_TEXT\":\"No errors\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609071753274952\",\"RRN\":\"625021299332\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"CEB257CC6195904375CF6E9D7630FCB9A05A1CE0\",\"TIMESTAMP\":\"20260907125356\",\"CVC2_RC\":\"\",\"INT_REF\":\"CB638F3A3BCEA254\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"E47D06349B195F7C4910D21C16B799DD\",\"MERCH_RN_ID\":\"C9B0809589104C1A\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"16-22\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-07 17:54:31', '2026-09-07 12:53:27', '2026-09-07 12:54:31'),
(55, 67, 24, 8, '202609071923076009', 'E9FA6CDBC74976AF', 2000000, 'KZT', 'failed', 'bcc', '{\"ACTION\":\"2\",\"RC\":\"65\",\"RC_TEXT\":\"Превышен лимит операций\",\"APPROVAL\":\"\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"20000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"DIAG_CODE\":\"NONE\",\"DIAG_CODE_TEXT\":\"No errors\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"20000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609071923076009\",\"RRN\":\"625023120335\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"D6B0E153F2FC8EEC5CB0E08954822BF38865AF7F\",\"TIMESTAMP\":\"20260907142424\",\"CVC2_RC\":\"\",\"INT_REF\":\"450C1CEF9832B945\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"B04B2049B3121B895CB20F85768C5661\",\"MERCH_RN_ID\":\"E9FA6CDBC74976AF\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"8-12\",\"price\":20000}]', NULL, NULL, NULL, '2026-09-07 19:24:46', '2026-09-07 14:23:07', '2026-09-07 14:24:46'),
(56, 64, 26, 8, '202609081228126559', '1A60385F86E83ECB', 1000000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642022\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"10000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"10000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081228126559\",\"RRN\":\"625122120354\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"fyodor\",\"P_SIGN\":\"5A1DF014011E760E0C66295A410EC2CE143822CF\",\"TIMESTAMP\":\"20260908073037\",\"CVC2_RC\":\"\",\"INT_REF\":\"19DEC0373BF232B7\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"DD10CBC14E8D7BD95CB1376FA1C7ADA2\",\"MERCH_RN_ID\":\"1A60385F86E83ECB\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', NULL, 'Фёдор', '77772979723', 'ch011tfe@mail.ru', '[{\"identifier\":\"4-17\",\"price\":5000},{\"identifier\":\"3-18\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-08 12:30:48', '2026-09-08 07:28:12', '2026-09-09 07:35:02'),
(57, 64, 26, 8, '202609081240415888', 'C779997458FF1BF9', 1000000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642023\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"10000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"10000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081240415888\",\"RRN\":\"625123120354\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"fyodor\",\"P_SIGN\":\"865B5FCEFAB2F34A111CB3B31E1F17F1194FCF89\",\"TIMESTAMP\":\"20260908074118\",\"CVC2_RC\":\"\",\"INT_REF\":\"C22FBADD642C8E76\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"85CB36AA8112A4EE63D7D2AB455D6E4F\",\"MERCH_RN_ID\":\"C779997458FF1BF9\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"4-18\",\"price\":5000},{\"identifier\":\"5-20\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-08 12:41:22', '2026-09-08 07:40:41', '2026-09-09 07:35:02'),
(58, 67, 24, 8, '202609081243409087', 'F85C2C19FE3EAAAB', 1000000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642024\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"10000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"10000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081243409087\",\"RRN\":\"625121299355\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"fyodor\",\"P_SIGN\":\"798BD01845730D0FDA4EB68A5D4463B21A789558\",\"TIMESTAMP\":\"20260908074429\",\"CVC2_RC\":\"\",\"INT_REF\":\"149D311414832556\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"D57C97C7847F95BF1D51A1E4F9BB6B54\",\"MERCH_RN_ID\":\"F85C2C19FE3EAAAB\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-23\",\"price\":5000},{\"identifier\":\"12-25\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-08 12:44:33', '2026-09-08 07:43:40', '2026-09-09 07:35:02'),
(59, 64, 26, 8, '202609081253106352', '421AA58F5DA12E09', 1000000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642025\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"10000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"10000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081253106352\",\"RRN\":\"625122120355\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"f\",\"P_SIGN\":\"2C0AA42FAFDD2A97B4E25E8759BCFFCA258EC76F\",\"TIMESTAMP\":\"20260908075401\",\"CVC2_RC\":\"\",\"INT_REF\":\"F71409F78C28C6D8\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"E0AC30493E34A035757245DCE5923A7E\",\"MERCH_RN_ID\":\"421AA58F5DA12E09\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"5-17\",\"price\":5000},{\"identifier\":\"6-17\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-08 12:54:04', '2026-09-08 07:53:10', '2026-09-09 07:35:02'),
(60, 67, 24, 8, '202609081255314444', '8ECB05DFFC157177', 500000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642026\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081255314444\",\"RRN\":\"625123120355\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"D4F03EE89424917FC5E80E5472B558FCBCF5CA22\",\"TIMESTAMP\":\"20260908075605\",\"CVC2_RC\":\"\",\"INT_REF\":\"B0DA1345321617A9\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"67B075C52E6582153847059D3CC93F81\",\"MERCH_RN_ID\":\"8ECB05DFFC157177\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"15-24\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-08 12:56:21', '2026-09-08 07:55:31', '2026-09-09 07:35:02'),
(61, 67, 24, 8, '202609081301329875', 'CB4F3B077EBE65DD', 500000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642027\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081301329875\",\"RRN\":\"625121299356\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"7D653CF5D6EBC270A14C6F3543727C842F1371A8\",\"TIMESTAMP\":\"20260908080203\",\"CVC2_RC\":\"\",\"INT_REF\":\"E6F973523FBD503A\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"E4D4539265F388FD3C8B4D75358F1C22\",\"MERCH_RN_ID\":\"CB4F3B077EBE65DD\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"13-24\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-08 13:02:06', '2026-09-08 08:01:32', '2026-09-09 07:35:02'),
(62, 67, 24, 8, '202609081329524724', 'B8EE3CD4D6A38D74', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"15-24\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-08 08:29:52', '2026-09-09 07:35:02'),
(63, 67, 24, 8, '202609081331013260', '7B9F0A9F32F900FB', 500000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642029\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081331013260\",\"RRN\":\"625122120361\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"70474DDA72B4576E7878D380347A2145C13563EA\",\"TIMESTAMP\":\"20260908083140\",\"CVC2_RC\":\"\",\"INT_REF\":\"6891D4AD35C2BE69\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"FF4C39987E5F3C90690A7FDC7AC16D29\",\"MERCH_RN_ID\":\"7B9F0A9F32F900FB\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', NULL, NULL, '77772979723', NULL, '[{\"identifier\":\"12-24\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-08 13:32:21', '2026-09-08 08:31:01', '2026-09-09 07:35:02'),
(64, 67, 24, 8, '202609081334085028', '8D5839F52FB26001', 500000, 'KZT', 'paid', 'bcc', '{\"ORDER\":\"202609081334085028\",\"ACTION\":\"0\",\"RC\":\"00\"}', 21, NULL, '77772979723', NULL, '[{\"identifier\":\"12-25\",\"price\":5000}]', '[\"847bceec0687cbde\"]', 215, '2026-09-08 13:37:28', '2026-09-08 13:37:38', '2026-09-08 08:34:08', '2026-09-08 08:37:38'),
(65, 64, 26, 8, '202609081344192737', 'A54C4C861FDCEF97', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Клиент 1', '77771117711', NULL, '[{\"identifier\":\"7-15\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-08 08:44:19', '2026-09-09 07:35:02'),
(66, 64, 26, 8, '202609081344252504', 'BB213CBD12E965AD', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Клиент 1', '77771117711', NULL, '[{\"identifier\":\"7-15\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-08 08:44:25', '2026-09-09 07:35:02'),
(67, 64, 26, 8, '202609081344262214', '3D009C2E06E20A58', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Клиент 1', '77771117711', NULL, '[{\"identifier\":\"7-15\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-08 08:44:26', '2026-09-09 07:35:02'),
(68, 64, 26, 8, '202609081344572110', '6E48EA4DAB1AD216', 500000, 'KZT', 'cancelled', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642032\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081344572110\",\"RRN\":\"625121299363\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"28A2A36226D32592699525F2E21BC3AF410205B7\",\"TIMESTAMP\":\"20260908084530\",\"CVC2_RC\":\"\",\"INT_REF\":\"04896CF3580094D6\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"8A07C2CBDA01AAF6146C97D5B2B2C89F\",\"MERCH_RN_ID\":\"6E48EA4DAB1AD216\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', NULL, 'Клиент 1', '77777771177', NULL, '[{\"identifier\":\"7-19\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-08 13:46:33', '2026-09-08 08:44:57', '2026-09-09 07:35:02'),
(69, 64, 26, 8, '202609081348564553', '566E1CF490C80DB8', 500000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Клиент 1', '77771117711', 'ch011tfe@mail.ru', '[{\"identifier\":\"7-14\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-08 08:48:56', '2026-09-09 07:35:02'),
(70, 64, 26, 8, '202609081357376376', 'B274340A7328E4A4', 1000000, 'KZT', 'failed', 'bcc', '{\"ACTION\":\"2\",\"RC\":\"61\",\"RC_TEXT\":\"Превышен лимит на сумму\",\"APPROVAL\":\"\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"10000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"DIAG_CODE\":\"NONE\",\"DIAG_CODE_TEXT\":\"No errors\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"10000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081357376376\",\"RRN\":\"625122120363\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"661F6256CA884408B82FFE7F2EBC5BFB7926F6DE\",\"TIMESTAMP\":\"20260908085810\",\"CVC2_RC\":\"\",\"INT_REF\":\"77E1E0B78C521952\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"8D373FEB36F34986C8E8BFF67FB66C11\",\"MERCH_RN_ID\":\"B274340A7328E4A4\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"\"}', NULL, 'Клиент 1', '77771111111', 'ch011tfe@mail.ru', '[{\"identifier\":\"7-20\",\"price\":5000},{\"identifier\":\"7-21\",\"price\":5000}]', NULL, NULL, NULL, '2026-09-08 14:00:07', '2026-09-08 08:57:37', '2026-09-08 09:00:07'),
(71, 67, 24, 8, '202609081650253294', '10BAEA89ECE7F540', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Клиент 1', '77771111111', 'email@email.com', '[{\"identifier\":\"14-22\",\"price\":5000},{\"identifier\":\"12-24\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-08 11:50:25', '2026-09-09 07:35:02'),
(72, 67, 24, 8, '202609081810002948', 'F0767233D96E17CF', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, NULL, '77771111111', NULL, '[{\"identifier\":\"15-24\",\"price\":5000},{\"identifier\":\"15-23\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-08 13:10:00', '2026-09-09 07:35:02'),
(73, 67, 24, 8, '202609081811257784', 'DEFC49CF6694A084', 1000000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642047\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"10000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"10000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081811257784\",\"RRN\":\"625122120392\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"C71A5A901FE1295F6A339B083D55ED7DCA659EC3\",\"TIMESTAMP\":\"20260908131155\",\"CVC2_RC\":\"\",\"INT_REF\":\"AF8A61B17B2203D5\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"F598333367B6A64B94703430C5230161\",\"MERCH_RN_ID\":\"DEFC49CF6694A084\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', 22, NULL, '77771111177', NULL, '[{\"identifier\":\"16-21\",\"price\":5000},{\"identifier\":\"16-22\",\"price\":5000}]', '[\"3d8f3345d17fcdf9\",\"0560af5a20536536\"]', 216, '2026-09-08 18:11:58', '2026-09-08 18:11:58', '2026-09-08 13:11:25', '2026-09-08 13:11:58'),
(74, 64, 26, 8, '202609081818286246', 'B2B97C0AF114C7EB', 1000000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642048\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"10000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"10000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609081818286246\",\"RRN\":\"625121299393\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"F4C18721250F1EF2CC3FEEF781DB3086015AE823\",\"TIMESTAMP\":\"20260908131908\",\"CVC2_RC\":\"\",\"INT_REF\":\"0A3D4A1EAFC662AB\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"7A21DC8F56FC1BEFD02279D64DD857E9\",\"MERCH_RN_ID\":\"B2B97C0AF114C7EB\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', 23, 'Коля', '77777777777', NULL, '[{\"identifier\":\"6-21\",\"price\":5000},{\"identifier\":\"5-20\",\"price\":5000}]', '[\"e6bab31940e1fd34\",\"5e1d55f414597e1a\"]', 217, '2026-09-08 18:21:55', '2026-09-08 18:21:55', '2026-09-08 13:18:28', '2026-09-08 13:21:55'),
(75, 64, 26, 8, '202609091033256680', '0C217ABCCC7C935E', 500000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642053\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609091033256680\",\"RRN\":\"625221299403\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"01DCF166A8FE5B9007F06029BD3722EAB62CA521\",\"TIMESTAMP\":\"20260909053402\",\"CVC2_RC\":\"\",\"INT_REF\":\"91F88A50B69C8AAA\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"0C866A94E73D17F7084E5FF99142E5CE\",\"MERCH_RN_ID\":\"0C217ABCCC7C935E\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', 24, 'Дядя Вас', '77773000000', 'pochta@pochta.com', '[{\"identifier\":\"7-17\",\"price\":5000}]', '[\"8639fc719be0f99c\"]', 218, '2026-09-09 10:34:07', '2026-09-09 10:34:07', '2026-09-09 05:33:25', '2026-09-09 05:34:07'),
(76, 67, 24, 8, '202609091049014102', '3A07484D20B583D8', 500000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642054\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609091049014102\",\"RRN\":\"625222120403\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"F0D103957584C15F772A3DAAE27CBAF1EDD6C9BF\",\"TIMESTAMP\":\"20260909054936\",\"CVC2_RC\":\"\",\"INT_REF\":\"18A4C361E9E872FB\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"AC22F92A4630FA9DD9ACD2E82684E954\",\"MERCH_RN_ID\":\"3A07484D20B583D8\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"A7995349B7D246C238B4DC4580AA427A\"}', 25, 'Васёк', '77001001010', NULL, '[{\"identifier\":\"12-24\",\"price\":5000}]', '[\"48120f169cd7f7cf\"]', 219, '2026-09-09 10:49:38', '2026-09-09 10:49:38', '2026-09-09 05:49:01', '2026-09-09 05:49:38'),
(77, 64, 26, 8, '202609091135278905', '232768AD4EF9736E', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Айгуль', '77111111111', NULL, '[{\"identifier\":\"2-17\",\"price\":5000},{\"identifier\":\"2-16\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-09 06:35:27', '2026-09-09 07:40:03'),
(78, 64, 26, 8, '202609091137484063', '2BD61CBC60DBC86A', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Айгуль', '77711551111', NULL, '[{\"identifier\":\"4-18\",\"price\":5000},{\"identifier\":\"4-19\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-09 06:37:48', '2026-09-09 07:40:03'),
(79, 67, 24, 8, '202609091153495406', '9488844437E0952B', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Анара', '77771234567', NULL, '[{\"identifier\":\"12-21\",\"price\":5000},{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-09 06:53:49', '2026-09-09 07:55:02'),
(80, 67, 24, 8, '202609091154059218', '9304D86F43062863', 1000000, 'KZT', 'cancelled', 'bcc', NULL, NULL, 'Анара', '77771234567', NULL, '[{\"identifier\":\"12-21\",\"price\":5000},{\"identifier\":\"12-22\",\"price\":5000}]', NULL, NULL, NULL, NULL, '2026-09-09 06:54:05', '2026-09-09 07:55:02'),
(81, 67, 24, 8, '202609101350121043', '9A326BFA2DF29CED', 500000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642081\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609101350121043\",\"RRN\":\"625323121117\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"E87B7FB662F44D16F83FA0746532E973075E07BF\",\"TIMESTAMP\":\"20260910085012\",\"CVC2_RC\":\"\",\"INT_REF\":\"6E8ADF0E37D7E27C\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"64AB8446ACD08E0ED8DF2423A5E85DF2\",\"MERCH_RN_ID\":\"9A326BFA2DF29CED\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"001F3F32582F4218481BEE637D2A7F37\"}', 26, 'Дядя Лёша', '+77774000000', 'pochta@pochta.kz', '[{\"identifier\":\"12-23\",\"price\":5000}]', '[\"ba5d80113735b2e0\"]', 220, '2026-09-10 14:13:23', '2026-09-10 14:13:23', '2026-09-10 08:50:12', '2026-09-10 09:13:23'),
(82, 67, 24, 8, '202609101952287718', '05BE3A2ABF979090', 1000000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642090\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"10000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"10000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609101952287718\",\"RRN\":\"625322121129\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"ch011tfe@mail.ru\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"A752E5998AF064D4B5AEE224CF1449453C1DD225\",\"TIMESTAMP\":\"20260910145228\",\"CVC2_RC\":\"\",\"INT_REF\":\"AFDCC77932638A66\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets 10 Негритят\",\"NONCE\":\"1E2EA5F6287F39B75F0A1E334BA374AC\",\"MERCH_RN_ID\":\"05BE3A2ABF979090\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"001F3F32582F4218481BEE637D2A7F37\"}', 27, 'Тимур и его команда', '+75555555555', NULL, '[{\"identifier\":\"15-21\",\"price\":5000},{\"identifier\":\"15-22\",\"price\":5000}]', '[\"033a74a421a6f50e\",\"230841b9be13361f\"]', 221, '2026-09-10 19:54:07', '2026-09-10 19:54:07', '2026-09-10 14:52:28', '2026-09-10 14:54:07'),
(83, 64, 26, 8, '202609102010228834', 'EE4C3095BA4A4F7D', 500000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642092\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609102010228834\",\"RRN\":\"625321300130\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"231A8F729C50A113BDCBA228A55D5688593719E1\",\"TIMESTAMP\":\"20260910151022\",\"CVC2_RC\":\"\",\"INT_REF\":\"4841F9954A4FF070\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"20F9E83159CB77A729B9C1F14DBDEECA\",\"MERCH_RN_ID\":\"EE4C3095BA4A4F7D\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"001F3F32582F4218481BEE637D2A7F37\"}', 28, 'Дядя', '+77776000000', 'pochta@pochta.com', '[{\"identifier\":\"3-20\",\"price\":5000}]', '[\"8c64c404142b8272\"]', 222, '2026-09-10 20:17:49', '2026-09-10 20:17:49', '2026-09-10 15:10:22', '2026-09-10 15:17:49'),
(84, 64, 26, 8, '202609102030096974', '119326E6EFDA1D04', 500000, 'KZT', 'paid', 'bcc', '{\"ACTION\":\"0\",\"RC\":\"00\",\"RC_TEXT\":\"Завершено успешно\",\"APPROVAL\":\"642094\",\"TRAN_CUR_NAME\":\"KZT\",\"TRAN_AMOUNT\":\"5000.00\",\"CARD_MASK\":\"4463XXXXXXXX7828\",\"TERMINAL\":\"88888881\",\"TRTYPE\":\"1\",\"AMOUNT\":\"5000.00\",\"CURRENCY\":\"398\",\"ORDER\":\"202609102030096974\",\"RRN\":\"625323121130\",\"MERCHANT\":\"00000001\",\"LANG\":\"ru\",\"EMAIL\":\"\",\"NAME\":\"teplov fyodor\",\"P_SIGN\":\"689B55D03EC17AACFDA49C3661E547C176B18B74\",\"TIMESTAMP\":\"20260910153009\",\"CVC2_RC\":\"\",\"INT_REF\":\"672E898E9D69AE05\",\"MERCH_GMT\":\"0\",\"DESC\":\"Tickets Зурико\",\"NONCE\":\"A85D4FC170FCE6608DD929F2CA3574C1\",\"MERCH_RN_ID\":\"119326E6EFDA1D04\",\"MERCH_TRAN_STATE\":\"\",\"RECUR_FREQ\":\"\",\"RECUR_EXP\":\"\",\"MERCH_TOKEN_ID\":\"001F3F32582F4218481BEE637D2A7F37\"}', 29, 'Дядя Кекс', '+77777000000', 'pochta@pochta.com', '[{\"identifier\":\"6-23\",\"price\":5000}]', '[\"aebd92d5295ae407\"]', 223, '2026-09-10 20:33:13', '2026-09-10 20:33:13', '2026-09-10 15:30:09', '2026-09-10 15:33:13');

-- --------------------------------------------------------

--
-- Структура таблицы `refunds`
--

CREATE TABLE `refunds` (
  `id` int(10) UNSIGNED NOT NULL,
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `ticket_uid` varchar(64) DEFAULT NULL,
  `schedule_id` int(10) UNSIGNED DEFAULT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `refund_status` enum('requested','approved','rejected','refunded') NOT NULL,
  `refund_method` enum('cash','noncash','bank') NOT NULL DEFAULT 'noncash',
  `refund_provider` varchar(100) DEFAULT NULL,
  `refund_transaction_id` varchar(255) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `refunds`
--

INSERT INTO `refunds` (`id`, `ticket_id`, `ticket_uid`, `schedule_id`, `refund_amount`, `refund_status`, `refund_method`, `refund_provider`, `refund_transaction_id`, `approved_at`, `reason`, `processed_by`, `created_at`) VALUES
(13, 297, '48120f169cd7f7cf', 67, 5000.00, 'requested', 'bank', 'bcc', NULL, NULL, 'Самостоятельный возврат клиента', NULL, '2026-09-09 06:07:49'),
(14, 298, 'ba5d80113735b2e0', 67, 5000.00, 'requested', 'bank', 'bcc', NULL, NULL, 'Самостоятельный возврат клиента', NULL, '2026-09-10 09:37:58'),
(15, 299, '033a74a421a6f50e', 67, 5000.00, 'requested', 'bank', 'bcc', NULL, NULL, 'Самостоятельный возврат клиента', NULL, '2026-09-10 15:05:26'),
(16, 300, '230841b9be13361f', 67, 5000.00, 'requested', 'bank', 'bcc', NULL, NULL, 'Самостоятельный возврат клиента', NULL, '2026-09-10 15:05:26'),
(17, 302, 'aebd92d5295ae407', 64, 5000.00, 'rejected', 'bank', 'bcc', NULL, NULL, 'BCC transport error: test3ds.bcc.kz:5445 unavailable', NULL, '2026-09-10 15:46:13'),
(18, 302, 'aebd92d5295ae407', 64, 5000.00, 'rejected', 'bank', 'bcc', '202609102030096974', NULL, 'BCC response: {\"ACTION\":\"\",\"RC\":\"TRANSPORT_ERROR\",\"RC_TEXT\":\"Failed to connect to test3ds.bcc.kz:5445 after 1036 ms: Could not connect to server\",\"ORDER\":\"202609102030096974\"}', NULL, '2026-09-10 15:58:20');

-- --------------------------------------------------------

--
-- Структура таблицы `schedules`
--

CREATE TABLE `schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `hall_id` int(10) UNSIGNED NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `sales_start_time` datetime DEFAULT NULL,
  `sales_end_time` datetime DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `min_price` decimal(10,2) DEFAULT NULL,
  `max_price` decimal(10,2) DEFAULT NULL,
  `status` enum('draft','upcoming','active','archive','cancelled') DEFAULT NULL,
  `is_sold_out` tinyint(1) NOT NULL DEFAULT 0,
  `seat_map` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`seat_map`)),
  `price_ranges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`price_ranges`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `schedules`
--

INSERT INTO `schedules` (`id`, `event_id`, `hall_id`, `start_time`, `end_time`, `sales_start_time`, `sales_end_time`, `base_price`, `min_price`, `max_price`, `status`, `is_sold_out`, `seat_map`, `price_ranges`, `notes`, `created_at`, `updated_at`) VALUES
(59, 26, 8, '2026-08-25 09:00:00', '2026-08-25 12:00:00', '2026-08-18 09:00:00', NULL, 2000.00, NULL, NULL, 'archive', 0, '{\"meta\":{\"baseSeatSize\":22,\"gapX\":8,\"gapY\":12},\"anchors\":{\"layout_origin\":{\"x\":0,\"y\":0,\"_px\":{\"x\":80,\"y\":40}}},\"rows\":[{\"number\":1,\"segments\":[{\"id\":\"seg_505007\",\"start\":1,\"end\":16,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.5,\"y\":16.235299999999999}}]},{\"number\":2,\"segments\":[{\"id\":\"seg_528935\",\"start\":1,\"end\":17,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.9,\"y\":15.3529}}]},{\"number\":3,\"segments\":[{\"id\":\"seg_539893\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":14.470599999999999}},{\"id\":\"seg_585741\",\"start\":3,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.4,\"y\":14.470599999999999}},{\"id\":\"seg_601124\",\"start\":21,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":14.470599999999999}}]},{\"number\":4,\"segments\":[{\"id\":\"seg_634636\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":13.588200000000001}},{\"id\":\"seg_642964\",\"start\":3,\"end\":21,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.9000000000000004,\"y\":13.588200000000001}},{\"id\":\"seg_658156\",\"start\":22,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":13.588200000000001}}]},{\"number\":5,\"segments\":[{\"id\":\"seg_678812\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":12.7059}},{\"id\":\"seg_908012\",\"start\":3,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.4000000000000004,\"y\":12.7058}},{\"id\":\"seg_935252\",\"start\":23,\"end\":24,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":12.7059}}]},{\"number\":6,\"segments\":[{\"id\":\"seg_005300\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":11.823499999999999}},{\"id\":\"seg_040628\",\"start\":3,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.9000000000000004,\"y\":11.823499999999999}},{\"id\":\"seg_067980\",\"start\":24,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":11.823499999999999}}]},{\"number\":7,\"segments\":[{\"id\":\"seg_107228\",\"start\":1,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.3000000000000007,\"y\":10.941000000000001}}]},{\"number\":8,\"segments\":[{\"id\":\"seg_169980\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":8.7353000000000005}}]},{\"number\":9,\"segments\":[{\"id\":\"seg_188773\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":7.8529}}]},{\"number\":10,\"segments\":[{\"id\":\"seg_196604\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.9706000000000001}}]},{\"number\":11,\"segments\":[{\"id\":\"seg_204268\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.0883000000000003}}]},{\"number\":12,\"segments\":[{\"id\":\"seg_215092\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":5.2058999999999997}}]},{\"number\":13,\"segments\":[{\"id\":\"seg_222460\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":4.3235000000000001}}]},{\"number\":14,\"segments\":[{\"id\":\"seg_228892\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":3.4411999999999998}}]},{\"number\":15,\"segments\":[{\"id\":\"seg_231084\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":2.5589}}]},{\"number\":16,\"segments\":[{\"id\":\"seg_322420\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":1.6765000000000001}}]}],\"elements\":[{\"id\":\"el_485535\",\"type\":\"rect\",\"name\":\"Сцена\",\"text\":\"Сцена\",\"x\":246,\"y\":642,\"width\":810,\"height\":54,\"fill\":\"#ffffff\",\"stroke\":\"#2f9b6f\",\"textColor\":\"#0b3b4a\"}],\"seats\":{\"13-1\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"13-2\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"13-3\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"13-4\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"13-5\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"13-6\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"13-7\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"13-8\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"13-9\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"14-1\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"14-2\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"14-3\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"14-4\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"14-5\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"14-6\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"14-7\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"14-8\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"14-9\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"15-1\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"15-2\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"15-3\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"15-4\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"15-5\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"15-6\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"15-7\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"15-8\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"15-9\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"16-1\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"16-2\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"16-3\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"16-4\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"16-5\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"16-6\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"16-7\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"16-8\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"16-9\":{\"meta\":{\"price\":\"3000\",\"color\":\"#1de917\"}},\"8-20\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"8-21\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"8-22\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"8-23\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"8-24\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"8-25\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"9-20\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"9-21\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"9-22\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"9-23\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"9-24\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"9-25\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"10-20\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"10-21\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"10-22\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"10-23\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"10-24\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"10-25\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"11-20\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"11-21\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"11-22\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"11-23\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"11-24\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"11-25\":{\"meta\":{\"price\":\"2000\",\"color\":\"#be7417\"}},\"3-3\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"3-4\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"3-5\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"4-3\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"4-4\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"4-5\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"4-6\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"5-3\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"5-4\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"5-5\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"5-6\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"6-3\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"6-4\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"6-5\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"6-6\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"6-7\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"7-1\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"7-2\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"7-3\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"7-4\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}},\"7-5\":{\"meta\":{\"price\":\"12000\",\"color\":\"#75b3f5\"}}}}', '[{\"id\":\"g1787113104970616\",\"price\":\"3000\",\"color\":\"#1de917\"},{\"id\":\"g1787116077869606\",\"price\":\"2000\",\"color\":\"#be7417\"},{\"id\":\"g1787118265772789\",\"price\":\"12000\",\"color\":\"#75b3f5\"}]', 'Рабочая схема', '2026-08-18 04:01:24', '2026-08-25 05:40:02'),
(61, 30, 8, '2026-08-30 12:29:00', '2026-08-30 14:29:00', NULL, NULL, 2000.00, NULL, NULL, 'archive', 0, '{\"meta\":{\"baseSeatSize\":22,\"gapX\":8,\"gapY\":12},\"anchors\":{\"layout_origin\":{\"x\":0,\"y\":0,\"_px\":{\"x\":80,\"y\":40}}},\"rows\":[{\"number\":1,\"segments\":[{\"id\":\"seg_505007\",\"start\":1,\"end\":16,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.5,\"y\":16.235299999999999}}]},{\"number\":2,\"segments\":[{\"id\":\"seg_528935\",\"start\":1,\"end\":17,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.9,\"y\":15.3529}}]},{\"number\":3,\"segments\":[{\"id\":\"seg_539893\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":14.470599999999999}},{\"id\":\"seg_585741\",\"start\":3,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.4,\"y\":14.470599999999999}},{\"id\":\"seg_601124\",\"start\":21,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":14.470599999999999}}]},{\"number\":4,\"segments\":[{\"id\":\"seg_634636\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":13.588200000000001}},{\"id\":\"seg_642964\",\"start\":3,\"end\":21,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.9000000000000004,\"y\":13.588200000000001}},{\"id\":\"seg_658156\",\"start\":22,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":13.588200000000001}}]},{\"number\":5,\"segments\":[{\"id\":\"seg_678812\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":12.7059}},{\"id\":\"seg_908012\",\"start\":3,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.4000000000000004,\"y\":12.7058}},{\"id\":\"seg_935252\",\"start\":23,\"end\":24,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":12.7059}}]},{\"number\":6,\"segments\":[{\"id\":\"seg_005300\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":11.823499999999999}},{\"id\":\"seg_040628\",\"start\":3,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.9000000000000004,\"y\":11.823499999999999}},{\"id\":\"seg_067980\",\"start\":24,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":11.823499999999999}}]},{\"number\":7,\"segments\":[{\"id\":\"seg_107228\",\"start\":1,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.3000000000000007,\"y\":10.941000000000001}}]},{\"number\":8,\"segments\":[{\"id\":\"seg_169980\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":8.7353000000000005}}]},{\"number\":9,\"segments\":[{\"id\":\"seg_188773\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":7.8529}}]},{\"number\":10,\"segments\":[{\"id\":\"seg_196604\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.9706000000000001}}]},{\"number\":11,\"segments\":[{\"id\":\"seg_204268\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.0883000000000003}}]},{\"number\":12,\"segments\":[{\"id\":\"seg_215092\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":5.2058999999999997}}]},{\"number\":13,\"segments\":[{\"id\":\"seg_222460\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":4.3235000000000001}}]},{\"number\":14,\"segments\":[{\"id\":\"seg_228892\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":3.4411999999999998}}]},{\"number\":15,\"segments\":[{\"id\":\"seg_231084\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":2.5589}}]},{\"number\":16,\"segments\":[{\"id\":\"seg_322420\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":1.6765000000000001}}]}],\"elements\":[{\"id\":\"el_485535\",\"type\":\"rect\",\"name\":\"Сцена\",\"text\":\"Сцена\",\"x\":246,\"y\":642,\"width\":810,\"height\":54,\"fill\":\"#ffffff\",\"stroke\":\"#2f9b6f\",\"textColor\":\"#0b3b4a\"}],\"seats\":{\"1-11\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"1-12\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"1-13\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"1-14\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"1-15\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"1-16\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"2-12\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"2-13\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"2-14\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"2-15\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"2-16\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"2-17\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"3-14\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"3-15\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"3-16\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"3-17\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"3-18\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"3-19\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"3-20\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"4-15\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"4-16\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"4-17\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"4-18\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"4-19\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"4-20\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"4-21\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"5-15\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"5-16\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"5-17\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"5-18\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"5-19\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"5-20\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"5-21\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"5-22\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"6-17\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"6-18\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"6-19\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"6-20\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"6-21\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"6-22\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"6-23\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"7-16\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"7-17\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"7-18\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"7-19\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"7-20\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"7-21\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"7-22\":{\"meta\":{\"price\":\"10000\",\"color\":\"#ec7cfe\"}},\"8-16\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"15-1\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"15-2\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"15-3\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"15-4\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"15-5\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"15-6\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"15-7\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"15-8\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"16-1\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"16-2\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"16-3\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"16-4\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"16-5\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"16-6\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"16-7\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"16-8\":{\"meta\":{\"price\":\"5000\",\"color\":\"#f5ff70\"}},\"7-1\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"7-2\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}},\"7-3\":{\"meta\":{\"price\":\"12000\",\"color\":\"#85ffda\"}}}}', '[{\"id\":\"g1787210967627964\",\"price\":\"12000\",\"color\":\"#85ffda\"},{\"id\":\"g1787210981271206\",\"price\":\"10000\",\"color\":\"#ec7cfe\"},{\"id\":\"g1787210999164961\",\"price\":\"8000\",\"color\":\"#ff938a\"},{\"id\":\"g1787211016031146\",\"price\":\"5000\",\"color\":\"#f5ff70\"}]', 'Для работы кассы', '2026-08-20 07:30:30', '2026-08-30 08:40:02'),
(62, 24, 11, '2026-09-10 22:43:00', '2026-09-11 00:44:00', NULL, NULL, 2000.00, NULL, NULL, 'archive', 0, '{\"meta\":{\"baseSeatSize\":28,\"gapX\":8,\"gapY\":12},\"anchors\":{\"layout_origin\":{\"x\":0,\"y\":0,\"_px\":{\"x\":80,\"y\":40}}},\"rows\":[{\"number\":1,\"segments\":[{\"id\":\"seg_699488\",\"start\":11,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.1112,\"y\":12.199999999999999}},{\"id\":\"seg_705440\",\"start\":1,\"end\":10,\"anchor\":\"layout_origin\",\"offset\":{\"x\":-0.111,\"y\":12.199999999999999}},{\"id\":\"seg_719200\",\"start\":21,\"end\":30,\"anchor\":\"layout_origin\",\"offset\":{\"x\":22.222300000000001,\"y\":12.199999999999999}}]},{\"number\":2,\"segments\":[{\"id\":\"seg_747975\",\"start\":21,\"end\":30,\"anchor\":\"layout_origin\",\"offset\":{\"x\":22.222200000000001,\"y\":11.300000000000001}},{\"id\":\"seg_758287\",\"start\":11,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.1111,\"y\":11.300000000000001}},{\"id\":\"seg_765527\",\"start\":1,\"end\":10,\"anchor\":\"layout_origin\",\"offset\":{\"x\":-0.1111,\"y\":11.300000000000001}}]},{\"number\":3,\"segments\":[{\"id\":\"seg_776551\",\"start\":1,\"end\":10,\"anchor\":\"layout_origin\",\"offset\":{\"x\":-0.1111,\"y\":10.4}},{\"id\":\"seg_796127\",\"start\":11,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.1111,\"y\":10.4}},{\"id\":\"seg_803575\",\"start\":21,\"end\":30,\"anchor\":\"layout_origin\",\"offset\":{\"x\":22.222200000000001,\"y\":10.4}}]}],\"elements\":[{\"id\":\"el_682336\",\"type\":\"rect\",\"name\":\"Сцена\",\"text\":\"Сцена\",\"x\":483,\"y\":581,\"width\":350,\"height\":112,\"fill\":\"#ffffff\",\"stroke\":\"#2f9b6f\",\"textColor\":\"#0b3b4a\"}],\"seats\":{\"3-1\":{\"meta\":{\"price\":\"2000\",\"color\":\"#bdffcd\"}},\"3-11\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"3-21\":{\"meta\":{\"price\":\"4250\",\"color\":\"#fff58a\"}},\"1-11\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"1-12\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"1-13\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"1-14\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"1-15\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"2-11\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"2-12\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"2-13\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"2-14\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"2-15\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"3-12\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"3-13\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"3-14\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}},\"3-15\":{\"meta\":{\"price\":\"3000\",\"color\":\"#f1adff\"}}}}', '[{\"id\":\"g1787420690826508\",\"price\":\"2000\",\"color\":\"#bdffcd\"},{\"id\":\"g1787420705598573\",\"price\":\"3000\",\"color\":\"#f1adff\"},{\"id\":\"g1787420728706549\",\"price\":\"4250\",\"color\":\"#fff58a\"}]', 'Проба малого зала', '2026-08-22 17:46:26', '2026-09-05 16:50:05'),
(63, 24, 8, '2026-08-28 14:07:00', '2026-08-28 15:07:00', NULL, NULL, 2000.00, NULL, NULL, 'archive', 0, '{\"meta\":{\"baseSeatSize\":22,\"gapX\":8,\"gapY\":12},\"anchors\":{\"layout_origin\":{\"x\":0,\"y\":0,\"_px\":{\"x\":80,\"y\":40}}},\"rows\":[{\"number\":1,\"segments\":[{\"id\":\"seg_505007\",\"start\":1,\"end\":16,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.5,\"y\":16.235299999999999}}]},{\"number\":2,\"segments\":[{\"id\":\"seg_528935\",\"start\":1,\"end\":17,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.9,\"y\":15.3529}}]},{\"number\":3,\"segments\":[{\"id\":\"seg_539893\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":14.470599999999999}},{\"id\":\"seg_585741\",\"start\":3,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.4,\"y\":14.470599999999999}},{\"id\":\"seg_601124\",\"start\":21,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":14.470599999999999}}]},{\"number\":4,\"segments\":[{\"id\":\"seg_634636\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":13.588200000000001}},{\"id\":\"seg_642964\",\"start\":3,\"end\":21,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.9000000000000004,\"y\":13.588200000000001}},{\"id\":\"seg_658156\",\"start\":22,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":13.588200000000001}}]},{\"number\":5,\"segments\":[{\"id\":\"seg_678812\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":12.7059}},{\"id\":\"seg_908012\",\"start\":3,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.4000000000000004,\"y\":12.7058}},{\"id\":\"seg_935252\",\"start\":23,\"end\":24,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":12.7059}}]},{\"number\":6,\"segments\":[{\"id\":\"seg_005300\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":11.823499999999999}},{\"id\":\"seg_040628\",\"start\":3,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.9000000000000004,\"y\":11.823499999999999}},{\"id\":\"seg_067980\",\"start\":24,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":11.823499999999999}}]},{\"number\":7,\"segments\":[{\"id\":\"seg_107228\",\"start\":1,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.3000000000000007,\"y\":10.941000000000001}}]},{\"number\":8,\"segments\":[{\"id\":\"seg_169980\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":8.7353000000000005}}]},{\"number\":9,\"segments\":[{\"id\":\"seg_188773\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":7.8529}}]},{\"number\":10,\"segments\":[{\"id\":\"seg_196604\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.9706000000000001}}]},{\"number\":11,\"segments\":[{\"id\":\"seg_204268\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.0883000000000003}}]},{\"number\":12,\"segments\":[{\"id\":\"seg_215092\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":5.2058999999999997}}]},{\"number\":13,\"segments\":[{\"id\":\"seg_222460\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":4.3235000000000001}}]},{\"number\":14,\"segments\":[{\"id\":\"seg_228892\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":3.4411999999999998}}]},{\"number\":15,\"segments\":[{\"id\":\"seg_231084\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":2.5589}}]},{\"number\":16,\"segments\":[{\"id\":\"seg_322420\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":1.6765000000000001}}]}],\"elements\":[{\"id\":\"el_485535\",\"type\":\"rect\",\"name\":\"Сцена\",\"text\":\"Сцена\",\"x\":246,\"y\":642,\"width\":810,\"height\":54,\"fill\":\"#ffffff\",\"stroke\":\"#2f9b6f\",\"textColor\":\"#0b3b4a\"}],\"seats\":{\"5-14\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"5-15\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"5-16\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"5-17\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"5-18\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"5-19\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"5-20\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"5-21\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"5-22\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-14\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-15\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-16\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-17\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-18\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-19\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-20\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-21\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-22\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"6-23\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-13\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-14\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-15\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-16\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-17\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-18\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-19\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-20\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-21\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"7-22\":{\"meta\":{\"price\":\"3000\",\"color\":\"#ffb8b8\"}},\"10-19\":{\"meta\":{\"price\":\"2000\",\"color\":\"#5cff6f\"}},\"12-20\":{\"meta\":{\"price\":\"2000\",\"color\":\"#5cff6f\"}},\"14-21\":{\"meta\":{\"price\":\"2000\",\"color\":\"#5cff6f\"}},\"9-23\":{\"meta\":{\"price\":\"2000\",\"color\":\"#5cff6f\"}},\"8-24\":{\"meta\":{\"price\":\"2000\",\"color\":\"#5cff6f\"}},\"9-7\":{\"meta\":{\"price\":\"2000\",\"color\":\"#5cff6f\"}},\"12-7\":{\"meta\":{\"price\":\"2000\",\"color\":\"#5cff6f\"}},\"1-1\":{\"meta\":{\"price\":\"2000\",\"color\":\"#5cff6f\"}}}}', '[{\"id\":\"g1787648903317439\",\"price\":\"3000\",\"color\":\"#ffb8b8\"},{\"id\":\"g1787648947442676\",\"price\":\"2000\",\"color\":\"#5cff6f\"}]', '', '2026-08-25 09:09:25', '2026-08-28 10:50:02'),
(64, 26, 8, '2026-09-23 14:12:00', '2026-09-23 15:12:00', '2026-08-26 13:30:00', '2026-09-23 14:12:00', 1000.00, NULL, NULL, 'upcoming', 0, '{\"meta\":{\"baseSeatSize\":22,\"gapX\":8,\"gapY\":12},\"anchors\":{\"layout_origin\":{\"x\":0,\"y\":0,\"_px\":{\"x\":80,\"y\":40}}},\"rows\":[{\"number\":1,\"segments\":[{\"id\":\"seg_505007\",\"start\":1,\"end\":16,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.5,\"y\":16.235299999999999}}]},{\"number\":2,\"segments\":[{\"id\":\"seg_528935\",\"start\":1,\"end\":17,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.9,\"y\":15.3529}}]},{\"number\":3,\"segments\":[{\"id\":\"seg_539893\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":14.470599999999999}},{\"id\":\"seg_585741\",\"start\":3,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.4,\"y\":14.470599999999999}},{\"id\":\"seg_601124\",\"start\":21,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":14.470599999999999}}]},{\"number\":4,\"segments\":[{\"id\":\"seg_634636\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":13.588200000000001}},{\"id\":\"seg_642964\",\"start\":3,\"end\":21,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.9000000000000004,\"y\":13.588200000000001}},{\"id\":\"seg_658156\",\"start\":22,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":13.588200000000001}}]},{\"number\":5,\"segments\":[{\"id\":\"seg_678812\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":12.7059}},{\"id\":\"seg_908012\",\"start\":3,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.4000000000000004,\"y\":12.7058}},{\"id\":\"seg_935252\",\"start\":23,\"end\":24,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":12.7059}}]},{\"number\":6,\"segments\":[{\"id\":\"seg_005300\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":11.823499999999999}},{\"id\":\"seg_040628\",\"start\":3,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.9000000000000004,\"y\":11.823499999999999}},{\"id\":\"seg_067980\",\"start\":24,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":11.823499999999999}}]},{\"number\":7,\"segments\":[{\"id\":\"seg_107228\",\"start\":1,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.3000000000000007,\"y\":10.941000000000001}}]},{\"number\":8,\"segments\":[{\"id\":\"seg_169980\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":8.7353000000000005}}]},{\"number\":9,\"segments\":[{\"id\":\"seg_188773\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":7.8529}}]},{\"number\":10,\"segments\":[{\"id\":\"seg_196604\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.9706000000000001}}]},{\"number\":11,\"segments\":[{\"id\":\"seg_204268\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.0883000000000003}}]},{\"number\":12,\"segments\":[{\"id\":\"seg_215092\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":5.2058999999999997}}]},{\"number\":13,\"segments\":[{\"id\":\"seg_222460\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":4.3235000000000001}}]},{\"number\":14,\"segments\":[{\"id\":\"seg_228892\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":3.4411999999999998}}]},{\"number\":15,\"segments\":[{\"id\":\"seg_231084\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":2.5589}}]},{\"number\":16,\"segments\":[{\"id\":\"seg_322420\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":1.6765000000000001}}]}],\"elements\":[{\"id\":\"el_485535\",\"type\":\"rect\",\"name\":\"Сцена\",\"text\":\"Сцена\",\"x\":246,\"y\":642,\"width\":810,\"height\":54,\"fill\":\"#ffffff\",\"stroke\":\"#2f9b6f\",\"textColor\":\"#0b3b4a\"}],\"seats\":{\"1-11\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"1-12\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"1-13\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"1-14\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"1-15\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"1-16\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"2-12\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"2-13\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"2-14\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"2-15\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"2-16\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"2-17\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"3-14\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"3-15\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"3-16\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"3-17\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"3-18\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"3-19\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"3-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"4-15\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"4-16\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"4-17\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"4-18\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"4-19\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"4-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"4-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-15\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-16\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-17\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-18\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-19\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-16\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-17\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-18\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-19\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-14\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-15\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-16\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-17\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-18\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-19\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"4-14\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-14\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-15\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-14\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-13\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-12\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-11\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"2-11\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"1-10\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"2-10\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"3-12\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"4-12\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"6-12\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"7-10\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"5-12\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ffadad\"}},\"8-1\":{\"meta\":{\"price\":\"10000\",\"color\":\"#dbd118\"}}}}', '[{\"id\":\"g1787732011668519\",\"price\":\"5000\",\"color\":\"#ffadad\"},{\"id\":\"g1787732076339662\",\"price\":\"10000\",\"color\":\"#dbd118\"}]', '', '2026-08-26 08:14:41', '2026-08-26 08:31:08'),
(67, 24, 8, '2026-09-26 13:31:00', '2026-09-26 14:31:00', '2026-08-27 13:31:00', '2026-08-27 14:34:00', 2000.00, NULL, NULL, 'upcoming', 0, '{\"meta\":{\"baseSeatSize\":22,\"gapX\":8,\"gapY\":12},\"anchors\":{\"layout_origin\":{\"x\":0,\"y\":0,\"_px\":{\"x\":80,\"y\":40}}},\"rows\":[{\"number\":1,\"segments\":[{\"id\":\"seg_505007\",\"start\":1,\"end\":16,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.5,\"y\":16.235299999999999}}]},{\"number\":2,\"segments\":[{\"id\":\"seg_528935\",\"start\":1,\"end\":17,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.9,\"y\":15.3529}}]},{\"number\":3,\"segments\":[{\"id\":\"seg_539893\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":14.470599999999999}},{\"id\":\"seg_585741\",\"start\":3,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.4,\"y\":14.470599999999999}},{\"id\":\"seg_601124\",\"start\":21,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":14.470599999999999}}]},{\"number\":4,\"segments\":[{\"id\":\"seg_634636\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":13.588200000000001}},{\"id\":\"seg_642964\",\"start\":3,\"end\":21,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.9000000000000004,\"y\":13.588200000000001}},{\"id\":\"seg_658156\",\"start\":22,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":13.588200000000001}}]},{\"number\":5,\"segments\":[{\"id\":\"seg_678812\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":12.7059}},{\"id\":\"seg_908012\",\"start\":3,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.4000000000000004,\"y\":12.7058}},{\"id\":\"seg_935252\",\"start\":23,\"end\":24,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":12.7059}}]},{\"number\":6,\"segments\":[{\"id\":\"seg_005300\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":11.823499999999999}},{\"id\":\"seg_040628\",\"start\":3,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.9000000000000004,\"y\":11.823499999999999}},{\"id\":\"seg_067980\",\"start\":24,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":11.823499999999999}}]},{\"number\":7,\"segments\":[{\"id\":\"seg_107228\",\"start\":1,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.3000000000000007,\"y\":10.941000000000001}}]},{\"number\":8,\"segments\":[{\"id\":\"seg_169980\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":8.7353000000000005}}]},{\"number\":9,\"segments\":[{\"id\":\"seg_188773\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":7.8529}}]},{\"number\":10,\"segments\":[{\"id\":\"seg_196604\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.9706000000000001}}]},{\"number\":11,\"segments\":[{\"id\":\"seg_204268\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.0883000000000003}}]},{\"number\":12,\"segments\":[{\"id\":\"seg_215092\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":5.2058999999999997}}]},{\"number\":13,\"segments\":[{\"id\":\"seg_222460\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":4.3235000000000001}}]},{\"number\":14,\"segments\":[{\"id\":\"seg_228892\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":3.4411999999999998}}]},{\"number\":15,\"segments\":[{\"id\":\"seg_231084\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":2.5589}}]},{\"number\":16,\"segments\":[{\"id\":\"seg_322420\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":1.6765000000000001}}]}],\"elements\":[{\"id\":\"el_485535\",\"type\":\"rect\",\"name\":\"Сцена\",\"text\":\"Сцена\",\"x\":246,\"y\":642,\"width\":810,\"height\":54,\"fill\":\"#ffffff\",\"stroke\":\"#2f9b6f\",\"textColor\":\"#0b3b4a\"}],\"seats\":{\"12-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-10\":{\"meta\":{\"price\":\"10000\",\"color\":\"#e887f7\"}},\"1-5\":{\"meta\":{\"price\":\"20000\",\"color\":\"#9efff4\"}},\"8-12\":{\"meta\":{\"price\":\"20000\",\"color\":\"#9efff4\"}},\"10-5\":{\"meta\":{\"price\":\"20000\",\"color\":\"#9efff4\"}}}}', '[{\"id\":\"g1787733126986642\",\"price\":\"5000\",\"color\":\"#ff9494\"},{\"id\":\"g1787823300040214\",\"price\":\"10000\",\"color\":\"#e887f7\"},{\"id\":\"g1788359902596104\",\"price\":\"20000\",\"color\":\"#9efff4\"}]', 'Продублированный', '2026-08-27 07:03:29', '2026-09-02 14:38:26');

-- --------------------------------------------------------

--
-- Структура таблицы `schedule_prices`
--

CREATE TABLE `schedule_prices` (
  `id` int(10) UNSIGNED NOT NULL,
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `row_start` int(10) UNSIGNED NOT NULL,
  `row_end` int(10) UNSIGNED NOT NULL,
  `zone_name` varchar(100) DEFAULT NULL,
  `seat_type` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `seats`
--

CREATE TABLE `seats` (
  `id` int(10) UNSIGNED NOT NULL,
  `hall_id` int(10) UNSIGNED NOT NULL,
  `row_number` int(10) UNSIGNED NOT NULL,
  `seat_number` int(10) UNSIGNED NOT NULL,
  `seat_label` varchar(100) DEFAULT NULL,
  `zone_name` varchar(100) DEFAULT NULL,
  `seat_type` varchar(50) DEFAULT NULL,
  `seat_group` varchar(50) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `is_wheelchair` tinyint(1) NOT NULL DEFAULT 0,
  `base_price` decimal(10,2) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `x` int(10) UNSIGNED DEFAULT NULL,
  `y` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `seat_occupancy`
--

CREATE TABLE `seat_occupancy` (
  `id` int(10) UNSIGNED NOT NULL,
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `seat_identifier` varchar(50) NOT NULL,
  `ticket_id` int(10) UNSIGNED DEFAULT NULL,
  `reserved_by` int(10) UNSIGNED DEFAULT NULL,
  `reserved_until` datetime DEFAULT NULL,
  `reserved_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `seat_occupancy`
--

INSERT INTO `seat_occupancy` (`id`, `schedule_id`, `seat_identifier`, `ticket_id`, `reserved_by`, `reserved_until`, `reserved_at`, `created_at`) VALUES
(97, 67, '16-10', 283, NULL, NULL, '2026-09-02 12:21:23', '2026-08-27 14:59:12'),
(103, 67, '16-20', 284, NULL, NULL, '2026-09-02 12:21:23', '2026-08-28 06:41:22'),
(104, 67, '16-21', NULL, NULL, NULL, '2026-08-28 11:41:22', '2026-08-28 06:41:22'),
(112, 67, '15-20', NULL, NULL, NULL, '2026-09-02 10:55:38', '2026-09-01 05:56:23'),
(113, 67, '15-21', NULL, NULL, NULL, '2026-09-02 10:55:38', '2026-09-01 05:56:23'),
(114, 67, '14-20', NULL, NULL, NULL, '2026-09-02 12:07:27', '2026-09-01 06:00:18'),
(115, 67, '14-21', NULL, NULL, NULL, '2026-09-02 12:07:27', '2026-09-01 06:00:18'),
(116, 67, '12-20', NULL, NULL, NULL, '2026-09-02 11:56:52', '2026-09-01 07:08:43'),
(117, 67, '12-21', NULL, NULL, NULL, '2026-09-02 11:56:52', '2026-09-01 07:08:43'),
(118, 67, '12-22', NULL, NULL, NULL, '2026-09-01 17:39:01', '2026-09-01 12:39:01'),
(119, 64, '5-17', NULL, NULL, NULL, '2026-09-01 17:39:58', '2026-09-01 12:39:58'),
(120, 64, '5-18', NULL, NULL, NULL, '2026-09-01 17:39:58', '2026-09-01 12:39:58'),
(121, 64, '8-1', 288, NULL, NULL, '2026-09-05 13:55:08', '2026-09-01 12:39:58'),
(122, 64, '7-10', NULL, NULL, NULL, '2026-09-01 17:52:55', '2026-09-01 12:52:55'),
(123, 67, '12-25', NULL, NULL, NULL, '2026-09-01 18:02:41', '2026-09-01 13:02:41'),
(124, 67, '12-24', NULL, NULL, NULL, '2026-09-01 18:02:41', '2026-09-01 13:02:41'),
(125, 67, '14-24', 286, NULL, NULL, '2026-09-02 16:37:44', '2026-09-01 13:11:11'),
(126, 67, '14-25', NULL, NULL, NULL, '2026-09-01 18:11:11', '2026-09-01 13:11:11'),
(127, 67, '15-24', NULL, NULL, NULL, '2026-09-01 18:13:06', '2026-09-01 13:13:06'),
(128, 67, '15-25', NULL, NULL, NULL, '2026-09-01 18:13:06', '2026-09-01 13:13:06'),
(129, 67, '15-22', NULL, NULL, NULL, '2026-09-02 11:39:21', '2026-09-02 06:39:21'),
(133, 67, '14-22', NULL, NULL, NULL, '2026-09-02 12:12:21', '2026-09-02 07:12:21'),
(134, 67, '14-23', 285, NULL, NULL, '2026-09-02 15:28:36', '2026-09-02 07:14:33'),
(135, 67, '13-20', NULL, NULL, NULL, '2026-09-02 12:19:33', '2026-09-02 07:19:33'),
(136, 67, '1-5', 287, NULL, NULL, '2026-09-02 19:39:08', '2026-09-02 14:39:08'),
(137, 64, '7-18', 289, NULL, NULL, '2026-09-05 13:55:08', '2026-09-05 08:55:08');

-- --------------------------------------------------------

--
-- Структура таблицы `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `key` varchar(150) NOT NULL,
  `label` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','text','int','float','bool','json','select','color','file','image') NOT NULL DEFAULT 'string',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_editable` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `settings`
--

INSERT INTO `settings` (`id`, `key`, `label`, `value`, `type`, `options`, `category`, `description`, `is_editable`, `sort_order`, `updated_at`) VALUES
(1, 'system.site_name', 'Название сайта', 'Алматинский театр \"Жас сахна\" им. Байтена Омарова', 'string', NULL, 'system', 'Отображается в заголовке и метаданных', 1, 1, '2026-08-13 14:39:49'),
(2, 'system.default_language', 'Язык по умолчанию', 'ru', 'select', '[\"ru\",\"kz\",\"en\"]', 'system', 'Основной язык интерфейса', 1, 2, '2026-08-13 14:39:58'),
(3, 'system.timezone', 'Часовой пояс', 'Asia/Almaty', 'string', NULL, 'system', 'Используется для расписания и логов', 1, 3, '2026-06-08 11:04:02'),
(4, 'system.maintenance_mode', 'Режим обслуживания', '0', 'bool', NULL, 'system', 'Отключает публичную часть сайта', 1, 4, '2026-06-08 11:04:02'),
(5, 'ui.theme_color', 'Основной цвет темы', '#b8b8b8', 'color', NULL, 'ui', 'Цвет кнопок и элементов интерфейса', 1, 1, '2026-09-10 12:32:20'),
(6, 'ui.logo_image', 'Логотип сайта', '/uploads/images/settings/ui_logo_image_20260812172237_10e54f06.png', 'image', NULL, 'ui', 'Логотип в шапке сайта', 1, 2, '2026-08-12 12:22:37'),
(7, 'ui.homepage_banner', 'Баннер на главной', '{\"title\":\"Добро пожаловать\",\"subtitle\":\"Официальный сайт театра\"}', 'json', NULL, 'ui', 'Контент баннера на главной странице', 1, 3, '2026-06-08 11:04:02'),
(8, 'tickets.auto_cancel_hours', 'Автоотмена неоплаченных билетов (часы)', '1', 'int', NULL, 'tickets', 'Через сколько часов отменять неоплаченные брони', 1, 1, '2026-09-09 07:12:36'),
(9, 'tickets.allow_refunds', 'Разрешить возвраты', '1', 'bool', NULL, 'tickets', 'Включает или отключает возвраты билетов', 1, 2, '2026-06-08 11:04:02'),
(10, 'tickets.max_tickets_per_user', 'Максимум билетов на пользователя', '5', 'int', NULL, 'tickets', 'Ограничение на покупку', 1, 3, '2026-09-08 10:32:28'),
(11, 'tickets.ticket_prefix', 'Префикс номера билета', '', 'string', NULL, 'tickets', 'Добавляется к номеру билета', 1, 4, '2026-08-08 10:53:36'),
(16, 'notifications.email_from', 'Email отправителя', 'noreply@theater.kz', 'string', NULL, 'notifications', 'Отправитель писем', 1, 1, '2026-06-08 11:04:02'),
(17, 'notifications.sms_provider', 'SMS провайдер', 'kazinfoteh', 'select', '[\"kazinfoteh\",\"turbosms\",\"twilio\"]', 'notifications', 'Выбор SMS сервиса', 1, 2, '2026-06-08 11:04:02'),
(18, 'notifications.telegram_bot_token', 'Telegram Bot Token', NULL, 'string', NULL, 'notifications', 'Токен Telegram бота', 0, 3, '2026-06-08 11:04:02'),
(19, 'security.password_min_length', 'Минимальная длина пароля', '8', 'int', NULL, 'security', 'Минимальное число символов для новых паролей пользователей.', 1, 40, '2026-09-10 10:55:16'),
(20, 'security.session_lifetime', 'Время жизни сессии (минуты)', '120', 'int', NULL, 'security', 'Через сколько минут истекает сессия', 1, 2, '2026-06-08 11:04:02'),
(21, 'security.two_factor_required', 'Обязательная 2FA', '0', 'bool', NULL, 'security', 'Требовать двухфакторную авторизацию', 1, 3, '2026-06-08 11:04:02'),
(22, 'tickets.discount_adult_percent', 'Скидка для взрослого билета (%)', '0', 'float', NULL, 'discounts', 'Процент скидки для типа билета \"Взрослый\"', 1, 10, '2026-08-08 11:40:36'),
(23, 'tickets.discount_child_percent', 'Скидка для детского билета (%)', '50', 'float', NULL, 'discounts', 'Процент скидки для типа билета \"Детский\"', 1, 11, '2026-08-11 02:44:51'),
(24, 'tickets.discount_student_percent', 'Скидка для студенческого билета (%)', '20', 'float', NULL, 'discounts', 'Процент скидки для типа билета \"Студенческий\"', 1, 12, '2026-08-11 02:44:51'),
(25, 'tickets.discount_senior_percent', 'Скидка для пенсионного билета (%)', '40', 'float', NULL, 'discounts', 'Процент скидки для типа билета \"Пенсионный\"', 1, 13, '2026-08-11 05:02:10'),
(26, 'tickets.custom_discount_enabled', 'Разрешить ручную скидку в кассе', '1', 'bool', NULL, 'discounts', 'Кассир сможет вводить дополнительную скидку при продаже', 1, 20, '2026-09-02 11:36:43'),
(27, 'tickets.custom_discount_max_percent', 'Максимальная ручная скидка (%)', '100', 'float', NULL, 'discounts', 'Ограничение для скидки кассира в процентах', 1, 21, '2026-09-09 07:16:20'),
(28, 'tickets.custom_discount_max_amount', 'Максимальная ручная скидка (тг)', '0', 'float', NULL, 'discounts', '0 = без ограничения по сумме', 1, 22, '2026-08-08 11:40:36'),
(29, 'security.role_permissions', 'Матрица прав доступа', '{\"admin\":{\"dashboard\":true,\"cash\":true,\"schedule\":true,\"events\":true,\"tickets\":true,\"actors\":true,\"halls\":true,\"reports\":true,\"settings_general\":true,\"settings_interface\":true,\"settings_tickets\":true,\"settings_discounts\":true,\"settings_payment\":true,\"settings_notifications\":true,\"settings_security\":true,\"settings_users\":true,\"settings_permissions\":true},\"manager\":{\"dashboard\":true,\"cash\":true,\"schedule\":true,\"events\":true,\"tickets\":true,\"actors\":true,\"halls\":true,\"reports\":true,\"settings_general\":true,\"settings_interface\":true,\"settings_tickets\":true,\"settings_discounts\":true,\"settings_payment\":false,\"settings_notifications\":true,\"settings_security\":false,\"settings_users\":false,\"settings_permissions\":false},\"cashier\":{\"dashboard\":true,\"cash\":true,\"schedule\":true,\"events\":true,\"tickets\":true,\"actors\":false,\"halls\":false,\"reports\":true,\"settings_general\":false,\"settings_interface\":false,\"settings_tickets\":false,\"settings_discounts\":true,\"settings_payment\":false,\"settings_notifications\":false,\"settings_security\":false,\"settings_users\":false,\"settings_permissions\":false}}', 'json', NULL, 'security', 'Управляет доступом ролей к разделам интерфейса и меню', 1, 40, '2026-08-12 06:52:51'),
(30, 'payments.bcc_enabled', 'BCC эквайринг включен', '1', 'bool', NULL, 'payments', 'Включить онлайн-оплату через BCC', 1, 10, '2026-09-05 09:39:39'),
(31, 'payments.bcc_mode', 'Режим BCC', 'test', 'select', '[\"test\",\"production\"]', 'payments', 'test или production', 1, 11, '2026-09-05 09:39:23'),
(32, 'payments.bcc_merchant', 'BCC MERCHANT', '00000001', 'string', NULL, 'payments', 'Идентификатор интернет-торговца', 1, 12, '2026-09-05 09:41:20'),
(33, 'payments.bcc_terminal', 'BCC TERMINAL', '88888881', 'string', NULL, 'payments', 'ID терминала', 1, 13, '2026-09-05 09:39:48'),
(34, 'payments.bcc_merch_name', 'BCC MERCH_NAME', 'ZHAS SAHNA THEATER', 'string', NULL, 'payments', 'Имя торговца латиницей UPPERCASE', 1, 14, '2026-09-05 09:39:48'),
(35, 'payments.bcc_mac_key', 'BCC MAC ключ', '6BB0AC02E47BDF73D98FEB777F3B5294', 'string', NULL, 'payments', 'Секретный ключ для подписи P_SIGN', 1, 15, '2026-09-05 09:39:48'),
(36, 'payments.bcc_test_url', 'BCC тестовый URL', 'https://test3ds.bcc.kz:5445/cgi-bin/cgi_link', 'string', NULL, 'payments', 'URL тестового хоста', 1, 16, '2026-09-05 09:39:48'),
(37, 'payments.bcc_prod_url', 'BCC боевой URL', 'https://3dsecure.bcc.kz/webview', 'string', NULL, 'payments', 'URL боевого хоста', 1, 17, '2026-09-08 08:26:35'),
(38, 'payments.bcc_backref_path', 'BCC BACKREF путь', '/payment/bcc/return.php', 'string', NULL, 'payments', 'Относительный путь для возврата клиента', 1, 18, '2026-09-08 08:26:35'),
(39, 'payments.bcc_notify_path', 'BCC NOTIFY путь', '/payment/bcc/notify.php', 'string', NULL, 'payments', 'Относительный путь для callback-уведомлений', 1, 19, '2026-09-08 08:26:35'),
(40, 'payments.bcc_notify_login', 'BCC NOTIFY логин', '', 'string', NULL, 'payments', 'Логин Basic Auth для NOTIFY_URL', 1, 20, '2026-09-08 08:21:47'),
(41, 'payments.bcc_notify_password', 'BCC NOTIFY пароль', '', 'string', NULL, 'payments', 'Пароль Basic Auth для NOTIFY_URL', 1, 21, '2026-09-08 08:21:47'),
(43, 'payments.bcc_backref_url', 'BCC BACKREF публичный URL', 'https://cabinet.zhassahna.kz', 'string', NULL, 'payments', 'Публичный домен для возврата клиента после оплаты. Путь задаётся отдельно; URL должен проксировать BACKREF на этот кабинет.', 1, 18, '2026-09-08 13:11:06'),
(44, 'payments.bcc_notify_url', 'BCC NOTIFY серверный URL', 'https://cabinet.zhassahna.kz', 'string', NULL, 'payments', 'Серверный домен для callback от BCC. Не указывайте здесь статический сайт Tilda.', 1, 20, '2026-09-08 08:26:35'),
(45, 'payments.bcc_self_refund_enabled', 'Разрешить самостоятельный возврат BCC', '1', 'bool', NULL, 'payments', 'Клиент сможет самостоятельно отменить оплаченный заказ через публичную страницу заказа.', 1, 22, '2026-09-09 05:47:55'),
(46, 'payments.bcc_self_refund_window_minutes', 'Окно самостоятельного возврата (минуты)', '30', 'int', NULL, 'payments', 'Рекомендуемое значение: 30 минут после покупки.', 1, 23, '2026-09-08 13:10:50'),
(47, 'payments.bcc_self_refund_min_hours_before_event', 'Минимум до спектакля для возврата (часы)', '12', 'int', NULL, 'payments', 'Возврат запрещается ближе указанного времени до начала спектакля.', 1, 24, '2026-09-09 05:48:23'),
(48, 'notifications.bcc_notify_url', 'BCC NOTIFY URL', 'https://cabinet.zhassahna.kz', 'string', NULL, 'notifications', 'URL для уведомлений BCC. Должен быть доступен извне и использовать HTTPS.', 1, 10, '2026-09-09 07:10:44'),
(49, 'notifications.bcc_notify_port', 'BCC NOTIFY порт', '443', 'int', NULL, 'notifications', 'BCC требует указывать порт в URL уведомлений. Обычно используется 443.', 1, 11, '2026-09-09 07:10:44'),
(50, 'notifications.bcc_notify_method', 'BCC NOTIFY метод', 'POST', 'select', '[\"POST\",\"GET\"]', 'notifications', 'Метод отправки уведомления от BCC по документации.', 1, 12, '2026-09-09 07:10:44'),
(51, 'notifications.bcc_basic_auth_enabled', 'BCC NOTIFY Basic Auth', '1', 'bool', NULL, 'notifications', 'Включить Basic Authentication для входящих уведомлений BCC.', 1, 13, '2026-09-09 07:11:26'),
(52, 'notifications.bcc_notify_login', 'BCC NOTIFY логин', '', 'string', NULL, 'notifications', 'Логин Basic Auth, который нужно передать в BCC.', 1, 14, '2026-09-09 07:10:44'),
(53, 'notifications.bcc_notify_password', 'BCC NOTIFY пароль', '', 'string', NULL, 'notifications', 'Пароль Basic Auth, который нужно передать в BCC.', 1, 15, '2026-09-09 07:10:44'),
(54, 'notifications.bcc_tls12', 'BCC NOTIFY TLS 1.2', '1', 'bool', NULL, 'notifications', 'Подтверждение поддержки TLS 1.2 сервером уведомлений.', 1, 16, '2026-09-09 07:10:44'),
(55, 'notifications.bcc_virtual_host', 'BCC NOTIFY виртуальный хост', '1', 'bool', NULL, 'notifications', 'Укажите, размещён ли endpoint уведомлений на виртуальном хосте.', 1, 17, '2026-09-09 07:10:45'),
(56, 'tickets.client_reservation_enabled', 'Разрешить резервирование клиентом', '1', 'bool', NULL, 'tickets', 'Клиент сможет временно забронировать места в публичном виджете.', 1, 5, '2026-09-09 07:31:51'),
(57, 'tickets.client_reservation_minutes', 'Время резерва клиентом (минуты)', '30', 'int', NULL, 'tickets', 'На сколько минут место блокируется в виджете. Рекомендуемое значение: 15 минут.', 1, 6, '2026-09-09 07:31:51'),
(58, 'security.session_idle_minutes', 'Таймаут неактивной сессии (минуты)', '480', 'int', NULL, 'security', 'Через сколько минут бездействия потребуется повторный вход. Пример: 480 = 8 часов.', 1, 10, '2026-09-10 10:55:16'),
(59, 'security.max_login_attempts', 'Максимум неудачных входов', '5', 'int', NULL, 'security', 'После этого числа попыток вход временно блокируется.', 1, 20, '2026-09-10 10:55:16'),
(60, 'security.login_lockout_minutes', 'Время блокировки входа (минуты)', '15', 'int', NULL, 'security', 'Пауза после превышения лимита попыток. Пример: 15 минут.', 1, 30, '2026-09-10 10:55:16'),
(61, 'security.password_require_uppercase', 'Требовать заглавную букву в пароле', '0', 'bool', NULL, 'security', 'Например, пароль должен содержать A–Z.', 1, 50, '2026-09-10 10:55:16'),
(62, 'security.password_require_lowercase', 'Требовать строчную букву в пароле', '0', 'bool', NULL, 'security', 'Например, пароль должен содержать a–z.', 1, 60, '2026-09-10 10:55:16'),
(63, 'security.password_require_number', 'Требовать цифру в пароле', '1', 'bool', NULL, 'security', 'Например, пароль должен содержать 0–9.', 1, 70, '2026-09-10 10:55:16'),
(64, 'security.password_require_special', 'Требовать специальный символ', '0', 'bool', NULL, 'security', 'Например: !, @, #, $, %. ', 1, 80, '2026-09-10 10:55:16'),
(65, 'system.maintenance_enabled', 'Режим обслуживания', '0', 'bool', NULL, 'system', 'Если включено, публичная афиша и форма покупки покажут вежливое сообщение о временной паузе.', 1, 10, '2026-09-10 10:56:01'),
(66, 'system.maintenance_title', 'Заголовок режима обслуживания', 'Мы скоро вернёмся', 'string', NULL, 'system', 'Короткий заголовок сообщения для страницы Tilda.', 1, 11, '2026-09-10 10:56:01'),
(67, 'system.maintenance_message', 'Сообщение режима обслуживания', 'Мы обновляем афишу и платёжную часть. Спасибо за терпение — скоро всё снова заработает.', 'text', NULL, 'system', 'Текст показывается посетителям вместо афиши. Пример: «Приносим извинения за паузу и скоро вернёмся».', 1, 12, '2026-09-10 10:56:01');

-- --------------------------------------------------------

--
-- Структура таблицы `staff`
--

CREATE TABLE `staff` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_uid` varchar(100) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `role` enum('admin','manager','cashier','scanner') NOT NULL DEFAULT 'manager',
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `tickets`
--

CREATE TABLE `tickets` (
  `id` int(10) UNSIGNED NOT NULL,
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `hall_id` int(10) UNSIGNED NOT NULL,
  `seat_id` int(10) UNSIGNED DEFAULT NULL,
  `seat_identifier` varchar(50) NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_segment` enum('adult','child','senior','student') NOT NULL DEFAULT 'adult',
  `channel` enum('web','mobile','kassa','agent','qr','admin') NOT NULL DEFAULT 'web',
  `source_ref` varchar(100) DEFAULT NULL,
  `ticket_uid` varchar(100) NOT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` int(11) NOT NULL DEFAULT 0,
  `status` enum('issued','cancelled','used') NOT NULL DEFAULT 'issued',
  `payment_status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `payment_provider` varchar(100) DEFAULT NULL,
  `payment_transaction_id` varchar(255) DEFAULT NULL,
  `payment_session_id` int(10) UNSIGNED DEFAULT NULL,
  `is_checked_in` tinyint(1) NOT NULL DEFAULT 0,
  `checked_in_at` datetime DEFAULT NULL,
  `refund_status` enum('none','requested','approved','refunded') NOT NULL DEFAULT 'none',
  `refund_at` datetime DEFAULT NULL,
  `purchased_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sold_by_staff_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tickets`
--

INSERT INTO `tickets` (`id`, `schedule_id`, `event_id`, `hall_id`, `seat_id`, `seat_identifier`, `customer_id`, `customer_name`, `customer_phone`, `customer_email`, `customer_segment`, `channel`, `source_ref`, `ticket_uid`, `barcode`, `qr_code`, `price`, `discount`, `status`, `payment_status`, `payment_provider`, `payment_transaction_id`, `payment_session_id`, `is_checked_in`, `checked_in_at`, `refund_status`, `refund_at`, `purchased_at`, `created_at`, `updated_at`, `sold_by_staff_id`) VALUES
(283, 67, 24, 8, NULL, '16-10', 21, NULL, NULL, NULL, 'senior', 'kassa', NULL, '0d8bebd1c7dba490', NULL, NULL, 10000.00, 40, 'issued', 'paid', NULL, '210', NULL, 0, NULL, 'none', NULL, '2026-09-02 12:21:23', '2026-09-02 07:21:23', '2026-09-02 07:21:23', NULL),
(284, 67, 24, 8, NULL, '16-20', 21, NULL, NULL, NULL, 'senior', 'kassa', NULL, 'f61f5280b6e668e4', NULL, NULL, 5000.00, 40, 'issued', 'paid', NULL, '210', NULL, 0, NULL, 'none', NULL, '2026-09-02 12:21:23', '2026-09-02 07:21:23', '2026-09-02 07:21:23', NULL),
(285, 67, 24, 8, NULL, '14-23', 21, NULL, NULL, NULL, 'child', 'kassa', NULL, '7064c679a066f3b4', NULL, NULL, 2500.00, 50, 'issued', 'paid', NULL, '211', NULL, 0, NULL, 'none', NULL, '2026-09-02 15:28:36', '2026-09-02 10:28:36', '2026-09-02 10:28:36', NULL),
(286, 67, 24, 8, NULL, '14-24', 21, NULL, NULL, NULL, 'adult', 'kassa', NULL, 'fa62acd86e709c42', NULL, NULL, 2000.00, 0, 'issued', 'paid', NULL, '212', NULL, 0, NULL, 'none', NULL, '2026-09-02 16:37:44', '2026-09-02 11:37:44', '2026-09-02 11:37:44', NULL),
(287, 67, 24, 8, NULL, '1-5', 21, NULL, NULL, NULL, 'child', 'kassa', NULL, '5e1f4cf9a0c51b2e', NULL, NULL, 10000.00, 50, 'issued', 'paid', NULL, '213', NULL, 0, NULL, 'none', NULL, '2026-09-02 19:39:08', '2026-09-02 14:39:08', '2026-09-02 14:39:08', NULL),
(288, 64, 26, 8, NULL, '8-1', 21, 'Теплов Федор Евгеньеич', '77772979723', 'ch011tfe@mail.ru', 'student', 'kassa', NULL, '63e4bb78714eabfb', NULL, NULL, 8000.00, 20, 'issued', 'paid', NULL, '214', NULL, 0, NULL, 'none', NULL, '2026-09-05 13:55:08', '2026-09-05 08:55:08', '2026-09-05 08:55:08', NULL),
(289, 64, 26, 8, NULL, '7-18', 21, 'Теплов Федор Евгеньеич', '77772979723', 'ch011tfe@mail.ru', 'student', 'kassa', NULL, '30697ebca5358f22', NULL, NULL, 4000.00, 20, 'issued', 'paid', NULL, '214', NULL, 0, NULL, 'none', NULL, '2026-09-05 13:55:08', '2026-09-05 08:55:08', '2026-09-05 08:55:08', NULL),
(290, 64, 26, 8, NULL, '3-17', 21, 'Теплов Федор Евгеньеич', '77772979723', 'ch011tfe@mail.ru', 'student', 'kassa', NULL, 'aa1ec26e7b928be2', NULL, NULL, 4000.00, 20, 'cancelled', 'paid', NULL, '214', NULL, 0, NULL, 'refunded', '2026-09-09 16:45:20', '2026-09-05 13:55:08', '2026-09-05 08:55:08', '2026-09-09 11:45:20', NULL),
(291, 67, 24, 8, NULL, '12-25', 21, NULL, '77772979723', NULL, 'adult', 'web', NULL, '847bceec0687cbde', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '215', 64, 0, NULL, 'none', NULL, '2026-09-08 13:37:28', '2026-09-08 08:37:28', '2026-09-08 08:37:28', NULL),
(292, 67, 24, 8, NULL, '16-21', 22, NULL, '77771111177', NULL, 'adult', 'web', NULL, '3d8f3345d17fcdf9', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '216', 73, 0, NULL, 'none', NULL, '2026-09-08 18:11:58', '2026-09-08 13:11:58', '2026-09-08 13:11:58', NULL),
(293, 67, 24, 8, NULL, '16-22', 22, NULL, '77771111177', NULL, 'adult', 'web', NULL, '0560af5a20536536', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '216', 73, 0, NULL, 'none', NULL, '2026-09-08 18:11:58', '2026-09-08 13:11:58', '2026-09-08 13:11:58', NULL),
(294, 64, 26, 8, NULL, '6-21', 23, 'Коля', '77777777777', NULL, 'adult', 'web', NULL, 'e6bab31940e1fd34', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '217', 74, 0, NULL, 'none', NULL, '2026-09-08 18:21:55', '2026-09-08 13:21:55', '2026-09-08 13:21:55', NULL),
(295, 64, 26, 8, NULL, '5-20', 23, 'Коля', '77777777777', NULL, 'adult', 'web', NULL, '5e1d55f414597e1a', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '217', 74, 0, NULL, 'none', NULL, '2026-09-08 18:21:55', '2026-09-08 13:21:55', '2026-09-08 13:21:55', NULL),
(296, 64, 26, 8, NULL, '7-17', 24, 'Дядя Вас', '77773000000', 'pochta@pochta.com', 'adult', 'web', NULL, '8639fc719be0f99c', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '218', 75, 0, NULL, 'none', NULL, '2026-09-09 10:34:07', '2026-09-09 05:34:07', '2026-09-09 05:34:07', NULL),
(297, 67, 24, 8, NULL, '12-24', 25, 'Васёк', '77001001010', NULL, 'adult', 'web', NULL, '48120f169cd7f7cf', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '219', 76, 0, NULL, 'none', NULL, '2026-09-09 10:49:38', '2026-09-09 05:49:38', '2026-09-09 05:49:38', NULL),
(298, 67, 24, 8, NULL, '12-23', 26, 'Дядя Лёша', '+77774000000', 'pochta@pochta.kz', 'adult', 'web', NULL, 'ba5d80113735b2e0', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '220', 81, 0, NULL, 'none', NULL, '2026-09-10 14:13:23', '2026-09-10 09:13:23', '2026-09-10 09:13:23', NULL),
(299, 67, 24, 8, NULL, '15-21', 27, 'Тимур и его команда', '+75555555555', NULL, 'adult', 'web', NULL, '033a74a421a6f50e', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '221', 82, 0, NULL, 'none', NULL, '2026-09-10 19:54:07', '2026-09-10 14:54:07', '2026-09-10 14:54:07', NULL),
(300, 67, 24, 8, NULL, '15-22', 27, 'Тимур и его команда', '+75555555555', NULL, 'adult', 'web', NULL, '230841b9be13361f', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '221', 82, 0, NULL, 'none', NULL, '2026-09-10 19:54:07', '2026-09-10 14:54:07', '2026-09-10 14:54:07', NULL),
(301, 64, 26, 8, NULL, '3-20', 28, 'Дядя', '+77776000000', 'pochta@pochta.com', 'adult', 'web', NULL, '8c64c404142b8272', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '222', 83, 0, NULL, 'none', NULL, '2026-09-10 20:17:49', '2026-09-10 15:17:49', '2026-09-10 15:17:49', NULL),
(302, 64, 26, 8, NULL, '6-23', 29, 'Дядя Кекс', '+77777000000', 'pochta@pochta.com', 'adult', 'web', NULL, 'aebd92d5295ae407', NULL, NULL, 5000.00, 0, 'issued', 'paid', 'bcc', '223', 84, 0, NULL, 'none', NULL, '2026-09-10 20:33:13', '2026-09-10 15:33:13', '2026-09-10 15:33:13', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('admin','manager','cashier') NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email` varchar(255) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `failed_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `is_active`, `email`, `last_login_at`, `password_changed_at`, `failed_attempts`, `locked_until`, `two_factor_secret`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$12$vQtdJxhf3XLgHvzVfuDhUOqzfh.cO2K4w28jrl7GFD5OIoDexgMra', 'Фёдор', 'admin', 1, 'admin@example.com', NULL, NULL, 0, NULL, NULL, '2026-05-22 03:06:55', '2026-09-10 09:10:39'),
(2, 'kassir_1', '$2y$12$XZF9PBaf9HAE0TPl3NWlxOl3op7ty8EGffyzNjfVw.ChnyjhXGzX6', 'Римма', 'cashier', 1, NULL, NULL, '2026-08-12 11:52:03', 0, NULL, NULL, '2026-08-12 06:52:03', '2026-08-12 07:12:07');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_logs_created` (`created_at`);

--
-- Индексы таблицы `cash_audit_log`
--
ALTER TABLE `cash_audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `cash_holds`
--
ALTER TABLE `cash_holds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_hold_session_seat` (`session_id`,`seat_key`),
  ADD KEY `idx_cash_holds_expires` (`expires_at`);

--
-- Индексы таблицы `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_transactions_user_created` (`user_id`,`created_at`),
  ADD KEY `fk_cash_transactions_customer` (`customer_id`);

--
-- Индексы таблицы `checkins`
--
ALTER TABLE `checkins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_checkins_scanner` (`scanner_id`);

--
-- Индексы таблицы `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customers_phone` (`phone`),
  ADD KEY `idx_customers_email` (`email`);

--
-- Индексы таблицы `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_created` (`created_at`),
  ADD KEY `fk_events_category_clean` (`category_id`),
  ADD KEY `fk_events_genre_clean` (`genre_id`),
  ADD KEY `idx_events_hall` (`hall_id`),
  ADD KEY `fk_events_language` (`language_id`);

--
-- Индексы таблицы `event_actors`
--
ALTER TABLE `event_actors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_actor_uid_eventnull` (`actor_uid`,`event_id`),
  ADD KEY `idx_event_actors_event` (`event_id`);

--
-- Индексы таблицы `event_categories`
--
ALTER TABLE `event_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `uniq_event_categories_slug` (`slug`);

--
-- Индексы таблицы `event_genres`
--
ALTER TABLE `event_genres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `uniq_event_genres_slug` (`slug`);

--
-- Индексы таблицы `event_languages`
--
ALTER TABLE `event_languages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Индексы таблицы `event_statuses`
--
ALTER TABLE `event_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_event_statuses_sort` (`sort_order`);

--
-- Индексы таблицы `halls`
--
ALTER TABLE `halls`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `payment_sessions`
--
ALTER TABLE `payment_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_order_number` (`order_number`),
  ADD KEY `idx_payment_session_schedule` (`session_id`),
  ADD KEY `idx_payment_session_status` (`status`),
  ADD KEY `idx_payment_session_created` (`created_at`);

--
-- Индексы таблицы `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_refunds_processed_by` (`processed_by`),
  ADD KEY `idx_refunds_ticket_uid` (`ticket_uid`),
  ADD KEY `idx_refunds_schedule_id` (`schedule_id`);

--
-- Индексы таблицы `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_schedules_start_time` (`start_time`),
  ADD KEY `idx_schedules_event` (`event_id`),
  ADD KEY `idx_schedules_hall` (`hall_id`);

--
-- Индексы таблицы `schedule_prices`
--
ALTER TABLE `schedule_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_schedule_prices_schedule_clean` (`schedule_id`);

--
-- Индексы таблицы `seats`
--
ALTER TABLE `seats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_seat_position` (`hall_id`,`row_number`,`seat_number`),
  ADD KEY `idx_hall_id` (`hall_id`),
  ADD KEY `idx_seats_row_seat` (`row_number`,`seat_number`);

--
-- Индексы таблицы `seat_occupancy`
--
ALTER TABLE `seat_occupancy`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_schedule_seat` (`schedule_id`,`seat_identifier`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_seat_occupancy_reserved_until` (`reserved_until`),
  ADD KEY `fk_seat_occupancy_reserved_by` (`reserved_by`);

--
-- Индексы таблицы `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_settings_key` (`key`),
  ADD KEY `idx_settings_category` (`category`);

--
-- Индексы таблицы `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_email` (`email`);

--
-- Индексы таблицы `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_uid` (`ticket_uid`),
  ADD KEY `idx_tickets_schedule` (`schedule_id`),
  ADD KEY `idx_tickets_event` (`event_id`),
  ADD KEY `idx_tickets_hall` (`hall_id`),
  ADD KEY `idx_tickets_seat` (`seat_id`),
  ADD KEY `idx_tickets_customer` (`customer_id`),
  ADD KEY `idx_tickets_sold_by` (`sold_by_staff_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uniq_users_email` (`email`),
  ADD KEY `idx_users_email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `cash_audit_log`
--
ALTER TABLE `cash_audit_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=282;

--
-- AUTO_INCREMENT для таблицы `cash_holds`
--
ALTER TABLE `cash_holds`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=198;

--
-- AUTO_INCREMENT для таблицы `cash_transactions`
--
ALTER TABLE `cash_transactions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=224;

--
-- AUTO_INCREMENT для таблицы `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT для таблицы `events`
--
ALTER TABLE `events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT для таблицы `event_actors`
--
ALTER TABLE `event_actors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT для таблицы `event_categories`
--
ALTER TABLE `event_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `event_genres`
--
ALTER TABLE `event_genres`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `event_languages`
--
ALTER TABLE `event_languages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `event_statuses`
--
ALTER TABLE `event_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `halls`
--
ALTER TABLE `halls`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `payment_sessions`
--
ALTER TABLE `payment_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT для таблицы `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT для таблицы `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT для таблицы `schedule_prices`
--
ALTER TABLE `schedule_prices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `seats`
--
ALTER TABLE `seats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `seat_occupancy`
--
ALTER TABLE `seat_occupancy`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT для таблицы `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT для таблицы `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=303;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD CONSTRAINT `fk_cash_transactions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `checkins`
--
ALTER TABLE `checkins`
  ADD CONSTRAINT `fk_checkins_scanner` FOREIGN KEY (`scanner_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_checkins_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_category_clean` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_events_genre_clean` FOREIGN KEY (`genre_id`) REFERENCES `event_genres` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_events_hall` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_events_language` FOREIGN KEY (`language_id`) REFERENCES `event_languages` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `event_actors`
--
ALTER TABLE `event_actors`
  ADD CONSTRAINT `fk_event_actors_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `fk_refunds_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_refunds_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedules_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_schedules_hall` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `schedule_prices`
--
ALTER TABLE `schedule_prices`
  ADD CONSTRAINT `fk_schedule_prices_schedule_clean` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `seats`
--
ALTER TABLE `seats`
  ADD CONSTRAINT `fk_seats_hall` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `seat_occupancy`
--
ALTER TABLE `seat_occupancy`
  ADD CONSTRAINT `fk_seat_occupancy_reserved_by` FOREIGN KEY (`reserved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_seat_occupancy_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_hall` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_seat` FOREIGN KEY (`seat_id`) REFERENCES `seats` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tickets_sold_by` FOREIGN KEY (`sold_by_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
