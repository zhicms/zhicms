<?php
namespace plugins\miniapp;

use ZhiCms\base\PluginManager;
use app\common\ConfigStore;

/**
 * 小程序 & App 插件后台配置（移动端综合管理）
 *
 * 三个独立设置页（通过 view 参数区分）：
 *   - 默认（无 view）= 自营商城 · 微信支付 V3 配置（存 miniapp 插件 config）
 *   - view=mobile = 移动端设置（聚合系统设置，存 site 配置）
 *   - view=ai     = 小程序&App：导购/主题色/默认角色/令牌（存 aichat 配置）+ 一键打包（原 wxapp_packer）
 */
class Setting
{
    private $meta;

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
            $opts = array(
                'http' => array(
                    'method' => "GET",
                    'timeout' => 5,
                    'header' => "User-Agent: miniapp_packer\r\n"
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
            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new \Exception('Invalid response format');
            }
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
            throw new \Exception('无法获取下载地址：' . $e->getMessage());
        }
    }

    /**
     * 顶部视图切换导航
     */
    private function navTabs($cur)
    {
        $base = 'index.php?r=manage/plugin/setting&alias=miniapp';
        $tabs = array(
            ''        => '微信支付设置',
            'mobile'  => '移动端设置',
            'ai'      => '小程序 & App',
            'pack'    => '一键打包',
            'upgrade' => 'App 升级',
        );
        $html = '<ul class="nav nav-tabs mb-3" style="border-bottom:2px solid #e3e6f0;">';
        foreach ($tabs as $k => $label) {
            $url = $k === '' ? $base : $base . '&view=' . $k;
            $active = $k === $cur ? ' active' : '';
            $html .= '<li class="nav-item"><a class="nav-link' . $active . '" href="' . $url . '" style="border:none;' . ($k === $cur ? 'color:#4e73df;font-weight:600;border-bottom:2px solid #4e73df!important;margin-bottom:-2px;' : 'color:#5a5c69;') . '">' . $label . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * 渲染配置表单（按 view 分三页）
     */
    public function view()
    {
        $view = $_GET['view'] ?? '';
        $csrf = $_SESSION['csrf_token'] ?? '';

        $pay   = PluginManager::getConfig('miniapp');
        $pay   = is_array($pay) ? $pay : array();
        $site  = ConfigStore::load('site');
        $site  = is_array($site) ? $site : array();
        $aichat= ConfigStore::load('aichat');
        $aichat= is_array($aichat) ? $aichat : array();

        $pack  = isset($pay['pack']) && is_array($pay['pack']) ? $pay['pack'] : array();
        try {
            $updateUrls = static::fetchUpdateUrls();
        } catch (\Throwable $e) {
            $updateUrls = array('uniapp_url' => '', 'mp_url' => '');
        }
        $form = array_merge(array(
            'target_url' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://' . ($_SERVER['HTTP_HOST'] ?? ''),
            'appid'      => '',
            'app_name'   => '',
            'build_mode' => 'miniprogram',
        ), $pack);

        $v = function ($arr, $k, $d = '') {
            return isset($arr[$k]) ? htmlspecialchars($arr[$k], ENT_QUOTES) : $d;
        };

        // 清理过期打包文件
        try {
            $plugin = PluginManager::instance('miniapp');
            if ($plugin && method_exists($plugin, 'cleanupOld')) {
                $plugin->cleanupOld();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        ob_start();

        if ($view === 'mobile') {
            // ============ 移动端设置 ============
            echo $this->navTabs('mobile');
            ?>
            <form method="post" action="">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="save_section" value="mobile">
                <div class="card">
                    <div class="card-header">移动端设置</div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>移动端风格</label>
                            <select name="mobile_style" class="form-control">
                                <option value="super_search" <?php echo (empty($site['mobile_style']) || $site['mobile_style']=='super_search') ? 'selected' : ''; ?>>超级搜索（默认）</option>
                                <option value="tb_minishop" <?php echo isset($site['mobile_style']) && $site['mobile_style']=='tb_minishop' ? 'selected' : ''; ?>>小样种草机</option>
                                <option value="rt_xb" <?php echo isset($site['mobile_style']) && $site['mobile_style']=='rt_xb' ? 'selected' : ''; ?>>好单线报</option>
                            </select>
                            <small class="form-text text-muted">m 端首页（/m.html）展示的页面风格，存于系统 site 配置。</small>
                        </div>
                        <div class="form-group">
                            <label>M 端自动跳转</label>
                            <select name="mobile_redirect" class="form-control">
                                <option value="1" <?php echo (!empty($site['mobile_redirect'])) ? 'selected' : ''; ?>>开启</option>
                                <option value="0" <?php echo (empty($site['mobile_redirect'])) ? 'selected' : ''; ?>>关闭</option>
                            </select>
                            <small class="form-text text-muted">开启后手机访问跳 m 端、电脑访问电脑版；关闭则前端自适应。</small>
                        </div>
                    </div>
                </div>
                <div class="form-group border-top pt-3 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save"></i> 保存移动端设置</button>
                </div>
            </form>
            <?php
            return ob_get_clean();
        }

        if ($view === 'ai') {
            // ============ 小程序 & App（仅导购设置） ============
            echo $this->navTabs('ai');
            ?>
            <form method="post" action="">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="save_section" value="ai">
                <div class="card">
                    <div class="card-header">小程序导购（AI 对话）</div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>开启 AI 对话导购</label>
                            <select name="aichat_enabled" class="form-control">
                                <option value="1" <?php echo (!empty($aichat['enabled'])) ? 'selected' : ''; ?>>开启</option>
                                <option value="0" <?php echo (empty($aichat['enabled'])) ? 'selected' : ''; ?>>关闭</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>小程序主题色</label>
                            <input type="text" name="aichat_theme_color" class="form-control" value="<?php echo $v($aichat, 'theme_color', '#6C63FF'); ?>" placeholder="#6C63FF">
                        </div>
                        <div class="form-group">
                            <label>默认 AI 角色</label>
                            <select name="aichat_default_role" class="form-control">
                                <option value="shopping" <?php echo isset($aichat['default_role']) && $aichat['default_role']=='shopping' ? 'selected' : ''; ?>>🛍️ 购物顾问</option>
                                <option value="fashion" <?php echo isset($aichat['default_role']) && $aichat['default_role']=='fashion' ? 'selected' : ''; ?>>👗 时尚达人</option>
                                <option value="tech" <?php echo isset($aichat['default_role']) && $aichat['default_role']=='tech' ? 'selected' : ''; ?>>💻 数码专家</option>
                                <option value="food" <?php echo isset($aichat['default_role']) && $aichat['default_role']=='food' ? 'selected' : ''; ?>>🍜 美食家</option>
                                <option value="assistant" <?php echo isset($aichat['default_role']) && $aichat['default_role']=='assistant' ? 'selected' : ''; ?>>🤖 智能助手</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>小程序访问令牌（可选）</label>
                            <input type="text" name="aichat_token" class="form-control" value="<?php echo $v($aichat, 'token'); ?>" placeholder="留空则不校验">
                            <small class="form-text text-muted">存于 aichat 配置，前端读取不变。</small>
                        </div>
                    </div>
                </div>
                <div class="form-group border-top pt-3 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save"></i> 保存导购设置</button>
                </div>
            </form>
            <?php
            return ob_get_clean();
        }

        if ($view === 'pack') {
            // ============ 一键打包（独立页） ============
            echo $this->navTabs('pack');
            ?>
            <div class="card">
                <div class="card-header">小程序一键打包</div>
                <div class="card-body">
                    <form method="post" action="" id="packForm" data-no-ajax="1">
                        <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="build">
                        <input type="hidden" name="return_json" value="1">
                        <div class="form-group">
                            <label>打包输出格式</label>
                            <div class="form-control">
                                <label class="radio-label">
                                    <input type="radio" name="build_mode" value="miniprogram" <?php echo ($form['build_mode'] === 'miniprogram') ? 'checked' : ''; ?>>
                                    <span><strong>小程序源码</strong>（推荐，替换网址/AppID/名称后下载，微信开发者工具直接打开上传）</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="build_mode" value="uniapp" <?php echo ($form['build_mode'] === 'uniapp') ? 'checked' : ''; ?>>
                                    <span><strong>uniapp 源码</strong>（直接打包服务器源码下载，用 HBuilderX 二次开发，参数自行修改）</span>
                                </label>
                            </div>
                        </div>
                        <div id="packParamsFields">
                            <div class="form-group">
                                <label>HTTPS 后端网址</label>
                                <input type="text" name="target_url" class="form-control" value="<?php echo htmlspecialchars($form['target_url']); ?>" placeholder="https://www.example.com">
                            </div>
                            <div class="form-group">
                                <label>微信 AppID</label>
                                <input type="text" name="appid" class="form-control" value="<?php echo htmlspecialchars($form['appid']); ?>" placeholder="wx + 16位字母数字" maxlength="18">
                            </div>
                            <div class="form-group">
                                <label>小程序名称</label>
                                <input type="text" name="app_name" class="form-control" value="<?php echo htmlspecialchars($form['app_name']); ?>" placeholder="如：好价精选" maxlength="32">
                            </div>
                        </div>
                        <div class="form-group border-top pt-3">
                            <button type="button" class="btn btn-primary btn-lg px-5" id="wxappPackerBtn"><i class="fas fa-bolt"></i> 一键打包并下载</button>
                        </div>
                    </form>
                </div>
            </div>

            <style>
            .info-box{padding:10px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:13px;color:#1e40af;}
            .radio-label{display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;color:#475569;padding:4px 0;}
            #lastDownloadLink{margin-left:4px;}
            </style>

            <script>
            (function () {
                var paramsFields = document.getElementById('packParamsFields');
                var rUni = document.querySelector('input[name="build_mode"][value="uniapp"]');
                function toggleParams() {
                    if (!paramsFields || !rUni) return;
                    paramsFields.style.display = rUni.checked ? 'none' : '';
                }
                var rMini = document.querySelector('input[name="build_mode"][value="miniprogram"]');
                if (rMini) rMini.addEventListener('change', toggleParams);
                if (rUni)  rUni.addEventListener('change', toggleParams);
                toggleParams();

                function notify(msg, type) {
                    if (typeof showToast === 'function') { showToast(msg, type || 'info'); return; }
                    if (window.success && type === 'success') { window.success(msg); return; }
                    if (window.error && type === 'danger') { window.error(msg); return; }
                    alert(msg);
                }

                var form = document.getElementById('packForm');
                var btn = document.getElementById('wxappPackerBtn');
                if (!form || !btn) return;

                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var fd = new FormData(form);
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spin"></span>打包中，请稍候...';

                    var url = form.getAttribute('action') || window.location.href;
                    var $ = window.jQuery;

                    function handleResponse(text) {
                        var data = null;
                        try { data = JSON.parse(text); } catch (_) { data = null; }
                        if (data && data.status === 'y') {
                            if (data.download_url) {
                                window.open(data.download_url, '_blank');
                                notify(data.info || '打包成功！已开始下载', 'success');
                            } else {
                                notify(data.info || '保存成功', 'success');
                            }
                        } else {
                            notify((data && data.info) ? data.info : '操作失败：返回数据异常', 'danger');
                        }
                    }

                    if ($ && $.ajax) {
                        $.ajax({
                            url: url, type: 'POST', data: fd,
                            processData: false, contentType: false,
                            dataType: 'text', timeout: 300000
                        }).done(handleResponse).fail(function (xhr) {
                            notify('网络错误：' + (xhr.status || '无法连接'), 'danger');
                        }).always(function () {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-bolt"></i> 一键打包并下载';
                        });
                    } else {
                        // 原生 fetch 兜底（不依赖 jQuery）
                        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
                            .then(function (r) { return r.text(); })
                            .then(function (t) { handleResponse(t); })
                            .catch(function (err) { notify('网络错误：' + err.message, 'danger'); })
                            .finally(function () {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-bolt"></i> 一键打包并下载';
                            });
                    }
                });
            })();
            </script>
            <?php
            return ob_get_clean();
        }

        if ($view === 'upgrade') {
            // ============ App 在线升级设置（安卓，整包 + 热更新） ============
            $upg = isset($pay['upgrade']) && is_array($pay['upgrade']) ? $pay['upgrade'] : array();
            $upg = array_merge(array(
                'enabled'     => 1,
                'versionCode' => 100,
                'versionName' => '1.0.0',
                'apk_url'     => '',
                'apk_force'   => 0,
                'wgt_md5'     => '',
                'wgt_url'     => '',
                'changelog'   => '',
            ), $upg);
            echo $this->navTabs('upgrade');
            ?>
            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="save_section" value="upgrade">
                <div class="card">
                    <div class="card-header">App 在线升级（安卓 · 整包 + 热更新）</div>
                    <div class="card-body">
                        <div class="alert alert-info" style="font-size:13px;">
                            前端启动时调 <code>index.php?r=api/app/check</code> 检查更新。<br>
                            <b>大版本更新（APK 整包）</b>：原生/权限/大版本改动，需用户确认安装；<b>受「升级通道开关」控制</b>。<br>
                            <b>热更新（WGT）</b>：页面/逻辑小版本，依包 <b>md5</b> 对比自动静默更新，<b>不受开关控制</b>。
                        </div>

                        <div class="form-group">
                            <label>升级通道开关（控制大版本整包升级是否开启；热更新始终生效）</label>
                            <select name="upg_enabled" class="form-control">
                                <option value="1" <?php echo !empty($upg['enabled']) ? 'selected' : ''; ?>>开启（允许整包升级）</option>
                                <option value="0" <?php echo empty($upg['enabled']) ? 'selected' : ''; ?>>关闭（整包升级暂停）</option>
                            </select>
                        </div>

                        <h6 class="mt-3" style="color:#4e73df;">① 大版本更新（整包 APK，受通道开关控制）</h6>
                        <div class="form-group">
                            <label>大版本号（versionCode，整包版本号，需大于当前已装版本）</label>
                            <input type="number" name="upg_versionCode" class="form-control" value="<?php echo htmlspecialchars($upg['versionCode']); ?>">
                        </div>
                        <div class="form-group">
                            <label>大版本名（versionName，展示版本名，如 1.0.1）</label>
                            <input type="text" name="upg_versionName" class="form-control" value="<?php echo htmlspecialchars($upg['versionName']); ?>" placeholder="1.0.1">
                        </div>
                        <div class="form-group">
                            <label>整包 APK（可直接上传，自动返回地址）</label>
                            <input type="file" name="upg_apk_file" class="form-control" accept=".apk">
                            <?php if (!empty($upg['apk_url'])): ?>
                            <small class="text-success">当前文件：<a href="<?php echo htmlspecialchars($upg['apk_url']); ?>" target="_blank"><?php echo htmlspecialchars($upg['apk_url']); ?></a></small>
                            <?php endif; ?>
                            <input type="text" name="upg_apk_url" class="form-control mt-2" value="<?php echo htmlspecialchars($upg['apk_url']); ?>" placeholder="或手动填写完整 URL">
                        </div>
                        <div class="form-group">
                            <label>大版本是否强制更新</label>
                            <select name="upg_apk_force" class="form-control">
                                <option value="1" <?php echo !empty($upg['apk_force']) ? 'selected' : ''; ?>>强制（不更新无法使用）</option>
                                <option value="0" <?php echo empty($upg['apk_force']) ? 'selected' : ''; ?>>可选（可稍后）</option>
                            </select>
                        </div>

                        <h6 class="mt-4" style="color:#4e73df;">② 热更新（WGT 静默更新，依 md5 自动）</h6>
                        <div class="form-group">
                            <label>热更包 WGT（可直接上传，自动计算 md5 并返回地址）</label>
                            <input type="file" name="upg_wgt_file" class="form-control" accept=".wgt">
                            <?php if (!empty($upg['wgt_url'])): ?>
                            <small class="text-success">当前文件：<a href="<?php echo htmlspecialchars($upg['wgt_url']); ?>" target="_blank"><?php echo htmlspecialchars($upg['wgt_url']); ?></a></small>
                            <?php endif; ?>
                            <input type="text" name="upg_wgt_md5" class="form-control mt-2" value="<?php echo htmlspecialchars($upg['wgt_md5']); ?>" placeholder="上传 WGT 后自动填充；或手动填写 md5">
                        </div>
                        <div class="form-group">
                            <label>热更 WGT 下载地址（.wgt 文件 URL）</label>
                            <input type="text" name="upg_wgt_url" class="form-control" value="<?php echo htmlspecialchars($upg['wgt_url']); ?>" placeholder="https://v5.zhicms.cc/upgrade/app.wgt">
                        </div>

                        <h6 class="mt-4" style="color:#4e73df;">③ 更新日志</h6>
                        <div class="form-group">
                            <label>更新内容说明（大版本升级弹窗展示，每行一条）</label>
                            <textarea name="upg_changelog" class="form-control" rows="4" placeholder="修复 xxx&#10;优化 xxx&#10;新增 xxx"><?php echo htmlspecialchars($upg['changelog']); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="form-group border-top pt-3 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save"></i> 保存升级配置</button>
                </div>
            </form>
            <?php
            return ob_get_clean();
        }

        // ============ 默认：微信支付 V3 配置 ============
        echo $this->navTabs('');
        ?>
        <form method="post" action="">
            <input type="hidden" name="_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="save_section" value="pay">
            <div class="card">
                <div class="card-header">自营商城 · 微信支付 V3 配置</div>
                <div class="card-body">
                    <div class="form-group">
                        <label>微信 AppID</label>
                        <input type="text" name="wx_appid" class="form-control" value="<?php echo $v($pay, 'wx_appid'); ?>" placeholder="wxxxxxxxxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label>微信商户号 MCHID</label>
                        <input type="text" name="wx_mchid" class="form-control" value="<?php echo $v($pay, 'wx_mchid'); ?>" placeholder="1900000000">
                    </div>
                    <div class="form-group">
                        <label>商户 APIv3 密钥</label>
                        <input type="text" name="wx_api_v3_key" class="form-control" value="<?php echo $v($pay, 'wx_api_v3_key'); ?>" placeholder="32位密钥">
                    </div>
                    <div class="form-group">
                        <label>商户证书序列号</label>
                        <input type="text" name="wx_serial_no" class="form-control" value="<?php echo $v($pay, 'wx_serial_no'); ?>" placeholder="证书序列号">
                    </div>
                    <div class="form-group">
                        <label>余额支付</label>
                        <select name="balance_enable" class="form-control">
                            <option value="1" <?php echo $v($pay, 'balance_enable', '1') == '1' ? 'selected' : ''; ?>>开启</option>
                            <option value="0" <?php echo $v($pay, 'balance_enable', '1') == '0' ? 'selected' : ''; ?>>关闭</option>
                        </select>
                    </div>
                    <p class="text-muted">证书文件请放置于站点根目录 cert/ 下：apiclient_cert.pem 与 apiclient_key.pem</p>
                </div>
            </div>
            <div class="form-group border-top pt-3 mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save"></i> 保存支付设置</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * 保存配置：按 save_section 分块写回，build 动作触发打包
     */
    public function save($post)
    {
        // 读取现有配置（保留其他区块字段，避免被清空）
        $pay    = PluginManager::getConfig('miniapp');
        $pay    = is_array($pay) ? $pay : array();
        $site   = ConfigStore::load('site');
        $site   = is_array($site) ? $site : array();
        $aichat = ConfigStore::load('aichat');
        $aichat = is_array($aichat) ? $aichat : array();

        $section = $post['save_section'] ?? '';

        // 打包分支（独立 pack 页，顺带保存导购字段默认值）
        if (!empty($post['action']) && $post['action'] === 'build') {
            $section = 'pack';
        }

        if ($section === 'pay') {
            $pay['wx_appid']       = trim($post['wx_appid'] ?? '');
            $pay['wx_mchid']       = trim($post['wx_mchid'] ?? '');
            $pay['wx_api_v3_key']  = trim($post['wx_api_v3_key'] ?? '');
            $pay['wx_serial_no']   = trim($post['wx_serial_no'] ?? '');
            $pay['balance_enable'] = isset($post['balance_enable']) ? intval($post['balance_enable']) : 1;
            PluginManager::setConfig('miniapp', $pay);
        } elseif ($section === 'mobile') {
            $site['mobile_style']    = trim($post['mobile_style'] ?? '');
            $site['mobile_redirect'] = isset($post['mobile_redirect']) && $post['mobile_redirect'] ? 1 : 0;
            ConfigStore::save('site', $site);
            ConfigStore::clearCache('site');
            $this->syncSiteFile($site);
        } elseif ($section === 'ai') {
            $aichat['enabled']      = !empty($post['aichat_enabled']);
            $aichat['theme_color']  = isset($post['aichat_theme_color']) ? trim($post['aichat_theme_color']) : '#6C63FF';
            $aichat['default_role'] = isset($post['aichat_default_role']) ? trim($post['aichat_default_role']) : 'shopping';
            $aichat['token']        = isset($post['aichat_token']) ? trim($post['aichat_token']) : '';
            ConfigStore::save('aichat', $aichat);
            ConfigStore::clearCache('aichat');
        } elseif ($section === 'pack') {
            // 打包页：保存打包默认值，供下次打开预填
            $pay['pack'] = array(
                'target_url' => rtrim(trim($post['target_url'] ?? ''), '/'),
                'appid'      => trim($post['appid'] ?? ''),
                'app_name'   => trim($post['app_name'] ?? ''),
                'build_mode' => in_array(($post['build_mode'] ?? 'miniprogram'), array('uniapp', 'miniprogram'), true)
                    ? $post['build_mode'] : 'miniprogram',
            );
            PluginManager::setConfig('miniapp', $pay);
        } elseif ($section === 'upgrade') {
            // App 升级配置（整包 + 热更新），存插件 config['upgrade']
            $old = isset($pay['upgrade']) && is_array($pay['upgrade']) ? $pay['upgrade'] : array();

            // 处理 APK 上传（若上传了新文件，则覆盖 apk_url）
            $apkUrl = trim($post['upg_apk_url'] ?? '');
            if (!empty($_FILES['upg_apk_file']) && $_FILES['upg_apk_file']['error'] === UPLOAD_ERR_OK) {
                $up = $this->saveUpgradeFile($_FILES['upg_apk_file'], 'apk');
                if ($up) {
                    $apkUrl = $up;
                }
            }
            // 处理 WGT 上传（若上传了新文件，则覆盖 wgt_url 并自动计算 md5）
            $wgtUrl = trim($post['upg_wgt_url'] ?? '');
            $wgtMd5 = strtolower(trim($post['upg_wgt_md5'] ?? ''));
            if (!empty($_FILES['upg_wgt_file']) && $_FILES['upg_wgt_file']['error'] === UPLOAD_ERR_OK) {
                $up = $this->saveUpgradeFile($_FILES['upg_wgt_file'], 'wgt');
                if ($up) {
                    $wgtUrl = $up;
                    // 自动计算上传 WGT 的 md5，回写配置（与前端热更 md5 对比逻辑一致）
                    $real = \BASE_PATH . 'public/upgrade/' . basename($up);
                    if (is_file($real)) {
                        $wgtMd5 = md5_file($real);
                    }
                }
            }

            $pay['upgrade'] = array(
                'enabled'     => !empty($post['upg_enabled']) ? 1 : 0,
                'versionCode' => intval($post['upg_versionCode'] ?? 100),
                'versionName' => trim($post['upg_versionName'] ?? ''),
                'apk_url'     => $apkUrl,
                'apk_force'   => !empty($post['upg_apk_force']) ? 1 : 0,
                'wgt_md5'     => $wgtMd5,
                'wgt_url'     => $wgtUrl,
                'changelog'   => trim($post['upg_changelog'] ?? ''),
            );
            PluginManager::setConfig('miniapp', $pay);
        } else {
            // 兜底：整页保存（兼容旧调用）
            $pay['wx_appid']       = trim($post['wx_appid'] ?? '');
            $pay['wx_mchid']       = trim($post['wx_mchid'] ?? '');
            $pay['wx_api_v3_key']  = trim($post['wx_api_v3_key'] ?? '');
            $pay['wx_serial_no']   = trim($post['wx_serial_no'] ?? '');
            $pay['balance_enable'] = isset($post['balance_enable']) ? intval($post['balance_enable']) : 1;
            $pay['pack'] = array(
                'target_url' => rtrim(trim($post['target_url'] ?? ''), '/'),
                'appid'      => trim($post['appid'] ?? ''),
                'app_name'   => trim($post['app_name'] ?? ''),
                'build_mode' => in_array(($post['build_mode'] ?? 'miniprogram'), array('uniapp', 'miniprogram'), true)
                    ? $post['build_mode'] : 'miniprogram',
            );
            PluginManager::setConfig('miniapp', $pay);
            $site['mobile_style']    = trim($post['mobile_style'] ?? '');
            $site['mobile_redirect'] = isset($post['mobile_redirect']) && $post['mobile_redirect'] ? 1 : 0;
            ConfigStore::save('site', $site);
            ConfigStore::clearCache('site');
            $this->syncSiteFile($site);
            $aichat['enabled']      = !empty($post['aichat_enabled']);
            $aichat['theme_color']  = isset($post['aichat_theme_color']) ? trim($post['aichat_theme_color']) : '#6C63FF';
            $aichat['default_role'] = isset($post['aichat_default_role']) ? trim($post['aichat_default_role']) : 'shopping';
            $aichat['token']        = isset($post['aichat_token']) ? trim($post['aichat_token']) : '';
            ConfigStore::save('aichat', $aichat);
            ConfigStore::clearCache('aichat');
        }

        // 打包动作
        if (!empty($post['action']) && $post['action'] === 'build') {
            $packParams = array(
                'build_mode' => in_array(($post['build_mode'] ?? 'miniprogram'), array('uniapp', 'miniprogram'), true)
                    ? $post['build_mode'] : 'miniprogram',
                'target_url' => rtrim(trim($post['target_url'] ?? ''), '/'),
                'appid'      => trim($post['appid'] ?? ''),
                'app_name'   => trim($post['app_name'] ?? ''),
            );
            if ($packParams['build_mode'] === 'miniprogram') {
                if ($packParams['target_url'] === '') throw new \Exception('请填写后端网址（HTTPS）');
                if ($packParams['appid'] === '')      throw new \Exception('请填写微信 AppID');
                if ($packParams['app_name'] === '')   throw new \Exception('请填写小程序名称');
                if (stripos($packParams['target_url'], 'https://') !== 0) {
                    throw new \Exception('小程序源码模式要求后端网址必须为 HTTPS');
                }
            }
            $plugin = PluginManager::instance('miniapp');
            if (!$plugin || !method_exists($plugin, 'build')) {
                throw new \Exception('插件未正确安装');
            }
            $result = $plugin->build($packParams['build_mode'], $packParams);
            // 返回完整配置 + 临时下载字段，避免 PluginController 剥离后得到空数组而把配置清空
            return array_merge($pay, array(
                '_download_url'  => $result['download_url'] ?? '',
                '_download_file' => $result['file_name'] ?? '',
                '_download_path' => $result['zip_path'] ?? '',
            ));
        }

        // 普通保存：返回完整 miniapp 配置，供 PluginController::setConfig 覆盖写入
        return $pay;
    }

    /**
     * 同步 site 配置写回 data/config/siteconfig.php（供 bootstrap 读取）
     */
    private function syncSiteFile($site)
    {
        try {
            $file = \CONFIG_PATH . 'siteconfig.php';
            $export = var_export($site, true);
            @file_put_contents($file, "<?php\n\$Siteinfo = " . $export . ";\nreturn \$Siteinfo;\n");
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * 保存 App 升级包文件到 public/upgrade/，返回可访问 URL（相对站点根）
     * @param array  $file   $_FILES[xxx] 元素
     * @param string $type   'apk' | 'wgt'（仅作校验扩展名，可扩展）
     * @return string|false  成功返回 URL（如 /upgrade/xxx.apk），失败返回 false
     */
    private function saveUpgradeFile($file, $type)
    {
        try {
            $name = isset($file['name']) ? basename($file['name']) : '';
            $tmp  = isset($file['tmp_name']) ? $file['tmp_name'] : '';
            if ($name === '' || !is_uploaded_file($tmp)) {
                return false;
            }
            // 仅允许 apk / wgt 扩展名，避免被上传执行文件
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, array('apk', 'wgt'), true)) {
                return false;
            }
            // 用类型 + 原扩展名生成稳定文件名（保留历史文件，避免覆盖正在下载的旧包）
            $safeName = $type . '.' . $ext;
            $dir = \BASE_PATH . 'public/upgrade/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $dest = $dir . $safeName;
            if (!@move_uploaded_file($tmp, $dest)) {
                return false;
            }
            // 站点根 URL 拼相对地址
            $base = rtrim($this->getSiteUrl(), '/');
            return $base . '/upgrade/' . $safeName;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** 取站点根 URL（供上传文件拼地址） */
    private function getSiteUrl()
    {
        $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '127.0.0.1';
        return $proto . '://' . $host . '/';
    }
}
