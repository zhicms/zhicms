<?php
/**
 * 优杰AI连接器 - 后台配置页
 * @package AiBase
 */
require '../../../zb_system/function/c_system_base.php';
require '../../../zb_system/function/c_system_admin.php';

$zbp->Load();

// 校验管理权限
if (!$zbp->CheckRights('admin')) {
    Redirect($zbp->host . 'zb_system/cmd.php?act=login');
}

// 自动加载配置类及插件列表数据
require_once dirname(__FILE__) . '/lib/Config.php';
require_once dirname(__FILE__) . '/lib/parsed_plugins.php';

// =========================================================================
// 1. AJAX 接口 - 测试连接
// =========================================================================
if (GetVars('action', 'GET') === 'test') {
    header('Content-Type: application/json; charset=utf-8');
    CheckIsRefererValid();
    
    $platform     = trim((string)GetVars('Platform', 'POST'));
    $apiKey       = trim((string)GetVars('ApiKey', 'POST'));
    $baseUrl      = trim((string)GetVars('BaseUrl', 'POST'));
    $defaultModel = trim((string)GetVars('DefaultModel', 'POST'));
    
    // 如果是星号占位符，或者为空但有已保存的 Key，从数据库解密读取
    if (strpos($apiKey, '***') !== false || empty($apiKey)) {
        $cfg = $zbp->Config('AiBase');
        $apiKeyVar = "Platform_{$platform}_ApiKey";
        $apiKey = $cfg->HasKey($apiKeyVar) ? AiConfigHelper::decrypt($cfg->$apiKeyVar) : '';
    }
    
    try {
        if ($platform !== 'ollama' && empty($apiKey)) {
            throw new Exception("API Key 不能为空！");
        }
        if (empty($baseUrl)) {
            throw new Exception("接口地址 (Base URL) 不能为空！");
        }
        
        require_once dirname(__FILE__) . '/lib/ApiClient.php';
        $client = new AiApiClient($apiKey, $baseUrl, $defaultModel, 15);
        
        $startTime = microtime(true);
        $testMsg = array(array('role' => 'user', 'content' => 'say ping'));
        // 发送微量请求
        $response = $client->chat($testMsg, array('max_tokens' => 5));
        $elapsed = round((microtime(true) - $startTime) * 1000);
        
        $reply = isset($response['choices'][0]['message']['content']) ? trim($response['choices'][0]['message']['content']) : '连接成功';
        
        echo json_encode(array(
            'status' => 'success',
            'message' => '连接成功！延时 ' . $elapsed . ' ms。模型返回: "' . $reply . '"',
            'latency' => $elapsed
        ));
    } catch (Exception $e) {
        echo json_encode(array(
            'status' => 'error',
            'message' => '连接失败: ' . $e->getMessage()
        ));
    }
    die();
}

// =========================================================================
// 1.1 AJAX 接口 - 一键自动获取最新的模型列表
// =========================================================================
if (GetVars('action', 'GET') === 'get_models') {
    header('Content-Type: application/json; charset=utf-8');
    CheckIsRefererValid();
    
    require_once dirname(__FILE__) . '/lib/ApiClient.php';
    
    $platform = trim((string)GetVars('Platform', 'POST'));
    $apiKey   = trim((string)GetVars('ApiKey', 'POST'));
    $baseUrl  = trim((string)GetVars('BaseUrl', 'POST'));
    
    if (strpos($apiKey, '***') !== false || empty($apiKey)) {
        $cfg = $zbp->Config('AiBase');
        $apiKeyVar = "Platform_{$platform}_ApiKey";
        $apiKey = $cfg->HasKey($apiKeyVar) ? AiConfigHelper::decrypt($cfg->$apiKeyVar) : '';
    }
    
    try {
        if ($platform !== 'ollama' && empty($apiKey)) {
            throw new Exception("API Key 不能为空！");
        }
        if (empty($baseUrl)) {
            throw new Exception("接口地址 (Base URL) 不能为空！");
        }
        
        $url = rtrim($baseUrl, '/') . '/models';
        
        $ajax = Network::Create();
        if (!$ajax) {
            throw new Exception("服务器不支持 Z-Blog Network 网络连接组件！");
        }
        $ajax->open('GET', $url);
        $ajax->setTimeOuts(15, 15, 15, 15);
        $ajax->enableGzip();
        
        if ($platform !== 'ollama' && !empty($apiKey)) {
            $ajax->setRequestHeader('Authorization', 'Bearer ' . $apiKey);
        }
        
        if ($platform === 'claude' && strpos($baseUrl, 'anthropic.com') !== false) {
            $ajax->setRequestHeader('x-api-key', $apiKey);
            $ajax->setRequestHeader('anthropic-version', '2023-06-01');
        }
        
        $ajax->send();
        $response = $ajax->responseText;
        $httpCode = $ajax->status;
        $errNo = $ajax->errno;
        $errMsg = $ajax->errstr;
        
        if ($errNo) {
            $friendly = AiApiClient::getFriendlyError($errMsg, 0, $errNo);
            throw new Exception($friendly);
        }
        
        if ($httpCode >= 400) {
            $errData = json_decode($response, true);
            $errDetail = isset($errData['error']['message']) ? $errData['error']['message'] : (isset($errData['error']) ? json_encode($errData['error']) : substr($response, 0, 150));
            $friendly = AiApiClient::getFriendlyError(trim($errDetail), $httpCode);
            throw new Exception($friendly);
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            $rawBrief = substr($response, 0, 150);
            $friendly = AiApiClient::getFriendlyError($rawBrief, $httpCode);
            if ($friendly !== $rawBrief) {
                throw new Exception($friendly);
            }
            throw new Exception("解析 JSON 失败: " . $rawBrief);
        }
        
        $models = array();
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $m) {
                if (isset($m['id']) && AiApiClient::isChatModel($m['id'])) {
                    $models[] = $m['id'];
                }
            }
        } elseif (isset($data['models']) && is_array($data['models'])) {
            foreach ($data['models'] as $m) {
                if (isset($m['name']) && AiApiClient::isChatModel($m['name'])) {
                    $models[] = $m['name'];
                }
            }
        }
        
        if (empty($models)) {
            throw new Exception("未在服务商返回数据中找到任何模型标识符。");
        }
        
        sort($models);
        
        echo json_encode(array(
            'status' => 'success',
            'models' => $models
        ));
    } catch (Exception $e) {
        echo json_encode(array(
            'status' => 'error',
            'message' => $e->getMessage()
        ));
    }
    die();
}

// =========================================================================
// 1.2 AJAX / 接口 - 导出下载配置
// =========================================================================
if (GetVars('action', 'GET') === 'export_download') {
    CheckIsRefererValid();
    $cfg = $zbp->Config('AiBase');
    AiBase_ApplyDefaults($cfg);
    $platforms = array('deepseek', 'openai', 'claude', 'gemini', 'aliyun', 'baidu', 'tencent', 'zhipu', 'moonshot', 'ollama', 'custom');
    $exportData = array();
    $exportKeys = array('ActivePlatform', 'Timeout', 'Version');
    foreach ($platforms as $platform) {
        $exportKeys[] = "Platform_{$platform}_BaseUrl";
        $exportKeys[] = "Platform_{$platform}_ApiKey";
        $exportKeys[] = "Platform_{$platform}_DefaultModel";
    }
    foreach ($exportKeys as $k) {
        if (strpos($k, '_ApiKey') !== false) {
            $exportData[$k] = AiConfigHelper::decrypt($cfg->$k);
        } else {
            $exportData[$k] = $cfg->$k;
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="aibase_config_' . date('Ymd_His') . '.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    die();
}

// =========================================================================
// 1.3 接口 - 导入配置
// =========================================================================
if (GetVars('action', 'GET') === 'import') {
    CheckIsRefererValid();
    $importData = null;
    
    $importText = trim((string)GetVars('import_text', 'POST'));
    if (!empty($importText)) {
        $decodedText = base64_decode($importText, true);
        if ($decodedText !== false) {
            $importData = json_decode($decodedText, true);
        }
    } elseif (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $fileContent = file_get_contents($_FILES['import_file']['tmp_name']);
        $importData = json_decode($fileContent, true);
    }
    
    if (is_array($importData)) {
        $cfg = $zbp->Config('AiBase');
        $platforms = array('deepseek', 'openai', 'claude', 'gemini', 'aliyun', 'baidu', 'tencent', 'zhipu', 'moonshot', 'ollama', 'custom');
        $validKeys = array('ActivePlatform', 'Timeout');
        foreach ($platforms as $platform) {
            $validKeys[] = "Platform_{$platform}_BaseUrl";
            $validKeys[] = "Platform_{$platform}_ApiKey";
            $validKeys[] = "Platform_{$platform}_DefaultModel";
        }
        
        $importedCount = 0;
        foreach ($validKeys as $k) {
            if (isset($importData[$k])) {
                if (strpos($k, '_ApiKey') !== false) {
                    $rawKey = $importData[$k];
                    if (strpos($rawKey, 'ssl:') === 0 || strpos($rawKey, 'xor:') === 0) {
                        $decrypted = AiConfigHelper::decrypt($rawKey);
                        if ($decrypted !== $rawKey) {
                            $rawKey = $decrypted;
                        }
                    }
                    $cfg->$k = AiConfigHelper::encrypt($rawKey);
                } else {
                    $cfg->$k = $importData[$k];
                }
                $importedCount++;
            }
        }
        
        if ($importedCount > 0) {
            $zbp->SaveConfig('AiBase');
            $zbp->SetHint('good', '配置导入成功，已恢复 ' . $importedCount . ' 项配置参数！');
        } else {
            $zbp->SetHint('bad', '导入失败：未在文件中找到有效的底座配置数据！');
        }
    } else {
        $zbp->SetHint('bad', '导入失败：配置格式无法识别（请确保上传的是有效的 JSON 备份文件或密文文本）！');
    }
    Redirect('main.php');
}

// =========================================================================
// 2. 配置保存
// =========================================================================
if (count($_POST) > 0 && !GetVars('action', 'GET')) {
    CheckIsRefererValid();
    $cfg = $zbp->Config('AiBase');
    
    $cfg->ActivePlatform = trim((string)GetVars('ActivePlatform', 'POST'));
    $cfg->Timeout        = max(5, min(300, (int)GetVars('Timeout', 'POST', 60)));
    
    $platforms = array('deepseek', 'openai', 'claude', 'gemini', 'aliyun', 'baidu', 'tencent', 'zhipu', 'moonshot', 'ollama', 'custom');
    foreach ($platforms as $platform) {
        $baseUrlVar = "Platform_{$platform}_BaseUrl";
        $apiKeyVar  = "Platform_{$platform}_ApiKey";
        $modelVar   = "Platform_{$platform}_DefaultModel";
        
        $cfg->$baseUrlVar = trim((string)GetVars($baseUrlVar, 'POST'));
        $cfg->$modelVar   = trim((string)GetVars($modelVar, 'POST'));
        
        $postKey = trim((string)GetVars($apiKeyVar, 'POST'));
        // 星号占位符不覆盖
        if (strpos($postKey, '***') === false) {
            $cfg->$apiKeyVar = AiConfigHelper::encrypt($postKey);
        }
        if (empty($postKey)) {
            $cfg->$apiKeyVar = '';
        }
    }
    
    $zbp->SaveConfig('AiBase');
    $zbp->SetHint('good', '基础配置已成功保存');
    Redirect('main.php');
}

// =========================================================================
// 3. 读取当前配置
// =========================================================================
AiBase_ApplyDefaults($zbp->Config('AiBase'));
$cfg = $zbp->Config('AiBase');

$activePlatform = htmlspecialchars($cfg->ActivePlatform);
$timeout        = (int)$cfg->Timeout;

$platforms = array('deepseek', 'openai', 'claude', 'gemini', 'aliyun', 'baidu', 'tencent', 'zhipu', 'moonshot', 'ollama', 'custom');
if (empty($activePlatform) || !in_array($activePlatform, $platforms)) {
    $activePlatform = 'deepseek';
}
$platformData = array();
foreach ($platforms as $platform) {
    $baseUrlVar = "Platform_{$platform}_BaseUrl";
    $apiKeyVar  = "Platform_{$platform}_ApiKey";
    $modelVar   = "Platform_{$platform}_DefaultModel";
    
    $baseUrl      = htmlspecialchars($cfg->$baseUrlVar);
    $defaultModel = htmlspecialchars($cfg->$modelVar);
    $rawKey       = AiConfigHelper::decrypt($cfg->$apiKeyVar);
    
    $displayKey = htmlspecialchars($rawKey);
    
    $platformData[$platform] = array(
        'BaseUrl'      => $baseUrl,
        'DefaultModel' => $defaultModel,
        'DisplayKey'   => $displayKey
    );
}

// 头部渲染
$blogtitle = '优杰AI大模型连接器 - 接口配置中心';
require $zbp->systemdir . 'admin/admin_header.php';
require $zbp->systemdir . 'admin/admin_top.php';

?>

<style>
/* 优杰 AI 后台风格重置 - 扁平无AI感，间距12px，圆角6px，满宽可视宽度 */
#divMain {
    box-sizing: border-box;
}
.yj-wrapper {
    width: 100%;
    box-sizing: border-box;
}

/* Tabs 页签 */
.jichu-admin-shell {
    margin-top: 12px;
}
.jichu-admin-tabs {
    border-bottom: 2px solid #cbd5e1;
    margin-bottom: 12px;
    padding-bottom: 6px;
    display: flex;
    gap: 12px;
    align-items: center;
}
.jichu-admin-tab {
    background: none;
    border: none;
    padding: 8px 12px;
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -8px;
    transition: all 0.2s ease;
    outline: none;
}
.jichu-admin-tab:hover {
    color: #2563eb;
}
.jichu-admin-tab.is-active, .jichu-admin-tab[aria-selected="true"] {
    color: #2563eb;
    border-bottom: 2px solid #2563eb;
}
.jichu-admin-panel {
    display: none;
}
.jichu-admin-panel.is-active {
    display: block !important;
}

/* 扁平白色卡片 */
.yj-card {
    background: #ffffff;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    padding: 12px;
    margin-bottom: 12px;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.yj-card-title {
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    margin: 0 0 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 8px;
}

/* 表单组与输入框 */
.yj-form-group {
    margin-bottom: 12px;
}
.yj-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}
.yj-input, .yj-select {
    width: 100%;
    padding: 8px 12px;
    font-size: 13px;
    color: #1e293b;
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    box-sizing: border-box;
}
.yj-input:focus, .yj-select:focus {
    border-color: #2563eb;
    outline: none;
}
.yj-help-text {
    font-size: 11px;
    color: #64748b;
    margin-top: 4px;
    line-height: 1.4;
}

/* 扁平化按钮 */
.yj-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    outline: none;
    gap: 6px;
}
.yj-btn-primary {
    background: #2563eb;
    color: #ffffff;
}
.yj-btn-primary:hover {
    background: #1d4ed8;
}
.yj-btn-secondary {
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
}
.yj-btn-secondary:hover {
    background: #f1f5f9;
}
.yj-btn-success {
    background: #10b981;
    color: #ffffff;
}
.yj-btn-success:hover {
    background: #059669;
}
.yj-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* 模型快速填充徽章 */
.yj-model-badge {
    font-size: 11px;
    background: #f1f5f9;
    color: #475569;
    padding: 2px 6px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid #cbd5e1;
    transition: all 0.2s ease;
    display: inline-block;
    margin-right: 6px;
    margin-bottom: 6px;
}
.yj-model-badge:hover {
    background: #eff6ff;
    color: #2563eb;
    border-color: #bfdbfe;
}

/* 现代化模型列表包装器 */
.yj-models-container {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    margin-top: 10px;
    max-height: 250px;
    overflow-y: auto;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.02);
}
.yj-models-search-box {
    margin-bottom: 12px;
    position: relative;
}
.yj-models-search-input {
    width: 100%;
    padding: 6px 12px;
    font-size: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    box-sizing: border-box;
    background: #ffffff;
    transition: all 0.2s ease;
}
.yj-models-search-input:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.yj-model-group-title {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    margin: 8px 0 6px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.yj-model-group-title::after {
    content: '';
    flex-grow: 1;
    height: 1px;
    background: #e2e8f0;
}
.yj-model-badge-group {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 12px;
}
.yj-model-badge-modern {
    font-size: 11px;
    background: #ffffff;
    color: #334155;
    padding: 3px 8px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    transition: all 0.15s ease-in-out;
    display: inline-block;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
    user-select: none;
}
.yj-model-badge-modern:hover {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.15);
}
.yj-model-badge-modern.is-recommended {
    border-left: 3px solid #f59e0b;
    background: #fffbeb;
    color: #78350f;
    font-weight: 600;
}
.yj-model-badge-modern.is-recommended:hover {
    background: #f59e0b;
    color: #ffffff;
    border-color: #f59e0b;
}

/* 测试连接结果框 */
.yj-test-result {
    margin-top: 12px;
    padding: 10px;
    border-radius: 6px;
    font-size: 13px;
    display: none;
    line-height: 1.4;
}
.yj-test-success {
    background-color: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}
.yj-test-error {
    background-color: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

/* 导航链接 */
.yj-platform-link {
    font-size: 12px;
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}
.yj-platform-link:hover {
    text-decoration: underline;
}

/* 扁平化健康检测及产品矩阵表格 */
.yj-check-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    border: 1px solid #cbd5e1;
}
.yj-check-table th, .yj-check-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
.yj-check-table th {
    font-weight: 600;
    color: #334155;
    background: #f1f5f9;
    border-bottom: 2px solid #cbd5e1;
}
.yj-check-pass {
    color: #166534;
    font-weight: 600;
}
.yj-check-fail {
    color: #991b1b;
    font-weight: 600;
}

/* 扁平表格状态标签 */
.yj-badge-active {
    font-size: 11px;
    background: #dcfce7;
    color: #15803d;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 600;
    border: 1px solid #bbf7d0;
    display: inline-block;
}
.yj-badge-inactive {
    font-size: 11px;
    background: #fff7ed;
    color: #c2410c;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 600;
    border: 1px solid #fed7aa;
    display: inline-block;
}
.yj-badge-planned {
    font-size: 11px;
    background: #f1f5f9;
    color: #475569;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    display: inline-block;
}

/* 平台设置左右布局 */
.yj-platform-layout {
    display: flex;
    gap: 16px;
    margin-top: 12px;
}
.yj-platform-left {
    width: 200px;
    flex-shrink: 0;
    border-right: 1px solid #cbd5e1;
    padding-right: 16px;
}
.yj-platform-right {
    flex-grow: 1;
    padding-left: 4px;
}
.yj-platform-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.yj-platform-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}
.yj-platform-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.yj-platform-item.is-selected {
    background: #eff6ff;
    border-color: #3b82f6;
}
.yj-platform-name-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
}
.yj-platform-item.is-selected .yj-platform-name-wrapper {
    color: #1d4ed8;
}
.yj-active-radio-label {
    font-size: 11px;
    color: #64748b;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    user-select: none;
}
.yj-platform-item.is-selected .yj-active-radio-label {
    color: #2563eb;
    font-weight: 600;
}
</style>

<div id="divMain">
    <div class="yj-wrapper">
        <form id="aiForm" method="post" action="main.php">
            <input type="hidden" name="csrfToken" value="<?php echo htmlspecialchars($zbp->GetCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>" />

            <div class="jichu-admin-shell" data-jichu-admin-shell data-initial-tab="platform">
                
                <!-- Tab Tabs -->
                <div class="jichu-admin-tabs" role="tablist" aria-label="底座插件配置">
                    <button class="jichu-admin-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="jichu-tab-platform" id="jichu-tab-trigger-platform" data-tab-target="platform">平台设置</button>
                    <button class="jichu-admin-tab" type="button" role="tab" aria-selected="false" aria-controls="jichu-tab-advanced" id="jichu-tab-trigger-advanced" data-tab-target="advanced">高级配置</button>
                    <button class="jichu-admin-tab" type="button" role="tab" aria-selected="false" aria-controls="jichu-tab-matrix" id="jichu-tab-trigger-matrix" data-tab-target="matrix">AI产品矩阵</button>
                    <button class="jichu-admin-tab" type="button" role="tab" aria-selected="false" aria-controls="jichu-tab-backup" id="jichu-tab-trigger-backup" data-tab-target="backup">备份与导入</button>
                    <button class="jichu-admin-tab" type="button" role="tab" aria-selected="false" aria-controls="jichu-tab-guide" id="jichu-tab-trigger-guide" data-tab-target="guide">使用指南 & 关于作者</button>
                    
                    <span style="margin-left: auto; font-size: 12px; color: #64748b; margin-right: 12px;">支持 Ctrl + S 保存</span>
                    <button class="yj-btn yj-btn-primary" type="submit" style="padding: 6px 14px; font-size: 13px; border-radius: 6px;">保存配置</button>
                </div>

                <!-- Tab Body -->
                <div class="jichu-admin-body">
                    
                    <!-- 面板 1: 平台设置 -->
                    <section class="jichu-admin-panel is-active" id="jichu-tab-platform" role="tabpanel" aria-labelledby="jichu-tab-trigger-platform" data-tab-panel="platform">
                        <div class="yj-card">
                            <h2 class="yj-card-title">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                接口与账号设置
                            </h2>

                            <div class="yj-platform-layout">
                                <!-- 左侧：大模型平台列表 -->
                                <div class="yj-platform-left">
                                    <div style="font-size: 11px; color: #64748b; margin-bottom: 10px; line-height: 1.4;">提示：单选钮控制哪个平台生效。点击行可以查看或修改其配置。</div>
                                    <div class="yj-platform-list">
                                        <?php
                                        $platformList = array(
                                            'deepseek' => 'DeepSeek (推荐)',
                                            'openai' => 'OpenAI',
                                            'claude' => 'Claude',
                                            'gemini' => 'Gemini',
                                            'aliyun' => '阿里云百炼',
                                            'baidu' => '百度千帆 v2',
                                            'tencent' => '腾讯混元',
                                            'zhipu' => '智谱 GLM',
                                            'moonshot' => '月之暗面',
                                            'ollama' => 'Ollama (本地)',
                                            'custom' => '自定义代理'
                                        );
                                        foreach ($platformList as $key => $name):
                                            $isSelected = ($activePlatform === $key);
                                        ?>
                                            <div class="yj-platform-item <?php echo $isSelected ? 'is-selected' : ''; ?>" data-platform-id="<?php echo $key; ?>">
                                                <div class="yj-platform-name-wrapper">
                                                    <span><?php echo $name; ?></span>
                                                </div>
                                                <label class="yj-active-radio-label">
                                                    <input type="radio" class="yj-active-radio" name="ActivePlatform" value="<?php echo $key; ?>" <?php echo $isSelected ? 'checked' : ''; ?> style="margin: 0; cursor: pointer;">
                                                    <span>生效</span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- 右侧：对应平台的配置参数 -->
                                <div class="yj-platform-right">
                                    <!-- 平台专属子配置 -->
                                    <div class="yj-platforms-container">
                                
                                <!-- 1. DeepSeek -->
                                <div id="platform-section-deepseek" class="platform-settings-section" style="<?php echo $activePlatform === 'deepseek' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">DeepSeek 接口设置</h3>
                                        <a href="https://platform.deepseek.com/" target="_blank" class="yj-platform-link">申请 API Key ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_deepseek_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_deepseek_BaseUrl" name="Platform_deepseek_BaseUrl" value="<?php echo $platformData['deepseek']['BaseUrl']; ?>" placeholder="https://api.deepseek.com">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_deepseek_ApiKey">API Key</label>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input class="yj-input" type="password" id="Platform_deepseek_ApiKey" name="Platform_deepseek_ApiKey" value="<?php echo $platformData['deepseek']['DisplayKey']; ?>" placeholder="sk-..." autocomplete="new-password" style="padding-right: 40px;">
                                            <button type="button" class="btn-toggle-password" style="position: absolute; right: 8px; background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center;" title="显示/隐藏 API Key">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_deepseek_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_deepseek_DefaultModel" name="Platform_deepseek_DefaultModel" value="<?php echo $platformData['deepseek']['DefaultModel']; ?>" placeholder="deepseek-chat" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="deepseek" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-deepseek" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="deepseek" data-model="deepseek-chat">deepseek-chat</span>
                                            <span class="yj-model-badge" data-platform="deepseek" data-model="deepseek-reasoner">deepseek-reasoner</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. OpenAI -->
                                <div id="platform-section-openai" class="platform-settings-section" style="<?php echo $activePlatform === 'openai' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">OpenAI 接口设置</h3>
                                        <a href="https://platform.openai.com/" target="_blank" class="yj-platform-link">申请 API Key ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_openai_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_openai_BaseUrl" name="Platform_openai_BaseUrl" value="<?php echo $platformData['openai']['BaseUrl']; ?>" placeholder="https://api.openai.com/v1">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_openai_ApiKey">API Key</label>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input class="yj-input" type="password" id="Platform_openai_ApiKey" name="Platform_openai_ApiKey" value="<?php echo $platformData['openai']['DisplayKey']; ?>" placeholder="sk-..." autocomplete="new-password" style="padding-right: 40px;">
                                            <button type="button" class="btn-toggle-password" style="position: absolute; right: 8px; background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center;" title="显示/隐藏 API Key">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_openai_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_openai_DefaultModel" name="Platform_openai_DefaultModel" value="<?php echo $platformData['openai']['DefaultModel']; ?>" placeholder="gpt-4o-mini" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="openai" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-openai" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="openai" data-model="gpt-4o-mini">gpt-4o-mini</span>
                                            <span class="yj-model-badge" data-platform="openai" data-model="gpt-4o">gpt-4o</span>
                                            <span class="yj-model-badge" data-platform="openai" data-model="o1-mini">o1-mini</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Claude -->
                                <div id="platform-section-claude" class="platform-settings-section" style="<?php echo $activePlatform === 'claude' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">Claude 接口设置</h3>
                                        <a href="https://console.anthropic.com/" target="_blank" class="yj-platform-link">申请 API Key ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_claude_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_claude_BaseUrl" name="Platform_claude_BaseUrl" value="<?php echo $platformData['claude']['BaseUrl']; ?>" placeholder="https://api.anthropic.com/v1">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_claude_ApiKey">API Key</label>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input class="yj-input" type="password" id="Platform_claude_ApiKey" name="Platform_claude_ApiKey" value="<?php echo $platformData['claude']['DisplayKey']; ?>" placeholder="sk-ant-..." autocomplete="new-password" style="padding-right: 40px;">
                                            <button type="button" class="btn-toggle-password" style="position: absolute; right: 8px; background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center;" title="显示/隐藏 API Key">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_claude_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_claude_DefaultModel" name="Platform_claude_DefaultModel" value="<?php echo $platformData['claude']['DefaultModel']; ?>" placeholder="claude-3-5-sonnet-latest" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="claude" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-claude" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="claude" data-model="claude-3-5-sonnet-latest">claude-3-5-sonnet-latest</span>
                                            <span class="yj-model-badge" data-platform="claude" data-model="claude-3-5-haiku-latest">claude-3-5-haiku-latest</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. Gemini -->
                                <div id="platform-section-gemini" class="platform-settings-section" style="<?php echo $activePlatform === 'gemini' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">Gemini 接口设置</h3>
                                        <a href="https://aistudio.google.com/" target="_blank" class="yj-platform-link">申请 API Key ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_gemini_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_gemini_BaseUrl" name="Platform_gemini_BaseUrl" value="<?php echo $platformData['gemini']['BaseUrl']; ?>" placeholder="https://generativelanguage.googleapis.com/v1beta/openai">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_gemini_ApiKey">API Key</label>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input class="yj-input" type="password" id="Platform_gemini_ApiKey" name="Platform_gemini_ApiKey" value="<?php echo $platformData['gemini']['DisplayKey']; ?>" placeholder="AIzaSy..." autocomplete="new-password" style="padding-right: 40px;">
                                            <button type="button" class="btn-toggle-password" style="position: absolute; right: 8px; background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center;" title="显示/隐藏 API Key">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_gemini_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_gemini_DefaultModel" name="Platform_gemini_DefaultModel" value="<?php echo $platformData['gemini']['DefaultModel']; ?>" placeholder="gemini-1.5-flash" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="gemini" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-gemini" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="gemini" data-model="gemini-1.5-flash">gemini-1.5-flash</span>
                                            <span class="yj-model-badge" data-platform="gemini" data-model="gemini-1.5-pro">gemini-1.5-pro</span>
                                            <span class="yj-model-badge" data-platform="gemini" data-model="gemini-2.0-flash-exp">gemini-2.0-flash-exp</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. Aliyun -->
                                <div id="platform-section-aliyun" class="platform-settings-section" style="<?php echo $activePlatform === 'aliyun' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">阿里云百炼设置</h3>
                                        <a href="https://bailian.console.aliyun.com/" target="_blank" class="yj-platform-link">申请 API Key ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_aliyun_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_aliyun_BaseUrl" name="Platform_aliyun_BaseUrl" value="<?php echo $platformData['aliyun']['BaseUrl']; ?>" placeholder="https://dashscope.aliyuncs.com/compatible-mode/v1">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_aliyun_ApiKey">API Key</label>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input class="yj-input" type="password" id="Platform_aliyun_ApiKey" name="Platform_aliyun_ApiKey" value="<?php echo $platformData['aliyun']['DisplayKey']; ?>" placeholder="sk-..." autocomplete="new-password" style="padding-right: 40px;">
                                            <button type="button" class="btn-toggle-password" style="position: absolute; right: 8px; background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center;" title="显示/隐藏 API Key">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_aliyun_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_aliyun_DefaultModel" name="Platform_aliyun_DefaultModel" value="<?php echo $platformData['aliyun']['DefaultModel']; ?>" placeholder="qwen-turbo" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="aliyun" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-aliyun" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="aliyun" data-model="qwen-turbo">qwen-turbo</span>
                                            <span class="yj-model-badge" data-platform="aliyun" data-model="qwen-plus">qwen-plus</span>
                                            <span class="yj-model-badge" data-platform="aliyun" data-model="qwen-max">qwen-max</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 6. Baidu -->
                                <div id="platform-section-baidu" class="platform-settings-section" style="<?php echo $activePlatform === 'baidu' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">百度千帆大模型平台 (v2) 设置</h3>
                                        <a href="https://console.bce.baidu.com/qianfan/" target="_blank" class="yj-platform-link">申请 API Key ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_baidu_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_baidu_BaseUrl" name="Platform_baidu_BaseUrl" value="<?php echo $platformData['baidu']['BaseUrl']; ?>" placeholder="https://qianfan.baidubce.com/v2">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_baidu_ApiKey">API Key</label>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input class="yj-input" type="password" id="Platform_baidu_ApiKey" name="Platform_baidu_ApiKey" value="<?php echo $platformData['baidu']['DisplayKey']; ?>" placeholder="输入百度千帆 v2 Token..." autocomplete="new-password" style="padding-right: 40px;">
                                            <button type="button" class="btn-toggle-password" style="position: absolute; right: 8px; background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center;" title="显示/隐藏 API Key">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_baidu_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_baidu_DefaultModel" name="Platform_baidu_DefaultModel" value="<?php echo $platformData['baidu']['DefaultModel']; ?>" placeholder="ernie-speed-128k" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="baidu" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-baidu" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="baidu" data-model="ernie-speed-128k">ernie-speed-128k</span>
                                            <span class="yj-model-badge" data-platform="baidu" data-model="ernie-lite-8k">ernie-lite-8k</span>
                                            <span class="yj-model-badge" data-platform="baidu" data-model="ernie-4.0-turbo-8k">ernie-4.0-turbo-8k</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 7. Tencent -->
                                <div id="platform-section-tencent" class="platform-settings-section" style="<?php echo $activePlatform === 'tencent' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">腾讯混元大模型设置</h3>
                                        <a href="https://console.cloud.tencent.com/hunyuan" target="_blank" class="yj-platform-link">申请 API Key ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_tencent_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_tencent_BaseUrl" name="Platform_tencent_BaseUrl" value="<?php echo $platformData['tencent']['BaseUrl']; ?>" placeholder="https://api.hunyuan.tencentcloudapi.com/v1">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_tencent_ApiKey">API Key</label>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input class="yj-input" type="password" id="Platform_tencent_ApiKey" name="Platform_tencent_ApiKey" value="<?php echo $platformData['tencent']['DisplayKey']; ?>" placeholder="SecretId:SecretKey..." autocomplete="new-password" style="padding-right: 40px;">
                                            <button type="button" class="btn-toggle-password" style="position: absolute; right: 8px; background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center;" title="显示/隐藏 API Key">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_tencent_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_tencent_DefaultModel" name="Platform_tencent_DefaultModel" value="<?php echo $platformData['tencent']['DefaultModel']; ?>" placeholder="hunyuan-lite" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="tencent" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-tencent" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="tencent" data-model="hunyuan-lite">hunyuan-lite</span>
                                            <span class="yj-model-badge" data-platform="tencent" data-model="hunyuan-standard">hunyuan-standard</span>
                                            <span class="yj-model-badge" data-platform="tencent" data-model="hunyuan-pro">hunyuan-pro</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 8. Zhipu -->
                                <div id="platform-section-zhipu" class="platform-settings-section" style="<?php echo $activePlatform === 'zhipu' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">智谱 GLM 大模型设置</h3>
                                        <a href="https://open.bigmodel.cn/" target="_blank" class="yj-platform-link">申请 API Key ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_zhipu_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_zhipu_BaseUrl" name="Platform_zhipu_BaseUrl" value="<?php echo $platformData['zhipu']['BaseUrl']; ?>" placeholder="https://open.bigmodel.cn/api/paas/v4">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_zhipu_ApiKey">API Key</label>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input class="yj-input" type="password" id="Platform_zhipu_ApiKey" name="Platform_zhipu_ApiKey" value="<?php echo $platformData['zhipu']['DisplayKey']; ?>" placeholder="api_key..." autocomplete="new-password" style="padding-right: 40px;">
                                            <button type="button" class="btn-toggle-password" style="position: absolute; right: 8px; background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center;" title="显示/隐藏 API Key">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_zhipu_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_zhipu_DefaultModel" name="Platform_zhipu_DefaultModel" value="<?php echo $platformData['zhipu']['DefaultModel']; ?>" placeholder="glm-4-flash" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="zhipu" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-zhipu" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="zhipu" data-model="glm-4-flash">glm-4-flash</span>
                                            <span class="yj-model-badge" data-platform="zhipu" data-model="glm-4-air">glm-4-air</span>
                                            <span class="yj-model-badge" data-platform="zhipu" data-model="glm-4-plus">glm-4-plus</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 9. Moonshot -->
                                <div id="platform-section-moonshot" class="platform-settings-section" style="<?php echo $activePlatform === 'moonshot' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">月之暗面 (Kimi) 接口设置</h3>
                                        <a href="https://platform.moonshot.cn/" target="_blank" class="yj-platform-link">申请 API Key ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_moonshot_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_moonshot_BaseUrl" name="Platform_moonshot_BaseUrl" value="<?php echo $platformData['moonshot']['BaseUrl']; ?>" placeholder="https://api.moonshot.cn/v1">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_moonshot_ApiKey">API Key</label>
                                        <div style="position: relative; display: flex; align-items: center;">
                                            <input class="yj-input" type="password" id="Platform_moonshot_ApiKey" name="Platform_moonshot_ApiKey" value="<?php echo $platformData['moonshot']['DisplayKey']; ?>" placeholder="sk-..." autocomplete="new-password" style="padding-right: 40px;">
                                            <button type="button" class="btn-toggle-password" style="position: absolute; right: 8px; background: none; border: none; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center;" title="显示/隐藏 API Key">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_moonshot_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_moonshot_DefaultModel" name="Platform_moonshot_DefaultModel" value="<?php echo $platformData['moonshot']['DefaultModel']; ?>" placeholder="moonshot-v1-8k" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="moonshot" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-moonshot" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="moonshot" data-model="moonshot-v1-8k">moonshot-v1-8k</span>
                                            <span class="yj-model-badge" data-platform="moonshot" data-model="moonshot-v1-32k">moonshot-v1-32k</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 10. Ollama -->
                                <div id="platform-section-ollama" class="platform-settings-section" style="<?php echo $activePlatform === 'ollama' ? '' : 'display:none;'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <h3 style="margin: 0; font-size: 14px; color: #1e293b; font-weight: 600;">Ollama 本地配置</h3>
                                        <a href="https://ollama.com/" target="_blank" class="yj-platform-link">访问 Ollama 官网 ➜</a>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_ollama_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_ollama_BaseUrl" name="Platform_ollama_BaseUrl" value="<?php echo $platformData['ollama']['BaseUrl']; ?>" placeholder="http://127.0.0.1:11434/v1">
                                        <p class="yj-help-text">本地局域网部署请确保服务器与 Ollama 主机网络互通，且已配置跨域环境变量 <code>OLLAMA_HOST=0.0.0.0</code>。</p>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_ollama_ApiKey">API Key</label>
                                        <input class="yj-input" type="text" id="Platform_ollama_ApiKey" name="Platform_ollama_ApiKey" value="<?php echo $platformData['ollama']['DisplayKey']; ?>" placeholder="无密钥时可为空">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_ollama_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_ollama_DefaultModel" name="Platform_ollama_DefaultModel" value="<?php echo $platformData['ollama']['DefaultModel']; ?>" placeholder="llama3" style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="ollama" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-ollama" style="margin-top: 6px; display: none;"></div>
                                        <div style="margin-top: 6px;">
                                            <span class="yj-model-badge" data-platform="ollama" data-model="llama3">llama3</span>
                                            <span class="yj-model-badge" data-platform="ollama" data-model="qwen2.5:7b">qwen2.5:7b</span>
                                            <span class="yj-model-badge" data-platform="ollama" data-model="deepseek-r1:7b">deepseek-r1:7b</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 11. Custom -->
                                <div id="platform-section-custom" class="platform-settings-section" style="<?php echo $activePlatform === 'custom' ? '' : 'display:none;'; ?>">
                                    <h3 style="margin: 0 0 12px 0; font-size: 14px; color: #1e293b; font-weight: 600;">自定义端点配置</h3>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_custom_BaseUrl">接口地址 (Base URL)</label>
                                        <input class="yj-input" type="text" id="Platform_custom_BaseUrl" name="Platform_custom_BaseUrl" value="<?php echo $platformData['custom']['BaseUrl']; ?>" placeholder="https://api.yourproxy.com/v1">
                                        <p class="yj-help-text">支持任意遵循标准 OpenAI 协议规范的代理或聚合平台接口。</p>
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_custom_ApiKey">API Key</label>
                                        <input class="yj-input" type="password" id="Platform_custom_ApiKey" name="Platform_custom_ApiKey" value="<?php echo $platformData['custom']['DisplayKey']; ?>" placeholder="sk-..." autocomplete="new-password">
                                    </div>
                                    <div class="yj-form-group">
                                        <label class="yj-label" for="Platform_custom_DefaultModel">默认模型 (Model)</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input class="yj-input" type="text" id="Platform_custom_DefaultModel" name="Platform_custom_DefaultModel" value="<?php echo $platformData['custom']['DefaultModel']; ?>" placeholder="输入指定模型标识符..." style="flex-grow: 1;">
                                            <button type="button" class="yj-btn yj-btn-secondary btn-fetch-models" data-platform="custom" style="flex-shrink: 0; padding: 0 12px; font-size: 12px; height: 35px; white-space: nowrap;">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg>
                                                获取模型列表
                                            </button>
                                        </div>
                                        <div class="yj-fetched-models" id="fetched-models-custom" style="margin-top: 6px; display: none;"></div>
                                    </div>
                                </div>

                                    </div>

                                    <div style="margin-top: 16px; display: flex; gap: 12px; border-top: 1px solid #cbd5e1; padding-top: 12px;">
                                        <button type="button" id="btnTest" class="yj-btn yj-btn-secondary">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            测试当前连接
                                        </button>
                                    </div>

                                    <div id="testResult" class="yj-test-result"></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- 面板 2: 高级配置 -->
                    <section class="jichu-admin-panel" id="jichu-tab-advanced" role="tabpanel" aria-labelledby="jichu-tab-trigger-advanced" data-tab-panel="advanced" hidden>
                        <div class="yj-card">
                            <h2 class="yj-card-title">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                                连接参数与环境检查
                            </h2>
                            
                            <div class="yj-form-group">
                                <label class="yj-label" for="Timeout">全局 API 请求超时限制 (秒)</label>
                                <input class="yj-input" type="number" id="Timeout" name="Timeout" value="<?php echo $timeout; ?>" min="5" max="300" required>
                                <p class="yj-help-text">超时时间默认建议设在 60 秒以上，长文本处理可适当上调。</p>
                            </div>

                            <div style="margin-top: 20px;">
                                <h3 style="font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 8px;">服务器环境健康状况</h3>
                                <table class="yj-check-table" style="border-radius: 6px;">
                                    <thead>
                                        <tr>
                                            <th>检测项</th>
                                            <th>期望条件</th>
                                            <th>当前环境</th>
                                            <th>状态</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>PHP 版本</td>
                                            <td>>= 7.0</td>
                                            <td><?php echo PHP_VERSION; ?></td>
                                            <td>
                                                <?php if (version_compare(PHP_VERSION, '7.0.0', '>=')): ?>
                                                    <span class="yj-check-pass">✓ 正常</span>
                                                <?php else: ?>
                                                    <span class="yj-check-fail">✗ 过低</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>PHP cURL 扩展</td>
                                            <td>开启</td>
                                            <td><?php echo function_exists('curl_init') ? '已启用' : '未加载'; ?></td>
                                            <td>
                                                <?php if (function_exists('curl_init')): ?>
                                                    <span class="yj-check-pass">✓ 正常</span>
                                                <?php else: ?>
                                                    <span class="yj-check-fail">✗ 缺失（无法请求接口）</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>PHP OpenSSL 扩展</td>
                                            <td>开启</td>
                                            <td><?php echo function_exists('openssl_encrypt') ? '已启用' : '未加载'; ?></td>
                                            <td>
                                                <?php if (function_exists('openssl_encrypt')): ?>
                                                    <span class="yj-check-pass">✓ 正常</span>
                                                <?php else: ?>
                                                    <span class="yj-check-fail">✗ 缺失（降级为异或混淆保存）</span>
                                                </td>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>内存可用限制</td>
                                            <td>>= 128M</td>
                                            <td><?php echo ini_get('memory_limit'); ?></td>
                                            <td><span class="yj-check-pass">✓ 正常</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- 面板 3: AI产品矩阵 -->
                    <section class="jichu-admin-panel" id="jichu-tab-matrix" role="tabpanel" aria-labelledby="jichu-tab-trigger-matrix" data-tab-panel="matrix" hidden>
                        <div class="yj-card">
                            <h2 class="yj-card-title">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                优杰AI 核心系列产品矩阵
                            </h2>
                            <p style="font-size: 13px; color: #64748b; margin-top: -10px; margin-bottom: 12px; line-height: 1.5;">
                                购买激活以下付费子插件可解锁独立功能。所有插件均基于底座 API 连接器，实现一次配妥密钥、全站开箱即用。
                            </p>

                            <div style="overflow-x: auto; margin-top: 8px;">
                                <table class="yj-check-table" style="width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 13px; margin: 0; border: 1px solid #cbd5e1; border-radius: 6px;">
                                    <thead>
                                        <tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1; color: #334155;">
                                            <th style="padding: 10px 12px; text-align: center; width: 40px; font-weight: 600;">#</th>
                                            <th style="padding: 10px 12px; text-align: left; width: 180px; font-weight: 600;">插件名称</th>
                                            <th style="padding: 10px 12px; text-align: left; width: 400px; font-weight: 600;">插件介绍</th>
                                            <th style="padding: 10px 12px; text-align: left; font-weight: 600;">插件亮点</th>
                                            <th style="padding: 10px 12px; text-align: center; width: 90px; font-weight: 600;">当前状态</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_plugins as $p): ?>
                                            <?php
                                            $app_id = isset($p['app_id']) ? $p['app_id'] : '';
                                             if (!empty($app_id)) {
                                                 $btn_html = '<a href="https://app.zblogcn.com/?id=' . $app_id . '" target="_blank" style="text-decoration: none;"><span class="yj-badge-active">去插件页</span></a>';
                                             } else {
                                                 $btn_html = '<span class="yj-badge-planned">规划中</span>';
                                             }
                                            ?>
                                            <tr style="border-bottom: 1px solid #e2e8f0; background: #ffffff;">
                                                <td style="padding: 10px 12px; text-align: center; color: #64748b;"><?php echo $p['order']; ?></td>
                                                <td style="padding: 10px 12px; font-weight: 600; color: #1e293b; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;" title="<?php echo htmlspecialchars($p['name']); ?>">
                                                    <?php echo htmlspecialchars($p['name']); ?>
                                                </td>
                                                <td style="padding: 10px 12px; color: #475569; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;" title="<?php echo htmlspecialchars($p['desc']); ?>"><?php echo htmlspecialchars($p['desc']); ?></td>
                                                <td style="padding: 10px 12px; color: #475569; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;" title="<?php echo htmlspecialchars($p['points']); ?>"><?php echo htmlspecialchars($p['points']); ?></td>
                                                <td style="padding: 10px 12px; text-align: center;"><?php echo $btn_html; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top: 12px; font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 6px;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink: 0; color: #2563eb;"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>更多实用 AI 插件（如：万能写文助手、自动摘要、垃圾评论拦截器、GEO 搜索优化助手等）正在开发与适配中，敬请期待！</span>
                            </div>
                        </div>
                    </section>

                    <!-- 面板 4: 备份与导入 -->
                    <section class="jichu-admin-panel" id="jichu-tab-backup" role="tabpanel" aria-labelledby="jichu-tab-trigger-backup" data-tab-panel="backup" hidden>
                        <div class="yj-card">
                            <h2 class="yj-card-title">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                导出配置 (备份)
                            </h2>
                            <div style="font-size: 13px; color: #475569; line-height: 1.6; margin-bottom: 12px;">
                                <p style="margin: 0 0 8px 0;">导出当前的平台配置（包括 API 地址、API Key、默认模型等，不含敏感的系统核心密码）。您可以复制下方的密文进行保存，或者直接下载 JSON 配置文件。</p>
                            </div>
                            
                            <div class="yj-form-group">
                                <label class="yj-label" for="backupTextarea">配置密文 (Base64 格式)</label>
                                <textarea id="backupTextarea" class="yj-input" style="height: 100px; font-family: monospace; font-size: 11px; resize: vertical; background-color: #f8fafc;" readonly><?php
                                    $cfg = $zbp->Config('AiBase');
                                    $platforms = array('deepseek', 'openai', 'claude', 'gemini', 'aliyun', 'baidu', 'tencent', 'zhipu', 'moonshot', 'ollama', 'custom');
                                    $exportData = array();
                                    $exportKeys = array('ActivePlatform', 'Timeout', 'Version');
                                    foreach ($platforms as $platform) {
                                        $exportKeys[] = "Platform_{$platform}_BaseUrl";
                                        $exportKeys[] = "Platform_{$platform}_ApiKey";
                                        $exportKeys[] = "Platform_{$platform}_DefaultModel";
                                    }
                                    foreach ($exportKeys as $k) {
                                        if (strpos($k, '_ApiKey') !== false) {
                                            $exportData[$k] = AiConfigHelper::decrypt($cfg->$k);
                                        } else {
                                            $exportData[$k] = $cfg->$k;
                                        }
                                    }
                                    echo base64_encode(json_encode($exportData));
                                ?></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <button type="button" class="yj-btn yj-btn-secondary" id="btnCopyBackupText">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                    一键复制配置文本
                                </button>
                                <a href="main.php?action=export_download&csrfToken=<?php echo htmlspecialchars($zbp->GetCSRFToken()); ?>" class="yj-btn yj-btn-primary" style="text-decoration: none; color: #ffffff;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    下载 JSON 备份文件
                                </a>
                            </div>
                        </div>

                        <div class="yj-card">
                            <h2 class="yj-card-title">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                导入配置 (恢复)
                            </h2>
                            <div style="font-size: 13px; color: #475569; line-height: 1.6; margin-bottom: 12px;">
                                <p style="margin: 0 0 8px 0; color: #dc2626; font-weight: 600;">⚠️ 警告：导入新配置会覆盖当前所有的模型接口地址、Key 以及模型设定，此操作不可逆，请谨慎操作！</p>
                                <p style="margin: 0;">您可以选择以下**任意一种**方式进行导入：</p>
                            </div>
                            
                            <div class="yj-form-group" style="border-bottom: 1px dashed #cbd5e1; padding-bottom: 15px; margin-bottom: 15px;">
                                <label class="yj-label" for="backupFileInput">方法一：上传 JSON 备份文件</label>
                                <input type="file" id="backupFileInput" class="yj-input" style="padding: 6px;" accept=".json" />
                                <div class="yj-help-text">请选择此前导出的 .json 格式备份文件。</div>
                            </div>
                            
                            <div class="yj-form-group">
                                <label class="yj-label" for="backupTextareaInput">方法二：粘贴配置密文</label>
                                <textarea id="backupTextareaInput" class="yj-input" style="height: 100px; font-family: monospace; font-size: 11px; resize: vertical;" placeholder="请在此处粘贴导出的 Base64 编码配置密文..."></textarea>
                                <div class="yj-help-text">请将导出的 Base64 密文完整复制并粘贴到上方输入框中。</div>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <button type="button" class="yj-btn yj-btn-primary" id="btnSubmitImport" style="background-color: #dc2626; border-color: #dc2626;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                    确认导入配置
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- 面板 5: 使用指南 & 关于作者 -->
                    <section class="jichu-admin-panel" id="jichu-tab-guide" role="tabpanel" aria-labelledby="jichu-tab-trigger-guide" data-tab-panel="guide" hidden>
                        <div class="yj-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #cbd5e1; padding-bottom: 12px;">
                                <h2 style="margin: 0; font-size: 16px; color: #1e293b; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                    使用指南 & 关于作者
                                </h2>
                                <a href="https://app.zblogcn.com/?auth=4a02f8cd-19a8-40ec-9ab9-1dc2aadfdfd1" target="_blank" class="yj-btn yj-btn-primary" style="text-decoration: none; padding: 6px 16px; font-weight: bold; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; background-color: #2563eb; color: #ffffff; border-radius: 6px; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.15);">
                                    访问作者应用中心主页 ➜
                                </a>
                            </div>

                            <!-- 1. 使用指南 Section -->
                            <div style="margin-bottom: 28px;">
                                <h3 style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 12px 0; border-left: 3px solid #2563eb; padding-left: 10px;">📖 大模型底座操作手册 (避坑指南)</h3>
                                <div style="font-size: 13px; color: #475569; line-height: 1.8; padding-left: 10px;">
                                    <p style="margin: 0 0 12px 0;"><strong>问：这玩意儿是干啥的？</strong><br>
                                    答：它就相当于你网站上所有 AI 插件的“大总管”。你在这里把大模型密钥配置好，其他子插件（如 AI 写作、AI 摘要、AI 评论等）直接开箱即用，不用每次都填一万个 API Key。省事，也省心。</p>
                                    
                                    <p style="margin: 0 0 12px 0;"><strong>问：到底选哪个模型好？我挑花眼了。</strong><br>
                                    答：大厂模型千千万，记住八字真言即可：<strong>「国内首选，国外备用」</strong>：
                                    <br>• <strong>DeepSeek</strong>：性价比之王。价格极其便宜，中文能力超强，国内直连速度快。首选默认！
                                    <br>• <strong>国产大厂（阿里百炼/百度千帆/腾讯混元）</strong>：新人注册都送几千万免费 Token，有效期 90 天，用来白嫖测试最爽，网络也是稳如狗。
                                    <br>• <strong>OpenAI (GPT-4o) / Claude</strong>：虽然智商极高，但由于众所周知的原因，大陆服务器**千万别直连**，必须找中转接口（或者配置代理），否则百分百网络超时。</p>
                                    
                                    <p style="margin: 0 0 12px 0;"><strong>问：点击“测试当前连接”提示错误，我是不是废了？</strong><br>
                                    答：别慌，先来对号入座，自己当回老中医：
                                    <br>• <strong>401 API Key 错误</strong>：基本上是你的 Key 复制错了，或者多复制了看不见的空格/换行符，要么是刚申请的 Key 还没生效。点击右侧的“小眼睛”睁开仔细核对。
                                    <br>• <strong>402 余额不足/超限</strong>：你的大模型账户充值额度用光了，或者免费额度过期了。去对应服务商后台充值点小钱（一瓶可乐钱就能用很久）。
                                    <br>• <strong>404 未找到路径/重定向 307</strong>：**十有八九是 Base URL（接口地址）填错了！** 许多同学手抖把 `/chat/completions` 也写到了接口地址后面，导致我们代码里拼成了 `/chat/completions/chat/completions`。正确的 Base URL 应该写到版本号就打住，比如 `https://api.deepseek.com` 或 `https://qianfan.baidubce.com/v2`，不要多尾随子路径。
                                    <br>• <strong>SSL 握手失败</strong>：说明你服务器的 OpenSSL 组件版本太老，听不懂远程大厂的安全加密协议。最快的解决办法是将接口地址开头的 `https://` 改写为 `http://`（如果对方支持），或者升级你服务器的 PHP/OpenSSL 环境。</p>
                                </div>
                            </div>

                            <!-- 2. 作者介绍 Section -->
                            <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; margin-bottom: 10px;">
                                <h3 style="font-size: 14px; font-weight: 700; color: #1e293b; margin: 0 0 12px 0; border-left: 3px solid #2563eb; padding-left: 10px;">🙋 关于作者 (Yojack)</h3>
                                <div style="font-size: 13px; color: #475569; line-height: 1.8; padding-left: 10px;">
                                    <p style="margin: 0 0 10px 0;">主业正经写前端（React、TypeScript、各种动效和动画），晚上和周末兼职折腾 Z-Blog。写主题也写插件，两份工作，一份头发。</p>
                                    <p style="margin: 0 0 10px 0;">目前在应用中心上架了多款主题和插件，包括响应式博客皮肤（睿知博汇/ModernMag/优杰简约）、网址导航主题，以及 3D 拟真站长名片、站长广告 Pro、SeoGeo Pro 优化插件等。如果您在使用过程中遇到任何 Bug（请先检查是不是缓存），或者有 Z-Blog 主题定制、插件二次开发等需求，可以通过我的个人网站联系我：</p>
                                    <p style="margin: 0;">🌐 **个人官网**：<a href="https://ztwd.cn" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 600;">https://ztwd.cn ➜</a></p>
                                </div>
                            </div>
                        </div>
                    </section>

                </div>
            </div>
        </form>
        
        <!-- 隐藏的导入表单，避免嵌套 Form 的问题 -->
        <form id="importForm" method="post" action="main.php?action=import" enctype="multipart/form-data" style="display:none;">
            <input type="hidden" name="csrfToken" value="<?php echo htmlspecialchars($zbp->GetCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>" />
            <textarea name="import_text" id="hiddenImportText"></textarea>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // 0. 密码/API Key 明文切换显示逻辑
    $(document).on("click", ".btn-toggle-password", function(e) {
        e.preventDefault();
        var $input = $(this).siblings("input");
        var isPassword = $input.attr("type") === "password";
        $input.attr("type", isPassword ? "text" : "password");
        
        // 切换眼睛图标
        var $svg = $(this).find("svg");
        if (isPassword) {
            // 闭眼/斜线眼睛
            $svg.html('<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.893 7.893L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />');
        } else {
            // 睁眼
            $svg.html('<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />');
        }
    });

    // 1. Tab页签切换逻辑
    $(".jichu-admin-tab, .yj-admin-tab").on("click", function(e) {
        e.preventDefault();
        var tab = $(this).attr("data-tab-target");
        
        // 按钮激活态切换
        $(".jichu-admin-tab, .yj-admin-tab").removeClass("is-active").attr("aria-selected", "false");
        $(this).addClass("is-active").attr("aria-selected", "true");
        
        // 面板显隐切换
        $(".jichu-admin-panel").removeClass("is-active").attr("hidden", "hidden");
        $("#jichu-tab-" + tab).addClass("is-active").removeAttr("hidden");
    });
    
    // 2. 左侧平台列表选择与活动通道激活联动
    $(".yj-platform-item").on("click", function(e) {
        var platform = $(this).attr("data-platform-id");
        
        $(".yj-platform-item").removeClass("is-selected");
        $(this).addClass("is-selected");
        
        $(".platform-settings-section").hide();
        $("#platform-section-" + platform).fadeIn(200);
    });
    
    $(".yj-active-radio").on("change", function() {
        var $item = $(this).closest(".yj-platform-item");
        $(".yj-platform-item").removeClass("is-selected");
        $item.addClass("is-selected");
        
        var platform = $item.attr("data-platform-id");
        $(".platform-settings-section").hide();
        $("#platform-section-" + platform).fadeIn(200);
    });
    
    // 3. 推荐模型徽章点击填入
    $(".yj-model-badge").on("click", function() {
        var platform = $(this).attr("data-platform");
        var model = $(this).attr("data-model");
        $("#Platform_" + platform + "_DefaultModel").val(model);
    });
    
    // 4. AJAX 测试连接逻辑
    $("#btnTest").on("click", function() {
        // 获取当前正在编辑/高亮显示的平台
        var platform = $(".yj-platform-item.is-selected").attr("data-platform-id");
        if (!platform) {
            platform = $("input[name='ActivePlatform']:checked").val();
        }
        var baseUrl = $("#Platform_" + platform + "_BaseUrl").val();
        var apiKey = $("#Platform_" + platform + "_ApiKey").val();
        var defaultModel = $("#Platform_" + platform + "_DefaultModel").val();
        
        var $btn = $(this);
        var $result = $("#testResult");
        
        $btn.prop("disabled", true).text("连接测试中...");
        $result.removeClass("yj-test-success yj-test-error").hide().text("");
        
        $.ajax({
            url: "main.php?action=test",
            type: "POST",
            data: {
                Platform: platform,
                BaseUrl: baseUrl,
                ApiKey: apiKey,
                DefaultModel: defaultModel,
                csrfToken: $("input[name='csrfToken']").val()
            },
            dataType: "json",
            success: function(res) {
                $btn.prop("disabled", false).html('<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg> 测试当前连接');
                if (res.status === "success") {
                    $result.addClass("yj-test-success").text(res.message).fadeIn();
                } else {
                    $result.addClass("yj-test-error").text(res.message).fadeIn();
                }
            },
            error: function(xhr, status, err) {
                $btn.prop("disabled", false).html('<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg> 测试当前连接');
                $result.addClass("yj-test-error").text("网络请求异常，测试失败。请检查您的网络连接或后端代理设置。").fadeIn();
            }
        });
    });
    // 6. 一键自动获取最新的模型列表
    $(".btn-fetch-models").on("click", function(e) {
        e.preventDefault();
        var platform = $(this).attr("data-platform");
        var baseUrl = $("#Platform_" + platform + "_BaseUrl").val();
        var apiKey = $("#Platform_" + platform + "_ApiKey").val();
        
        var $btn = $(this);
        var $listContainer = $("#fetched-models-" + platform);
        
        $btn.prop("disabled", true).text("获取中...");
        $listContainer.hide().empty();
        
        $.ajax({
            url: "main.php?action=get_models",
            type: "POST",
            data: {
                Platform: platform,
                BaseUrl: baseUrl,
                ApiKey: apiKey,
                csrfToken: $("input[name='csrfToken']").val()
            },
            dataType: "json",
            success: function(res) {
                $btn.prop("disabled", false).html('<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg> 获取模型列表');
                if (res.status === "success" && res.models && res.models.length > 0) {
                    var families = [
                        { name: "DeepSeek 系列", key: "deepseek", list: [] },
                        { name: "GPT 系列 (OpenAI)", key: "gpt", list: [] },
                        { name: "Claude 系列", key: "claude", list: [] },
                        { name: "Gemini 系列", key: "gemini", list: [] },
                        { name: "通义千问 (Qwen) 系列", key: "qwen", list: [] },
                        { name: "文心一言 (Ernie) 系列", key: "ernie", list: [] },
                        { name: "GLM (智谱) 系列", key: "glm", list: [] },
                        { name: "其他模型", key: "other", list: [] }
                    ];

                    res.models.forEach(function(m) {
                        var lower = m.toLowerCase();
                        var added = false;
                        for (var i = 0; i < families.length - 1; i++) {
                            var kw = families[i].key;
                            if (lower.indexOf(kw) !== -1 || (kw === "gpt" && lower.indexOf("o1-") !== -1)) {
                                families[i].list.push(m);
                                added = true;
                                break;
                            }
                        }
                        if (!added) {
                            families[families.length - 1].list.push(m);
                        }
                    });

                    var html = '<div style="font-size:11px; color:#64748b; margin-top:8px; margin-bottom:4px;">成功获取模型，点击徽章快速填入（标黄为推荐主力模型）：</div>';
                    html += '<div class="yj-models-container">';
                    
                    // 搜索框
                    html += '<div class="yj-models-search-box">';
                    html += '  <input type="text" class="yj-models-search-input" placeholder="🔍 输入关键字过滤模型... (如: r1, max, speed)" />';
                    html += '</div>';

                    families.forEach(function(fam) {
                        if (fam.list.length === 0) return;
                        
                        html += '<div class="yj-model-family-group" data-family-key="' + fam.key + '">';
                        html += '  <div class="yj-model-group-title">' + fam.name + '</div>';
                        html += '  <div class="yj-model-badge-group">';
                        
                        fam.list.forEach(function(m) {
                            var lowerM = m.toLowerCase();
                            var isRec = false;
                            
                            if (lowerM.indexOf("deepseek-v3") !== -1 || 
                                lowerM.indexOf("deepseek-r1") !== -1 || 
                                lowerM.indexOf("gpt-4o") !== -1 || 
                                lowerM.indexOf("claude-3-5") !== -1 || 
                                lowerM.indexOf("gemini-1.5-flash") !== -1 || 
                                lowerM.indexOf("gemini-2.0") !== -1 || 
                                lowerM.indexOf("ernie-speed") !== -1 || 
                                lowerM.indexOf("ernie-4.0") !== -1 || 
                                lowerM.indexOf("qwen-plus") !== -1 || 
                                lowerM.indexOf("qwen-max") !== -1 || 
                                lowerM.indexOf("glm-4-flash") !== -1) {
                                isRec = true;
                            }
                            
                            var recClass = isRec ? " is-recommended" : "";
                            var recTitle = isRec ? " title='推荐主力模型'" : "";
                            
                            html += '<span class="yj-model-badge-modern' + recClass + '"' + recTitle + ' data-platform="' + platform + '" data-model="' + m + '">' + m + '</span>';
                        });
                        
                        html += '  </div>';
                        html += '</div>';
                    });

                    html += '</div>';
                    $listContainer.html(html).fadeIn();
                    
                    // 绑定点击事件给动态生成的徽章
                    $listContainer.find(".yj-model-badge-modern").on("click", function() {
                        var model = $(this).attr("data-model");
                        $("#Platform_" + platform + "_DefaultModel").val(model);
                        // 添加微动反馈
                        $(this).css("transform", "scale(0.95)");
                        setTimeout(function() {
                            $(".yj-model-badge-modern").css("transform", "");
                        }, 100);
                    });

                    // 绑定搜索框键盘事件
                    $listContainer.find(".yj-models-search-input").on("input", function() {
                        var searchVal = $(this).val().toLowerCase().trim();
                        if (searchVal === "") {
                            $listContainer.find(".yj-model-badge-modern").show();
                            $listContainer.find(".yj-model-family-group").show();
                        } else {
                            $listContainer.find(".yj-model-family-group").each(function() {
                                var $group = $(this);
                                var visibleCount = 0;
                                $group.find(".yj-model-badge-modern").each(function() {
                                    var modelName = $(this).attr("data-model").toLowerCase();
                                    if (modelName.indexOf(searchVal) !== -1) {
                                        $(this).show();
                                        visibleCount++;
                                    } else {
                                        $(this).hide();
                                    }
                                });
                                if (visibleCount > 0) {
                                    $group.show();
                                } else {
                                    $group.hide();
                                }
                            });
                        }
                    });
                } else {
                    var errMsg = res.message || "未返回可用模型列表。";
                    $listContainer.html('<div style="font-size:11px; color:#ef4444; margin-top:6px;">获取失败: ' + errMsg + '</div>').fadeIn();
                }
            },
            error: function() {
                $btn.prop("disabled", false).html('<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path></svg> 获取模型列表');
                $listContainer.html('<div style="font-size:11px; color:#ef4444; margin-top:6px;">网络请求异常，无法获取。</div>').fadeIn();
            }
        });
    });
    
    // 5. Ctrl+S 键盘保存快捷键
    $(document).on("keydown", function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "s") {
            e.preventDefault();
            $("#aiForm").submit();
        }
    });

    // 7. 一键复制配置文本
    $(document).on("click", "#btnCopyBackupText", function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $textarea = $("#backupTextarea");
        
        $textarea.select();
        try {
            document.execCommand("copy");
            var oldHtml = $btn.html();
            $btn.html('<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> 已成功复制！').addClass("yj-btn-success").prop("disabled", true);
            setTimeout(function() {
                $btn.html(oldHtml).removeClass("yj-btn-success").prop("disabled", false);
            }, 2000);
        } catch (err) {
            alert("复制失败，请手动选择文本进行复制。");
        }
    });

    // 7.1 点击文本框自动全选
    $(document).on("click", "#backupTextarea", function() {
        $(this).select();
    });

    // 8. 导入配置校验与提交
    $(document).on("click", "#btnSubmitImport", function(e) {
        e.preventDefault();
        var textVal = $("#backupTextareaInput").val().trim();
        var fileInput = $("#backupFileInput")[0];
        var hasFile = fileInput.files && fileInput.files.length > 0;
        
        if (!textVal && !hasFile) {
            alert("请先粘贴配置文本或选择备份文件！");
            return;
        }
        
        if (confirm("⚠️ 注意：导入配置将覆盖当前所有的平台和模型设置，此操作不可逆！\n\n确定要继续吗？")) {
            $("#hiddenImportText").val(textVal);
            if (hasFile) {
                $("#backupFileInput").attr("name", "import_file").appendTo("#importForm");
            }
            $("#importForm").submit();
        }
    });
});
</script>

<?php
// 引入 Z-Blog 后台标准尾部
require $zbp->systemdir . 'admin/admin_footer.php';
RunTime();
?>
