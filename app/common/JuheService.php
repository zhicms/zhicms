<?php
namespace app\common;

/**
 * 聚合数据资讯采集服务
 * 封装两个新闻类接口：
 *   - 235 新闻头条   https://v.juhe.cn/toutiao/index
 *   - 850 AI新闻简报 https://apis.juhe.cn/fapigw/aibrief/list
 * 两个接口各自独立的 key，分类体系不同（235 用拼音，850 用英文）。
 * 返回统一结构，便于 FindController 入库。
 */
class JuheService
{
    /** 接口 235 配置 */
    const API_235 = 'https://v.juhe.cn/toutiao/index';
    /** 接口 850 配置 */
    const API_850 = 'https://apis.juhe.cn/fapigw/aibrief/list';

    /**
     * 接口 235 新闻头条分类（type 枚举）
     * @return array code => 中文名
     */
    public static function types235()
    {
        return array(
            'top'     => '头条',
            'guonei'  => '国内',
            'guoji'   => '国际',
            'shehui'  => '社会',
            'tiyu'    => '体育',
            'yule'    => '娱乐',
            'keji'    => '科技',
            'caijing' => '财经',
            'shishang' => '时尚',
            'junshi'  => '军事',
            'auto'    => '汽车',
            'game'    => '游戏',
            'kaoshi'  => '考试',
        );
    }

    /**
     * 接口 850 AI新闻简报分类（type 枚举）
     * @return array code => 中文名
     */
    public static function types850()
    {
        return array(
            'hot'    => '热点',
            'tech'   => '科技',
            'sports' => '体育',
            'money'  => '财经',
            'ent'    => '娱乐',
            'auto'   => '汽车',
            'war'    => '军事',
            'game'   => '游戏',
            'mobile' => '手机',
            'digi'   => '数码',
        );
    }

    /**
     * 发起 GET 请求（curl 优先，file_get_contents 兜底）
     * @return array [ok, data|error]
     */
    private static function get($url, $params)
    {
        $url = $url . '?' . http_build_query($params);
        $raw = '';
        if (extension_loaded('curl')) {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_USERAGENT      => 'ZhiCms/5.0',
            ));
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err) {
                return array('ok' => false, 'error' => 'CURL 错误: ' . $err);
            }
        } elseif (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(array('http' => array('timeout' => 15, 'user_agent' => 'ZhiCms/5.0')));
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                return array('ok' => false, 'error' => 'file_get_contents 读取失败（可能 allow_url_fopen 关闭）');
            }
        } else {
            return array('ok' => false, 'error' => 'curl 与 allow_url_fopen 均不可用，无法请求聚合接口');
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return array('ok' => false, 'error' => '接口返回非 JSON: ' . substr($raw, 0, 200));
        }
        if (isset($json['error_code']) && $json['error_code'] != 0) {
            return array('ok' => false, 'error' => '接口错误 ' . $json['error_code'] . ': ' . ($json['reason'] ?? '未知'));
        }
        if (isset($json['resultcode']) && $json['resultcode'] != 0) {
            return array('ok' => false, 'error' => '接口错误 ' . $json['resultcode'] . ': ' . ($json['reason'] ?? '未知'));
        }
        return array('ok' => true, 'data' => $json);
    }

    /**
     * 调用接口 235 拉取指定分类新闻
     * @param string $key  接口 key
     * @param string $type 分类 code
     * @param int    $pages 翻页数
     * @return array [ok, list|error, reason]
     */
    public static function fetch235($key, $type, $pages = 1)
    {
        $all = array();
        for ($i = 1; $i <= max(1, $pages); $i++) {
            $res = self::get(self::API_235, array(
                'key'  => $key,
                'type' => $type,
                'page' => $i,
            ));
            if (!$res['ok']) {
                return array('ok' => false, 'error' => $res['error']);
            }
            $data = $res['data']['result']['data'] ?? array();
            if (empty($data)) {
                break;
            }
            foreach ($data as $item) {
                $all[] = array(
                    'title'     => trim($item['title'] ?? ''),
                    'content'   => $item['content'] ?? '',
                    'summary'   => $item['summary'] ?? '',
                    'pic'       => $item['thumbnail_pic'] ?? ($item['pic'] ?? ''),
                    'source'    => $item['author_name'] ?? '',
                    'pubDate'   => isset($item['date']) ? date('Y-m-d H:i:s', strtotime($item['date'])) : date('Y-m-d H:i:s'),
                    'url'       => $item['url'] ?? '',
                    'uniquekey' => $item['uniquekey'] ?? md5($item['title'] ?? uniqid()),
                );
            }
            if (count($data) < 20) {
                break;
            }
        }
        return array('ok' => true, 'list' => $all);
    }

    /**
     * 调用接口 850 拉取指定分类新闻
     * @param string $key  接口 key
     * @param string $type 分类 code
     * @param int    $pages 页数
     * @return array [ok, list|error]
     */
    public static function fetch850($key, $type, $pages = 1)
    {
        $all = array();
        for ($i = 1; $i <= max(1, $pages); $i++) {
            $res = self::get(self::API_850, array(
                'key'       => $key,
                'type'      => $type,
                'page'      => $i,
                'page_size' => 20,
            ));
            if (!$res['ok']) {
                return array('ok' => false, 'error' => $res['error']);
            }
            $data = $res['data']['result']['list'] ?? array();
            if (empty($data)) {
                break;
            }
            foreach ($data as $item) {
                $all[] = array(
                    'title'     => trim($item['title'] ?? ''),
                    'content'   => $item['summary'] ?? '',
                    'summary'   => $item['summary'] ?? '',
                    'pic'       => $item['image_url'] ?? '',
                    'source'    => $item['author_name'] ?? '',
                    'pubDate'   => isset($item['publish_date']) ? $item['publish_date'] : date('Y-m-d H:i:s'),
                    'url'       => $item['url'] ?? '',
                    'uniquekey' => $item['id'] ?? md5($item['title'] ?? uniqid()),
                );
            }
            if (count($data) < 20) {
                break;
            }
        }
        return array('ok' => true, 'list' => $all);
    }
}
