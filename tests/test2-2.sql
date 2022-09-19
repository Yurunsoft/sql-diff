/*
 Navicat Premium Data Transfer

 Source Server         : 127.0.0.1_3306
 Source Server Type    : MySQL
 Source Server Version : 50736
 Source Host           : 127.0.0.1:3306
 Source Schema         : b

 Target Server Type    : MySQL
 Target Server Version : 50736
 File Encoding         : 65001

 Date: 19/09/2022 16:30:04
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for tb_diff2
-- ----------------------------
DROP TABLE IF EXISTS `tb_diff2`;
CREATE TABLE `tb_diff2`  (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `value`(`value`) USING BTREE,
  CONSTRAINT `tb_diff2_ibfk_1` FOREIGN KEY (`value`) REFERENCES `tb_test2` (`value`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for tb_test2
-- ----------------------------
DROP TABLE IF EXISTS `tb_test2`;
CREATE TABLE `tb_test2`  (
  `id` int(11) NOT NULL,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `value`(`value`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
