-- ZhiCms 社区与评论功能升级脚本
-- 说明：{pre} 会被自动替换为数据库前缀（默认 yun_）
-- 功能：文章评论/点赞增强、微社区（板块/小组/帖子/回复）

-- ====================================================================
-- 一、yun_comment 表升级：支持无限嵌套回复、未登录评论、点赞、审核、置顶
-- ====================================================================
ALTER TABLE `{pre}comment`
  ADD COLUMN `pid` int(11) NOT NULL DEFAULT 0 COMMENT '父评论ID（0为顶级）' AFTER `id`,
  ADD COLUMN `poster` varchar(50) NOT NULL DEFAULT '' COMMENT '评论人昵称（未登录时用）' AFTER `uid`,
  ADD COLUMN `mail` varchar(60) NOT NULL DEFAULT '' COMMENT '邮箱（未登录时登记）' AFTER `poster`,
  ADD COLUMN `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像URL' AFTER `mail`,
  ADD COLUMN `hide` char(1) NOT NULL DEFAULT 'n' COMMENT '是否隐藏 y/n（审核）' AFTER `model`,
  ADD COLUMN `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'IP地址' AFTER `content`,
  ADD COLUMN `agent` varchar(255) NOT NULL DEFAULT '' COMMENT 'User-Agent' AFTER `ip`,
  ADD COLUMN `top` char(1) NOT NULL DEFAULT 'n' COMMENT '是否置顶 y/n' AFTER `agent`,
  ADD COLUMN `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '评论点赞数' AFTER `top`,
  ADD INDEX `idx_pid` (`pid`),
  ADD INDEX `idx_mid_model` (`mid`, `model`);

-- ====================================================================
-- 二、yun_like 表升级：增加 IP/cookie，用于未登录用户防重复点赞
-- ====================================================================
ALTER TABLE `{pre}like`
  ADD COLUMN `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'IP地址' AFTER `model`,
  ADD COLUMN `cookie` varchar(64) NOT NULL DEFAULT '' COMMENT '未登录用户cookie标识' AFTER `ip`,
  ADD INDEX `idx_fid_model` (`fid`, `model`);

-- ====================================================================
-- 三、yun_forum 表升级：增加发帖人、状态、回复数等
-- ====================================================================
ALTER TABLE `{pre}forum`
  ADD COLUMN `uid` int(11) NOT NULL DEFAULT 0 COMMENT '发帖人ID' AFTER `id`,
  ADD COLUMN `poster` varchar(50) NOT NULL DEFAULT '' COMMENT '发帖人昵称' AFTER `uid`,
  ADD COLUMN `mail` varchar(60) NOT NULL DEFAULT '' COMMENT '邮箱' AFTER `poster`,
  ADD COLUMN `view` int(11) NOT NULL DEFAULT 0 COMMENT '浏览量' AFTER `content`,
  ADD COLUMN `reply_count` int(11) NOT NULL DEFAULT 0 COMMENT '回复数' AFTER `view`,
  ADD COLUMN `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'IP地址' AFTER `reply_count`,
  ADD COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1显示/0隐藏' AFTER `ip`,
  ADD INDEX `idx_groupid` (`groupid`),
  ADD INDEX `idx_status` (`status`);

-- ====================================================================
-- 四、新增 yun_forum_reply 表：帖子回复（独立于文章评论，支持无限嵌套）
-- ====================================================================
DROP TABLE IF EXISTS `{pre}forum_reply`;
CREATE TABLE `{pre}forum_reply` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `forum_id` int(11) NOT NULL DEFAULT 0 COMMENT '帖子ID',
  `pid` int(11) NOT NULL DEFAULT 0 COMMENT '父回复ID（0为顶级）',
  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '回复人ID',
  `poster` varchar(50) NOT NULL DEFAULT '' COMMENT '回复人昵称',
  `mail` varchar(60) NOT NULL DEFAULT '' COMMENT '邮箱',
  `content` text COMMENT '回复内容',
  `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'IP地址',
  `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
  `hide` char(1) NOT NULL DEFAULT 'n' COMMENT '是否隐藏 y/n',
  `date` varchar(50) NOT NULL DEFAULT '' COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_forum_id` (`forum_id`),
  KEY `idx_pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='帖子回复表';

-- ====================================================================
-- 五、配置项：全局开关（写入 yun_config，若表不存在则创建）
-- ====================================================================
CREATE TABLE IF NOT EXISTS `{pre}config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(50) NOT NULL DEFAULT '',
  `value` text,
  `desc` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站点配置表';

INSERT INTO `{pre}config` (`key`, `value`, `desc`) VALUES
  ('comment_on', '1', '评论功能总开关 1开/0关'),
  ('forum_on', '1', '社区功能总开关 1开/0关'),
  ('comment_anonymous', '1', '允许未登录评论 1允许/0禁止'),
  ('comment_check', '0', '评论需要审核 1是/0否'),
  ('comment_interval', '60', '评论间隔秒数（防刷）')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
