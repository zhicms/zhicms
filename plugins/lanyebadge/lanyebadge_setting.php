<?php
!defined('EMLOG_ROOT') && exit('access deined!');

function plugin_setting_view() {
    $db = Database::getInstance();
    $act = isset($_GET['act']) ? $_GET['act'] : 'list';
    $msg = '';

    // 加载资源
    echo '<link rel="stylesheet" href="' . BLOG_URL . 'content/plugins/lanyebadge/static/fontawesom/font-awesome.min.css">';
    echo '<link rel="stylesheet" href="' . BLOG_URL . 'content/plugins/lanyebadge/static/admin.css">';
    echo '<script src="' . BLOG_URL . 'content/plugins/lanyebadge/static/admin.js" defer></script>';

    // ========== 删除 ==========
    if ($act == 'delete') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id > 0) {
            $db->query("DELETE FROM " . DB_PREFIX . "lanyebadge WHERE id = $id");
            $msg = '<div class="ly-success"><i class="fa fa-trash"></i> 删除成功！将在 <b id="wait_time">3</b> 秒后返回列表...</div>
                    <script>var w=3;var t=setInterval(function(){w--;document.getElementById("wait_time").innerHTML=w;if(w<=0){location.href="./plugin.php?plugin=lanyebadge";clearInterval(t);}},1000);</script>';
        }
        $act = 'list';
    }

    // ========== 保存（添加） ==========
    if ($act == 'save') {
        $params = array(
            'left_text'   => isset($_POST['left_text']) ? trim($_POST['left_text']) : '蓝叶',
            'right_text'  => isset($_POST['right_text']) ? trim($_POST['right_text']) : 'LANYEW.COM',
            'left_bg'     => isset($_POST['left_bg']) ? trim($_POST['left_bg']) : '#000000',
            'right_bg'    => isset($_POST['right_bg']) ? trim($_POST['right_bg']) : '#00aff0',
            'left_color'  => isset($_POST['left_color']) ? trim($_POST['left_color']) : '#ffffff',
            'right_color' => isset($_POST['right_color']) ? trim($_POST['right_color']) : '#ffffff',
            'font_size'   => isset($_POST['font_size']) ? intval($_POST['font_size']) : 11,
            'width'       => isset($_POST['width']) ? intval($_POST['width']) : 120,
            'height'      => isset($_POST['height']) ? intval($_POST['height']) : 28,
            'left_width'  => isset($_POST['left_width']) ? intval($_POST['left_width']) : 0,
            'font_family' => isset($_POST['font_family']) ? trim($_POST['font_family']) : 'Arial, sans-serif'
        );
        $svg_content = lanyebadge_generate_svg($params);
        $add_time = time();

        $left_text   = addslashes($params['left_text']);
        $right_text  = addslashes($params['right_text']);
        $left_bg     = $params['left_bg'];
        $right_bg    = $params['right_bg'];
        $left_color  = $params['left_color'];
        $right_color = $params['right_color'];
        $font_size   = $params['font_size'];
        $width       = $params['width'];
        $height      = $params['height'];
        $left_width  = $params['left_width'];
        $font_family = addslashes($params['font_family']);
        $svg_content = addslashes($svg_content);

        $sql = "INSERT INTO " . DB_PREFIX . "lanyebadge (left_text, right_text, left_bg, right_bg, left_color, right_color, font_size, width, height, left_width, font_family, svg_content, add_time)
                VALUES ('$left_text', '$right_text', '$left_bg', '$right_bg', '$left_color', '$right_color', $font_size, $width, $height, $left_width, '$font_family', '$svg_content', $add_time)";
        $db->query($sql);
        $msg = '<div class="ly-success"><i class="fa fa-check-circle"></i> 徽章添加成功！将在 <b id="wait_time">3</b> 秒后返回列表...</div>
                <script>var w=3;var t=setInterval(function(){w--;document.getElementById("wait_time").innerHTML=w;if(w<=0){location.href="./plugin.php?plugin=lanyebadge";clearInterval(t);}},1000);</script>';
        $act = 'show_msg';
    }

    // ========== 更新 ==========
    if ($act == 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $params = array(
            'left_text'   => isset($_POST['left_text']) ? trim($_POST['left_text']) : '',
            'right_text'  => isset($_POST['right_text']) ? trim($_POST['right_text']) : '',
            'left_bg'     => isset($_POST['left_bg']) ? trim($_POST['left_bg']) : '#000000',
            'right_bg'    => isset($_POST['right_bg']) ? trim($_POST['right_bg']) : '#00aff0',
            'left_color'  => isset($_POST['left_color']) ? trim($_POST['left_color']) : '#ffffff',
            'right_color' => isset($_POST['right_color']) ? trim($_POST['right_color']) : '#ffffff',
            'font_size'   => isset($_POST['font_size']) ? intval($_POST['font_size']) : 11,
            'width'       => isset($_POST['width']) ? intval($_POST['width']) : 120,
            'height'      => isset($_POST['height']) ? intval($_POST['height']) : 28,
            'left_width'  => isset($_POST['left_width']) ? intval($_POST['left_width']) : 0,
            'font_family' => isset($_POST['font_family']) ? trim($_POST['font_family']) : 'Arial, sans-serif'
        );
        $svg_content = lanyebadge_generate_svg($params);
        $left_text   = addslashes($params['left_text']);
        $right_text  = addslashes($params['right_text']);
        $left_bg     = $params['left_bg'];
        $right_bg    = $params['right_bg'];
        $left_color  = $params['left_color'];
        $right_color = $params['right_color'];
        $font_size   = $params['font_size'];
        $width       = $params['width'];
        $height      = $params['height'];
        $left_width  = $params['left_width'];
        $font_family = addslashes($params['font_family']);
        $svg_content = addslashes($svg_content);

        $sql = "UPDATE " . DB_PREFIX . "lanyebadge SET
                left_text='$left_text', right_text='$right_text', left_bg='$left_bg', right_bg='$right_bg',
                left_color='$left_color', right_color='$right_color', font_size=$font_size,
                width=$width, height=$height, left_width=$left_width, font_family='$font_family',
                svg_content='$svg_content'
                WHERE id = $id";
        $db->query($sql);
        $msg = '<div class="ly-success"><i class="fa fa-save"></i> 更新成功！将在 <b id="wait_time">3</b> 秒后返回列表...</div>
                <script>var w=3;var t=setInterval(function(){w--;document.getElementById("wait_time").innerHTML=w;if(w<=0){location.href="./plugin.php?plugin=lanyebadge";clearInterval(t);}},1000);</script>';
        $act = 'show_msg';
    }

    // ========== 导航菜单 ==========
    ?>
    <div class="ly-admin-nav">
        <a href="./plugin.php?plugin=lanyebadge"><i class="fa fa-list"></i> 徽章列表</a>
        <a href="./plugin.php?plugin=lanyebadge&act=add"><i class="fa fa-plus-circle"></i> 添加徽章</a>
        <a href="https://lanyew.com" target="_blank"><i class="fa fa-user"></i> 拜访作者</a>
    </div>

    <?php echo $msg; ?>

    <?php if ($act == 'list' || $act == 'show_msg'): ?>
        <!-- 列表页 -->
        <div class="ly-panel">
            <div>
                <h5><i class="fa fa-database"></i> 徽章数据管理</h5>
                <a href="./plugin.php?plugin=lanyebadge&act=add" class="ly-btn"><i class="fa fa-plus"></i> 添加徽章</a>
            </div>
            <table class="ly-table">
                <thead>
                    <tr><th width="40">ID</th><th>预览</th><th>左侧文字</th><th>右侧文字</th><th>尺寸</th><th>操作</th></tr>
                </thead>
                <tbody>
                    <?php
                    $res = $db->query("SELECT * FROM " . DB_PREFIX . "lanyebadge ORDER BY id DESC LIMIT 100");
                    while ($row = $db->fetch_array($res)):
                        $badge_url = lanyebadge_getBadgeUrl($row['id']);
                        $img_tag = '<img src="' . $badge_url . '" alt="徽章" />';
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><img src="<?php echo $badge_url; ?>" style="height:28px; vertical-align:middle;"></td>
                        <td><?php echo htmlspecialchars($row['left_text']); ?></td>
                        <td><?php echo htmlspecialchars($row['right_text']); ?></td>
                        <td><?php echo $row['width'] . '×' . $row['height']; ?></td>
                        <td class="actions">
                            <a href="./plugin.php?plugin=lanyebadge&act=edit&id=<?php echo $row['id']; ?>" class="action-edit"><i class="fa fa-edit"></i> 编辑</a>
                            <a href="javascript:;" class="action-copy" data-url="<?php echo htmlspecialchars($badge_url); ?>" data-html="<?php echo htmlspecialchars($img_tag); ?>"><i class="fa fa-copy"></i> 复制</a>
                            <a href="./plugin.php?plugin=lanyebadge&act=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('确定删除该徽章吗？');" class="action-delete"><i class="fa fa-trash"></i> 删除</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($act == 'add' || $act == 'edit'): 
        $is_edit = ($act == 'edit');
        $id = $is_edit ? intval($_GET['id']) : 0;
        $row = array();
        if ($is_edit) {
            $res = $db->query("SELECT * FROM " . DB_PREFIX . "lanyebadge WHERE id = $id");
            $row = $db->fetch_array($res);
            if (!$row) { echo '<div class="ly-panel">徽章不存在</div>'; return; }
        }
        // 默认值
        $defaults = array(
            'left_text' => '蓝叶', 'right_text' => 'LANYEW.COM',
            'left_bg' => '#000000', 'right_bg' => '#00aff0',
            'left_color' => '#ffffff', 'right_color' => '#ffffff',
            'font_size' => 11, 'width' => 120, 'height' => 28,
            'left_width' => 0, 'font_family' => 'Arial, sans-serif'
        );
        foreach ($defaults as $k => $v) {
            if ($is_edit && isset($row[$k])) {
                $defaults[$k] = $row[$k];
            }
        }
        $form_action = $is_edit ? './plugin.php?plugin=lanyebadge&act=update' : './plugin.php?plugin=lanyebadge&act=save';
    ?>
        <div class="ly-panel">
            <div>
                <h5><i class="fa fa-<?php echo $is_edit ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $is_edit ? '编辑徽章' : '添加新徽章'; ?></h5>
                <a href="./plugin.php?plugin=lanyebadge" class="ly-btn ly-btn-outline"><i class="fa fa-arrow-left"></i> 返回列表</a>
            </div>
            <!-- 实时预览 -->
            <div class="preview-box" style="text-align:center; margin-bottom:20px;">
                <img id="badgePreview" src="<?php echo BLOG_URL . 'content/plugins/lanyebadge/lanyebadge.php?lanyebadge=ajax&t=' . time(); ?>" style="height:<?php echo $defaults['height']; ?>px;">
            </div>
            <form action="<?php echo $form_action; ?>" method="post" autocomplete="off">
                <?php if ($is_edit): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
                <div class="ly-form-grid">
                    <div class="ly-form-group">
                        <label>左侧文字</label>
                        <input type="text" name="left_text" value="<?php echo htmlspecialchars($defaults['left_text']); ?>" required>
                    </div>
                    <div class="ly-form-group">
                        <label>右侧文字</label>
                        <input type="text" name="right_text" value="<?php echo htmlspecialchars($defaults['right_text']); ?>" required>
                    </div>
                    <div class="ly-form-group">
                        <label>左侧背景色</label>
                        <input type="color" name="left_bg" value="<?php echo $defaults['left_bg']; ?>">
                    </div>
                    <div class="ly-form-group">
                        <label>右侧背景色</label>
                        <input type="color" name="right_bg" value="<?php echo $defaults['right_bg']; ?>">
                    </div>
                    <div class="ly-form-group">
                        <label>左侧文字颜色</label>
                        <input type="color" name="left_color" value="<?php echo $defaults['left_color']; ?>">
                    </div>
                    <div class="ly-form-group">
                        <label>右侧文字颜色</label>
                        <input type="color" name="right_color" value="<?php echo $defaults['right_color']; ?>">
                    </div>
                    <div class="ly-form-group">
                        <label>字体大小 (8~24)</label>
                        <input type="number" name="font_size" value="<?php echo $defaults['font_size']; ?>" min="8" max="24">
                    </div>
                    <div class="ly-form-group">
                        <label>总宽度 (80~800)</label>
                        <input type="number" name="width" value="<?php echo $defaults['width']; ?>" min="80" max="800">
                    </div>
                    <div class="ly-form-group">
                        <label>总高度 (20~100)</label>
                        <input type="number" name="height" value="<?php echo $defaults['height']; ?>" min="20" max="100">
                    </div>
                    <div class="ly-form-group">
                        <label>左侧宽度 (0=自动平分)</label>
                        <input type="number" name="left_width" value="<?php echo $defaults['left_width']; ?>" min="0">
                    </div>
                    <div class="ly-form-group">
                        <label>字体族</label>
                        <select name="font_family">
                            <option value="Arial, sans-serif" <?php if($defaults['font_family']=='Arial, sans-serif') echo 'selected'; ?>>Arial</option>
                            <option value="'PingFang SC','Microsoft YaHei',sans-serif" <?php if($defaults['font_family']=="'PingFang SC','Microsoft YaHei',sans-serif") echo 'selected'; ?>>苹方/微软雅黑</option>
                            <option value="Verdana, Geneva, sans-serif" <?php if($defaults['font_family']=='Verdana, Geneva, sans-serif') echo 'selected'; ?>>Verdana</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="ly-btn"><i class="fa fa-save"></i> <?php echo $is_edit ? '保存更新' : '确认添加'; ?></button>
            </form>
        </div>
        <script>
        (function(){
            var form = document.querySelector('form');
            var preview = document.getElementById('badgePreview');
            function updatePreview(){
                var fd = new FormData(form);
                var params = new URLSearchParams();
                for(var pair of fd.entries()){
                    if(pair[0] !== 'id') params.set(pair[0], pair[1]);
                }
                params.set('lanyebadge', 'ajax');
                params.set('t', Date.now());
                preview.src = '<?php echo BLOG_URL . 'content/plugins/lanyebadge/lanyebadge.php'; ?>?' + params.toString();
                var h = form.querySelector('[name="height"]').value || 28;
                preview.style.height = h + 'px';
            }
            form.addEventListener('input', updatePreview);
            form.addEventListener('change', updatePreview);
        })();
        </script>
    <?php endif; ?>

    <!-- 复制弹窗 -->
    <div id="copyModal" class="ly-modal">
        <div class="ly-modal-content">
            <span class="ly-modal-close">&times;</span>
            <h4><i class="fa fa-share-alt"></i> 复制徽章代码</h4>
            <div class="ly-form-group">
                <label>图片URL</label>
                <input type="text" id="modalUrl" readonly onclick="this.select()">
                <button class="ly-btn ly-btn-sm" id="copyUrlBtn"><i class="fa fa-link"></i> 复制URL</button>
            </div>
            <div class="ly-form-group">
                <label>HTML代码</label>
                <textarea id="modalHtml" readonly rows="3" onclick="this.select()"></textarea>
                <button class="ly-btn ly-btn-sm" id="copyHtmlBtn"><i class="fa fa-code"></i> 复制HTML</button>
            </div>
        </div>
    </div>
    <?php
}