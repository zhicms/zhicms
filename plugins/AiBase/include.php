<?php
/**
 * 优杰AI连接器 - 插件入口与全局 API
 * @package AiBase
 */

// 注册插件
RegisterPlugin('AiBase', 'ActivePlugin_AiBase');

define('AIBASE_VERSION', '1.0.0');

/**
 * 激活插件时挂载钩子
 */
function ActivePlugin_AiBase()
{
    // 底座插件核心服务无需直接在核心挂载前台钩子，提供接口供子插件挂载
    Add_Filter_Plugin('Filter_Plugin_Admin_TopMenu', 'AiBase_AddAdminMenu');
}

/**
 * 默认配置
 */
function AiBase_DefaultConfig()
{
    return array(
        'Version'        => AIBASE_VERSION,
        'ActivePlatform' => 'deepseek',
        'Timeout'        => 60,
        
        // DeepSeek
        'Platform_deepseek_BaseUrl'      => 'https://api.deepseek.com',
        'Platform_deepseek_ApiKey'       => '',
        'Platform_deepseek_DefaultModel' => 'deepseek-chat',
        
        // OpenAI
        'Platform_openai_BaseUrl'      => 'https://api.openai.com/v1',
        'Platform_openai_ApiKey'       => '',
        'Platform_openai_DefaultModel' => 'gpt-4o-mini',
        
        // Claude
        'Platform_claude_BaseUrl'      => 'https://api.anthropic.com/v1',
        'Platform_claude_ApiKey'       => '',
        'Platform_claude_DefaultModel' => 'claude-3-5-sonnet-latest',
        
        // Gemini
        'Platform_gemini_BaseUrl'      => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'Platform_gemini_ApiKey'       => '',
        'Platform_gemini_DefaultModel' => 'gemini-1.5-flash',
        
        // Aliyun
        'Platform_aliyun_BaseUrl'      => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
        'Platform_aliyun_ApiKey'       => '',
        'Platform_aliyun_DefaultModel' => 'qwen-turbo',
        
        // Baidu
        'Platform_baidu_BaseUrl'      => 'https://qianfan.baidubce.com/v2',
        'Platform_baidu_ApiKey'       => '',
        'Platform_baidu_DefaultModel' => 'ernie-speed-128k',
        
        // Tencent
        'Platform_tencent_BaseUrl'      => 'https://api.hunyuan.tencentcloudapi.com/v1',
        'Platform_tencent_ApiKey'       => '',
        'Platform_tencent_DefaultModel' => 'hunyuan-lite',
        
        // Zhipu
        'Platform_zhipu_BaseUrl'      => 'https://open.bigmodel.cn/api/paas/v4',
        'Platform_zhipu_ApiKey'       => '',
        'Platform_zhipu_DefaultModel' => 'glm-4-flash',
        
        // Moonshot
        'Platform_moonshot_BaseUrl'      => 'https://api.moonshot.cn/v1',
        'Platform_moonshot_ApiKey'       => '',
        'Platform_moonshot_DefaultModel' => 'moonshot-v1-8k',
        
        // Ollama
        'Platform_ollama_BaseUrl'      => 'http://127.0.0.1:11434/v1',
        'Platform_ollama_ApiKey'       => 'ollama',
        'Platform_ollama_DefaultModel' => 'llama3',
        
        // Custom
        'Platform_custom_BaseUrl'      => '',
        'Platform_custom_ApiKey'       => '',
        'Platform_custom_DefaultModel' => '',
    );
}

/**
 * 应用配置默认值
 */
function AiBase_ApplyDefaults($cfg)
{
    foreach (AiBase_DefaultConfig() as $key => $value) {
        if (!$cfg->HasKey($key)) {
            $cfg->$key = $value;
        }
    }
}

/**
 * 插件安装钩子 (首次启用运行)
 */
function InstallPlugin_AiBase()
{
    global $zbp;
    $cfg = $zbp->Config('AiBase');
    AiBase_ApplyDefaults($cfg);
    $cfg->Version = AIBASE_VERSION;
    $zbp->SaveConfig('AiBase');
}

/**
 * 插件卸载钩子 (停用并删除时运行)
 */
function UninstallPlugin_AiBase()
{
    global $zbp;
    $zbp->DelConfig('AiBase');
}

// =========================================================================
// 全局通用大模型接口（供子插件无缝依赖调用）
// =========================================================================

/**
 * 获取大模型 API 客户端实例
 * @return AiApiClient
 * @throws Exception
 */
function aibase_get_client()
{
    global $zbp;
    
    // 自动引入必要库文件
    require_once dirname(__FILE__) . '/lib/Config.php';
    require_once dirname(__FILE__) . '/lib/ApiClient.php';
    
    $cfg = $zbp->Config('AiBase');
    
    // 兼容老版本升级迁移
    $activePlatform = $cfg->HasKey('ActivePlatform') ? (string)$cfg->ActivePlatform : '';
    if (empty($activePlatform)) {
        if ($cfg->HasKey('ApiKey') && !empty($cfg->ApiKey)) {
            $oldUrl = (string)$cfg->BaseUrl;
            if (strpos($oldUrl, 'deepseek') !== false) {
                $activePlatform = 'deepseek';
            } elseif (strpos($oldUrl, 'openai') !== false) {
                $activePlatform = 'openai';
            } else {
                $activePlatform = 'custom';
            }
            $cfg->ActivePlatform = $activePlatform;
            
            $apiKeyVar = "Platform_{$activePlatform}_ApiKey";
            $baseUrlVar = "Platform_{$activePlatform}_BaseUrl";
            $modelVar = "Platform_{$activePlatform}_DefaultModel";
            
            $cfg->$apiKeyVar = $cfg->ApiKey;
            $cfg->$baseUrlVar = $cfg->BaseUrl;
            $cfg->$modelVar = $cfg->DefaultModel;
            $zbp->SaveConfig('AiBase');
        } else {
            $activePlatform = 'deepseek';
            $cfg->ActivePlatform = 'deepseek';
            $zbp->SaveConfig('AiBase');
        }
    }
    
    $apiKeyVar = "Platform_{$activePlatform}_ApiKey";
    $baseUrlVar = "Platform_{$activePlatform}_BaseUrl";
    $modelVar = "Platform_{$activePlatform}_DefaultModel";
    
    $apiKey = $cfg->HasKey($apiKeyVar) ? (string)$cfg->$apiKeyVar : '';
    $baseUrl = $cfg->HasKey($baseUrlVar) ? (string)$cfg->$baseUrlVar : '';
    $model = $cfg->HasKey($modelVar) ? (string)$cfg->$modelVar : '';
    
    $decryptedKey = AiConfigHelper::decrypt($apiKey);
    if ($activePlatform !== 'ollama' && empty($decryptedKey)) {
        throw new Exception("当前选中的大模型平台 [{$activePlatform}] API Key 为空，请先在底座后台配置！");
    }
    
    return new AiApiClient(
        $decryptedKey,
        $baseUrl,
        $model,
        $cfg->Timeout
    );
}

/**
 * 快速大模型聊天生成请求（非流式）
 * @param array|string $messages 可以是消息数组，也可以直接是 Prompt 字符串
 * @param string $system 系统提示词（可选）
 * @param array $options 额外参数（如 temperature 等）
 * @return string 生成的内容文本
 * @throws Exception
 */
function aibase_chat($messages, $system = '', $options = array())
{
    $client = aibase_get_client();
    
    $msgArray = array();
    if (!empty($system)) {
        $msgArray[] = array('role' => 'system', 'content' => $system);
    }
    
    if (is_array($messages)) {
        $msgArray = array_merge($msgArray, $messages);
    } else {
        $msgArray[] = array('role' => 'user', 'content' => $messages);
    }
    
    $result = $client->chat($msgArray, $options);
    return $result['choices'][0]['message']['content'];
}

/**
 * 快速大模型聊天流式生成请求（流式）
 * @param array|string $messages 可以是消息数组，也可以直接是 Prompt 字符串
 * @param callable $callback 回调函数：function($token)
 * @param string $system 系统提示词（可选）
 * @param array $options 额外参数
 * @return bool
 * @throws Exception
 */
function aibase_chat_stream($messages, $callback, $system = '', $options = array())
{
    $client = aibase_get_client();
    
    $msgArray = array();
    if (!empty($system)) {
        $msgArray[] = array('role' => 'system', 'content' => $system);
    }
    
    if (is_array($messages)) {
        $msgArray = array_merge($msgArray, $messages);
    } else {
        $msgArray[] = array('role' => 'user', 'content' => $messages);
    }
    
    return $client->chatStream($msgArray, $callback, $options);
}

/**
 * 注册后台顶部管理菜单链接
 */
function AiBase_AddAdminMenu(&$topmenus)
{
    global $zbp;
    $item = MakeTopMenu(
        'root',
        'AI底座连接器',
        $zbp->host . 'zb_users/plugin/AiBase/main.php',
        '',
        'topmenu_aibase',
        'icon-cpu'
    );
    if ($item !== '') {
        // 插入在第一位（后台首页之后）
        array_splice($topmenus, 1, 0, array($item));
    }
}
