<?php
/**
 * Demo 插件设置表单（由 Setting::includeView('setting') 包含）
 * $cfg 由控制器传入（插件当前配置）
 */
$cfg = $cfg ?? array();
$siteName = $cfg['site_name'] ?? '';
$enableBanner = !empty($cfg['enable_banner']);
$token = $_SESSION['csrf_token'] ?? '';
?>
<form class="layui-form" method="post" action="">
    <input type="hidden" name="_token" value="<?php echo htmlspecialchars($token); ?>">
    <div class="layui-form-item">
        <label class="layui-form-label">站点名称</label>
        <div class="layui-input-block">
            <input type="text" name="site_name" value="<?php echo htmlspecialchars($siteName); ?>"
                   class="layui-input" placeholder="例如：ZhiCms Demo">
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">欢迎横幅</label>
        <div class="layui-input-block">
            <input type="checkbox" name="enable_banner" value="1" <?php echo $enableBanner ? 'checked' : ''; ?>
                   lay-skin="switch" lay-text="开|关">
        </div>
    </div>
    <div class="layui-form-item">
        <div class="layui-input-block">
            <button type="submit" class="layui-btn layui-btn-normal">
                <i class="layui-icon layui-icon-ok"></i> 保存设置
            </button>
        </div>
    </div>
</form>
