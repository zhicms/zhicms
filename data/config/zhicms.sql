/*
 Navicat Premium Data Transfer

 Source Server         : 本地数据库
 Source Server Type    : MySQL
 Source Server Version : 50553
 Source Host           : localhost:3306
 Source Schema         : zhicms

 Target Server Type    : MySQL
 Target Server Version : 50553
 File Encoding         : 65001

 Date: 13/11/2019 14:18:43
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for yun_article
-- ----------------------------
CREATE TABLE IF NOT EXISTS `yun_article` (
  `id` int NOT NULL,
  `goodsId` bigint DEFAULT '0',
  `itemLink` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `cid` int NOT NULL DEFAULT '0',
  `mainPic` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `dec` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `view` int DEFAULT '0',
  `like` int DEFAULT '0',
  `lock` int DEFAULT '0',
  `couponEndTime` varchar(255) NOT NULL,
  `date` varchar(255) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `yun_article`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `goodsId` (`goodsId`),
  ADD KEY `title` (`title`) USING BTREE;
  
ALTER TABLE `yun_article`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;


-- ----------------------------
-- Table structure for yun_goods
-- ----------------------------
CREATE TABLE IF NOT EXISTS `yun_goods` (
  `id` int NOT NULL,
  `goodsId` bigint DEFAULT '0',
  `itemLink` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` longtext CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `cid` int NOT NULL DEFAULT '0',
  `mainPic` varchar(255) DEFAULT NULL,
  `originalPrice` double(10,2) NOT NULL DEFAULT '0.00',
  `actualPrice` double(10,2) NOT NULL DEFAULT '0.00',
  `discounts` double(10,2) NOT NULL DEFAULT '0.00',
  `commissionRate` double(4,2) DEFAULT '0.00',
  `couponTotalNum` int DEFAULT '0',
  `couponReceiveNum` int DEFAULT '0',
  `couponEndTime` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT '0',
  `couponStartTime` varchar(255) DEFAULT '0',
  `couponConditions` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `couponPrice` int DEFAULT '0',
  `monthSales` int NOT NULL DEFAULT '0',
  `shopType` varchar(20) DEFAULT NULL,
  `del` int NOT NULL DEFAULT '0',
  `top` tinyint(1) DEFAULT '0',
  `top_stime` varchar(255) DEFAULT '0',
  `top_etime` varchar(255) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `yun_goods`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `goodsId_2` (`goodsId`),
  ADD KEY `shopType` (`shopType`) USING BTREE,
  ADD KEY `goodsId` (`goodsId`) USING BTREE,
  ADD KEY `cid` (`cid`) USING BTREE,
  ADD KEY `title` (`title`) USING BTREE,
  ADD KEY `originalPrice` (`originalPrice`) USING BTREE,
  ADD KEY `actualPrice` (`actualPrice`) USING BTREE,
  ADD KEY `del` (`del`) USING BTREE,
  ADD KEY `top` (`top`) USING BTREE,
  ADD KEY `top_stime` (`top_stime`) USING BTREE,
  ADD KEY `top_etime` (`top_etime`) USING BTREE;
  
  
 ALTER TABLE `yun_goods`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

-- ----------------------------
-- Table structure for yun_huan
-- ----------------------------
DROP TABLE IF EXISTS `yun_huan`;
CREATE TABLE `yun_huan`  (
  `id` int(9) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `pic` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `link` varchar(200) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `file` int(9) NOT NULL,
  `type` int(9) NOT NULL COMMENT '0pc 1移动',
  `date` varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 7 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of yun_huan
-- ----------------------------
INSERT INTO `yun_huan` VALUES (4, '3', 'upload/huan/20180717182237_100.png', 'http://www.umaijie.com/page-2.html', 0, 0, '2018-05-29 10:45:43');
INSERT INTO `yun_huan` VALUES (6, '测试', 'upload/huan/20180716113103_746.jpg', 'http://www.umaijie.com', 0, 1, '2018-07-16 11:31:12');



-- ----------------------------
-- Table structure for yun_manage
-- ----------------------------
DROP TABLE IF EXISTS `yun_manage`;
CREATE TABLE `yun_manage`  (
  `id` int(9) NOT NULL AUTO_INCREMENT,
  `username` varchar(30) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `password` varchar(35) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `pic` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 2 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of yun_manage
-- ----------------------------
INSERT INTO `yun_manage` VALUES (1, 'admin', '9daef705a0bd551a87be632eb3fd84c5', 'upload/manageuser/20180507103202_760.jpg');


-- ----------------------------
-- Table structure for yun_page
-- ----------------------------
DROP TABLE IF EXISTS `yun_page`;
CREATE TABLE `yun_page`  (
  `id` int(9) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `keywords` varchar(300) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `dec` varchar(500) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `body` text CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `date` varchar(30) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 8 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of yun_page
-- ----------------------------
INSERT INTO `yun_page` VALUES (1, '关于我们', '关于我们', '关于我们', 'ZhiCms是一家行业领先的创意礼物导购网站，创立于2012年。我们从淘宝、天猫、京东、亚马逊等各大电商精选品质好物，只为解决用户选礼难、送礼难的困扰。<br />\r\n<br />\r\n<p>\r\n	我们努力寻求将互联网技术与专家送礼经验完美融合，致力于为用户提供最全的礼物品类以及最多的送礼场景参考，帮助用户从繁重的甄选礼物的过程中解脱出来，让您快速轻松地找到满意的礼物。送对的礼物，让礼物传递爱和温情。\r\n</p>\r\n<p>\r\n	<br />\r\n</p>', '2018-05-07 20:49:31');
INSERT INTO `yun_page` VALUES (2, '商家合作', '1', '2', '商家须知<br />\r\n我站是淘宝导购网站，目前只支持淘宝、天猫商家合作（合作方式：阿里妈妈-淘宝联盟），暂不支持京东、亚马逊等其它电商平台合作。工厂和批发商朋友请不要联系我们，我们不要货源。<br />\r\n<br />\r\n如何推广<br />\r\n请联系我站合作专员，添加QQ好友后主动将店铺链接发给合作专员，专员会根据贵店商品的品质、销量、用户评价、个性化等因素来判定是否与您合作。<br />\r\n<br />\r\n首先，以下情况无法合作<br />\r\n1、新店<br />\r\n2、销量低（单品月销量低于50）、评分低、口碑差、转化差的店铺<br />\r\n3、存在刷单、刷好评等虚假行为<br />\r\n4、所售商品缺少礼品属性<br />\r\n5、销售假冒伪劣产品<br />\r\n6、达成合作意向后，违背诚信原则，佣金率随意改动，我站将即刻终止合作<br />\r\n<br />\r\n如果贵店达到合作门槛，我们会要求您单独设置一个高佣金定向推广计划（佣金比例高于同行其他网店，具体和合作专员商定）。然后，我站编辑会将贵店商品上架到网站和APP上进行推广，推广力度大小与佣金率高低有直接关系。<br />', '2018-05-07 20:49:46');
INSERT INTO `yun_page` VALUES (3, '如何购买', '1', '2', '首先请知悉ZhiCms是一家导购网站，网站上的商品均来源于淘宝、天猫等电商平台，我们不直接销售商品。<br />\r\n<p>\r\n	在优买街礼物上找到您想要购买的商品，在商品详情页点击“去淘宝购买”按钮（如下图），进入淘宝后请联系商家购买。如您有购物疑问，请联系商家旺旺咨询。\r\n</p>\r\n<p>\r\n	<img src=\"upload/page/20180530111251_764.jpg\" alt=\"\" /> \r\n</p>\r\n<p>\r\n	<br />\r\n</p>\r\n<p>\r\n	常见问题<br />\r\n1、我不会网购怎么办？<br />\r\n如果您没有网购经验，可以将我们网站的链接地址发给您有网购经验的朋友，让您朋友来帮您购买。\r\n</p>\r\n<p>\r\n	3、购买后几天能送到？<br />\r\n购买礼物快慢取决于以下几个因素：<br />\r\nA、商品是否需要定制：个性化定制的商品，比如刻字、定制照片、手工定制等商品，可能需要花费一定的制作时间才能发货，具体时间请联系销售该商品的商家咨询；<br />\r\nB、快递时间：商家和您的所在位置越近送货越快；如果选用顺丰快递，一般1-2日就能送达，时效好于一般快递公司。<br />\r\n<br />\r\n4、为什么我喜欢一件商品后，在“我的喜欢”页面还是空的？<br />\r\n有可能您的浏览器禁用了cookies功能，导致您喜欢过一件商品后而无法将该条数据存入cookies。如果您想正常使用此项功能，建议开启浏览器的cookies功能。\r\n</p>', '2018-05-07 20:49:56');
INSERT INTO `yun_page` VALUES (4, '版权声明', '1', '1', '优买街礼物网 程序后端研发 前端UI设计 均为优买街原创，任何人都可以抄袭，一个破UI 算得了啥！ 和命踏马一样金贵！', '2018-05-30 09:00:58');
INSERT INTO `yun_page` VALUES (5, '用户协议', '1', '1', '待编辑', '2018-05-30 09:10:56');
INSERT INTO `yun_page` VALUES (6, '意见反馈', '1', '1', '在前行的路上，离不开您对我们的关注和支持，我们尚在成长期，还有很多的不足，因此，特别希望您给予我们宝贵的建议和意见。无论是反馈一个小小的bug，或是功能改进的想法，亦或是对我们产品和服务的某些方面有不满意的地方，都请发送邮件至team#zhicms.vip（发邮件时请将#换成@)，我们会用最真诚的态度来回应您的声音。<br />\r\n<br />\r\n如果您提供的建设性想法被我们采纳，您将有机会获得我们赠送的精美小礼品或微信红包。<br />', '2018-05-30 09:13:00');
INSERT INTO `yun_page` VALUES (7, '友情链接', '1', '1', '<!-- /title -->\r\n<div class=\"title fz18\">\r\n	<h3 class=\"tith\">\r\n		友链交换\r\n	</h3>\r\n</div>\r\n<div class=\"cont mb30\">\r\n	<p>\r\n		1、首页要求：百度权重&gt;=5；Alexa世界排名10万以内；收录大于10000；已交换友链不超过50个；知名网站、电商类网站为佳。<br />\r\n2、内页要求：百度权重&gt;=3；无搜索引擎惩罚记录；收录正常。\r\n	</p>\r\n	<p>\r\n		交换请发送邮件至team#zhicms.vip（发邮件时请将#换成@)，邮件内请写明网站名、网址、百度权重值、联系人QQ号。\r\n	</p>\r\n</div>', '2018-05-30 09:13:20');

DROP TABLE IF EXISTS `yun_plug`;
CREATE TABLE `yun_plug`  (
  `id` int(9) NOT NULL AUTO_INCREMENT,
  `pic` varchar(200) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `title` varchar(200) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `controller` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `info` text CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `level` int(9) NOT NULL,
  `author` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `version` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `biaoshi` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `manage` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `index` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `id_2`(`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 3 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of yun_plug
-- ----------------------------
INSERT INTO `yun_plug` VALUES (2, 'upload/plug/2.png', '优买街礼物网', 'index.php?r=plug/gift/index', '仿优买街礼物网', 5, '官方开发', '1.0.0', 'gift', 'manage', 'index');


-- ----------------------------
-- Table structure for yun_plug_h5_huan
-- ----------------------------
DROP TABLE IF EXISTS `yun_plug_h5_huan`;
CREATE TABLE `yun_plug_h5_huan`  (
  `id` int(9) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `link` varchar(500) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `pic` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `file` int(9) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for yun_link
-- ----------------------------
DROP TABLE IF EXISTS `yun_link`;
CREATE TABLE `yun_link`  (
  `id` int(9) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) CHARACTER SET gbk COLLATE gbk_chinese_ci NOT NULL,
  `link` varchar(300) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `px` int(9) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `id`(`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
