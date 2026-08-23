-- ZhiCms miniapp 插件：自营商城表结构
-- 前缀 {pre} 由 PluginManager::runInstallSql 自动替换为真实表前缀（默认 yun_）

-- 商城商品分类
DROP TABLE IF EXISTS `{pre}shop_category`;
CREATE TABLE `{pre}shop_category` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL DEFAULT '' COMMENT '分类名称',
  `pid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '父级ID',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1启用0禁用',
  `addtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='自营商城分类';

-- 商城商品
DROP TABLE IF EXISTS `{pre}shop_goods`;
CREATE TABLE `{pre}shop_goods` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cat_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID',
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '商品标题',
  `subtitle` varchar(200) NOT NULL DEFAULT '' COMMENT '副标题',
  `cover` varchar(500) NOT NULL DEFAULT '' COMMENT '封面图',
  `images` text COMMENT '详情图(逗号分隔)',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '售价',
  `original_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '原价',
  `stock` int(11) NOT NULL DEFAULT '0' COMMENT '库存',
  `sales` int(11) NOT NULL DEFAULT '0' COMMENT '销量',
  `content` text COMMENT '商品详情HTML',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1上架0下架',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `addtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cat_id` (`cat_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='自营商城商品';

-- 购物车
DROP TABLE IF EXISTS `{pre}shop_cart`;
CREATE TABLE `{pre}shop_cart` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `goods_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品ID',
  `num` int(11) NOT NULL DEFAULT '1' COMMENT '数量',
  `addtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_goods` (`uid`,`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商城购物车';

-- 订单主表
DROP TABLE IF EXISTS `{pre}shop_order`;
CREATE TABLE `{pre}shop_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号',
  `uid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `total_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单总额',
  `pay_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实付金额',
  `balance_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '余额抵扣',
  `pay_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1微信2余额',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待支付1已支付2已发货3已完成4已取消',
  `address` varchar(500) NOT NULL DEFAULT '' COMMENT '收货地址JSON',
  `wx_prepay_id` varchar(64) NOT NULL DEFAULT '' COMMENT '微信预支付ID',
  `transaction_id` varchar(64) NOT NULL DEFAULT '' COMMENT '微信交易号',
  `paytime` datetime DEFAULT NULL COMMENT '支付时间',
  `addtime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `uid` (`uid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商城订单';

-- 订单商品明细
DROP TABLE IF EXISTS `{pre}shop_order_item`;
CREATE TABLE `{pre}shop_order_item` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '订单ID',
  `goods_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品ID',
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '商品标题快照',
  `cover` varchar(500) NOT NULL DEFAULT '' COMMENT '封面快照',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '单价快照',
  `num` int(11) NOT NULL DEFAULT '1' COMMENT '数量',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单明细';

-- 订单物流字段（幂等：已存在则忽略）
ALTER TABLE `{pre}shop_order` ADD `express_type` varchar(30) NOT NULL DEFAULT '' COMMENT '快递公司';
ALTER TABLE `{pre}shop_order` ADD `express_no` varchar(40) NOT NULL DEFAULT '' COMMENT '快递单号';
ALTER TABLE `{pre}shop_order` ADD `ship_time` datetime DEFAULT NULL COMMENT '发货时间';
ALTER TABLE `{pre}shop_order` ADD `confirm_time` datetime DEFAULT NULL COMMENT '确认收货时间';

-- 用户余额字段（商城余额支付/积分依赖，幂等：已存在则忽略）
-- 注意：主库 zhicms.sql 的 yun_user 无此列，这里在插件安装时补充，确保 ShopController 余额支付可用
ALTER TABLE `{pre}user` ADD `balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '账户余额';

-- 专属邀请码字段（幂等：已存在则忽略）
-- 6位大小写字母+数字，注册时随机生成；用于分享邀请归因，不使用 uid
ALTER TABLE `{pre}user` ADD `invite_code` varchar(8) NOT NULL DEFAULT '' COMMENT '专属邀请码（6位大小写字母+数字）';
ALTER TABLE `{pre}user` ADD UNIQUE KEY `uk_invite_code` (`invite_code`);

-- 邀请人关系字段（幂等：已存在则忽略）
-- 存储邀请人的 uid（非邀请码，稳定可关联）；0 表示无邀请人
ALTER TABLE `{pre}user` ADD `invited_by` int(11) NOT NULL DEFAULT '0' COMMENT '邀请人uid';
ALTER TABLE `{pre}user` ADD KEY `idx_invited_by` (`invited_by`);

