-- GSSF Family Match Tracker - MySQL / MariaDB Schema (optional)
-- By default, the app uses SQLite at /var/www/gssf/data/gssf.sqlite.
-- To use MySQL instead: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS `gssf_tracker`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `gssf_tracker`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `default_division` VARCHAR(64) NOT NULL DEFAULT 'Amateur Civilian',
  `color` VARCHAR(16) NOT NULL DEFAULT '#1d4ed8',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `match_results` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `match_name` VARCHAR(120) NOT NULL,
  `match_date` DATE NOT NULL,
  `location` VARCHAR(120) NOT NULL DEFAULT '',
  `division` VARCHAR(64) NOT NULL DEFAULT 'Amateur Civilian',
  `five_to_glock` DECIMAL(8,2) NOT NULL,
  `glock_the_plates` DECIMAL(8,2) NOT NULL,
  `glock_m` DECIMAL(8,2) NOT NULL,
  `penalties` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `total_time` DECIMAL(8,2) NOT NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`, `match_date`),
  CONSTRAINT `fk_results_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `passkeys` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `credential_id` VARCHAR(512) NOT NULL,
  `public_key_pem` TEXT NOT NULL,
  `cose_alg` INT NOT NULL DEFAULT -7,
  `sign_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `label` VARCHAR(100) NOT NULL DEFAULT 'Passkey',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_credential_id` (`credential_id`),
  KEY `idx_passkey_user` (`user_id`),
  CONSTRAINT `fk_passkeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `match_name` VARCHAR(120) NOT NULL,
  `match_date` DATE NOT NULL,
  `location` VARCHAR(120) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_match_date` (`match_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `match_shooters` (
  `match_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`match_id`, `user_id`),
  CONSTRAINT `fk_ms_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ms_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
