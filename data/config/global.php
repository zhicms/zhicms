<?php
// 兼容 PHP 8.x：array_merge 不接受 null 参数。
// 注意：rule.php / db.php 采用「顶层定义变量」方式（无 return 语句），
// 必须用 include 让其变量进入当前作用域，不能写成 $x = include(...)，
// 否则拿到的是 include 返回值 int(1) 而非数组。
// db.php 在未安装时可能不存在（由安装向导生成），缺失时回退空数组，
// 避免安装时报 "array_merge(): Argument #3 must be of type array, null given"。
if (file_exists(__DIR__ . '/rule.php')) {
    include __DIR__ . '/rule.php';
}
if (file_exists(__DIR__ . '/db.php')) {
    include __DIR__ . '/db.php';
}
if (!isset($db)) {
    $db = array();
}
$global=array(
    'DEFAULT_APP' => 'index',                     //默认访问模块，后台控制
    'DEFAULT_CONTROLLER' => $rule['moren'],           //默认访问控制器，后台控制
    'DEFAULT_ACTION' => 'index',                 //默认访问方法，后台控制
   
    'TPL'=>array(
        'TPL_PATH' => '',                        //模板路径
        'TPL_SUFFIX' => '.html',                 //模板后缀
        'TPL_CACHE' => 'TPL_CACHE',              //使用缓存配置
        'TPL_DEPR' => '/',                       //模板路径分隔符
        'ENGINE' => 'legacy',                    //模板引擎：legacy(旧引擎) | think(think-template 真引擎)
    ),
    'CACHE'=>array(                              //缓存配置
        'TPL_CACHE' => array(
            'CACHE_TYPE' => 'FileCache',
            //缓存类型（FileCache,Memcached,Memcache,SaeMemcache），具体缓存服务配置请自行查阅驱动文件
            'CACHE_PATH' => ROOT_PATH . 'data/cache/',
            'GROUP' => 'tpl',                    //缓存目录
            'HASH_DEEP' => 0,                    //散列深度
        ),

        'DB_CACHE' => array(
            'CACHE_TYPE' => 'FileCache',
            'CACHE_PATH' => ROOT_PATH . 'data/cache/',
            'GROUP' => 'db',
            'HASH_DEEP' => 2,
        ),
    ),
    'STORAGE'=>array(                           //文件存储设置
        'default'=>array(
            'STORAGE_TYPE'=>'File',             //存储驱动
        ),
    ),
   
);
return array_merge($global,$rule,$db);