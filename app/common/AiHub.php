<?php
/**
 * AI 中台增强层（本地测试原型）
 *
 * 定位：在现有 app/common/AiService.php（协议适配 + SSL + 历史）之上，
 *      增加「能力抽象 / 统一网关 / 用量统计 / 失败降级 / 超时重试」。
 *      本文件为【增强层】，不改动 AiService 的任何方法，仅做组合与编排。
 *
 * 设计原则（参考行业现状 2025-2026）：
 *  1. 能力路由：capability = chat | embed | rerank | image | tts | function
 *     —— 未来 RAG 只需调用 AiHub::embed()/rerank()，业务侧零改动。
 *  2. 统一客户端：client($capability) 返回「当前可用的最优模型客户端」，
 *     调用方不再关心协议/openai/gemini 差异。
 *  3. 降级：主模型失败 → 按成本/优先级自动切到备用模型（配置化）。
 *  4. 可观测：每次调用记录 {模型, 能力, 耗时, token, 状态} 到 runtime/ai_usage.jsonl。
 *  5. 重试：网络/5xx 瞬时错误自动重试（最多 2 次，指数退避）。
 *
 * 本地测试说明：
 *  - 仅依赖 AiService 已有能力；未配置的模型返回结构化错误，不致命。
 *  - 用量日志写入 runtime/ 目录，可在本地直接 tail 观察。
 *
 * @package common
 */

namespace app\common;

class AiHub
{
    /** 能力类型常量 */
    const CAP_CHAT    = 'chat';
    const CAP_EMBED   = 'embed';
    const CAP_RERANK  = 'rerank';
    const CAP_IMAGE   = 'image';
    const CAP_TTS     = 'tts';

    /** 最大重试次数（瞬时错误） */
    const MAX_RETRY = 2;

    /** 本地兜底嵌入类（无 AI 时使用，零 API 成本） */
    const LOCAL_EMBED_CLASS = 'app\\common\\LocalEmbedding';

    /**
     * 统一对话入口（带降级 + 重试 + 埋点）
     *
     * @param string $prompt        用户消息
     * @param string $systemPrompt  系统提示
     * @param bool   $useHistory    是否带历史
     * @param array  $opts           ['model'=>指定模型key, 'fallback'=>true默认开]
     * @return string AI 回复（错误时返回 isErrorResult 可识别的结构化文本）
     */
    public static function chat($prompt, $systemPrompt = '你是一个有用的助手', $useHistory = false, $opts = array())
    {
        $start = microtime(true);
        $modelKey = isset($opts['model']) ? $opts['model'] : '';
        $fallback = !isset($opts['fallback']) || $opts['fallback'];

        $lastErr    = '';
        $candidates = self::chatCandidateKeys($modelKey);

        // 关闭降级时只用首选模型，不串到其它模型（避免"指定了 A 却被 B 回答"）
        if (!$fallback) {
            $candidates = array_slice($candidates, 0, 1);
        }

        // 剔除不可用（缺 api_key / model）的候选，避免空转
        $usable = array();
        foreach ($candidates as $key) {
            $info = self::modelInfo($key);
            if (!empty($info['api_key']) && !empty($info['model'])) {
                $usable[] = $key;
            }
        }

        // 无任何可用模型（用户未配置 AI Key）：立即返回可识别错误，
        // 不做无意义重试；由业务层（如 AiAssistantController）走本地规则兜底文案。
        if (empty($usable)) {
            self::record(self::CAP_CHAT, 'none', $start, false, '未配置 AI 模型');
            return 'AI 模型未配置，请在后台「AI 设置」中添加模型或稍后再试';
        }

        for ($attempt = 0; $attempt <= self::MAX_RETRY; $attempt++) {
            foreach ($usable as $key) {
                $callStart = microtime(true); // 每次调用单独计时，否则耗时统计会累加失真
                $reply     = null;            // 必须每轮重置，否则可能沿用上一轮的旧值
                try {
                    // 临时把该模型设为当前 chat 模型（通过配置覆写）
                    self::withChatModel($key, function () use ($prompt, $systemPrompt, $useHistory, &$reply) {
                        $reply = AiService::chat($prompt, $systemPrompt, $useHistory);
                    });
                    if (!self::isErrorResult($reply)) {
                        self::record(self::CAP_CHAT, $key, $callStart, true);
                        return $reply;
                    }
                    $lastErr = (string)$reply;
                } catch (\Throwable $e) {
                    $lastErr = '大模型处理异常：' . $e->getMessage();
                }
                // 单个模型失败：记录并继续下一个候选（降级）
                self::record(self::CAP_CHAT, $key, $callStart, false, $lastErr);
            }
            if ($attempt < self::MAX_RETRY) {
                usleep(200000 * ($attempt + 1)); // 200ms / 400ms 退避
            }
        }
        // 全部失败：返回带可识别前缀的错误，供业务层判定并走本地兜底
        return $lastErr ?: '大模型处理异常：AI 服务暂不可用，请稍后重试';
    }

    /**
     * 流式对话入口（透传 AiService::chatStream，仅加埋点）
     */
    public static function chatStream($prompt, $systemPrompt = '你是一个有用的助手', $useHistory = false, $opts = array())
    {
        $start    = microtime(true);
        $modelKey = isset($opts['model']) ? $opts['model'] : '';
        $key      = $modelKey ?: self::currentChatKey();

        $full = null;
        try {
            self::withChatModel($key, function () use ($prompt, $systemPrompt, $useHistory, &$full) {
                $full = AiService::chatStream($prompt, $systemPrompt, $useHistory);
            });
        } catch (\Throwable $e) {
            // 流式已在输出中，无法改写响应；仅如实埋点，避免"失败被记成成功"
            self::record(self::CAP_CHAT, $key ?: 'none', $start, false, '大模型处理异常：' . $e->getMessage());
            return '';
        }

        $ok = !self::isErrorResult($full);
        self::record(self::CAP_CHAT, $key ?: 'none', $start, $ok, $ok ? '' : (string)$full);
        return $full;
    }

    /**
     * 嵌入向量（RAG 基础能力）
     *
     * 支持两类端点（按模型 api_url 自动判断）：
     *   - 智谱 PaaS：https://open.bigmodel.cn/api/paas/v4/embeddings
     *   - OpenAI 兼容：https://xxx/v1/embeddings
     * 统一请求体 {"model":..., "input":[...]}，返回 {"data":[{"embedding":[...]}]}
     *
     * @param string|array $text  单条文本或文本数组
     * @param array  $opts ['model'=>指定模型key, 'dim'=>向量维度(部分平台支持)]
     * @return array ['vectors'=>[[...],[...]]] 或 ['error'=>...]
     */
    public static function embed($text, $opts = array())
    {
        $start = microtime(true);
        $key = isset($opts['model']) ? $opts['model'] : self::firstModelByCap(self::CAP_EMBED);
        $info = $key ? self::modelInfo($key) : array();

        // 无 AI Key / 未配置 Embed 模型 → 自动降级到本地哈希向量（零成本、业务不失效）
        if (empty($info['api_url']) || empty($info['api_key'])) {
            $local = self::localEmbed($text);
            self::record(self::CAP_EMBED, 'local', $start, true, '本地哈希兜底');
            return array('vectors' => $local, 'local' => true);
        }

        $inputs = is_array($text) ? array_values($text) : array($text);
        $inputs = array_map('strval', $inputs);
        if (empty($inputs)) {
            return array('vectors' => array());
        }
        $body = array('model' => $info['model'], 'input' => $inputs);
        if (!empty($opts['dim'])) {
            $body['dimensions'] = (int)$opts['dim']; // 部分平台(如 OpenAI text-embedding-3)支持
        }

        $resp = self::postJson($info['api_url'], $info['api_key'], $body, 30);
        if (isset($resp['__http_error'])) {
            // 远程失败也兜底到本地，保证不返回 error
            $local = self::localEmbed($text);
            self::record(self::CAP_EMBED, $key, $start, false, $resp['__http_error'] . ' → 本地兜底');
            return array('vectors' => $local, 'local' => true);
        }

        $vectors = array();
        if (isset($resp['data']) && is_array($resp['data'])) {
            foreach ($resp['data'] as $pos => $item) {
                if (!is_array($item) || !isset($item['embedding']) || !is_array($item['embedding'])) {
                    continue;
                }
                // 平台可能乱序返回，用 index 字段对齐输入顺序
                $i = isset($item['index']) ? (int)$item['index'] : (int)$pos;
                $vectors[$i] = $item['embedding'];
            }
            ksort($vectors);
            $vectors = array_values($vectors);
        }
        // 数量与输入不一致说明结果不可信（错位会导致向量与文本对不上）
        if (!empty($vectors) && count($vectors) !== count($inputs)) {
            $local = self::localEmbed($text);
            self::record(self::CAP_EMBED, $key, $start, false,
                '返回向量数(' . count($vectors) . ')与输入数(' . count($inputs) . ')不符 → 本地兜底');
            return array('vectors' => $local, 'local' => true);
        }
        if (empty($vectors)) {
            // 解析失败同样兜底
            $local = self::localEmbed($text);
            $msg = isset($resp['error']) ? json_encode($resp['error'], JSON_UNESCAPED_UNICODE) : '返回结构异常';
            self::record(self::CAP_EMBED, $key, $start, false, $msg . ' → 本地兜底');
            return array('vectors' => $local, 'local' => true);
        }
        self::record(self::CAP_EMBED, $key, $start, true);
        return array('vectors' => $vectors);
    }

    /**
     * 本地哈希向量（无 AI 兜底层，与 open.zhicms.cc 的 HashingEmbedding 同思路）
     * 纯 PHP、零 API、确定性：中文 unigram+bigram+trigram 哈希到固定维度 + L2 归一。
     * 适合商品/文章相似召回，作为「无 AI Key 时」的自动兜底，业务永远不失效。
     *
     * @param string|array $text
     * @return array 向量数组（单条时返回 [vec]，与远程结构一致）
     */
    private static function localEmbed($text)
    {
        $cls = self::LOCAL_EMBED_CLASS;
        $emb = new $cls();
        $inputs = is_array($text) ? $text : array($text);
        $out = array();
        foreach ($inputs as $t) {
            $out[] = $emb->embed((string)$t);
        }
        return $out;
    }

    /**
     * 重排（RAG 第二步：在向量召回后做精排）
     *
     * 支持：
     *   - 智谱 PaaS：https://open.bigmodel.cn/api/paas/v4/rerank
     *     请求体 {"model":..., "query":..., "documents":[...]}
     *     返回 {"results":[{"index":int, "relevance_score":float}]}
     *
     * @param string $query   查询词
     * @param array  $docs    候选文档（字符串数组）
     * @param array  $opts    ['model'=>指定模型key, 'top_n'=>截取前N]
     * @return array ['results'=>[{index,score,doc}]] 或 ['error'=>...]
     */
    public static function rerank($query, $docs, $opts = array())
    {
        $start = microtime(true);
        $key = isset($opts['model']) ? $opts['model'] : self::firstModelByCap(self::CAP_RERANK);
        $info = $key ? self::modelInfo($key) : array();

        if (empty($docs) || !is_array($docs)) {
            return array('error' => 'rerank 需要非空文档数组');
        }

        // 统一重建索引：传给远程的是 array_values，返回的 index 也基于它，
        // 若原数组是关联数组（键非 0..n），不重建会导致 doc 取错。
        $docs  = array_values($docs);
        $topN  = isset($opts['top_n']) ? (int)$opts['top_n'] : 0;

        // 无 AI Key / 未配置 Rerank 模型 → 降级到本地 BM25 相关性重排
        if (empty($info['api_url']) || empty($info['api_key'])) {
            $out = self::localRerank($query, $docs);
            if ($topN > 0) {
                $out = array_slice($out, 0, $topN);
            }
            self::record(self::CAP_RERANK, 'local', $start, true, '本地重排兜底');
            return array('results' => $out, 'local' => true);
        }

        $body = array(
            'model'      => $info['model'],
            'query'      => $query,
            'documents'  => $docs,
        );
        if ($topN > 0) {
            $body['top_n'] = $topN;
        }

        $resp = self::postJson($info['api_url'], $info['api_key'], $body, 30);
        if (isset($resp['__http_error']) || empty($resp['results']) || !is_array($resp['results'])) {
            // 远程失败也兜底到本地，保证不返回 error
            $out = self::localRerank($query, $docs);
            if ($topN > 0) {
                $out = array_slice($out, 0, $topN);
            }
            $reason = isset($resp['__http_error']) ? $resp['__http_error']
                    : (isset($resp['error']) ? json_encode($resp['error'], JSON_UNESCAPED_UNICODE) : '返回结构异常');
            self::record(self::CAP_RERANK, $key, $start, false, $reason . ' → 本地兜底');
            return array('results' => $out, 'local' => true);
        }

        $out = array();
        foreach ($resp['results'] as $r) {
            if (!is_array($r)) {
                continue;
            }
            $idx = isset($r['index']) ? (int)$r['index'] : -1;
            // 优先用平台回传的 document（可能是字符串或 {text:...}），否则按 index 回查
            $doc = '';
            if (isset($r['document'])) {
                $doc = is_array($r['document'])
                    ? (isset($r['document']['text']) ? $r['document']['text'] : '')
                    : $r['document'];
            }
            if ($doc === '' && $idx >= 0 && isset($docs[$idx])) {
                $doc = $docs[$idx];
            }
            $out[] = array(
                'index' => $idx,
                'score' => isset($r['relevance_score']) ? (float)$r['relevance_score'] : 0.0,
                'doc'   => $doc,
            );
        }
        if (empty($out)) {
            // 结构解析不出任何条目，同样兜底
            $out = self::localRerank($query, $docs);
            if ($topN > 0) {
                $out = array_slice($out, 0, $topN);
            }
            self::record(self::CAP_RERANK, $key, $start, false, 'results 解析为空 → 本地兜底');
            return array('results' => $out, 'local' => true);
        }
        self::record(self::CAP_RERANK, $key, $start, true);
        return array('results' => $out);
    }

    /**
     * 本地重排（无 AI 兜底层）：基于 BM25 + 词重叠打分，对候选文档 relevance 排序。
     * 与 open.zhicms.cc 的 Bm25Index 同思路；作为「无 AI Key 时」的自动兜底。
     *
     * @return array [{index,score,doc}] 按 score 降序
     */
    private static function localRerank($query, $docs)
    {
        $qTerms = self::localTokenize($query);
        $qSet = array_fill_keys($qTerms, true);
        $results = array();
        foreach ($docs as $i => $doc) {
            $dTerms = self::localTokenize(is_array($doc) ? implode(' ', $doc) : (string)$doc);
            $docFreq = array();
            foreach ($dTerms as $t) {
                $docFreq[$t] = isset($docFreq[$t]) ? $docFreq[$t] + 1 : 1;
            }
            // BM25（简化，无 IDF 语料时退化为词频重叠 + 覆盖率）
            $hit = 0;
            $tfSum = 0;
            foreach ($docFreq as $t => $tf) {
                if (isset($qSet[$t])) {
                    $hit++;
                    $tfSum += $tf;
                }
            }
            $coverage = count($qSet) > 0 ? ($hit / count($qSet)) : 0;
            $score = round($coverage * 0.6 + min(1.0, $tfSum / 10) * 0.4, 4);
            $results[] = array(
                'index' => $i,
                'score' => $score,
                'doc'   => is_array($doc) ? $doc : $doc,
            );
        }
        usort($results, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        return $results;
    }

    /**
     * 本地中文分词（unigram+bigram+trigram），供 embed/rerank 兜底共用。
     */
    private static function localTokenize($text)
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
        // 汉字切分复用 LocalEmbedding::zhChars（纯 preg，不依赖 mbstring 扩展）
        $cls = self::LOCAL_EMBED_CLASS;
        $chars = $cls::zhChars($text);
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
     * 通用 JSON POST（复用 AiService 的 SSL 选项，保证与 chat 同等安全策略）
     */
    private static function postJson($url, $apiKey, $body, $timeout = 30)
    {
        if (!extension_loaded('curl')) {
            return array('__http_error' => 'curl 扩展未安装');
        }
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return array('__http_error' => '请求体JSON编码失败：' . json_last_error_msg());
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            // 必须限制连接阶段超时，否则 DNS/握手卡住会远超 TIMEOUT 拖垮页面
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ) + AiService::sslOptions());

        $raw      = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return array('__http_error' => 'CURL错误：' . $err);
        }
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if ($httpCode >= 400 || !is_array($decoded)) {
            return array('__http_error' => 'HTTP错误 ' . $httpCode . '：' . self::cut($raw, 200));
        }
        return $decoded;
    }

    // ===================== 内部编排 =====================

    /**
     * 返回 chat 候选模型 key 列表（主模型优先，其余按配置顺序）
     */
    private static function chatCandidateKeys($prefer = '')
    {
        $models = AiService::models();

        // 只挑对话类模型：type=chat 或 未标注 type（老配置默认按 chat 处理）。
        // 关键：必须排除 image/embed/rerank/tts，否则降级时会串到嵌入模型上。
        $chatKeys = array();
        foreach ($models as $k => $m) {
            $type = isset($m['type']) ? strtolower(trim($m['type'])) : '';
            if ($type === self::CAP_CHAT || $type === '') {
                $chatKeys[] = $k;
            }
        }

        // 当前后台选中的 chat 模型天然作为首选
        $current = AiService::getCurrentChatKey();
        $head    = array();
        if ($prefer !== '' && isset($models[$prefer])) {
            $head[] = $prefer;
        }
        if ($current !== '' && isset($models[$current]) && !in_array($current, $head, true)) {
            $head[] = $current;
        }
        if (empty($head)) {
            return $chatKeys;
        }
        // 首选置顶，其余对话模型作为降级候选（去重并重建索引）
        return array_values(array_unique(array_merge($head, array_diff($chatKeys, $head))));
    }

    /**
     * 候选中是否存在「真正可用」的 chat 模型（有 api_key 且有 model 名）
     */
    private static function hasUsableChatModel($candidates)
    {
        foreach ($candidates as $key) {
            $info = self::modelInfo($key);
            if (!empty($info['api_key']) && !empty($info['model'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 是否具备可用的 AI 对话能力（供业务层提前判断，决定走 AI 还是本地规则）
     */
    public static function available($cap = self::CAP_CHAT)
    {
        if ($cap === self::CAP_CHAT) {
            return self::hasUsableChatModel(self::chatCandidateKeys(''));
        }
        $key = self::firstModelByCap($cap);
        $info = $key ? self::modelInfo($key) : array();
        return !empty($info['api_key']) && !empty($info['api_url']);
    }

    private static function firstModelByCap($cap)
    {
        $models = AiService::models();
        foreach ($models as $k => $m) {
            if (($m['type'] ?? '') === $cap) {
                return $k;
            }
        }
        return '';
    }

    /**
     * 当前 chat 模型 key。
     * 注意：不能用 getChatModelInfo()（它返回的是模型数组，不含 key），
     * 必须用 getCurrentChatKey() 读取 ai_chat 指向。
     */
    private static function currentChatKey()
    {
        return AiService::getCurrentChatKey();
    }

    /**
     * 错误结果判定（与 AiService::isErrorResult 同构，避免跨类调用 private 方法）
     * 任何以这些前缀开头的回复都视为错误，不写入历史/不展示给用户。
     */
    public static function isErrorResult($result)
    {
        if (empty($result)) {
            return true;
        }
        foreach (array('AI 模型未配置', '大模型处理异常', '大模型API错误', 'HTTP错误', 'CURL错误') as $prefix) {
            if (strpos($result, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 安全截断字符串（不依赖 mbstring；无 mbstring 时按 UTF-8 边界截断，避免半个汉字）
     */
    private static function cut($s, $len)
    {
        $s = (string)$s;
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $len, 'UTF-8');
        }
        if (strlen($s) <= $len) {
            return $s;
        }
        $s = substr($s, 0, $len);
        // 回退到合法 UTF-8 边界
        while ($s !== '' && (ord($s[strlen($s) - 1]) & 0xC0) === 0x80) {
            $s = substr($s, 0, -1);
        }
        if ($s !== '' && (ord($s[strlen($s) - 1]) & 0x80) !== 0) {
            $s = substr($s, 0, -1);
        }
        return $s;
    }

    private static function modelInfo($key)
    {
        $models = AiService::models();
        return isset($models[$key]) ? $models[$key] : array();
    }

    /**
     * 临时把指定模型设为 AiService 的当前 chat 模型，执行回调后还原。
     * AiService 内部用 getChatModelInfo() 读取 ai_chat 指向的模型，
     * 这里通过覆写配置数组（静态单例）实现临时切换，避免改 AiService。
     */
    private static function withChatModel($key, $cb)
    {
        // key 为空或不存在时不做切换，直接执行（避免把 ai_chat 覆写成空值导致必然失败）
        $models = AiService::models();
        if ($key === '' || $key === null || !isset($models[$key])) {
            $cb();
            return;
        }

        // 关键：先调用 loadConfig() 触发懒加载，确保静态 $config 已是完整数组。
        // 否则 Reflection 读到的可能是 null，回写时会丢掉 ai_models 造成整请求 AI 失效。
        AiService::loadConfig();

        $ref  = new \ReflectionClass('app\\common\\AiService');
        $prop = $ref->getProperty('config');
        if (\PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        $cfg = $prop->getValue();
        if (!is_array($cfg)) {
            // 兜底：读取异常时不冒险改写配置
            $cb();
            return;
        }
        $hadKey = array_key_exists('ai_chat', $cfg);
        $old    = $hadKey ? $cfg['ai_chat'] : null;

        $cfg['ai_chat'] = $key;
        $prop->setValue(null, $cfg);
        try {
            $cb();
        } finally {
            // 精确还原：原本没有该键就删掉，而不是塞一个 null 进去
            if ($hadKey) {
                $cfg['ai_chat'] = $old;
            } else {
                unset($cfg['ai_chat']);
            }
            $prop->setValue(null, $cfg);
        }
    }

    // ===================== 可观测 =====================

    /**
     * 记录一次调用（本地测试：写入 runtime/ai_usage.jsonl）
     */
    private static function record($cap, $modelKey, $startTs, $ok, $err = '')
    {
        // 埋点绝不能影响主流程：任何异常一律吞掉
        try {
            $row = array(
                'ts'    => date('Y-m-d H:i:s'),
                'cap'   => $cap,
                'model' => $modelKey,
                'ms'    => round((microtime(true) - $startTs) * 1000, 1),
                'ok'    => $ok ? 1 : 0,
                'err'   => $ok ? '' : self::cut($err, 200),
            );
            $file = self::usageFile();
            $dir  = dirname($file);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return;
            }
            // 超过 2MB 自动轮转，避免日志无限增长撑爆磁盘
            if (@filesize($file) > 2097152) {
                @rename($file, $file . '.1');
            }
            @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * 用量日志路径。
     * 不直接依赖 ROOT_PATH（它由 realpath('./') 定义，CLI 下随工作目录变化），
     * 未定义时按本文件位置回推项目根，保证路径稳定。
     */
    private static function usageFile()
    {
        if (defined('ROOT_PATH') && \ROOT_PATH) {
            $root = rtrim(str_replace('\\', '/', \ROOT_PATH), '/') . '/';
        } else {
            $root = rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/') . '/';
        }
        return $root . 'runtime/ai_usage.jsonl';
    }

    /**
     * 读取最近 N 条用量（供后台/调试展示）
     */
    public static function usage($limit = 50)
    {
        $limit = max(1, (int)$limit);
        $file  = self::usageFile();
        if (!is_file($file)) {
            return array();
        }
        // 只从文件尾部读取，避免大日志时把整个文件load进内存
        $lines = self::tailLines($file, $limit);
        $out   = array();
        foreach ($lines as $l) {
            $d = json_decode($l, true);
            if (is_array($d)) {
                $out[] = $d;
            }
        }
        return $out;
    }

    /**
     * 读取文件末尾 N 行（分块反向读取，内存友好）
     */
    private static function tailLines($file, $n)
    {
        $fp = @fopen($file, 'rb');
        if (!$fp) {
            return array();
        }
        $buffer = '';
        $chunk  = 8192;
        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        while ($pos > 0 && substr_count($buffer, "\n") <= $n) {
            $read = (int)min($chunk, $pos);
            $pos -= $read;
            fseek($fp, $pos, SEEK_SET);
            $buffer = fread($fp, $read) . $buffer;
        }
        fclose($fp);
        $lines = preg_split('/\r?\n/', trim($buffer));
        if (!is_array($lines)) {
            return array();
        }
        $lines = array_values(array_filter($lines, function ($v) {
            return trim($v) !== '';
        }));
        return array_slice($lines, -$n);
    }
}
