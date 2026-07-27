# ZhiCms 5.0.0

> 专业的电商导购 CMS 系统 · PHP 自研 MVC 框架

ZhiCms 是一套开箱即用的电商导购/值得买系统，集成商品采集、内容发布、转链跳转、佣金结算、微社区互动等完整功能。采用轻量自研框架 + ThinkPHP 组件，支持 PC / H5 / 微信小程序多端运行，SEO 友好，是搭建导购网站的首选方案。

---

## 目录

- [特性亮点](#特性亮点)
- [环境要求](#环境要求)
- [快速开始](#快速开始)
- [功能详解](#功能详解)
  - [前台功能](#前台功能)
  - [后台管理](#后台管理)
  - [本地选品库](#本地选品库)
  - [联盟库与采集](#联盟库与采集)
  - [微社区系统](#微社区系统)
  - [AI 开放平台](#ai-开放平台)
  - [插件系统](#插件系统)
  - [第三方 API 集成](#第三方-api-集成)
- [配置说明](#配置说明)
- [目录结构](#目录结构)
- [技术栈](#技术栈)
- [Web 服务器配置](#web-服务器配置)
- [常见问题](#常见问题)

---

## 特性亮点

| 类别 | 特性 |
|------|------|
| 电商全链路 | 商品采集 → 内容发布 → 用户浏览 → 转链跳转 → 佣金结算 |
| 多平台 | 淘宝/天猫、京东、拼多多、唯品会 四大平台商品搜索与比价 |
| 微社区 | 板块/小组/帖子三层结构 + 无限嵌套回复 + 点赞 + 商品卡片 |
| 插件系统 | 原生格式 + 兼容 WordPress / Z-Blog / Emlog 三种插件格式 |
| 多端覆盖 | PC 端 + H5 移动端 + 微信小程序（uni-app） |
| 模板引擎 | Legacy 正则引擎 + ThinkTemplate 引擎，可切换 |
| AI 能力 | 大模型对接（对话/图像生成）、AI 购物助手、智能导购 |
| 一键安装 | 图形化安装向导，环境检测 → 数据库配置 → 三步完成 |
| SEO 优化 | 伪静态 URL、自定义 TDK、Sitemap |

---

## 环境要求

| 项目 | 最低要求 | 推荐配置 |
|------|---------|---------|
| PHP | 7.0 | 7.4+ / 8.x |
| MySQL | 5.5 | 5.7+ / 8.0 |
| PHP 扩展 | pdo, pdo_mysql, curl, gd, mbstring, json, fileinfo | opcache, redis |
| Web Server | Apache / Nginx / IIS | Nginx |

---

## 快速开始

### 1. 上传文件

将全部文件上传到网站根目录。

### 2. 设置目录权限

以下目录需要可写权限（Linux 执行 `chmod -R 777`）：

```
data/
data/config/
data/cache/
data/log/
upload/
```

### 3. 访问安装向导

浏览器访问网站域名，系统检测到未安装会自动跳转到安装向导。

按提示完成三步：
1. **环境检查** — PHP 版本、扩展、目录权限
2. **数据库配置** — 主机、端口、用户名、密码、库名、表前缀
3. **管理员设置** — 账号、密码、站点名称

### 4. 安装完成

- **前台**：`http://你的域名/`
- **后台**：`http://你的域名/index.php?r=manage`

使用安装时设置的管理员账号登录。

---

## 功能详解

### 前台功能

#### 首页展示

- 最新文章列表（30 分钟内），按时间倒序排列
- 按月 / 分类筛选文章
- 侧边栏数据（热门文章、推荐商品等）
- 分页浏览

#### 商品搜索与比价

- **多平台搜索**：支持本地库、淘宝、京东、拼多多、唯品会五个数据源
- **比价模式**：同一关键词跨平台聚合对比，展示各平台最低价
- **淘宝链接识别**：自动识别淘宝商品链接，直达详情页
- 支持优惠券筛选、价格排序、销量排序

#### 商品详情页

- 商品图片、标题、价格（原价/券后价/优惠券金额）
- 月销量、店铺名称、佣金信息
- 直减优惠券领取 + 购买跳转
- 自动走联盟推广链接，佣金归站长所有

#### 商品跳转与转链

- 用户点击"去购买" → 系统调用淘客 API 获取联盟推广链接 → 302 跳转
- 支持淘宝/京东/拼多多/唯品会四种平台转链
- 跳转链接带 PID，佣金自动计入站长账户
- 跳转结果缓存，减少 API 调用

#### 风云榜

- 对接大淘客实时榜单接口
- 实时榜 / 全天热销榜 / 热推榜 / 综合热搜榜
- 按分类筛选，展示商品排行

#### 品牌中心

- 品牌列表浏览（大淘客品牌栏目）
- 品牌详情页（品牌下商品列表）
- 支持分类筛选

#### 优惠券商城

- 仿"什么值得买"精选页面
- 分类筛选 + 关键词搜索
- 展示优惠券商品（券后价 / 优惠券金额 / 销量）

#### 移动端（H5）

- 自动 UA 检测，移动设备自动跳转 H5 页面
- 支持 4 种移动端风格：super_search / tb_minishop / welfare_listing / rt_xb
- Vue 3 前端 + Swiper 滑动交互
- 可通过 `?pc=1` 参数强制访问 PC 版

#### AI 购物助手

- 智能购物意图识别：用户描述需求 → 自动搜索站内商品
- 全网比价模式：同时搜索多个平台找最优价
- 非购物问题自动转发 AI 大模型对话
- 基于 Cookie 的独立会话历史

#### 用户中心

- 用户名 / 手机号 / 密码修改
- 我的收藏（帖子收藏 + 文章收藏）
- 评论历史

#### 互动系统

- **文章评论**：支持登录用户和无登录用户评论（昵称 + 邮箱）
- **嵌套回复**：无限级嵌套回复
- **点赞**：文章点赞 + 评论点赞，支持未登录用户
- **评论审核**：可配置评论需审核后显示
- **防刷机制**：评论间隔秒数控制

---

### 后台管理

#### 仪表盘

- 统计数据概览：用户数、商品数、文章数、今日文章数、今日有效商品数
- 版本更新检测：远程检查是否有新版本
- 一键清除缓存

#### 文章管理

- 文章列表，支持关键词搜索
- **一键采集**：从好单库朋友圈素材库拉取文案，自动生成包含商品卡片的文章入库
- 文章编辑：标题 / 内容 / 分类 / SEO 关键词 / 摘要

#### 商品管理

包含 **本地选品库** 和 **联盟库** 两个模块：

##### 本地选品库

- 管理已采集入库的商品
- 分类筛选、关键词搜索
- 编辑商品信息（标题/价格/图片等）
- 商品置顶（设定置顶时间范围）
- 清理过期商品

##### 联盟库

- 通过大淘客 / 好单库 API 实时搜索商品
- 多平台切换（淘宝/京东/拼多多/唯品会）
- 关键词搜索、分类筛选、是否有优惠券
- **单条采集**：选中商品一键入库
- **批量更新**：拉取最新商品数据，更新价格/优惠券/销量/佣金等动态字段
- **比价模式**：跨平台同款商品聚合对比
- 采集日志：所有操作记录到 `data/log/collect.log`

#### 社区管理

- 帖子列表，支持删除（同时删除关联回复）
- 帖子显示/隐藏切换
- 板块和小组管理

#### 用户管理

- 前台用户列表
- 添加/编辑用户（用户名/密码/手机号/VIP标记/锁定状态）

#### 插件管理

- 已安装插件列表：安装/卸载/启用/停用
- 自动扫描未安装插件（`plugins/` 目录）
- 兼容格式检测：原生 / Emlog / Z-Blog / WordPress
- 插件元信息读取（名称/版本/作者/描述）
- 插件市场对接

#### 系统设置

- **网站基础**：网站名称 / URL / Logo / 二维码 / 关键词 / 描述
- **网站状态**：关闭开关 + 关闭提示信息
- **缓存设置**：缓存时间 / 模板缓存 / 数据库缓存
- **水印设置**：开关 + 自定义水印文字
- **CDN 加速**：CDN 域名配置
- **分页**：每页显示条数
- **URL 路由**：自定义伪静态规则（正则匹配 + 目标路由）
- **SEO 设置**：首页 / 品牌 / 榜单 / 优惠券 / 详情 / 搜索 / 移动端 各页面的 title / keywords / description
- **API 配置**：大淘客 AppKey/AppSecret、好单库 AppKey、淘宝联盟 PID、阿里妈妈密钥
- **转链方式**：大淘客 (DTK) / 好单库 (HDK) 切换
- **移动端风格**：四种风格可选
- **短信配置**：短信通道对接
- **统计代码**：嵌入第三方统计 JS
- **互动设置**：评论开关 / 社区开关 / 匿名评论 / 评论审核 / 评论间隔
- **文件上传**：支持 WebP 自动转换

#### 广告管理

- 幻灯广告（PC 端 + 移动端）
- 友情链接管理

#### 单页管理

- 独立页面发布 / 编辑
- 支持自定义模板视图
- 内置默认页面：关于我们、商家合作、如何购买、版权声明、用户协议、意见反馈、友情链接

#### AI 开放平台

- 管理多个 AI 模型配置
- 文本对话模型 + 图像生成模型
- 支持 OpenAI 协议兼容的大模型（DeepSeek / 智谱 / Kimi / 通义千问 等）
- 模型添加/编辑/删除/启用切换
- 流式对话 (SSE) 输出，打字机效果
- 会话历史管理
- 系统提示词配置
- 应用场景：后台 AI 对话、AI 生成签名、AI 回复评论

#### 管理员管理

- 管理员账户列表
- 添加/编辑/删除管理员

---

### 第三方 API 集成

系统通过 `ZhiCms/ext/Tjk/` 聚合层统一对接两大淘客平台：

#### 大淘客 (DTK)

- 商品搜索 / 商品详情 / 转链
- 品牌栏目 / 排行榜 / 线报
- 最新商品拉取（用于定时更新）
- 分类列表

#### 好单库 (HDK)

- 淘宝 / 京东 / 拼多多 / 唯品会商品搜索
- 商品详情 / 转链
- 朋友圈素材库（文章采集用）

#### 转链方式

后台可切换使用大淘客或好单库作为转链引擎，API Key 在系统设置中配置。

---

### AI 开放平台

支持添加多个 AI 模型，提供系统级 AI 能力：

- **文本对话**：聊天补全接口，支持所有 OpenAI 协议兼容的大模型
- **图像生成**：文生图接口
- **流式输出 (SSE)**：实时打字机效果
- **会话管理**：多轮对话上下文保持

内置应用场景：
- 后台右下角 AI 对话悬浮按钮
- 用户资料页 AI 生成个性签名
- 评论回复 AI 辅助
- 前台 AI 购物助手
- 可扩展插件调用

---

### 插件系统

ZhiCms 独创的四合一插件兼容引擎：

#### 支持的插件格式

| 格式 | 识别文件 | 说明 |
|------|----------|------|
| 原生格式 | `plugin.json` | ZhiCms 专用，功能最完整 |
| Z-Blog 格式 | `plugin.xml` | 兼容 Z-Blog PHP 生态插件 |
| Emlog 格式 | 主文件特征检测 | 兼容 Emlog 生态插件 |
| WordPress 格式 | 文件头注释 `Plugin Name:` | 兼容 WP 生态插件（子集） |

#### 插件目录结构

```
plugins/{插件别名}/
├── plugin.json       # 原生格式：元信息 + 配置
├── Plugin.php        # 主入口类，继承 BasePlugin
├── ...               # 其他插件文件
```

#### 插件生命周期

`安装 → 启用 → 注册钩子 → 运行 → 停用 → 卸载`

#### 系统钩子

| 钩子名 | 类型 | 触发时机 |
|--------|------|---------|
| `appBegin` | 动作 | 应用启动 |
| `appEnd` | 动作 | 应用结束 |
| `appError` | 动作 | 应用异常 |
| `actionBefore` | 动作 | 控制器方法执行前 |
| `actionAfter` | 动作 | 控制器方法执行后 |
| `routeParseUrl` | 动作 | 路由解析完成 |
| `dbQueryBegin` | 动作 | 数据库查询开始 |
| `dbQueryEnd` | 动作 | 数据库查询结束 |

---

## 配置说明

### 数据库配置

`data/config/db.php` — 安装时自动写入，支持读写分离：

```php
'DB' => array(
    'default' => array(
        'DB_TYPE'    => 'MysqlPdo',   // MysqlPdo | Mysqli
        'DB_HOST'    => 'localhost',
        'DB_USER'    => 'root',
        'DB_PWD'     => '',
        'DB_PORT'    => '3306',
        'DB_NAME'    => 'zhicms',
        'DB_CHARSET' => 'utf8mb4',
        'DB_PREFIX'  => 'yun_',
        'DB_CACHE'   => 'DB_CACHE',
    ),
),
```

### 路由配置

`data/config/rule.php` — 自定义伪静态规则：

```php
'REWRITE_ON' => 1,
'REWRITE_RULE' => array(
    'index.html'           => 'index/index/index',
    'view-<id>.html'       => 'index/index/view/id=<id>',
    'shequ.html'           => 'index/forum/index',
    'shequ-<gid>.html'     => 'index/forum/group/gid=<gid>',
    'tiezi-<id>.html'      => 'index/forum/view/id=<id>',
    // ... 更多规则
),
```

### 模板引擎配置

`data/config/global.php` 中切换引擎：

```php
'TPL' => array(
    'TPL_PATH'   => '',
    'TPL_SUFFIX' => '.html',
    'ENGINE'     => 'legacy',  // 'legacy'（旧引擎）或 'think'（ThinkTemplate）
),
```

### 模板开发

- 模板路径：`app/{模块}/view/{控制器}/{方法}.html`
- Legacy 语法：`{$var}` / `{foreach $list as $v}` / `{if $cond}`
- Think 语法：`{$var}` / `{volist name="list" id="v"}` / `{include file="..."}`
- 模板编译缓存：`data/cache/tpl/`

---

## 目录结构

```
zhicms/
├── index.php                 # 入口文件
├── composer.json             # Composer 依赖
│
├── ZhiCms/                   # 核心框架
│   ├── core.php              #   框架启动（常量、自动加载、辅助函数）
│   ├── bootstrap.php         #   前置引导（Session、编码、Gzip）
│   ├── base/                 #   基础类库
│   │   ├── App.php           #     应用调度（路由→控制器）
│   │   ├── Config.php        #     配置管理
│   │   ├── Route.php         #     URL 路由
│   │   ├── Hook.php          #     钩子系统
│   │   ├── Controller.php    #     基础控制器
│   │   ├── Model.php         #     基础模型（CRUD + 分页）
│   │   ├── PluginManager.php #     插件管理器
│   │   ├── Template.php      #     Legacy 模板引擎
│   │   ├── ThinkTemplate.php #     Think 引擎适配
│   │   ├── compat/           #     多平台兼容层
│   │   ├── db/               #     数据库驱动（PDO/MySQLi）
│   │   ├── cache/            #     缓存驱动（File/Memcache/Memcached）
│   │   └── plugin/           #     插件基类
│   └── ext/                  #   扩展工具库
│       ├── Install.php       #     数据库安装
│       ├── Tjk.php           #     淘客 API 聚合层
│       ├── Tjk/Dtk.php       #     大淘客 API
│       ├── Tjk/Hdk.php       #     好单库 API
│       ├── Http.php          #     HTTP 请求库
│       ├── Upload.php        #     文件上传
│       ├── Image.php         #     图片处理
│       ├── Auth.php          #     权限认证
│       ├── Email.php         #     邮件发送
│       ├── Dbbak.php         #     数据库备份
│       ├── Page.php          #     分页组件
│       ├── Encrypter.php     #     加密解密
│       └── Pinyin.php        #     中文转拼音
│
├── app/                      # 应用模块
│   ├── index/                #   前台（24个控制器）
│   │   ├── controller/       #     首页/搜索/详情/社区/用户/移动端
│   │   └── view/             #     前台模板（51个HTML）
│   ├── manage/               #   后台（15个控制器）
│   │   ├── controller/       #     仪表盘/文章/商品/插件/设置
│   │   └── view/             #     后台模板（45个HTML）
│   ├── api/                  #   API 模块（移动端/小程序）
│   ├── install/              #   安装向导
│   ├── base/                 #   基础共享模块
│   │   ├── controller/       #     基础控制器/错误处理
│   │   ├── model/            #     基础模型
│   │   └── hook/             #     系统钩子
│   ├── go/                   #   商品跳转模块
│   └── plug/                 #   插件前端模块
│
├── data/                     # 数据目录
│   └── config/               #   配置文件
│       ├── db.php            #     数据库配置
│       ├── global.php        #     全局配置
│       ├── rule.php          #     路由规则
│       ├── siteconfig.php    #     站点设置
│       ├── seo.php           #     SEO 配置
│       ├── apiset.php        #     API 密钥
│       ├── version.php       #     版本号
│       ├── zhicms.sql        #     完整建表 SQL
│       └── install.lock      #     安装锁文件
│
├── plugins/                  # 插件目录
├── public/                   # 静态资源
│   ├── web/                  #   PC 端（CSS/JS/图片）
│   ├── h5/                   #   H5 移动端（Swiper/Vue/WeUI）
│   ├── admin/                #   后台资源（LayUI）
│   ├── assets/               #   公共资源（FontAwesome/JS库）
│   ├── global/               #   全局资源（编辑器/弹窗/验证）
│   └── vendor/               #   前端依赖（Vue.js）
│
├── upload/                   # 用户上传
├── vendor/                   # Composer 依赖
│   └── topthink/             #   ThinkORM + ThinkCache + ThinkTemplate
└── mini/                     # 微信小程序源码（uni-app）
```

---

## 技术栈

| 层级 | 方案 |
|------|------|
| **框架** | ZhiCms Framework（自研轻量 MVC） |
| **ORM** | ThinkORM 2.x（ThinkPHP 组件） |
| **模板引擎** | ThinkTemplate 2.x + Legacy 双引擎 |
| **缓存** | ThinkCache 2.x（File / Memcache / Memcached） |
| **数据库** | MySQL，PDO / MySQLi 双驱动，支持读写分离 |
| **代码组织** | PSR-4 + classmap 混合自动加载 |
| **后台 UI** | LayUI |
| **PC 端** | jQuery + 模板渲染 |
| **H5 端** | Vue 3 + Swiper + WeUI |
| **小程序** | uni-app |
| **第三方 API** | 大淘客 (DTK) + 好单库 (HDK) |

---

## Web 服务器配置

### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/zhicms;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问 data 目录
    location ~ /data/ {
        deny all;
    }
}
```

### Apache

项目已内置 `.htaccess` 文件，确保 Apache 开启了 `mod_rewrite` 模块。

### IIS

导入 `web.config` 或手动配置 URL Rewrite 模块。

---

## 安全建议

1. **安装后删除** `/app/install/` 目录
2. **禁止直接访问** `data/` 目录（Nginx 添加 deny 规则）
3. **修改管理员密码**，使用强密码
4. **定期备份** `data/config/` 和数据库
5. **及时更新**插件和框架版本
6. **关闭调试模式**：生产环境将 `rule.php` 中 `DEBUG` 设为 0
7. **设置 PHP 安全配置**：`open_basedir` 限制访问范围

---

## 常见问题

### 安装相关

**Q: 安装时提示"数据库连接失败"？**  
检查主机地址、端口、用户名、密码是否正确，确认 MySQL 服务已启动。如果数据库不存在，安装向导会自动创建。

**Q: 安装后访问页面空白？**  
检查 `data/config/db.php` 数据库配置是否正确，查看 `data/log/` 错误日志，确认 PHP 版本 >= 7.0。

**Q: 如何重新安装？**  
删除 `data/config/install.lock` 文件，访问网站会自动跳转到安装向导。

### 运营相关

**Q: 如何对接淘宝客？**  
后台 → 设置 → API 配置，填入大淘客或好单库的 AppKey 和 AppSecret，以及淘宝联盟 PID。

**Q: 商品没有佣金？**  
确保已配置正确的 PID，转链方式与 API 密钥匹配（大淘客密钥 → DTK 转链，好单库密钥 → HDK 转链）。

**Q: 如何添加新商品？**  
方式一：后台 → 联盟库 → 搜索商品 → 点击"采集"按钮入库  
方式二：后台 → 联盟库 → "批量更新"拉取最新商品

**Q: 如何发布文章？**  
方式一：手动发布 — 后台 → 文章管理 → 添加文章  
方式二：一键采集 — 后台 → 文章管理 → 点击"采集"，自动从好单库素材库拉取文案生成文章

### 技术相关

**Q: 如何切换模板引擎？**  
修改 `data/config/global.php`，`TPL.ENGINE` 设为 `legacy` 或 `think`。

**Q: 如何添加自定义路由规则？**  
后台 → 设置 → URL 规则，添加正则匹配规则。

**Q: 如何开发插件？**  
在 `plugins/` 下创建目录，添加 `plugin.json` 和 `Plugin.php`，继承 `BasePlugin` 并实现 `register()` 方法。

**Q: 如何开启调试模式？**  
`data/config/rule.php` 中设 `DEBUG` 为 1，错误信息会直接在页面显示。

**Q: 如何清除缓存？**  
方式一：后台首页 → 清除缓存按钮  
方式二：手动删除 `data/cache/` 目录下文件

---

## 更新日志

### v5.0.0

- 重构框架核心，全面兼容 PHP 7.0 ~ 8.x
- 新增四合一插件兼容引擎（原生 + WP + ZB + Emlog）
- 新增微社区 v2 系统（微博化帖子 + 多图 + 商品卡片）
- 新增图形化安装向导（环境检测 + 一键安装）
- 新增 AI 开放平台（多模型管理 + 对话 + 图像生成）
- 新增 AI 购物助手（智能导购 + 全网比价）
- 双模板引擎支持（Legacy + ThinkTemplate）
- 微信小程序（uni-app）
- 优化商品采集与批量更新逻辑
- 完善 SEO 配置（各页面独立 TDK）
- 新增 WebP 上传自动转换
- 安全管理增强（开放重定向验证、CSRF 防护）
