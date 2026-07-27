<?php
namespace app\api\controller;

/**
 * 首页好价爆料 Feed（仿什么值得买）
 *
 * 直接读取 ZhiCms 主程序爆料数据表 yun_items，与网站“优惠券商城/什么值得买精选页”同源，
 * 为小程序“首页”提供 SMZDM 风格的信息流。后续若要接入点赞/评论/收藏等互动数据，
 * 只需在此基础上关联 yun_like / yun_comment 等表即可。
 *
 * GET index.php?r=api/feed/index&cid=0&keyword=&page=1
 */
class FeedController extends ApiBaseController {

    // 分类映射（与 CheapsController::getAllCategories 保持一致）
    private $cats = array(
        1  => '女装', 2  => '母婴', 3  => '化妆品', 4  => '居家',
        5  => '鞋包配饰', 6  => '美食', 7  => '文体车品', 8  => '数码家电',
        9  => '男装', 10 => '内衣', 11 => '箱包', 12 => '配饰',
        13 => '户外运动', 14 => '家装家纺',
    );

    public function index() {
        $this->options();

        $cid     = intval($this->raw('cid', 0));
        $page    = max(1, intval($this->raw('page', 1)));
        $keyword = trim($this->raw('keyword', ''));

        $where = array();
        $where[] = "`del` = 0";
        if ($cid > 0) {
            $where[] = "`cid` = {$cid}";
        }
        if ($keyword !== '') {
            // 防 LIKE 注入：转义 % _ \
            $kw = str_replace(array('%', '_', '\\'), array('\%', '\_', '\\\\'), $keyword);
            $where[] = "`title` LIKE '%{$kw}%'";
        }

        $all = obj('api/ApiData')->dataSelect('yun_items', $where, '`top` DESC, `id` DESC');
        if (empty($all)) {
            $all = array();
        }

        $total    = count($all);
        $pageSize = 10;
        $list     = array_slice($all, ($page - 1) * $pageSize, $pageSize);
        $items    = array_map(array($this, 'mapItem'), $list);

        $this->json(array(
            'code'      => 1,
            'message'   => 'success',
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
            'items'     => $items,
            'categories'=> $this->catList(),
        ));
    }

    /**
     * 映射为小程序 SMZDM 风格字段
     */
    protected function mapItem($it) {
        if (empty($it) || !is_array($it)) {
            return array();
        }
        $title     = $it['dtitle'] ?: $it['title'];
        $price     = floatval($it['actualPrice']);
        $orig      = floatval($it['originalPrice']);
        $discount  = $orig > 0 ? round($price / $orig * 10, 1) : 0;
        $shopType  = intval($it['shopType']);
        $monthSales= intval($it['monthSales']);

        // 值率：根据折扣力度与月销推导一个稳定的展示指标（非真实用户投票）。
        // 后续接入 yun_like 等真实点赞数据后，可替换为真实「值 / 不值」比例。
        $worthRate = 0;
        if ($discount > 0) {
            $worthRate = min(99, max(60, intval(60 + (10 - $discount) * 4 + ($monthSales % 6))));
        }

        return array(
            'id'           => $it['id'],
            'goodsId'      => $it['goodsId'],
            'goodsSign'    => $it['goodsSign'],   // 大淘客商品标识，转链（领取优惠券）必传
            'title'        => $title,
            'pic'          => $it['mainPic'],
            'price'        => $price,
            'originalPrice'=> $orig,
            'couponPrice'  => floatval($it['couponPrice']),
            'discount'     => $discount,        // 几折，0 表示无折扣信息
            'shopType'     => $shopType,
            'shopLabel'    => $shopType === 1 ? '天猫' : '淘宝',
            'shopName'     => $it['shopName'],
            'cid'          => intval($it['cid']),
            'catName'      => $this->cats[intval($it['cid'])] ?? '',
            'monthSales'   => $monthSales,
            'worthRate'    => $worthRate,       // 值率（%）
            'itemLink'     => $it['itemLink'],
            'couponLink'   => $it['couponLink'], // 领券/推广链接，转链失败时的兜底
            'item_from'    => $it['item_from'],
            'isChoice'     => intval($it['choice']) === 1,
        );
    }

    /**
     * 分类列表（精选 + 各分类），供首页顶部导航使用
     */
    protected function catList() {
        $list = array(array('id' => 0, 'name' => '精选'));
        foreach ($this->cats as $id => $name) {
            $list[] = array('id' => $id, 'name' => $name);
        }
        return $list;
    }
}
