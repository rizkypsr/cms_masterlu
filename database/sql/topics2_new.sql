-- Topics2 table (like Book table but without url and url_pdf fields)
CREATE TABLE IF NOT EXISTS `topics2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_category_id` int(11) DEFAULT NULL,
  `synopsis` text,
  `title` text,
  `seq` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics2 chapters table (like book_chapters)
CREATE TABLE IF NOT EXISTS `topics2_chapters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `topics2_id` int(11) DEFAULT NULL,
  `title` text,
  `description` text,
  `seq` int(11) DEFAULT NULL,
  `have_child` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topics2 contents table (exactly like book_contents)
CREATE TABLE IF NOT EXISTS `topics2_contents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `topics2_chapters_id` int(11) DEFAULT NULL,
  `content` text,
  `page` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
