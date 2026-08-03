-- ZhiCms 5.0.1 数据库升级 SQL
-- 服务器数据库: zhicms | 前缀: yun_
-- 用途：旧版本升级到当前结构时由 remote_update.php 自动执行（按分号逐条执行）
-- 注意：以下字段中 yun_article.allow_comment/featured/navid 已固化进安装文件
--       data/config/zhicms.sql，此处保留 ALTER 仅供尚未执行过的旧库升级使用。
--       若目标库已存在对应字段，执行会报错（属预期，可忽略或手动跳过）。

-- yun_article 表：新增允许评论、推荐文章、发现分类字段
ALTER TABLE `yun_article` ADD COLUMN `allow_comment` tinyint(1) NOT NULL DEFAULT '1' COMMENT '允许评论 1允许 0禁止';
ALTER TABLE `yun_article` ADD COLUMN `featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推荐文章 1推荐 0否';
ALTER TABLE `yun_article` ADD COLUMN `navid` int(11) NOT NULL DEFAULT '0' COMMENT '发现分类ID（yun_nav.id），0=未分类';

-- yun_items 表：新增规格字段
ALTER TABLE `yun_items` ADD COLUMN `spec` varchar(500) DEFAULT '' COMMENT '规格';

-- yun_forum 表：新增锁定、推荐字段
ALTER TABLE `yun_forum` ADD COLUMN `lock` int(11) DEFAULT '0' COMMENT '锁定';
ALTER TABLE `yun_forum` ADD COLUMN `featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推荐';

-- 版本号对齐：升级后将 cfg_version 更新为 5.0.1（与 data/config/version.php 一致）
INSERT INTO `yun_config` (`key`, `value`, `desc`) VALUES ('cfg_version', '{"version":"5.0.1"}', '版本号')
ON DUPLICATE KEY UPDATE `value` = '{"version":"5.0.1"}';

-- 后台操作日志表（系统日志模块）
CREATE TABLE IF NOT EXISTS `yun_admin_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL DEFAULT '' COMMENT '操作类型：login/setting/cache/plugin/user/article/forum/items/page 等',
  `content` varchar(500) NOT NULL DEFAULT '' COMMENT '操作描述',
  `operator` varchar(100) NOT NULL DEFAULT '' COMMENT '操作人（后台账号）',
  `ip` varchar(45) NOT NULL DEFAULT '' COMMENT '操作IP',
  `url` varchar(500) NOT NULL DEFAULT '' COMMENT '请求地址',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '操作时间戳',
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台操作日志';

-- 计划任务表（计划任务模块）
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

-- 违规词库表（违规词检测模块）
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

-- ============================================================
-- ZhiCms 5.0.2 升级：用户中心 + 用户系统字段扩展
-- 目标：旧库升级到 5.0.2（yun_user 新增用户系统字段、用户开关、收藏索引）
-- 注意：若字段已存在会报错（SQLSTATE 42S21/42S11），属预期可忽略
-- ============================================================

-- 1. yun_user 表：新增用户系统字段（用户中心/注册/资料/密码修改依赖）
ALTER TABLE `yun_user` ADD COLUMN `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱' AFTER `mobile`;
ALTER TABLE `yun_user` ADD COLUMN `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像URL' AFTER `email`;
ALTER TABLE `yun_user` ADD COLUMN `reg_time` varchar(20) NOT NULL DEFAULT '' COMMENT '注册时间' AFTER `avatar`;
ALTER TABLE `yun_user` ADD COLUMN `reg_ip` varchar(64) NOT NULL DEFAULT '' COMMENT '注册IP' AFTER `reg_time`;
ALTER TABLE `yun_user` ADD COLUMN `login_ip` varchar(64) NOT NULL DEFAULT '' COMMENT '最后登录IP' AFTER `reg_ip`;
ALTER TABLE `yun_user` ADD COLUMN `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1正常 0禁用' AFTER `login_ip`;

-- 2. yun_user 表：手机号唯一索引（注册查重、登录识别）
ALTER TABLE `yun_user` ADD UNIQUE KEY `uk_mobile` (`mobile`);

-- 3. yun_config 表：用户功能开关默认值（后台「互动设置」读取）
INSERT INTO `yun_config` (`key`, `value`, `desc`) VALUES
('user_reg_captcha', '1', '注册开启图形验证码 1开/0关'),
('user_email_verify', '0', '注册需要邮箱验证 1是/0否（当前仅预留）'),
('user_show_login', '1', '前台显示用户登录/注册入口 1显示/0隐藏')
ON DUPLICATE KEY UPDATE `desc` = VALUES(`desc`);

-- 4. 版本号对齐：升级后将 cfg_version 更新为 5.0.2
INSERT INTO `yun_config` (`key`, `value`, `desc`) VALUES ('cfg_version', '{"version":"5.0.2"}', '版本号')
ON DUPLICATE KEY UPDATE `value` = '{"version":"5.0.2"}';
