<?php
namespace app\index\controller;

/**
 * 榜单中心（风云榜）
 * 对接大淘客「各大榜单」接口 get-ranking-list（文档 id=6）
 * 原 HotController 的热榜逻辑已合并进本控制器，HotController 现改为「线报」栏目。
 */
class RankController extends \app\base\controller\BaseController {

    /** 榜单类型定义（顺序即前端标签展示顺序） */
    public static $rankTypes = [
        1 => ['name' => '实时榜',     'icon' => '⚡', 'desc' => '实时更新的高转化热销好货'],
        2 => ['name' => '全天热销榜', 'icon' => '🔥', 'desc' => '当日全天销量领先的爆款商品'],
        3 => ['name' => '热推榜',     'icon' => '🚀', 'desc' => '导购热推、佣金给力的优选商品'],
        7 => ['name' => '综合热搜榜', 'icon' => '🔎', 'desc' => '综合搜索热度最高的当红商品'],
    ];

    public function index(){
        $this->renderRank(1);
    }

    /**
     * 渲染榜单页（RankController 与 HotController 共用）
     * @param int $defaultType 未指定 type 时的默认榜单类型
     */
    protected function renderRank($defaultType = 1){
        $type = intval($this->arg('type'));
        if (!isset(self::$rankTypes[$type])) {
            $type = $defaultType;
        }
        $cid = $this->arg('cid');

        $tjk = new \ZhiCms\ext\Tjk();
        $result = $tjk->getRankingList($type, $cid, 100, '1');

        if (empty($result) || $result['code'] != 1) {
            $this->tips = $result['message'] ?? '榜单数据获取失败';
        }

        $this->rankType  = $type;
        $this->rankInfo  = self::$rankTypes[$type];
        $this->rankTypes = self::$rankTypes;
        $this->list      = $result['items'] ?? [];
        $this->cid       = $cid;
        $this->title     = self::$rankTypes[$type]['name'];

        // SEO：榜单页
        $rankTitle = self::$rankTypes[$type]['name'];
        $this->pageTitle = $rankTitle . ' - ' . (obj('base/Base')->SEO('rank_title') ?: '风云榜') . ' - ' . obj('base/Base')->SiteConfig('sitename');
        $this->pageKeywords = $rankTitle . ',' . (obj('base/Base')->SEO('rank_keywords') ?: '');
        $this->pageDescription = self::$rankTypes[$type]['desc'] ?? (obj('base/Base')->SEO('rank_dec') ?: '');
        $this->canonicalUrl = obj('base/Base')->SiteConfig('hosturl') . 'rank.html';

        // 加载公共侧边栏（分类 + 热门文章等）
        $this->loadCommonSidebar();

        // 强制使用榜单视图，确保 HotController 复用时模板一致
        $this->display('app/index/view/rank/index');
    }
}
