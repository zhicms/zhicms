<?php
/**
 * AI 开放平台控制器
 * 
 * 提供 AI 模型管理、流式对话、图像生成等功能。
 * 路由入口：index.php?r=manage/ai/{action}
 *
 * @package manage
 */

namespace app\manage\controller;

use app\common\AiService;
use app\base\controller\ManageControllerTrait;

class AiController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

    /**
     * AI 设置页面 - 模型管理
     * GET / POST: index.php?r=manage/ai/setting
     */
    public function setting()
    {
        $this->checkManageSession();
        // 禁止浏览器缓存本页，避免编辑弹窗 JS 停留在旧版本
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        $this->pageText = array('AI 开放平台', '模型管理');

        $aiModels    = AiService::models();
        $currentChat  = AiService::getCurrentChatKey();
        $currentImage = AiService::getCurrentImageKey();
        $config      = AiService::loadConfig();
        $aiSystemPrompt = isset($config['ai_system_prompt']) ? $config['ai_system_prompt'] : '';

        $this->assign('aiModels', $aiModels);
        $this->assign('currentChatKey', $currentChat);
        $this->assign('currentImageKey', $currentImage);
        $this->assign('aiSystemPrompt', $aiSystemPrompt);

        $this->display('app/manage/view/ai/setting');
    }

    /**
     * 保存/添加模型
     * POST: index.php?r=manage/ai/save
     */
    public function save()
    {
        $this->checkManageSession();
        if (!$this->isPost()) {
            $this->redirect('index.php?r=manage/ai/setting');
            return;
        }

        $apiUrl   = isset($_POST['ai_api_url']) ? trim($_POST['ai_api_url']) : '';
        $apiKey   = isset($_POST['ai_api_key']) ? trim($_POST['ai_api_key']) : '';
        $model    = isset($_POST['ai_model']) ? trim($_POST['ai_model']) : '';
        $modelType = isset($_POST['ai_model_type']) ? trim($_POST['ai_model_type']) : 'chat';

        if (empty($apiUrl) || empty($apiKey) || empty($model)) {
            echo json_encode(array('info' => '请填写完整的 API 信息', 'status' => 'n'));
            return;
        }

        $config  = AiService::loadConfig();
        $models  = isset($config['ai_models']) ? $config['ai_models'] : array();

        // 生成唯一 key
        $key = substr(md5($apiUrl . $model), 0, 10);
        $models[$key] = array(
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'model'   => $model,
            'type'    => $modelType,
        );

        $config['ai_models'] = $models;

        // 如果该类型还没有启用的模型，自动启用
        if ($modelType === 'chat' && empty($config['ai_chat'])) {
            $config['ai_chat'] = $key;
        } elseif ($modelType === 'image' && empty($config['ai_image'])) {
            $config['ai_image'] = $key;
        }

        AiService::saveConfig($config);

        echo json_encode(array('info' => '模型添加成功', 'status' => 'y'));
    }

    /**
     * 更新模型
     * POST: index.php?r=manage/ai/update
     */
    public function update()
    {
        $this->checkManageSession();

        // POST：接收弹窗表单提交，保存模型配置
        if ($this->isPost()) {
            $key       = isset($_POST['ai_model_key']) ? trim($_POST['ai_model_key']) : '';
            // 兼容两种字段名前缀（edit_ 前缀为当前前端使用；旧缓存前端可能用无前缀同名），避免提交字段不匹配导致参数不更新
            $apiUrl    = isset($_POST['edit_ai_api_url']) ? trim($_POST['edit_ai_api_url']) : (isset($_POST['ai_api_url']) ? trim($_POST['ai_api_url']) : '');
            $apiKey    = isset($_POST['edit_ai_api_key']) ? trim($_POST['edit_ai_api_key']) : (isset($_POST['ai_api_key']) ? trim($_POST['ai_api_key']) : '');
            $model     = isset($_POST['edit_ai_model']) ? trim($_POST['edit_ai_model']) : (isset($_POST['ai_model']) ? trim($_POST['ai_model']) : '');
            $modelType = isset($_POST['ai_model_type']) ? trim($_POST['ai_model_type']) : 'chat';

            if (empty($key)) {
                $this->updateAjaxOrRedirect(array('info' => '参数错误', 'status' => 'n'));
                return;
            }

            $config = AiService::loadConfig();
            $models = isset($config['ai_models']) ? $config['ai_models'] : array();

            if (!isset($models[$key])) {
                $this->updateAjaxOrRedirect(array('info' => '模型不存在', 'status' => 'n'));
                return;
            }

            // 更新：如果 API Key 留空则保留原值
            if (!empty($apiUrl)) {
                $models[$key]['api_url'] = $apiUrl;
            }
            if (!empty($apiKey)) {
                $models[$key]['api_key'] = $apiKey;
            }
            if (!empty($model)) {
                $models[$key]['model'] = $model;
            }
            $models[$key]['type'] = $modelType;

            $config['ai_models'] = $models;
            AiService::saveConfig($config);

            $this->updateAjaxOrRedirect(array('info' => '模型更新成功', 'status' => 'y'));
            return;
        }

        // GET：直接访问该路由时，渲染设置页并自动弹出「编辑模型」弹窗（而非返回 JSON）
        $key = isset($_GET['key']) ? trim($_GET['key']) : '';
        $config = AiService::loadConfig();
        $models = isset($config['ai_models']) ? $config['ai_models'] : array();
        // 未指定 key 时默认取第一个模型
        if ($key === '' && !empty($models)) {
            foreach (array_keys($models) as $k) { $key = $k; break; }
        }
        $data = isset($models[$key]) ? $models[$key] : array();
        $this->assign('autoOpenEdit', !empty($data));
        $this->assign('autoOpenEditKey', $key);
        $this->assign('autoOpenEditData', $data);
        $this->setting();
    }

    /**
     * 启用/切换模型
     * GET: index.php?r=manage/ai/active&key=xxx&type=chat|image
     */
    public function active()
    {
        $this->checkManageSession();
        $key  = isset($_GET['key']) ? trim($_GET['key']) : '';
        $type = isset($_GET['type']) ? trim($_GET['type']) : 'chat';

        if (empty($key)) {
            echo json_encode(array('info' => '参数错误', 'status' => 'n'));
            return;
        }

        $config = AiService::loadConfig();
        if ($type === 'image') {
            $config['ai_image'] = $key;
        } else {
            $config['ai_chat'] = $key;
        }
        AiService::saveConfig($config);

        echo json_encode(array('info' => '模型已切换', 'status' => 'y'));
    }

    /**
     * 删除模型
     * GET: index.php?r=manage/ai/delete&key=xxx
     */
    public function delete()
    {
        $this->checkManageSession();
        $key = isset($_GET['key']) ? trim($_GET['key']) : '';

        if (empty($key)) {
            echo json_encode(array('info' => '参数错误', 'status' => 'n'));
            return;
        }

        $config = AiService::loadConfig();
        $models = isset($config['ai_models']) ? $config['ai_models'] : array();

        if (isset($models[$key])) {
            unset($models[$key]);
            // 如果删除的是当前启用的模型，清除选中状态
            if ($config['ai_chat'] === $key) {
                $config['ai_chat'] = '';
            }
            if ($config['ai_image'] === $key) {
                $config['ai_image'] = '';
            }
        }

        $config['ai_models'] = $models;
        AiService::saveConfig($config);

        echo json_encode(array('info' => '模型已删除', 'status' => 'y'));
    }

    /**
     * 保存系统提示词
     * POST: index.php?r=manage/ai/savePrompt
     */
    public function savePrompt()
    {
        $this->checkManageSession();
        $prompt = isset($_POST['prompt']) ? trim($_POST['prompt']) : '';

        $config = AiService::loadConfig();
        $config['ai_system_prompt'] = $prompt;
        AiService::saveConfig($config);

        echo json_encode(array('info' => '系统提示词保存成功', 'status' => 'y'));
    }

    // ==================== AI 对话 API ====================

    /**
     * 流式对话 (SSE)
     * GET: index.php?r=manage/ai/chatStream&message=xxx
     */
    public function chatStream()
    {
        $this->checkManageSession();
        $message = isset($_GET['message']) ? trim($_GET['message']) : '';

        if (empty($message)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('code' => -1, 'msg' => '消息不能为空'));
            return;
        }

        // 设置 SSE 响应头
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // 构建系统提示词
        $systemPrompt = self::buildSystemPrompt();

        AiService::chatStream($message, $systemPrompt, true);
        exit;
    }

    /**
     * 获取会话历史
     * GET: index.php?r=manage/ai/getHistory
     */
    public function getHistory()
    {
        $this->checkManageSession();
        header('Content-Type: application/json; charset=utf-8');
        $history = AiService::getHistoryPublic();
        echo json_encode(array('code' => 0, 'data' => $history), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 清除会话历史
     * POST: index.php?r=manage/ai/clearHistory
     */
    public function clearHistory()
    {
        $this->checkManageSession();
        AiService::clearHistory();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('code' => 0, 'msg' => '已清除'));
    }

    // ==================== AI 图像生成 API ====================

    /**
     * 生成图像
     * POST: index.php?r=manage/ai/generateImage
     */
    public function generateImage()
    {
        $this->checkManageSession();
        header('Content-Type: application/json; charset=utf-8');

        $prompt  = isset($_POST['prompt']) ? trim($_POST['prompt']) : '';
        $size    = isset($_POST['size']) ? trim($_POST['size']) : '1024x1024';
        $quality = isset($_POST['quality']) ? trim($_POST['quality']) : 'standard';

        if (empty($prompt)) {
            echo json_encode(array('code' => -1, 'msg' => '请输入图像描述'));
            return;
        }

        $options = array(
            'size'    => $size,
            'quality' => $quality,
            'n'       => 1,
        );

        $result = AiService::generateImage($prompt, $options);

        if (isset($result['error'])) {
            echo json_encode(array('code' => -1, 'msg' => $result['error']));
            return;
        }

        // 提取图像 URL
        $imageUrl = '';
        if (isset($result['data'][0]['url'])) {
            $imageUrl = $result['data'][0]['url'];
        } elseif (isset($result['data'][0]['b64_json'])) {
            // Base64 格式：存为文件
            $imageUrl = $this->saveBase64Image($result['data'][0]['b64_json']);
        }

        if (empty($imageUrl)) {
            echo json_encode(array('code' => -1, 'msg' => '图像生成失败，未返回有效数据'));
            return;
        }

        echo json_encode(array(
            'code' => 0,
            'data' => array(
                'url'     => $imageUrl,
                'prompt'  => $prompt,
                'size'    => $size,
            )
        ), JSON_UNESCAPED_UNICODE);
    }

    // ==================== AI 辅助写作 API ====================

    /**
     * AI 一键生成（关键词 + 描述）
     * POST: index.php?r=manage/ai/autoGenerate
     */
    public function autoGenerate()
    {
        $this->checkManageSession();
        header('Content-Type: application/json; charset=utf-8');

        $title   = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? $_POST['content'] : '';

        if (empty($title)) {
            echo json_encode(array('code' => -1, 'msg' => '标题不能为空'));
            return;
        }

        // 大模型未配置：明确报错，提示站长先添加并启用模型
        if (!AiService::isChatAvailable()) {
            echo json_encode(array(
                'code' => -2,
                'msg'  => 'AI 对话模型未配置，无法使用 AI 提取关键词。请先到【AI 开放平台 → 模型管理】中添加并启用一个对话模型。'
            ));
            return;
        }

        set_time_limit(120);

        $keywords = AiService::extractKeywords($title, $content);
        $dec      = AiService::generateDec($title, $content);

        $isFallback = !AiService::isChatAvailable();

        echo json_encode(array(
            'code'       => 0,
            'data'       => array(
                'keywords' => $keywords,
                'dec'      => $dec,
            ),
            'isFallback' => $isFallback,
            'msg'        => $isFallback ? 'AI未配置，使用本地规则生成' : '生成成功',
        ), JSON_UNESCAPED_UNICODE);
    }

    /**
     * AI 匹配商品
     * POST: index.php?r=manage/ai/matchGoods
     */
    public function matchGoods()
    {
        $this->checkManageSession();
        header('Content-Type: application/json; charset=utf-8');

        $title    = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content  = isset($_POST['content']) ? $_POST['content'] : '';
        $platform = isset($_POST['platform']) ? trim($_POST['platform']) : 'taobao';

        if (empty($title)) {
            echo json_encode(array('code' => -1, 'msg' => '标题不能为空'));
            return;
        }

        // 大模型未配置：明确报错，提示站长先添加并启用模型
        if (!AiService::isChatAvailable()) {
            echo json_encode(array(
                'code' => -2,
                'msg'  => 'AI 对话模型未配置，无法使用 AI 匹配商品。请先到【AI 开放平台 → 模型管理】中添加并启用一个对话模型。'
            ));
            return;
        }

        set_time_limit(120);

        $result = AiService::matchGoodsByAi($title, $content, $platform);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * AI 发布文章助手 - 完整流程
     * 自动提取关键词、生成描述、匹配商品
     * POST: index.php?r=manage/ai/publishAssistant
     */
    public function publishAssistant()
    {
        $this->checkManageSession();
        header('Content-Type: application/json; charset=utf-8');

        $title    = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content  = isset($_POST['content']) ? $_POST['content'] : '';
        $platform = isset($_POST['platform']) ? trim($_POST['platform']) : 'taobao';
        $needGoods = isset($_POST['needGoods']) ? intval($_POST['needGoods']) : 1;

        if (empty($title)) {
            echo json_encode(array('code' => -1, 'msg' => '标题不能为空'));
            return;
        }

        // 大模型未配置：明确报错，提示站长先添加并启用模型
        if (!AiService::isChatAvailable()) {
            echo json_encode(array(
                'code' => -2,
                'msg'  => 'AI 对话模型未配置，无法使用 AI 发布助手。请先到【AI 开放平台 → 模型管理】中添加并启用一个对话模型。'
            ));
            return;
        }

        set_time_limit(180);

        $keywords = AiService::extractKeywords($title, $content);
        $dec      = AiService::generateDec($title, $content);
        $isFallback = !AiService::isChatAvailable();

        $goodsResult = array('code' => 0, 'items' => array(), 'keyword' => '');
        if ($needGoods) {
            $goodsResult = AiService::matchGoodsByAi($title, $content, $platform);
        }

        echo json_encode(array(
            'code'       => 0,
            'data'       => array(
                'keywords' => $keywords,
                'dec'      => $dec,
                'goods'    => $goodsResult,
            ),
            'isFallback' => $isFallback,
            'msg'        => $isFallback ? 'AI未配置，使用本地规则生成' : '生成成功',
        ), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 检查 AI 状态
     * GET: index.php?r=manage/ai/status
     */
    public function status()
    {
        $this->checkManageSession();
        header('Content-Type: application/json; charset=utf-8');

        $chatAvailable = AiService::isChatAvailable();
        $imageAvailable = (bool)AiService::getImageModelInfo();

        echo json_encode(array(
            'code' => 0,
            'data' => array(
                'chat'  => $chatAvailable,
                'image' => $imageAvailable,
            ),
        ), JSON_UNESCAPED_UNICODE);
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 统一的更新结果输出：
     * - AJAX 请求（编辑弹窗提交）：返回 JSON，由前端 toast 提示并刷新
     * - 原生表单提交（被浏览器整页跳转）：重定向回设置页，绝不输出裸 JSON
     */
    private function updateAjaxOrRedirect($result)
    {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result);
            return;
        }

        // 非 AJAX：重定向回设置页（数据已保存），避免整页显示 JSON
        $this->redirect('index.php?r=manage/ai/setting');
    }


    /**
     * 构建系统提示词
     */
    private static function buildSystemPrompt()
    {
        $config = AiService::loadConfig();
        if (!empty($config['ai_system_prompt'])) {
            return $config['ai_system_prompt'];
        }

        // 默认系统提示词
        return '你是 ZhiCms 站点的 AI 智能助手。你可以帮助站长：
1. 回答网站管理和内容创作相关的问题
2. 提供 SEO 优化建议
3. 协助编写和润色文章内容
4. 解答技术问题
请使用专业、友好的语气，用中文回答。';
    }

    /**
     * 将 Base64 图像保存为文件
     */
    private function saveBase64Image($base64)
    {
        $imageData = base64_decode($base64);
        if ($imageData === false) {
            return '';
        }

        $dir = date('Ym');
        $uploadDir = \BASE_PATH . 'upload/ai/' . $dir . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = 'ai_' . uniqid() . '.png';
        $filePath = $uploadDir . $fileName;

        if (file_put_contents($filePath, $imageData)) {
            return '/upload/ai/' . $dir . '/' . $fileName;
        }

        return '';
    }
}
