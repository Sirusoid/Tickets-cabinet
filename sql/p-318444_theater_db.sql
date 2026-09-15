-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Сен 15 2026 г., 17:35
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

--
-- Дамп данных таблицы `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `performed_by_type`, `action`, `entity_type`, `entity_name`, `entity_id`, `before_data`, `after_data`, `ip_address`, `user_agent`, `created_at`) VALUES
(52, 1, 'admin', 'sale:create', 'session', NULL, 67, NULL, '{\"seats\":[\"16-10\",\"16-20\"],\"tx_id\":\"253\",\"amount\":15000,\"uids\":[\"48f7b115e69a2872\",\"01bc5aeb27d7239f\"],\"customer_id\":37,\"discount\":{\"final_total\":15000,\"applied\":{\"segment\":\"adult\",\"auto_percent\":0,\"auto_amount\":0,\"custom_type\":\"none\",\"custom_value\":0,\"custom_amount\":0,\"total_discount\":0}}}', '91.198.101.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 YaBrowser/26.8.0.0 Safari/537.36', '2026-09-15 12:26:35'),
(53, 1, 'admin', 'sale:create', 'session', NULL, 67, NULL, '{\"seats\":[\"16-22\",\"16-23\"],\"tx_id\":\"254\",\"amount\":8000,\"uids\":[\"e7550c7cee5e4222\",\"5929db1d69f61c40\"],\"customer_id\":37,\"discount\":{\"final_total\":8000,\"applied\":{\"segment\":\"student\",\"auto_percent\":0,\"auto_amount\":0,\"custom_type\":\"none\",\"custom_value\":0,\"custom_amount\":0,\"total_discount\":0}}}', '91.198.101.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 YaBrowser/26.8.0.0 Safari/537.36', '2026-09-15 12:26:49');

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
(253, 'sale', 67, 1, 37, 15000, 'KZT', 'cash', '{\"schema_version\":2,\"action\":\"sale\",\"source\":\"cashier\",\"payment_method\":\"cash\",\"customer_segment\":\"adult\"}', '[\"48f7b115e69a2872\",\"01bc5aeb27d7239f\"]', '2026-09-15 17:26:34'),
(254, 'sale', 67, 1, 37, 8000, 'KZT', 'cash', '{\"schema_version\":2,\"action\":\"sale\",\"source\":\"cashier\",\"payment_method\":\"cash\",\"customer_segment\":\"student\"}', '[\"e7550c7cee5e4222\",\"5929db1d69f61c40\"]', '2026-09-15 17:26:48');

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
(29, '4d2086208e882059', 'Дядя Кекс', NULL, NULL, 'pochta@pochta.com', '+77777000000', NULL, 0, NULL, NULL, NULL, '2026-09-10 15:33:13', '2026-09-10 15:33:13'),
(30, 'cfb32fb1c2c76f84', 'Ляля', NULL, NULL, NULL, '+70001111111', NULL, 0, NULL, NULL, NULL, '2026-09-10 18:15:10', '2026-09-10 18:15:10'),
(31, 'a0d404bef588f7ee', 'Ванька', NULL, NULL, NULL, '+73333333333', NULL, 0, NULL, NULL, NULL, '2026-09-10 18:19:36', '2026-09-10 18:19:36'),
(32, '89171961a02b81e0', 'Гость', NULL, NULL, NULL, '+77772979722', NULL, 0, NULL, NULL, NULL, '2026-09-11 13:37:13', '2026-09-11 13:37:13'),
(33, '97f1a1185ad1c614', 'Гость', NULL, NULL, NULL, '+77773000003', NULL, 0, NULL, NULL, NULL, '2026-09-11 14:08:23', '2026-09-11 14:08:23'),
(34, '1c4ec501e9760c98', 'Гость', NULL, NULL, NULL, '+77773000005', NULL, 0, NULL, NULL, NULL, '2026-09-11 14:24:11', '2026-09-11 14:24:11'),
(35, '40ae215724047e55', 'Гость', NULL, NULL, NULL, '+77773000010', NULL, 0, NULL, NULL, NULL, '2026-09-12 06:28:54', '2026-09-12 06:28:54'),
(36, 'b1a5b4479101efbc', 'Жаннат Омарова', NULL, NULL, 'zhannat14@gmail.com', '+77017111807', NULL, 0, NULL, NULL, NULL, '2026-09-12 07:47:16', '2026-09-12 07:47:16'),
(37, 'da2918f61b76c732', 'Гость', NULL, '', '', '+77773000000', '', 0, NULL, NULL, NULL, '2026-09-15 11:32:16', '2026-09-15 11:32:16');

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
  `order_email_sent_at` datetime DEFAULT NULL,
  `seats_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`seats_payload`)),
  `ticket_uids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ticket_uids`)),
  `cash_transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notify_received_at` datetime DEFAULT NULL,
  `return_visited_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(67, 24, 8, '2026-09-26 13:31:00', '2026-09-26 14:31:00', '2026-08-27 13:31:00', '2026-08-27 14:34:00', 2000.00, NULL, NULL, 'upcoming', 0, '{\"meta\":{\"baseSeatSize\":22,\"gapX\":8,\"gapY\":12},\"anchors\":{\"layout_origin\":{\"x\":0,\"y\":0,\"_px\":{\"x\":80,\"y\":40}}},\"rows\":[{\"number\":1,\"segments\":[{\"id\":\"seg_505007\",\"start\":1,\"end\":16,\"anchor\":\"layout_origin\",\"offset\":{\"x\":11.5,\"y\":16.235299999999999}}]},{\"number\":2,\"segments\":[{\"id\":\"seg_528935\",\"start\":1,\"end\":17,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.9,\"y\":15.3529}}]},{\"number\":3,\"segments\":[{\"id\":\"seg_539893\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":14.470599999999999}},{\"id\":\"seg_585741\",\"start\":3,\"end\":20,\"anchor\":\"layout_origin\",\"offset\":{\"x\":10.4,\"y\":14.470599999999999}},{\"id\":\"seg_601124\",\"start\":21,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":14.470599999999999}}]},{\"number\":4,\"segments\":[{\"id\":\"seg_634636\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":13.588200000000001}},{\"id\":\"seg_642964\",\"start\":3,\"end\":21,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.9000000000000004,\"y\":13.588200000000001}},{\"id\":\"seg_658156\",\"start\":22,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":13.588200000000001}}]},{\"number\":5,\"segments\":[{\"id\":\"seg_678812\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":12.7059}},{\"id\":\"seg_908012\",\"start\":3,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":9.4000000000000004,\"y\":12.7058}},{\"id\":\"seg_935252\",\"start\":23,\"end\":24,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":12.7059}}]},{\"number\":6,\"segments\":[{\"id\":\"seg_005300\",\"start\":1,\"end\":2,\"anchor\":\"layout_origin\",\"offset\":{\"x\":3.5,\"y\":11.823499999999999}},{\"id\":\"seg_040628\",\"start\":3,\"end\":23,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.9000000000000004,\"y\":11.823499999999999}},{\"id\":\"seg_067980\",\"start\":24,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":32.600000000000001,\"y\":11.823499999999999}}]},{\"number\":7,\"segments\":[{\"id\":\"seg_107228\",\"start\":1,\"end\":22,\"anchor\":\"layout_origin\",\"offset\":{\"x\":8.3000000000000007,\"y\":10.941000000000001}}]},{\"number\":8,\"segments\":[{\"id\":\"seg_169980\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":8.7353000000000005}}]},{\"number\":9,\"segments\":[{\"id\":\"seg_188773\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":7.8529}}]},{\"number\":10,\"segments\":[{\"id\":\"seg_196604\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.9706000000000001}}]},{\"number\":11,\"segments\":[{\"id\":\"seg_204268\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":6.0883000000000003}}]},{\"number\":12,\"segments\":[{\"id\":\"seg_215092\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":5.2058999999999997}}]},{\"number\":13,\"segments\":[{\"id\":\"seg_222460\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":4.3235000000000001}}]},{\"number\":14,\"segments\":[{\"id\":\"seg_228892\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":3.4411999999999998}}]},{\"number\":15,\"segments\":[{\"id\":\"seg_231084\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":2.5589}}]},{\"number\":16,\"segments\":[{\"id\":\"seg_322420\",\"start\":1,\"end\":25,\"anchor\":\"layout_origin\",\"offset\":{\"x\":6.5999999999999996,\"y\":1.6765000000000001}}]}],\"elements\":[{\"id\":\"el_485535\",\"type\":\"rect\",\"name\":\"Сцена\",\"text\":\"Сцена\",\"x\":246,\"y\":642,\"width\":810,\"height\":54,\"fill\":\"#ffffff\",\"stroke\":\"#2f9b6f\",\"textColor\":\"#0b3b4a\"}],\"seats\":{\"12-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"12-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"13-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"14-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"15-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-20\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-21\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-22\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-23\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-24\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-25\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"16-10\":{\"meta\":{\"price\":\"10000\",\"color\":\"#e887f7\"}},\"1-5\":{\"meta\":{\"price\":\"20000\",\"color\":\"#9efff4\"}},\"8-12\":{\"meta\":{\"price\":\"20000\",\"color\":\"#9efff4\"}},\"10-5\":{\"meta\":{\"price\":\"20000\",\"color\":\"#9efff4\"}},\"7-13\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"7-14\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"6-18\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}},\"5-15\":{\"meta\":{\"price\":\"5000\",\"color\":\"#ff9494\"}}}}', '[{\"id\":\"g1787733126986642\",\"price\":\"5000\",\"color\":\"#ff9494\"},{\"id\":\"g1787823300040214\",\"price\":\"10000\",\"color\":\"#e887f7\"},{\"id\":\"g1788359902596104\",\"price\":\"20000\",\"color\":\"#9efff4\"}]', 'Продублированный', '2026-08-27 07:03:29', '2026-09-12 07:52:36');

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
(144, 67, '16-10', 332, NULL, NULL, '2026-09-15 17:26:34', '2026-09-15 12:26:34'),
(145, 67, '16-20', 333, NULL, NULL, '2026-09-15 17:26:34', '2026-09-15 12:26:34'),
(146, 67, '16-22', 334, NULL, NULL, '2026-09-15 17:26:48', '2026-09-15 12:26:48'),
(147, 67, '16-23', 335, NULL, NULL, '2026-09-15 17:26:48', '2026-09-15 12:26:48');

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
(51, 'notifications.bcc_basic_auth_enabled', 'BCC NOTIFY Basic Auth', '0', 'bool', NULL, 'notifications', 'Включить Basic Authentication для входящих уведомлений BCC.', 1, 13, '2026-09-12 06:22:34'),
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
(67, 'system.maintenance_message', 'Сообщение режима обслуживания', 'Мы обновляем афишу и платёжную часть. Спасибо за терпение — скоро всё снова заработает.', 'text', NULL, 'system', 'Текст показывается посетителям вместо афиши. Пример: «Приносим извинения за паузу и скоро вернёмся».', 1, 12, '2026-09-10 10:56:01'),
(68, 'notifications.order_email_enabled', 'Отправлять письма с билетами', '1', 'bool', NULL, 'notifications', 'После успешной онлайн-покупки клиент получает номер заказа и ссылку на билеты.', 1, 1, '2026-09-15 10:36:21'),
(69, 'notifications.email_from_name', 'Имя отправителя', 'Алматинский театр \"Жас сахна\" им. Б.Омарова', 'string', NULL, 'notifications', 'Название, которое увидит клиент в почтовом ящике.', 1, 3, '2026-09-15 10:36:21'),
(70, 'notifications.email_reply_to', 'Адрес для ответа', '', 'string', NULL, 'notifications', 'Ответы клиентов будут направляться на этот адрес.', 1, 4, '2026-09-15 10:36:21'),
(71, 'notifications.email_feedback', 'Email обратной связи', '', 'string', NULL, 'notifications', 'Адрес обратной связи театра. Используется, если адрес для ответа не задан.', 1, 5, '2026-09-15 10:36:21');

-- --------------------------------------------------------

--
-- Структура таблицы `staff_backup_before_users_migration`
--

CREATE TABLE `staff_backup_before_users_migration` (
  `id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `staff_uid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','manager','cashier','scanner') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manager',
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

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
  `original_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` int(11) NOT NULL DEFAULT 0,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
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

INSERT INTO `tickets` (`id`, `schedule_id`, `event_id`, `hall_id`, `seat_id`, `seat_identifier`, `customer_id`, `customer_name`, `customer_phone`, `customer_email`, `customer_segment`, `channel`, `source_ref`, `ticket_uid`, `barcode`, `qr_code`, `price`, `original_price`, `final_price`, `discount`, `discount_amount`, `status`, `payment_status`, `payment_provider`, `payment_transaction_id`, `payment_session_id`, `is_checked_in`, `checked_in_at`, `refund_status`, `refund_at`, `purchased_at`, `created_at`, `updated_at`, `sold_by_staff_id`) VALUES
(332, 67, 24, 8, NULL, '16-10', 37, 'Дядя Вас', '+77773000000', 'pochta@pochta.com', 'adult', 'kassa', NULL, '48f7b115e69a2872', NULL, NULL, 10000.00, 10000.00, 10000.00, 0, 0.00, 'issued', 'paid', NULL, '253', NULL, 0, NULL, 'none', NULL, '2026-09-15 17:26:34', '2026-09-15 12:26:34', '2026-09-15 12:26:34', NULL),
(333, 67, 24, 8, NULL, '16-20', 37, 'Дядя Вас', '+77773000000', 'pochta@pochta.com', 'adult', 'kassa', NULL, '01bc5aeb27d7239f', NULL, NULL, 5000.00, 5000.00, 5000.00, 0, 0.00, 'issued', 'paid', NULL, '253', NULL, 0, NULL, 'none', NULL, '2026-09-15 17:26:34', '2026-09-15 12:26:34', '2026-09-15 12:26:34', NULL),
(334, 67, 24, 8, NULL, '16-22', 37, 'Дядя Вас', '+77773000000', 'pochta@pochta.com', 'student', 'kassa', NULL, 'e7550c7cee5e4222', NULL, NULL, 4000.00, 4000.00, 4000.00, 20, 0.00, 'issued', 'paid', NULL, '254', NULL, 0, NULL, 'none', NULL, '2026-09-15 17:26:48', '2026-09-15 12:26:48', '2026-09-15 12:26:48', NULL),
(335, 67, 24, 8, NULL, '16-23', 37, 'Дядя Вас', '+77773000000', 'pochta@pochta.com', 'student', 'kassa', NULL, '5929db1d69f61c40', NULL, NULL, 4000.00, 4000.00, 4000.00, 20, 0.00, 'issued', 'paid', NULL, '254', NULL, 0, NULL, 'none', NULL, '2026-09-15 17:26:48', '2026-09-15 12:26:48', '2026-09-15 12:26:48', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('admin','manager','cashier','scanner') NOT NULL DEFAULT 'manager',
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
(2, 'kassir_1', '$2y$12$XZF9PBaf9HAE0TPl3NWlxOl3op7ty8EGffyzNjfVw.ChnyjhXGzX6', 'Римма', 'cashier', 1, NULL, NULL, '2026-08-12 11:52:03', 0, NULL, NULL, '2026-08-12 06:52:03', '2026-09-12 06:10:46');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT для таблицы `cash_audit_log`
--
ALTER TABLE `cash_audit_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=282;

--
-- AUTO_INCREMENT для таблицы `cash_holds`
--
ALTER TABLE `cash_holds`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=232;

--
-- AUTO_INCREMENT для таблицы `cash_transactions`
--
ALTER TABLE `cash_transactions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=255;

--
-- AUTO_INCREMENT для таблицы `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT для таблицы `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT для таблицы `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT для таблицы `seats`
--
ALTER TABLE `seats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `seat_occupancy`
--
ALTER TABLE `seat_occupancy`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT для таблицы `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT для таблицы `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=336;

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
  ADD CONSTRAINT `fk_checkins_scanner_user` FOREIGN KEY (`scanner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
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
  ADD CONSTRAINT `fk_refunds_processed_by_user` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_refunds_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fk_schedules_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_schedules_hall` FOREIGN KEY (`hall_id`) REFERENCES `halls` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_tickets_sold_by_user` FOREIGN KEY (`sold_by_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
