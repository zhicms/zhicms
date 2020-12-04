<?php
       
$db=array(
                    'DB'=>array(                                 //数据库信息
                          'default'=>array(
                              'DB_TYPE' => 'Mysqlpdo',             //数据库驱动 (mysqlpdo、mysql)
                              'DB_HOST' => '127.0.0.1',            //数据库地址
                              'DB_USER' => 'zhicms',                 //数据库用户
                              'DB_PWD' => 'zhicms',                      //数据库密码
                              'DB_PORT' => '3306',                   //数据库端口
                              'DB_NAME' => 'zhicms',                   //数据库名称
                              'DB_CHARSET' => 'utf8mb4',              //数据库编码
                              'DB_PREFIX' => 'yun_',                   //数据表前缀
                              'DB_CACHE' => 'DB_CACHE',            //使用缓存配置
                          ),
                      ),
                          );