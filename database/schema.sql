-- =============================================================================
-- Quran Website - MySQL schema (Version 1)
-- Target: MySQL 5.7+ / 8.x, MariaDB 10.2+  (InnoDB, utf8mb4)
--
-- Import with:
--   mysql -u USER -p < database/schema.sql
-- or through phpMyAdmin -> Import.
--
-- The database name below is a default. On shared hosting you usually get a
-- prefixed name (e.g. "cpuser_quran"). In that case create the database in
-- cPanel first, then import this file with the CREATE DATABASE / USE lines
-- removed, or simply edit those two lines before importing.
--
-- This file is idempotent: it creates tables only when they do not exist.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `quran_website`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `quran_website`;

-- -----------------------------------------------------------------------------
-- 1. Users and authentication
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(255) NULL DEFAULT NULL,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens. Only a SHA-256 hash of the token is stored, so a
-- database leak cannot be used to take over accounts.
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `requested_ip` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reset_token` (`token_hash`),
  KEY `idx_reset_user` (`user_id`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Personal data (bookmarks, reading progress, settings)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `surah_number` INT UNSIGNED NOT NULL,
  `verse_number` INT UNSIGNED NOT NULL,
  `note` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_verse` (`user_id`, `surah_number`, `verse_number`),
  KEY `idx_bookmarks_verse` (`surah_number`, `verse_number`),
  CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reading_progress` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `surah_number` INT UNSIGNED NOT NULL,
  `verse_number` INT UNSIGNED NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_settings` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `translation_id` VARCHAR(100) NULL DEFAULT NULL,
  `reciter_id` VARCHAR(100) NULL DEFAULT NULL,
  `arabic_font_size` VARCHAR(20) NULL DEFAULT NULL,
  `theme` VARCHAR(20) NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Quran content cache
--
-- Quran text is NOT hardcoded in this project. It is fetched from the Quran
-- data API by bin/import-quran.php and cached here. The site then serves
-- readers, search and navigation from MySQL, which keeps page loads fast and
-- survives API outages or rate limits.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `chapters` (
  `id` TINYINT UNSIGNED NOT NULL,
  `name_arabic` VARCHAR(100) NOT NULL,
  `name_simple` VARCHAR(100) NOT NULL,
  `name_english` VARCHAR(150) NOT NULL,
  `translated_name` VARCHAR(150) NOT NULL,
  `revelation_place` ENUM('makkah','madinah') NOT NULL DEFAULT 'makkah',
  `revelation_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `verses_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `bismillah_pre` TINYINT(1) NOT NULL DEFAULT 1,
  `pages_from` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `pages_to` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `verses` (
  `id` INT UNSIGNED NOT NULL COMMENT 'Global ayah number 1..6236 - used for audio URLs',
  `surah_number` TINYINT UNSIGNED NOT NULL,
  `verse_number` SMALLINT UNSIGNED NOT NULL,
  `verse_key` VARCHAR(10) NOT NULL COMMENT 'e.g. 2:255',
  `text_uthmani` TEXT NOT NULL,
  `text_plain` TEXT NOT NULL COMMENT 'Normalised Arabic used for search',
  `juz` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `hizb_quarter` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `page` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `sajda` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_verse_key` (`surah_number`, `verse_number`),
  KEY `idx_verses_page` (`page`),
  KEY `idx_verses_juz` (`juz`),
  CONSTRAINT `fk_verses_chapter` FOREIGN KEY (`surah_number`) REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `translations` (
  `id` VARCHAR(100) NOT NULL COMMENT 'Edition identifier, e.g. en.sahih',
  `name` VARCHAR(150) NOT NULL,
  `english_name` VARCHAR(150) NOT NULL,
  `author_name` VARCHAR(190) NOT NULL DEFAULT '',
  `language_code` VARCHAR(10) NOT NULL DEFAULT 'en',
  `language_name` VARCHAR(100) NOT NULL DEFAULT 'English',
  `direction` ENUM('ltr','rtl') NOT NULL DEFAULT 'ltr',
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `verse_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `synced_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_translations_lang` (`language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `verse_translations` (
  `verse_id` INT UNSIGNED NOT NULL,
  `translation_id` VARCHAR(100) NOT NULL,
  `text` TEXT NOT NULL,
  `text_plain` TEXT NOT NULL COMMENT 'Lower-cased, punctuation-stripped text used for search',
  PRIMARY KEY (`verse_id`, `translation_id`),
  KEY `idx_vt_translation` (`translation_id`),
  CONSTRAINT `fk_vt_verse` FOREIGN KEY (`verse_id`) REFERENCES `verses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vt_translation` FOREIGN KEY (`translation_id`) REFERENCES `translations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reciters` (
  `id` VARCHAR(100) NOT NULL COMMENT 'CDN edition id, e.g. ar.alafasy',
  `name` VARCHAR(150) NOT NULL,
  `english_name` VARCHAR(150) NOT NULL,
  `style` VARCHAR(50) NOT NULL DEFAULT '',
  `bitrate` SMALLINT UNSIGNED NOT NULL DEFAULT 128,
  `audio_base` VARCHAR(255) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `juzs` (
  `number` TINYINT UNSIGNED NOT NULL,
  `first_surah` TINYINT UNSIGNED NOT NULL,
  `first_verse` SMALLINT UNSIGNED NOT NULL,
  `last_surah` TINYINT UNSIGNED NOT NULL,
  `last_verse` SMALLINT UNSIGNED NOT NULL,
  `verses_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Operational tables
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket` VARCHAR(40) NOT NULL,
  `identifier` CHAR(64) NOT NULL,
  `window_start` INT UNSIGNED NOT NULL,
  `hits` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rate_window` (`bucket`, `identifier`, `window_start`),
  KEY `idx_rate_cleanup` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task` VARCHAR(40) NOT NULL,
  `status` ENUM('running','success','failed') NOT NULL DEFAULT 'running',
  `message` TEXT NULL DEFAULT NULL,
  `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sync_task` (`task`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Optional future-feature tables (kept for compatibility with the brief)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `surah_number` INT UNSIGNED NOT NULL,
  `verse_number` INT UNSIGNED NOT NULL,
  `note` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_note` (`user_id`, `surah_number`, `verse_number`),
  CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `highlights` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `surah_number` INT UNSIGNED NOT NULL,
  `verse_number` INT UNSIGNED NOT NULL,
  `color` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_highlight` (`user_id`, `surah_number`, `verse_number`),
  CONSTRAINT `fk_highlights_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
