<?php
/**
 * ZhiCms 本地语义分析服务（纯 PHP，无外部依赖、无外网）
 *
 * 移植自 open.zhicms.cc 的语义分析 SDK（App\Vector\* + App\Service\VectorService），
 * 但**只保留纯算法层**，剔除了与数据库商品向量表、PhalApi 框架、远程大淘客/好单库
 * 检索相关的部分（那些与 AI 助手的「语义理解/意图识别」无关，且本环境无对应依赖）。
 *
 * 能力：
 *  1. 中文分词（unigram+bigram+trigram，与向量端同源）
 *  2. 同义词扩展（Bm25Index::SYNONYMS 词典，如 保温杯↔水杯↔杯）
 *  3. 核心词提取（extractSearchWords）
 *  4. 语义向量相似度（HashingEmbedding + CosineSimilarity），用于判断用户词与品类词的语义接近度
 *  5. BM25 在「品类词库」上排序（bm25Rank），支持"保温杯"召回"水杯"类目
 *
 * 调用方：app/index/controller/AiAssistantController::analyzeIntent 的语义增强分支。
 */
namespace ZhiCms\ext;

class VectorService
{
    /** @var \ZhiCms\ext\Vector\Bm25Index */
    private $bm25;
    /** @var \ZhiCms\ext\Vector\HashingEmbedding */
    private $embed;

    public function __construct()
    {
        $this->bm25 = new \ZhiCms\ext\Vector\Bm25Index();
        $this->embed = new \ZhiCms\ext\Vector\HashingEmbedding();
    }

    /**
     * 中文分词（委托给 Bm25Index，保证与向量/BM25 同源）。
     */
    public function tokenize($text)
    {
        return $this->bm25->tokenize($text);
    }

    /**
     * 同义词扩展：给定一个词，返回它及其所有同义词（双向词典）。
     * 例如 expandSynonyms('保温杯') => ['保温杯','水杯','杯','隔热杯','玻璃杯','吸管杯','焖烧']
     */
    public function expandSynonyms($word)
    {
        $word = trim($word);
        $syn = \ZhiCms\ext\Vector\Bm25Index::SYNONYMS;
        $out = array($word);
        // 正向：word 是 key
        if (isset($syn[$word])) {
            foreach ($syn[$word] as $s) {
                $out[] = $s;
            }
        }
        // 反向：word 是某 key 的 value
        foreach ($syn as $k => $list) {
            if (in_array($word, $list, true)) {
                $out[] = $k;
                foreach ($list as $s) {
                    if ($s !== $word) {
                        $out[] = $s;
                    }
                }
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * 提取用于搜索的核心词 + 长修饰词（与 VectorService::extractSearchWords 等价，纯本地版）。
     * 例："轻薄办公笔记本 5000内" => ['笔记本','轻薄办公笔记本']（用于 SQL 粗筛 + 主召回）
     *
     * @param string $query
     * @return array
     */
    public function extractSearchWords($query)
    {
        $q = trim($query);
        if ($q === '') {
            return array();
        }
        // 先按同义词/品类词做最长匹配，命中则直接作为核心词
        $synMap = \ZhiCms\ext\Vector\Bm25Index::SYNONYMS;
        $keys = array_keys($synMap);
        usort($keys, function ($a, $b) { return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8'); });
        $hitCate = '';
        foreach ($keys as $cw) {
            if (mb_stripos($q, $cw) !== false) {
                $hitCate = $cw;
                break;
            }
        }

        $stopPrefix = array('2000内','1000内','3000内','500内','千元内','预算','元左右','以内','内',
            '轻薄','办公','学生','小学生','年级','同款','女','男','儿童','婴儿','宝宝','夏季','春秋',
            '冬季','夏天','冬天','春秋款','夏款','冬款','新款','网红','爆款','热销','推荐','正品','官方',
            '旗舰','店','店铺','包邮','适用','通用','透气','防晒','保暖','加厚','薄款','宽松','修身');

        $words = array();
        if ($hitCate !== '') {
            $words[] = $hitCate;
            $remain = $q;
            foreach ($stopPrefix as $m) {
                $remain = str_replace($m, '', $remain);
            }
            $pos = mb_stripos($remain, $hitCate);
            $before = $pos > 0 ? mb_substr($remain, 0, $pos, 'UTF-8') : '';
            $longCand = trim($before . $hitCate);
            if (mb_strlen($longCand, 'UTF-8') > mb_strlen($hitCate, 'UTF-8') && mb_strlen($longCand, 'UTF-8') <= 12) {
                $words[] = $longCand;
            }
        } else {
            $text = $q;
            foreach ($stopPrefix as $sp) {
                $text = str_replace($sp, '', $text);
            }
            $segs = preg_split('/[^\x{4e00}-\x{9fa5}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            $best = '';
            foreach ($segs as $s) {
                if (mb_strlen($s, 'UTF-8') > mb_strlen($best, 'UTF-8')) {
                    $best = $s;
                }
            }
            if ($best !== '') {
                $words[] = $best;
                if (mb_strlen($best, 'UTF-8') >= 2) {
                    $words[] = $best;
                }
            }
        }
        // 兜底：若没有任何词，退回原始 query 分词
        if (empty($words)) {
            $segs = preg_split('/[^\x{4e00}-\x{9fa5}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($segs as $s) {
                if (mb_strlen($s, 'UTF-8') >= 2) {
                    $words[] = $s;
                }
            }
        }
        return array_values(array_unique($words));
    }

    /**
     * 语义向量相似度：把 query 与每个候选词做 HashingEmbedding 向量化后算余弦。
     * 用于判断"保温杯"与"水杯"的语义接近度，弥补字符串匹配的漏识别。
     *
     * @param string $query
     * @param array  $vocab  候选词列表，如 ['水杯','手机','电脑',...]
     * @param float  $threshold 低于此分数视为不相关
     * @return array ['best'=>词, 'score'=>float, 'all'=>[词=>分]]
     */
    public function semanticMatch($query, array $vocab, $threshold = 0.35)
    {
        $qVec = $this->embed->embed($query);
        $scores = array();
        $best = '';
        $bestScore = 0.0;
        foreach ($vocab as $word) {
            $wVec = $this->embed->embed($word);
            $sim = \ZhiCms\ext\Vector\CosineSimilarity::score($qVec, $wVec);
            $scores[$word] = $sim;
            if ($sim > $bestScore) {
                $bestScore = $sim;
                $best = $word;
            }
        }
        arsort($scores);
        return array(
            'best'      => $bestScore >= $threshold ? $best : '',
            'score'     => $bestScore,
            'all'       => $scores,
        );
    }

    /**
     * 在品类词库上构建 BM25 索引，返回 query 的排序结果 [词=>分]。
     * 与 semanticMatch 互补：BM25 保证"词要对得上"（精确词频），语义向量保证泛化。
     *
     * @param string $query
     * @param array  $vocab 品类词库
     * @param int    $topK
     * @return array 排序后的 [词=>bm25分]
     */
    public function bm25Rank($query, array $vocab, $topK = 0)
    {
        // 把每个品类词当作一篇"文档"构建索引
        $docs = array();
        foreach ($vocab as $v) {
            // 文档内容 = 词本身 + 它的同义词（扩展召回）
            $docs[$v] = $v . ' ' . implode(' ', $this->expandSynonyms($v));
        }
        $this->bm25->build($docs);
        return $this->bm25->search($query, array(), $topK);
    }

    /**
     * 综合判断：query 是否与给定品类词库相关（语义向量 OR BM25 任一命中即相关）。
     * 优先返回语义最匹配的品类词（用于关键词归一化：用户说"保温杯"，归一为"水杯"以便转链/搜索）。
     *
     * @param string $query
     * @param array  $vocab
     * @return array ['hit'=>bool, 'keyword'=>归一化品类词, 'score'=>float, 'method'=>'semantic'|'bm25'|'']
     */
    public function matchCategory($query, array $vocab)
    {
        // 1) BM25 精确/同义召回（命中即可信）
        $bm25 = $this->bm25Rank($query, $vocab, 1);
        if (!empty($bm25)) {
            $topWord = key($bm25);
            $topScore = reset($bm25);
            if ($topScore > 0) {
                return array('hit' => true, 'keyword' => $topWord, 'score' => $topScore, 'method' => 'bm25');
            }
        }
        // 2) 语义向量相似度（泛化，如"保温杯"≈"水杯"）
        $sem = $this->semanticMatch($query, $vocab, 0.35);
        if ($sem['best'] !== '') {
            return array('hit' => true, 'keyword' => $sem['best'], 'score' => $sem['score'], 'method' => 'semantic');
        }
        return array('hit' => false, 'keyword' => '', 'score' => 0.0, 'method' => '');
    }
}
