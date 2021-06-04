<?php
       
$rule=array(
       'ENV' => 'global',                      //调试配置文件名
    'DEBUG' =>false,                             //是否显示详细错误信息
    'LOG_ON' => false,                           //错误日志记录，需要自己扩展appHook
    'LOG_PATH' => ROOT_PATH . 'data/log/',       //日志记录目录
    'TIMEZONE'=> 'PRC',                          //时间区域
	 'moren' => 'index',                       //默认首页访问模块
    'REWRITE_ON' => true,                       //伪静态开关，需要放入对应环境的伪静态规则

     'REWRITE_RULE' => array(
      'index.html'=>'index/index/index',
      'view-<id>.html'=>'index/index/view/id=<id>',
      'brand.html'=>'index/brand/index',
      'brand-view-<id>.html'=>'index/brand/view/id=<id>',
      'cheaps.html'=>'index/cheaps/index',
      'cheaps-<id>.html'=>'index/cheaps/index/id=<id>',
      'detail-<id>.html'=>'index/view/detail/id=<id>',
      'vip-<id>.html'=>'index/view/vip/id=<id>',
      'product-<type>-<id>.html'=>'index/view/view/id=<id>',
      'rank.html'=>'index/rank/index', 
      'm.html'=>'index/m/index',
     'm-search-<key>.html'=>'index/m/search/key=<key>' , 
		'gotb.html'=>'go/tb/itemiid',
		'goto.html'=>'go/to/url',
		'go.html'=>'go/to/wjp',
		'so.html'=>'index/search/index',
		'app.html'=>'index/page/app',
		'side.html'=>'index/page/side',
        

    ),
      );
       