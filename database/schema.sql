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
  `wallet_balance` DECIMAL(12,2) NOT NULL DEFAULT 500.00,
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

-- Seed Users (Admin / Sellers / Buyers)
INSERT IGNORE INTO `users` (`id`, `name`, `username`, `email`, `password_hash`, `role`, `status`, `avatar`, `title`, `bio`, `skills`, `country`, `is_verified`, `seller_level`, `wallet_balance`, `earnings_total`, `rating_avg`, `review_count`) VALUES
(1, 'Elena Vance', 'admin', 'admin@kraftr.io', '$2y$10$xFM1uP5M3E73t8.ppdKLROmT1alqnLmCXqJ7dAwJNGdPxq41ZHEYW', 'admin', 'active', 'assets/images/avatar-admin.jpg', 'Platform Director', 'Marketplace operations and trust safety.', 'Operations,Moderation', 'USA', 1, 'pro', 2500.00, 0.00, 5.00, 0),
(2, 'Alex Mercer', 'alexmercer', 'alex@kraftr.io', '$2y$10$xFM1uP5M3E73t8.ppdKLROmT1alqnLmCXqJ7dAwJNGdPxq41ZHEYW', 'seller', 'active', 'assets/images/avatar-1.jpg', 'Full-Stack Web Architect', 'Building fast PHP, MySQL, and modern JS web applications with clean architecture.', 'PHP,MySQL,JavaScript,API,UI', 'Canada', 1, 'top_rated', 1420.00, 3840.00, 4.95, 2),
(3, 'Maya Lin', 'mayalin', 'maya@kraftr.io', '$2y$10$xFM1uP5M3E73t8.ppdKLROmT1alqnLmCXqJ7dAwJNGdPxq41ZHEYW', 'seller', 'active', 'assets/images/avatar-2.jpg', 'Product & Brand Designer', 'Minimalist SaaS interfaces, design systems, and crisp brand identities.', 'Figma,UI/UX,Design Systems,Branding', 'Singapore', 1, 'pro', 980.00, 5210.00, 5.00, 2),
(4, 'Liam Carter', 'liamcarter', 'liam@kraftr.io', '$2y$10$xFM1uP5M3E73t8.ppdKLROmT1alqnLmCXqJ7dAwJNGdPxq41ZHEYW', 'seller', 'active', 'assets/images/avatar-3.jpg', 'AI Automation Specialist', 'Custom LLM workflows, Python pipelines, and analytics dashboards.', 'AI,Python,Automation,Data', 'UK', 1, 'rising', 640.00, 1950.00, 4.80, 1),
(5, 'Sara Novak', 'saranovak', 'sara@kraftr.io', '$2y$10$xFM1uP5M3E73t8.ppdKLROmT1alqnLmCXqJ7dAwJNGdPxq41ZHEYW', 'seller', 'active', 'assets/images/avatar-4.jpg', 'Motion & Video Editor', 'High-retention product reels, SaaS launch videos, and 3D motion graphics.', 'After Effects,Premiere,3D,Motion', 'Germany', 1, 'top_rated', 710.00, 2760.00, 4.90, 1),
(6, 'Jordan Reed', 'jordanreed', 'buyer@kraftr.io', '$2y$10$xFM1uP5M3E73t8.ppdKLROmT1alqnLmCXqJ7dAwJNGdPxq41ZHEYW', 'buyer', 'active', 'assets/images/avatar-5.jpg', 'Startup Founder', 'Hiring vetted engineers and designers for rapid product launches.', 'Product,Growth', 'USA', 1, 'new', 1850.00, 0.00, 0.00, 0),
(7, 'Omar Khalid', 'omarkhalid', 'omar@kraftr.io', '$2y$10$xFM1uP5M3E73t8.ppdKLROmT1alqnLmCXqJ7dAwJNGdPxq41ZHEYW', 'buyer', 'active', 'assets/images/avatar-6.jpg', 'E-commerce Lead', 'Scaling direct-to-consumer digital storefronts.', 'E-commerce,Marketing', 'UAE', 0, 'new', 920.00, 0.00, 0.00, 0);

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

-- Seed Gigs
INSERT IGNORE INTO `gigs` (`id`, `seller_id`, `category_id`, `title`, `slug`, `description`, `price`, `delivery_days`, `revisions`, `features`, `tags`, `cover_image`, `status`, `is_featured`, `rating_avg`, `review_count`, `orders_count`, `views_count`) VALUES
(1, 2, 1, 'Build a fast full-stack PHP & MySQL web app with clean UI', 'fast-fullstack-php-mysql-web-app', 'Production-ready custom web application built with PHP 8, MySQL PDO, responsive CSS, and smooth vanilla JS interactions. Secure auth, REST endpoints, and deployment-ready structure included.', 180.00, 4, 3, "Clean MVC-style architecture\nMySQL schema & prepared statements\nResponsive mobile-first UI\nFull source code & documentation", 'php,mysql,webapp,fullstack', 'assets/images/gig-1.jpg', 'active', 1, 5.00, 2, 18, 420),
(2, 3, 2, 'Design a modern SaaS dashboard & interactive Figma system', 'modern-saas-dashboard-figma-system', 'Complete dark and light UI kit with reusable Figma components, responsive grids, spacing tokens, and interactive prototypes ready for developer handoff.', 140.00, 3, 4, "12+ responsive screens\nComponent library & tokens\nInteractive clickable prototype\nDeveloper handoff specs", 'figma,uiux,saas,dashboard', 'assets/images/gig-2.jpg', 'active', 1, 5.00, 1, 24, 510),
(3, 4, 3, 'Create custom AI workflow & automated data pipeline', 'custom-ai-workflow-data-pipeline', 'Connect LLMs, webhooks, and SQL databases into reliable automated pipelines with structured JSON outputs, error handling, and usage monitoring.', 220.00, 5, 2, "Custom API & webhook integration\nStructured JSON extraction\nPrompt evaluation benchmark\nDeployment guide", 'ai,automation,python,data', 'assets/images/gig-3.jpg', 'active', 1, 4.80, 1, 11, 290),
(4, 5, 4, 'Produce a crisp 45s SaaS launch motion reel in 4K', 'crisp-saas-launch-motion-reel-4k', 'High-converting product explainer with kinetic typography, smooth UI mockups, custom sound design, and social aspect-ratio exports.', 165.00, 3, 2, "4K 60fps master export\nLicensed SFX & soundtrack\nAnimated UI screen captures\n9:16 & 16:9 cuts included", 'motion,video,saas,explainer', 'assets/images/gig-4.jpg', 'active', 1, 4.90, 1, 15, 340),
(5, 3, 7, 'Craft a minimalist geometric brand identity & logo kit', 'minimalist-geometric-brand-identity', 'Distinctive vector wordmark, symbol mark, typography pairing, color palette, and social launch kit tailored for modern tech companies.', 110.00, 2, 3, "3 initial logo concepts\nSVG, PNG & PDF vector files\nBrand color & font sheet\nSocial profile kit", 'logo,branding,vector,minimal', 'assets/images/gig-5.jpg', 'active', 0, 5.00, 1, 31, 610),
(6, 2, 1, 'Optimize MySQL queries & refactor legacy PHP backend speed', 'optimize-mysql-queries-php-speed', 'Deep bottleneck audit, composite indexing, N+1 query elimination, and clean API caching to cut page latency under 100ms.', 85.00, 2, 2, "Slow query audit report\nIndex & schema tuning\nSecurity & SQLi hardening\nBefore/after benchmark", 'mysql,performance,php,backend', 'assets/images/gig-6.jpg', 'active', 0, 4.90, 0, 9, 175),
(7, 4, 5, 'Write high-converting landing page & onboarding microcopy', 'high-converting-landing-page-copy', 'Sharp, zero-fluff product messaging, hero headlines, value props, and transactional email sequences engineered for conversion.', 75.00, 2, 3, "Full landing page wireframe copy\n3 hero headline variants\n5 onboarding email templates\nTone of voice mini-guide", 'copywriting,saas,landing,uxwriting', 'assets/images/gig-7.jpg', 'active', 0, 4.85, 0, 14, 215),
(8, 5, 6, 'Run a technical SEO audit & Core Web Vitals sprint', 'technical-seo-audit-core-web-vitals', 'Actionable crawl diagnostics, structured schema markup, meta architecture, and speed remediation checklist for organic growth.', 95.00, 3, 2, "Full site crawl & fixes\nSchema.org JSON-LD setup\nCore Web Vitals tuning\nKeyword gap roadmap", 'seo,growth,analytics,vitals', 'assets/images/gig-8.jpg', 'active', 0, 4.75, 0, 7, 160),
(9, 2, 8, 'Perform OWASP web security audit & penetration checklist', 'owasp-web-security-audit-checklist', 'Manual and automated verification covering Auth, CSRF, XSS, SQL injection, file upload sanitization, and session hardening.', 150.00, 3, 1, "OWASP Top 10 inspection\nPatch code recommendations\nAuth & upload review\nExecutive fix summary", 'security,owasp,pentest,audit', 'assets/images/gig-9.jpg', 'active', 0, 5.00, 0, 6, 130),
(10, 3, 2, 'Redesign your mobile app UI for iOS and Android clarity', 'redesign-mobile-app-ui-ios-android', 'Clean, thumb-friendly mobile screens following native ergonomics, crisp typography, and smooth micro-interaction guidelines.', 190.00, 4, 3, "10 core mobile screens\nLight & dark variants\nFigma auto-layout file\nExportable SVG icons", 'mobile,ios,android,uidesign', 'assets/images/gig-10.jpg', 'active', 0, 4.95, 0, 12, 260),
(11, 5, 7, 'Render sleek 3D product hero visuals for landing pages', 'render-3d-product-hero-visuals', 'Photorealistic or stylized glassmorphic 3D assets rendered with transparent PNGs and clean lighting for SaaS and hardware launches.', 130.00, 3, 2, "4 high-res 3D renders\nTransparent PNG + source\nCustom brand lighting\nWebP web-ready files", '3d,blender,product,renders', 'assets/images/gig-11.jpg', 'active', 0, 4.90, 0, 8, 195),
(12, 4, 3, 'Build an executive KPI analytics & reporting dashboard', 'executive-kpi-analytics-dashboard', 'Interactive metrics dashboard integrating CSV, SQL, or REST sources with clean filters and automated weekly snapshots.', 160.00, 4, 2, "Live SQL/API connectors\nFilterable KPI views\nCSV/PDF export support\nClean documentation", 'analytics,kpi,bi,dashboard', 'assets/images/gig-12.jpg', 'active', 0, 4.85, 0, 10, 240);

-- Seed Favorites
INSERT IGNORE INTO `favorites` (`user_id`, `gig_id`) VALUES
(6, 1),
(6, 2),
(6, 4),
(7, 2);

-- Seed Orders
INSERT IGNORE INTO `orders` (`id`, `order_code`, `gig_id`, `buyer_id`, `seller_id`, `gig_title`, `price`, `platform_fee`, `seller_net`, `total_paid`, `delivery_days`, `revisions_left`, `requirements`, `delivery_note`, `status`, `due_at`, `delivered_at`, `completed_at`, `created_at`) VALUES
(1, 'ORD-904101', 1, 6, 2, 'Build a fast full-stack PHP & MySQL web app with clean UI', 180.00, 18.00, 162.00, 180.00, 4, 3, 'Need a clean client portal with login, ticket submission, and status tracker.', 'Completed all modules with PDO auth, responsive tables, and SQL dump attached.', 'completed', DATE_ADD(NOW(), INTERVAL -1 DAY), DATE_ADD(NOW(), INTERVAL -2 DAY), DATE_ADD(NOW(), INTERVAL -1 DAY), DATE_ADD(NOW(), INTERVAL -5 DAY)),
(2, 'ORD-904102', 2, 6, 3, 'Design a modern SaaS dashboard & interactive Figma system', 140.00, 14.00, 126.00, 140.00, 3, 4, 'Fintech analytics dashboard in light mode with crisp indigo accents.', 'Delivered 14 screens plus full component token library in Figma.', 'completed', DATE_ADD(NOW(), INTERVAL -2 DAY), DATE_ADD(NOW(), INTERVAL -3 DAY), DATE_ADD(NOW(), INTERVAL -2 DAY), DATE_ADD(NOW(), INTERVAL -6 DAY)),
(3, 'ORD-904103', 3, 6, 4, 'Create custom AI workflow & automated data pipeline', 220.00, 22.00, 198.00, 220.00, 5, 2, 'Summarize incoming support emails into structured priority tags in MySQL.', 'First build is staged for review with sample JSON test output.', 'delivered', DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NULL, DATE_ADD(NOW(), INTERVAL -2 DAY)),
(4, 'ORD-904104', 4, 7, 5, 'Produce a crisp 45s SaaS launch motion reel in 4K', 165.00, 16.50, 148.50, 165.00, 3, 2, 'Highlight 3 product features with fast kinetic transitions and upbeat audio.', NULL, 'in_progress', DATE_ADD(NOW(), INTERVAL 2 DAY), NULL, NULL, DATE_ADD(NOW(), INTERVAL -1 DAY)),
(5, 'ORD-904105', 5, 7, 3, 'Craft a minimalist geometric brand identity & logo kit', 110.00, 11.00, 99.00, 110.00, 2, 3, 'Geometric emblem for a cloud logistics startup named SwiftGrid.', NULL, 'pending', DATE_ADD(NOW(), INTERVAL 2 DAY), NULL, NULL, NOW());

INSERT IGNORE INTO `order_events` (`order_id`, `actor_id`, `event_type`, `note`, `created_at`) VALUES
(1, 6, 'created', 'Order placed and requirements submitted.', DATE_ADD(NOW(), INTERVAL -5 DAY)),
(1, 2, 'started', 'Seller started working on the project.', DATE_ADD(NOW(), INTERVAL -4 DAY)),
(1, 2, 'delivered', 'Completed all modules with PDO auth, responsive tables, and SQL dump attached.', DATE_ADD(NOW(), INTERVAL -2 DAY)),
(1, 6, 'completed', 'Buyer approved delivery and released payment.', DATE_ADD(NOW(), INTERVAL -1 DAY)),
(2, 6, 'created', 'Order placed and requirements submitted.', DATE_ADD(NOW(), INTERVAL -6 DAY)),
(2, 3, 'delivered', 'Delivered 14 screens plus full component token library in Figma.', DATE_ADD(NOW(), INTERVAL -3 DAY)),
(2, 6, 'completed', 'Buyer approved design handoff.', DATE_ADD(NOW(), INTERVAL -2 DAY)),
(3, 6, 'created', 'Order placed and requirements submitted.', DATE_ADD(NOW(), INTERVAL -2 DAY)),
(3, 4, 'started', 'Pipeline scaffolding initialized.', DATE_ADD(NOW(), INTERVAL -1 DAY)),
(3, 4, 'delivered', 'First build is staged for review with sample JSON test output.', NOW()),
(4, 7, 'created', 'Order placed and requirements submitted.', DATE_ADD(NOW(), INTERVAL -1 DAY)),
(4, 5, 'started', 'Storyboarding and motion keyframes underway.', NOW()),
(5, 7, 'created', 'Order placed and waiting for seller acceptance.', NOW());

-- Seed Reviews
INSERT IGNORE INTO `reviews` (`id`, `order_id`, `gig_id`, `buyer_id`, `seller_id`, `rating`, `comment`, `is_visible`) VALUES
(1, 1, 1, 6, 2, 5, 'Exceptionally clean PHP code and fast delivery. Zero bugs on deployment.', 1),
(2, 2, 2, 6, 3, 5, 'Crisp Figma system, thoughtfully named components, and super responsive communication.', 1);

-- Seed Conversations & Messages
INSERT IGNORE INTO `conversations` (`id`, `user_one_id`, `user_two_id`, `gig_id`, `order_id`, `last_message_at`) VALUES
(1, 2, 6, 1, 1, DATE_ADD(NOW(), INTERVAL -1 HOUR)),
(2, 3, 6, 2, 2, DATE_ADD(NOW(), INTERVAL -3 HOUR)),
(3, 4, 6, 3, 3, NOW());

INSERT IGNORE INTO `messages` (`conversation_id`, `sender_id`, `gig_id`, `order_id`, `body`, `is_read`, `created_at`) VALUES
(1, 6, 1, 1, 'Hey Alex, can you include CSV export in the client portal?', 1, DATE_ADD(NOW(), INTERVAL -2 HOUR)),
(1, 2, 1, 1, 'Yes, already added a clean CSV export endpoint. Check the delivered archive!', 1, DATE_ADD(NOW(), INTERVAL -1 HOUR)),
(2, 3, 2, 2, 'Shared the Figma prototype link inside the order deliverables.', 1, DATE_ADD(NOW(), INTERVAL -3 HOUR)),
(3, 4, 3, 3, 'Hi Jordan, I just delivered the first pipeline build for your review. Let me know if you want any schema tweaks!', 0, NOW());

