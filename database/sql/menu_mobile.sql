CREATE TABLE `menu_mobile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` tinyint DEFAULT '1' COMMENT '1=enabled, 0=disabled',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default menu items
INSERT INTO `menu_mobile` (`nama`, `code`, `status`) VALUES
('Menu Agenda', 'agenda', 1),
('Menu Topik 1', 'topik1', 1),
('Menu Topik 2', 'topik2', 1),
('Menu Topik 3', 'topik3', 1),
('Menu Community', 'community', 1),
('Menu Book', 'book', 1);
