<?php
       
$rule=array(
       'ENV' => 'global',                      //调试配置文件名
    'DEBUG' =>1,                             //是否显示详细错误信息
    'LOG_ON' => false,                           //错误日志记录，需要自己扩展appHook
    'LOG_PATH' => ROOT_PATH . 'data/log/',       //日志记录目录
    'TIMEZONE'=> 'PRC',                          //时间区域
	 'moren' => 'index',                       //默认首页访问模块
    'REWRITE_ON' => 1,                       //伪静态开关，需要放入对应环境的伪静态规则

     'REWRITE_RULE' => array(
      'index.html'=>'index/index/index',
      'index-<list>.html'=>'index/index/index/list=<list>',
      'view-<id>.html'=>'index/index/view/id=<id>',
      'brand.html'=>'index/brand/index',
      'brand-cid-<cid>.html'=>'index/brand/index/cid=<cid>',
      'brand-view-<id>.html'=>'index/brand/view/id=<id>',
      'cheaps.html'=>'index/cheaps/index',
      'cheaps-<id>.html'=>'index/cheaps/index/id=<id>',
      'detail-<id>.html'=>'index/view/detail/id=<id>',
      'detail-<id>-<type>.html'=>'index/view/detail/id=<id>/type=<type>',
      'vip-<id>.html'=>'index/view/vip/id=<id>',
      'product-<type>-<id>.html'=>'index/view/view/type=<type>/id=<id>',
      'rank.html'=>'index/rank/index', 
      'hot.html'=>'index/hot/index',
      'm.html'=>'index/m/index',
      'm-search-<key>.html'=>'index/m/search/key=<key>', 
      'go-tb-<id>.html'=>'go/tb/itemiid/id=<id>',
      'go-url-<url>.html'=>'go/to/url/url=<url>',
      'go-wjp-<id>-<type>.html'=>'go/to/wjp/id=<id>/type=<type>',
      'so.html'=>'index/search/index',
      'app.html'=>'index/page/app',
      'side.html'=>'index/page/side',
      // 微社区
      'shequ.html'=>'index/forum/index',
      'shequ-bid-<bid>.html'=>'index/forum/index/bid=<bid>',
      'shequ-<gid>.html'=>'index/forum/group/gid=<gid>',
      'tiezi-<id>.html'=>'index/forum/view/id=<id>',
        
    ),
      );
       