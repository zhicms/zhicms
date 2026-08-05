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
-- 版本号对齐（务必放在最后执行）
-- ------------------------------------------------------------
INSERT INTO `__PREFIX__config` (`key`, `value`, `desc`) VALUES ('cfg_version', '{"version":"5.0.2"}', '版本号')
ON DUPLICATE KEY UPDATE `value` = '{"version":"5.0.2"}';
