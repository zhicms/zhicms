<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

class SpiderController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /** 默认配置 */
    private $defaults = array(
        'enable'        => '0',          // 总开关
        'mode'          => 'blacklist',  // blacklist 屏蔽命中关键词的UA；whitelist 仅放行白名单UA
        'log_all'       => '0',          // 记录所有蜘蛛访问到 visit.log
        'blacklist'     => "AhrefsBot\nSemrushBot\nMJ12bot\nDotBot\nPetalBot\nYandexBot\nBLEXBot\nMegaIndex\nBytespider\nDataprovider\nMasscan\nzgrab",
        'whitelist'     => "Baiduspider\nGooglebot\nbingbot\n360Spider\nSogou web spider\nYisouSpider\nBytespider",
        'rate_limit'    => '0',          // 每分钟最大请求数，0=不限制
        'block_message' => '您的访问频率过高或不在允许范围内，已被限制。',
    );

    private function cfg(){
        $cfg = \app\common\ConfigStore::load('spider');
        if (!is_array($cfg) || empty($cfg)) $cfg = array();
        return array_merge($this->defaults, $cfg);
    }

    public function index(){
        $this->checkManageSession();
        $this->pageText = array('蜘蛛限制');
        $this->toolTitle = '蜘蛛限制';

        $cfg = $this->cfg();
        $this->cfg = $cfg;

        // 最近拦截记录
        $logFile = \ROOT_PATH . 'data/spiderlog/blocked.log';
        $this->blockLog = $this->parseLog($logFile, 4);

        // 最近蜘蛛访问记录（开启 log_all 后才有）
        $visitFile = \ROOT_PATH . 'data/spiderlog/visit.log';
        $this->visitLog = $this->parseLog($visitFile, 5);
        $this->display();
    }

    /**
     * 将 TAB 分隔的日志行解析为字段数组，避免在模板中执行 explode()
     * 字段索引：0=时间 1=IP 2=原因/页面 3=UA( 拦截日志: 2=原因 3=UA / 访问日志: 3=状态 4=UA)
     */
    private function parseLog($file, $cols){
        if (!is_file($file)) return array();
        $raw = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $raw = array_slice(array_reverse($raw), 0, 50);
        $out = array();
        foreach ($raw as $line) {
            $p = explode("\t", $line);
            $row = array();
            for ($i = 0; $i < $cols; $i++) {
                $row[] = isset($p[$i]) ? $p[$i] : '';
            }
            $out[] = $row;
        }
        return $out;
    }

    public function save(){
        $this->checkManageSession();
        $data = array(
            'enable'        => $this->arg('enable') ? '1' : '0',
            'mode'          => $this->arg('mode') === 'whitelist' ? 'whitelist' : 'blacklist',
            'log_all'       => $this->arg('log_all') ? '1' : '0',
            'blacklist'     => trim($this->arg('blacklist')),
            'whitelist'     => trim($this->arg('whitelist')),
            'rate_limit'    => max(0, (int)$this->arg('rate_limit')),
            'block_message' => trim($this->arg('block_message')),
        );
        \app\common\ConfigStore::save('spider', $data);
        \app\common\ConfigStore::clearCache('spider');
        \ZhiCms\ext\AdminLog::write('spider', '保存了蜘蛛限制配置（' . ($data['enable'] ? '已开启' : '已关闭') . '）');
        exit(json_encode(array('info' => '保存成功', 'status' => 'y')));
    }

    /**
     * 清空拦截日志
     */
    public function clearLog(){
        $this->checkManageSession();
        $logFile = \ROOT_PATH . 'data/spiderlog/blocked.log';
        if (is_file($logFile)) @unlink($logFile);
        exit(json_encode(array('info' => '拦截日志已清空', 'status' => 'y')));
    }

    /**
     * 供前端引导调用的拦截判定（返回 true 表示应拦截）
     * 委托给 ZhiCms\ext\SpiderGuard，保证单一逻辑来源
     */
    public static function shouldBlock(){
        if (!class_exists('\\ZhiCms\\ext\\SpiderGuard')) return false;
        return \ZhiCms\ext\SpiderGuard::shouldBlock();
    }
}
