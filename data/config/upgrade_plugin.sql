-- ZhiCms 标准化插件系统：插件注册表（yun_plug）
-- 说明：{pre} 会被 PluginManager 自动替换为数据库前缀（默认 yun_）
-- 该表用于替代旧版 gift 插件的注册表，字段已重新定义

DROP TABLE IF EXISTS `{pre}plug`;

CREATE TABLE `{pre}plug` (
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
