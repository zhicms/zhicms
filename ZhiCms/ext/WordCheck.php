<?php
namespace ZhiCms\ext;

/**
 * 违规词检测
 * 1) 本地词库：yun_sensitive_word 表 + 后台可配置的附加关键词
 * 2) 第三方免费接口：可配置一个接收 {"text": "..."} 返回 {"hits":["词",...]} 的内容审核接口
 *    用于与第三方文本审核服务合作（如内容安全 API）。未配置则不调用，本地检测照常工作。
 */
class WordCheck {

    /**
     * 检测文本中的违规词
     * @param string $text 待检测文本
     * @param array  $cfg  配置：enable_api / api_url / api_key / extra_words / local_only
     * @return array ['hits'=>[...], 'source'=>['local'=>[...],'api'=>[...]], 'ok'=>bool]
     */
    public static function check($text, $cfg = array()){
        $hits = array();
        $srcLocal = self::localHits($text, $cfg);
        foreach ($srcLocal as $w) { $hits[$w] = true; }

        $srcApi = array();
        if (!empty($cfg['enable_api']) && !empty($cfg['api_url']) && trim($text) !== '') {
            $srcApi = self::apiHits($text, $cfg);
            foreach ($srcApi as $w) { $hits[$w] = true; }
        }

        return array(
            'ok'    => empty($hits),
            'hits'  => array_keys($hits),
            'local' => $srcLocal,
            'api'   => $srcApi,
        );
    }

    /**
     * 本地词库命中
     */
    public static function localHits($text, $cfg = array()){
        if ($text === '' || $text === null) return array();
        $words = self::loadWords($cfg);
        $found = array();
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '') continue;
            if (stripos($text, $w) !== false) $found[] = $w;
        }
        return $found;
    }

    /**
     * 加载全部敏感词（表 + 配置附加）
     */
    private static function loadWords($cfg){
        $words = array();
        try {
            if (class_exists('\\app\\api\\model\\ApiDataModel')) {
                $rows = obj('api/ApiData')->thisQuery("SELECT `word` FROM `yun_sensitive_word`");
                if (!empty($rows)) {
                    foreach ($rows as $r) { $words[] = $r['word']; }
                }
            }
        } catch (\Throwable $e) { /* 表不存在时忽略 */ }

        if (!empty($cfg['extra_words'])) {
            $extra = preg_split('/\r\n|\r|\n/', $cfg['extra_words']);
            foreach ($extra as $e) { $e = trim($e); if ($e) $words[] = $e; }
        }
        return $words;
    }

    /**
     * 调用第三方内容审核接口
     * 约定请求体：{"text":"...","key":"..."}
     * 约定响应（JSON）：{"hits":["词1","词2"]} 或 {"data":{"hits":[...]}}
     */
    private static function apiHits($text, $cfg){
        $url = $cfg['api_url'];
        $body = array('text' => $text);
        if (!empty($cfg['api_key'])) $body['key'] = $cfg['api_key'];
        $header = "Content-Type: application/json\r\n";
        $json = \ZhiCms\ext\Http::doPost($url, json_encode($body), 8, $header);
        if (!$json) return array();
        $data = json_decode($json, true);
        if (!is_array($data)) return array();
        if (isset($data['hits']) && is_array($data['hits'])) return array_map('strval', $data['hits']);
        if (isset($data['data']['hits']) && is_array($data['data']['hits'])) return array_map('strval', $data['data']['hits']);
        return array();
    }

    /**
     * 新增敏感词（返回是否新增成功）
     */
    public static function addWord($word, $level = 1, $category = ''){
        $word = trim($word);
        if ($word === '') return false;
        try {
            obj('api/ApiData')->insertData('yun_sensitive_word', array(
                'word'     => $word,
                'level'    => (int)$level,
                'category' => $category,
                'create_time' => time(),
            ));
            return true;
        } catch (\Throwable $e) {
            // 唯一键冲突（已存在）视为成功
            return (stripos($e->getMessage(), 'Duplicate') !== false);
        }
    }

    public static function delWord($id){
        try {
            obj('api/ApiData')->executeQuery("DELETE FROM `yun_sensitive_word` WHERE `id` = " . (int)$id);
            return true;
        } catch (\Throwable $e) { return false; }
    }

    public static function listWords($limit = 200){
        try {
            $rows = obj('api/ApiData')->thisQuery("SELECT * FROM `yun_sensitive_word` ORDER BY `level` DESC, `id` DESC LIMIT " . (int)$limit);
            return is_array($rows) ? $rows : array();
        } catch (\Throwable $e) { return array(); }
    }

    public static function countWords(){
        try {
            $r = obj('api/ApiData')->thisQuery("SELECT COUNT(*) AS c FROM `yun_sensitive_word`");
            return isset($r[0]['c']) ? (int)$r[0]['c'] : 0;
        } catch (\Throwable $e) { return 0; }
    }
}
