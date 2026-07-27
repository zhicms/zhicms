<?php
namespace app\api\controller;

/**
 * 站点 / AI 配置接口
 * 供小程序启动时拉取：站点名称、主题色、AI 是否开启、对话端点、默认角色、访问令牌等。
 * 注意：不会下发真实 AI 提供商的 api_key。
 */
class ConfigController extends ApiBaseController {

    public function index() {
        $this->options();

        $site = array();
        if (file_exists(CONFIG_PATH . 'siteconfig.php')) {
            include CONFIG_PATH . 'siteconfig.php';
            $site = $Siteinfo ?? array();
        }

        $ai = $this->loadAiConfig();

        // 社区总开关与配置（供小程序/App 动态控制底部 Tab 显隐）
        $forumOn = '1';
        $forumRow = obj('api/ApiData')->thisQuery(
            "SELECT `value` FROM `{pre}config` WHERE `key` = ?",
            array('forum_on')
        );
        if (!empty($forumRow[0]['value'])) {
            $forumOn = $forumRow[0]['value'];
        }

        $data = array(
            'code'    => 1,
            'message' => 'success',
            'data'    => array(
                'site' => array(
                    'name'        => $site['sitename'] ?? '',
                    'logo'        => $site['logo'] ?? '',
                    'site_url'    => $this->siteUrl(),
                ),
                'forum' => array(
                    'on'        => $forumOn === '1',
                    'api_url'   => $this->siteUrl() . 'index.php?r=api/forum',
                ),
                'ai' => array(
                    'enabled'      => !empty($ai['enabled']),
                    'provider'     => $ai['provider'] ?? '',
                    'model'        => $ai['model'] ?? '',
                    'theme_color'  => $ai['theme_color'] ?? '#6C63FF',
                    'default_role' => $ai['default_role'] ?? 'shopping',
                    'api_url'      => $this->siteUrl() . 'index.php?r=api/ai/chat',
                    'token'        => $ai['token'] ?? '',   // 站点访问令牌（非提供商密钥）
                ),
                'goods_api_url' => $this->siteUrl() . 'index.php?r=api/goods/search',
            ),
        );

        $this->json($data);
    }
}
