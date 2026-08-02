-- ZhiCms 5.0.1 数据库升级 SQL
-- 服务器数据库: zhicms | 前缀: yun_
-- 用途：旧版本升级到当前结构时由 remote_update.php 自动执行（按分号逐条执行）
-- 注意：以下 ALTER 字段若目标库已存在则会报错（属预期，可忽略或手动跳过）。
-- 生成时间: 2026-08-02 15:41:37

ALTER TABLE `yun_article` ADD COLUMN `navid` int(11) NOT NULL DEFAULT '0' COMMENT '发现分类ID（yun_nav.id），0=未分类';
ALTER TABLE `yun_article` ADD COLUMN `allow_comment` tinyint(1) NOT NULL DEFAULT '1' COMMENT '允许评论 1允许 0禁止';
ALTER TABLE `yun_article` ADD COLUMN `featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推荐文章 1推荐 0否';
ALTER TABLE `yun_items` ADD COLUMN `spec` varchar(500) DEFAULT '' COMMENT '规格';
ALTER TABLE `yun_forum` ADD COLUMN `lock` int(11) DEFAULT '0' COMMENT '锁定';
ALTER TABLE `yun_forum` ADD COLUMN `featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推荐';

-- 版本号对齐：升级后将 cfg_version 更新为 5.0.1（与 data/config/version.php 一致）
INSERT INTO `yun_config` (`key`, `value`, `desc`) VALUES ('cfg_version', '{"version":"5.0.1"}', '版本号')
ON DUPLICATE KEY UPDATE `value` = '{"version":"5.0.1"}';

-- 后台操作日志表（系统日志模块，如不存在则创建）
CREATE TABLE IF NOT EXISTS `yun_admin_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL DEFAULT '',
  `content` varchar(500) NOT NULL DEFAULT '',
  `operator` varchar(100) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `url` varchar(500) NOT NULL DEFAULT '',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台操作日志';


-- 计划任务表（计划任务模块）（如不存在则创建）
CREATE TABLE IF NOT EXISTS `yun_cron_task` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '任务名称',
  `type` varchar(20) NOT NULL DEFAULT 'custom' COMMENT 'system=系统任务 custom=自定义任务',
  `exec_type` varchar(20) NOT NULL DEFAULT 'url' COMMENT 'url/php/shell/python',
  `command` text COMMENT 'URL 或 PHP/Shell/Python 代码或脚本路径',
  `schedule` varchar(50) NOT NULL DEFAULT '' COMMENT 'cron 表达式或 every N minute/hour/day',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1启用 0停用',
  `last_run` int(11) NOT NULL DEFAULT '0' COMMENT '上次执行时间',
  `last_result` varchar(500) NOT NULL DEFAULT '' COMMENT '上次执行结果摘要',
  `next_run` int(11) NOT NULL DEFAULT '0' COMMENT '预计下次执行时间',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='计划任务';

-- 违规词库表（违规词检测模块）（如不存在则创建）
CREATE TABLE IF NOT EXISTS `yun_sensitive_word` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `word` varchar(100) NOT NULL DEFAULT '' COMMENT '违规词',
  `level` tinyint(2) NOT NULL DEFAULT '1' COMMENT '1低 2中 3高',
  `category` varchar(50) NOT NULL DEFAULT '' COMMENT '分类',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `word` (`word`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='违规词库';
