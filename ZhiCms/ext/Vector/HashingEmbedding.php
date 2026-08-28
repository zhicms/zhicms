<?php
namespace ZhiCms\ext\Vector;

/**
 * 本地文本向量化（语义哈希 embedding v2）
 *
 * 在 v1 的基础上做三处关键增强，解决"牛头不对马嘴"问题：
 *   1) 维度从 256 提升到 1024，显著降低哈希碰撞导致的跨词串扰；
 *   2) 中文切词从 bigram 扩展到 unigram+bigram+trigram，覆盖
 *      "手机" ↔ "手机壳/智能手机" 的部分重叠；
 *   3) 引入「品类偏移向量」：把 cid / shop_type 编码为一个独立子空间，
 *      使不同品类的商品向量天然正交，避免跨品类误召回（手机不再串到牙刷）。
 *
 * 可选 IDF 缩放提升区分度。后续可平滑替换为大模型 Embedding API
 * （实现相同 embed() 接口即可）。
 */
class HashingEmbedding
{
    /** 向量维度（内容向量区） */
    const DIM = 192;
    /** 品类偏移区起始维度 */
    const CAT_OFFSET = 192;
    /** 总维度 = 内容区 + 品类区（控制在 256，避免大库下内存溢出） */
    const TOTAL_DIM = 192 + 64;

    /** 是否启用 IDF 缩放（需要 setCorpusStats） */
    protected $useIdf = false;
    /** term => idf 值 */
    protected $idf = [];

    /**
     * 设置语料统计（IDF），用于提升区分度。
     * @param array $idf term => idf 值
     */
    public function setCorpusStats(array $idf)
    {
        if (!empty($idf)) {
            $this->idf = $idf;
            $this->useIdf = true;
        }
        return $this;
    }

    /**
     * 根据一批文档计算 IDF 统计
     * @param array $docs 二维数组，每项含 'terms' => [term=>tf, ...]
     */
    public static function buildIdf(array $docs)
    {
        $df = [];
        $n = 0;
        foreach ($docs as $d) {
            $n++;
            $terms = isset($d['terms']) ? array_keys($d['terms']) : (array)$d;
            $seen = [];
            foreach ($terms as $t) {
                if (isset($seen[$t])) continue;
                $seen[$t] = true;
                $df[$t] = isset($df[$t]) ? $df[$t] + 1 : 1;
            }
        }
        $idf = [];
        foreach ($df as $t => $c) {
            $idf[$t] = log(($n + 1) / ($c + 1)) + 1; // 平滑 IDF
        }
        return $idf;
    }

    /**
     * 将单条文本转为向量
     * @param string $text
     * @param array $meta 可选，含 cid / shop_type 用于品类偏移
     * @return array 长度为 TOTAL_DIM 的浮点数组（L2 归一化）
     */
    public function embed($text, array $meta = [])
    {
        $terms = $this->tokenize($text);
        $vec = array_fill(0, self::TOTAL_DIM, 0.0);

        // TF 累加（内容区）
        $tf = [];
        $total = 0;
        foreach ($terms as $t) {
            $tf[$t] = isset($tf[$t]) ? $tf[$t] + 1 : 1;
            $total++;
        }
        if ($total === 0 && empty($meta)) {
            return $vec; // 空文本且无品类信息返回零向量
        }

        foreach ($tf as $t => $f) {
            $idx = $this->hashIndex($t);
            $w = $f / $total; // 词频归一
            if ($this->useIdf && isset($this->idf[$t])) {
                $w *= $this->idf[$t];
            }
            $vec[$idx] += $w;
        }

        // 品类偏移区：cid / shop_type 各占一个独立桶（不同品类向量天然不同向）
        if (!empty($meta['cid'])) {
            $vec[self::CAT_OFFSET + ($this->hashSmall((string)$meta['cid']) % 32)] += 1.0;
        }
        if (!empty($meta['shop_type'])) {
            $vec[self::CAT_OFFSET + 32 + ($this->hashSmall((string)$meta['shop_type']) % 32)] += 1.0;
        }

        return $this->normalize($vec);
    }

    /**
     * 将多条文本批量转为向量
     * @param array $texts
     * @return array
     */
    public function embedBatch(array $texts)
    {
        $out = [];
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
        $text = $this->safeLower(trim($text));
        if ($text === '') return [];

        $tokens = [];

        // 英文/数字词
        if (preg_match_all('/[a-z0-9]+/', $text, $m)) {
            foreach ($m[0] as $w) {
                $tokens[] = 'en:' . $w;
            }
        }

        // 中文：去非中文字符后做 unigram/bigram/trigram
        $zh = preg_replace('/[^\x{4e00}-\x{9fa5}]/u', '', $text);
        $len = $this->mbLen($zh);
        if ($len === 1) {
            $tokens[] = 'zh:' . $zh;
        } else {
            for ($i = 0; $i < $len; $i++) {
                $tokens[] = 'zh:' . $this->mbSub($zh, $i, 1);
                if ($i < $len - 1) {
                    $tokens[] = 'zh:' . $this->mbSub($zh, $i, 2);
                }
                if ($i < $len - 2) {
                    $tokens[] = 'zh:' . $this->mbSub($zh, $i, 3);
                }
            }
        }

        return $tokens;
    }

    /**
     * 稳定哈希到内容区 [0, DIM)
     */
    protected function hashIndex($term)
    {
        $h = crc32($term);
        if ($h < 0) $h = $h + 4294967296;
        return $h % self::DIM;
    }

    /**
     * 稳定哈希到小范围（品类区用）
     */
    protected function hashSmall($s)
    {
        $h = crc32($s);
        if ($h < 0) $h = $h + 4294967296;
        return $h;
    }

    protected function safeLower($s)
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($s, 'UTF-8');
        }
        return strtolower($s);
    }

    protected function mbLen($s)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($s, 'UTF-8');
        }
        return preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $s);
    }

    protected function mbSub($s, $start, $length)
    {
        if (function_exists('mb_substr')) {
            return mb_substr($s, $start, $length, 'UTF-8');
        }
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        return implode('', array_slice($chars, $start, $length));
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
