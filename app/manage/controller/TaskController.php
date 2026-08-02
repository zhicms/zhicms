<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;

class TaskController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /** 系统任务定义（代码注册，不可删） */
    private $systemTasks = array(
        'goods_collect' => array('title' => '商品采集', 'exec_type' => 'php', 'command' => 'job:goods_collect', 'schedule' => 'every 30 minute'),
        'news_collect'  => array('title' => '资讯采集', 'exec_type' => 'php', 'command' => 'job:news_collect',  'schedule' => 'every 60 minute'),
    );

    private function runToken(){
        $cfg = \app\common\ConfigStore::load('task');
        return isset($cfg['run_token']) ? $cfg['run_token'] : '';
    }

    public function index(){
        $this->checkManageSession();
        $this->pageText = array('计划任务');
        $this->toolTitle = '计划任务';

        $this->ensureSystemTasks();
        $tasks = obj('api/ApiData')->thisQuery("SELECT * FROM `yun_cron_task` ORDER BY `type` DESC, `id` ASC");
        $this->tasks = is_array($tasks) ? $tasks : array();
        $this->systemKeys = array_keys($this->systemTasks);

        // 健康检查：检测外部 cron/计划任务是否真的在触发
        $last = \ZhiCms\ext\CronRunner::lastPing();
        $now = time();
        $ok = $last > 0 && ($now - $last) <= 180; // 3 分钟内视为正常
        $this->cronStatus   = $ok ? 'ok' : 'bad';
        $this->cronLastText = $last ? date('Y-m-d H:i:s', $last) : '从未触发';
        $this->cronMsg      = $ok ? '定时触发正常（服务器 cron/计划任务已在调用）'
                                   : '已超过 3 分钟未检测到触发，请检查服务器 crontab / 计划任务程序是否配置 index.php?r=manage/task/run&token=...';

        $this->display();
    }

    /**
     * 确保系统任务已注册（并清理已下线的系统任务）
     */
    private function ensureSystemTasks(){
        try {
            // 清理配置中已移除的系统任务（如订单采集/匹配），保留用户未手动改过的记录
            $validCmds = array();
            foreach ($this->systemTasks as $key => $t) {
                $validCmds[] = "'job:{$key}'";
            }
            $validList = implode(',', $validCmds);
            obj('api/ApiData')->executeQuery("DELETE FROM `yun_cron_task` WHERE `type`='system' AND `command` NOT IN ({$validList})");

            foreach ($this->systemTasks as $key => $t) {
                $exists = obj('api/ApiData')->thisQuery("SELECT `id` FROM `yun_cron_task` WHERE `command` = 'job:{$key}'");
                if (empty($exists)) {
                    $next = \ZhiCms\ext\CronRunner::nextRun($t['schedule']);
                    obj('api/ApiData')->insertData('yun_cron_task', array(
                        'title'      => $t['title'],
                        'type'       => 'system',
                        'exec_type'  => $t['exec_type'],
                        'command'    => $t['command'],
                        'schedule'   => $t['schedule'],
                        'status'     => 1,
                        'next_run'   => $next,
                        'create_time'=> time(),
                    ));
                }
            }
        } catch (\Throwable $e) { /* 表不存在等忽略 */ }
    }

    public function add(){
        $this->checkManageSession();
        $this->pageText = array('新增任务');
        $this->toolTitle = '新增计划任务';
        $this->display();
    }

    public function edit(){
        $this->checkManageSession();
        $id = (int)$this->arg('id', 0);
        $row = obj('api/ApiData')->thisQuery("SELECT * FROM `yun_cron_task` WHERE `id` = " . $id);
        if (empty($row)) { $this->alert('任务不存在', 'index.php?r=manage/task/index'); }
        $this->task = $row[0];
        $this->pageText = array('编辑任务');
        $this->toolTitle = '编辑计划任务';
        $this->display();
    }

    public function save(){
        $this->checkManageSession();
        $id = (int)$this->arg('id', 0);
        $data = array(
            'title'     => trim($this->arg('title', '未命名任务')),
            'exec_type' => $this->arg('exec_type', 'url'),
            'command'   => trim($this->arg('command')),
            'schedule'  => trim($this->arg('schedule', 'every 30 minute')),
            'status'    => $this->arg('status') ? 1 : 0,
        );
        $data['next_run'] = \ZhiCms\ext\CronRunner::nextRun($data['schedule']);
        if ($id) {
            obj('api/ApiData')->executeQuery("UPDATE `yun_cron_task` SET `title`='" . addslashes($data['title'])
                . "', `exec_type`='" . addslashes($data['exec_type']) . "', `command`='" . addslashes($data['command'])
                . "', `schedule`='" . addslashes($data['schedule']) . "', `status`=" . $data['status']
                . ", `next_run`=" . (int)$data['next_run'] . " WHERE `id`=" . $id);
            \ZhiCms\ext\AdminLog::write('task', '编辑了计划任务：' . $data['title']);
        } else {
            $data['type'] = 'custom';
            $data['create_time'] = time();
            obj('api/ApiData')->insertData('yun_cron_task', $data);
            \ZhiCms\ext\AdminLog::write('task', '新增了计划任务：' . $data['title']);
        }
        $this->alert('保存成功', 'index.php?r=manage/task/index');
    }

    public function del(){
        $this->checkManageSession();
        $id = (int)$this->arg('id', 0);
        $row = obj('api/ApiData')->thisQuery("SELECT * FROM `yun_cron_task` WHERE `id` = " . $id);
        if (!empty($row) && $row[0]['type'] === 'system') {
            exit(json_encode(array('info' => '系统任务不可删除', 'status' => 'n')));
        }
        obj('api/ApiData')->executeQuery("DELETE FROM `yun_cron_task` WHERE `id` = " . $id);
        \ZhiCms\ext\AdminLog::write('task', '删除了计划任务 ID：' . $id);
        exit(json_encode(array('info' => '已删除', 'status' => 'y')));
    }

    public function toggle(){
        $this->checkManageSession();
        $id = (int)$this->arg('id', 0);
        $row = obj('api/ApiData')->thisQuery("SELECT * FROM `yun_cron_task` WHERE `id` = " . $id);
        if (empty($row)) exit(json_encode(array('info' => '任务不存在', 'status' => 'n')));
        $new = $row[0]['status'] ? 0 : 1;
        obj('api/ApiData')->executeQuery("UPDATE `yun_cron_task` SET `status`={$new}, `next_run`=" . \ZhiCms\ext\CronRunner::nextRun($row[0]['schedule']) . " WHERE `id`={$id}");
        exit(json_encode(array('info' => $new ? '已启用' : '已停用', 'status' => 'y')));
    }

    /**
     * 立即执行一个任务
     */
    public function runNow(){
        $this->checkManageSession();
        $id = (int)$this->arg('id', 0);
        $row = obj('api/ApiData')->thisQuery("SELECT * FROM `yun_cron_task` WHERE `id` = " . $id);
        if (empty($row)) exit(json_encode(array('info' => '任务不存在', 'status' => 'n')));
        $res = $this->executeTask($row[0]);
        $this->updateResult($id, $res);
        exit(json_encode(array('info' => ($res['ok'] ? '执行成功' : '执行失败') . '：' . mb_substr($res['output'], 0, 120), 'status' => $res['ok'] ? 'y' : 'n')));
    }

    /**
     * 系统/外部 cron 触发入口：index.php?r=manage/task/run&token=XXX
     * 执行所有到点的任务；也可 ?id=N 只执行一个
     */
    public function run(){
        $token = $this->arg('token', '');
        $cfgToken = $this->runToken();
        if ($cfgToken === '' || $token !== $cfgToken) {
            header('HTTP/1.1 403 Forbidden');
            exit('invalid token');
        }
        // 健康检查：记录外部 cron/计划任务的最近触发时间
        \ZhiCms\ext\CronRunner::markPing();
        $now = time();
        $executed = 0;
        if ($id = (int)$this->arg('id', 0)) {
            $rows = obj('api/ApiData')->thisQuery("SELECT * FROM `yun_cron_task` WHERE `id` = " . $id);
        } else {
            $rows = obj('api/ApiData')->thisQuery("SELECT * FROM `yun_cron_task` WHERE `status`=1 AND `next_run`>0 AND `next_run`<={$now}");
        }
        foreach ($rows as $task) {
            $res = $this->executeTask($task);
            $this->updateResult($task['id'], $res);
            $executed++;
        }
        echo json_encode(array('executed' => $executed, 'time' => date('Y-m-d H:i:s', $now)));
    }

    /**
     * 生成/查看运行令牌
     */
    public function token(){
        $this->checkManageSession();
        $cfg = \app\common\ConfigStore::load('task');
        $token = isset($cfg['run_token']) ? $cfg['run_token'] : '';
        if ($token === '' || $this->arg('regen')) {
            $token = md5(uniqid('', true) . microtime());
            $cfg['run_token'] = $token;
            \app\common\ConfigStore::save('task', $cfg);
            \app\common\ConfigStore::clearCache('task');
            \ZhiCms\ext\AdminLog::write('task', '重置了计划任务运行令牌');
        }
        $this->token = $token;
        $this->runUrl = 'index.php?r=manage/task/run&token=' . $token;
        $this->pageText = array('运行令牌');
        $this->toolTitle = '计划任务运行令牌';
        $this->display();
    }

    // ======================= 内部执行 =======================

    private function executeTask($task){
        // 系统任务走内部 job 分发
        if ($task['type'] === 'system' && strpos($task['command'], 'job:') === 0) {
            return $this->runSystemJob(substr($task['command'], 4));
        }
        return \ZhiCms\ext\CronRunner::execute($task);
    }

    /**
     * 执行系统采集任务（绕过后台 session 校验）
     */
    private function runSystemJob($name){
        try {
            $_SESSION['manage_system'] = 'cron';
            switch ($name) {
                case 'goods_collect':
                    $c = new \app\manage\controller\UnionController();
                    ob_start();
                    $c->collectGoods();
                    $out = ob_get_clean();
                    return array('ok' => true, 'output' => '商品采集完成');
                case 'news_collect':
                    $c = new \app\manage\controller\FindController();
                    ob_start();
                    $c->newsCollect();
                    $out = ob_get_clean();
                    return array('ok' => true, 'output' => '资讯采集完成');
                default:
                    return array('ok' => false, 'output' => '未知系统任务：' . $name);
            }
        } catch (\Throwable $e) {
            return array('ok' => false, 'output' => '系统任务异常：' . $e->getMessage());
        }
    }

    private function updateResult($id, $res){
        obj('api/ApiData')->executeQuery("UPDATE `yun_cron_task` SET `last_run`=" . time()
            . ", `last_result`='" . addslashes(mb_substr($res['output'], 0, 480)) . "'"
            . ", `next_run`=(SELECT CASE WHEN `schedule`<>'' THEN " . \ZhiCms\ext\CronRunner::nextRun($this->taskSchedule($id)) . " ELSE 0 END)"
            . " WHERE `id`=" . (int)$id);
    }

    private function taskSchedule($id){
        $row = obj('api/ApiData')->thisQuery("SELECT `schedule` FROM `yun_cron_task` WHERE `id` = " . (int)$id);
        return !empty($row) ? $row[0]['schedule'] : 'every 30 minute';
    }
}
