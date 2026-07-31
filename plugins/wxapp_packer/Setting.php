<?php
namespace plugins\wxapp_packer;

use ZhiCms\base\PluginManager;

class Setting
{
    protected $meta = array();

    public function __construct($meta = array())
    {
        $this->meta = $meta;
    }

	/**
	 * 从 update_check.php 获取最新的下载地址
	 */
	public static function fetchUpdateUrls()
	{
		$updateUrl = 'https://www.zhi.red/update_check.php';

		try {
			// 使用 stream_context_create 支持允许的协议
			$opts = array(
				'http' => array(
					'method' => "GET",
					'timeout' => 5,
					'header' => "User-Agent: wxapp_packer\r\n"
			),
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false
			)
		);
			$context = stream_context_create($opts);

			$response = file_get_contents($updateUrl, false, $context);
			if ($response === false) {
				throw new \Exception('Failed to fetch update URLs');
			}

			// 解析JSON响应
			$data = json_decode($response, true);
			if (!is_array($data)) {
				throw new \Exception('Invalid response format');
			}

			// 提取uniapp和mp-weixin的下载地址
			$result = array();
			if (!empty($data['uniapp'])) {
				$result['uniapp_url'] = $data['uniapp'];
			} else {
				throw new \Exception('uniapp URL not found in response');
			}
			if (!empty($data['mp-weixin'])) {
				$result['mp_url'] = $data['mp-weixin'];
			} else {
				throw new \Exception('mp-weixin URL not found in response');
			}

			return $result;
		} catch (\Throwable $e) {
			// 抛出异常让调用方处理
			throw new \Exception('无法获取下载地址：' . $e->getMessage());
		}
	}

    public function view()
    {
        // 读取上次保存的默认值（可选）
        $defaults = PluginManager::getConfig('wxapp_packer');
        $defaults = is_array($defaults) ? $defaults : array();

		// 从 update_check.php 获取最新的下载地址
		$updateUrls = static::fetchUpdateUrls();

	         $form = array_merge(array(
			 'target_url' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://' . ($_SERVER['HTTP_HOST'] ?? ''),
			 'appid'      => '',
			 'app_name'   => '',
			 'build_mode' => 'miniprogram',
			 'uniapp_url' => $updateUrls['uniapp_url'],
			 'mp_url'     => $updateUrls['mp_url'],
	         ), $defaults);

        // 清理过期文件
        try {
            $plugin = PluginManager::instance('wxapp_packer');
            if ($plugin) {
                $plugin->cleanupOld();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        ob_start();
        include __DIR__ . '/view/setting.php';
        return ob_get_clean();
    }

    public function save($data)
    {
		// 获取最新的下载地址，不从用户提交的数据中获取
		$updateUrls = static::fetchUpdateUrls();

	         $config = array(
			 'target_url' => rtrim(trim($data['target_url'] ?? ''), '/'),
			 'appid'      => trim($data['appid'] ?? ''),
			 'app_name'   => trim($data['app_name'] ?? ''),
			 'build_mode' => in_array(($data['build_mode'] ?? 'miniprogram'), array('uniapp', 'miniprogram'), true)
			 	? $data['build_mode']
			 	: 'miniprogram',
			 'uniapp_url' => $updateUrls['uniapp_url'],
			 'mp_url'     => $updateUrls['mp_url'],
	         );

        // 字段合法性：保存默认值时也校验格式（除了允许为空其他格式必须对）
        if ($config['appid'] !== '' && !preg_match('/^wx[0-9a-f]{16}$/i', $config['appid'])) {
            throw new \Exception('微信 AppID 格式不正确（应为 wx + 16位字母数字）');
        }
        if ($config['target_url'] !== '' && strpos($config['target_url'], 'http') !== 0) {
            throw new \Exception('后端网址必须以 http:// 或 https:// 开头');
        }

        $buildAction = isset($data['action']) ? trim($data['action']) : '';

        if ($buildAction === 'build') {
            // 仅小程序源码模式需要校验必填参数（uniapp 源码直接打包下载，无需替换）
            if ($config['build_mode'] === 'miniprogram') {
                if ($config['target_url'] === '') throw new \Exception('请填写后端网址（HTTPS）');
                if ($config['appid'] === '')      throw new \Exception('请填写微信 AppID');
                if ($config['app_name'] === '')   throw new \Exception('请填写小程序名称');
                if (stripos($config['target_url'], 'https://') !== 0) {
                    throw new \Exception('小程序源码模式要求后端网址必须为 HTTPS');
                }
            }

            $plugin = PluginManager::instance('wxapp_packer');
            if (!$plugin) {
                throw new \Exception('插件未正确安装');
            }
            $result = $plugin->build($config['build_mode'], $config);
            $config['_download_url']  = $result['download_url'] ?? '';
            $config['_download_file'] = $result['file_name'] ?? '';
            $config['_download_path'] = $result['zip_path'] ?? ''; // 添加文件路径信息
        }

        return $config;
    }
}
