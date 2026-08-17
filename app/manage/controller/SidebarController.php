<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
use \app\common\SidebarService;

/**
 * 网页版右侧侧栏模块化后台管理
 * 路由：manage/sidebar/index  （列表+设置）
 *       manage/sidebar/save    （异步保存配置）
 */
class SidebarController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    public function index()
    {
        $this->checkManageSession();

        if (!\IS_POST) {
            $widgets = SidebarService::loadConfig();
            // 全部可选类型（含被禁用/未启用的，便于后台勾选启用）
            $allTypes = SidebarService::TYPES;

            // 补齐未出现过的类型（默认值）
            $existing = array_column($widgets, 'type');
            foreach ($allTypes as $t => $label) {
                if (!in_array($t, $existing, true)) {
                    $def = self::defaultWidget($t, $label);
                    $widgets[] = $def;
                }
            }
            usort($widgets, function ($a, $b) { return ($a['sort'] ?? 99) <=> ($b['sort'] ?? 99); });

            $this->widgets = $widgets;
            $this->allTypes = $allTypes;
            $this->display('sidebar/index');
        }
    }

    public function save()
    {
        $this->checkManageSession();
        if (!\IS_POST) {
            Output::error('请求方式错误');
        }

        $raw = Input::postStrVar('widgets');
        if (empty($raw)) {
            Output::error('未接收到侧栏配置');
        }
        $list = json_decode($raw, true);
        if (!is_array($list)) {
            Output::error('配置格式错误');
        }

        $clean = array();
        foreach ($list as $w) {
            if (!isset($w['type']) || !isset(SidebarService::TYPES[$w['type']])) {
                continue;
            }
            $clean[] = array(
                'type'     => $w['type'],
                'title'    => isset($w['title']) ? trim(strip_tags($w['title'])) : SidebarService::TYPES[$w['type']],
                'enabled'  => isset($w['enabled']) ? (int) $w['enabled'] : 0,
                'sort'     => isset($w['sort']) ? (int) $w['sort'] : 99,
                'limit'    => isset($w['limit']) ? max(1, min(50, (int) $w['limit'])) : 5,
                'img_pos'  => in_array($w['img_pos'] ?? '', array('left','right','top','bottom')) ? $w['img_pos'] : 'left',
                'show_no'  => isset($w['show_no']) ? (int) $w['show_no'] : 0,
                'style'    => in_array($w['style'] ?? '', array('list','card','grid')) ? $w['style'] : 'list',
            );
        }

        if (empty($clean)) {
            Output::error('没有有效的侧栏模块');
        }

        $ok = SidebarService::saveConfig($clean);
        if ($ok) {
            // 清前台侧栏缓存，立即生效
            $cache = CacheService::instance();
            foreach (array('sidebar_cheaps','sidebar_brands','sidebar_rank','sidebar_articles','sidebar_comments') as $k) {
                $cache->delete($k);
            }
            Output::ok('侧栏配置已保存');
        } else {
            Output::error('保存失败，请重试');
        }
    }

    private static function defaultWidget($type, $label)
    {
        $defaults = array(
            'user'     => array('title'=>'用户中心','enabled'=>1,'sort'=>10,'limit'=>5, 'img_pos'=>'left','show_no'=>0,'style'=>'card'),
            'stats'    => array('title'=>'站内速览','enabled'=>1,'sort'=>20,'limit'=>5, 'img_pos'=>'left','show_no'=>0,'style'=>'card'),
            'search'   => array('title'=>'搜索','enabled'=>1,'sort'=>30,'limit'=>5, 'img_pos'=>'left','show_no'=>0,'style'=>'card'),
            'cheaps'   => array('title'=>'近期好券','enabled'=>1,'sort'=>40,'limit'=>10,'img_pos'=>'left','show_no'=>0,'style'=>'list'),
            'cats'     => array('title'=>'商品分类','enabled'=>1,'sort'=>50,'limit'=>20,'img_pos'=>'left','show_no'=>0,'style'=>'list'),
            'navs'     => array('title'=>'文章分类','enabled'=>1,'sort'=>55,'limit'=>20,'img_pos'=>'left','show_no'=>0,'style'=>'list'),
            'articles' => array('title'=>'热门文章','enabled'=>1,'sort'=>60,'limit'=>10,'img_pos'=>'left','show_no'=>1,'style'=>'list'),
            'brands'   => array('title'=>'品牌推荐','enabled'=>0,'sort'=>70,'limit'=>9, 'img_pos'=>'top', 'show_no'=>0,'style'=>'grid'),
            'rank'     => array('title'=>'热榜商品','enabled'=>0,'sort'=>80,'limit'=>10,'img_pos'=>'left','show_no'=>1,'style'=>'list'),
            'comments' => array('title'=>'最新评论','enabled'=>0,'sort'=>90,'limit'=>8, 'img_pos'=>'top', 'show_no'=>0,'style'=>'list'),
        );
        $d = isset($defaults[$type]) ? $defaults[$type] : array('title'=>$label,'enabled'=>0,'sort'=>99,'limit'=>5,'img_pos'=>'left','show_no'=>0,'style'=>'list');
        $d['type'] = $type;
        return $d;
    }
}
