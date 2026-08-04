<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
use \app\common\ConfigStore;

class SetController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

	public function index(){


	$this->checkManageSession();


		if(!\IS_POST){
			$this->pageText=array("基础设置","网站设置");
			// 所有网站配置统一从 ConfigStore（DB）读取
			$siteConfig  = ConfigStore::load('site');
			$seoConfig   = ConfigStore::load('seo');
			$apiConfig   = ConfigStore::load('api');
			$smsConfig   = ConfigStore::load('sms');
			$aichatCfg   = ConfigStore::load('aichat');
			// 前端 AI 对话模型由「AI 开放平台」统一管理：读取对话模型池与当前指定
			$aiChatModels = \app\common\AiService::getModelsByType('chat');
			$aiChatKey    = \app\common\AiService::getCurrentChatKey();
			$seopushCfg  = ConfigStore::load('seopush');
			// rule 保持文件
			include \CONFIG_PATH . 'rule.php';
			// 推送日志保持文件
			$logFile = \CONFIG_PATH . 'seopush_log.json';
			$this->pushLog = is_file($logFile) ? json_decode(file_get_contents($logFile), true) : array();
			$this->ret = array_merge($siteConfig, $seoConfig, $apiConfig, $smsConfig, $aichatCfg, $seopushCfg);
			$this->assign('aiChatModels', $aiChatModels);
			$this->assign('aiChatKey', $aiChatKey);
			$this->DEBUG=(int)$rule['DEBUG'];
			$this->REWRITE_ON=(int)$rule['REWRITE_ON'];
			$this->moren=$rule['moren'];
			// 当前模板引擎（legacy / think），供“模板引擎”切换页回显
			$tplCfg = \ZhiCms\base\Config::get('TPL');
			$this->tplEngine = isset($tplCfg['ENGINE']) ? $tplCfg['ENGINE'] : 'legacy';

			// 加载互动开关（yun_config 表）合并到 $ret，供"互动设置"标签页使用
			$interactKeys = array('comment_on', 'forum_on', 'comment_anonymous', 'comment_check', 'comment_interval');
			$in = implode(',', array_fill(0, count($interactKeys), '?'));
			$cfgRows = obj("api/ApiData")->thisQuery(
				"SELECT `key`, `value` FROM `{pre}config` WHERE `key` IN ({$in})",
				$interactKeys
			);
			$interactDefaults = array(
				'comment_on' => '1', 'forum_on' => '1',
				'comment_anonymous' => '1', 'comment_check' => '0', 'comment_interval' => '60',
			);
			if (!empty($cfgRows)) {
				foreach ($cfgRows as $r) {
					$interactDefaults[$r['key']] = $r['value'];
				}
			}
			$this->ret = array_merge($this->ret, $interactDefaults);

		    $this->display();
		}else{
			// 保存网站基础配置到 DB
			$Siteinfo = array(
				'sitename'       => $_POST['sitename']       ?? '',
				'hosturl'        => $_POST['hosturl']        ?? '',
				'logo'           => $_POST['logo']           ?? '',
				'ewm'            => $_POST['ewm']            ?? '',
				'apiurl'         => $_POST['apiurl']         ?? '',
				'download'       => $_POST['download']       ?? '',
				'sitekeywords'   => $_POST['sitekeywords']   ?? '',
				'sitedescription'=> $_POST['sitedescription']?? '',
				'closed'         => $_POST['closed']         ?? '0',
				'closemsg'       => $_POST['closemsg']       ?? '',
				'cachetime'      => $_POST['cachetime']      ?? '3600',
				'pagelimit'      => $_POST['pagelimit']      ?? '20',
				'defaultimg'     => $_POST['defaultimg']     ?? '',
				'cdnurl'         => $_POST['cdnurl']         ?? '',
				'watermark'      => $_POST['watermark']      ?? '0',
				'watermarktext'  => $_POST['watermarktext']  ?? '',
				'banquan'        => $_POST['banquan']        ?? '',
				'about'          => $_POST['about']          ?? '',
				'beian'          => $_POST['beian']          ?? '',
				'model'          => $_POST['model']          ?? '',
				'key'            => $_POST['key']            ?? '',
				// mobile_style 由「移动端」标签页单独保存，这里不处理，避免基础设置覆盖移动端风格
			);
			ConfigStore::save('site', $Siteinfo);
			ConfigStore::clearCache('site');
			\ZhiCms\ext\AdminLog::write('setting', '保存了网站基础设置');
		echo json_encode(array("info" => "设置成功", "status" => "y"));
	}

	}

	/**
	 * 模板引擎切换：保存 TPL.ENGINE 到 data/config/global.php
	 * 仅允许 legacy / think 两种取值，采用原位替换，保留文件其余内容（global.php 为受保护配置，
	 * 不整体重写以免破坏与 $rule/$db 的合并逻辑）。
	 */
	public function saveTplEngine(){
		$this->checkManageSession();
		$engine = isset($_POST['engine']) ? trim($_POST['engine']) : '';
		if (!in_array($engine, array('legacy', 'think'), true)) {
			echo json_encode(array('info' => '不支持的模板引擎', 'status' => 'n'));
			return;
		}
		$file = \CONFIG_PATH . 'global.php';
		if (!is_file($file)) {
			echo json_encode(array('info' => '未找到 global.php 配置文件', 'status' => 'n'));
			return;
		}
		$content = file_get_contents($file);
		// 精确替换 TPL 数组内的 'ENGINE' => 'xxx'
		$new = preg_replace(
			"/('ENGINE'\s*=>\s*')(legacy|think)(')/i",
			'$1' . $engine . '$3',
			$content,
			-1,
			$count
		);
		if ($count === 0) {
			echo json_encode(array('info' => '配置项中未找到 ENGINE 字段', 'status' => 'n'));
			return;
		}
		if (file_put_contents($file, $new) === false) {
			echo json_encode(array('info' => '写入 global.php 失败，请检查文件权限', 'status' => 'n'));
			return;
		}
		\ZhiCms\ext\AdminLog::write('setting', '切换模板引擎为：' . $engine);
		echo json_encode(array('info' => '模板引擎已切换为：' . ($engine === 'think' ? 'ThinkTemplate（真·ThinkPHP 引擎）' : 'ZhiCms 自研引擎'), 'status' => 'y'));
	}

	public function url(){


	$this->checkManageSession();


		if(!\IS_POST){
			$this->pagetext=array("基础设置","自定义rul");

			include \CONFIG_PATH . 'rule.php';
			$keys=array_keys($rule['REWRITE_RULE']);

			$this->DEBUG=(int)$rule['DEBUG'];
			$this->REWRITE_ON=(int)$rule['REWRITE_ON'];
			$this->moren=$rule['moren'];
			$this->ret=$keys;

		    $this->display();
		}else{

			// 兼容新版后台(1/0)与旧版后台(true/false)两种提交值：开启=1，关闭=0
			$DEBUG = (!empty($_POST['DEBUG']) && $_POST['DEBUG'] != '0' && $_POST['DEBUG'] != 'false') ? 1 : 0;
			$REWRITE_ON = (!empty($_POST['REWRITE_ON']) && $_POST['REWRITE_ON'] != '0' && $_POST['REWRITE_ON'] != 'false') ? 1 : 0;
			$moren = isset($_POST['moren']) ? $_POST['moren'] : '';

			$rule = array(
				'ENV' => 'global',
				'DEBUG' => $DEBUG,
				'LOG_ON' => false,
				'LOG_PATH' => '\ROOT_PATH . \'data/log/\'',
				'TIMEZONE' => 'PRC',
				'moren' => $moren,
				'REWRITE_ON' => $REWRITE_ON,
				'REWRITE_RULE' => array(
					// ===== 文章资讯 =====
					'index.html' => 'index/index/index',
					'install.html' => 'install/index/index',
					'cat-<nav>.html' => 'index/index/index/nav=<nav>',
					'list-<cid>.html' => 'index/index/index/list=<cid>',
					'archive-<ym>.html' => 'index/index/index/ym=<ym>',
					'view-<id>.html' => 'index/index/view/id=<id>',
					'article-<id>.html' => 'index/index/view/id=<id>',
					// ===== 微社区（forum）=====
					'forum.html' => 'index/forum/index',
					'forum-b<bid>.html' => 'index/forum/index/bid=<bid>',
					'group-<gid>.html' => 'index/forum/group/gid=<gid>',
					'topic-<id>.html' => 'index/forum/view/id=<id>',
					'bbs.html' => 'index/forum/index',
					'bbs-b<bid>.html' => 'index/forum/index/bid=<bid>',
					'bbs-group-<gid>.html' => 'index/forum/group/gid=<gid>',
					'bbs-topic-<id>.html' => 'index/forum/view/id=<id>',
					// ===== 商品 / 优惠券 =====
					'brand.html' => 'index/brand/index',
					'brand-view-<id>.html' => 'index/brand/view/id=<id>',
					'cheaps.html' => 'index/cheaps/index',
					'cheaps-<id>.html' => 'index/cheaps/index/id=<id>',
					'rank.html' => 'index/rank/index',
					'hot.html' => 'index/hot/index',
					'detail-<id>.html' => 'index/view/detail/id=<id>',
					'vip-<id>.html' => 'index/view/vip/id=<id>',
					'product-<type>-<id>.html' => 'index/view/view/type=<type>/id=<id>',
					// ===== 搜索 / 单页 / 用户 =====
					'so.html' => 'index/search/index',
					'so-<key>.html' => 'index/search/index/content=<key>',
					'page-<id>.html' => 'index/page/index/id=<id>',
					'app.html' => 'index/page/app',
					'side.html' => 'index/page/side',
					'login.html' => 'index/login/index',
					'register.html' => 'index/login/register',
					'ucenter.html' => 'index/ucenter/index',
					'ucenter-comment.html' => 'index/ucenter/myComment',
					'ucenter-forum.html' => 'index/ucenter/myForum',
					'ucenter-profile.html' => 'index/ucenter/profile',
					'ucenter-pwd.html' => 'index/ucenter/pwd',
					'ai.html' => 'index/aiassistant/chat',
					// ===== 移动端 =====
					'm.html' => 'index/m/index',
					'm-rank.html' => 'index/m/rank',
					'm-cheaps.html' => 'index/m/cheaps',
					'm-search-<key>.html' => 'index/m/search/key=<key>',
					// ===== 跳转 / 转链 =====
					'gotb.html' => 'go/tb/itemiid',
					'goto.html' => 'go/to/url',
					'go.html' => 'go/to/wjp',
				),
			);

			$content = "<?php\r\n\$rule=" . var_export($rule, true) . ";\n";
			$content = str_replace("'\ROOT_PATH . \\'data/log/\\''", '\ROOT_PATH . \'data/log/\'', $content);
			
			$of = fopen(\CONFIG_PATH . 'rule.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

			echo json_encode(array("info" => "设置成功", "status" => "y"));
		}
	}


	public function seo(){



	$this->checkManageSession();


		if(!\IS_POST){
			$this->pagetext=array("基础设置","SEO设置");
			$this->ret=ConfigStore::load('seo');
			$this->display();
			exit;
		}else{
			$SEO = array(
				'index_title' => $_POST['index_title'] ?? '',
				'index_keywords' => $_POST['index_keywords'] ?? '',
				'index_dec' => $_POST['index_dec'] ?? '',
				'brand_title' => $_POST['brand_title'] ?? '',
				'brand_keywords' => $_POST['brand_keywords'] ?? '',
				'brand_dec' => $_POST['brand_dec'] ?? '',
				'rank_title' => $_POST['rank_title'] ?? '',
				'rank_keywords' => $_POST['rank_keywords'] ?? '',
				'rank_dec' => $_POST['rank_dec'] ?? '',
				'cheaps_title' => $_POST['cheaps_title'] ?? '',
				'cheaps_keywords' => $_POST['cheaps_keywords'] ?? '',
				'cheaps_dec' => $_POST['cheaps_dec'] ?? '',
				'view_title' => $_POST['view_title'] ?? '',
				'view_keywords' => $_POST['view_keywords'] ?? '',
				'view_dec' => $_POST['view_dec'] ?? '',
				'search_title' => $_POST['search_title'] ?? '',
				'search_keywords' => $_POST['search_keywords'] ?? '',
				'search_dec' => $_POST['search_dec'] ?? '',
				'm_title' => $_POST['m_title'] ?? '',
				'm_keywords' => $_POST['m_keywords'] ?? '',
				'm_dec' => $_POST['m_dec'] ?? ''
			);
			ConfigStore::save('seo', $SEO);
			ConfigStore::clearCache('seo');
			echo json_encode(array("info" => "设置成功", "status" => "y"));
		}
	}


   public function api(){



   $this->checkManageSession();


      if(!\IS_POST){
        $this->pagetext=array("基础设置","生成高佣API");
        $this->ret=ConfigStore::load('api');
        $this->display();
        exit;
      }else{
       $api = array(
              'tb_appkey' => $_POST['tb_appkey'] ?? '',
              'tb_secretKey' => $_POST['tb_secretKey'] ?? '',
              'tb_pid' => $_POST['tb_pid'] ?? '',
              'ali_appid' => $_POST['ali_appid'] ?? '',
              'ali_appsecretKey' => $_POST['ali_appsecretKey'] ?? '',
              'apiurl' => $_POST['apiurl'] ?? 'https://open.zhicms.vip/',
              'appid' => $_POST['appid'] ?? '',
              'secretkey' => $_POST['secretkey'] ?? '',
              'zhuan' => $_POST['zhuan'] ?? 'dtk',
              'dtk_appkey' => $_POST['dtk_appkey'] ?? '',
              'dtk_appsecret' => $_POST['dtk_appsecret'] ?? '',
              'hdk_appkey' => $_POST['hdk_appkey'] ?? '',
        );
        ConfigStore::save('api', $api);
        ConfigStore::clearCache('api');
        // 同步 apiurl 到 site 配置
        $siteConfig = ConfigStore::load('site');
        $siteConfig['apiurl'] = $api['apiurl'];
        ConfigStore::save('site', $siteConfig);

       echo json_encode(array("info" => "设置成功", "status" => "y"));

      }
   }

    /**
     * AI 对话配置
     * 表单 POST 到 manage/set/aichat。
     * 说明：网站前端 AI 对话的模型由「AI 开放平台」统一管理，此处仅负责：
     *  1) 指定前端对话使用的模型（写入 ai.php 的 ai_chat）
     *  2) 保留小程序导购开关（enabled / token 等向后兼容）
     */
    public function aichat() {

        $this->checkManageSession();

        if (!\IS_POST) {
            $this->redirect('index.php?r=manage/set/index');
            exit;
        }

        // 1) 指定前端对话模型（来自 AI 开放平台的对话模型池）
        $frontKey = isset($_POST['front_chat_key']) ? trim($_POST['front_chat_key']) : '';
        $config = \app\common\AiService::loadConfig();
        $chatModels = \app\common\AiService::getModelsByType('chat');
        // 仅当 key 存在于对话模型池时才写入，避免脏数据
        if ($frontKey !== '' && isset($chatModels[$frontKey])) {
            $config['ai_chat'] = $frontKey;
            \app\common\AiService::saveConfig($config);
        }

        // 2) 小程序导购开关（保留向后兼容，避免影响已有小程序）
        $cfg = array(
            'enabled'       => !empty($_POST['enabled']) ? true : false,
            'theme_color'   => isset($_POST['theme_color']) ? trim($_POST['theme_color']) : '#6C63FF',
            'default_role'  => isset($_POST['default_role']) ? trim($_POST['default_role']) : 'shopping',
            'token'         => isset($_POST['token']) ? trim($_POST['token']) : '',
        );

        ConfigStore::save('aichat', $cfg);
        ConfigStore::clearCache('aichat');
        echo json_encode(array("info" => "AI 设置保存成功", "status" => "y"));
    }

    /**
     * 移动端设置：保存移动端相关配置（原短信设置已合并移除，改用移动端标签页）
     * mobile_style 仍存于 site 配置，与前端 m/ 端读取保持一致
     */
    public function mobile(){
        $this->checkManageSession();
        if(!\IS_POST){
            $this->pagetext=array("基础设置","移动端");
            $this->ret=ConfigStore::load('site');
            $this->display();
            exit;
        }else{
            $site = ConfigStore::load('site');
            $site['mobile_style'] = isset($_POST['mobile_style']) ? trim($_POST['mobile_style']) : '';
            ConfigStore::save('site', $site);
            ConfigStore::clearCache('site');

            echo json_encode(array("info" => "设置成功", "status" => "y"));
        }
    }

    public function upload(){
        // 统一转发到 FileController 上传接口
        $_GET['type'] = 'manage';
        $_GET['field'] = 'file';
        $fileCtrl = new \app\manage\controller\FileController();
        $fileCtrl->upload();
    }

    /**
     * 兼容旧路由：js → seopush
     */
    public function js(){
        $this->seopush();
    }

    public function seopush(){

    $this->checkManageSession();

        if (!\IS_POST) {
            $this->pagetext = array("基础设置", "SEO推送");
            $this->ret = ConfigStore::load('seopush');
            // 最近推送记录
            $logFile = \CONFIG_PATH . 'seopush_log.json';
            $this->pushLog = is_file($logFile) ? json_decode(file_get_contents($logFile), true) : array();
            $this->display();
            exit;
        }

        // 处理推送操作（手动推送 URL）
        if (isset($_POST['action']) && $_POST['action'] === 'push') {
            $push = new \app\common\SeoPush();
            $result = $push->pushUrls();
            echo json_encode($result);
            return;
        }

        // 推送最近 50 篇文章
        if (isset($_POST['action']) && $_POST['action'] === 'push_recent') {
            $push = new \app\common\SeoPush();
            $result = $push->pushRecentArticles(50);
            $total = $result['total'] ?? 0;
            echo json_encode(array(
                'info'   => "已推送最近的 {$total} 篇文章到各搜索引擎（详情见日志）",
                'status' => 'y',
                'detail' => $result['results'] ?? array(),
            ));
            return;
        }

        // 推送全站核心页面
        if (isset($_POST['action']) && $_POST['action'] === 'push_all') {
            $push = new \app\common\SeoPush();
            $result = $push->pushAll();
            $total = $result['total'] ?? 0;
            echo json_encode(array(
                'info'   => "已推送全站 {$total} 个页面到各搜索引擎（详情见日志）",
                'status' => 'y',
                'detail' => $result['results'] ?? array(),
            ));
            return;
        }

        // 清空推送日志
        if (isset($_POST['action']) && $_POST['action'] === 'clear_log') {
            $logFile = \CONFIG_PATH . 'seopush_log.json';
            file_put_contents($logFile, '[]');
            echo json_encode(array('info' => '推送日志已清空', 'status' => 'y'));
            return;
        }

        // 保存设置
        $pu = array(
            'baidu_token' => isset($_POST['baidu_token']) ? trim($_POST['baidu_token']) : '',
            'baidu_enabled' => isset($_POST['baidu_enabled']) ? '1' : '0',
            'bing_apikey' => isset($_POST['bing_apikey']) ? trim($_POST['bing_apikey']) : '',
            'bing_enabled' => isset($_POST['bing_enabled']) ? '1' : '0',
            'weibo_token' => isset($_POST['weibo_token']) ? trim($_POST['weibo_token']) : '',
            'weibo_enabled' => isset($_POST['weibo_enabled']) ? '1' : '0',
            'auto_push' => isset($_POST['auto_push']) ? '1' : '0',
            'push_on_save' => isset($_POST['push_on_save']) ? '1' : '0',
        );

        ConfigStore::save('seopush', $pu);
        ConfigStore::clearCache('seopush');
        echo json_encode(array("info" => "SEO推送设置保存成功", "status" => "y"));
    }

    /**
     * 配置迁移：将旧 data/config/*.php 文件一次性导入 DB
     * 访问：index.php?r=manage/set/migrateConfig
     */
    public function migrateConfig() {
        $this->checkManageSession();
        require_once \CONFIG_PATH . 'migrate_to_db.php';
        $result = migrate_all_to_db();
        
        $success = $result['success'];
        $skipped = $result['skipped'];
        $errors  = !empty($result['errors']) ? ' 错误：' . implode('; ', $result['errors']) : '';
        
        echo json_encode(array(
            'info'   => "迁移完成：成功导入 {$success} 组，跳过 {$skipped} 组。{$errors}",
            'status' => empty($result['errors']) ? 'y' : 'n',
        ));
    }

    /**
     * 互动设置：评论/社区 总开关 + 评论审核/间隔/匿名
     * 配置项写入 yun_config 表（key/value/desc）
     */
    public function interact(){

        $this->checkManageSession();

        if (!\IS_POST) {
            $this->pagetext = array("基础设置", "互动设置");
            $this->pageText = array("基础设置", "互动设置");

            $keys = array('comment_on', 'forum_on', 'comment_anonymous', 'comment_check', 'comment_interval',
                'user_reg_captcha', 'user_email_verify', 'user_show_login');
            $in = implode(',', array_fill(0, count($keys), '?'));

            // 使用 thisQuery 参数化查询
            $rows = obj("api/ApiData")->thisQuery(
                "SELECT `key`, `value` FROM `{pre}config` WHERE `key` IN ({$in})",
                $keys
            );

            $defaults = array(
                'comment_on' => '1',
                'forum_on' => '1',
                'comment_anonymous' => '1',
                'comment_check' => '0',
                'comment_interval' => '60',
                'user_reg_captcha' => '1',
                'user_email_verify' => '0',
                'user_show_login' => '1',
            );
            $config = $defaults;
            if (!empty($rows)) {
                foreach ($rows as $r) {
                    $config[$r['key']] = $r['value'];
                }
            }
            // 兜底：缺失项补默认值
            foreach ($defaults as $k => $v) {
                if (!isset($config[$k]) || $config[$k] === '') {
                    $config[$k] = $v;
                }
            }
            $this->ret = $config;
            $this->display();
            exit;
        } else {
            // 防止缺失表：执行前做容错（若 yun_config 不存在则建表）
            obj("api/ApiData")->executeQuery(
                "CREATE TABLE IF NOT EXISTS `{pre}config` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `key` varchar(100) NOT NULL DEFAULT '',
                  `value` text,
                  `desc` varchar(255) NOT NULL DEFAULT '',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `key` (`key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $settings = array(
                'comment_on'       => array('value' => !empty($_POST['comment_on']) ? '1' : '0', 'desc' => '评论功能总开关 1开/0关'),
                'forum_on'         => array('value' => !empty($_POST['forum_on']) ? '1' : '0', 'desc' => '社区功能总开关 1开/0关'),
                'comment_anonymous' => array('value' => !empty($_POST['comment_anonymous']) ? '1' : '0', 'desc' => '允许未登录评论 1允许/0禁止'),
                'comment_check'    => array('value' => !empty($_POST['comment_check']) ? '1' : '0', 'desc' => '评论需要审核 1是/0否'),
                'comment_interval' => array('value' => (string)max(0, intval($_POST['comment_interval'] ?? 60)), 'desc' => '评论间隔秒数（防刷）'),
                'user_reg_captcha' => array('value' => !empty($_POST['user_reg_captcha']) ? '1' : '0', 'desc' => '注册开启图形验证码 1开/0关'),
                'user_email_verify' => array('value' => !empty($_POST['user_email_verify']) ? '1' : '0', 'desc' => '注册需要邮箱验证 1是/0否（当前仅预留）'),
                'user_show_login'  => array('value' => !empty($_POST['user_show_login']) ? '1' : '0', 'desc' => '前台显示用户登录/注册入口 1显示/0隐藏'),
            );

            foreach ($settings as $k => $item) {
                // upsert：INSERT ... ON DUPLICATE KEY UPDATE
                obj("api/ApiData")->executeQuery(
                    "INSERT INTO `{pre}config` (`key`, `value`, `desc`) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `desc` = VALUES(`desc`)",
                    array($k, $item['value'], $item['desc'])
                );
            }

            echo json_encode(array("info" => "互动设置保存成功", "status" => "y"));
        }
    }

}
