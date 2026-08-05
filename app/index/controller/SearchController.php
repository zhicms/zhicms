<?php
namespace app\index\controller;
class SearchController extends \app\base\controller\BaseController
{
    public $platforms = [
        'local'   => ['name' => '本地', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" fill="#667eea"/><rect x="6" y="9" width="12" height="2" fill="#fff"/><rect x="6" y="13" width="8" height="2" fill="#fff"/></svg>'],
        'taobao' => ['name' => '淘宝', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><circle cx="12" cy="12" r="10" fill="#ff5000"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="10" font-weight="bold">淘</text></svg>'],
        'jd'     => ['name' => '京东', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" fill="#e4393c"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="10" font-weight="bold">京</text></svg>'],
        'pdd'    => ['name' => '拼多多', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><circle cx="12" cy="12" r="10" fill="#e02e24"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold">拼</text></svg>'],
        'vip'    => ['name' => '唯品会', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><rect x="3" y="3" width="18" height="18" rx="4" fill="#e60012"/><text x="12" y="16" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold">V</text></svg>'],
        'compare' => ['name' => '比价', 'icon' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5z" fill="#9b59b6"/><path d="M2 17l10 5 10-5" stroke="#9b59b6" stroke-width="2" fill="none"/><path d="M2 12l10 5 10-5" stroke="#9b59b6" stroke-width="2" fill="none"/></svg>'],
    ];

    public function index(){
        $content = trim(urldecode($this->arg("content", '')));
        $platform = strtolower($this->arg("platform", 'local'));
        $pageNum = max(1, intval($this->arg("page", 1)));
        
        if (!array_key_exists($platform, $this->platforms)) {
            $platform = 'local';
        }

        if ($platform === 'taobao' && preg_match('/(taobao\.com|tmall\.com)/i', $content)) {
            $goodsId = $this->resolveTaobaoGoodsId($content);
            if ($goodsId) {
                $url = url($route='index/view/detail/id=<id>', $params=array('id' => $goodsId, 'type' => 'taobao'));
                $this->title = '';
                $this->toUrl = $url;
                $this->display('app/index/view/search/to');
                exit();
            }
        }

        $this->key = $content;
        $this->keyword = $content;
        $this->q = urlencode($content);
        $this->platform = $platform;
        $this->platforms = $this->platforms;

        // SEO：搜索页
        if (!empty($content)) {
            $this->pageTitle = '"' . $content . '" 的搜索结果 - ' . obj('base/Base')->SiteConfig('sitename');
            $this->pageDescription = '搜索"' . $content . '"的相关商品和优惠信息';
        } else {
            $this->setPageSEO(
                obj('base/Base')->SEO('search_title') ?: '搜索',
                obj('base/Base')->SEO('search_keywords'),
                obj('base/Base')->SEO('search_dec')
            );
        }
        $this->pageKeywords = !empty($content) ? $content . ',搜索,' . obj('base/Base')->SEO('search_keywords') : obj('base/Base')->SEO('search_keywords');

        $this->loadCommonSidebar();

        if (empty($content)) {
            $this->page = array('list' => array(), 'count' => 0, 'pages' => '');
            $this->Page = $this->page;
            $this->emptyTip = '请输入搜索关键词';
            $this->display('app/index/view/search/index');
            exit();
        }

        if ($platform === 'compare') {
            $this->searchCompare($content, $pageNum);
        } else {
            $this->searchByKeyword($content, $platform, $pageNum);
        }
    }

    private function searchByKeyword($keyword, $platform = 'local', $pageNum = 1){
        $pageSize = 20;
        
        if ($platform === 'local') {
            $localResult = $this->searchLocal($keyword, $platform, $pageNum, $pageSize);
            if (!empty($localResult['list'])) {
                $this->page = $localResult;
                $this->Page = $localResult;
                $this->dataSource = 'local';
                $this->display('app/index/view/search/index');
                exit();
            }
            $this->page = array('list' => array(), 'count' => 0, 'pages' => '');
            $this->Page = $this->page;
            $this->dataSource = 'none';
            $this->emptyTip = '本地库中没有找到相关商品';
            $this->display('app/index/view/search/index');
            exit();
        }

        $apiResult = $this->searchApi($keyword, $platform, $pageNum, $pageSize);
        
        if ($apiResult['code'] == 1 && !empty($apiResult['items'])) {
            $list = $apiResult['items'];
            foreach ($list as &$it) {
                $this->buildBuyUrl($it);
            }
            unset($it);

            $root = "so.html?content=" . urlencode($keyword) . "&platform=" . $platform;
            $listUrl = $root . "&page={page}";
            $total = $apiResult['total'];

            $pages = new \ZhiCms\ext\PageIndex;
            $array = array('list' => $list, 'count' => $total, 'pages' => $pages->show($listUrl, $total, $pageSize));
            $this->page = $array;
            $this->Page = $array;
            $this->dataSource = 'api';
            $this->display('app/index/view/search/index');
            exit();
        }

        $this->page = array('list' => array(), 'count' => 0, 'pages' => '');
        $this->Page = $this->page;
        $this->dataSource = 'none';
        $this->emptyTip = $apiResult['message'] ?? '没有找到相关商品';
        $this->display('app/index/view/search/index');
    }

    private function searchCompare($keyword, $pageNum = 1){
        $pageSize = 20;

        $localGroups = $this->searchLocalCompare($keyword);
        
        $apiGroups = array();
        $client = $this->createTjkClient();
        if ($client) {
            $all = array();

            $tbResp = $client->searchGoods($keyword, 'taobao', 1, $pageSize);
            if ($tbResp['code'] == 1 && !empty($tbResp['items'])) {
                $all = array_merge($all, $tbResp['items']);
            }

            if ($client->getHdk()) {
                $jdResp = $client->searchGoods($keyword, 'jd', 1, $pageSize);
                if ($jdResp['code'] == 1 && !empty($jdResp['items'])) {
                    $all = array_merge($all, $jdResp['items']);
                }
                $pddResp = $client->searchGoods($keyword, 'pdd', 1, $pageSize);
                if ($pddResp['code'] == 1 && !empty($pddResp['items'])) {
                    $all = array_merge($all, $pddResp['items']);
                }
            }

            if (!empty($all)) {
                $apiGroups = $this->aggregateSameItems($all);
            }
        }

        $allGroups = array_merge($localGroups, $apiGroups);
        $seen = array();
        $dedup = array();
        foreach ($allGroups as $g) {
            $key = $g['repTitle'] ?? '';
            if (!empty($key) && !isset($seen[$key])) {
                $seen[$key] = true;
                $dedup[] = $g;
            }
        }

        usort($dedup, function($a, $b) {
            $pa = floatval($a['minPrice'] ?? PHP_FLOAT_MAX);
            $pb = floatval($b['minPrice'] ?? PHP_FLOAT_MAX);
            if (abs($pa - $pb) > 0.001) {
                return $pa <=> $pb;
            }
            return ($b['platformCount'] ?? 0) <=> ($a['platformCount'] ?? 0);
        });

        $total = count($dedup);
        $offset = ($pageNum - 1) * $pageSize;
        $pagedGroups = array_slice($dedup, $offset, $pageSize);

        foreach ($pagedGroups as &$g) {
            foreach ($g['items'] as &$it) {
                $this->buildBuyUrl($it);
            }
            unset($it);
        }
        unset($g);

        $root = "so.html?content=" . urlencode($keyword) . "&platform=compare";
        $listUrl = $root . "&page={page}";

        $pages = new \ZhiCms\ext\PageIndex;
        $array = array('list' => $pagedGroups, 'count' => $total, 'pages' => $pages->show($listUrl, $total, $pageSize));
        $this->page = $array;
        $this->Page = $array;
        $this->dataSource = 'compare';
        $this->display('app/index/view/search/index');
    }

    private function searchLocal($keyword, $platform, $pageNum, $pageSize){
        $where = array();
        $where[] = "`del` = 0";
        $where[] = "`title` LIKE '%" . addslashes($keyword) . "%'";

        if ($platform !== 'local') {
            $laiyuan = $this->platformToLaiyuan($platform);
            $where[] = "`laiyuan` = {$laiyuan}";
        }

        $baseUrl = "so.html?content=" . urlencode($keyword) . "&platform=" . $platform;
        
        $page = obj('api/ApiData')->page($pageSize, "yun_items", $where, "`id` DESC", $baseUrl);
        
        if (!empty($page['list'])) {
            foreach ($page['list'] as &$it) {
                $this->buildBuyUrl($it);
            }
            unset($it);
        }

        return $page;
    }

    private function searchLocalCompare($keyword){
        $where = array();
        $where[] = "`del` = 0";
        $where[] = "`title` LIKE '%" . addslashes($keyword) . "%'";

        $items = obj('api/ApiData')->dataSelect("yun_items", $where, "`id` DESC LIMIT 0, 30");
        
        if (empty($items)) {
            return array();
        }

        foreach ($items as &$it) {
            $this->buildBuyUrl($it);
        }
        unset($it);

        return $this->aggregateSameItems($items);
    }

    private function searchApi($keyword, $platform, $pageNum, $pageSize){
        $client = $this->createTjkClient();
        if (!$client) {
            return ['code' => 0, 'message' => 'API未配置', 'items' => [], 'total' => 0];
        }

        return $client->searchGoods($keyword, $platform, $pageNum, $pageSize);
    }

    private function buildBuyUrl(&$item){
        $item['buyUrl'] = '';
        
        $goodsId = $item['goodsId'] ?? '';
        $platform = $item['item_from'] ?? ($item['laiyuan'] == 2 ? 'pdd' : ($item['laiyuan'] == 4 ? 'jd' : ($item['laiyuan'] == 3 ? 'vip' : 'taobao')));

        // 统一走 Tjk 转链，不再用 itemLink 直跳（itemLink 为原始链接，无佣金）
        if (!empty($goodsId)) {
            $item['buyUrl'] = url($route='index/redirect/jump', ['platform' => $platform, 'id' => $goodsId]);
        } else {
            $title = urlencode($item['title'] ?? '');
            switch ($platform) {
                case 'jd':  $item['buyUrl'] = 'https://search.jd.com/Search?keyword=' . $title; break;
                case 'pdd': $item['buyUrl'] = 'https://mobile.yangkeduo.com/search_result.html?search_key=' . $title; break;
                case 'vip': $item['buyUrl'] = 'https://search.vip.com/s/?keyword=' . $title; break;
                default:    $item['buyUrl'] = 'https://s.taobao.com/search?q=' . $title;
            }
        }
    }

    private function aggregateSameItems(array $items): array {
        $groups = [];
        foreach ($items as $item) {
            $title = trim($item['title'] ?? '');
            if ($title === '') continue;
            $norm = $this->normTitle($title);
            $bestIdx = -1;
            $bestSim = 0.0;
            foreach ($groups as $gi => $g) {
                $sim = $this->titleSim($norm, $g['norm']);
                if ($sim > $bestSim) {
                    $bestSim = $sim;
                    $bestIdx = $gi;
                }
            }
            if ($bestIdx >= 0 && $bestSim >= 0.5) {
                $groups[$bestIdx]['items'][] = $item;
            } else {
                $groups[] = [
                    'norm'     => $norm,
                    'repTitle' => $title,
                    'repPic'   => $item['mainPic'] ?? '',
                    'items'    => [$item],
                ];
            }
        }

        foreach ($groups as &$g) {
            $priceByPlatform = [];
            $minPrice = PHP_FLOAT_MAX;
            foreach ($g['items'] as $it) {
                $pf = $it['item_from'] ?? ($it['laiyuan'] == 2 ? 'pdd' : ($it['laiyuan'] == 4 ? 'jd' : ($it['laiyuan'] == 3 ? 'vip' : 'taobao')));
                $price = floatval($it['actualPrice'] ?? $it['price'] ?? 0);
                if (!isset($priceByPlatform[$pf]) || $price < $priceByPlatform[$pf]['price']) {
                    $priceByPlatform[$pf] = ['price' => $price, 'item' => $it];
                }
                if ($price < $minPrice) {
                    $minPrice = $price;
                }
            }
            $g['priceByPlatform'] = $priceByPlatform;
            $g['minPrice'] = $minPrice == PHP_FLOAT_MAX ? 0 : $minPrice;
            $g['platformCount'] = count($priceByPlatform);
        }
        unset($g);

        usort($groups, function($a, $b) {
            if (abs($a['minPrice'] - $b['minPrice']) > 0.001) {
                return $a['minPrice'] <=> $b['minPrice'];
            }
            return $b['platformCount'] <=> $a['platformCount'];
        });

        return $groups;
    }

    private function normTitle(string $t): string {
        $t = preg_replace('/[^\p{Han}A-Za-z0-9]/u', '', $t);
        $stop = ['包邮','券后','优惠券','旗舰店','官方','正品','同款','现货','顺丰','免运费','百亿补贴',
            '天猫','淘宝','京东','拼多多','京东自营','促销','热销','爆款','新品','包退','运费险','专柜',
            '代购','原装','正品保证','限时','秒杀','抢','拍下','立减','满减','折扣','买','送','赠','元','个'];
        $t = str_replace($stop, '', $t);
        return mb_strtolower($t, 'UTF-8');
    }

    private function titleSim(string $a, string $b): float {
        if ($a === '' || $b === '') return 0;
        if ($a === $b) return 1;
        $ga = $this->bigrams($a);
        $gb = $this->bigrams($b);
        if (empty($ga) || empty($gb)) return 0;
        $inter = count(array_intersect($ga, $gb));
        $union = count(array_unique(array_merge($ga, $gb)));
        return $union ? $inter / $union : 0;
    }

    private function bigrams(string $s): array {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($chars);
        if ($n <= 1) return $chars;
        $bg = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $bg[] = $chars[$i] . $chars[$i + 1];
        }
        return $bg;
    }

    private function platformToLaiyuan($platform) {
        switch (strtolower($platform)) {
            case 'pdd': return 2;
            case 'vip': return 3;
            case 'jd':  return 4;
            default:    return 1;
        }
    }

    private function createTjkClient() {
        $api = \app\common\ConfigStore::load('api');
        $dtkAppKey = $api['dtk_appkey'] ?? '';
        $dtkAppSecret = $api['dtk_appsecret'] ?? '';
        $hdkApiKey = $api['hdk_appkey'] ?? '';

        if (empty($dtkAppKey) && empty($hdkApiKey)) {
            return null;
        }

        return new \ZhiCms\ext\Tjk([
            'DtkappKey' => $dtkAppKey,
            'DtkappSecret' => $dtkAppSecret,
            'HdkApiKey' => $hdkApiKey,
        ]);
    }

    private function resolveTaobaoGoodsId($url) {
        $cacheKey = 'search_tb_gid_' . md5($url);
        return tcache($cacheKey, function() use ($url) {
            try {
                $api = \app\common\ConfigStore::load('api');
                $dtkAppKey = $api['dtk_appkey'] ?? '';
                $dtkAppSecret = $api['dtk_appsecret'] ?? '';
                if (empty($dtkAppKey) || empty($dtkAppSecret)) return null;

                $tjk = new \ZhiCms\ext\Tjk();
                $dtk = $tjk->getDtk();
                if (!$dtk) return null;

                $parsed = $dtk->ParseContent($url);
                if ($parsed['code'] == 1 && !empty($parsed['data']['goodsId'])) {
                    return $parsed['data']['goodsId'];
                }

                $twd = $dtk->TwdToTwd($url);
                if ($twd['code'] == 1 && !empty($twd['data']['goodsId'])) {
                    return $twd['data']['goodsId'];
                }
                return null;
            } catch (\Exception $e) {
                return null;
            }
        }, 600);
    }
}