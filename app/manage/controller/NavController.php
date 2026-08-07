<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;

/**
 * 导航管理：前台主导航/子导航的增删改、拖拽排序、新窗口、显示隐藏，
 * 以及从「固定栏目 + 单页」勾选批量添加。
 * 数据表：yun_navmenu（参考 emlog 的 emlog_navi 表设计）
 */
class NavController extends \app\base\controller\BaseController
{
	use ManageControllerTrait;

	/** 固定可勾选栏目：key => [名称, 生成URL的路由] */
	private function fixedColumns(){
		return array(
			'cheaps' => array('优惠券', 'index/cheaps/index'),
			'brand'  => array('大牌',   'index/brand/index'),
			'rank'   => array('风云榜', 'index/rank/index'),
			'hot'    => array('热榜',   'index/hot/index'),
			'forum'  => array('社区',   'index/forum/index'),
		);
	}

	/** 类型显示名 */
	public function typeName($type){
		$map = array(
			'custom' => '自定义',
			'cheaps' => '优惠券',
			'brand'  => '大牌',
			'rank'   => '风云榜',
			'hot'    => '热榜',
			'forum'  => '社区',
		);
		if (strpos($type, 'page_') === 0) return '单页';
		return isset($map[$type]) ? $map[$type] : '自定义';
	}

	/** 单页列表：供「勾选添加单页」使用 */
	private function getPages(){
		$rows = obj('api/ApiData')->dataSelect('yun_page', array('1'), '`id` DESC');
		if (empty($rows)) return array();
		if (isset($rows['id'])) $rows = array($rows); // 单条时归一化为二维
		return $rows;
	}

	/** 列表页 */
	public function index(){
		$this->checkManageSession();

		$this->pageText = array('网站管理', '导航管理');

		// 所有导航（按父级+排序）
		$all = obj('api/ApiData')->dataSelect('yun_navmenu', array('1'), '`parent_id` ASC, `sort` ASC, `id` ASC');
		if (empty($all)) $all = array();
		if (isset($all['id'])) $all = array($all);

		// 组装：主导航 + 子导航
		$navs = array();     // 主导航列表
		$children = array(); // parent_id => [子]
		foreach ($all as $r) {
			$r['name'] = htmlspecialchars(trim($r['name']));
			$r['url']  = htmlspecialchars(trim($r['url']));
			// 预计算类型显示名（模板中不能调用 $this->xxx，改为预置字段）
			$r['type_name'] = $this->typeName($r['type']);
			if ((int)$r['parent_id'] === 0) {
				$navs[$r['id']] = $r;
			} else {
				$children[$r['parent_id']][] = $r;
			}
		}
		$this->navs = $navs;
		$this->navChildren = $children;

		// 固定栏目（含是否已被加入）
		$existTypes = array();
		foreach ($all as $r) $existTypes[$r['type']] = 1;
		$columns = array();
		foreach ($this->fixedColumns() as $key => $v) {
			$columns[] = array('key' => $key, 'name' => $v[0], 'added' => isset($existTypes[$key]) ? 1 : 0);
		}
		$this->columns = $columns;

		// 单页（含是否已加入）
		$pages = $this->getPages();
		$pageList = array();
		foreach ($pages as $p) {
			$pageList[] = array(
				'id'     => $p['id'],
				'title'  => htmlspecialchars($p['title']),
				'added'  => isset($existTypes['page_' . $p['id']]) ? 1 : 0,
			);
		}
		$this->pageList = $pageList;

		$this->display();
	}

	/**
	 * 批量添加：勾选固定栏目 + 单页 + 自定义导航
	 * POST: columns[]=cheaps|brand|rank|hot|forum, pages[]=页面id, 以及 name/url/newtab 自定义项
	 */
	public function add(){
		$this->checkManageSession();
		if (!\IS_POST) {
			$this->alert('非法请求');
		}

		$created = 0;
		// 1) 勾选的固定栏目
		$cols = isset($_POST['columns']) && is_array($_POST['columns']) ? $_POST['columns'] : array();
		foreach ($cols as $key) {
			$f = $this->fixedColumns();
			if (!isset($f[$key])) continue;
			$this->insertNav($f[$key][0], $key, $this->routeUrl($f[$key][1]));
		}

		// 2) 勾选的单页
		$pageIds = isset($_POST['pages']) && is_array($_POST['pages']) ? array_map('intval', $_POST['pages']) : array();
		if ($pageIds) {
			foreach ($pageIds as $pid) {
				$p = obj('api/ApiData')->dataSelect('yun_page', array("`id` = $pid"));
				if (empty($p) || isset($p[0])) continue;
				$this->insertNav($p['title'], 'page_' . $pid, url('index/page/index', array('id' => $pid)), $pid);
			}
		}

		// 3) 自定义导航（弹窗里新增）
		$name = trim(isset($_POST['name']) ? $_POST['name'] : '');
		$url  = trim(isset($_POST['url']) ? $_POST['url'] : '');
		if ($name !== '' && $url !== '') {
			$this->insertNav($name, 'custom', $this->normalizeNavUrl($url));
		}

		// 4) 已存在的导航项更新（编辑）
		$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array();
		foreach ($ids as $id) {
			$id = intval($id);
			$name = trim(isset($_POST['name_' . $id]) ? $_POST['name_' . $id] : '');
			$url  = trim(isset($_POST['url_' . $id]) ? $_POST['url_' . $id] : '');
			$pid  = intval(isset($_POST['pid_' . $id]) ? $_POST['pid_' . $id] : 0);
			$target = isset($_POST['target_' . $id]) ? 1 : 0;
			if ($name === '' || $url === '') continue;
			obj('api/ApiData')->dataUpdate('yun_navmenu', array(
				'name'      => $name,
				'url'       => $this->normalizeNavUrl($url),
				'parent_id' => $pid,
				'target'    => $target,
			), array("`id` = $id"));
			$created++;
		}

		exit(json_encode(array('info' => '保存成功（新增/更新 ' . max($created, 1) . ' 项）', 'status' => 'y')));
	}

	/**
	 * 新增/编辑单条导航（弹窗表单）
	 * POST: id(0=新增), name, url, pid, target
	 */
	public function saveOne(){
		$this->checkManageSession();
		if (!\IS_POST) $this->alert('非法请求');

		$id     = intval($_POST['id'] ?? 0);
		$name   = trim($_POST['name'] ?? '');
		$url    = trim($_POST['url'] ?? '');
		$pid    = intval($_POST['pid'] ?? 0);
		$target = intval($_POST['target'] ?? 0);

		if ($name === '' || $url === '') {
			exit(json_encode(array('info' => '名称和链接不能为空', 'status' => 'n')));
		}
		$url = $this->normalizeNavUrl($url);

		if ($id > 0) {
			obj('api/ApiData')->dataUpdate('yun_navmenu', array(
				'name'      => $name,
				'url'       => $url,
				'parent_id' => $pid,
				'target'    => $target,
			), array("`id` = $id"));
			exit(json_encode(array('info' => '已保存', 'status' => 'y')));
		}

		$lastId = $this->insertNav($name, 'custom', $url);
		// 把新导航设为子导航或新窗口
		if ($pid > 0 || $target) {
			$data = array();
			if ($pid > 0) $data['parent_id'] = $pid;
			if ($target) $data['target'] = 1;
			if ($data) obj('api/ApiData')->dataUpdate('yun_navmenu', $data, array("`id` = $lastId"));
		}
		exit(json_encode(array('info' => '已添加', 'status' => 'y')));
	}

	/** 保存拖拽排序：POST sort[]=id1,id2,...（主导航顺序）+ child 映射 */
	public function sort(){
		$this->checkManageSession();
		if (!\IS_POST) $this->alert('非法请求');

		// 前端以 JSON 提交：navsort={"parent":{"6":40,"7":30},"child":{"...":...}}
		// （用 JSON 避免 PHP 把数字字符串数组键转成整数导致格式误判）
		$map = array();
		if (isset($_POST['navsort']) && $_POST['navsort'] !== '') {
			$decoded = json_decode($_POST['navsort'], true);
			if (is_array($decoded)) {
				if (!empty($decoded['parent']) && is_array($decoded['parent'])) $map += $decoded['parent'];
				if (!empty($decoded['child'])  && is_array($decoded['child']))  $map += $decoded['child'];
			}
		} else {
			// 兼容旧版提交格式 sort[]=id / sort[id]=值
			$order = isset($_POST['sort']) && is_array($_POST['sort']) ? $_POST['sort'] : array();
			$i = 1;
			foreach ($order as $key => $val) {
				if (is_string($key) && $key !== '') {
					$map[$key] = intval($val);
				} else {
					$map[$val] = $i; $i++;
				}
			}
		}

		foreach ($map as $navId => $sortVal) {
			$navId = intval($navId);
			$sort  = intval($sortVal);
			if (!$navId) continue;
			obj('api/ApiData')->dataUpdate('yun_navmenu', array('sort' => $sort), array("`id` = $navId"));
		}
		exit(json_encode(array('info' => '排序已保存', 'status' => 'y')));
	}

	/** 删除导航项（含其子导航） */
	public function del(){
		$this->checkManageSession();
		$id = intval($this->arg('id'));
		if (!$id) exit(json_encode(array('info' => '参数错误', 'status' => 'n')));
		$row = obj('api/ApiData')->dataSelect('yun_navmenu', array("`id` = $id"));
		if (!empty($row) && !empty($row['isdefault'])) {
			exit(json_encode(array('info' => '系统内置导航不可删除', 'status' => 'n')));
		}
		obj('api/ApiData')->deleteThis('yun_navmenu', "`id` = ?", array($id));
		obj('api/ApiData')->deleteThis('yun_navmenu', "`parent_id` = ?", array($id));
		exit(json_encode(array('info' => '删除成功', 'status' => 'y')));
	}

	/** 显示/隐藏 */
	public function toggle(){
		$this->checkManageSession();
		$id = intval($this->arg('id'));
		if (!$id) exit(json_encode(array('info' => '参数错误', 'status' => 'n')));
		$row = obj('api/ApiData')->dataSelect('yun_navmenu', array("`id` = $id"));
		if (empty($row)) exit(json_encode(array('info' => '导航不存在', 'status' => 'n')));
		$hide = empty($row['hide']) ? 1 : 0;
		obj('api/ApiData')->dataUpdate('yun_navmenu', array('hide' => $hide), array("`id` = $id"));
		exit(json_encode(array('info' => $hide ? '已隐藏' : '已显示', 'status' => 'y', 'hide' => $hide)));
	}

	/** 新增一条导航，返回新记录 id */
	private function insertNav($name, $type, $url, $typeId = 0){
		return obj('api/ApiData')->insertData('yun_navmenu', array(
			'name'       => $name,
			'url'        => $url,
			'type'       => $type,
			'type_id'    => intval($typeId),
			'parent_id'  => 0,
			'target'     => 0,
			'hide'       => 0,
			'isdefault'  => 0,
			'sort'       => $this->nextSort(),
			'create_time'=> time(),
		));
	}

	/**
	 * 生成固定栏目链接：始终存储【动态】地址（index.php?r=...）。
	 * 数据库统一存动态，前端 nav_url() 再根据伪静态开关渲染，
	 * 后台切换伪静态后无需逐个改导航。
	 */
	private function routeUrl($route){
		return 'index.php?r=' . $route;
	}

	/**
	 * 导航链接规范化：把站内伪静态（.html）反向解析为动态地址入库。
	 * 这样无论后台是否开启伪静态、管理员手填的是 so.html 还是动态地址，
	 * 数据库都统一存动态，前端 nav_url() 再按当前开关自动输出正确格式。
	 * - 外链(http/https/ftp)、锚点(#)、相对路径 → 原样返回（外部链接不处理）
	 * - 站内伪静态(.html) → 反向匹配 REWRITE_RULE 转成 index.php?r=...
	 * - 已是 index.php?r= 动态 → 原样返回
	 */
	private function normalizeNavUrl($url){
		$url = trim((string)$url);
		if ($url === '') return $url;
		// 外链 / 锚点 / 带协议的其它链接：原样返回
		if (preg_match('/^(https?:\/\/|ftp:\/\/|#)/i', $url)) return $url;
		// 已经是动态地址
		if (stripos($url, 'index.php?r=') !== false) return $url;
		// 相对路径（/开头，但非本站伪静态 html）→ 原样返回
		if (strpos($url, '/') === 0 && stripos($url, '.html') === false) return $url;
		// 仅处理本站伪静态 .html
		if (stripos($url, '.html') === false) return $url;

		$rule = \ZhiCms\base\Config::get('REWRITE_RULE');
		if (empty($rule) || !is_array($rule)) return $url;

		// 归一化：去掉可能的域名/脚本前缀，只保留 path
		$path = $url;
		if (preg_match('/\/([^\/]+\.html(?:\?.*)?)$/i', $url, $m)) {
			$path = $m[1];
		}
		$path = ltrim($path, '/');

		foreach ($rule as $pattern => $mapper) {
			$pattern = ltrim($pattern, './\\');
			// 把 <key> 换成捕获组（与 Route::parseUrl 一致，允许字母数字%-）
			$regex = preg_replace_callback('/<([a-zA-Z0-9_]+)>/', function ($mm) {
				$name = $mm[1];
				$pat = ($name === 'platform') ? '\w+' : '[\w%-]+';
				return '(?<' . $name . '>' . $pat . ')';
			}, $pattern);
			$regex = '/^' . str_ireplace(array('-', '/', '.'), array('\-', '\/', '\.'), $regex) . '$/i';
			if (preg_match($regex, $path, $matches)) {
				$realRoute = $mapper;
				$query = array();
				if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)=<([a-zA-Z0-9_]+)>/', $mapper, $pm, PREG_SET_ORDER)) {
					foreach ($pm as $p) {
						$val = $matches[$p[2]] ?? '';
						$realRoute = str_ireplace('<' . $p[2] . '>', $val, $realRoute);
						// 仅当参数未并入路由段时才单独放入 query，避免 page-3.html => index/page/index/id=3&id=3 重复
						if (stripos($realRoute, $p[1] . '=' . $val) === false) {
							$query[$p[1]] = $val;
						}
					}
				}
				$realRoute = preg_replace('/\/[a-zA-Z_][a-zA-Z0-9_]*=\<[a-zA-Z0-9_]+\>/i', '', $realRoute);
				$out = 'index.php?r=' . trim($realRoute, '/');
				if ($query) {
					$out .= '&' . http_build_query($query);
				}
				return $out;
			}
		}
		// 匹配不到规则，原样返回（避免误伤）
		return $url;
	}

	/** 下一个排序值（当前最大 sort + 1） */
	private function nextSort(){
		$row = obj('api/ApiData')->thisQuery("SELECT IFNULL(MAX(`sort`),0)+1 AS s FROM `{pre}navmenu`");
		$v = is_array($row) && isset($row[0]) ? $row[0]['s'] : (isset($row['s']) ? $row['s'] : 1);
		return intval($v);
	}
}
