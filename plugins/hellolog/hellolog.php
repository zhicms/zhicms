<?php
/*
Plugin Name: HelloLog 兼容示例
Version: 1.0.0
Plugin URL: https://www.zhicms.com
Description: 演示以 emlog 格式编写、在 ZhiCms 下直接运行的插件（兼容层）。
Author: ZhiCms
Author URL: https://www.zhicms.com
*/

defined('EMLOG_ROOT') || exit('access denied!');

// 注册到 ZhiCms 原生钩子（兼容层把 adm_main_top / adm_head 映射过去）
addAction('adm_main_top', 'hellolog_banner');
addAction('adm_head', 'hellolog_css');

function hellolog_banner()
{
    echo '<div style="padding:8px 12px;margin:6px 0;background:#e8f4ff;border-left:3px solid #1e9fff;color:#0b4d8c;">'
        . '👋 这是通过 emlog 兼容层运行的插件（hellolog）'
        . '</div>';
}

function hellolog_css()
{
    echo "<style>.hellolog-tip{color:#0b4d8c}</style>\n";
}
