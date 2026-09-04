<?php
namespace app\manage\controller;

use \app\base\controller\ManageControllerTrait;
use ZhiCms\ext\Vector\Bm25Index;
use ZhiCms\ext\VectorService;

/**
 * 语义库自学习（知识库）管理后台
 * 路由：index.php?r=manage/lexicon/index
 *
 * 数据流：
 *   用户搜索 / AI 对话 → 静默写入原始信号(lexicon_signals.jsonl)
 *   → 分析(analyze 预览) → 入库(learn 落盘 EcomLexicon.learned.php + .json)
 *   → 自动并入检索/扩展链路(expandQuery / expandSynonyms / extractSearchWords)，索引缓存自动失效重建。
 *
 * 定时自动学习（无需人工）：php cron_dispatch.php lexicon  （见 index 页 crontab 片段）
 */
class LexiconController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /** 总览 + 知识库展示 */
    public function index()
    {
        $this->checkManageSession();
        $this->pageText = array('语义库自学习（知识库）');
        $this->toolTitle = '语义库自学习';

        $stats = Bm25Index::lexiconStats();
        $this->stats = $stats;

        $learned = Bm25Index::getLearnedLexicon();
        $this->learnedSynList = $this->flattenSyn($learned['synonyms'] ?? array());
        $this->learnedLtList  = array_slice($learned['longtail'] ?? array(), 0, 300);
        $this->learnedMeta    = $learned['_meta'] ?? null;
        $this->learnedExists  = is_file(Bm25Index::learnedJsonPath());

        // 学习令牌（供 web 版 cron 调用 lexiconLearn 接口；CLI 方式无需令牌）
        $this->learnToken = Bm25Index::learnToken();

        // 展示用 cron 片段（路径用占位，避免猜错绝对路径）
        $this->cronCli = "*/10 * * * * php /你的站点根目录/cron_dispatch.php lexicon";
        $this->cronWeb = "*/10 * * * * curl -s \"https://{你的域名}/index.php?r=index/aiAssistant/lexiconLearn&token=" . $this->learnToken . "\"";

        $this->display();
    }

    /** 分析预览（不落盘）：返回将学到的词，供"分析后再入库"确认 */
    public function analyze()
    {
        $this->checkManageSession();
        $res = $this->vs()->analyzeLexicon($this->learnOpts());
        exit(json_encode($res, JSON_UNESCAPED_UNICODE));
    }

    /** 入库：把分析结果落盘为知识库（.php + .json），并与已有词库合并累积 */
    public function learn()
    {
        $this->checkManageSession();
        $opts = $this->learnOpts();
        if (!empty($_REQUEST['clearSignals'])) {
            $opts['clearSignals'] = true;
        }
        $res = $this->vs()->learnLexicon($opts);
        \ZhiCms\ext\AdminLog::write('lexicon', '执行了语义库学习（入库）');
        exit(json_encode($res, JSON_UNESCAPED_UNICODE));
    }

    /** 清空原始搜索信号（不删已学词库） */
    public function clearSignals()
    {
        $this->checkManageSession();
        $f = Bm25Index::signalPath();
        if (is_file($f)) {
            @unlink($f);
        }
        exit(json_encode(array('status' => 'y', 'info' => '已清空原始搜索信号')));
    }

    /** 导出已学习词库 JSON（便于二次使用 / 外部系统消费） */
    public function export()
    {
        $this->checkManageSession();
        $data = Bm25Index::getLearnedLexicon();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="ecom_lexicon_' . date('Ymd') . '.json"');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /** 懒加载 VectorService */
    private function vs()
    {
        static $v = null;
        if ($v === null) {
            $v = new VectorService();
        }
        return $v;
    }

    /** 从请求解析分析参数 */
    private function learnOpts()
    {
        $opts = array();
        foreach (array('minHits', 'minCo', 'maxTerms') as $k) {
            if (isset($_REQUEST[$k]) && is_numeric($_REQUEST[$k])) {
                $opts[$k] = (int)$_REQUEST[$k];
            }
        }
        return $opts;
    }

    /** 同义数组( key=>[terms] ) 扁平为 [{query,term}] */
    private function flattenSyn(array $syn)
    {
        $out = array();
        foreach ($syn as $k => $list) {
            foreach ((array)$list as $t) {
                $out[] = array('query' => $k, 'term' => $t);
            }
        }
        return $out;
    }
}
