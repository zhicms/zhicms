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
        // 自动清理 7 天前的操作日志（保留近 7 天）
        $this->clearOld();

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
        // 每页条数：支持 10 / 20 / 30 / 50，默认 20（与后台列表统一）
        $allowSize = array(10, 20, 30, 50);
        $pagesize = (int)$this->arg('pagesize', 20);
        if (!in_array($pagesize, $allowSize)) $pagesize = 20;

        $baseUrl = "index.php?r=manage/log/index";
        if ($type)    $baseUrl .= "&type=" . urlencode($type);
        if ($keyword) $baseUrl .= "&keyword=" . urlencode($keyword);
        $baseUrl .= "&pagesize=" . $pagesize;

        $page = obj('api/ApiData')->page((string)$pagesize, "yun_admin_log", $where, "`id` DESC", $baseUrl);
        $this->page = $page;
        $this->ret  = $page['list'];
        $this->type = $type;
        $this->keyword = $keyword;
        $this->pagesize = $pagesize;
        $this->pageSizes = $allowSize;
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
     * 进入页面时自动清理 7 天前的操作日志（保留近 7 天）
     */
    private function clearOld(){
        try {
            \ZhiCms\ext\AdminLog::ensureTable();
            $before = time() - 7 * 86400;
            obj('api/ApiData')->executeQuery("DELETE FROM `yun_admin_log` WHERE `create_time` < {$before}");
        } catch (\Throwable $e) {
            // 自动清理失败不影响页面展示
        }
    }

    /**
     * 清空日志
     */
    public function clear(){
        $this->checkManageSession();
        try {
            \ZhiCms\ext\AdminLog::ensureTable();
            obj('api/ApiData')->executeQuery("TRUNCATE TABLE `" . obj('api/ApiData')->realTable('yun_admin_log') . "`");
            \ZhiCms\ext\AdminLog::write('log', '清空了全部操作日志');
            exit(json_encode(array('info' => '日志已清空', 'status' => 'y')));
        } catch (\Throwable $e) {
            exit(json_encode(array('info' => '清空失败：' . $e->getMessage(), 'status' => 'n')));
        }
    }

    /**
     * 仅清理 7 天前的日志（保留近 7 天）
     */
    public function clearOldApi(){
        $this->checkManageSession();
        try {
            \ZhiCms\ext\AdminLog::ensureTable();
            $before = time() - 7 * 86400;
            obj('api/ApiData')->executeQuery("DELETE FROM `yun_admin_log` WHERE `create_time` < {$before}");
            \ZhiCms\ext\AdminLog::write('log', '清理了 7 天前的操作日志');
            exit(json_encode(array('info' => '已清理 7 天前的操作日志', 'status' => 'y')));
        } catch (\Throwable $e) {
            exit(json_encode(array('info' => '清理失败：' . $e->getMessage(), 'status' => 'n')));
        }
    }
}
