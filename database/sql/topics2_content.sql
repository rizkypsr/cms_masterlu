CREATE TABLE `topics2_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `topics2_category_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `id_header` int(11) DEFAULT NULL,
  `seq` int(11) DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
