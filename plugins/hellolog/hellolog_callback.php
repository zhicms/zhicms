<?php
/**
 * emlog 生命周期回调（兼容层在安装时调用 callback_init，卸载时调用 callback_rm）
 */
function callback_init()
{
    // 此处可执行建表、初始化配置等
}

function callback_rm()
{
    // 清理自建数据
}

function callback_up()
{
}
