<?php
namespace app\index\controller;

class CheapsController extends \app\base\controller\BaseController
{
    /**
     * 优惠券商城首页 - 仿什么值得买精选页面
     */
    public function index()
    {
        $Siteinfo = \app\common\ConfigStore::load('site');
        
        // 获取请求参数并过滤
        $categoryId = max(0, intval($this->arg("id")));
        $searchKey = trim(htmlspecialchars($this->arg("key"), ENT_QUOTES, 'UTF-8'));
        // 再次过滤，避免空值或非法字符造成问题
        $searchKey = preg_replace('/[<>&"\']/', '', $searchKey);
        $searchKey = addslashes($searchKey);
        
        // 构建查询条件
        $where = [];
        $baseUrlParams = [];
        $urlSeparator = "?";
        
        if ($categoryId > 0 && $searchKey) {
            $where[] = "`cid` = {$categoryId} AND `title` LIKE '%{$searchKey}%'";
            $baseUrlParams["id"] = $categoryId;
            $baseUrlParams["key"] = urlencode($searchKey);
            $urlSeparator = "&";
        } elseif ($categoryId > 0) {
            $where[] = "`cid` = {$categoryId}";
            $baseUrlParams["id"] = $categoryId;
            $urlSeparator = "&";
        } elseif ($searchKey) {
            $where[] = "`title` LIKE '%{$searchKey}%'";
            $baseUrlParams["key"] = urlencode($searchKey);
            $urlSeparator = "&";
        } else {
            $where[] = "`del` = 0";
        }
        
        // 构建分页URL
        if (!empty($baseUrlParams)) {
            $route = 'index/cheaps/index';
            $baseUrl = url($route, $baseUrlParams);
        } else {
            $baseUrl = url('index/cheaps/index', []);
        }
        
        // 获取分页数据 - 每页48条优惠券商品
        try {
            $page = obj('api/ApiData')->page("48", "yun_items", $where, "`id` DESC", $baseUrl, $urlSeparator);
        } catch (\Exception $e) {
            // 如果数据获取失败，给默认数据
            $page = [
                'list' => [], 
                'count' => 0, 
                'page' => '',
                'total' => 0
            ];
        }
        
        // 分配数据到模板
        $this->page = $page;
        $this->totalItems = $page['count'] ?? 0;
        $this->currentCategoryId = $categoryId;
        $this->searchKeyword = $searchKey;
        $this->siteInfo = $Siteinfo;
        
        $this->setSEOInfo($categoryId, $searchKey);
        
        $this->categories = $this->getAllCategories();
        
        // 加载公共侧边栏数据
        $this->loadCommonSidebar();

        $this->display();
    }
    
    /**
     * 优惠券商品独立详情页 - 显示产品详情信息
     */
    public function detail()
    {
        $goodsId = trim($this->arg('id'));
        $type = $this->arg('type');
        $valid = array('tb', 'jd', 'pdd', 'vip', 'taobao');
        $platform = ($type && in_array($type, $valid)) ? $type : 'tb';
        if ($platform == 'taobao' || $platform == 'dtk') {
            $platform = 'tb';
        }

        $item = null;
        $itemFromDb = null;

        if ($goodsId) {
            $itemFromDb = obj('api/ApiData')->thisQuery("SELECT * FROM yun_items WHERE goodsId = ? AND del = 0 LIMIT 1", array($goodsId));
            if (!empty($itemFromDb)) {
                $itemFromDb = $itemFromDb[0];
                if (!$platform || $platform == 'taobao' || $platform == 'dtk') {
                    $dbFrom = $itemFromDb['item_from'] ?? ($itemFromDb['laiyuan'] == 1 ? 'taobao' : ($itemFromDb['laiyuan'] == 4 ? 'jd' : ($itemFromDb['laiyuan'] == 2 ? 'pdd' : ($itemFromDb['laiyuan'] == 3 ? 'vip' : 'taobao'))));
                    if ($dbFrom == 'dtk' || $dbFrom == 'taobao') {
                        $dbFrom = 'tb';
                    }
                    $platform = $dbFrom;
                }
            }
        }

        if ($platform && $goodsId) {
            $tjk = new \ZhiCms\ext\Tjk();
            $res = $tjk->getGoodsDetail($goodsId, $platform);
            // getGoodsDetail 返回键为 'data'（Dtk/Hdk::GetGoodsDetails 输出），兼容旧 'item' 键
            $resData = !empty($res['data']) ? $res['data'] : (!empty($res['item']) ? $res['item'] : null);
            if ($res['code'] == 1 && !empty($resData)) {
                $item = $resData;
                if (!empty($itemFromDb)) {
                    $item = array_merge($itemFromDb, $item);
                }
            }
        }

        if (empty($item) && !empty($itemFromDb)) {
            $item = $itemFromDb;
        }

        if (!empty($item)) {
            $this->ret = $item;
            $this->platform = $platform;
            $this->platformName = array('taobao' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会')[$platform] ?? '商城';
            $this->savedAmount = number_format(floatval($item['originalPrice'] ?? 0) - floatval($item['actualPrice'] ?? 0), 2);
            $this->breadcrumbJson = $this->buildBreadcrumbJson($platform);
            $this->setDetailSeo($item, $platform);
            // 解析详情图
            $detailPics = $item['detailPics'] ?? '';
            if (is_string($detailPics)) {
                $detailPics = json_decode($detailPics, true);
            }
            $this->detailPicsHtml = '';
            if (is_array($detailPics) && !empty($detailPics)) {
                foreach ($detailPics as $img) {
                    if (!empty($img)) {
                        $this->detailPicsHtml .= '<img src="' . htmlspecialchars($img) . '" alt="商品详情" style="width:100%;height:auto;margin-bottom:12px;border-radius:8px;">';
                    }
                }
            }
            $this->loadCommonSidebar();
            $this->display('app/index/view/cheaps/detail');
            return;
        }

        $this->ret = null;
        $this->platform = $platform;
        $this->platformName = array('taobao' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会')[$platform] ?? '商城';
        $this->savedAmount = '0.00';
        $this->breadcrumbJson = '';
        $this->errmsg = '优惠券商品信息获取失败，请稍后重试';
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $this->pageTitle = '优惠券详情 - ' . $siteName;
        $this->loadCommonSidebar();
        $this->display('app/index/view/cheaps/detail');
    }

    /**
     * 设置详情页SEO
     */
    private function setDetailSeo($item, $platform)
    {
        $title = $item['title'] ?? '';
        $platformName = array('taobao' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会')[$platform] ?? '商城';
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $this->pageTitle = $title . ' - ' . $platformName . '优惠券 - ' . $siteName;
        $this->pageKeywords = ($item['dtitle'] ?? '') . ',' . obj('base/Base')->SEO('cheaps_keywords') . ',' . $platformName;
        $this->pageDescription = mb_substr(strip_tags($item['content'] ?? $item['dtitle'] ?? $title), 0, 180, 'UTF-8') ?: ($title . '|' . $platformName . '优惠券');
        $this->canonicalUrl = obj('base/Base')->SiteConfig('hosturl') . 'cheaps-detail.html?type=' . urlencode($platform) . '&id=' . urlencode($item['goodsId'] ?? '');
        if (!empty($item['mainPic'])) {
            $this->ogImage = $item['mainPic'];
        }
    }

    /**
     * 生成面包屑 JSON-LD（替代模板内 {php} 块，兼容 think 引擎）
     */
    private function buildBreadcrumbJson($platform)
    {
        $siteUrl = rtrim(obj('base/Base')->SiteConfig('hosturl'), '/');
        $platformName = array('taobao' => '淘宝', 'jd' => '京东', 'pdd' => '拼多多', 'vip' => '唯品会')[$platform] ?? '商城';
        $breadcrumb = array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array(
                array('@type' => 'ListItem', 'position' => 1, 'name' => '首页', 'item' => $siteUrl . '/'),
                array('@type' => 'ListItem', 'position' => 2, 'name' => '优惠券中心', 'item' => $siteUrl . '/cheaps.html'),
                array('@type' => 'ListItem', 'position' => 3, 'name' => $platformName . '优惠券', 'item' => $siteUrl . '/cheaps.html')
            )
        );
        return json_encode($breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 获取所有分类用于导航
     */
    public function getAllCategories()
    {
        return [
            ['id' => 1, 'name' => '女装'],
            ['id' => 2, 'name' => '母婴'], 
            ['id' => 3, 'name' => '化妆品'],
            ['id' => 4, 'name' => '居家'],
            ['id' => 5, 'name' => '鞋包配饰'],
            ['id' => 6, 'name' => '美食'],
            ['id' => 7, 'name' => '文体车品'],
            ['id' => 8, 'name' => '数码家电'],
            ['id' => 9, 'name' => '男装'],
            ['id' => 10, 'name' => '内衣'],
            ['id' => 11, 'name' => '箱包'],
            ['id' => 12, 'name' => '配饰'],
            ['id' => 13, 'name' => '户外运动'],
            ['id' => 14, 'name' => '家装家纺']
        ];
    }
    
    /**
     * 设置SEO信息
     */
    private function setSEOInfo($categoryId, $searchKey)
    {
        $siteName = obj('base/Base')->SiteConfig('sitename');
        $baseTitle = obj('base/Base')->SEO('cheaps_title');
        
        if ($categoryId > 0) {
            $categoryName = $this->lists($categoryId, 'y');
            if ($categoryName) {
                $nameParts = explode(',', $categoryName);
                $this->pageTitle = $nameParts[0] . '优惠券 - ' . $baseTitle;
            } else {
                $this->pageTitle = '分类优惠券 - ' . $baseTitle;
            }
        } elseif ($searchKey) {
            $this->pageTitle = "'{$searchKey}' 搜索结果 - " . $baseTitle;
        } else {
            $this->pageTitle = $baseTitle ?: ('优惠券 - ' . $siteName);
        }
        
        $this->pageKeywords = obj('base/Base')->SEO('cheaps_keywords') ?: obj('base/Base')->SiteConfig('sitekeywords');
        $this->pageDescription = obj('base/Base')->SEO('cheaps_dec') ?: obj('base/Base')->SiteConfig('sitedescription');
        $this->siteTitle = $siteName;
        $this->canonicalUrl = obj('base/Base')->SiteConfig('hosturl') . 'cheaps.html';
    }
}