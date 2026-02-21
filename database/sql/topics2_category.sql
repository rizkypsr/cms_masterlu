CREATE TABLE `topics2_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `topics2_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `seq` int(11) DEFAULT NULL,
  `have_child` int(11) DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
