-- Topics3 table (like Topics2 but with content categories)
CREATE TABLE IF NOT EXISTS `topics3` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_category_id` int(11) DEFAULT NULL,
  `synopsis` text,
  `title` text,
  `seq` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics3 chapters table (like topics2_chapters)
CREATE TABLE IF NOT EXISTS `topics3_chapters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `topics3_id` int(11) DEFAULT NULL,
  `title` text,
  `description` text,
  `seq` int(11) DEFAULT NULL,
  `have_child` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics3 content category table (NEW - for categorizing content)
CREATE TABLE IF NOT EXISTS `topics3_content_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `seq` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics3 contents table (like topics2_contents but with category_id)
CREATE TABLE IF NOT EXISTS `topics3_contents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `topics3_chapters_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL COMMENT 'Reference to topics3_content_category',
  `content` text,
  `page` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_topics3_chapters_id` (`topics3_chapters_id`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
