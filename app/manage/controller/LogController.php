<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

class LogController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    public function index(){
        $this->checkManageSession();
        $this->pageText = array('系统日志');

        // 确保日志表存在（首次访问自动建表，避免“表不存在”错误）
        \ZhiCms\ext\AdminLog::ensureTable();

        $where = array("1");
        $type = $this->arg('type', '');
        if ($type) {
            $where = array("`type` = '" . addslashes($type) . "'");
        }
        $keyword = $this->arg('keyword', '');
        if ($keyword) {
            $safeKey = addslashes($keyword);
            $where = array("`content` LIKE '%{$safeKey}%' OR `operator` LIKE '%{$safeKey}%'");
        }
        $baseUrl = "index.php?r=manage/log/index";
        if ($type)    $baseUrl .= "&type=" . urlencode($type);
        if ($keyword) $baseUrl .= "&keyword=" . urlencode($keyword);

        $page = obj('api/ApiData')->page("30", "yun_admin_log", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->ret  = $page['list'];
        $this->type = $type;
        $this->keyword = $keyword;
        // 类型选项（用于筛选下拉）
        $this->types = array(
            'login'    => '登录', 'logout' => '退出',
            'setting'  => '系统设置', 'cache' => '缓存管理',
            'plugin'   => '插件管理', 'user' => '用户管理',
            'article'  => '发现/文章', 'forum' => '社区', 'items' => '电商宝库',
            'page'     => '单页', 'ad' => '广告',
        );
        $this->display();
    }

    /**
     * 清空日志
     */
    public function clear(){
        $this->checkManageSession();
        try {
            \ZhiCms\ext\AdminLog::ensureTable();
            obj('api/ApiData')->executeQuery("TRUNCATE TABLE `yun_admin_log`");
            \ZhiCms\ext\AdminLog::write('log', '清空了全部操作日志');
            exit(json_encode(array('info' => '日志已清空', 'status' => 'y')));
        } catch (\Throwable $e) {
            exit(json_encode(array('info' => '清空失败：' . $e->getMessage(), 'status' => 'n')));
        }
    }
}
