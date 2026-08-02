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
