<?php

namespace app\index\controller;

class RedirectController extends \app\base\controller\BaseController
{
    private $platformConfig = [
        'tb' => ['name' => '淘宝', 'api_platform' => 'taobao'],
        'taobao' => ['name' => '淘宝', 'api_platform' => 'taobao'],
        'jd' => ['name' => '京东', 'api_platform' => 'jd'],
        'pdd' => ['name' => '拼多多', 'api_platform' => 'pdd'],
        'vip' => ['name' => '唯品会', 'api_platform' => 'vip']
    ];

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

        $config = $this->platformConfig[$platform];
        $apiPlatform = $config['api_platform'];

        $redirectUrl = $this->getRedirectUrl($apiPlatform, $id);

        if (!empty($redirectUrl)) {
            header('Location: ' . $redirectUrl);
        } else {
            header('Location: /');
        }
        exit;
    }

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
            'jd' => 'https://www.jd.com',
            'pdd' => 'https://mobile.yangkeduo.com',
            'vip' => 'https://www.vip.com'
        ];
        return $urls[$platform] ?? $urls['taobao'];
    }
}
