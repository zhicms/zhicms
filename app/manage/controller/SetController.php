<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class SetController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

	public function index(){


	$this->checkManageSession();


		if(!IS_POST){
			$this->pageText=array("基础设置","网站设置");
			include CONFIG_PATH . 'siteconfig.php';
			include CONFIG_PATH . 'seo.php';
			include CONFIG_PATH . 'rule.php';
			include CONFIG_PATH . 'apiset.php';
			include CONFIG_PATH . 'sms.php';
			include CONFIG_PATH . 'aichat.php';
			$this->ret=array_merge($Siteinfo, $SEO, $api, $sms, $ai);
			$this->DEBUG=(int)$rule['DEBUG'];
			$this->REWRITE_ON=(int)$rule['REWRITE_ON'];
			$this->moren=$rule['moren'];

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
			include CONFIG_PATH . 'siteconfig.php';
			$Siteinfo['sitename'] = isset($_POST['sitename']) ? $_POST['sitename'] : $Siteinfo['sitename'];
			$Siteinfo['hosturl'] = isset($_POST['hosturl']) ? $_POST['hosturl'] : $Siteinfo['hosturl'];
			$Siteinfo['logo'] = isset($_POST['logo']) ? $_POST['logo'] : $Siteinfo['logo'];
			$Siteinfo['ewm'] = isset($_POST['ewm']) ? $_POST['ewm'] : $Siteinfo['ewm'];
			$Siteinfo['apiurl'] = isset($_POST['apiurl']) ? $_POST['apiurl'] : $Siteinfo['apiurl'];
			$Siteinfo['download'] = isset($_POST['download']) ? $_POST['download'] : $Siteinfo['download'];
			$Siteinfo['sitekeywords'] = isset($_POST['sitekeywords']) ? $_POST['sitekeywords'] : $Siteinfo['sitekeywords'];
			$Siteinfo['sitedescription'] = isset($_POST['sitedescription']) ? $_POST['sitedescription'] : $Siteinfo['sitedescription'];
			$Siteinfo['closed'] = isset($_POST['closed']) ? $_POST['closed'] : $Siteinfo['closed'];
			$Siteinfo['closemsg'] = isset($_POST['closemsg']) ? $_POST['closemsg'] : $Siteinfo['closemsg'];
			$Siteinfo['cachetime'] = isset($_POST['cachetime']) ? $_POST['cachetime'] : $Siteinfo['cachetime'];
			$Siteinfo['pagelimit'] = isset($_POST['pagelimit']) ? $_POST['pagelimit'] : $Siteinfo['pagelimit'];
			$Siteinfo['defaultimg'] = isset($_POST['defaultimg']) ? $_POST['defaultimg'] : $Siteinfo['defaultimg'];
			$Siteinfo['cdnurl'] = isset($_POST['cdnurl']) ? $_POST['cdnurl'] : $Siteinfo['cdnurl'];
			$Siteinfo['watermark'] = isset($_POST['watermark']) ? $_POST['watermark'] : $Siteinfo['watermark'];
			$Siteinfo['watermarktext'] = isset($_POST['watermarktext']) ? $_POST['watermarktext'] : $Siteinfo['watermarktext'];
			$Siteinfo['banquan'] = isset($_POST['banquan']) ? $_POST['banquan'] : $Siteinfo['banquan'];
			$Siteinfo['about'] = isset($_POST['about']) ? $_POST['about'] : $Siteinfo['about'];
			$Siteinfo['beian'] = isset($_POST['beian']) ? $_POST['beian'] : $Siteinfo['beian'];
			$Siteinfo['model'] = isset($_POST['model']) ? $_POST['model'] : $Siteinfo['model'];
			$Siteinfo['key'] = isset($_POST['key']) ? $_POST['key'] : $Siteinfo['key'];
			$Siteinfo['mobile_style'] = isset($_POST['mobile_style']) ? trim($_POST['mobile_style']) : $Siteinfo['mobile_style'];
       
         $content = "<?php\r\n\$Siteinfo=" . var_export($Siteinfo, true) . ";\n";
           $of = fopen(CONFIG_PATH . 'siteconfig.php', 'w');
              if ($of) {
                  fwrite($of, $content);
              }
              fclose($of);
              echo json_encode(array("info" => "设置成功", "status" => "y"));
		}

	}

	public function url(){


	$this->checkManageSession();


		if(!IS_POST){
			$this->pagetext=array("基础设置","自定义rul");

			include CONFIG_PATH . 'rule.php';
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
				'LOG_PATH' => 'ROOT_PATH . \'data/log/\'',
				'TIMEZONE' => 'PRC',
				'moren' => $moren,
				'REWRITE_ON' => $REWRITE_ON,
				'REWRITE_RULE' => array(
					'index.html' => 'index/index/index',
					'view-<id>.html' => 'index/index/view/id=<id>',
					'brand.html' => 'index/brand/index',
					'brand-view-<id>.html' => 'index/brand/view/id=<id>',
					'cheaps.html' => 'index/cheaps/index',
					'cheaps-<id>.html' => 'index/cheaps/index/id=<id>',
					'detail-<id>.html' => 'index/view/detail/id=<id>',
					'vip-<id>.html' => 'index/view/vip/id=<id>',
					'product-<type>-<id>.html' => 'index/view/view/type=<type>/id=<id>',
					'rank.html' => 'index/rank/index',
					'm.html' => 'index/m/index',
					'm-search-<key>.html' => 'index/m/search/key=<key>',
					'gotb.html' => 'go/tb/itemiid',
					'goto.html' => 'go/to/url',
					'go.html' => 'go/to/wjp',
					'so.html' => 'index/search/index',
					'app.html' => 'index/page/app',
					'side.html' => 'index/page/side',
				),
			);

			$content = "<?php\r\n\$rule=" . var_export($rule, true) . ";\n";

			$content = str_replace("'ROOT_PATH . \\'data/log/\\''", 'ROOT_PATH . \'data/log/\'', $content);
			
	  $of = fopen(CONFIG_PATH . 'rule.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

       echo json_encode(array("info" => "设置成功", "status" => "y"));
     


		}
	}


	public function seo(){



	$this->checkManageSession();


		if(!IS_POST){
			$this->pagetext=array("基础设置","SEO设置");
			include CONFIG_PATH . 'seo.php';
			$this->ret=$SEO;
			$this->display();
			exit;
		}else{
			 $SEO = array(
              'index_title' => isset($_POST['index_title']) ? $_POST['index_title'] : '',
              'index_keywords' => isset($_POST['index_keywords']) ? $_POST['index_keywords'] : '',
              'index_dec' => isset($_POST['index_dec']) ? $_POST['index_dec'] : '',
              'brand_title' => isset($_POST['brand_title']) ? $_POST['brand_title'] : '',
              'brand_keywords' => isset($_POST['brand_keywords']) ? $_POST['brand_keywords'] : '',
              'brand_dec' => isset($_POST['brand_dec']) ? $_POST['brand_dec'] : '',
              'rank_title' => isset($_POST['rank_title']) ? $_POST['rank_title'] : '',
              'rank_keywords' => isset($_POST['rank_keywords']) ? $_POST['rank_keywords'] : '',
              'rank_dec' => isset($_POST['rank_dec']) ? $_POST['rank_dec'] : '',
              'cheaps_title' => isset($_POST['cheaps_title']) ? $_POST['cheaps_title'] : '',
              'cheaps_keywords' => isset($_POST['cheaps_keywords']) ? $_POST['cheaps_keywords'] : '',
              'cheaps_dec' => isset($_POST['cheaps_dec']) ? $_POST['cheaps_dec'] : '',
              'view_title' => isset($_POST['view_title']) ? $_POST['view_title'] : '',
              'view_keywords' => isset($_POST['view_keywords']) ? $_POST['view_keywords'] : '',
              'view_dec' => isset($_POST['view_dec']) ? $_POST['view_dec'] : '',
              'search_title' => isset($_POST['search_title']) ? $_POST['search_title'] : '',
              'search_keywords' => isset($_POST['search_keywords']) ? $_POST['search_keywords'] : '',
              'search_dec' => isset($_POST['search_dec']) ? $_POST['search_dec'] : '',
              'm_title' => isset($_POST['m_title']) ? $_POST['m_title'] : '',
              'm_keywords' => isset($_POST['m_keywords']) ? $_POST['m_keywords'] : '',
              'm_dec' => isset($_POST['m_dec']) ? $_POST['m_dec'] : ''
        );
        $content = "<?php\r\n\$SEO=" . var_export($SEO, true) . ";\n";

        $of = fopen(CONFIG_PATH . 'seo.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

       echo json_encode(array("info" => "设置成功", "status" => "y"));

		}
	}


   public function api(){



   $this->checkManageSession();


      if(!IS_POST){
        $this->pagetext=array("基础设置","生成高佣API");
        include CONFIG_PATH . 'apiset.php';
        $this->ret=$api;
        $this->display();
        exit;
      }else{
        $tb_appkey = isset($_POST['tb_appkey']) ? $_POST['tb_appkey'] : '';
        $tb_secretKey = isset($_POST['tb_secretKey']) ? $_POST['tb_secretKey'] : '';
        $tb_pid = isset($_POST['tb_pid']) ? $_POST['tb_pid'] : '';
        $ali_appid = isset($_POST['ali_appid']) ? $_POST['ali_appid'] : '';
        $ali_appsecretKey = isset($_POST['ali_appsecretKey']) ? $_POST['ali_appsecretKey'] : '';
        $apiurl = isset($_POST['apiurl']) ? $_POST['apiurl'] : 'https://open.zhicms.vip/';
        $appid = isset($_POST['appid']) ? $_POST['appid'] : '';
        $secretkey = isset($_POST['secretkey']) ? $_POST['secretkey'] : '';
        $zhuan = isset($_POST['zhuan']) ? $_POST['zhuan'] : 'dtk';
        $dtk_appkey = isset($_POST['dtk_appkey']) ? $_POST['dtk_appkey'] : '';
        $dtk_appsecret = isset($_POST['dtk_appsecret']) ? $_POST['dtk_appsecret'] : '';
        $hdk_appkey = isset($_POST['hdk_appkey']) ? $_POST['hdk_appkey'] : '';

       $api = array(
              'tb_appkey' => $tb_appkey,
              'tb_secretKey' => $tb_secretKey,
              'tb_pid' => $tb_pid,
              'ali_appid' => $ali_appid,
              'ali_appsecretKey' => $ali_appsecretKey,
              'apiurl' => $apiurl,
              'appid' => $appid,
              'secretkey' => $secretkey,
              'zhuan' => $zhuan,
              'dtk_appkey' => $dtk_appkey,
              'dtk_appsecret' => $dtk_appsecret,
              'hdk_appkey' => $hdk_appkey,
        );
        $content = "<?php\r\n\$api=" . var_export($api, true) . ";\n";

        $of = fopen(CONFIG_PATH . 'apiset.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

        include CONFIG_PATH . 'siteconfig.php';
        $Siteinfo['apiurl'] = $apiurl;
        $siteContent = "<?php\r\n\$Siteinfo=" . var_export($Siteinfo, true) . ";\n";
        $siteOf = fopen(CONFIG_PATH . 'siteconfig.php', 'w');
        if ($siteOf) {
            fwrite($siteOf, $siteContent);
        }
        fclose($siteOf);

       echo json_encode(array("info" => "设置成功", "status" => "y"));

      }
   }

    /**
     * AI 对话（智能导购）配置
     * 表单 POST 到 manage/set/aichat，写入 data/config/aichat.php
     */
    public function aichat() {

        $this->checkManageSession();

        if (!IS_POST) {
            $this->redirect('index.php?r=manage/set/index');
            exit;
        }

        $cfg = array(
            'enabled'       => !empty($_POST['enabled']) ? true : false,
            'theme_color'   => isset($_POST['theme_color']) ? trim($_POST['theme_color']) : '#6C63FF',
            'default_role'  => isset($_POST['default_role']) ? trim($_POST['default_role']) : 'shopping',
            'provider'      => isset($_POST['provider']) ? trim($_POST['provider']) : 'deepseek',
            'api_url'       => isset($_POST['api_url']) ? trim($_POST['api_url']) : '',
            'api_key'       => isset($_POST['api_key']) ? trim($_POST['api_key']) : '',
            'model'         => isset($_POST['model']) ? trim($_POST['model']) : '',
            'temperature'   => isset($_POST['temperature']) ? floatval($_POST['temperature']) : 0.7,
            'max_tokens'    => isset($_POST['max_tokens']) ? intval($_POST['max_tokens']) : 1024,
            'stream'        => false,
            'token'         => isset($_POST['token']) ? trim($_POST['token']) : '',
        );

        $content = "<?php\r\nreturn " . var_export($cfg, true) . ";\n";
        $of = fopen(CONFIG_PATH . 'aichat.php', 'w');
        if ($of) {
            fwrite($of, $content);
            fclose($of);
        }

        echo json_encode(array("info" => "AI 设置保存成功", "status" => "y"));
    }

    public function sms(){


    $this->checkManageSession();


      if(!IS_POST){
        $this->pagetext=array("基础设置","短信通道");
        include CONFIG_PATH . 'sms.php';
        $this->ret=$sms;
        $this->display();
        exit;
      }else{

        $sms = array(
            'smsurl' => isset($_POST['smsurl']) ? $_POST['smsurl'] : '',
            'reg_sms' => isset($_POST['reg_sms']) ? $_POST['reg_sms'] : '',
      );
       $content = "<?php\r\n\$sms=" . var_export($sms, true) . ";\n";

        $of = fopen(CONFIG_PATH . 'sms.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

       echo json_encode(array("info" => "设置成功", "status" => "y"));

      }
   }

    public function upload(){


    $this->checkManageSession();

        error_reporting(0);
        if($_FILES['file']['error'] == 0){
            $upload_dir = ROOT_PATH . 'data/uploadfile/';
            if(!is_dir($upload_dir)){
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $imageTypes = array('jpg', 'jpeg', 'png', 'gif', 'bmp');
            
            if (function_exists('imagewebp') && in_array(strtolower($ext), $imageTypes)) {
                $filename = date('YmdHis') . '_' . mt_rand(1000, 9999) . '.webp';
                $tempPath = $upload_dir . 'temp_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $tempPath)) {
                    echo json_encode(array("info" => "上传失败", "status" => "n"));
                    return;
                }
                
                $info = getimagesize($tempPath);
                if ($info !== false) {
                    $mime = $info['mime'];
                    switch ($mime) {
                        case 'image/jpeg':
                            $src = imagecreatefromjpeg($tempPath);
                            break;
                        case 'image/png':
                            $src = imagecreatefrompng($tempPath);
                            imagealphablending($src, true);
                            imagesavealpha($src, true);
                            break;
                        case 'image/gif':
                            $src = imagecreatefromgif($tempPath);
                            break;
                        case 'image/bmp':
                            $src = imagecreatefrombmp($tempPath);
                            break;
                        default:
                            $src = false;
                    }
                    
                    if ($src !== false) {
                        $filepath = $upload_dir . $filename;
                        imagewebp($src, $filepath, 80);
                        imagedestroy($src);
                        @unlink($tempPath);
                    } else {
                        $filepath = $upload_dir . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                        move_uploaded_file($_FILES['file']['tmp_name'], $filepath);
                        $filename = basename($filepath);
                    }
                } else {
                    $filepath = $upload_dir . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    move_uploaded_file($_FILES['file']['tmp_name'], $filepath);
                    $filename = basename($filepath);
                }
            } else {
                $filename = date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                $filepath = $upload_dir . $filename;
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
                    echo json_encode(array("info" => "上传失败", "status" => "n"));
                    return;
                }
            }
            
            include CONFIG_PATH . 'siteconfig.php';
            $cdnUrl = !empty($Siteinfo['cdnurl']) ? rtrim($Siteinfo['cdnurl'], '/') : '';
            $baseUrl = !empty($cdnUrl) ? $cdnUrl : rtrim($Siteinfo['hosturl'], '/');
            $url = $baseUrl . '/data/uploadfile/' . $filename;
            
            echo json_encode(array("info" => "上传成功", "status" => "y", "url" => $url));
        }else{
            echo json_encode(array("info" => "文件错误", "status" => "n"));
        }
    }

    /*生成 js代码*/
    public function js(){

    $this->checkManageSession();

        if (!IS_POST) {
            $this->pagetext=array("基础设置","统计代码");
            header("Content-Type:text/html;charset=utf-8");

            $this->tongji = file_get_contents(CONFIG_PATH . 'codejs/tongji.zhicms');

            $this->display();
            exit;
        } else {

            $tongji = fopen(CONFIG_PATH . 'codejs/tongji.zhicms', 'w');
            if ($tongji) {
                fwrite($tongji, $_POST['tongji']);
            }

            fclose($tongji);
            echo json_encode(array("info" => "设置成功", "status" => "y"));
        }
    }

    /**
     * 互动设置：评论/社区 总开关 + 评论审核/间隔/匿名
     * 配置项写入 yun_config 表（key/value/desc）
     */
    public function interact(){

        $this->checkManageSession();

        if (!IS_POST) {
            $this->pagetext = array("基础设置", "互动设置");
            $this->pageText = array("基础设置", "互动设置");

            $keys = array('comment_on', 'forum_on', 'comment_anonymous', 'comment_check', 'comment_interval');
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
                  `key` varchar(50) NOT NULL DEFAULT '',
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
