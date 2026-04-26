-- ============================================================
-- TourMate Social — Complete Database Schema v3
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- USE THIS FILE for a fresh install.
-- For existing installs use: tourmate_v2_migration.sql
-- ============================================================

-- ============================================================
-- HOW TO IMPORT ON HOSTINGER / SHARED HOSTING:
-- 1. Create database via hosting control panel (Databases section)
-- 2. Open phpMyAdmin and CLICK on your database name (left sidebar)
-- 3. Then click Import tab and choose this file
-- Do NOT run CREATE DATABASE here — hosting panel does that.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `mobile`        VARCHAR(20)  DEFAULT NULL,
  `password`      VARCHAR(255) NOT NULL,
  `profile_photo` VARCHAR(255) DEFAULT NULL,
  `address`       TEXT         DEFAULT NULL,
  `bio`           TEXT         DEFAULT NULL,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `role`          ENUM('user','admin')      NOT NULL DEFAULT 'user',
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: admins
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: tour_groups
-- ============================================================
CREATE TABLE IF NOT EXISTS `tour_groups` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `destination` VARCHAR(200) NOT NULL,
  `start_date`  DATE         NOT NULL,
  `return_date` DATE         NOT NULL,
  `cover_photo` VARCHAR(255) DEFAULT NULL,
  `description` TEXT         DEFAULT NULL,
  `status`      ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  `created_by`  INT(11)      NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_group_creator` (`created_by`),
  KEY `idx_groups_status` (`status`),
  CONSTRAINT `fk_group_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: group_members
-- ============================================================
CREATE TABLE IF NOT EXISTS `group_members` (
  `id`        INT(11) NOT NULL AUTO_INCREMENT,
  `group_id`  INT(11) NOT NULL,
  `user_id`   INT(11) NOT NULL,
  `role`      ENUM('admin','member') NOT NULL DEFAULT 'member',
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`group_id`,`user_id`),
  KEY `fk_gm_user` (`user_id`),
  CONSTRAINT `fk_gm_group` FOREIGN KEY (`group_id`) REFERENCES `tour_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gm_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: tour_plans
-- ============================================================
CREATE TABLE IF NOT EXISTS `tour_plans` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `group_id`       INT(11)      NOT NULL,
  `title`          VARCHAR(200) NOT NULL,
  `description`    TEXT         DEFAULT NULL,
  `plan_date`      DATE         DEFAULT NULL,
  `plan_time`      TIME         DEFAULT NULL,
  `location`       VARCHAR(200) DEFAULT NULL,
  `assigned_to`    INT(11)      DEFAULT NULL,
  `estimated_cost` DECIMAL(12,2) DEFAULT NULL,
  `status`         ENUM('pending','running','completed') NOT NULL DEFAULT 'pending',
  `created_by`     INT(11)      NOT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_plan_group`    (`group_id`),
  KEY `fk_plan_assigned` (`assigned_to`),
  KEY `fk_plan_creator`  (`created_by`),
  CONSTRAINT `fk_plan_group`    FOREIGN KEY (`group_id`)    REFERENCES `tour_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plan_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plan_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: expenses
-- ============================================================
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `group_id`      INT(11)      NOT NULL,
  `title`         VARCHAR(200) NOT NULL,
  `amount`        DECIMAL(12,2) NOT NULL,
  `category`      ENUM('transport','food','hotel','ticket','shopping','emergency','other') NOT NULL DEFAULT 'other',
  `paid_by`       INT(11)      NOT NULL,
  `expense_date`  DATE         DEFAULT NULL,
  `note`          TEXT         DEFAULT NULL,
  `receipt_image` VARCHAR(255) DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_exp_group`  (`group_id`),
  KEY `fk_exp_paidby` (`paid_by`),
  CONSTRAINT `fk_exp_group`  FOREIGN KEY (`group_id`) REFERENCES `tour_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exp_paidby` FOREIGN KEY (`paid_by`)  REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: expense_splits
-- ============================================================
CREATE TABLE IF NOT EXISTS `expense_splits` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `expense_id` INT(11)      NOT NULL,
  `user_id`    INT(11)      NOT NULL,
  `amount`     DECIMAL(12,2) NOT NULL,
  `is_paid`    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_split` (`expense_id`,`user_id`),
  KEY `fk_split_user` (`user_id`),
  CONSTRAINT `fk_split_expense` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_split_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: posts
-- ============================================================
CREATE TABLE IF NOT EXISTS `posts` (
  `id`         INT(11)  NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)  NOT NULL,
  `group_id`   INT(11)  DEFAULT NULL,
  `content`    TEXT     NOT NULL,
  `image`      VARCHAR(255) DEFAULT NULL,
  `visibility` ENUM('public','group') NOT NULL DEFAULT 'group',
  `status`     ENUM('active','deleted') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_post_user`  (`user_id`),
  KEY `fk_post_group` (`group_id`),
  KEY `idx_posts_status` (`status`),
  CONSTRAINT `fk_post_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_post_group` FOREIGN KEY (`group_id`) REFERENCES `tour_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: post_likes
-- ============================================================
CREATE TABLE IF NOT EXISTS `post_likes` (
  `id`         INT(11)   NOT NULL AUTO_INCREMENT,
  `post_id`    INT(11)   NOT NULL,
  `user_id`    INT(11)   NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_like` (`post_id`,`user_id`),
  KEY `fk_like_user` (`user_id`),
  CONSTRAINT `fk_like_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_like_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: post_comments
-- ============================================================
CREATE TABLE IF NOT EXISTS `post_comments` (
  `id`         INT(11)   NOT NULL AUTO_INCREMENT,
  `post_id`    INT(11)   NOT NULL,
  `user_id`    INT(11)   NOT NULL,
  `content`    TEXT      NOT NULL,
  `status`     ENUM('active','deleted') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_comment_post` (`post_id`),
  KEY `fk_comment_user` (`user_id`),
  CONSTRAINT `fk_comment_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`      INT(11)      NOT NULL,
  `from_user_id` INT(11)      DEFAULT NULL,
  `group_id`     INT(11)      DEFAULT NULL,
  `type`         VARCHAR(50)  NOT NULL,
  `message`      TEXT         NOT NULL,
  `link`         VARCHAR(255) DEFAULT NULL,
  `is_read`      TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_user`  (`user_id`),
  KEY `fk_notif_from`  (`from_user_id`),
  KEY `fk_notif_group` (`group_id`),
  KEY `idx_notif_read` (`user_id`,`is_read`),
  CONSTRAINT `fk_notif_user`  FOREIGN KEY (`user_id`)      REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_from`  FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_group` FOREIGN KEY (`group_id`)     REFERENCES `tour_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: group_payments (advance / settlement payments)
-- ============================================================
CREATE TABLE IF NOT EXISTS `group_payments` (
  `id`           INT(11)       NOT NULL AUTO_INCREMENT,
  `group_id`     INT(11)       NOT NULL,
  `from_user_id` INT(11)       NOT NULL,
  `to_user_id`   INT(11)       NOT NULL,
  `amount`       DECIMAL(12,2) NOT NULL,
  `note`         TEXT          DEFAULT NULL,
  `payment_date` DATE          NOT NULL,
  `created_by`   INT(11)       NOT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_gp_group`    (`group_id`),
  KEY `fk_gp_from`     (`from_user_id`),
  KEY `fk_gp_to`       (`to_user_id`),
  KEY `fk_gp_creator`  (`created_by`),
  CONSTRAINT `fk_gp_group`   FOREIGN KEY (`group_id`)     REFERENCES `tour_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gp_from`    FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gp_to`      FOREIGN KEY (`to_user_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gp_creator` FOREIGN KEY (`created_by`)   REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: site_settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         DEFAULT NULL,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Default site settings
-- ============================================================
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
  ('site_name',        'TourMate Social'),
  ('site_email',       'admin@tourmate.com'),
  ('footer_text',      '© 2025 TourMate Social. All rights reserved.'),
  ('maintenance_mode', '0'),
  ('primary_color',    '#2563eb'),
  ('logo',             '')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- ============================================================
-- Upload directories placeholder (.gitkeep)
-- (Create these folders manually: uploads/profile, uploads/group,
--  uploads/posts, uploads/receipts)
-- ============================================================

COMMIT;

-- ============================================================
-- After import, create your first admin account:
-- Visit: http://yourdomain.com/admin/setup.php
-- Delete setup.php immediately after!
-- ============================================================

-- Test user (password = "password123")
INSERT INTO `users` (`name`,`email`,`mobile`,`password`,`bio`,`status`) VALUES
('Demo User','demo@tourmate.com','01700000000',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Travel lover and adventure seeker!', 'active')
ON DUPLICATE KEY UPDATE `id` = `id`;
