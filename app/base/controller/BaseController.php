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
     * 静态分类映射（对标 emlog 的全局缓存变量设计）
     * emlog 将分类名硬编码为静态数组，避免每次请求查库
     */
    private static $categoryMap = [
        1 => '女装', 2 => '母婴', 3 => '化妆品', 4 => '居家',
        5 => '鞋包配饰', 6 => '美食', 7 => '文体车品', 8 => '数码家电',
        9 => '男装', 10 => '内衣', 11 => '箱包', 12 => '配饰',
        13 => '户外运动', 14 => '家装家纺',
    ];

    /**
     * 获取所有分类目录（带缓存，对标 emlog 的 $CACHE 全局变量）
     */
    public static function getCategories() {
        $cats = [];
        foreach (self::$categoryMap as $cid => $name) {
            $cats[] = ['name' => $name, 'cid' => $cid];
        }
        return $cats;
    }

    /**
     * 根据分类ID获取分类名（对标 emlog 的缓存读取）
     */
    public static function getCategoryName($cid) {
        $cid = (int)$cid;
        return isset(self::$categoryMap[$cid]) ? self::$categoryMap[$cid] : '';
    }

    /**
     * 加载公共侧边栏数据
     * 
     * 优化要点（对标 emlog 大数据量设计）：
     *   1. 5 次 COUNT 合并为 1 次 UNION 查询（减少 80% DB 往返）
     *   2. 分类目录用静态映射（0 次 DB 查询）
     *   3. 热门文章缓存 10 分钟（避免大表 ORDER BY view 扫描）
     *   4. 统计数据缓存 5 分钟
     */
    protected function loadCommonSidebar() {
        $cache = \app\common\CacheService::instance();

        // 热门文章（缓存 10 分钟，emlog 也缓存热门区块）
        $this->hot = $cache->remember('sidebar_hot', function () {
            $whereHot = ['1'];
            $hot = obj("api/ApiData")->dataSelect("yun_article", $whereHot, "`view` DESC LIMIT 0, 10");
            if ($hot) {
                foreach ($hot as $i => &$h) { $h['rank'] = $i + 1; }
                unset($h);
            }
            return $hot ?: [];
        }, 600);

        // 分类目录：使用静态映射（对标 emlog 缓存设计，0 次 DB 查询）
        $this->cats = self::getCategories();

        // 站内速览：5 次 COUNT → 1 次 UNION（对标 emlog 的 site_stat 缓存）
        $today = date("Y-m-d");
        $yesterday = date("Y-m-d", strtotime('-1 day'));
        $stats = $cache->remember('sidebar_stats', function () use ($today, $yesterday) {
            $sql = "SELECT 'today' AS period, COUNT(*) AS cnt FROM `{pre}article` WHERE `date` >= '{$today} 00:00:00' AND `date` <= '{$today} 23:59:59'
                    UNION ALL
                    SELECT 'yesterday', COUNT(*) FROM `{pre}article` WHERE `date` >= '{$yesterday} 00:00:00' AND `date` <= '{$yesterday} 23:59:59'
                    UNION ALL
                    SELECT 'week', COUNT(*) FROM `{pre}article` WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    UNION ALL
                    SELECT 'month', COUNT(*) FROM `{pre}article` WHERE `date` >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                    UNION ALL
                    SELECT 'total', COUNT(*) FROM `{pre}article`";
            $rows = obj("api/ApiData")->thisQuery($sql);
            $map = ['today' => 0, 'yesterday' => 0, 'week' => 0, 'month' => 0, 'total' => 0];
            if ($rows) {
                foreach ($rows as $r) {
                    $map[$r['period']] = (int)$r['cnt'];
                }
            }
            return $map;
        }, 300);

        $this->todayCount     = $stats['today'];
        $this->yesterdayCount = $stats['yesterday'];
        $this->weekCount      = $stats['week'];
        $this->monthCount     = $stats['month'];
        $this->totalCount     = $stats['total'];
    }

    /**
     * 设置页面SEO信息（带三级兜底：页面指定 > 后台SEO设置 > 站点全局配置）
     * @param string $title 页面标题（不含站点名，方法会自动追加 " - 站点名"）
     * @param string $keywords 页面关键字
     * @param string $description 页面描述
     */
    protected function setPageSEO($title = '', $keywords = '', $description = '') {
        $siteName = obj('base/Base')->SiteConfig('sitename');
        
        if ($title) {
            $this->pageTitle = $title . ' - ' . $siteName;
        } else {
            // 三级兜底：SEO(index_title) > sitename
            $this->pageTitle = obj('base/Base')->SEO('index_title') ?: $siteName;
        }
        
        // 三级兜底：$keywords > SEO(index_keywords) > SiteConfig(sitekeywords)
        $this->pageKeywords = $keywords ?: (obj('base/Base')->SEO('index_keywords') ?: obj('base/Base')->SiteConfig('sitekeywords'));
        
        // 三级兜底：$description > SEO(index_dec) > SiteConfig(sitedescription)
        $this->pageDescription = $description ?: (obj('base/Base')->SEO('index_dec') ?: obj('base/Base')->SiteConfig('sitedescription'));
    }

    /**
     * 获取分类信息数据（兼容旧模板调用）
     * @param int $cid 分类ID
     * @param string $lock 锁定模式
     * @return string
     */
    public function lists($cid, $lock = "n") {
        $name = self::getCategoryName($cid);
        if ($lock == "y" && $name !== '') {
            return "{$name},{$cid}";
        }
        return $name;
    }
  
}