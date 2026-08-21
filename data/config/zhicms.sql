-- ============================================
-- ZhiCms 5.0.2 数据库安装脚本
-- 兼容 MySQL 5.7 ~ 8.0
-- 表前缀 __PREFIX__ 将在安装时自动替换为用户选择的表前缀
-- 默认管理员: admin / admin88
-- 更新时间: 2026-08-03
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

--
-- ------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `__PREFIX__ad`
--

DROP TABLE IF EXISTS `__PREFIX__ad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__ad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `class` varchar(50) DEFAULT NULL,
  `img` varchar(255) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `sort` int(11) DEFAULT '0',
  `status` tinyint(4) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__admin_log`
--

DROP TABLE IF EXISTS `__PREFIX__admin_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__admin_log` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__article`
--

DROP TABLE IF EXISTS `__PREFIX__article`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goodsId` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '商品ID',
  `itemLink` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `cid` int(11) NOT NULL DEFAULT '0',
  `navid` int(11) NOT NULL DEFAULT '0' COMMENT '发现分类(nav)ID',
  `mainPic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dec` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `view` int(11) DEFAULT '0',
  `like` int(11) DEFAULT '0',
  `lock` int(11) DEFAULT '0',
  `status` int(11) DEFAULT '0',
  `allow_comment` tinyint(1) NOT NULL DEFAULT '1' COMMENT '允许评论 1允许 0禁止',
  `featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推荐文章 1推荐 0否',
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '作者昵称（默认调用管理员昵称）',
  `author_pic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '作者头像（默认调用管理员头像）',
  `laiyuan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `surl` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '文章来源链接',
  `sort` int(11) DEFAULT '0',
  `hits` int(11) DEFAULT '0',
  `bili` int(11) DEFAULT '0',
  `sheng` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `couponEndTime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `goodsId` (`goodsId`),
  KEY `title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__bankuai`
--

DROP TABLE IF EXISTS `__PREFIX__bankuai`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__bankuai` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `px` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__comment`
--

DROP TABLE IF EXISTS `__PREFIX__comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '父评论ID（0为顶级）',
  `uid` int(11) DEFAULT '0',
  `poster` varchar(50) NOT NULL DEFAULT '' COMMENT '评论人昵称（未登录时用）',
  `mail` varchar(60) NOT NULL DEFAULT '' COMMENT '邮箱（未登录时登记）',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像URL',
  `mid` int(11) DEFAULT '0',
  `model` tinyint(4) DEFAULT '1',
  `hide` char(1) NOT NULL DEFAULT 'n' COMMENT '是否隐藏 y/n（审核）',
  `content` text,
  `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'IP地址',
  `agent` varchar(255) NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `top` char(1) NOT NULL DEFAULT 'n' COMMENT '是否置顶 y/n',
  `like_count` int(11) NOT NULL DEFAULT '0' COMMENT '评论点赞数',
  `date` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pid` (`pid`),
  KEY `idx_mid_model` (`mid`,`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__config`
--

DROP TABLE IF EXISTS `__PREFIX__config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(50) NOT NULL DEFAULT '',
  `value` text,
  `desc` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站点配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__duomai`
--

DROP TABLE IF EXISTS `__PREFIX__duomai`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__duomai` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__forum`
--

DROP TABLE IF EXISTS `__PREFIX__forum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__forum` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '发帖人ID',
  `poster` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '发帖人昵称',
  `mail` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮箱',
  `groupid` int(11) NOT NULL DEFAULT '0',
  `bankuai_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属板块ID（0=综合）',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '标题（可选，不填则用正文摘要）',
  `pic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `images` text COLLATE utf8mb4_unicode_ci COMMENT '帖子图片JSON数组',
  `goods_data` text COLLATE utf8mb4_unicode_ci COMMENT '商品卡片数据JSON',
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `view` int(11) NOT NULL DEFAULT '0' COMMENT '浏览量',
  `reply_count` int(11) NOT NULL DEFAULT '0' COMMENT '回复数',
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'IP地址',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1显示/0隐藏',
  `like` int(11) NOT NULL DEFAULT '0',
  `date` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lock` int(11) DEFAULT '0' COMMENT '锁定',
  `featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推荐',
  PRIMARY KEY (`id`),
  KEY `groupid` (`groupid`),
  KEY `idx_groupid` (`groupid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__forum_reply`
--

DROP TABLE IF EXISTS `__PREFIX__forum_reply`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__forum_reply` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `forum_id` int(11) NOT NULL DEFAULT '0' COMMENT '帖子ID',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '父回复ID（0为顶级）',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '回复人ID',
  `poster` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '回复人昵称',
  `mail` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邮箱',
  `content` text COLLATE utf8mb4_unicode_ci COMMENT '回复内容',
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'IP地址',
  `like_count` int(11) NOT NULL DEFAULT '0' COMMENT '点赞数',
  `hide` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'n' COMMENT '是否隐藏 y/n',
  `date` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_forum_id` (`forum_id`),
  KEY `idx_pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='帖子回复表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__group`
--

DROP TABLE IF EXISTS `__PREFIX__group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `groupname` varchar(100) DEFAULT NULL,
  `bankuai_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属板块ID（0=综合）',
  `px` int(11) DEFAULT '0',
  `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '小组图标URL',
  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '小组简介',
  `member_count` int(11) NOT NULL DEFAULT '0' COMMENT '小组成员数',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__home_mall`
--

DROP TABLE IF EXISTS `__PREFIX__home_mall`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__home_mall` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `union` int(11) NOT NULL DEFAULT '0',
  `view` int(11) NOT NULL DEFAULT '0',
  `px` int(11) NOT NULL DEFAULT '0',
  `link` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `union` (`union`),
  KEY `view` (`view`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__huan`
--

DROP TABLE IF EXISTS `__PREFIX__huan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__huan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pic` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` int(11) NOT NULL DEFAULT '0',
  `type` int(11) NOT NULL DEFAULT '0' COMMENT '0pc 1移动',
  `date` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `px` int(11) NOT NULL DEFAULT '0' COMMENT '排序，越小越靠前',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__items`
--

DROP TABLE IF EXISTS `__PREFIX__items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `laiyuan` int(11) NOT NULL DEFAULT '0' COMMENT '来源平台 1淘宝/天猫 2拼多多 3唯品会 4京东',
  `item_from` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '来源标记 taobao/jd/pdd/vip',
  `goodsId` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '商品ID',
  `goodsSign` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '商品标识',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '商品标题',
  `dtitle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '营销短标题',
  `content` longtext COLLATE utf8mb4_unicode_ci COMMENT '商品描述',
  `itemLink` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '商品链接',
  `mainPic` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '主图',
  `marketingMainPic` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '营销主图',
  `originalPrice` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '原价',
  `actualPrice` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '券后价',
  `discounts` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '折扣',
  `couponPrice` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '券额',
  `couponLink` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '领券链接',
  `couponStartTime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '券开始时间',
  `couponEndTime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '券结束时间',
  `couponConditions` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '券使用条件',
  `couponTotalNum` int(11) NOT NULL DEFAULT '0' COMMENT '券总量',
  `couponReceiveNum` int(11) NOT NULL DEFAULT '0' COMMENT '券领取量',
  `couponRemainCount` int(11) NOT NULL DEFAULT '0' COMMENT '券剩余量',
  `couponId` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '券ID',
  `commissionRate` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '佣金比例',
  `commissionType` tinyint(4) NOT NULL DEFAULT '0' COMMENT '佣金类型 1通用 2营销 3定向',
  `monthSales` int(11) NOT NULL DEFAULT '0' COMMENT '月销量',
  `twoHoursSales` int(11) NOT NULL DEFAULT '0' COMMENT '两小时销量',
  `dailySales` int(11) NOT NULL DEFAULT '0' COMMENT '日销量',
  `shopType` tinyint(4) NOT NULL DEFAULT '0' COMMENT '店铺类型 1天猫 0淘宝',
  `shopName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '店铺名称',
  `shopId` bigint(20) NOT NULL DEFAULT '0' COMMENT '卖家ID',
  `shopLevel` int(11) NOT NULL DEFAULT '0' COMMENT '店铺等级',
  `shopLogo` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '店铺LOGO',
  `cid` int(11) NOT NULL DEFAULT '0' COMMENT '商品分类',
  `subcid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '子分类',
  `tbcid` int(11) NOT NULL DEFAULT '0' COMMENT '淘宝分类ID',
  `brand` int(11) NOT NULL DEFAULT '0' COMMENT '品牌',
  `brandId` bigint(20) NOT NULL DEFAULT '0' COMMENT '品牌ID(大淘客返回为大数值，需用bigint)',
  `brandName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '品牌名称',
  `activityType` int(11) NOT NULL DEFAULT '0' COMMENT '活动类型',
  `activityStartTime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '活动开始',
  `activityEndTime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '活动结束',
  `activityName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '活动名称',
  `activityId` int(11) NOT NULL DEFAULT '0' COMMENT '活动ID',
  `createTime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '创建时间',
  `detailPics` longtext COLLATE utf8mb4_unicode_ci COMMENT '详情图',
  `yunfeixian` tinyint(4) NOT NULL DEFAULT '0' COMMENT '运费险/包邮',
  `freeshipRemoteDistrict` tinyint(4) NOT NULL DEFAULT '0' COMMENT '偏远地区包邮',
  `choice` tinyint(4) NOT NULL DEFAULT '0' COMMENT '精选',
  `hotPush` int(11) NOT NULL DEFAULT '0' COMMENT '热门推送',
  `goldSellers` int(11) NOT NULL DEFAULT '0' COMMENT '金牌卖家',
  `haitao` int(11) NOT NULL DEFAULT '0' COMMENT '海淘',
  `tchaoshi` int(11) NOT NULL DEFAULT '0' COMMENT '天猫超市',
  `estimateAmount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '预估返利',
  `specialText` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '特殊文案',
  `inspectedGoods` int(11) NOT NULL DEFAULT '0' COMMENT '验货',
  `dsrScore` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '描述相符',
  `dsrPercent` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '描述百分比',
  `shipScore` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '物流评分',
  `shipPercent` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '物流百分比',
  `serviceScore` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '服务评分',
  `servicePercent` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '服务百分比',
  `quanMLink` int(11) NOT NULL DEFAULT '0' COMMENT '券链接标记',
  `hzQuanOver` int(11) NOT NULL DEFAULT '0' COMMENT '券剩余标记',
  `del` int(11) NOT NULL DEFAULT '0' COMMENT '删除',
  `top` tinyint(4) NOT NULL DEFAULT '0' COMMENT '置顶',
  `top_stime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '置顶开始',
  `top_etime` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '置顶结束',
  `spec` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '规格',
  PRIMARY KEY (`id`),
  UNIQUE KEY `goodsId` (`goodsId`),
  KEY `item_from` (`item_from`),
  KEY `shopType` (`shopType`),
  KEY `cid` (`cid`),
  KEY `title` (`title`),
  KEY `originalPrice` (`originalPrice`),
  KEY `actualPrice` (`actualPrice`),
  KEY `commissionRate` (`commissionRate`),
  KEY `monthSales` (`monthSales`),
  KEY `del` (`del`),
  KEY `top` (`top`),
  KEY `idx_shopName` (`shopName`),
  KEY `idx_shopId` (`shopId`),
  KEY `idx_laiyuan` (`laiyuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='电商选品库（本地库）';
/*!40101 SET character_set_client = @saved_cs_client */;

-- 防御性兜底：将历史可能存在的 item_from 别名（dtk/taobao/tmall/tm）统一归并为 tb，
-- 与转链入口 RedirectController::jump() 白名单（tb/jd/pdd/vip）保持一致。
-- 全新安装时表为空，本语句影响 0 行；对已存在库重新导入时起到清洗作用。
UPDATE `__PREFIX__items` SET `item_from` = 'tb' WHERE `item_from` IN ('dtk', 'taobao', 'tmall', 'tm');

--
-- Table structure for table `__PREFIX__items_old_feed`
--

DROP TABLE IF EXISTS `__PREFIX__items_old_feed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__items_old_feed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fid` int(11) DEFAULT '0',
  `url` varchar(500) DEFAULT NULL,
  `itemsurl` varchar(500) DEFAULT NULL,
  `wa` text,
  `taoToken` varchar(255) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__like`
--

DROP TABLE IF EXISTS `__PREFIX__like`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fid` int(11) NOT NULL DEFAULT '0',
  `uid` int(11) NOT NULL DEFAULT '0',
  `model` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'IP地址',
  `cookie` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '未登录用户cookie标识',
  `date` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `fid` (`fid`),
  KEY `uid` (`uid`),
  KEY `model` (`model`),
  KEY `idx_fid_model` (`fid`,`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__link`
--

DROP TABLE IF EXISTS `__PREFIX__link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__link` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `px` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__mall`
--

DROP TABLE IF EXISTS `__PREFIX__mall`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__mall` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `union_id` int(11) NOT NULL DEFAULT '0' COMMENT '所属联盟模型ID（yun_union.id）',
  `link` varchar(500) DEFAULT NULL,
  `img` varchar(255) DEFAULT NULL,
  `sort` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__manage`
--

DROP TABLE IF EXISTS `__PREFIX__manage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__manage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '对外昵称（前台文章作者展示）',
  `pic` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__nav`
--

DROP TABLE IF EXISTS `__PREFIX__nav`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__nav` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `keywords` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dec` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `px` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__page`
--

DROP TABLE IF EXISTS `__PREFIX__page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__page` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias` varchar(60) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '页面别名，用于伪静态访问 page-<alias>.html（NULL 允许多条空别名）',
  `keywords` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dec` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `display` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '指定显示模板（留空用默认 page）',
  `date` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__navmenu`
--

DROP TABLE IF EXISTS `__PREFIX__navmenu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__navmenu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '菜单名称',
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '链接地址（绝对/相对/伪静态）',
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom' COMMENT '来源类型：custom自定义 cheaps优惠券 brand大牌 rank风云榜 hot热榜 forum社区 page单页',
  `type_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联ID（page类型为页面ID，其他栏目为0）',
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '父级ID，0=主导航',
  `target` tinyint(1) NOT NULL DEFAULT '0' COMMENT '新窗口打开 1是 0否',
  `hide` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1隐藏 0显示',
  `isdefault` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1系统内置不可删除（如首页）',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序（越小越靠前）',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='前台导航菜单';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__plug`
--

DROP TABLE IF EXISTS `__PREFIX__plug`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__plug` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(50) NOT NULL DEFAULT '' COMMENT '插件别名/目录名（唯一）',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '插件显示名称',
  `version` varchar(20) NOT NULL DEFAULT '' COMMENT '插件版本',
  `author` varchar(50) NOT NULL DEFAULT '' COMMENT '作者',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0停用 1启用',
  `installed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未安装 1已安装',
  `config` text COMMENT '插件设置（JSON）',
  `addtime` int(11) NOT NULL DEFAULT '0' COMMENT '安装时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件注册表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__plug_h5_huan`
--

DROP TABLE IF EXISTS `__PREFIX__plug_h5_huan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__plug_h5_huan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pic` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `file` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__skuitems`
--

DROP TABLE IF EXISTS `__PREFIX__skuitems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__skuitems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `shop` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `time` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `country` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body` longtext COLLATE utf8mb4_unicode_ci,
  `link` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `website` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `link` (`link`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__union`
--

DROP TABLE IF EXISTS `__PREFIX__union`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__union` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `code` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `__PREFIX__user`
--

DROP TABLE IF EXISTS `__PREFIX__user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像URL',
  `reg_time` varchar(20) NOT NULL DEFAULT '' COMMENT '注册时间',
  `reg_ip` varchar(64) NOT NULL DEFAULT '' COMMENT '注册IP',
  `login_ip` varchar(64) NOT NULL DEFAULT '' COMMENT '最后登录IP',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1正常 0禁用',
  `vest` tinyint(4) DEFAULT '1',
  `lock` tinyint(4) DEFAULT '0',
  `date` varchar(50) DEFAULT NULL,
  `wx_openid` varchar(64) NOT NULL DEFAULT '' COMMENT '微信开放平台 openid（小程序/微信登录）',
  `wx_unionid` varchar(64) NOT NULL DEFAULT '' COMMENT '微信开放平台 unionid（跨应用唯一标识）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mobile` (`mobile`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


--
CREATE TABLE `__PREFIX__cron_task` (
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

--
CREATE TABLE `__PREFIX__sensitive_word` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `word` varchar(100) NOT NULL DEFAULT '' COMMENT '违规词',
  `level` tinyint(2) NOT NULL DEFAULT '1' COMMENT '1低 2中 3高',
  `category` varchar(50) NOT NULL DEFAULT '' COMMENT '分类',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `word` (`word`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='违规词库';

--
-- 版本号配置（全新安装即为 5.0.2，与 data/config/version.php 对齐）
--
INSERT INTO `__PREFIX__config` (`key`, `value`, `desc`) VALUES
('cfg_version', '{"version":"5.0.2"}', '版本号');

--
-- 用户功能开关默认值（全新安装即生效，后台「互动设置」可改）
--
INSERT INTO `__PREFIX__config` (`key`, `value`, `desc`) VALUES
('user_reg_captcha', '1', '注册开启图形验证码 1开/0关'),
('user_email_verify', '0', '注册需要邮箱验证 1是/0否（当前仅预留）'),
('user_show_login', '1', '前台显示用户登录/注册入口 1显示/0隐藏'),
('forum_on', '1', '社区功能总开关 1开/0关'),
('comment_on', '1', '评论功能总开关 1开/0关'),
('comment_anonymous', '1', '允许未登录评论 1允许/0禁止'),
('comment_check', '0', '评论需要审核 1是/0否'),
('comment_interval', '60', '评论间隔秒数（防刷）'),
('FILE_UPLOAD_TYPE', 'webp', '文件上传类型（webp/原格式）'),
('APP_DEBUG', '0', '调试模式 1开/0关'),
('SITE_NAME', '', '站点名称');

--
-- Dumping data for table `__PREFIX__manage`
--
INSERT INTO `__PREFIX__manage` (`id`, `username`, `password`, `pic`) VALUES (1,'admin','61e2f0d3f61fc7f06741d6230632dd25','upload/manageuser/20180507103202_760.jpg');

--
-- Table structure for table `__PREFIX__union_auth`
--

DROP TABLE IF EXISTS `__PREFIX__union_auth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `__PREFIX__union_auth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '平台标识 tb/jd/pdd/vip',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '展示名称',
  `pid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '推广位PID',
  `free_pid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备用PID',
  `app_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AppKey/ClientId',
  `app_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AppSecret/ClientSecret',
  `auth_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '授权类型 tb/jd/pdd/vip 等',
  `union_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联盟类型 dtk/hdk/pdd_sdk 等',
  `beian` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已备案（拼多多自写SDK需PID备案）',
  `bind_tuanzhang` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绑定团长 0否 1是',
  `order_sync` tinyint(1) NOT NULL DEFAULT '0' COMMENT '订单同步 0关 1开',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认联盟',
  `invite_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邀请码',
  `expire_time` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '授权过期时间（字符串日期）',
  `add_time` int(11) NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`),
  KEY `platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='联盟授权配置';
/*!40101 SET character_set_client = @saved_cs_client */;

SET FOREIGN_KEY_CHECKS = 1;
