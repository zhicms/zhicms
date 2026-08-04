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
		if (defined('\APP_NAME') && \APP_NAME != 'manage' && \APP_NAME != 'install') {
			$closeFile = \CONFIG_PATH . 'siteconfig.php';
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

		// 后台导航：注入已启用插件的后台菜单（供 emlog_nav 动态渲染）
		if (defined('\APP_NAME') && \APP_NAME == 'manage') {
			if (class_exists('\\ZhiCms\\base\\PluginManager')) {
				$this->pluginMenus = \ZhiCms\base\PluginManager::getAdminMenus();
			} else {
				$this->pluginMenus = array();
			}
		}

		// 前台统一注入当前登录用户（供侧栏/模板使用），construct 阶段 obj 可能未就绪则用 try 兜底
		if (defined('\APP_NAME') && \APP_NAME != 'manage' && \APP_NAME != 'install') {
			try {
				$this->loginUser = $this->resolveLoginUser();
			} catch (\Throwable $e) {
				$this->loginUser = null;
			}
			try {
				$this->showUserEntry = $this->resolveUserSwitch('user_show_login', '1') === '1';
			} catch (\Throwable $e) {
				$this->showUserEntry = true;
			}
		}
	}

	/**
	 * 解析当前登录用户（前台）：基于 ZhiCmsUser Cookie（手机号）查询 yun_user
	 * 与 InteractController / ForumController 的识别方式保持一致。
	 * @return array|null
	 */
	protected function resolveLoginUser() {
		if (empty($_COOKIE['ZhiCmsUser'])) return null;
		$cookie = $_COOKIE['ZhiCmsUser'];
		// 仅在用户/论坛/互动等前台模块加载 obj（避免安装期报错）
		if (!function_exists('obj')) return null;
		// 关键修复：直接用 ApiData model 查询，避免 obj("index/global") 触发
		// BaseController::__construct → resolveLoginUser → obj("index/global") → make() →
		// __construct 的无限递归（obj() 在 make() 返回前不缓存正在构造的实例）。
		$safeUid = str_replace(['%', '_', '\\'], ['\%', '\_', '\\\\'], $cookie);
		// 注意：dataSelect() 未传 order 时内部走 find()，返回的是【单个用户关联数组】而非二维数组，
		// 因此不能再用 $rows[0] 取用户（那会是 null），需直接使用 $rows。
		$u = obj("api/ApiData")->dataSelect("yun_user", array("`mobile` LIKE '{$safeUid}'"));
		if (empty($u)) return null;
		// 脱敏手机号作为展示名（无昵称字段，用手机号）
		$mobile = isset($u['mobile']) ? $u['mobile'] : $cookie;
		$u['show_name'] = (strlen($mobile) >= 11)
			? substr($mobile, 0, 3) . '****' . substr($mobile, 7)
			: $mobile;
		return $u;
	}

	/**
	 * 读取用户开关（yun_config）
	 */
	protected function resolveUserSwitch($key, $default = '1') {
		if (!function_exists('obj')) return $default;
		$row = obj("api/ApiData")->thisQuery(
			"SELECT `value` FROM `{pre}config` WHERE `key` = ? LIMIT 1",
			array($key)
		);
		// 注意：DB 值可能为字符串 '0'，不能用 empty() 判断（empty('0') 为 true），
		// 否则开关设为 0 时会错误地回落到默认值导致开关失效。
		if (isset($row[0]['value']) && $row[0]['value'] !== '') {
			return $row[0]['value'];
		}
		return $default;
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
   
   /**
     * 统一 404 处理：发送 404 状态码并输出简洁提示（不抛未定义方法错误）
     */
    public function e_404() {
        header('HTTP/1.1 404 Not Found');
        header('status: 404 Not Found');
        header('Content-Type:text/html;charset=utf-8');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<title>页面不存在 - 404</title><style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Microsoft YaHei",sans-serif;background:#f5f6fa;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
            . '.box{background:#fff;padding:40px 50px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.08);'
            . 'text-align:center;max-width:480px}.box h2{margin:0 0 12px;color:#2d3748}'
            . '.box p{color:#718096;line-height:1.7;margin:0 0 16px}'
            . '.box a{color:#ff4d4f;text-decoration:none}.box a:hover{text-decoration:underline}</style></head><body>'
            . '<div class="box"><h2>404 - 页面不存在</h2>'
            . '<p>您访问的页面不存在或已被删除。</p>'
            . '<a href="/">返回首页</a></div></body></html>';
        exit;
    }

   /*通用上传（已弃用，请使用 FileController 代替）*/
	public function upload(){
		$file = isset($_FILES['file']) ? $_FILES['file'] : null;
		if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
			return false;
		}
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
			return false;
		}
		$fileName = substr(md5($file['name']), 0, 4) . time() . '.' . $ext;
		$dateDir = date('Ymd');
		$uploadDir = \ROOT_PATH . '/upload/file/images/' . $dateDir;
		if (!is_dir($uploadDir)) {
			@mkdir($uploadDir, 0777, true);
		}
		$targetPath = $uploadDir . '/' . $fileName;
		if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
			return false;
		}
		return $dateDir . '/' . $fileName;
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
     * 获取「文章资讯」分类（发现分类 yun_nav）映射 id => name
     * 用于首页右侧分类目录、文章卡片分类标签，避免与商品分类(cid)混淆
     */
    public static function getNavCategories() {
        static $navMap = null;
        if ($navMap !== null) {
            return $navMap;
        }
        $navMap = [];
        $rows = obj("api/ApiData")->dataSelect("yun_nav", array("1"), "`px` ASC, `id` ASC");
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $navMap[(int)$r['id']] = $r['name'];
            }
        }
        return $navMap;
    }

    /**
     * 根据发现分类ID(navid)获取分类名（文章资讯分类）
     */
    public static function getNavName($navid) {
        $navid = (int)$navid;
        $map = self::getNavCategories();
        return isset($map[$navid]) ? $map[$navid] : '';
    }

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

        // 分类目录：文章资讯分类(yun_nav) 与 电商商品分类(cid) 分别赋值，避免混淆
        // $navs = 文章资讯分类（首页/文章分类页/阅读页使用）
        // $cats = 电商商品分类（优惠券/大牌/风云榜/热榜/商品详情页使用）
        $this->navs = self::getNavCategories();
        $this->cats = self::getCategories();

        // 友情链接（缓存 10 分钟，所有前端页面底部 footer 统一调用）
        $this->links = $cache->remember('footer_links', function () {
            $links = obj("api/ApiData")->dataSelect("yun_link", array(), "`px` ASC, `id` ASC LIMIT 0, 20");
            return $links ?: [];
        }, 600);

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