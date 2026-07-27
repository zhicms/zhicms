<?php
// 注册插件（zblog 风格）。兼容层会立即调用 ActivePlugin_zbdemo 注册接口。
RegisterPlugin("zbdemo", "ActivePlugin_zbdemo");

function ActivePlugin_zbdemo()
{
    // 接口名经兼容层映射为 ZhiCms 原生钩子 admin_head
    Add_Filter_Plugin('Filter_Plugin_Admin_Header', 'zbdemo_head');
    Add_Filter_Plugin('Filter_Plugin_Admin_Menu', 'zbdemo_menu');
}

function zbdemo_head()
{
    echo '<meta name="generator" content="ZhiCms + zblog compat">';
}

function zbdemo_menu()
{
    global $zbp;
    echo '<div style="padding:4px 0;color:#666;">· 来自 zblog 兼容插件的菜单项</div>';
}

// zblog 安装/卸载钩子（兼容层在对应时机调用）
function InstallPlugin_zbdemo()
{
}

function UninstallPlugin_zbdemo()
{
}
