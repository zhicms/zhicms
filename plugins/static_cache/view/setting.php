<div class="content-box">
    <div class="content-box-header">
        <h3>全站静态化配置</h3>
    </div>
    <div class="content-box-content">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token ?? ''; ?>">
            
            <div class="form-group">
                <label class="form-label">启用静态化</label>
                <div class="form-control">
                    <label class="switch-label">
                        <input type="checkbox" name="enabled" value="1" <?php echo $config['enabled'] ? 'checked' : ''; ?>>
                        <span>开启后，页面将自动缓存为静态HTML文件</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">缓存有效期（秒）</label>
                <div class="form-control">
                    <input type="number" name="expire" value="<?php echo $config['expire']; ?>" min="0" step="60" style="width: 150px;">
                    <span class="form-help">设置为 0 表示永不过期，建议设置为 3600（1小时）或 86400（1天）</span>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">排除后台页面</label>
                <div class="form-control">
                    <label class="switch-label">
                        <input type="checkbox" name="exclude_admin" value="1" <?php echo $config['exclude_admin'] ? 'checked' : ''; ?>>
                        <span>不缓存后台管理页面（推荐开启）</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">排除的路由路径</label>
                <div class="form-control">
                    <textarea name="exclude_paths" rows="6" style="width: 500px;" placeholder="每行一个路径前缀，如：&#10;index.php?r=index/forum&#10;index.php?r=index/search"><?php echo htmlspecialchars($config['exclude_paths']); ?></textarea>
                    <div class="form-help">以该路径开头的请求将不被缓存，每行一个</div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">缓存统计</label>
                <div class="form-control">
                    <div class="cache-stats">
                        <span class="stat-item">缓存文件数：<strong><?php echo $stats['file_count']; ?></strong></span>
                        <span class="stat-item">占用空间：<strong><?php echo $statsText; ?></strong></span>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存配置</button>
                <button type="submit" name="clear_cache" value="1" class="btn btn-danger" onclick="return confirm('确定要清空所有静态缓存吗？')">清空缓存</button>
            </div>
        </form>
    </div>
</div>

<style>
.content-box {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    margin-bottom: 16px;
}
.content-box-header {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 6px 6px 0 0;
}
.content-box-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}
.content-box-content {
    padding: 20px;
}
.form-group {
    margin-bottom: 20px;
    display: flex;
    gap: 20px;
}
.form-label {
    width: 140px;
    font-weight: 600;
    color: #334155;
    padding-top: 8px;
    font-size: 13px;
}
.form-control {
    flex: 1;
}
.form-control input[type="text"],
.form-control input[type="number"],
.form-control textarea {
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    color: #1e293b;
    background: #fff;
    transition: border-color 0.2s;
}
.form-control input:focus,
.form-control textarea:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}
.form-help {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #64748b;
}
.switch-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    color: #475569;
}
.switch-label input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}
.cache-stats {
    display: flex;
    gap: 24px;
    padding: 12px 16px;
    background: #f1f5f9;
    border-radius: 6px;
}
.stat-item {
    font-size: 13px;
    color: #475569;
}
.stat-item strong {
    color: #2563eb;
    font-size: 15px;
}
.form-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
}
.btn {
    padding: 8px 20px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-primary {
    background: #2563eb;
    color: #fff;
}
.btn-primary:hover {
    background: #1d4ed8;
}
.btn-danger {
    background: #ef4444;
    color: #fff;
}
.btn-danger:hover {
    background: #dc2626;
}
</style>

<div class="content-box" style="margin-top: 20px;">
    <div class="content-box-header">
        <h3>使用说明</h3>
    </div>
    <div class="content-box-content">
        <div style="font-size: 13px; color: #475569; line-height: 1.8;">
            <p><strong>1. 工作原理：</strong>当访客首次访问某个页面时，系统会将该页面的HTML内容保存为静态文件。后续访问同一页面时，将直接返回静态文件，无需经过PHP解析，大幅提升访问速度。</p>
            <p><strong>2. 缓存路径：</strong>静态文件保存在 <code>runtime/static_cache/</code> 目录下，按URL哈希分目录存储。</p>
            <p><strong>3. 缓存有效期：</strong>超过有效期后，下次访问会自动重新生成缓存。设置为0表示永不过期。</p>
            <p><strong>4. 注意事项：</strong></p>
            <ul style="padding-left: 20px;">
                <li>用户登录状态依赖COOKIE，静态缓存默认不处理（会跳过带特定参数的请求）</li>
                <li>后台页面建议排除，避免影响管理操作</li>
                <li>更新文章/页面后，建议手动清空缓存或等待自动过期</li>
                <li>动态页面（如搜索、会员中心）请加入排除列表</li>
            </ul>
        </div>
    </div>
</div>
