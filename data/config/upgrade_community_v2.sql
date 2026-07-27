-- ============================================
-- 微社区 v2 升级脚本
-- - 帖子微博化（标题可选、多图、商品卡片）
-- - 小组归属板块
-- - 新增微社区配置项
-- ============================================

-- 1. yun_forum 升级
ALTER TABLE `{pre}forum` ADD COLUMN `images` TEXT NULL COMMENT '帖子图片JSON数组' AFTER `pic`;
ALTER TABLE `{pre}forum` ADD COLUMN `goods_data` TEXT NULL COMMENT '商品卡片数据JSON' AFTER `images`;
ALTER TABLE `{pre}forum` ADD COLUMN `bankuai_id` INT(11) NOT NULL DEFAULT 0 COMMENT '所属板块ID（0=综合）' AFTER `groupid`;
ALTER TABLE `{pre}forum` MODIFY COLUMN `title` VARCHAR(255) NULL COMMENT '标题（可选，不填则用正文摘要）';

-- 2. yun_group 升级（小组归属板块 + 描述 + 图标 + 成员数）
ALTER TABLE `{pre}group` ADD COLUMN `bankuai_id` INT(11) NOT NULL DEFAULT 0 COMMENT '所属板块ID（0=综合）' AFTER `groupname`;
ALTER TABLE `{pre}group` ADD COLUMN `icon` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '小组图标URL';
ALTER TABLE `{pre}group` ADD COLUMN `desc` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '小组简介';
ALTER TABLE `{pre}group` ADD COLUMN `member_count` INT(11) NOT NULL DEFAULT 0 COMMENT '小组成员数';

-- 3. 板块默认数据（如果 yun_bankuai 为空则插入示例）
INSERT INTO `{pre}bankuai` (`name`, `px`) SELECT * FROM (SELECT '好物推荐', 1) AS tmp WHERE NOT EXISTS (SELECT 1 FROM `{pre}bankuai` LIMIT 1);
INSERT INTO `{pre}bankuai` (`name`, `px`) SELECT '晒单分享', 2 WHERE NOT EXISTS (SELECT 1 FROM `{pre}bankuai` WHERE `name`='晒单分享');
INSERT INTO `{pre}bankuai` (`name`, `px`) SELECT '生活日常', 3 WHERE NOT EXISTS (SELECT 1 FROM `{pre}bankuai` WHERE `name`='生活日常');
INSERT INTO `{pre}bankuai` (`name`, `px`) SELECT '福利爆料', 4 WHERE NOT EXISTS (SELECT 1 FROM `{pre}bankuai` WHERE `name`='福利爆料');

-- 4. 新增微社区配置项
INSERT INTO `{pre}config` (`key`, `value`, `desc`) VALUES
  ('forum_max_images', '9', '微社区单帖最大图片数')
  ON DUPLICATE KEY UPDATE `desc` = VALUES(`desc`);
INSERT INTO `{pre}config` (`key`, `value`, `desc`) VALUES
  ('forum_max_chars', '300', '微社区单帖最大字数')
  ON DUPLICATE KEY UPDATE `desc` = VALUES(`desc`);
INSERT INTO `{pre}config` (`key`, `value`, `desc`) VALUES
  ('forum_link_card', '1', '微社区电商链接自动转链 1开/0关')
  ON DUPLICATE KEY UPDATE `desc` = VALUES(`desc`);
