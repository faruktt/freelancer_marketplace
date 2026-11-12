CREATE DATABASE IF NOT EXISTS `kraftr_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kraftr_db`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
  `status` ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `title` VARCHAR(120) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `skills` VARCHAR(255) DEFAULT NULL,
  `country` VARCHAR(60) NOT NULL DEFAULT 'Remote',
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `seller_level` ENUM('new','rising','top_rated','pro') NOT NULL DEFAULT 'new',
  `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `earnings_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `review_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `icon` VARCHAR(60) NOT NULL DEFAULT 'category',
  `accent_color` VARCHAR(20) NOT NULL DEFAULT '#4F46E5',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gigs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `description` TEXT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `delivery_days` INT UNSIGNED NOT NULL DEFAULT 3,
  `revisions` INT UNSIGNED NOT NULL DEFAULT 2,
  `features` TEXT DEFAULT NULL,
  `tags` VARCHAR(255) DEFAULT NULL,
  `cover_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','paused','pending','rejected') NOT NULL DEFAULT 'active',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `review_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `orders_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_gig_slug` (`slug`),
  KEY `idx_gig_seller` (`seller_id`),
  KEY `idx_gig_category_status` (`category_id`, `status`),
  KEY `idx_gig_price` (`price`),
  CONSTRAINT `fk_gig_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gig_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `favorites` (
  `user_id` INT UNSIGNED NOT NULL,
  `gig_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `gig_id`),
  CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_gig` FOREIGN KEY (`gig_id`) REFERENCES `gigs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_code` VARCHAR(24) NOT NULL,
  `gig_id` INT UNSIGNED NOT NULL,
  `buyer_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NOT NULL,
  `gig_title` VARCHAR(160) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `platform_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `seller_net` DECIMAL(10,2) NOT NULL,
  `total_paid` DECIMAL(10,2) NOT NULL,
  `delivery_days` INT UNSIGNED NOT NULL DEFAULT 3,
  `revisions_left` INT NOT NULL DEFAULT 2,
  `requirements` TEXT DEFAULT NULL,
  `delivery_note` TEXT DEFAULT NULL,
  `delivery_file` VARCHAR(255) DEFAULT NULL,
  `revision_note` TEXT DEFAULT NULL,
  `status` ENUM('pending','in_progress','delivered','revision','completed','cancelled','disputed') NOT NULL DEFAULT 'pending',
  `due_at` DATETIME DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_code` (`order_code`),
  KEY `idx_order_buyer` (`buyer_id`, `status`),
  KEY `idx_order_seller` (`seller_id`, `status`),
  KEY `idx_order_gig` (`gig_id`),
  CONSTRAINT `fk_order_gig` FOREIGN KEY (`gig_id`) REFERENCES `gigs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `actor_id` INT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(40) NOT NULL,
  `note` TEXT DEFAULT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_order` (`order_id`),
  CONSTRAINT `fk_event_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `gig_id` INT UNSIGNED NOT NULL,
  `buyer_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_review_order` (`order_id`),
  KEY `idx_review_gig` (`gig_id`, `is_visible`),
  KEY `idx_review_seller` (`seller_id`, `is_visible`),
  CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_gig` FOREIGN KEY (`gig_id`) REFERENCES `gigs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_one_id` INT UNSIGNED NOT NULL,
  `user_two_id` INT UNSIGNED NOT NULL,
  `gig_id` INT UNSIGNED DEFAULT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `last_message_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_conv_pair` (`user_one_id`, `user_two_id`),
  KEY `idx_conv_last` (`last_message_at`),
  CONSTRAINT `fk_conv_u1` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conv_u2` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `gig_id` INT UNSIGNED DEFAULT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `body` TEXT NOT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `attachment_name` VARCHAR(160) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_conv` (`conversation_id`, `id`),
  KEY `idx_msg_unread` (`is_read`, `sender_id`),
  CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payout_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(60) NOT NULL DEFAULT 'Bank Transfer',
  `account_info` VARCHAR(160) NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payout_seller` (`seller_id`),
  CONSTRAINT `fk_payout_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(60) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Kraftr'),
('site_logo', ''),
('site_favicon', ''),
('logo_show_name', '1'),
('commission_pct', '10'),
('auto_approve_gigs', '0'),
('min_gig_price', '5'),
('max_gig_price', '10000'),
('announcement', '');

-- Seed Admin User
INSERT IGNORE INTO `users` (`id`, `name`, `username`, `email`, `password_hash`, `role`, `status`, `avatar`, `title`, `bio`, `skills`, `country`, `is_verified`, `seller_level`, `wallet_balance`, `earnings_total`, `rating_avg`, `review_count`) VALUES
(1, 'Administrator', 'admin', 'admin@kraftr.io', '$2y$10$xFM1uP5M3E73t8.ppdKLROmT1alqnLmCXqJ7dAwJNGdPxq41ZHEYW', 'admin', 'active', NULL, 'Platform Administrator', 'Marketplace administration and moderation.', NULL, 'Remote', 1, 'pro', 0.00, 0.00, 0.00, 0);

-- Seed Categories
INSERT IGNORE INTO `categories` (`id`, `name`, `slug`, `icon`, `accent_color`, `sort_order`, `is_active`) VALUES
(1, 'Web & App Dev', 'web-app-dev', 'code', '#4F46E5', 1, 1),
(2, 'UI/UX Design', 'ui-ux-design', 'palette', '#EC4899', 2, 1),
(3, 'AI & Data', 'ai-data', 'smart_toy', '#0EA5E9', 3, 1),
(4, 'Video & Motion', 'video-motion', 'movie_edit', '#F59E0B', 4, 1),
(5, 'Copywriting', 'copywriting', 'edit_note', '#10B981', 5, 1),
(6, 'Growth & SEO', 'growth-seo', 'campaign', '#8B5CF6', 6, 1),
(7, '3D & Brand', '3d-brand', 'view_in_ar', '#F97316', 7, 1),
(8, 'Security & QA', 'security-qa', 'shield', '#14B8A6', 8, 1);

