<?php
/**
 * 本地文本向量化（无 AI 兜底层）
 *
 * 设计目标（与 open.zhicms.cc 的 HashingEmbedding 同哲学，但独立实现、零跨项目依赖）：
 *   - 纯 PHP、无外部 API、无外网，确定性输出（同文本永远同向量）。
 *   - 中文 unigram + bigram + trigram 哈希到固定维度 + L2 归一。
 *   - 作为 AiHub::embed() 在「无 AI Key / AI 不可用」时的自动兜底，
 *     保证语义检索/相似召回类业务永不因缺 Key 而失效。
 *   - 精度虽不及神经网络 embeddings，但足够商品/文章相似召回、本地 RAG 初筛。
 *
 * @package common
 */
namespace app\common;

class LocalEmbedding
{
    /** 向量维度（内容区） */
    const DIM = 256;

    /**
     * 将文本转为向量
     * @param string $text
     * @param array  $meta 预留（品类偏移等，后续可扩展）
     * @return array 长度为 DIM 的浮点数组（L2 归一化）
     */
    public function embed($text, array $meta = array())
    {
        $terms = $this->tokenize($text);
        $vec = array_fill(0, self::DIM, 0.0);

        $tf = array();
        $total = 0;
        foreach ($terms as $t) {
            $tf[$t] = isset($tf[$t]) ? $tf[$t] + 1 : 1;
            $total++;
        }
        if ($total === 0 && empty($meta)) {
            return $vec; // 空文本返回零向量
        }

        foreach ($tf as $t => $f) {
            $idx = $this->hashIndex($t);
            $vec[$idx] += $f / $total; // 词频归一
        }

        return $this->normalize($vec);
    }

    /**
     * 批量向量化
     */
    public function embedBatch(array $texts)
    {
        $out = array();
        foreach ($texts as $k => $t) {
            $out[$k] = $this->embed($t);
        }
        return $out;
    }

    /**
     * 文本分词：中文 unigram+bigram+trigram + 英文/数字按词
     */
    public function tokenize($text)
    {
        $text = trim($text);
        if ($text === '') {
            return array();
        }
        $tokens = array();

        if (preg_match_all('/[a-z0-9]+/i', $text, $m)) {
            foreach ($m[0] as $w) {
                $tokens[] = 'en:' . strtolower($w);
            }
        }

        // 汉字按 UTF-8 逐字切分（不依赖 mbstring，兜底层必须零扩展依赖）
        $chars = self::zhChars($text);
        $len = count($chars);
        if ($len === 1) {
            $tokens[] = 'zh:' . $chars[0];
        } else {
            for ($i = 0; $i < $len; $i++) {
                $tokens[] = 'zh:' . $chars[$i];
                if ($i < $len - 1) {
                    $tokens[] = 'zh:' . $chars[$i] . $chars[$i + 1];
                }
                if ($i < $len - 2) {
                    $tokens[] = 'zh:' . $chars[$i] . $chars[$i + 1] . $chars[$i + 2];
                }
            }
        }
        return $tokens;
    }

    /**
     * 提取文本中的汉字数组（纯 preg，不依赖 mbstring）
     * @return array 每个元素为一个 UTF-8 汉字
     */
    public static function zhChars($text)
    {
        if (preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $text, $m)) {
            return $m[0];
        }
        return array();
    }

    /**
     * 稳定哈希到 [0, DIM)
     */
    protected function hashIndex($term)
    {
        $h = crc32($term);
        if ($h < 0) {
            $h += 4294967296;
        }
        return $h % self::DIM;
    }

    /**
     * 余弦相似度（供调用方本地比对）
     */
    public static function cosine(array $a, array $b)
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }
        if ($na <= 0 || $nb <= 0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * L2 归一化
     */
    protected function normalize(array $vec)
    {
        $sum = 0.0;
        foreach ($vec as $v) {
            $sum += $v * $v;
        }
        if ($sum <= 0) {
            return $vec;
        }
        $norm = sqrt($sum);
        foreach ($vec as &$v) {
            $v = $v / $norm;
        }
        return $vec;
    }
}
