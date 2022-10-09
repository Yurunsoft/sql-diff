CREATE TABLE `tb_diff1` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `modify` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `drop` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `index1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `index2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` year NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `index_modify` (`index1`) USING BTREE,
  KEY `index_drop` (`index2`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC