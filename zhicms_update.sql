-- ============================================================
-- ZhiCms 数据库升级 SQL（累积式，可重复执行）
-- ============================================================
-- 表前缀：使用 __PREFIX__ 占位符，执行时由程序替换为站点真实前缀
--         （后台在线升级 IndexController::execSqlFile 会自动替换，
--           手动导入时请先把 __PREFIX__ 全部替换成你的前缀，如 yun_）
--
-- 兼容性：MySQL 5.6 / 5.7 / 8.0+，MariaDB 10.x
--   - 不使用 utf8mb4_0900_ai_ci（MySQL 8 专属排序规则，5.x 不识别）
--   - 不使用 int(11) 显示宽度（MySQL 8.0.17 起弃用，此处统一写 int）
--   - 不使用 JSON 类型（MySQL 5.6 不支持），JSON 数据统一存 text
--   - varchar 索引长度控制在 191 以内，兼容 5.6 的 767 字节索引上限
--
-- 幂等性：ALTER TABLE ADD COLUMN 在字段已存在时会报错（SQLSTATE 42S21），
--         程序会自动识别并忽略此类"重复执行"错误，不影响升级结果。
-- ============================================================


-- ------------------------------------------------------------
-- 5.0.1：文章 / 商品 / 社区字段扩展
-- ------------------------------------------------------------

ALTER TABLE `__PREFIX__article` ADD COLUMN `allow_comment` tinyint(1) NOT NULL DEFAULT '1' COMMENT '允许评论 1允许 0禁止';
ALTER TABLE `__PREFIX__article` ADD COLUMN `featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推荐文章 1推荐 0否';
ALTER TABLE `__PREFIX__article` ADD COLUMN `navid` int NOT NULL DEFAULT '0' COMMENT '发现分类ID，0=未分类';

ALTER TABLE `__PREFIX__items` ADD COLUMN `spec` varchar(500) DEFAULT '' COMMENT '规格';

-- 注意：lock 是 MySQL 保留字，必须加反引号
ALTER TABLE `__PREFIX__forum` ADD COLUMN `lock` int DEFAULT '0' COMMENT '锁定';
ALTER TABLE `__PREFIX__forum` ADD COLUMN `featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT '推荐';

-- 后台操作日志表
CREATE TABLE IF NOT EXISTS `__PREFIX__admin_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL DEFAULT '' COMMENT '操作类型：login/setting/cache/plugin/user/article/forum/items/page 等',
  `content` varchar(500) NOT NULL DEFAULT '' COMMENT '操作描述',
  `operator` varchar(100) NOT NULL DEFAULT '' COMMENT '操作人（后台账号）',
  `ip` varchar(45) NOT NULL DEFAULT '' COMMENT '操作IP',
  `url` varchar(500) NOT NULL DEFAULT '' COMMENT '请求地址',
  `create_time` int NOT NULL DEFAULT '0' COMMENT '操作时间戳',
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台操作日志';

-- 计划任务表
CREATE TABLE IF NOT EXISTS `__PREFIX__cron_task` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '任务名称',
  `type` varchar(20) NOT NULL DEFAULT 'custom' COMMENT 'system=系统任务 custom=自定义任务',
  `exec_type` varchar(20) NOT NULL DEFAULT 'url' COMMENT 'url/php/shell/python',
  `command` text COMMENT 'URL 或 PHP/Shell/Python 代码或脚本路径',
  `schedule` varchar(50) NOT NULL DEFAULT '' COMMENT 'cron 表达式或 every N minute/hour/day',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1启用 0停用',
  `last_run` int NOT NULL DEFAULT '0' COMMENT '上次执行时间',
  `last_result` varchar(500) NOT NULL DEFAULT '' COMMENT '上次执行结果摘要',
  `next_run` int NOT NULL DEFAULT '0' COMMENT '预计下次执行时间',
  `create_time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='计划任务';

-- 违规词库表
CREATE TABLE IF NOT EXISTS `__PREFIX__sensitive_word` (
  `id` int NOT NULL AUTO_INCREMENT,
  `word` varchar(100) NOT NULL DEFAULT '' COMMENT '违规词',
  `level` tinyint(2) NOT NULL DEFAULT '1' COMMENT '1低 2中 3高',
  `category` varchar(50) NOT NULL DEFAULT '' COMMENT '分类',
  `create_time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `word` (`word`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='违规词库';


-- ------------------------------------------------------------
-- 5.0.2：用户中心 + 用户系统字段扩展
-- ------------------------------------------------------------

ALTER TABLE `__PREFIX__user` ADD COLUMN `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱' AFTER `mobile`;
ALTER TABLE `__PREFIX__user` ADD COLUMN `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像URL' AFTER `email`;
ALTER TABLE `__PREFIX__user` ADD COLUMN `reg_time` varchar(20) NOT NULL DEFAULT '' COMMENT '注册时间' AFTER `avatar`;
ALTER TABLE `__PREFIX__user` ADD COLUMN `reg_ip` varchar(64) NOT NULL DEFAULT '' COMMENT '注册IP' AFTER `reg_time`;
ALTER TABLE `__PREFIX__user` ADD COLUMN `login_ip` varchar(64) NOT NULL DEFAULT '' COMMENT '最后登录IP' AFTER `reg_ip`;
ALTER TABLE `__PREFIX__user` ADD COLUMN `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1正常 0禁用' AFTER `login_ip`;
-- 账户余额（商城余额支付 / 积分依赖；主库 yun_user 无此列，升级时补）
ALTER TABLE `__PREFIX__user` ADD COLUMN `balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '账户余额' AFTER `status`;

-- 手机号 / 用户名唯一索引
-- 注意：若目标库已存在重复数据会报错，需先清理重复记录再执行
ALTER TABLE `__PREFIX__user` ADD UNIQUE KEY `uk_mobile` (`mobile`);
ALTER TABLE `__PREFIX__user` ADD UNIQUE KEY `uk_username` (`username`);

-- 商城表：所属联盟模型
ALTER TABLE `__PREFIX__mall` ADD COLUMN `union_id` int NOT NULL DEFAULT '0' COMMENT '所属联盟模型ID' AFTER `name`;

-- 用户功能开关默认值（后台「互动设置」读取）
INSERT INTO `__PREFIX__config` (`key`, `value`, `desc`) VALUES
('user_reg_captcha', '1', '注册开启图形验证码 1开/0关'),
('user_email_verify', '0', '注册需要邮箱验证 1是/0否（当前仅预留）'),
('user_show_login', '1', '前台显示用户登录/注册入口 1显示/0隐藏')
ON DUPLICATE KEY UPDATE `desc` = VALUES(`desc`);

-- 单页表：别名（伪静态 page-<alias>.html）+ 指定模板
ALTER TABLE `__PREFIX__page` ADD COLUMN `alias` varchar(60) NULL DEFAULT NULL COMMENT '页面别名，NULL 允许多条空别名' AFTER `title`;
ALTER TABLE `__PREFIX__page` ADD COLUMN `display` varchar(30) NOT NULL DEFAULT '' COMMENT '指定显示模板（留空用默认 page）' AFTER `body`;
ALTER TABLE `__PREFIX__page` ADD UNIQUE KEY `alias` (`alias`);

-- 前台导航菜单表（与 __PREFIX__nav 文章「发现」分类不同，本表用于顶部主导航）
CREATE TABLE IF NOT EXISTS `__PREFIX__navmenu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '菜单名称',
  `url` varchar(500) NOT NULL DEFAULT '' COMMENT '链接地址（绝对/相对/伪静态）',
  `type` varchar(20) NOT NULL DEFAULT 'custom' COMMENT '来源类型：custom自定义 cheaps优惠券 brand大牌 rank风云榜 hot热榜 forum社区 page单页',
  `type_id` int NOT NULL DEFAULT '0' COMMENT '关联ID（page类型为页面ID，其他栏目为0）',
  `parent_id` int NOT NULL DEFAULT '0' COMMENT '父级ID，0=主导航',
  `target` tinyint(1) NOT NULL DEFAULT '0' COMMENT '新窗口打开 1是 0否',
  `hide` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1隐藏 0显示',
  `isdefault` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1系统内置不可删除（如首页）',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序（越小越靠前）',
  `create_time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='前台导航菜单';


-- ------------------------------------------------------------
-- 管理员对外昵称 + 文章作者头像
-- ------------------------------------------------------------

-- 管理员表：对外昵称（前台发布文章默认调用的作者名）
ALTER TABLE `__PREFIX__manage` ADD COLUMN `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '对外昵称（前台文章作者展示）' AFTER `password`;

-- 文章表：作者头像（默认调用管理员头像）
ALTER TABLE `__PREFIX__article` ADD COLUMN `author_pic` varchar(255) NOT NULL DEFAULT '' COMMENT '作者头像（默认调用管理员头像）' AFTER `author`;

-- 联盟授权表（拼多多自写SDK一条龙所需）
-- 注意：auth_type / union_type 为字符串标识（tb/jd/pdd/vip、dtk/hdk/pdd_sdk 等），必须与代码写入保持一致用 varchar
CREATE TABLE IF NOT EXISTS `__PREFIX__union_auth` (
  `id` int NOT NULL AUTO_INCREMENT,
  `platform` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '平台标识 tb/jd/pdd/vip',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '展示名称',
  `pid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '推广位PID',
  `free_pid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备用PID',
  `app_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AppKey/ClientId',
  `app_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AppSecret/ClientSecret',
  `auth_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '授权类型 tb/jd/pdd/vip 等',
  `union_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联盟类型 dtk/hdk/pdd_sdk 等',
  `beian` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已备案 1已备案 0未备案',
  `bind_tuanzhang` tinyint(1) NOT NULL DEFAULT '0' COMMENT '绑定团长 0否 1是',
  `order_sync` tinyint(1) NOT NULL DEFAULT '0' COMMENT '订单同步 0关 1开',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认联盟',
  `invite_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '邀请码',
  `expire_time` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '授权过期时间（字符串日期）',
  `add_time` int NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`),
  KEY `platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='联盟授权配置';

-- 兼容旧库：若 auth_type / union_type 曾被错误建成 tinyint，改回 varchar（与代码写入一致，避免 1366 错误）
ALTER TABLE `__PREFIX__union_auth` MODIFY COLUMN `auth_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '授权类型 tb/jd/pdd/vip 等';
ALTER TABLE `__PREFIX__union_auth` MODIFY COLUMN `union_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联盟类型 dtk/hdk/pdd_sdk 等';

-- 联盟授权表：拼多多自写SDK一条龙「是否已备案」标记（0未备案 1已备案）
ALTER TABLE `__PREFIX__union_auth` ADD COLUMN `beian` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已备案 1已备案 0未备案' AFTER `union_type`;

-- 兼容更早版本旧库：补齐其余可能缺失的列（与 SetController::ensureUnionAuthTable 一致）
ALTER TABLE `__PREFIX__union_auth` ADD COLUMN `free_pid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备用PID' AFTER `pid`;
ALTER TABLE `__PREFIX__union_auth` ADD COLUMN `app_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AppKey/ClientId' AFTER `free_pid`;
ALTER TABLE `__PREFIX__union_auth` ADD COLUMN `app_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AppSecret/ClientSecret' AFTER `app_key`;
ALTER TABLE `__PREFIX__union_auth` ADD COLUMN `union_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '联盟类型 dtk/hdk/pdd_sdk 等' AFTER `auth_type`;


-- ------------------------------------------------------------
-- 5.0.2 补丁：文章来源链接 surl 字段扩容（避免长 URL 写入报 1406 Data too long）
-- ------------------------------------------------------------
ALTER TABLE `__PREFIX__article` MODIFY COLUMN `surl` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '文章来源链接';

-- 5.0.2 补丁：清洗已存库的 AI 错误串
-- 发布文章时若 AI 接口限流（如 429），旧版会把 "HTTP错误: 429 ..." 当成
-- 正常的描述/关键词写入 yun_article.dec / keywords，前台列表直接渲染导致显示错误。
-- 此处将命中错误前缀的字段清空，下次保存时（已修复降级逻辑）会自动重新生成。
UPDATE `__PREFIX__article` SET `dec` = '' WHERE `dec` LIKE 'HTTP错误%' OR `dec` LIKE 'CURL错误%';
UPDATE `__PREFIX__article` SET `keywords` = '' WHERE `keywords` LIKE 'HTTP错误%' OR `keywords` LIKE 'CURL错误%';


-- ------------------------------------------------------------
-- 5.0.2 补丁：管理员密码列扩容为 varchar(100)
-- 旧库 password 为 varchar(35)，仅够存 md5（32 字符）；登录成功后会自动把
-- 旧 md5 升级为 bcrypt（60 字符），会触发 1406 Data too long for column 'password'
-- 导致登录接口抛异常、前端报「网络请求失败」。扩容后即可正常写入。
-- ------------------------------------------------------------
ALTER TABLE `__PREFIX__manage` MODIFY COLUMN `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '登录密码（md5 或 bcrypt）';

-- ------------------------------------------------------------
-- 5.0.2 补丁：商品来源标识 item_from 归一化（闭环转链断裂根因）
-- ------------------------------------------------------------
-- 旧版采集/入库未对 item_from 做规范，可能写入 dtk / taobao / tmall / tm 等别名，
-- 而转链入口 RedirectController::jump() 白名单只认 tb/jd/pdd/vip，命中别名会被拦截 400，
-- 导致前台「去购买/领券」全部失效。下方将所有别名统一归并为 tb（淘宝/天猫），
-- jd/pdd/vip 保持不变。新版本代码已在入库（UnionController::saveGoodsBatch）与
-- getPrivilegeLink 处做别名归一，本语句仅清洗历史已存在的脏数据。
UPDATE `__PREFIX__items` SET `item_from` = 'tb'
WHERE `item_from` IN ('dtk', 'taobao', 'tmall', 'tm');


-- ------------------------------------------------------------
-- 5.0.2 补丁：幻灯广告(huan)排序字段 px（支持手动排序，越小越靠前）
-- 旧库无 px 列会导致列表/前台按 id 默认排序，无法调整顺序
-- ------------------------------------------------------------
ALTER TABLE `__PREFIX__huan` ADD COLUMN `px` int NOT NULL DEFAULT '0' COMMENT '排序，越小越靠前';


-- ------------------------------------------------------------
-- 版本号对齐（务必放在最后执行）
-- ------------------------------------------------------------
INSERT INTO `__PREFIX__config` (`key`, `value`, `desc`) VALUES ('cfg_version', '{"version":"5.0.2"}', '版本号')
ON DUPLICATE KEY UPDATE `value` = '{"version":"5.0.2"}';
