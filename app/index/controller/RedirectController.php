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
        if (!in_array($platform, $allow) || empty($id)) {
            header('HTTP/1.1 400 Bad Request');
            echo '参数错误：platform 和 id 不能为空';
            exit;
        }

        // 以数据库真实 platform 为准，纠正模板可能传错/硬编码的平台，
        // 确保京东/拼多多/唯品会商品跳转到对应平台而非误跳淘宝。
        if (is_numeric($id)) {
            try {
                $item = obj('api/ApiData')->dataSelect('yun_items', array('id' => intval($id)));
                if (!empty($item) && !empty($item['platform']) && in_array($item['platform'], $allow)) {
                    $platform = $item['platform'];
                }
            } catch (\Exception $e) {
            }
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
            } else {
                // 京东/拼多多/唯品会：当前 Tjk 体系（get-privilege-link / ratesurl）
                // 仅支持淘宝，误用会转错链接。这些平台暂未接入各自联盟转链，
                // 直接回退到对应平台正确的商品落地页，保证「跳转对应平台正确」。
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
