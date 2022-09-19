<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yurun\SqlDiff\SqlDiff;

class SqlDiffTest extends TestCase
{
    public function test1(): void
    {
        $sqls = SqlDiff::diff(file_get_contents(__DIR__ . '/test1-1.sql'), file_get_contents(__DIR__ . '/test1-2.sql'));
        $this->assertEquals(<<<SQL
        DROP TABLE `tb_diff1_2`;
        CREATE TABLE `tb_test1` (
          `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `b` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
          PRIMARY KEY (`id`) USING BTREE,
          INDEX `b` (`b`) USING BTREE
        ) ENGINE=InnoDB AUTO_INCREMENT=1 CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=Dynamic;
        ALTER TABLE `tb_diff1` COMMENT='123' ROW_FORMAT=DYNAMIC ;
        ALTER TABLE `tb_diff1` DROP FOREIGN KEY `tb_diff1_ibfk_1` ;
        ALTER TABLE `tb_diff1` DROP INDEX `index_drop` ;
        ALTER TABLE `tb_diff1` DROP INDEX `index_modify` ;
        ALTER TABLE `tb_diff1` DROP COLUMN `drop` ;
        ALTER TABLE `tb_diff1` MODIFY COLUMN `modify` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL FIRST;
        ALTER TABLE `tb_diff1` ADD COLUMN `add` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `index2`;
        ALTER TABLE `tb_diff1` ADD INDEX `index_modify` (`index1`,`index2`) USING BTREE ;
        ALTER TABLE `tb_diff1` ADD INDEX `tb_diff1_ibfk_1` (`index2`) USING BTREE ;
        ALTER TABLE `tb_diff1` 
        PARTITION BY HASH (`id`)
        PARTITIONS 4 ;
        SQL, implode(';' . \PHP_EOL, $sqls) . ';');
    }

    public function test2(): void
    {
        $sqls = SqlDiff::diff(file_get_contents(__DIR__ . '/test2-1.sql'), file_get_contents(__DIR__ . '/test2-2.sql'));
        $this->assertEquals(<<<SQL
        CREATE TABLE `tb_test2` (
          `id` int(11) NOT NULL,
          `value` int(11) NOT NULL,
          PRIMARY KEY (`id`) USING BTREE,
          INDEX `value` (`value`) USING BTREE
        ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=Dynamic;
        ALTER TABLE `tb_diff2` ADD INDEX `value` (`value`) USING BTREE ;
        ALTER TABLE `tb_diff2` ADD CONSTRAINT `tb_diff2_ibfk_1` FOREIGN KEY (`value`) REFERENCES `tb_test2` (`value`) ON DELETE RESTRICT ON UPDATE RESTRICT ;
        SQL, implode(';' . \PHP_EOL, $sqls) . ';');
    }
}
