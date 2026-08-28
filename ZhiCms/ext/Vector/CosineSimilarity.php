<?php
namespace ZhiCms\ext\Vector;

/**
 * 余弦相似度计算工具
 */
class CosineSimilarity
{
    /**
     * 两向量的余弦相似度（向量需已 L2 归一化时，等价于点积）
     * @param array $a
     * @param array $b
     * @return float 范围 [-1, 1]
     */
    public static function score(array $a, array $b)
    {
        $dim = min(count($a), count($b));
        if ($dim === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $dim; $i++) {
            $dot += ($a[$i] ?? 0) * ($b[$i] ?? 0);
            $na += ($a[$i] ?? 0) * ($a[$i] ?? 0);
            $nb += ($b[$i] ?? 0) * ($b[$i] ?? 0);
        }
        if ($na == 0.0 || $nb == 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * 批量相似度：query 向量与一组候选向量比较
     * @param array $query 查询向量
     * @param array $candidates [id => vector, ...]
     * @param int $topN 返回前 N 个
     * @param float $minScore 最低相似度阈值
     * @return array 有序列表 [[ 'id'=>, 'score'=> ], ...]
     */
    public static function topN(array $query, array $candidates, $topN = 10, $minScore = 0.0)
    {
        $scored = [];
        foreach ($candidates as $id => $vec) {
            $s = self::score($query, $vec);
            if ($s >= $minScore) {
                $scored[] = ['id' => $id, 'score' => $s];
            }
        }
        usort($scored, function ($x, $y) {
            return $y['score'] <=> $x['score'];
        });
        return array_slice($scored, 0, $topN);
    }

    /**
     * 将向量序列化为可存库的 JSON 字符串
     */
    public static function encode(array $vec)
    {
        return json_encode($vec, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 从库中 JSON 还原向量
     */
    public static function decode($json)
    {
        if (is_array($json)) {
            return $json;
        }
        $v = json_decode((string)$json, true);
        return is_array($v) ? $v : [];
    }
}
