CREATE TABLE IF NOT EXISTS `#__dnaaitranslator_map` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `source_article_id` int unsigned NOT NULL,
  `target_language` varchar(7) NOT NULL,
  `target_article_id` int unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `notes` varchar(1000) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_source_language` (`source_article_id`, `target_language`),
  KEY `idx_target_article` (`target_article_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dnaaitranslator_category_map` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `source_category_id` int unsigned NOT NULL,
  `target_language` varchar(7) NOT NULL,
  `target_category_id` int unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_source_language` (`source_category_id`, `target_language`),
  KEY `idx_target_category` (`target_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
