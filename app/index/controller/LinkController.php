<?php
namespace app\index\controller;

/**
 * 商品转链控制器
 * 接收平台 + 商品ID，调用 Tjk 获取佣金跳转链接并 302 跳转
 * - 淘宝/天猫 → DTK GetPrivilegeLink
 * - 京东/拼多多/唯品会 → HDK RatesUrl
 */
class LinkController extends \ZhiCms\base\Controller
{
    public function jump()
    {
        $platform = strtolower(trim($this->arg('platform', '')));
        $id       = trim($this->arg('id', ''));

        // 允许的平台白名单
        $allow = array('tb', 'jd', 'pdd', 'vip');
        if (!in_array($platform, $allow) || empty($id)) {
            header('HTTP/1.1 400 Bad Request');
            echo '参数错误：platform 和 id 不能为空';
            exit;
        }

        // 缓存键
        $cacheKey = 'link_redirect_' . $platform . '_' . md5($id);

        $redirectUrl = tcache($cacheKey, function () use ($platform, $id) {
            try {
                $tjk = new \ZhiCms\ext\Tjk();

                if ($platform === 'tb') {
                    // 淘宝走大淘客转链
                    $result = $tjk->getPrivilegeLink($id, '', 'dtk');
                    // DTK 转链成功码为 code==0（注意：Dtk 类的正确返回 code==0 表示成功）
                    // 但通过 Tjk 封装后，统一为 code==1
                    if (isset($result['code']) && $result['code'] == 1) {
                        // DTK 返回的字段可能是 shortUrl / couponClickUrl 等
                        $url = $result['data']['shortUrl']
                            ?? $result['data']['couponClickUrl']
                            ?? $result['data']['clickUrl']
                            ?? $result['data']['url']
                            ?? $result['data']['itemUrl']
                            ?? '';
                        if (!empty($url)) return $url;
                    }
                    // 尝试直接调用 DTK 实例
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
                } else {
                    // 京东/拼多多/唯品会走好单库转链
                    $hdk = $tjk->getHdk();
                    if ($hdk) {
                        $raw = $hdk->RatesUrl($id);
                        if (isset($raw['code']) && ($raw['code'] == 1 || $raw['code'] == 200)) {
                            $url = $raw['data']['shortUrl']
                                ?? $raw['data']['couponClickUrl']
                                ?? $raw['data']['clickUrl']
                                ?? $raw['data']['url']
                                ?? $raw['data']['itemUrl']
                                ?? '';
                            if (!empty($url)) return $url;
                        }
                        // HDK 可能直接返回 url 字符串
                        if (isset($raw['url']) && !empty($raw['url'])) {
                            return $raw['url'];
                        }
                    }
                }
                return '';
            } catch (\Exception $e) {
                return '';
            }
        }, 1800); // 缓存 30 分钟

        if (empty($redirectUrl)) {
            // 转链失败，尝试降级兜底
            header('HTTP/1.1 302 Moved Temporarily');
            // 拼一个商品详情页链接兜底
            $fallback = $this->buildFallbackUrl($platform, $id);
            header('Location: ' . $fallback);
            exit;
        }

        header('HTTP/1.1 302 Moved Temporarily');
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * 转链失败时拼一个兜底跳转链接
     */
    private function buildFallbackUrl($platform, $id)
    {
        // 如果 id 已经是完整 URL，直接使用
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
}
