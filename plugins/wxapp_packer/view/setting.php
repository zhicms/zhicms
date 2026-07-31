<div class="content-box">
    <div class="content-box-header">
        <h3>小程序打包工具 <span style="font-weight:normal;color:#64748b;font-size:12px;">（ZhiCms 配套多端小程序一键打包）</span></h3>
    </div>
    <div class="content-box-content">
        <form id="wxappPackerForm" method="post" action="">
            <input type="hidden" name="_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="action" id="wxappPackerAction" value="build">
            <input type="hidden" name="return_json" value="1">

            <div class="form-group">
                <label class="form-label">打包输出格式 <span class="req">*</span></label>
                <div class="form-control">
                    <label class="radio-label">
                        <input type="radio" name="build_mode" value="miniprogram" id="mode_miniprogram" <?php echo ($form['build_mode'] === 'miniprogram') ? 'checked' : ''; ?>>
                        <span><strong>小程序源码</strong>（推荐，替换网址/AppID/名称后下载，微信开发者工具直接打开上传）</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="build_mode" value="uniapp" id="mode_uniapp" <?php echo ($form['build_mode'] === 'uniapp') ? 'checked' : ''; ?>>
                        <span><strong>uniapp 源码</strong>（直接打包服务器源码下载，用 HBuilderX 二次开发，参数自行修改）</span>
                    </label>
                </div>
            </div>

            <div id="paramsFields">
            <div class="form-group">
                <label class="form-label">HTTPS 后端网址 <span class="req">*</span></label>
                <div class="form-control">
                    <input type="text" name="target_url" value="<?php echo htmlspecialchars($form['target_url']); ?>" placeholder="https://www.example.com" style="width: 480px;">
                    <span class="form-help">用户网站根地址（结尾不要带 /），小程序将从此地址拉取商品、AI、用户等数据。微信小程序必须 HTTPS，且需在微信公众平台配置「request合法域名」和「业务域名」。</span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">微信 AppID <span class="req">*</span></label>
                <div class="form-control">
                    <input type="text" name="appid" value="<?php echo htmlspecialchars($form['appid']); ?>" placeholder="wx + 16位字母数字" style="width: 360px;" maxlength="18">
                    <span class="form-help">在 <a href="https://mp.weixin.qq.com/" target="_blank" rel="noopener">微信公众平台</a> → 开发管理 → 开发设置 中获取</span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">小程序名称 <span class="req">*</span></label>
                <div class="form-control">
                    <input type="text" name="app_name" value="<?php echo htmlspecialchars($form['app_name']); ?>" placeholder="如：好价精选" style="width: 360px;" maxlength="32">
                    <span class="form-help">显示在小程序顶部导航栏的标题，建议 4-16 个字符</span>
                </div>
            </div>
            </div><!-- /paramsFields -->

            <!-- uniapp_url 和 mp_url 改为从服务端拉取，前端隐藏相关输入项 -->
            <input type="hidden" name="uniapp_url" value="">
            <input type="hidden" name="mp_url" value="">

            <div class="form-group" id="lastDownloadWrap" style="display:none;">
                <label class="form-label">打包下载</label>
                <div class="form-control">
                    <div class="info-box">
                        打包完成！文件：<a id="lastDownloadLink" href="#" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;">点击下载</a>
                        <span class="form-help" style="display:block;margin-top:4px;">文件在服务器上保留 1 小时，过期自动清理</span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" id="wxappPackerBtn" class="btn btn-primary">⚡ 一键打包并下载</button>
                <button type="button" id="wxappSaveDefaultBtn" class="btn btn-secondary" style="margin-left:12px;">仅保存默认值</button>
            </div>
        </form>
    </div>
</div>

<div class="content-box" style="margin-top: 20px;">
    <div class="content-box-header">
        <h3>使用说明</h3>
    </div>
    <div class="content-box-content">
        <div style="font-size:13px;color:#475569;line-height:1.8;">
            <p><strong>方式一：小程序源码（推荐给最终用户）</strong></p>
            <ol style="padding-left:22px;margin:4px 0 16px;">
                <li>选择「小程序源码」模式，填写 HTTPS 网址、AppID、小程序名称后点击「一键打包并下载」</li>
                <li>系统自动从远程下载编译好的小程序源码，替换为你填写的参数后打包成 ZIP</li>
                <li>解压下载的 ZIP，用 <strong>微信开发者工具</strong> → 导入项目 → 选择解压后的文件夹</li>
                <li>AppID 已替换为你填的，确认无误后点击「上传」即可</li>
            </ol>

            <p><strong>方式二：uniapp 源码（推荐给二次开发者）</strong></p>
            <ol style="padding-left:22px;margin:4px 0 16px;">
                <li>选择「uniapp 源码」模式，点击「一键打包并下载」</li>
                <li>系统直接从远程下载 uniapp 源码 ZIP，无需填写参数（拿到源码后自行修改）</li>
                <li>解压 ZIP，用 <strong>HBuilderX</strong> → 打开目录，执行 <code>npm install</code> 后即可运行</li>
            </ol>

            <p><strong>⚠️ 微信公众平台配置（必做，否则小程序无网络）：</strong></p>
            <ul style="padding-left:22px;margin:4px 0;">
                <li>「开发管理 → 开发设置 → 服务器域名」中配置 <strong>request合法域名</strong> = <code>你的HTTPS网址</code></li>
                <li>「开发管理 → 开发设置 → 业务域名」中配置 <strong>业务域名</strong> = <code>你的HTTPS网址</code>（用于 WebView 页面跳转）</li>
            </ul>
        </div>
    </div>
</div>

<style>
.content-box{background:#fff;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:16px;}
.content-box-header{padding:12px 16px;border-bottom:1px solid #e2e8f0;background:#f8fafc;border-radius:6px 6px 0 0;}
.content-box-header h3{margin:0;font-size:14px;font-weight:600;color:#1e293b;}
.content-box-content{padding:20px;}
.form-group{margin-bottom:20px;display:flex;gap:20px;}
.form-label{width:160px;font-weight:600;color:#334155;padding-top:8px;font-size:13px;}
.form-label .req{color:#ef4444;}
.form-control{flex:1;}
.form-control input[type="text"]{padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;color:#1e293b;background:#fff;transition:border-color .2s;}
.form-control input:focus{border-color:#3b82f6;outline:none;box-shadow:0 0 0 2px rgba(59,130,246,.1);}
.form-help{display:block;margin-top:6px;font-size:12px;color:#64748b;}
.form-help a{color:#2563eb;text-decoration:none;}
.radio-label{display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;color:#475569;padding:4px 0;}
.radio-label input[type="radio"]{width:16px;height:16px;margin-top:2px;cursor:pointer;}
.form-actions{margin-top:24px;padding-top:20px;border-top:1px solid #e2e8f0;display:flex;gap:12px;align-items:center;}
.btn{padding:8px 20px;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;}
.btn-primary{background:#2563eb;color:#fff;}
.btn-primary:hover{background:#1d4ed8;}
.btn-primary:disabled{background:#93c5fd;cursor:not-allowed;}
.btn-secondary{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;}
.btn-secondary:hover{background:#e2e8f0;}
.warn-box{padding:12px 16px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:6px;font-size:13px;line-height:1.6;margin-bottom:20px;}
.warn-box code{background:#fff;padding:2px 4px;border-radius:3px;color:#9a3412;}
.info-box{padding:10px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:13px;color:#1e40af;}
.btn-primary .spin{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite;vertical-align:-2px;margin-right:6px;}
@keyframes spin{to{transform:rotate(360deg);}}
</style>

<script>
(function () {
    'use strict';
    var $ = window.jQuery;
    if (!$) return;

    var form       = document.getElementById('wxappPackerForm');
    var actionInp  = document.getElementById('wxappPackerAction');
    var submitBtn  = document.getElementById('wxappPackerBtn');
    var saveDefBtn = document.getElementById('wxappSaveDefaultBtn');
    var dlWrap     = document.getElementById('lastDownloadWrap');
    var dlLink     = document.getElementById('lastDownloadLink');
    var originalBtnText = submitBtn ? submitBtn.innerHTML : '';

    // ======= 根据打包模式显示/隐藏参数输入框 =======
    var paramsFields = document.getElementById('paramsFields');
    var radioMini = document.getElementById('mode_miniprogram');
    var radioUni  = document.getElementById('mode_uniapp');
    function toggleParams() {
        if (!paramsFields) return;
        var isUniapp = radioUni && radioUni.checked;
        paramsFields.style.display = isUniapp ? 'none' : '';
    }
    if (radioMini) radioMini.addEventListener('change', toggleParams);
    if (radioUni)  radioUni.addEventListener('change', toggleParams);
    toggleParams(); // 初始化

    // ======= 「仅保存默认值」按钮：不用拦截，直接走原生统一 AJAX =======
    if (saveDefBtn) {
        saveDefBtn.addEventListener('click', function () {
            if (actionInp) actionInp.value = 'save_only';
            // 用原生 click 触发 submit（外层 setting.html 已统一拦截 AJAX）
            var ev = document.createEvent('Event');
            ev.initEvent('submit', true, true);
            form.dispatchEvent(ev);
            // 恢复 action（下次默认打包）
            setTimeout(function () {
                if (actionInp) actionInp.value = 'build';
            }, 100);
        });
    }

    // ======= 「一键打包并下载」：AJAX请求 + window.open 触发下载 =======
    // 用 useCapture=true 优先拦截表单 submit（在外层拦截器之前），自己处理打包下载流程。
    if (form && actionInp) {
        form.addEventListener('submit', function (e) {
            if (actionInp && actionInp.value !== 'build') {
                // 不是打包动作，交给外层拦截器
				return;
			}

            // 是打包动作，我们这里拦截处理
            e.preventDefault();
			e.stopPropagation();

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spin"></span>打包中，请稍候...';
            }

            var formData = new FormData(form);
            $.ajax({
                url: form.action || window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                timeout: 300000 // 打包最多等 5 分钟
            }).done(function (data) {
                if (data && data.status === 'y') {
                    // 打包成功，显示下载链接并自动触发下载
                    if (data.download_url) {
                        // 直接在新窗口打开下载链接，触发浏览器下载对话框
                        window.open(data.download_url, '_blank');
                        
                        if (typeof window.success === 'function') {
                            window.success(data.info || '打包成功！已开始下载');
                        }
                    } else {
                        if (typeof window.success === 'function') {
                            window.success(data.info || '保存成功');
                        }
                    }
                } else {
                    if (typeof window.error === 'function') {
                        window.error((data && data.info) || '操作失败');
                    }
                }
            }).fail(function (xhr, status, err) {
                if (typeof window.error === 'function') {
                    window.error('网络错误：' + (err || status) + '，请稍候重试');
                }
            }).always(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
                // 恢复表单属性
                if (actionInp) actionInp.value = 'build';
            });
        }, true); // useCapture=true，抢在外层之前
    }
})();
</script>
