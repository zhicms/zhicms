<?php

/**
 * 基础控制器
 */

namespace app\base\controller;
use ZhiCms\base\ThinkTemplate;
class BaseController extends \ZhiCms\base\Controller {

	/**
	 * 初始化
	 */
	public function __construct() {
		// 站点关闭拦截：仅前台生效，后台(manage)与安装(install)不受影响
		if (defined('APP_NAME') && APP_NAME != 'manage' && APP_NAME != 'install') {
			$closeFile = CONFIG_PATH . 'siteconfig.php';
			if (file_exists($closeFile)) {
				$Siteinfo = array();
				include $closeFile;
				if (!empty($Siteinfo['closed']) && $Siteinfo['closed'] == '1') {
					header('Content-Type:text/html;charset=utf-8');
					$msg = !empty($Siteinfo['closemsg']) ? $Siteinfo['closemsg'] : '站点维护中，请稍后再访问。';
					echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
						. '<title>站点关闭</title><style>'
						. 'body{font-family:-apple-system,BlinkMacSystemFont,"Microsoft YaHei",sans-serif;background:#f5f6fa;'
						. 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
						. '.box{background:#fff;padding:40px 50px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.08);'
						. 'text-align:center;max-width:480px}.box h2{margin:0 0 12px;color:#2d3748}'
						. '.box p{color:#718096;line-height:1.7;margin:0}</style></head><body>'
						. '<div class="box"><h2>站点暂时关闭</h2><p>' . htmlspecialchars($msg, ENT_QUOTES) . '</p></div>'
						. '</body></html>';
					exit;
				}
			}
		}
		$this->initSessionSecurity();
	}  
   
    /**
     * 初始化Session安全配置
     */
    protected function initSessionSecurity() {
        if (!isset($_SESSION)) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            session_set_cookie_params([
                'lifetime' => 1800,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $this->generateCsrfToken();
        }
    }
    
    /**
     * 生成CSRF Token
     */
    protected function generateCsrfToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * 获取CSRF Token
     */
    protected function getCsrfToken() {
        return $_SESSION['csrf_token'] ?? $this->generateCsrfToken();
    }
    
    /**
     * 验证CSRF Token
     */
    protected function checkCsrfToken() {
        $token = $this->arg('_token') ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($token) || $token !== $sessionToken) {
            exit(json_encode(array("info" => "CSRF验证失败", "status" => "n")));
        }
    }
   
   /*通用上传*/
	public function upload(){
		$upload=new \ZhiCms\ext\Upload();
		$upload->maxSize=1024*1024*2;
		$upload->allowExts  = explode(',','png');
		$upload_dir = '';
		$upload->savePath =ROOT_PATH.'/upload/file/images/'.$upload_dir."/";

		if(!$upload->upload())
		       {
		        //捕获上传异常
		       print_r($upload->getErrorMsg());
		       exit;
		      }
		      else 
		      {
		        //取得成功上传的文件信息
		       $file= $upload->getUploadFileInfo();
		       $File=$this->file=$file['0']['savename'];
		       return $File; 
		      }		
	}

    /**
     * 加载公共侧边栏数据
     * 包括：热门文章、分类目录、站内统计
     */
    protected function loadCommonSidebar() {
        // 热门文章（按浏览量）
        $whereHot[] = "1";
        $hot = obj("api/ApiData")->dataSelect("yun_article", $whereHot, "`view` DESC LIMIT 0, 10");
        if ($hot) { foreach ($hot as $i => &$h) { $h['rank'] = $i + 1; } unset($h); }
        $this->hot = $hot ? $hot : array();

        // 分类目录
        $cats = array();
        for ($i = 1; $i <= 14; $i++) {
            $nav = $this->lists($i, 'y');
            if ($nav) {
                $p = explode(',', $nav);
                if (count($p) == 2 && $p[0] !== '') {
                    $cats[] = array('name' => $p[0], 'cid' => $p[1]);
                }
            }
        }
        $this->cats = $cats;

        // 站内速览报表数据
        $today = date("Y-m-d");
        $yesterday = date("Y-m-d", strtotime('-1 day'));
        $monthStart = date("Y-m-01");

        $todayWhere[] = "`date` >= '{$today} 00:00:00' AND `date` <= '{$today} 23:59:59'";
        $this->todayCount = obj("api/ApiData")->dataCount("yun_article", $todayWhere);

        $yesterdayWhere[] = "`date` >= '{$yesterday} 00:00:00' AND `date` <= '{$yesterday} 23:59:59'";
        $this->yesterdayCount = obj("api/ApiData")->dataCount("yun_article", $yesterdayWhere);

        $weekWhere[] = "`date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $this->weekCount = obj("api/ApiData")->dataCount("yun_article", $weekWhere);

        $monthWhere[] = "`date` >= '{$monthStart} 00:00:00'";
        $this->monthCount = obj("api/ApiData")->dataCount("yun_article", $monthWhere);

        $totalWhere[] = "1";
        $this->totalCount = obj("api/ApiData")->dataCount("yun_article", $totalWhere);
    }

    /**
     * 设置页面SEO信息
     * @param string $title 页面标题
     * @param string $keywords 页面关键字
     * @param string $description 页面描述
     */
    protected function setPageSEO($title = '', $keywords = '', $description = '') {
        $siteName = obj('base/Base')->SiteConfig('sitename');
        
        if ($title) {
            $this->pageTitle = $title . ' - ' . $siteName;
        } else {
            $this->pageTitle = obj('base/Base')->SEO('index_title');
        }
        
        $this->pageKeywords = $keywords ?: obj('base/Base')->SEO('index_keywords');
        $this->pageDescription = $description ?: obj('base/Base')->SEO('index_dec');
    }

    /**
     * 获取分类信息数据
     * @param int $cid 分类ID
     * @param string $lock 锁定模式
     * @return string 分类名称和ID
     */
    public function lists($cid, $lock = "n") {
        $categories = [
            1 => '女装', 2 => '母婴', 3 => '化妆品', 4 => '居家',
            5 => '鞋包配饰', 6 => '美食', 7 => '文体车品', 8 => '数码家电',
            9 => '男装', 10 => '内衣', 11 => '箱包', 12 => '配饰',
            13 => '户外运动', 14 => '家装家纺'
        ];
        
        if ($lock == "y" && isset($categories[$cid])) {
            return "{$categories[$cid]},{$cid}";
        }
        
        return isset($categories[$cid]) ? $categories[$cid] : '';
    }
  
}