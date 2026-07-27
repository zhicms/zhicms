<?php
/*
Plugin Name: 徽章生成器
Version: 1.0
Plugin URL: https://lanyew.com
Description: 网页徽章生成器，自定义文字、颜色、尺寸，生成精美SVG徽章，一键复制或下载。
Author: 蓝叶
Author URL: https://lanyew.com
*/
if (!defined('EMLOG_ROOT')) {
    if (isset($_GET['lanyebadge']) && $_GET['lanyebadge'] === 'ajax') {
        lanyebadge_ajax_output();
    }
    exit;
}
!defined('EMLOG_ROOT') && exit('access deined!');
function lanyebadge_generate_svg($params) {
    $defaults = array(
        'left_text'   => '蓝叶',
        'right_text'  => 'LANYEW.COM',
        'left_bg'     => '#000000',
        'right_bg'    => '#00aff0',
        'left_color'  => '#ffffff',
        'right_color' => '#ffffff',
        'font_size'   => 11,
        'width'       => 120,
        'height'      => 28,
        'left_width'  => 0,
        'font_family' => 'Arial, sans-serif'
    );
    $p = array_merge($defaults, $params);

    // 安全过滤
    $p['left_text']   = htmlspecialchars(strip_tags($p['left_text']), ENT_QUOTES, 'UTF-8');
    $p['right_text']  = htmlspecialchars(strip_tags($p['right_text']), ENT_QUOTES, 'UTF-8');
    $p['font_size']   = max(8, min(24, intval($p['font_size'])));
    $p['width']       = max(80, min(800, intval($p['width'])));
    $p['height']      = max(20, min(100, intval($p['height'])));
    $p['left_width']  = max(0, min($p['width'], intval($p['left_width'])));
    $p['font_family'] = preg_replace('/[^a-zA-Z0-9,\s\'\-"]/', '', $p['font_family']);
    if (empty($p['font_family'])) $p['font_family'] = 'Arial, sans-serif';

    // 颜色格式标准化
    foreach (array('left_bg','right_bg','left_color','right_color') as $key) {
        $val = trim($p[$key]);
        if (preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $val)) {
            $p[$key] = '#' . ltrim($val, '#');
        } else {
            $p[$key] = $defaults[$key];
        }
    }

    // 计算左侧宽度
    if ($p['left_width'] <= 0 || $p['left_width'] >= $p['width']) {
        $leftWidth = floor($p['width'] / 2);
    } else {
        $leftWidth = $p['left_width'];
    }
    $rightWidth = $p['width'] - $leftWidth;

    // 构建 SVG
    $title = $p['left_text'] . ': ' . $p['right_text'];
    $svg  = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $p['width'] . '" height="' . $p['height'] . '" role="img">';
    $svg .= '<title>' . $title . '</title>';
    $svg .= '<g shape-rendering="crispEdges">';
    $svg .= '<rect width="' . $leftWidth . '" height="' . $p['height'] . '" fill="' . $p['left_bg'] . '" />';
    $svg .= '<rect x="' . $leftWidth . '" width="' . $rightWidth . '" height="' . $p['height'] . '" fill="' . $p['right_bg'] . '" />';
    $svg .= '</g>';
    $svg .= '<text x="' . ($leftWidth / 2) . '" y="' . ($p['height'] / 2) . '" fill="' . $p['left_color'] . '" text-anchor="middle" dominant-baseline="central" font-family="' . $p['font_family'] . '" font-size="' . $p['font_size'] . '">' . $p['left_text'] . '</text>';
    $svg .= '<text x="' . ($leftWidth + $rightWidth / 2) . '" y="' . ($p['height'] / 2) . '" fill="' . $p['right_color'] . '" text-anchor="middle" dominant-baseline="central" font-family="' . $p['font_family'] . '" font-size="' . $p['font_size'] . '" font-weight="bold">' . $p['right_text'] . '</text>';
    $svg .= '</svg>';
    return $svg;
}

/**
 * 独立访问时的输出（动态生成 SVG）
 */
function lanyebadge_ajax_output() {
    $params = array(
        'left_text'   => isset($_GET['left_text'])   ? $_GET['left_text']   : '蓝叶',
        'right_text'  => isset($_GET['right_text'])  ? $_GET['right_text']  : 'LANYEW.COM',
        'left_bg'     => isset($_GET['left_bg'])     ? $_GET['left_bg']     : '#000000',
        'right_bg'    => isset($_GET['right_bg'])    ? $_GET['right_bg']    : '#00aff0',
        'left_color'  => isset($_GET['left_color'])  ? $_GET['left_color']  : '#ffffff',
        'right_color' => isset($_GET['right_color']) ? $_GET['right_color'] : '#ffffff',
        'font_size'   => isset($_GET['font_size'])   ? $_GET['font_size']   : 11,
        'width'       => isset($_GET['width'])       ? $_GET['width']       : 120,
        'height'      => isset($_GET['height'])      ? $_GET['height']      : 28,
        'left_width'  => isset($_GET['left_width'])  ? $_GET['left_width']  : 36,
        'font_family' => isset($_GET['font_family']) ? $_GET['font_family'] : 'Arial, sans-serif'
    );
    $svg = lanyebadge_generate_svg($params);
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $svg;
    exit;
}

// 获取徽章完整 URL
function lanyebadge_getBadgeUrl($id) {
    $blog_url = BLOG_URL;
    $is_rewrite = Option::get('isurlrewrite');
    if ($id > 0) {
        return $is_rewrite > 0 ? "{$blog_url}plugin/lanyebadge?id={$id}" : "{$blog_url}?plugin=lanyebadge&id={$id}";
    }
    return $is_rewrite > 0 ? "{$blog_url}plugin/lanyebadge" : "{$blog_url}?plugin=lanyebadge";
}