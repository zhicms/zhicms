<?php
namespace plugins\kiees\controller;

/**
 * 站点业务控制器（仿 kiees.com 优惠导购布局）
 *
 * 访问：
 *   plug-kiees.html            -> index()   首页（商品推荐流 + 侧边热门榜）
 *   plug-kiees-<id>.html       -> detail()  详情
 *
 * 数据直接来自站点数据库：
 *   yun_article（推荐流/详情/热门榜，图片 mainPic、描述 dec、人气 view、购买链接 itemLink）
 *   yun_nav（顶部推荐类别导航）
 */
class SiteController extends KieesController
{
    public function run($params = array())
    {
        $id  = isset($params['id']) ? intval($params['id']) : 0;
        $mod = isset($params['mod']) ? strtolower(trim($params['mod'])) : '';
        if ($id > 0) {
            $this->detail($id);
            return;
        }
        switch ($mod) {
            case 'cheaps':
                $this->cheaps();
                break;
            case 'brand':
                $this->brand();
                break;
            case 'rank':
                $this->rank();
                break;
            default:
                $this->index();
        }
    }

    /**
     * 首页 / 搜索结果
     * - 无 kw：左栏推荐流（分页） + 右栏热门榜
     * - 有 kw：搜索 yun_article（标题/摘要模糊匹配），渲染仿 kiees.com 搜索结果页中间内容
     */
    public function index()
    {
        $kw = trim($this->arg('kw', ''));
        if ($kw !== '') {
            $this->search($kw);
            return;
        }

        $db = $this->db();

        // 左栏推荐流（最新，分页）
        $page = max(1, intval($this->arg('page', 1)));
        $pageSize = 15;
        $where = array("`status` = 1");
        $total = $db->dataCount('yun_article', $where);
        $list = $db->dataSelect(
            'yun_article',
            $where,
            "`id` DESC LIMIT " . (($page - 1) * $pageSize) . ", {$pageSize}"
        );
        $list = $list ?: array();

        // 首页卡片点击进入插件文章详情页
        foreach ($list as &$item) {
            $item['buy_url'] = $this->plugUrl(null, $item['id']);
            $item['hot'] = intval($item['view']);
        }
        unset($item);

        // 右栏热门榜（view 最高的 10 条）
        $hot = $db->dataSelect('yun_article', array("`status` = 1"), '`view` DESC LIMIT 0, 10');
        $hot = $hot ?: array();

        // 分页条
        $pager = $this->buildPager($page, $pageSize, $total);

        $this->assign(array(
            'list'      => $list,
            'hot'       => $hot,
            'total'     => $total,
            'pager'     => $pager,
            'page_title'=> '发现值得买 - 高性价比网购推荐',
            'page_keywords'   => $this->siteName() . ',优惠券,好物推荐,网购省钱,白菜价,返利优惠,折扣精选',
            'page_description'=> $this->siteName() . ' 每日精选高性价比优惠券与好物推荐，覆盖淘宝、京东、拼多多、唯品会大额券，领券下单更省，帮你省钱逛遍全网好货。',
        ));

        $this->display('index');
    }

    /**
     * 站内搜索（混合展示）：仿 kiees.com 搜索结果页中间主要内容
     * 同时检索本地「文章(yun_article)」与「优惠券/商品(yun_items)」，
     * 合并后按人气排序混合输出，每条记录带 type 标记（article / items），
     * 视图层据此展示不同参数（文章：摘要+详情；优惠券：券额+券后价+领券）。
     */
    public function search($kw)
    {
        $db = $this->db();
        $page = max(1, intval($this->arg('page', 1)));
        $pageSize = 15;

        // 筛选参数：type=article|items；from=tb|jd|pdd|vip（仅对优惠券有效）
        $filterType = strtolower(trim($this->arg('type', '')));
        $filterFrom = strtolower(trim($this->arg('from', '')));
        if (!in_array($filterType, array('article', 'items'), true)) $filterType = '';
        if (!in_array($filterFrom, array('tb', 'jd', 'pdd', 'vip'), true)) $filterFrom = '';

        // 防注入：参数绑定 + 转义 % 与 _
        $like = '%' . addcslashes($kw, '%_') . '%';

        // 是否需要查文章（type 为空 或 article）
        $needArticle = ($filterType === '' || $filterType === 'article');
        // 是否需要查优惠券（type 为空 或 items）
        $needItems   = ($filterType === '' || $filterType === 'items');

        $totalA = 0;
        $totalI = 0;
        $merged = array();

        // 1) 文章（status=1，匹配 title/content/dec）
        if ($needArticle) {
            $aTail = " FROM {pre}article WHERE `status` = 1 AND (`title` LIKE ? OR `content` LIKE ? OR `dec` LIKE ?)";
            $aParams = array($like, $like, $like);
            $aCount = $db->thisQuery("SELECT COUNT(*) AS c " . $aTail, $aParams);
            $totalA = isset($aCount[0]['c']) ? intval($aCount[0]['c']) : 0;
        }

        // 2) 优惠券：
        //    - 默认 / 点「优惠券」按钮（未指定平台）：查本地库 yun_items
        //    - 仅当点击了平台筛选（淘宝/京东/拼多多/唯品会）：走 API 接口实时搜索，与网站一致
        $apiItemsList = array();
        $useApi = $needItems && ($filterFrom !== '');
        if ($useApi) {
            $apiRes = $this->searchApiItems($kw, $filterFrom, $page, $pageSize);
            $apiItemsList = $apiRes['items'];
            $totalI = $apiRes['total'];
        } elseif ($needItems) {
            $iTail = " FROM {pre}items WHERE `del` = 0 AND (`title` LIKE ? OR `content` LIKE ? OR `dtitle` LIKE ?)";
            $iParams = array($like, $like, $like);
            $iCount = $db->thisQuery("SELECT COUNT(*) AS c " . $iTail, $iParams);
            $totalI = isset($iCount[0]['c']) ? intval($iCount[0]['c']) : 0;
        }

        $total = $totalA + $totalI;

        // 两表各取本页区间，合并后按权重（人气）排序再裁到 pageSize，保证都能被覆盖
        $artsList = array();
        $couponsList = array();
        if ($needArticle && $totalA > 0) {
            $arts = $db->thisQuery(
                "SELECT * " . $aTail . " ORDER BY `id` DESC LIMIT " . (($page - 1) * $pageSize) . ", {$pageSize}",
                $aParams
            ) ?: array();
            foreach ($arts as $a) {
                $artsList[] = array(
                    'type'    => 'article',
                    'id'      => intval($a['id']),
                    'title'   => $a['title'] ?? '',
                    'summary' => $this->stripSummary($a['dec'] ?? ($a['content'] ?? '')),
                    'img'     => $a['mainPic'] ?? '',
                    'buy_url' => $this->plugUrl(null, $a['id']),
                    'hot'     => intval($a['view']),
                    'price'   => '',
                    'origin'  => '',
                    'coupon'  => '',
                    'from'    => '',
                );
            }
        }
        if ($useApi && !empty($apiItemsList)) {
            // 平台筛选命中：优惠券来自 API 接口（淘宝=大淘客，jd/pdd/vip=好单库）
            foreach ($apiItemsList as $it) {
                $gid  = $it['goodsId'] ?? '';
                $from = $it['item_from'] ?? 'tb';
                $fromMap = array(
                    'taobao' => 'tb', 'tmall' => 'tb', 'tb' => 'tb',
                    'jd' => 'jd', 'jd.com' => 'jd',
                    'pdd' => 'pdd', 'pinduoduo' => 'pdd', 'yangkeduo' => 'pdd',
                    'vip' => 'vip', 'vip.com' => 'vip', 'vipshop' => 'vip',
                );
                $from = $fromMap[strtolower(trim($from))] ?? 'tb';
                // 淘宝以 goodsSign 为产品 id（与采集入库一致）；其余平台用 goodsId
                if ($from === 'tb' && !empty($it['goodsSign'])) {
                    $gid = $it['goodsSign'];
                }
                $buyUrl = function_exists('url')
                    ? url('index/redirect/jump', array('platform' => $from, 'id' => $gid))
                    : ('buy-' . $from . '.html?id=' . $gid);
                $couponsList[] = array(
                    'type'    => 'items',
                    'id'      => intval($it['id'] ?? 0),
                    'title'   => $it['title'] ?? ($it['dtitle'] ?? ''),
                    'summary' => $this->stripSummary($it['content'] ?? ($it['dtitle'] ?? '')),
                    'img'     => $it['mainPic'] ?? '',
                    'buy_url' => $buyUrl,
                    'hot'     => intval($it['monthSales'] ?? 0),
                    'price'   => $it['actualPrice'] ?? '',
                    'origin'  => $it['originalPrice'] ?? '',
                    'coupon'  => $it['couponPrice'] ?? '',
                    'from'    => $from,
                );
            }
        } elseif ($needItems && $totalI > 0) {
            // 默认 / 点「优惠券」按钮：优惠券来自本地库 yun_items
            $items = $db->thisQuery(
                "SELECT * " . $iTail . " ORDER BY `id` DESC LIMIT " . (($page - 1) * $pageSize) . ", {$pageSize}",
                $iParams
            ) ?: array();
            foreach ($items as $it) {
                $gid  = $it['goodsId'] ?? ($it['goods_sign'] ?? ($it['id'] ?? 0));
                $from = $it['item_from'] ?? ($it['laiyuan'] ?? 1);
                $fromMap = array(
                    1 => 'tb', 2 => 'pdd', 3 => 'vip', 4 => 'jd',
                    'taobao' => 'tb', 'tmall' => 'tb', 'tb' => 'tb',
                    'jd' => 'jd', 'jd.com' => 'jd',
                    'pdd' => 'pdd', 'pinduoduo' => 'pdd', 'yangkeduo' => 'pdd',
                    'vip' => 'vip', 'vip.com' => 'vip', 'vipshop' => 'vip',
                );
                $fromKey = is_numeric($from) ? intval($from) : strtolower(trim($from));
                $from = $fromMap[$fromKey] ?? 'tb';
                $buyUrl = function_exists('url')
                    ? url('index/redirect/jump', array('platform' => $from, 'id' => $gid))
                    : ('buy-' . $from . '.html?id=' . $gid);
                $couponsList[] = array(
                    'type'    => 'items',
                    'id'      => intval($it['id']),
                    'title'   => $it['title'] ?? ($it['dtitle'] ?? ''),
                    'summary' => $this->stripSummary($it['content'] ?? ($it['dtitle'] ?? '')),
                    'img'     => $it['mainPic'] ?? '',
                    'buy_url' => $buyUrl,
                    'hot'     => intval($it['monthSales'] ?? 0),
                    'price'   => $it['actualPrice'] ?? '',
                    'origin'  => $it['originalPrice'] ?? '',
                    'coupon'  => $it['couponPrice'] ?? '',
                    'from'    => $from,
                );
            }
        }

        // 混合展示：文章与优惠券交替穿插，保证每页两类都出现（避免高销量优惠券挤掉文章）
        $merged = array();
        $ai = 0; $ci = 0;
        $ac = count($artsList);
        $cc = count($couponsList);
        while (count($merged) < $pageSize && ($ai < $ac || $ci < $cc)) {
            if ($ai < $ac) { $merged[] = $artsList[$ai++]; }
            if (count($merged) >= $pageSize) break;
            if ($ci < $cc) { $merged[] = $couponsList[$ci++]; }
        }
        $list = $merged;

        // 右栏热门榜（view 最高的 10 条文章）
        $hot = $db->dataSelect('yun_article', array("`status` = 1"), '`view` DESC LIMIT 0, 10');
        $hot = $hot ?: array();

        // 搜索结果分页（保留 kw / type / from 参数）
        $pager = $this->buildSearchPager($page, $pageSize, $total, $kw, $filterType, $filterFrom);

        $this->assign(array(
            'keyword'   => $kw,
            'list'      => $list,
            'hot'       => $hot,
            'total'     => $total,
            'total_article' => $totalA,
            'total_items'   => $totalI,
            'filter_type'   => $filterType,
            'filter_from'   => $filterFrom,
            'pager'     => $pager,
            'is_search' => true,
            'page_title'=> '搜索：' . $kw . ' - 发现值得买',
            'page_keywords'   => $kw . ',优惠券,好物推荐,' . $this->siteName() . ',网购省钱',
            'page_description'=> '在 ' . $this->siteName() . ' 搜索「' . $kw . '」共找到 ' . $total . ' 条结果，包含优惠文章与可领券商品，点击查看详情并领券购买更省。',
        ));

        $this->display('search');
    }

    /** 取摘要纯文本（截 120 字，去标签与 [ZhiCmsUrl] 标签） */
    protected function stripSummary($text)
    {
        if (empty($text)) return '';
        $text = preg_replace('/\[ZhiCmsUrl\].*?\[\/ZhiCmsUrl\]/s', '', (string) $text);
        $text = trim(strip_tags($text));
        $text = mb_substr($text, 0, 120, 'UTF-8');
        return $text;
    }

    /**
     * 调用统一 API 接口实时搜索优惠券/商品（与网站 SearchController 一致）
     * - 淘宝(tb) 走大淘客 Tjk::search
     * - 京东(jd)/拼多多(pdd)/唯品会(vip) 走好单库（需配置，未配置则该平台返回空）
     * @param string $kw        关键词
     * @param string $filterFrom 平台筛选（tb/jd/pdd/vip，空=全部）
     * @param int    $page      页码
     * @param int    $pageSize  每页数量
     * @return array ['total'=>int, 'items'=>array]
     */
    protected function searchApiItems($kw, $filterFrom, $page, $pageSize)
    {
        $tjk = new \ZhiCms\ext\Tjk();
        $platforms = ($filterFrom !== '') ? array($filterFrom) : array('tb', 'jd', 'pdd', 'vip');
        // Tjk::searchGoods 的淘宝平台参数需为 'taobao'（而非内部统一的 'tb'）
        $apiPlatformMap = array('tb' => 'taobao', 'jd' => 'jd', 'pdd' => 'pdd', 'vip' => 'vip');
        $all = array();
        $total = 0;
        try {
            foreach ($platforms as $pf) {
                $apiPf = $apiPlatformMap[$pf] ?? $pf;
                // 好单库平台(jd/pdd/vip) 需 hdk 配置；无配置则跳过，不报错
                if ($apiPf !== 'taobao' && !$tjk->getHdk()) {
                    continue;
                }
                $resp = $tjk->searchGoods($kw, $apiPf, $page, $pageSize);
                if ($resp['code'] == 1 && !empty($resp['items'])) {
                    foreach ($resp['items'] as &$it) {
                        $it['item_from'] = $pf; // 显式标注平台（内部统一为 tb/jd/pdd/vip），避免依赖接口内部赋值
                    }
                    unset($it);
                    $all = array_merge($all, $resp['items']);
                    $total += intval($resp['total'] ?? count($resp['items']));
                }
            }
        } catch (\Throwable $e) {
            // API 异常不影响文章结果展示
        }
        return array('total' => $total, 'items' => $all);
    }

    /** 搜索结果分页条（保留 kw / type / from 查询参数） */
    protected function buildSearchPager($page, $pageSize, $total, $kw, $filterType = '', $filterFrom = '')
    {
        $pages = ceil($total / $pageSize);
        if ($pages <= 1) return '';
        // 直接使用插件基链接（伪静态为 plug-kiees.html，动态含 ?），
        // 不再去掉 .html 后缀，避免翻页地址变成 plug-kiees?kw=.. 这种无效形式。
        $base = $this->plugUrl();
        $sep  = (strpos($base, '?') !== false) ? '&' : '?';
        $qs   = 'kw=' . urlencode($kw);
        if ($filterType !== '') $qs .= '&type=' . urlencode($filterType);
        if ($filterFrom !== '') $qs .= '&from=' . urlencode($filterFrom);
        $html = '<div class="pager">';
        if ($page > 1) {
            $html .= '<a href="' . $base . $sep . $qs . '&page=' . ($page - 1) . '">上一页</a>';
        }
        $html .= '<span class="cur">' . $page . ' / ' . $pages . '</span>';
        if ($page < $pages) {
            $html .= '<a href="' . $base . $sep . $qs . '&page=' . ($page + 1) . '">下一页</a>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * 详情页
     */
    public function detail($id)
    {
        $db = $this->db();
        $row = $db->dataSelect('yun_article', array("`id` = {$id}", "`status` = 1"));
        if (empty($row)) {
            header('HTTP/1.1 404 Not Found');
            echo '内容不存在或已删除';
            exit;
        }
        // 详情页正文解析 [ZhiCmsUrl]...[/ZhiCmsUrl] 标签，渲染成商品卡片
        // （与主站 IndexController::view 行为一致，数据复用主站转链）
        if (!empty($row['content'])) {
            $row['content'] = $this->parseZhiCmsUrl($row['content']);
        }

        // 相关推荐（随机 4 条，链接统一指向插件文章详情页保证可打开）
        $related = $db->dataSelect(
            'yun_article',
            array("`id` != {$id}", "`status` = 1"),
            'RAND() LIMIT 0, 4'
        );
        $related = $related ?: array();
        foreach ($related as &$it) {
            $it['buy_url'] = $this->plugUrl(null, $it['id']);
        }
        unset($it);

        $this->assign(array(
            'article'   => $row,
            'related'   => $related,
            'page_title'=> (isset($row['title']) ? $row['title'] : '详情') . ' - 发现值得买',
            'page_keywords'   => (isset($row['title']) ? $row['title'] : '好物推荐') . ',优惠券,网购省钱,' . $this->siteName(),
            'page_description'=> !empty($row['dec']) ? $this->stripSummary($row['dec']) : ($this->stripSummary($row['content'] ?? '') ?: ((isset($row['title']) ? $row['title'] : '好物推荐') . ' - ' . $this->siteName() . ' 精选优惠好物，查看详情领券更省。')),
            'og_image'  => $row['mainPic'] ?? '',
        ));

        $this->display('detail');
    }

    /** 生成插件链接
     * @param string|null $mod 模块名（cheaps/brand/rank），无则首页
     * @param int|null    $id  详情 id
     */
    protected function plugUrl($mod = null, $id = null)
    {
        if ($id !== null) {
            $base = \ZhiCms\base\PluginManager::url($this->alias);
            if (strpos($base, '?') !== false) {
                return $base . '&id=' . $id;
            }
            // 伪静态：base 可能是 plug-kiees.html（含后缀），需去掉后再拼 -id.html
            $base = preg_replace('/\.html$/i', '', $base);
            return rtrim($base, '/') . '-' . $id . '.html';
        }
        if ($mod !== null) {
            return $this->modUrl($mod);
        }
        return \ZhiCms\base\PluginManager::url($this->alias);
    }

    /**
     * 解析正文中的 [ZhiCmsUrl]...[/ZhiCmsUrl] 标签，渲染为商品卡片（与主站一致）
     * 平台识别 → 主站转链(index/redirect/jump) → SMZDM 风格卡片 HTML
     * @param string $content 原始正文
     * @return string 解析后的 HTML
     */
    protected function parseZhiCmsUrl($content)
    {
        if (empty($content)) return $content;
        $content = urldecode($content);
        return preg_replace_callback('/\[ZhiCmsUrl](.+?)\[\/ZhiCmsUrl]/', array($this, 'renderZhiCmsCard'), $content);
    }

    /**
     * [ZhiCmsUrl] 标签回调：解析正文商品短链，渲染图文商品卡片（标题/主图/销量/券后价）
     * 1) 提取标签内 URL
     * 2) 本站转链 buy-tb.html?id=xxx → 直接用 xxx 当 goodsId 调 DTK 详情解析
     * 3) 真实 taobao.com/tmall.com 短链 → ParseContent 拿 goodsId 再 DTK 详情解析
     * 4) 解析成功出带图卡；仅当完全解析失败才回退兜底按钮
     */
    protected function renderZhiCmsCard($matches)
    {
        $raw = $matches[1];
        if (preg_match('/http[s]{0,1}:\/\/([\w.]+\/?)\S*/', $raw, $m)) {
            $url = urldecode(preg_replace('/\[\/ZhiCmsUrl]/', '', $m[0]));
        } else {
            $url = urldecode(trim($raw));
        }
        $url = trim($url);
        if (empty($url)) return '';

        // 本站转链入口：buy-tb.html?id=加密goodsId / buy-jd / buy-pdd / buy-vip
        // 这种链接的 id 本身就是大淘客商品 ID，可直接拿去解析商品详情
        $goodsId = $this->extractBuyLinkId($url);
        if ($goodsId !== '') {
            $card = $this->resolveLinkCard($url, $goodsId);
            if ($card !== null) return $card;
        }

        // 真实淘宝/天猫短链
        $isTaobao = (strpos($url, 'taobao.com') !== false || strpos($url, 'tmall.com') !== false);
        if ($isTaobao) {
            $card = $this->resolveLinkCard($url);
            if ($card !== null) return $card;
        }

        // 其他平台（京东/拼多多/唯品会）走兜底转链卡片；完全不认识则外部推荐
        return $this->buildFallbackBtn($url);
    }

    /**
     * 从本站转链 buy-<platform>.html?id=xxx 中提取商品 ID
     * @return string 成功返回 goodsId，否则空串
     */
    protected function extractBuyLinkId($url)
    {
        if (!preg_match('/buy-(tb|jd|pdd|vip)\.html/i', $url)) return '';
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) return '';
        parse_str($query, $p);
        return trim($p['id'] ?? '');
    }

    /**
     * 使用 DTK 本地接口解析商品并生成图文商品卡片
     * 优先用传入的 $goodsId 直接 GetGoodsDetails；否则先 ParseContent 解析短链拿到 goodsId
     * @param string $url 原始链接（用于兜底/转链）
     * @param string $goodsId 已知商品 ID（站点转链场景）
     * @return string|null 成功返回卡片 HTML；失败返回 null
     */
    protected function resolveLinkCard($url, $goodsId = '')
    {
        $url = trim($url);
        if (empty($url)) return null;

        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $dtk = $tjk->getDtk();
            if (!$dtk) return null;

            // 没有现成 goodsId 时，先用 ParseContent 解析短链拿到 goodsId
            $parsed = array('code' => 0, 'data' => array());
            if (empty($goodsId)) {
                $parsed = $dtk->ParseContent($url);
                $goodsId = ($parsed['code'] == 1 && !empty($parsed['data']['goodsId'])) ? $parsed['data']['goodsId'] : '';
                if (empty($goodsId)) {
                    $twd = $dtk->TwdToTwd($url);
                    if ($twd['code'] == 1 && !empty($twd['data']['goodsId'])) {
                        $goodsId = $twd['data']['goodsId'];
                    }
                }
            }

            // 商品数据合并策略：
            // 1) 优先 GetGoodsDetails（含 原价/券额/月销/主图/现价 完整字段）
            // 2) 失败则用 ParseContent 直接返回的数据兜底（已含 主图/标题/现价/店铺/券类型）
            $item = array(
                'title'         => '',
                'mainPic'       => '',
                'originalPrice' => 0,
                'actualPrice'   => 0,
                'couponPrice'   => 0,
                'monthSales'    => 0,
                'shopName'      => '',
                'couponLabel'   => '',
            );
            if (!empty($goodsId)) {
                $detail = $dtk->GetGoodsDetails($goodsId);
                if ($detail['code'] == 1 && !empty($detail['data'])) {
                    $d = $detail['data'];
                    $item['title']         = $d['title'] ?? '';
                    $item['mainPic']       = $d['mainPic'] ?? '';
                    $item['originalPrice'] = floatval($d['originalPrice'] ?? 0);
                    $item['actualPrice']   = floatval($d['actualPrice'] ?? 0);
                    $item['couponPrice']   = floatval($d['couponPrice'] ?? 0);
                    $item['monthSales']    = intval($d['monthSales'] ?? 0);
                    $item['shopName']      = $d['shopName'] ?? '';
                }
            }
            // ParseContent 兜底补充（GetGoodsDetails 失败或字段缺失时）
            if ($parsed['code'] == 1 && !empty($parsed['data'])) {
                $pd = $parsed['data'];
                $item['title']         = $item['title'] ?: ($pd['title'] ?? '');
                $item['mainPic']       = $item['mainPic'] ?: ($pd['image'] ?? '');
                $item['actualPrice']   = $item['actualPrice'] ?: floatval($pd['price'] ?? 0);
                $item['shopName']      = $item['shopName'] ?: ($pd['shopName'] ?? '');
                // 券类型（如「二合一券」）作为优惠标签展示
                $item['couponLabel']   = $pd['originType'] ?? '';
            }

            // 没有任何商品数据则回退兜底卡片
            if (empty($item['title']) && empty($item['mainPic'])) {
                return null;
            }

            // 跳转链接：优先用原始链接（buy-tb.html 转链 / s.click.taobao.com 短链）
            // 在非框架 url() 环境下也能正常转链
            $buyUrl = $url;

            return $this->renderProductCard(array(
                'title'      => $item['title'] ?? '',
                'pic'        => $item['mainPic'] ?? '',
                'origPrice'  => floatval($item['originalPrice'] ?? 0),
                'actPrice'   => floatval($item['actualPrice'] ?? 0),
                'coupon'     => floatval($item['couponPrice'] ?? 0),
                'sales'      => intval($item['monthSales'] ?? 0),
                'shopName'   => $item['shopName'] ?? '',
                'couponLabel'=> $item['couponLabel'] ?? '',
                'platform'   => 'tb',
                'goodsId'    => $goodsId,
                'buyUrl'     => $buyUrl,
            ));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 兜底商品卡片：无法解析短链时，根据 URL 域名识别平台生成统一卡片
     * 与主站 IndexController::buildFallbackBtn 逻辑一致
     */
    protected function buildFallbackBtn($url)
    {
        $url = trim($url);
        if (empty($url)) return '';

        if (strpos($url, 'pinduoduo.com') !== false || strpos($url, 'yangkeduo.com') !== false) {
            $platform = 'pdd'; $name = '拼多多';
        } elseif (strpos($url, 'jd.com') !== false) {
            $platform = 'jd'; $name = '京东';
        } elseif (strpos($url, 'vip.com') !== false || strpos($url, 'vipshop.com') !== false) {
            $platform = 'vip'; $name = '唯品会';
        } elseif (strpos($url, 'taobao.com') !== false || strpos($url, 'tmall.com') !== false) {
            $platform = 'tb'; $name = '淘宝 / 天猫';
        } else {
            $platform = ''; $name = '';
        }

        if ($platform && $name) {
            // 尝试提取商品 ID（各类链接的常见 param）
            $goodsId = $this->extractGoodsId($url, $platform);
            $buyUrl = function_exists('url')
                ? url('index/redirect/jump', array('platform' => $platform, 'id' => $goodsId ?: $url))
                : ('buy-' . $platform . '.html?id=' . ($goodsId ?: urlencode($url)));
            $args = array(
                'title'      => $name . '好物推荐',
                'pic'        => '',
                'origPrice'  => 0,
                'actPrice'   => 0,
                'coupon'     => 0,
                'sales'      => 0,
                'shopName'   => '',
                'platform'   => $platform,
                'goodsId'    => $goodsId ?: '',
                'buyUrl'     => $buyUrl,
            );
        } else {
            // 完全不认识的链接，直接跳原地址
            $args = array(
                'title'   => '外部好物推荐',
                'pic'     => '',
                'origPrice' => 0, 'actPrice' => 0, 'coupon' => 0, 'sales' => 0,
                'shopName' => '', 'platform' => '', 'goodsId' => '',
                'buyUrl' => $url,
            );
        }
        return $this->renderProductCard($args);
    }

    /** 从 URL 提取商品 ID（按平台） */
    protected function extractGoodsId($url, $platform)
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) return '';
        parse_str($query, $params);
        if ($platform === 'tb')  return $params['id'] ?? '';
        if ($platform === 'jd')  return $params['sku'] ?? $params['wareId'] ?? '';
        if ($platform === 'pdd') return $params['goods_id'] ?? $params['goodsId'] ?? '';
        if ($platform === 'vip') return $params['goods_id'] ?? $params['productId'] ?? '';
        return '';
    }

    /** 渲染 SMZDM 风格商品卡片（与主站 renderProductCard 结构一致） */
    protected function renderProductCard($args)
    {
        $title     = htmlspecialchars($args['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $pic       = htmlspecialchars($args['pic'] ?? '', ENT_QUOTES, 'UTF-8');
        $origPrice = floatval($args['origPrice'] ?? 0);
        $actPrice  = floatval($args['actPrice'] ?? 0);
        $coupon    = floatval($args['coupon'] ?? 0);
        $sales     = intval($args['sales'] ?? 0);
        $shopName  = htmlspecialchars($args['shopName'] ?? '', ENT_QUOTES, 'UTF-8');
        $buyUrl    = htmlspecialchars($args['buyUrl'] ?? '', ENT_QUOTES, 'UTF-8');
        $platform  = $args['platform'] ?? '';
        $couponLabel = htmlspecialchars($args['couponLabel'] ?? '', ENT_QUOTES, 'UTF-8');
        $hasPrice  = ($actPrice > 0);

        $platLabel = '';
        $platClass = '';
        if ($platform === 'tb')  { $platLabel = '淘宝'; $platClass = 'pc-plat-tb'; }
        elseif ($platform === 'jd')  { $platLabel = '京东'; $platClass = 'pc-plat-jd'; }
        elseif ($platform === 'pdd') { $platLabel = '拼多多'; $platClass = 'pc-plat-pdd'; }
        elseif ($platform === 'vip') { $platLabel = '唯品会'; $platClass = 'pc-plat-vip'; }

        $html  = '<div class="product-card">';
        if ($pic) {
            $html .= '<a class="pc-cover" href="' . $buyUrl . '" target="_blank" rel="nofollow">';
            $html .= '<img src="' . $pic . '" alt="' . $title . '" loading="lazy" referrerpolicy="no-referrer">';
            if ($platLabel) $html .= '<span class="pc-plat ' . $platClass . '">' . $platLabel . '</span>';
            $html .= '</a>';
        }
        $html .= '<div class="pc-body">';
        $html .= '<a class="pc-title" href="' . $buyUrl . '" target="_blank" rel="nofollow">' . $title . '</a>';
        if ($hasPrice) {
            $html .= '<div class="pc-prices">';
            $html .= '<span class="pc-cur">¥<b>' . number_format($actPrice, 2) . '</b></span>';
            if ($origPrice > 0 && $origPrice > $actPrice) {
                $html .= '<span class="pc-old">¥' . number_format($origPrice, 2) . '</span>';
            }
            if ($coupon > 0) {
                $html .= '<span class="pc-coupon">券 ¥' . intval($coupon) . '</span>';
            } elseif ($couponLabel) {
                // 无具体券额但有券类型（如「二合一券」）时，展示券类型标签
                $html .= '<span class="pc-coupon pc-coupon-label">' . $couponLabel . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '<div class="pc-meta">';
        if ($shopName) $html .= '<span class="pc-shop">' . $shopName . '</span>';
        if ($sales > 0)  $html .= '<span class="pc-sales">月销 ' . ($sales >= 10000 ? round($sales/10000, 1).'万+' : $sales) . '</span>';
        if (!$shopName && !$sales && $couponLabel) {
            // 既无店铺也无销量时，至少展示券类型，保证「优惠信息」可见
            $html .= '<span class="pc-sales">' . $couponLabel . '</span>';
        }
        $html .= '</div>';
        $html .= '<div class="pc-actions">';
        $html .= '<a class="pc-btn-buy" href="' . $buyUrl . '" target="_blank" rel="nofollow" style="color:#fff !important;">立即去看看</a>';
        $html .= '</div>';
        $html .= '</div></div>';

        return $html;
    }


    /** 简单分页条（兼容伪静态/动态 URL 的 ? 与 & 拼接） */
    protected function buildPager($page, $pageSize, $total)
    {
        $pages = ceil($total / $pageSize);
        if ($pages <= 1) return '';
        // 规范化：去掉 base 自带的 .html 后缀，统一用 “.html?page=N” 形式，避免 “.html-1.html” 畸形
        $base = preg_replace('/\.html$/i', '', $this->plugUrl());
        $html = '<div class="pager">';
        if ($page > 1) {
            $html .= '<a href="' . $base . '.html?page=' . ($page - 1) . '">上一页</a>';
        }
        $html .= '<span class="cur">' . $page . ' / ' . $pages . '</span>';
        if ($page < $pages) {
            $html .= '<a href="' . $base . '.html?page=' . ($page + 1) . '">下一页</a>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * 优惠券（复用主站 yun_items 数据，插件自有模板）
     */
    public function cheaps()
    {
        $db = $this->db();
        $page = max(1, intval($this->arg('page', 1)));
        $pageSize = 24;
        $where = array("`del` = 0");
        $total = $db->dataCount('yun_items', $where);
        $list = $db->dataSelect(
            'yun_items',
            $where,
            "`id` DESC LIMIT " . (($page - 1) * $pageSize) . ", {$pageSize}"
        );
        $list = $list ?: array();
        // 统一字段，便于模板渲染
        foreach ($list as &$item) {
            $item['title']   = $item['title'] ?? ($item['goods_name'] ?? '');
            $item['img']     = $item['mainPic'] ?? ($item['main_pic'] ?? ($item['image'] ?? ''));
            $item['coupon']  = $item['couponPrice'] ?? ($item['coupon_price'] ?? ($item['coupon'] ?? ''));
            $item['price']   = $item['actualPrice'] ?? ($item['price'] ?? ($item['now_price'] ?? ''));
            $item['origin']  = $item['originalPrice'] ?? ($item['original_price'] ?? '');
            $item['sales']   = $item['sales'] ?? ($item['monthSales'] ?? 0);
            $gid = $item['goodsId'] ?? ($item['goods_id'] ?? ($item['id'] ?? 0));
            $from = $item['item_from'] ?? ($item['laiyuan'] ?? 1);
            // 平台归一化：数字 1/2/3/4 或字符串 taobao/jd/pdd/vip 都统一为 tb/jd/pdd/vip，
            // 保证站点转链 buy-<platform>.html 的 platform 是白名单值（否则跳转失败）。
            $fromMap = array(
                1 => 'tb', 2 => 'pdd', 3 => 'vip', 4 => 'jd',
                'taobao' => 'tb', 'tmall' => 'tb', 'tb' => 'tb',
                'jd' => 'jd', 'jd.com' => 'jd',
                'pdd' => 'pdd', 'pinduoduo' => 'pdd', 'yangkeduo' => 'pdd',
                'vip' => 'vip', 'vip.com' => 'vip', 'vipshop' => 'vip',
            );
            $fromKey = is_numeric($from) ? intval($from) : strtolower(trim($from));
            $item['from'] = $fromMap[$fromKey] ?? 'tb';
            // 优惠券页「立即领券」直接走站点转链（buy-<platform>.html?id=大淘客商品ID），
            // 打开即跳转淘宝/对应平台领券购买；不跳主站详情页，保持在插件导购站内体验一致。
            $item['buy_url'] = function_exists('url')
                ? url('index/redirect/jump', array('platform' => $item['from'], 'id' => $gid))
                : ('buy-' . $item['from'] . '.html?id=' . $gid);
        }
        unset($item);

        $this->assign(array(
            'mod'    => 'cheaps',
            'list'   => $list,
            'total'  => $total,
            'pager'  => $this->buildModPager($page, $pageSize, $total, 'cheaps'),
            'page_title' => '优惠券 - ' . $this->siteName(),
            'page_keywords'   => '优惠券,领券中心,淘宝优惠券,京东优惠券,拼多多优惠券,唯品会优惠券,' . $this->siteName(),
            'page_description'=> '每日更新大额优惠券与券后低价好物，覆盖淘宝、京东、拼多多、唯品会，先领券再下单立省，' . $this->siteName() . ' 优惠券专区帮你花更少买更好。',
        ));
        $this->display('cheaps');
    }

    /**
     * 大牌（复用主站大淘客接口 Tjk::getBrandList，插件自有模板）
     */
    public function brand()
    {
        $page = max(1, intval($this->arg('page', 1)));
        $tjk = new \ZhiCms\ext\Tjk();
        $result = $tjk->getBrandList(20, (string) $page, '');
        $list = $result['brands'] ?? array();
        $total = $result['total'] ?? 0;
        $tips = ($result['code'] != 1) ? ($result['message'] ?? '大牌数据获取失败') : '';

        $this->assign(array(
            'mod'   => 'brand',
            'list'  => $list,
            'total' => $total,
            'tips'  => $tips,
            'pager' => $this->buildModPager($page, 20, $total, 'brand'),
            'page_title' => '大牌 - ' . $this->siteName(),
            'page_keywords'   => '大牌优惠,品牌折扣,品牌优惠券,品牌特卖,' . $this->siteName(),
            'page_description'=> '汇集各大品牌官方折扣与限时特卖，天猫京东拼多多大牌优惠券一键领，' . $this->siteName() . ' 大牌专区带你用更优价格入手品质好物。',
        ));
        $this->display('brand');
    }

    /**
     * 风云榜（复用主站大淘客接口 Tjk::getRankingList，插件自有模板）
     */
    public function rank()
    {
        $type = intval($this->arg('type', 1));
        $types = array(1 => '实时榜', 2 => '全天热销榜', 3 => '热推榜', 7 => '综合热搜榜');
        if (!isset($types[$type])) $type = 1;
        $tjk = new \ZhiCms\ext\Tjk();
        $result = $tjk->getRankingList($type, '', 100, '1');
        $list = $result['items'] ?? array();
        $tips = ($result['code'] != 1) ? ($result['message'] ?? '榜单数据获取失败') : '';

        $this->assign(array(
            'mod'      => 'rank',
            'rankType' => $type,
            'types'    => $types,
            'list'     => $list,
            'tips'     => $tips,
            'page_title' => $types[$type] . ' - ' . $this->siteName(),
            'page_keywords'   => $types[$type] . ',热门榜单,热销排行,人气好物,网购排行榜,' . $this->siteName(),
            'page_description'=> $this->siteName() . ' ' . $types[$type] . '实时更新，汇总全网最热销、最受欢迎的优惠好物与高人气商品，跟着榜单买不踩坑，省钱又省心。',
        ));
        $this->display('rank');
    }

    /** 站点名（供 page_title 使用） */
    protected function siteName()
    {
        return obj('base/Base')->SiteConfig('sitename') ?: 'ZhiCms';
    }

    /** 模块分页条（基于插件 mod URL） */
    protected function buildModPager($page, $pageSize, $total, $mod)
    {
        $pages = ceil($total / $pageSize);
        if ($pages <= 1) return '';
        // 规范化：去掉 base 自带的 .html 后缀，统一用 “.html?page=N” 形式，避免 “.html-1.html” 畸形
        $base = preg_replace('/\.html$/i', '', $this->plugUrl($mod));
        $html = '<div class="pager">';
        if ($page > 1) {
            $html .= '<a href="' . $base . '.html?page=' . ($page - 1) . '">上一页</a>';
        }
        $html .= '<span class="cur">' . $page . ' / ' . $pages . '</span>';
        if ($page < $pages) {
            $html .= '<a href="' . $base . '.html?page=' . ($page + 1) . '">下一页</a>';
        }
        $html .= '</div>';
        return $html;
    }
}
