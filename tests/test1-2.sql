SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for tb_diff1
-- ----------------------------
DROP TABLE IF EXISTS `tb_diff1`;
CREATE TABLE `tb_diff1`  (
  `modify` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `index1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `index2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `add` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `index_modify`(`index1`, `index2`) USING BTREE,
  INDEX `tb_diff1_ibfk_1`(`index2`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '123' ROW_FORMAT = DYNAMIC PARTITION BY HASH (`id`)
PARTITIONS 4
(PARTITION `p0` MAX_ROWS = 0 MIN_ROWS = 0 ,
PARTITION `p1` MAX_ROWS = 0 MIN_ROWS = 0 ,
PARTITION `p2` MAX_ROWS = 0 MIN_ROWS = 0 ,
PARTITION `p3` MAX_ROWS = 0 MIN_ROWS = 0 )
;

-- ----------------------------
-- Table structure for tb_test1
-- ----------------------------
DROP TABLE IF EXISTS `tb_test1`;
CREATE TABLE `tb_test1`  (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `b` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `b`(`b`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
