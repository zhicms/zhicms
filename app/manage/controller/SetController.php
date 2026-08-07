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

			// 统一「安全 Key」：不存在则自动生成（用于火车头/简数采集免登、在线升级、小程序/App API 等）
			$securityKey = isset($siteConfig['security_key']) ? trim((string)$siteConfig['security_key']) : '';
			if ($securityKey === '') {
			    $securityKey = $this->generateSecurityKey();
			    $siteConfig['security_key'] = $securityKey;
			    ConfigStore::save('site', $siteConfig);
			    ConfigStore::clearCache('site');
			}
			$this->securityKey = $securityKey;
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
				// 统一安全 Key：仅在显式提交时保存（重置由 resetSecurityKey 单独处理），避免误覆盖自动生成值
				'security_key'   => isset($_POST['security_key']) ? trim($_POST['security_key']) : '',
				// mobile_style 由「移动端」标签页单独保存，这里不处理，避免基础设置覆盖移动端风格
			);
			// 若未提交 security_key（老表单/其它方式），回退为现有值，避免被清空
			if (empty($Siteinfo['security_key'])) {
			    $oldSite = ConfigStore::load('site');
			    $Siteinfo['security_key'] = isset($oldSite['security_key']) ? $oldSite['security_key'] : '';
			}
			ConfigStore::save('site', $Siteinfo);
			ConfigStore::clearCache('site');
			\ZhiCms\ext\AdminLog::write('setting', '保存了网站基础设置');
		echo json_encode(array("info" => "设置成功", "status" => "y"));
	}

	}

	/**
	 * 生成安全 Key：40 位随机字母数字，足够熵防暴力猜测
	 */
	private function generateSecurityKey() {
	    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	    $key = '';
	    $max = strlen($alphabet) - 1;
	    for ($i = 0; $i < 40; $i++) {
	        $key .= $alphabet[random_int(0, $max)];
	    }
	    return $key;
	}

	/**
	 * 重置安全 Key：后台点击「重置」时调用，生成新 key 并保存
	 * 访问：index.php?r=manage/set/resetSecurityKey
	 */
	public function resetSecurityKey() {
	    $this->checkManageSession();
	    if (!\IS_POST) {
	        echo json_encode(array('info' => '非法请求', 'status' => 'n'));
	        return;
	    }
	    $newKey = $this->generateSecurityKey();
	    $siteConfig = ConfigStore::load('site');
	    if (!is_array($siteConfig)) $siteConfig = array();
	    $siteConfig['security_key'] = $newKey;
	    ConfigStore::save('site', $siteConfig);
	    ConfigStore::clearCache('site');
	    \ZhiCms\ext\AdminLog::write('setting', '重置了网站安全 Key');
	    echo json_encode(array('info' => '安全 Key 已重置', 'status' => 'y', 'security_key' => $newKey));
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
					// 商品购买转链（京东/拼多多/唯品会/淘宝统一入口，由 RedirectController 二次转链生成佣金链接）
					// 使用 buy- 前缀，避免与 vip-<id>（商品详情页）等规则冲突
					'buy-<platform>.html' => 'index/redirect/jump/platform=<platform>',
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
   $this->pageText=array("基础设置","联盟授权");

      if(!\IS_POST){
        $this->pagetext=array("基础设置","生成高佣API");
        $this->ret=ConfigStore::load('api');
        $this->ensureUnionAuthTable();
        // 联盟授权：预置淘宝/京东/拼多多/唯品会四个占位，表格内直接管理
        $this->unionPlatforms = $this->unionAuthPlatforms();
        $this->unionTypeList = $this->unionTypeList();
        $this->unionAuthList = $this->getUnionAuthList(true);
        $this->display();
        exit;
      }else{
       $api = array(
              // 兼容保留旧字段（新 UI 已迁移到联盟设置，但若配置中已有值不丢失）
              'tb_appkey' => $_POST['tb_appkey'] ?? ($this->ret['tb_appkey'] ?? ''),
              'tb_secretKey' => $_POST['tb_secretKey'] ?? ($this->ret['tb_secretKey'] ?? ''),
              'tb_pid' => $_POST['tb_pid'] ?? ($this->ret['tb_pid'] ?? ''),
              'ali_appid' => $_POST['ali_appid'] ?? ($this->ret['ali_appid'] ?? ''),
              'ali_appsecretKey' => $_POST['ali_appsecretKey'] ?? ($this->ret['ali_appsecretKey'] ?? ''),
              'apiurl' => $_POST['apiurl'] ?? ($this->ret['apiurl'] ?? 'https://open.zhicms.vip/'),
              'appid' => $_POST['appid'] ?? ($this->ret['appid'] ?? ''),
              'secretkey' => $_POST['secretkey'] ?? ($this->ret['secretkey'] ?? ''),
              'zhuan' => $_POST['zhuan'] ?? ($this->ret['zhuan'] ?? 'dtk'),
              'dtk_appkey' => $_POST['dtk_appkey'] ?? ($this->ret['dtk_appkey'] ?? ''),
              'dtk_appsecret' => $_POST['dtk_appsecret'] ?? ($this->ret['dtk_appsecret'] ?? ''),
              'hdk_appkey' => $_POST['hdk_appkey'] ?? ($this->ret['hdk_appkey'] ?? ''),
              // 保留手动维护的字段（不随后台保存丢失）
              'hdk_union_id' => $_POST['hdk_union_id'] ?? ($this->ret['hdk_union_id'] ?? ''),
              'hdk_vip_pid' => $_POST['hdk_vip_pid'] ?? ($this->ret['hdk_vip_pid'] ?? ''),
              'hdk_pdd_pid' => $_POST['hdk_pdd_pid'] ?? ($this->ret['hdk_pdd_pid'] ?? ''),
              // 拼多多官方多多进宝 SDK 配置
              'pdd_client_id' => $_POST['pdd_client_id'] ?? ($this->ret['pdd_client_id'] ?? ''),
              'pdd_client_secret' => $_POST['pdd_client_secret'] ?? ($this->ret['pdd_client_secret'] ?? ''),
              'pdd_pid' => $_POST['pdd_pid'] ?? ($this->ret['pdd_pid'] ?? ''),
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

    // ======================= 联盟授权（集中 API / 联盟账号授权） =======================
    /**
     * 确保联盟授权表存在（兼容旧库：字段缺失则 ALTER）
     */
    private function ensureUnionAuthTable() {
        try {
            obj("api/ApiData")->executeQuery(
                "CREATE TABLE IF NOT EXISTS `{pre}union_auth` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `platform` varchar(20) NOT NULL DEFAULT '' COMMENT '平台标识 tb/jd/pdd/vip',
                  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '授权名称/备注',
                  `pid` varchar(100) NOT NULL DEFAULT '' COMMENT '推广位 PID',
                  `free_pid` varchar(100) NOT NULL DEFAULT '' COMMENT '免单专用 PID',
                  `app_key` varchar(255) NOT NULL DEFAULT '' COMMENT '平台 AppKey',
                  `app_secret` varchar(255) NOT NULL DEFAULT '' COMMENT '平台 AppSecret',
                  `auth_type` varchar(30) NOT NULL DEFAULT '' COMMENT '授权类型',
                  `union_type` varchar(20) NOT NULL DEFAULT '' COMMENT '联盟类型 dtk/hdk/pdd',
                  `bind_tuanzhang` tinyint(1) NOT NULL DEFAULT 0 COMMENT '绑定团长 0/1',
                  `order_sync` tinyint(1) NOT NULL DEFAULT 0 COMMENT '订单同步 0/1',
                  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认 0/1',
                  `invite_code` varchar(100) NOT NULL DEFAULT '' COMMENT '渠道邀请码',
                  `expire_time` varchar(30) NOT NULL DEFAULT '' COMMENT '到期时间',
                  `add_time` int(11) NOT NULL DEFAULT 0,
                  PRIMARY KEY (`id`),
                  KEY `platform` (`platform`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            // 兼容旧库：检测并补字段
            $cols = array();
            try {
                $rows = obj("api/ApiData")->thisQuery("SHOW COLUMNS FROM `{pre}union_auth`");
                foreach ($rows as $r) { $cols[$r['Field']] = true; }
            } catch (\Exception $e2) { /* ignore */ }
            $adds = array(
                'free_pid'   => "ALTER TABLE `{pre}union_auth` ADD COLUMN `free_pid` varchar(100) NOT NULL DEFAULT '' AFTER `pid`",
                'app_key'    => "ALTER TABLE `{pre}union_auth` ADD COLUMN `app_key` varchar(255) NOT NULL DEFAULT '' AFTER `free_pid`",
                'app_secret' => "ALTER TABLE `{pre}union_auth` ADD COLUMN `app_secret` varchar(255) NOT NULL DEFAULT '' AFTER `app_key`",
                'union_type' => "ALTER TABLE `{pre}union_auth` ADD COLUMN `union_type` varchar(20) NOT NULL DEFAULT '' AFTER `auth_type`",
                'beian'      => "ALTER TABLE `{pre}union_auth` ADD COLUMN `beian` tinyint(1) NOT NULL DEFAULT 0 AFTER `union_type`",
            );
            foreach ($adds as $col => $sql) {
                if (!isset($cols[$col])) {
                    try { obj("api/ApiData")->executeQuery($sql); } catch (\Exception $e3) { /* ignore */ }
                }
            }
        } catch (\Exception $e) {
            // 建表失败静默
        }
    }

    /**
     * 联盟类型枚举（联盟设置里的凭证，按联盟区分）
     */
    private function unionTypeList() {
        return array(
            'dtk' => '大淘客',
            'hdk' => '好单库',
            'pdd' => '拼多多自写SDK',
        );
    }

    /**
     * 联盟授权平台枚举（含每个平台「可选联盟」与默认联盟）
     *  淘宝 → 仅大淘客；京东 → 仅好单库；唯品会 → 仅好单库；拼多多 → 自写SDK / 好单库
     */
    private function unionAuthPlatforms() {
        return array(
            'tb'  => array('name' => '淘宝联盟', 'unions' => array('dtk' => '大淘客'), 'default_union' => 'dtk'),
            'jd'  => array('name' => '京东推广位', 'unions' => array('hdk' => '好单库'), 'default_union' => 'hdk'),
            'pdd' => array('name' => '拼多多', 'unions' => array('pdd' => '拼多多自写SDK', 'hdk' => '好单库'), 'default_union' => 'pdd'),
            'vip' => array('name' => '唯品会', 'unions' => array('hdk' => '好单库'), 'default_union' => 'hdk'),
        );
    }

    /**
     * 获取联盟授权列表；若为空且 $withPlaceholder 为真，预置 tb/jd/pdd/vip 四个占位
     */
    private function getUnionAuthList($withPlaceholder = true) {
        $rows = obj("api/ApiData")->thisQuery(
            "SELECT * FROM `{pre}union_auth` ORDER BY FIELD(`platform`,'tb','jd','pdd','vip'), `id` ASC"
        );
        $list = is_array($rows) ? $rows : array();
        if ($withPlaceholder && empty($list)) {
            $platforms = $this->unionAuthPlatforms();
            $inserted = array();
            foreach ($platforms as $pf => $cfg) {
                $data = array(
                    'platform'   => $pf,
                    'name'       => $cfg['name'],
                    'pid'        => '',
                    'free_pid'   => '',
                    'app_key'    => '',
                    'app_secret' => '',
                    'auth_type'  => '',
                    'union_type' => $cfg['default_union'],
                    'add_time'   => time(),
                );
                $id = obj("api/ApiData")->insertData("{pre}union_auth", $data);
                $data['id'] = $id;
                $inserted[] = $data;
            }
            $list = $inserted;
        }
        return $list;
    }

    /**
     * AI 授权平台清单（卡片展示用，与 AiController 保持一致）
     */
    private function aiPlatformList() {
        return array(
            'deepseek'   => array('name' => 'DeepSeek', 'protocol' => 'openai', 'url' => 'https://api.deepseek.com/v1/chat/completions', 'models' => array('deepseek-chat', 'deepseek-reasoner', 'deepseek-coder')),
            'zhipu'      => array('name' => '智谱 AI (GLM)', 'protocol' => 'openai', 'url' => 'https://open.bigmodel.cn/api/paas/v4/chat/completions', 'models' => array('glm-4.7-flash', 'glm-4-air', 'glm-4-airx', 'glm-4-plus', 'glm-4-long', 'glm-4v')),
            'qwen'       => array('name' => '通义千问 (阿里百炼)', 'protocol' => 'openai', 'url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions', 'models' => array('qwen-plus', 'qwen-turbo', 'qwen-max', 'qwen-max-longcontext', 'qwen2.5-7b-instruct', 'qwen2.5-72b-instruct', 'qwen3-235b-a22b')),
            'siliconflow'=> array('name' => '硅基流动 (SiliconFlow)', 'protocol' => 'openai', 'url' => 'https://api.siliconflow.cn/v1/chat/completions', 'models' => array('Qwen/Qwen2.5-7B-Instruct', 'Qwen/Qwen2.5-14B-Instruct', 'deepseek-ai/DeepSeek-R1-Distill-Qwen-7B', 'THUDM/glm-4-9b-chat', 'Qwen/Qwen2.5-72B-Instruct', 'deepseek-ai/DeepSeek-V3', 'deepseek-ai/DeepSeek-R1', 'meta-llama/Llama-3.3-70B-Instruct')),
            'doubao'     => array('name' => '豆包 (火山方舟)', 'protocol' => 'openai', 'url' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions', 'models' => array('doubao-seed-1.6-250615', 'doubao-lite-32k', 'doubao-pro-32k', 'doubao-vision-lite-32k')),
            'kimi'       => array('name' => 'Kimi (Moonshot)', 'protocol' => 'openai', 'url' => 'https://api.moonshot.cn/v1/chat/completions', 'models' => array('moonshot-v1-8k', 'moonshot-v1-32k', 'moonshot-v1-128k', 'moonshot-v1-mini')),
            'minimax'    => array('name' => 'MiniMax', 'protocol' => 'openai', 'url' => 'https://api.minimaxi.com/v1/text/chatcompletion_v2', 'models' => array('MiniMax-Text-01', 'abab6.5s-chat', 'abab6.5t-chat')),
            'stepfun'    => array('name' => '阶跃星辰 (StepFun)', 'protocol' => 'openai', 'url' => 'https://api.stepfun.com/v1/chat/completions', 'models' => array('step-1-flash', 'step-1v-8k', 'step-2-16k')),
            'baichuan'   => array('name' => '百川智能 (Baichuan)', 'protocol' => 'openai', 'url' => 'https://api.baichuan-ai.com/v1/chat/completions', 'models' => array('Baichuan4', 'Baichuan3-Turbo', 'Baichuan2-13B-Chat')),
            'openai'     => array('name' => 'OpenAI', 'protocol' => 'openai', 'url' => 'https://api.openai.com/v1/chat/completions', 'models' => array('gpt-4o', 'gpt-4o-mini', 'gpt-4.1-mini', 'o1', 'o3-mini')),
            'azure'      => array('name' => 'Azure OpenAI', 'protocol' => 'azure', 'url' => 'https://<resource>.openai.azure.com/openai/deployments/<deployment>/chat/completions', 'models' => array('gpt-4o', 'gpt-35-turbo', 'gpt-4.1')),
            'gemini'     => array('name' => 'Google Gemini', 'protocol' => 'gemini', 'url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent', 'models' => array('gemini-1.5-pro', 'gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-2.5-pro')),
            'anthropic'  => array('name' => 'Anthropic Claude', 'protocol' => 'anthropic', 'url' => 'https://api.anthropic.com/v1/messages', 'models' => array('claude-3-5-sonnet-20241022', 'claude-3-opus-20240229', 'claude-3-haiku-20240307', 'claude-3-5-haiku')),
            'ernie'      => array('name' => '百度文心 ERNIE (千帆V2)', 'protocol' => 'openai', 'url' => 'https://qianfan.baidubce.com/v2/chat/completions', 'models' => array('ernie-4.5-turbo-128k', 'ernie-4.0-8k', 'ernie-3.5-8k', 'ernie-speed-128k', 'ernie-speed-8k')),
            'xinghuo'    => array('name' => '科大讯飞星火', 'protocol' => 'openai', 'url' => 'https://spark-api-open.xf-yun.com/v1/chat/completions', 'models' => array('lite', 'generalv3', 'pro-128k', 'generalv3.5', 'max-32k', '4.0Ultra')),
        );
    }

    /**
     * 联盟授权 / AI 授权 集中授权中心
     */
    public function unionAuth() {
        $this->checkManageSession();
        $this->ensureUnionAuthTable();

        if (!\IS_POST) {
            $this->pagetext = array("电商宝库", "授权中心");
            $this->platforms = $this->unionAuthPlatforms();
            // 当前联盟授权列表（空则预置淘宝/京东/拼多多/唯品会四个占位）
            $this->list = $this->getUnionAuthList(true);
            // 同步各联盟平台授权状态（是否有已启用授权）
            $status = array();
            foreach (array_keys($this->platforms) as $pf) {
                $status[$pf] = 0;
            }
            foreach ($this->list as $row) {
                if (!empty($row['pid']) || !empty($row['app_key'])) {
                    $status[$row['platform']] = 1;
                }
            }
            $this->status = $status;

            $this->display();
            exit;
        }

        // POST：保存（新增或更新）
        $id = intval($this->arg("id", 0));
        $platform = strtolower(trim($this->arg("platform", '')));
        $platforms = $this->unionAuthPlatforms();
        if (!isset($platforms[$platform])) {
            echo json_encode(array("info" => "请选择正确的授权类型（平台）", "status" => "n"));
            return;
        }
        $data = array(
            'platform'        => $platform,
            'name'            => trim($this->arg("name", '')),
            'pid'             => trim($this->arg("pid", '')),
            'free_pid'        => trim($this->arg("free_pid", '')),
            'app_key'         => trim($this->arg("app_key", '')),
            'app_secret'      => trim($this->arg("app_secret", '')),
            'auth_type'       => trim($this->arg("auth_type", '')),
            'bind_tuanzhang'  => $this->arg("bind_tuanzhang", 0) ? 1 : 0,
            'order_sync'      => $this->arg("order_sync", 0) ? 1 : 0,
            'is_default'      => $this->arg("is_default", 0) ? 1 : 0,
            'invite_code'     => trim($this->arg("invite_code", '')),
            'expire_time'     => trim($this->arg("expire_time", '')),
        );
        // 设为默认时，同平台其它授权取消默认
        if ($data['is_default']) {
            obj("api/ApiData")->executeQuery(
                "UPDATE `{pre}union_auth` SET `is_default`=0 WHERE `platform`=?",
                array($platform)
            );
        }
        if ($id > 0) {
            obj("api/ApiData")->dataUpdate("{pre}union_auth", $data, "`id`=?", array($id));
        } else {
            $data['add_time'] = time();
            obj("api/ApiData")->insertData("{pre}union_auth", $data);
        }
        \ZhiCms\ext\AdminLog::write('union_auth', '保存了联盟授权（' . $platforms[$platform] . '）');
        echo json_encode(array("info" => "授权保存成功", "status" => "y"));
    }

    /**
     * 联盟授权新增 / 编辑页（独立页面，url: manage/set/unionAuthAdd，编辑传 id）
     */
    public function unionAuthAdd() {
        $this->checkManageSession();
        $this->ensureUnionAuthTable();
        $this->platforms = $this->unionAuthPlatforms();

        $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
        $this->row = array(
            'id' => 0, 'platform' => 'tb', 'name' => '', 'pid' => '', 'free_pid' => '',
            'app_key' => '', 'app_secret' => '', 'auth_type' => '', 'invite_code' => '', 'expire_time' => '',
        );
        $this->isEdit = false;
        if ($id > 0) {
            $rows = obj("api/ApiData")->thisQuery("SELECT * FROM `{pre}union_auth` WHERE `id`=?", array($id));
            if (!empty($rows)) {
                $this->row = array_merge($this->row, $rows[0]);
                $this->isEdit = true;
            }
        }

        if (\IS_POST) {
            $platform = strtolower(trim($this->arg("platform", '')));
            if (!isset($this->platforms[$platform])) {
                echo json_encode(array("info" => "请选择正确的平台", "status" => "n"));
                return;
            }
            // 校验所选联盟是否属于该平台可选范围
            $unionType = strtolower(trim($this->arg("union_type", '')));
            $allowUnions = $this->platforms[$platform]['unions'];
            if (!isset($allowUnions[$unionType])) {
                echo json_encode(array("info" => "该平台仅支持联盟：" . implode('/', $allowUnions), "status" => "n"));
                return;
            }
            $data = array(
                'platform'    => $platform,
                'union_type'  => $unionType,
                'name'        => trim($this->arg("name", '')),
                'pid'         => trim($this->arg("pid", '')),
                'free_pid'    => trim($this->arg("free_pid", '')),
                'app_key'     => trim($this->arg("app_key", '')),
                'app_secret'  => trim($this->arg("app_secret", '')),
                'auth_type'   => $unionType,
                'invite_code' => trim($this->arg("invite_code", '')),
                'expire_time' => trim($this->arg("expire_time", '')),
            );
            if ($data['name'] === '' || $data['pid'] === '') {
                echo json_encode(array("info" => "名称、PID 必填", "status" => "n"));
                return;
            }
            try {
                if ($id > 0) {
                    obj("api/ApiData")->dataUpdate("{pre}union_auth", $data, "`id`=?", array($id));
                } else {
                    $data['add_time'] = time();
                    obj("api/ApiData")->insertData("{pre}union_auth", $data);
                }
                echo json_encode(array("info" => "保存成功", "status" => "y"));
            } catch (\Exception $ex) {
                echo json_encode(array("info" => "保存失败：" . $ex->getMessage(), "status" => "n"));
            }
            return;
        }

        $this->pagetext = array("电商宝库", $id > 0 ? "修改授权" : "新增授权");
        $this->display();
    }

    /**
     * 联盟授权详情（弹窗编辑时拉取，返回 JSON）
     */
    public function unionAuthInfo() {
        $this->checkManageSession();
        $id = intval($this->arg("id", 0));
        if ($id <= 0) {
            echo json_encode(array("info" => "参数错误", "status" => "n"));
            return;
        }
        $rows = obj("api/ApiData")->thisQuery("SELECT * FROM `{pre}union_auth` WHERE `id`=?", array($id));
        if (empty($rows)) {
            echo json_encode(array("info" => "记录不存在", "status" => "n"));
            return;
        }
        echo json_encode(array("status" => "y", "data" => $rows[0]));
    }

    /**
     * 删除联盟授权
     */
    public function unionAuthDelete() {
        $this->checkManageSession();
        $id = intval($this->arg("id", 0));
        if ($id <= 0) {
            echo json_encode(array("info" => "参数错误", "status" => "n"));
            return;
        }
        obj("api/ApiData")->deleteThis("yun_union_auth", "`id`=?", array($id));
        echo json_encode(array("info" => "已删除", "status" => "y"));
    }

    /**
     * 批量删除联盟授权
     */
    public function unionAuthBatchDelete() {
        $this->checkManageSession();
        $idsRaw = $this->arg("ids", "");
        $ids = array();
        foreach (explode(',', $idsRaw) as $v) {
            $v = intval($v);
            if ($v > 0) $ids[] = $v;
        }
        if (!$ids) {
            echo json_encode(array("info" => "请选择要删除的记录", "status" => "n"));
            return;
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        obj("api/ApiData")->executeQuery("DELETE FROM `{pre}union_auth` WHERE `id` IN ($place)", $ids);
        echo json_encode(array("info" => "已批量删除 " . count($ids) . " 条", "status" => "y"));
    }

    /**
     * 设为默认授权
     */
    public function unionAuthDefault() {
        $this->checkManageSession();
        $id = intval($this->arg("id", 0));
        if ($id <= 0) {
            echo json_encode(array("info" => "参数错误", "status" => "n"));
            return;
        }
        $row = obj("api/ApiData")->thisQuery(
            "SELECT `platform` FROM `{pre}union_auth` WHERE `id`=?", array($id)
        );
        if (empty($row)) {
            echo json_encode(array("info" => "记录不存在", "status" => "n"));
            return;
        }
        $platform = $row[0]['platform'];
        obj("api/ApiData")->executeQuery(
            "UPDATE `{pre}union_auth` SET `is_default`=0 WHERE `platform`=?", array($platform)
        );
        obj("api/ApiData")->executeQuery(
            "UPDATE `{pre}union_auth` SET `is_default`=1 WHERE `id`=?", array($id)
        );
        echo json_encode(array("info" => "已设为默认", "status" => "y"));
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
            // M 端自动跳转开关（默认关闭，开启后手机访问跳 m 端、电脑访问电脑版，关闭则前端自适应）
            $site['mobile_redirect'] = isset($_POST['mobile_redirect']) && $_POST['mobile_redirect'] ? 1 : 0;
            ConfigStore::save('site', $site);
            ConfigStore::clearCache('site');
            // 同步写回轻量文件，供 bootstrap.php 在框架加载前读取开关（避免每次请求查 DB）
            $this->syncSiteFile($site);

            echo json_encode(array("info" => "设置成功", "status" => "y"));
        }
    }

    /**
     * 把 site 配置同步写回 data/config/siteconfig.php（保持 $Siteinfo 变量名，兼容 bootstrap 读取）
     */
    private function syncSiteFile(array $site){
        $file = \CONFIG_PATH . 'siteconfig.php';
        if (!is_dir(dirname($file))) return;
        $content = "<?php\r\n\$Siteinfo=" . var_export($site, true) . ";\n";
        @file_put_contents($file, $content);
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
        $migrateFile = \CONFIG_PATH . 'migrate_to_db.php';
        if (!is_file($migrateFile)) {
            echo json_encode(array(
                'info'   => '迁移脚本（migrate_to_db.php）不存在，可能已完成迁移或无需迁移',
                'status' => 'n',
            ));
            return;
        }
        require_once $migrateFile;
        if (!function_exists('migrate_all_to_db')) {
            echo json_encode(array('info' => '迁移函数未定义', 'status' => 'n'));
            return;
        }
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
