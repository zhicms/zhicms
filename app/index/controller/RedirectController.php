<?php

namespace app\index\controller;

class RedirectController extends \app\base\controller\BaseController
{
    private $platformConfig = [
        'tb'     => ['name' => '淘宝',   'api_platform' => 'taobao'],
        'taobao' => ['name' => '淘宝',   'api_platform' => 'taobao'],
        'jd'     => ['name' => '京东',   'api_platform' => 'jd'],
        'pdd'    => ['name' => '拼多多', 'api_platform' => 'pdd'],
        'vip'    => ['name' => '唯品会', 'api_platform' => 'vip']
    ];

    /* ========== go() — Tjk 本地 SDK 转链跳转（原 RedirectController） ========== */
    public function go()
    {
        $platform = strtolower($this->arg('platform'));
        $id = $this->arg('id');

        if (empty($platform) || empty($id)) {
            header('Location: /');
            exit;
        }
        if (!isset($this->platformConfig[$platform])) {
            header('Location: /');
            exit;
        }

        $apiPlatform = $this->platformConfig[$platform]['api_platform'];
        $redirectUrl = $this->getRedirectUrl($apiPlatform, $id);

        header('Location: ' . (!empty($redirectUrl) ? $redirectUrl : '/'));
        exit;
    }

    /* ========== jump() — 带缓存 + 降级兜底的转链跳转（原 LinkController） ========== */
    public function jump()
    {
        $platform = strtolower(trim($this->arg('platform', '')));
        $id       = trim($this->arg('id', ''));

        $allow = array('tb', 'jd', 'pdd', 'vip');

        // 规范兼容：taobao -> tb，其余未知值标记为空交由下方纠正
        if ($platform === 'taobao') $platform = 'tb';

        // 以数据库真实记录为准做「纠正」，但必须用 goodsId 列匹配
        // （模板传入的 id 是各平台商品 id，如淘宝 num_iid，绝非 yun_items 自增主键 id），
        // 否则会把正确的淘宝 num_iid 当成主键去查、张冠李戴导致平台错 + id 错。
        // 仅当：传入 platform 非法 或 传入 id 能匹配到数据库记录时，才采纳库内值。
        $dbPlatform = '';
        $dbGoodsId  = '';
        if (!empty($id)) {
            try {
                $item = obj('api/ApiData')->dataSelect('yun_items', array('goodsId' => $id));
                if (!empty($item) && !empty($item['platform'])) {
                    $dbPlatform = strtolower($item['platform']);
                    if ($dbPlatform === 'taobao') $dbPlatform = 'tb';
                    $dbGoodsId  = $item['goodsId'];
                    if (!in_array($dbPlatform, $allow)) $dbPlatform = '';
                }
            } catch (\Exception $e) {
            }
        }

        // 1) 传入 platform 合法 -> 以传入为准（模板已正确标记平台）
        // 2) 传入 platform 非法但有库记录 -> 用库内正确平台
        if (!in_array($platform, $allow)) {
            if (!empty($dbPlatform)) {
                $platform = $dbPlatform;
            } else {
                header('HTTP/1.1 400 Bad Request');
                echo '参数错误：无法识别 platform';
                exit;
            }
        }
        // 若数据库能匹配到该商品，用库内 goodsId 兜底（保证 id 正确）
        if (!empty($dbGoodsId)) {
            $id = $dbGoodsId;
        }

        if (empty($id)) {
            header('HTTP/1.1 400 Bad Request');
            echo '参数错误：id 不能为空';
            exit;
        }

        $cacheKey = 'link_redirect_' . $platform . '_' . md5($id);
        $redirectUrl = tcache($cacheKey, function () use ($platform, $id) {
            return $this->resolveLink($platform, $id);
        }, 1800);

        if (empty($redirectUrl)) {
            $redirectUrl = $this->buildFallbackUrl($platform, $id);
        }

        header('HTTP/1.1 302 Moved Temporarily');
        header('Location: ' . $redirectUrl);
        exit;
    }

    private function resolveLink($platform, $id)
    {
        try {
            $tjk = new \ZhiCms\ext\Tjk();

            if ($platform === 'tb') {
                // 淘宝走大淘客/好单库高佣转链
                $url = $this->resolveTbLink($tjk, $id);
                if (!empty($url)) return $url;
                // 转链失败：若 id 不是正常淘宝 num_iid（纯数字，可能误标平台或旧链接残留），
                // 回退到淘宝搜索而非生成无效 tmall 商品页（如旧 AI 卡片把京东/拼多多 id 标成 tb）。
                if (!ctype_digit((string) $id)) {
                    return 'https://s.taobao.com/search?q=' . urlencode($id);
                }
        } else {
            // 京东/拼多多/唯品会：好单库 RatesUrl 接口支持多平台转链（按 itemid 自动识别），
            // 调用 getPrivilegeLink 走 RatesUrl 生成带推广位的佣金链接。
            // 仅在转链失败时才回退到平台正确商品落地页，保证「跳转对应平台正确」。
            $url = $this->resolveOtherLink($tjk, $platform, $id);
            if (!empty($url)) return $url;
            return $this->buildFallbackUrl($platform, $id);
        }
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    private function resolveTbLink($tjk, $id)
    {
        $result = $tjk->getPrivilegeLink($id, '', 'dtk');
        if (isset($result['code']) && $result['code'] == 1) {
            $url = $result['data']['shortUrl']
                ?? $result['data']['couponClickUrl']
                ?? $result['data']['clickUrl']
                ?? $result['data']['url']
                ?? $result['data']['itemUrl']
                ?? '';
            if (!empty($url)) return $url;
        }
        $dtk = $tjk->getDtk();
        if ($dtk) {
            $raw = $dtk->GetPrivilegeLink($id);
            if (isset($raw['code']) && $raw['code'] == 0) {
                $url = $raw['data']['shortUrl']
                    ?? $raw['data']['couponClickUrl']
                    ?? '';
                if (!empty($url)) return $url;
            }
        }
        return '';
    }

    /**
     * 京东/拼多多/唯品会转链：好单库 RatesUrl 接口（getPrivilegeLink 内部调用）
     * 支持多平台，按 itemid 自动识别平台，返回带推广位的佣金链接（couponurl）。
     */
    private function resolveOtherLink($tjk, $platform, $id)
    {
        $map = array(
            'jd'  => 'jd',
            'pdd' => 'pdd',
            'vip' => 'vip',
        );
        $p = isset($map[$platform]) ? $map[$platform] : $platform;
        // 转链结果按 platform+id 缓存，避免每次点击都打好单库 RatesUrl（rate limit + 慢）
        $cacheKey = 'jump_ratesurl_' . $p . '_' . $id;
        $cached = tcache($cacheKey, function () use ($tjk, $p, $id) {
            $result = $tjk->getPrivilegeLink($id, '', $p);
            if (isset($result['code']) && $result['code'] == 1) {
                $data = $result['data'] ?? array();
                // 好单库 RatesUrl 返回的佣金链接字段
                $url = $data['couponLink']
                    ?? $data['couponurl']
                    ?? $data['shortLink']
                    ?? $data['url']
                    ?? '';
                if (!empty($url) && preg_match('#^https?://#i', $url)) {
                    return $url;
                }
            }
            return '';
        }, 3600);
        return $cached;
    }

    private function buildFallbackUrl($platform, $id)
    {
        if (preg_match('#^https?://#i', $id)) {
            return $id;
        }
        $maps = array(
            'tb'  => 'https://detail.tmall.com/item.htm?id=',
            'jd'  => 'https://item.jd.com/',
            'pdd' => 'https://mobile.yangkeduo.com/goods.html?goods_id=',
            'vip' => 'https://detail.vip.com/detail-',
        );
        return isset($maps[$platform]) ? ($maps[$platform] . $id) : 'https://www.taobao.com/';
    }

    /* ========== 私有方法 ========== */

    private function getRedirectUrl($platform, $goodsId)
    {
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $res = $tjk->getPrivilegeLink($goodsId, '', $platform);
            
            if ($res['code'] == 1 && !empty($res['data'])) {
                $data = $res['data'];
                if (is_array($data)) {
                    if (isset($data['url'])) return $data['url'];
                    if (isset($data['couponLink'])) return $data['couponLink'];
                    if (isset($data['itemLink'])) return $data['itemLink'];
                }
                if (is_string($data)) return $data;
            }
        } catch (\Exception $e) {
        }
        return $this->getDefaultUrl($platform);
    }

    private function getDefaultUrl($platform)
    {
        $urls = [
            'taobao' => 'https://www.taobao.com',
            'jd'     => 'https://www.jd.com',
            'pdd'    => 'https://mobile.yangkeduo.com',
            'vip'    => 'https://www.vip.com'
        ];
        return $urls[$platform] ?? $urls['taobao'];
    }
}
