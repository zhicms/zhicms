<?php
$rule=array (
  'ENV' => 'global',
  'DEBUG' => 1,
  'LOG_ON' => false,
  'LOG_PATH' => ROOT_PATH . 'data/log/',
  'TIMEZONE' => 'PRC',
  'moren' => 'index',
  'REWRITE_ON' => 1,
  'REWRITE_RULE' => 
  array (
    // ===== 文章资讯 =====
    'index.html' => 'index/index/index',
    'cat-<nav>.html' => 'index/index/index/nav=<nav>',
    'list-<cid>.html' => 'index/index/index/list=<cid>',
    'archive-<ym>.html' => 'index/index/index/ym=<ym>',
    'view-<id>.html' => 'index/index/view/id=<id>',
    'article-<id>.html' => 'index/index/view/id=<id>',
    // ===== 微社区（forum）=====
    'forum.html' => 'index/forum/index',
    'forum-b<bid>.html' => 'index/forum/index/bid=<bid>',
    'group-<gid>.html' => 'index/forum/group/gid=<gid>',
    'topic-<id>.html' => 'index/forum/view/id=<id>',
    // ===== 商品 / 优惠券 =====
    'brand.html' => 'index/brand/index',
    'brand-view-<id>.html' => 'index/brand/view/id=<id>',
    'cheaps.html' => 'index/cheaps/index',
    'cheaps-<id>.html' => 'index/cheaps/index/id=<id>',
    'rank.html' => 'index/rank/index',
    'hot.html' => 'index/hot/index',
    'detail-<id>.html' => 'index/view/detail/id=<id>',
    'vip-<id>.html' => 'index/view/vip/id=<id>',
    'product-<type>-<id>.html' => 'index/view/view/type=<type>/id=<id>',
    // ===== 搜索 / 单页 / 用户 =====
    'so.html' => 'index/search/index',
    'so-<key>.html' => 'index/search/index/content=<key>',
    'page-<id>.html' => 'index/page/index/id=<id>',
    'app.html' => 'index/page/app',
    'side.html' => 'index/page/side',
    'ucenter.html' => 'index/ucenter/index',
    'ai.html' => 'index/aiassistant/chat',
    // ===== 移动端 =====
    'm.html' => 'index/m/index',
    'm-rank.html' => 'index/m/rank',
    'm-cheaps.html' => 'index/m/cheaps',
    'm-search-<key>.html' => 'index/m/search/key=<key>',
    // ===== 跳转 / 转链 =====
    'gotb.html' => 'go/tb/itemiid',
    'goto.html' => 'go/to/url',
    'go.html' => 'go/to/wjp',
  ),
);
