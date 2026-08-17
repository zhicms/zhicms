<?php

namespace app\index\controller;

class RedirectController extends \app\base\controller\BaseController
{
    private $platformConfig = [
        'tb'     => ['name' => '淘宝',   'api_platform' => 'taobao'],
        'taobao' => ['name' => '淘宝',   'api_platform' => 'taobao'],
        'dtk'    => ['name' => '淘宝',   'api_platform' => 'taobao'],
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

        // 兜底：极端环境下若 $args 未正确合并 $_GET（如某些 SAPI 下 $_GET 被重置），
        // 直接从原始 QUERY_STRING 再取一次，确保淘宝带 "-" 的 id 百分百可取。
        if (($platform === '' || $id === '') && isset($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $qs);
            if ($platform === '' && isset($qs['platform'])) {
                $platform = strtolower(trim($qs['platform']));
            }
            if ($id === '' && isset($qs['id'])) {
                $id = trim($qs['id']);
            }
        }

        $allow = array('tb', 'jd', 'pdd', 'vip');

        // 规范兼容：taobao / dtk（大淘客淘宝标记）-> tb，其余未知值标记为空交由下方纠正
        if ($platform === 'taobao' || $platform === 'dtk') $platform = 'tb';

        // 以数据库真实记录为准做「纠正」，但必须用 goodsId 列匹配
        // （模板传入的 id 是各平台商品 id，如淘宝 num_iid，绝非 yun_items 自增主键 id），
        // 商品平台标识存在 yun_items.item_from（taobao/jd/pdd/vip），而非 platform 列。
        // 只要 goodsId 能在商品库匹配到，就以库内真实平台为准（比前端传入更可靠，
        // 避免文章/首页等场景下把京东/拼多多/唯品会商品误标成 tb 导致转链失败）。
        $dbPlatform = '';
        $dbGoodsId  = '';
        $dbGoodsSign = '';
        if (!empty($id)) {
            try {
                $item = obj('api/ApiData')->dataSelect('yun_items', array('goodsId' => $id));
                if (!empty($item) && !empty($item['item_from'])) {
                    $dbPlatform = strtolower($item['item_from']);
                    // 大淘客淘宝商品 item_from 存的是 'dtk' 或 'taobao'，统一规范为 'tb'
                    if ($dbPlatform === 'taobao' || $dbPlatform === 'dtk') $dbPlatform = 'tb';
                    $dbGoodsId  = $item['goodsId'];
                    // 大淘客商品转链优先用 goodsSign（与采集入库一致），DTK 用 goodsSign 可正确生成券链接
                    $dbGoodsSign = $item['goodsSign'] ?? '';
                    if (!in_array($dbPlatform, $allow)) $dbPlatform = '';
                }
            } catch (\Exception $e) {
            }
        }

        // 1) 数据库能匹配到该商品 -> 以库内真实平台为准（覆盖前端可能标错的 platform）
        // 2) 数据库无匹配但有合法传入 platform -> 用传入值（如论坛外部商品）
        // 3) 都无 -> 报错
        if (!empty($dbPlatform)) {
            $platform = $dbPlatform;
        } elseif (!in_array($platform, $allow)) {
            header('HTTP/1.1 400 Bad Request');
            echo '参数错误：无法识别 platform';
            exit;
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
        $redirectUrl = tcache($cacheKey, function () use ($platform, $id, $dbGoodsSign) {
            return $this->resolveLink($platform, $id, $dbGoodsSign);
        }, 1800);

        if (empty($redirectUrl)) {
            $redirectUrl = $this->buildFallbackUrl($platform, $id);
        }

        header('HTTP/1.1 302 Moved Temporarily');
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * 统一转链入口：拿到产品 id 就直接走 API 转链，获取到短链就跳转。
     * 四个平台全部走 getPrivilegeLink 统一转链（淘宝=大淘客，京东/拼多多/唯品会=好单库），
     * 不再做「非数字就回退搜索」之类的特殊分支——只要 API 返回短链就跳转，
     * 转链失败再统一回退到对应平台的商品落地页。
     */
    private function resolveLink($platform, $id, $goodsSign = '')
    {
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $result = $tjk->getPrivilegeLink($id, '', $platform, $goodsSign);
            if (isset($result['code']) && $result['code'] == 1) {
                $data = $result['data'] ?? array();
                // 大淘客返回的字段与好单库 RatesUrl 返回的字段不同，统一兼容提取短链
                $url = $data['couponClickUrl']
                    ?? $data['shortUrl']
                    ?? $data['url']
                    ?? $data['couponLink']
                    ?? $data['couponurl']
                    ?? $data['shortLink']
                    ?? $data['clickUrl']
                    ?? $data['itemUrl']
                    ?? '';
                if (!empty($url) && preg_match('#^https?://#i', $url)) {
                    return $url;
                }
            }
            // 转链失败：回退到对应平台商品落地页（不再回退搜索页）
            return $this->buildFallbackUrl($platform, $id);
        } catch (\Exception $e) {
            return $this->buildFallbackUrl($platform, $id);
        }
    }

    private function buildFallbackUrl($platform, $id)
    {
        if (preg_match('#^https?://#i', $id)) {
            return $id;
        }
        $maps = array(
            'tb'  => 'https://detail.tmall.com/item.htm?id=',
            'jd'  => 'https://item.jd.com/',
            'pdd' => 'https://mobile.yangkeduo.com/goods.html?goods_sign=',
            'vip' => 'https://detail.vip.com/detail-',
        );
        return isset($maps[$platform]) ? ($maps[$platform] . $id) : 'https://www.taobao.com/';
    }

    /* ========== 私有方法 ========== */

    private function getRedirectUrl($platform, $goodsId)
    {
        // 从本地商品库取真实 goodsSign 透传给转链（大淘客必须传 goodsSign 才能正确生成券链接）
        $goodsSign = '';
        try {
            $item = obj('api/ApiData')->dataSelect('yun_items', array('goodsId' => $goodsId));
            if (!empty($item) && !empty($item['goodsSign'])) {
                $goodsSign = $item['goodsSign'];
            }
        } catch (\Exception $e) {
        }
        try {
            $tjk = new \ZhiCms\ext\Tjk();
            $res = $tjk->getPrivilegeLink($goodsId, '', $platform, $goodsSign);
            
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
