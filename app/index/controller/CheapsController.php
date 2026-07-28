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