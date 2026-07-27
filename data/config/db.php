<?php

$db=array(
    'DB'=>array(
        'default'=>array(
            'DB_TYPE' => 'MysqlPdo',
            'DB_HOST' => 'localhost',
            'DB_USER' => 'zhicms',
            'DB_PWD' => 'zhicms',
            'DB_PORT' => '3306',
            'DB_NAME' => 'zhicms',
            'DB_CHARSET' => 'utf8mb4',
            'DB_PREFIX' => 'yun_',
            'DB_CACHE' => 'DB_CACHE',

            // ==================== 读写分离配置（需要时取消注释） ====================
            // 配置后：SELECT 查询自动走从库负载均衡，INSERT/UPDATE/DELETE 走主库
            // 从库故障时自动回退到主库，不影响业务
            // 'DB_SLAVE' => array(
            //     array(
            //         'DB_HOST' => '192.168.1.101',
            //         'DB_USER' => 'zhicms',
            //         'DB_PWD'  => 'zhicms',
            //         'DB_PORT' => '3306',
            //     ),
            //     // 支持配置多台从库，读请求自动随机负载均衡
            //     // array(
            //     //     'DB_HOST' => '192.168.1.102',
            //     //     'DB_USER' => 'zhicms',
            //     //     'DB_PWD'  => 'zhicms',
            //     //     'DB_PORT' => '3306',
            //     // ),
            // ),
        ),
    ),
);