<?php
include('rule.php');
include('db.php');
$global=array(
    'DEFAULT_APP' => 'index',                     //默认访问模块，后台控制
    'DEFAULT_CONTROLLER' => $rule['moren'],           //默认访问控制器，后台控制
    'DEFAULT_ACTION' => 'index',                 //默认访问方法，后台控制
   
    'TPL'=>array(
        'TPL_PATH' => '',                        //模板路径
        'TPL_SUFFIX' => '.html',                 //模板后缀
        'TPL_CACHE' => 'TPL_CACHE',              //使用缓存配置
        'TPL_DEPR' => '/',                       //模板路径分隔符
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