SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `yun_article`;
CREATE TABLE `yun_article` (
  `id` int NOT NULL AUTO_INCREMENT,
  `goodsId` varchar(100) DEFAULT '',
  `itemLink` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `cid` int NOT NULL DEFAULT 0,
  `mainPic` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `dec` varchar(255) DEFAULT NULL,
  `view` int DEFAULT 0,
  `like` int DEFAULT 0,
  `lock` int DEFAULT 0,
  `status` int DEFAULT 0,
  `author` varchar(255) DEFAULT '',
  `laiyuan` varchar(255) DEFAULT '',
  `surl` varchar(255) DEFAULT '',
  `sort` int DEFAULT 0,
  `hits` int DEFAULT 0,
  `bili` int DEFAULT 0,
  `sheng` varchar(255) DEFAULT '',
  `couponEndTime` varchar(255) NOT NULL DEFAULT '',
  `date` varchar(255) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `goodsId` (`goodsId`),
  KEY `title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_items`;
CREATE TABLE `yun_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `laiyuan` int NOT NULL DEFAULT 0,
  `goodsId` varchar(100) DEFAULT '',
  `itemLink` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `cid` int NOT NULL DEFAULT 0,
  `mainPic` varchar(255) DEFAULT NULL,
  `originalPrice` decimal(10,2) NOT NULL DEFAULT 0.00,
  `actualPrice` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discounts` decimal(10,2) NOT NULL DEFAULT 0.00,
  `commissionRate` decimal(4,2) DEFAULT 0.00,
  `couponTotalNum` int DEFAULT 0,
  `couponReceiveNum` int DEFAULT 0,
  `couponEndTime` varchar(255) DEFAULT '0',
  `couponStartTime` varchar(255) DEFAULT '0',
  `couponConditions` varchar(255) DEFAULT NULL,
  `couponPrice` int DEFAULT 0,
  `monthSales` int(11) NOT NULL DEFAULT 0,
  `shopType` varchar(20) DEFAULT NULL,
  `shopName` varchar(255) DEFAULT NULL COMMENT '店铺名称',
  `shopId` bigint(20) DEFAULT 0 COMMENT '店铺ID',
  `commissionType` tinyint DEFAULT 0 COMMENT '佣金类型 1通用 2营销计划 3定向高佣',
  `dtitle` text COMMENT '短标题/推荐语',
  `freeshipRemoteDistrict` tinyint DEFAULT 0 COMMENT '偏远地区包邮 0否 1是',
  `yunfeixian` tinyint DEFAULT 0 COMMENT '运费险 0否 1是',
  `choice` tinyint DEFAULT 0 COMMENT '精选商品 0否 1是',
  `del` int NOT NULL DEFAULT 0,
  `top` tinyint DEFAULT 0,
  `top_stime` varchar(255) DEFAULT '0',
  `top_etime` varchar(255) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `shopType` (`shopType`),
  KEY `cid` (`cid`),
  KEY `title` (`title`),
  KEY `originalPrice` (`originalPrice`),
  KEY `actualPrice` (`actualPrice`),
  KEY `del` (`del`),
  KEY `top` (`top`),
  KEY `top_stime` (`top_stime`),
  KEY `top_etime` (`top_etime`),
  KEY `laiyuan` (`laiyuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_huan`;
CREATE TABLE `yun_huan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `pic` varchar(200) NOT NULL,
  `link` varchar(200) NOT NULL,
  `file` int NOT NULL DEFAULT 0,
  `type` int NOT NULL DEFAULT 0 COMMENT '0pc 1移动',
  `date` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `yun_huan` VALUES (4, '3', 'upload/huan/20180717182237_100.png', 'http://www.umaijie.com/page-2.html', 0, 0, '2018-05-29 10:45:43');
INSERT INTO `yun_huan` VALUES (6, '测试', 'upload/huan/20180716113103_746.jpg', 'http://www.umaijie.com', 0, 1, '2018-07-16 11:31:12');

DROP TABLE IF EXISTS `yun_manage`;
CREATE TABLE `yun_manage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL,
  `password` varchar(35) NOT NULL,
  `pic` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `yun_manage` VALUES (1, 'admin', '9daef705a0bd551a87be632eb3fd84c5', 'upload/manageuser/20180507103202_760.jpg');

DROP TABLE IF EXISTS `yun_page`;
CREATE TABLE `yun_page` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `keywords` varchar(300) NOT NULL DEFAULT '',
  `dec` varchar(500) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `date` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `yun_page` VALUES (1, '关于我们', '关于我们', '关于我们', 'ZhiCms是一家行业领先的创意礼物导购网站，创立于2012年。我们从淘宝、天猫、京东、亚马逊等各大电商精选品质好物，只为解决用户选礼难、送礼难的困扰。<br />\r\n<br />\r\n<p>\r\n\t我们努力寻求将互联网技术与专家送礼经验完美融合，致力于为用户提供最全的礼物品类以及最多的送礼场景参考，帮助用户从繁重的甄选礼物的过程中解脱出来，让您快速轻松地找到满意的礼物。送对的礼物，让礼物传递爱和温情。\r\n</p>\r\n<p>\r\n\t<br />\r\n</p>', '2018-05-07 20:49:31');
INSERT INTO `yun_page` VALUES (2, '商家合作', '1', '2', '商家须知<br />\r\n我站是淘宝导购网站，目前只支持淘宝、天猫商家合作（合作方式：阿里妈妈-淘宝联盟），暂不支持京东、亚马逊等其它电商平台合作。工厂和批发商朋友请不要联系我们，我们不要货源。<br />\r\n<br />\r\n如何推广<br />\r\n请联系我站合作专员，添加QQ好友后主动将店铺链接发给合作专员，专员会根据贵店商品的品质、销量、用户评价、个性化等因素来判定是否与您合作。<br />\r\n<br />\r\n首先，以下情况无法合作<br />\r\n1、新店<br />\r\n2、销量低（单品月销量低于50）、评分低、口碑差、转化差的店铺<br />\r\n3、存在刷单、刷好评等虚假行为<br />\r\n4、所售商品缺少礼品属性<br />\r\n5、销售假冒伪劣产品<br />\r\n6、达成合作意向后，违背诚信原则，佣金率随意改动，我站将即刻终止合作<br />\r\n<br />\r\n如果贵店达到合作门槛，我们会要求您单独设置一个高佣金定向推广计划（佣金比例高于同行其他网店，具体和合作专员商定）。然后，我站编辑会将贵店商品上架到网站和APP上进行推广，推广力度大小与佣金率高低有直接关系。<br />', '2018-05-07 20:49:46');
INSERT INTO `yun_page` VALUES (3, '如何购买', '1', '2', '首先请知悉ZhiCms是一家导购网站，网站上的商品均来源于淘宝、天猫等电商平台，我们不直接销售商品。<br />\r\n<p>\r\n\t在优买街礼物上找到您想要购买的商品，在商品详情页点击“去淘宝购买”按钮（如下图），进入淘宝后请联系商家购买。如您有购物疑问，请联系商家旺旺咨询。\r\n</p>\r\n<p>\r\n\t<img src=\"upload/page/20180530111251_764.jpg\" alt=\"\" /> \r\n</p>\r\n<p>\r\n\t<br />\r\n</p>\r\n<p>\r\n\t常见问题<br />\r\n1、我不会网购怎么办？<br />\r\n如果您没有网购经验，可以将我们网站的链接地址发给您有网购经验的朋友，让您朋友来帮您购买。\r\n</p>\r\n<p>\r\n\t3、购买后几天能送到？<br />\r\n购买礼物快慢取决于以下几个因素：<br />\r\nA、商品是否需要定制：个性化定制的商品，比如刻字、定制照片、手工定制等商品，可能需要花费一定的制作时间才能发货，具体时间请联系销售该商品的商家咨询；<br />\r\nB、快递时间：商家和您的所在位置越近送货越快；如果选用顺丰快递，一般1-2日就能送达，时效好于一般快递公司。<br />\r\n<br />\r\n4、为什么我喜欢一件商品后，在“我的喜欢”页面还是空的？<br />\r\n有可能您的浏览器禁用了cookies功能，导致您喜欢过一件商品后而无法将该条数据存入cookies。如果您想正常使用此项功能，建议开启浏览器的cookies功能。\r\n</p>', '2018-05-07 20:49:56');
INSERT INTO `yun_page` VALUES (4, '版权声明', '1', '1', '优买街礼物网 程序后端研发 前端UI设计 均为优买街原创，任何人都可以抄袭，一个破UI 算得了啥！ 和命踏马一样金贵！', '2018-05-30 09:00:58');
INSERT INTO `yun_page` VALUES (5, '用户协议', '1', '1', '待编辑', '2018-05-30 09:10:56');
INSERT INTO `yun_page` VALUES (6, '意见反馈', '1', '1', '在前行的路上，离不开您对我们的关注和支持，我们尚在成长期，还有很多的不足，因此，特别希望您给予我们宝贵的建议和意见。无论是反馈一个小小的bug，或是功能改进的想法，亦或是对我们产品和服务的某些方面有不满意的地方，都请发送邮件至team#zhicms.vip（发邮件时请将#换成@)，我们会用最真诚的态度来回应您的声音。<br />\r\n<br />\r\n如果您提供的建设性想法被我们采纳，您将有机会获得我们赠送的精美小礼品或微信红包。<br />', '2018-05-30 09:13:00');
INSERT INTO `yun_page` VALUES (7, '友情链接', '1', '1', '<!-- /title -->\r\n<div class=\"title fz18\">\r\n\t<h3 class=\"tith\">\r\n\t\t友链交换\r\n\t</h3>\r\n</div>\r\n<div class=\"cont mb30\">\r\n\t<p>\r\n\t\t1、首页要求：百度权重&gt;=5；Alexa世界排名10万以内；收录大于10000；已交换友链不超过50个；知名网站、电商类网站为佳。<br />\r\n2、内页要求：百度权重&gt;=3；无搜索引擎惩罚记录；收录正常。\r\n\t</p>\r\n\t<p>\r\n\t\t交换请发送邮件至team#zhicms.vip（发邮件时请将#换成@)，邮件内请写明网站名、网址、百度权重值、联系人QQ号。\r\n\t</p>\r\n</div>', '2018-05-30 09:13:20');

DROP TABLE IF EXISTS `yun_plug`;
CREATE TABLE `yun_plug` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(50) NOT NULL DEFAULT '' COMMENT '插件别名/目录名（唯一）',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '插件显示名称',
  `version` varchar(20) NOT NULL DEFAULT '' COMMENT '插件版本',
  `author` varchar(50) NOT NULL DEFAULT '' COMMENT '作者',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0停用 1启用',
  `installed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0未安装 1已安装',
  `config` text COMMENT '插件设置（JSON）',
  `addtime` int(11) NOT NULL DEFAULT 0 COMMENT '安装时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件注册表';


DROP TABLE IF EXISTS `yun_plug_h5_huan`;
CREATE TABLE `yun_plug_h5_huan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `link` varchar(500) NOT NULL DEFAULT '',
  `pic` varchar(300) NOT NULL DEFAULT '',
  `file` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_link`;
CREATE TABLE `yun_link` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `link` varchar(300) NOT NULL DEFAULT '',
  `px` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_user`;
CREATE TABLE `yun_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `vest` tinyint DEFAULT 1 COMMENT '1普通用户 2VIP用户',
  `lock` tinyint DEFAULT 0 COMMENT '0正常 1冻结',
  `date` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_comment`;
CREATE TABLE `yun_comment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int DEFAULT 0,
  `mid` int DEFAULT 0,
  `model` tinyint DEFAULT 1,
  `content` text,
  `date` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_ad`;
CREATE TABLE `yun_ad` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `class` varchar(50) DEFAULT NULL,
  `img` varchar(255) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `sort` int DEFAULT 0,
  `status` tinyint DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_mall`;
CREATE TABLE `yun_mall` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `img` varchar(255) DEFAULT NULL,
  `sort` int DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_group`;
CREATE TABLE `yun_group` (
  `id` int NOT NULL AUTO_INCREMENT,
  `groupname` varchar(100) DEFAULT NULL,
  `px` int DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_union`;
CREATE TABLE `yun_union` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `code` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_bankuai`;
CREATE TABLE `yun_bankuai` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `px` int DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 注：yun_items 已重建为“电商选品库”商品表（见下方原 yun_goods 定义，已重命名为 yun_items）。
-- 原淘口令(taoToken)功能已废弃，相关字段(fid/url/itemsurl/wa/taoToken/model)不再使用。


DROP TABLE IF EXISTS `yun_duomai`;
CREATE TABLE `yun_duomai` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ads_id` varchar(50) DEFAULT NULL,
  `ads_name` varchar(255) DEFAULT NULL,
  `channel` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `applay_mode` varchar(50) DEFAULT NULL,
  `hide` varchar(20) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `cate_name` varchar(100) DEFAULT NULL,
  `ads_endtime` varchar(50) DEFAULT NULL,
  `ads_commission` varchar(50) DEFAULT NULL,
  `site_url` varchar(500) DEFAULT NULL,
  `site_logo` varchar(255) DEFAULT NULL,
  `site_description` text,
  `adser` varchar(100) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_nav`;
CREATE TABLE `yun_nav` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `pic` varchar(255) NOT NULL DEFAULT '',
  `keywords` varchar(255) NOT NULL DEFAULT '',
  `dec` varchar(255) NOT NULL DEFAULT '',
  `px` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_forum`;
CREATE TABLE `yun_forum` (
  `id` int NOT NULL AUTO_INCREMENT,
  `groupid` int NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT '',
  `pic` varchar(255) NOT NULL DEFAULT '',
  `content` longtext,
  `like` int NOT NULL DEFAULT 0,
  `date` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `groupid` (`groupid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_like`;
CREATE TABLE `yun_like` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fid` int NOT NULL DEFAULT 0,
  `uid` int NOT NULL DEFAULT 0,
  `model` varchar(50) NOT NULL DEFAULT '',
  `date` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `fid` (`fid`),
  KEY `uid` (`uid`),
  KEY `model` (`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_home_mall`;
CREATE TABLE `yun_home_mall` (
  `id` int NOT NULL AUTO_INCREMENT,
  `union` int NOT NULL DEFAULT 0,
  `view` int NOT NULL DEFAULT 0,
  `px` int NOT NULL DEFAULT 0,
  `link` varchar(500) NOT NULL DEFAULT '',
  `pic` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `union` (`union`),
  KEY `view` (`view`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yun_skuitems`;
CREATE TABLE `yun_skuitems` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `pic` varchar(255) NOT NULL DEFAULT '',
  `shop` varchar(100) NOT NULL DEFAULT '',
  `time` varchar(50) NOT NULL DEFAULT '',
  `country` varchar(10) NOT NULL DEFAULT '0',
  `type` varchar(50) NOT NULL DEFAULT '',
  `body` longtext,
  `link` varchar(500) NOT NULL DEFAULT '',
  `date` varchar(50) NOT NULL DEFAULT '',
  `website` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `link` (`link`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================
-- 合并升级脚本: upgrade_community.sql
-- ============================================
-- ZhiCms 社区与评论功能升级脚本
-- 说明：yun_ 会被自动替换为数据库前缀（默认 yun_）
-- 功能：文章评论/点赞增强、微社区（板块/小组/帖子/回复）

-- ====================================================================
-- 一、yun_comment 表升级：支持无限嵌套回复、未登录评论、点赞、审核、置顶
-- ====================================================================
ALTER TABLE `yun_comment`
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
ALTER TABLE `yun_like`
  ADD COLUMN `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'IP地址' AFTER `model`,
  ADD COLUMN `cookie` varchar(64) NOT NULL DEFAULT '' COMMENT '未登录用户cookie标识' AFTER `ip`,
  ADD INDEX `idx_fid_model` (`fid`, `model`);

-- ====================================================================
-- 三、yun_forum 表升级：增加发帖人、状态、回复数等
-- ====================================================================
ALTER TABLE `yun_forum`
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
DROP TABLE IF EXISTS `yun_forum_reply`;
CREATE TABLE `yun_forum_reply` (
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
CREATE TABLE IF NOT EXISTS `yun_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(50) NOT NULL DEFAULT '',
  `value` text,
  `desc` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站点配置表';

INSERT INTO `yun_config` (`key`, `value`, `desc`) VALUES
  ('comment_on', '1', '评论功能总开关 1开/0关'),
  ('forum_on', '1', '社区功能总开关 1开/0关'),
  ('comment_anonymous', '1', '允许未登录评论 1允许/0禁止'),
  ('comment_check', '0', '评论需要审核 1是/0否'),
  ('comment_interval', '60', '评论间隔秒数（防刷）')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);


-- ============================================
-- 合并升级脚本: upgrade_community_v2.sql
-- ============================================
-- ============================================
-- 微社区 v2 升级脚本
-- - 帖子微博化（标题可选、多图、商品卡片）
-- - 小组归属板块
-- - 新增微社区配置项
-- ============================================

-- 1. yun_forum 升级
ALTER TABLE `yun_forum` ADD COLUMN `images` TEXT NULL COMMENT '帖子图片JSON数组' AFTER `pic`;
ALTER TABLE `yun_forum` ADD COLUMN `goods_data` TEXT NULL COMMENT '商品卡片数据JSON' AFTER `images`;
ALTER TABLE `yun_forum` ADD COLUMN `bankuai_id` INT(11) NOT NULL DEFAULT 0 COMMENT '所属板块ID（0=综合）' AFTER `groupid`;
ALTER TABLE `yun_forum` MODIFY COLUMN `title` VARCHAR(255) NULL COMMENT '标题（可选，不填则用正文摘要）';

-- 2. yun_group 升级（小组归属板块 + 描述 + 图标 + 成员数）
ALTER TABLE `yun_group` ADD COLUMN `bankuai_id` INT(11) NOT NULL DEFAULT 0 COMMENT '所属板块ID（0=综合）' AFTER `groupname`;
ALTER TABLE `yun_group` ADD COLUMN `icon` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '小组图标URL';
ALTER TABLE `yun_group` ADD COLUMN `desc` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '小组简介';
ALTER TABLE `yun_group` ADD COLUMN `member_count` INT(11) NOT NULL DEFAULT 0 COMMENT '小组成员数';

-- 3. 板块默认数据（如果 yun_bankuai 为空则插入示例）
INSERT INTO `yun_bankuai` (`name`, `px`) SELECT * FROM (SELECT '好物推荐', 1) AS tmp WHERE NOT EXISTS (SELECT 1 FROM `yun_bankuai` LIMIT 1);
INSERT INTO `yun_bankuai` (`name`, `px`) SELECT '晒单分享', 2 WHERE NOT EXISTS (SELECT 1 FROM `yun_bankuai` WHERE `name`='晒单分享');
INSERT INTO `yun_bankuai` (`name`, `px`) SELECT '生活日常', 3 WHERE NOT EXISTS (SELECT 1 FROM `yun_bankuai` WHERE `name`='生活日常');
INSERT INTO `yun_bankuai` (`name`, `px`) SELECT '福利爆料', 4 WHERE NOT EXISTS (SELECT 1 FROM `yun_bankuai` WHERE `name`='福利爆料');

-- 4. 新增微社区配置项
INSERT INTO `yun_config` (`key`, `value`, `desc`) VALUES
  ('forum_max_images', '9', '微社区单帖最大图片数')
  ON DUPLICATE KEY UPDATE `desc` = VALUES(`desc`);
INSERT INTO `yun_config` (`key`, `value`, `desc`) VALUES
  ('forum_max_chars', '300', '微社区单帖最大字数')
  ON DUPLICATE KEY UPDATE `desc` = VALUES(`desc`);
INSERT INTO `yun_config` (`key`, `value`, `desc`) VALUES
  ('forum_link_card', '1', '微社区电商链接自动转链 1开/0关')
  ON DUPLICATE KEY UPDATE `desc` = VALUES(`desc`);


-- ============================================
-- 合并升级脚本: upgrade_plugin.sql
-- ============================================
-- ZhiCms 标准化插件系统：插件注册表（yun_plug）
-- 说明：yun_ 会被 PluginManager 自动替换为数据库前缀（默认 yun_）
-- 该表用于替代旧版 gift 插件的注册表，字段已重新定义

DROP TABLE IF EXISTS `yun_plug`;

CREATE TABLE `yun_plug` (
  `id`        int(11) unsigned NOT NULL AUTO_INCREMENT,
  `alias`     varchar(50)  NOT NULL DEFAULT '' COMMENT '插件别名/目录名（唯一）',
  `name`      varchar(100) NOT NULL DEFAULT '' COMMENT '插件显示名称',
  `version`   varchar(20)  NOT NULL DEFAULT '' COMMENT '插件版本',
  `author`    varchar(50)  NOT NULL DEFAULT '' COMMENT '作者',
  `status`    tinyint(1)   NOT NULL DEFAULT 0 COMMENT '0停用 1启用',
  `installed` tinyint(1)   NOT NULL DEFAULT 0 COMMENT '0未安装 1已安装',
  `config`    text         NULL                COMMENT '插件设置（JSON）',
  `addtime`   int(11)      NOT NULL DEFAULT 0 COMMENT '安装时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件注册表';


SET FOREIGN_KEY_CHECKS = 1;
