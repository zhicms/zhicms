<?php /*a:5:{s:52:"D:\phpstudy_pro\WWW\plugins/guangdiu/view\index.html";i:1786180828;s:59:"D:\phpstudy_pro\WWW\plugins/guangdiu/view\inc\seo_head.html";i:1786186636;s:53:"D:\phpstudy_pro\WWW\plugins/guangdiu/view\header.html";i:1786187221;s:54:"D:\phpstudy_pro\WWW\plugins/guangdiu/view\sidebar.html";i:1786187706;s:53:"D:\phpstudy_pro\WWW\plugins/guangdiu/view\footer.html";i:1786186633;}*/ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo tpl_raw($seo_title); ?></title>
    <meta name="renderer" content="webkit">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<title><?php echo tpl_raw($seo_title); ?></title>
<meta name="keywords" content="<?php echo tpl_raw($page_keywords); ?>">
<meta name="description" content="<?php echo tpl_raw($page_description); ?>">
<link rel="canonical" href="<?php echo tpl_raw($canonical); ?>">
<meta property="og:type" content="<?php echo tpl_raw($og_type); ?>">
<meta property="og:site_name" content="<?php echo tpl_raw($site_name); ?>">
<meta property="og:title" content="<?php echo tpl_raw($seo_title); ?>">
<meta property="og:description" content="<?php echo tpl_raw($page_description); ?>">
<meta property="og:url" content="<?php echo tpl_raw($canonical); ?>">
<?php if(!empty($og_image)): ?><meta property="og:image" content="<?php echo tpl_raw($og_image); ?>"><?php endif; ?>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"WebSite","name":"<?php echo tpl_raw($site_name); ?>","url":"<?php echo tpl_raw($canonical); ?>","potentialAction":{"@type":"SearchAction","target":"<?php echo tpl_raw($canonical); ?>?mod=search&kw={search_term_string}","query-input":"required name=search_term_string"}}
</script>
<link rel="icon" href="<?php echo tpl_raw($public); ?>favicon.ico">

    <link rel="stylesheet" href="<?php echo tpl_raw($public); ?>web/css/blog.css?v=5.0.2">
    <link rel="stylesheet" href="<?php echo tpl_raw($public); ?>web/css/common.css?v=5.0.2">
    <link rel="stylesheet" href="<?php echo tpl_raw($plug_static); ?>/css/style.css">
</head>
<body>

<!-- 插件公共头部：导航 + 搜索（仿 kiees.com 配色，可被所有页面复用） -->
<nav class="gd-header">
  <div class="gd-header-inner">
    <a class="gd-logo" href="<?php echo tpl_raw($plug_url); ?>" title="<?php echo tpl_raw($site_name); ?>">
      <?php if(!empty($site_logo)): ?><img src="<?php echo tpl_raw($site_logo); ?>" alt="<?php echo tpl_raw($site_name); ?>"><?php else: ?><span><?php echo tpl_raw($site_name); ?></span><?php endif; ?>
    </a>
    <ul class="gd-nav">
      <li<?php if($mod!='cheaps' && $mod!='brand' && $mod!='rank' && empty($is_search)): ?> class="active"<?php endif; ?>><a href="<?php echo tpl_raw($plug_url); ?>">首页</a></li>
      <li<?php if($mod=='cheaps'): ?> class="active"<?php endif; ?>><a href="<?php echo tpl_raw($nav_cheaps); ?>">优惠券</a></li>
      <li<?php if($mod=='brand'): ?> class="active"<?php endif; ?>><a href="<?php echo tpl_raw($nav_brand); ?>">大牌</a></li>
      <li<?php if($mod=='rank'): ?> class="active"<?php endif; ?>><a href="<?php echo tpl_raw($nav_rank); ?>">风云榜</a></li>
    </ul>
    <form class="gd-search" method="get" action="<?php echo tpl_raw($plug_url); ?>">
      <input type="text" name="kw" placeholder="搜索优惠券 / 文章" value="<?php if(!empty($keyword)): ?><?php echo tpl_raw(htmlspecialchars($keyword)); ?><?php endif; ?>" />
      <button type="submit">搜索</button>
    </form>
  </div>
</nav>


<main class="gd-main">
  <div class="gd-wrap">
    <!-- 中间内容区 -->
    <div class="gd-content">
      <div class="gd-box">
        <div class="gd-sectitle"><h3>今日精选 · 逛丢优惠好物</h3></div>
        <?php if(!empty($list)): ?>
        <div class="gd-list">
          <?php foreach($list as $item): ?>
          <div class="gd-post">
            <a class="gd-post-img" href="<?php echo tpl_raw($item['buy_url']); ?>" target="_blank">
              <?php if(!empty($item['mainPic'])): ?><img src="<?php echo tpl_raw($item['mainPic']); ?>" alt="<?php echo tpl_raw($item['title']); ?>" loading="lazy"><?php endif; ?>
            </a>
            <div class="gd-post-body">
              <h2 class="gd-post-title"><a href="<?php echo tpl_raw($item['buy_url']); ?>" target="_blank"><?php echo tpl_raw($item['title']); ?></a></h2>
              <div class="gd-post-detail"><?php if(!empty($item['dec'])): ?><?php echo tpl_raw($item['dec']); ?><?php endif; ?></div>
              <div class="gd-post-meta">
                <span class="gd-post-hot">人气：<em><?php echo tpl_raw($item['hot']); ?></em></span>
                <a class="gd-gobuy" href="<?php echo tpl_raw($item['buy_url']); ?>" target="_blank">查看详情</a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php echo tpl_raw($pager); else: ?>
        <div class="gd-empty">暂无内容，请稍后再来查看！</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 右侧栏（APP下载 + 热门榜，与 kiees 一致） -->
    <?php if($show_sidebar): ?><!-- 插件公共右侧栏：热门榜（可被所有页面复用） -->
<aside class="gd-sidebar">
  <?php if(!empty($app_iphone) || !empty($app_android)): ?>
  <div class="gd-app">
    <?php if(!empty($app_iphone)): ?><a class="gd-app-btn" href="<?php echo tpl_raw($app_iphone); ?>" rel="nofollow" target="_blank">iPhone APP 下载</a><?php endif; if(!empty($app_android)): ?><a class="gd-app-btn" href="<?php echo tpl_raw($app_android); ?>" target="_blank">Android APP 下载</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="gd-widget">
    <h3 class="gd-widget-title">热度比较高的商品</h3>
    <ul class="gd-top10">
      <?php foreach($hot as $k=>$h): ?>
      <li class="gd-top10-item">
        <a class="gd-top10-img" href="<?php if(!empty($h['itemLink'])): ?><?php echo tpl_raw($h['itemLink']); else: ?><?php echo tpl_raw($plug_base); ?>-<?php echo tpl_raw($h['id']); ?>.html<?php endif; ?>" target="_blank">
          <?php if(!empty($h['mainPic'])): ?><img src="<?php echo tpl_raw($h['mainPic']); ?>" alt="<?php echo tpl_raw($h['title']); ?>" /><?php else: ?><span class="gd-img-empty">值</span><?php endif; ?>
        </a>
        <div class="gd-top10-body">
          <h4 class="gd-top10-title"><a href="<?php if(!empty($h['itemLink'])): ?><?php echo tpl_raw($h['itemLink']); else: ?><?php echo tpl_raw($plug_base); ?>-<?php echo tpl_raw($h['id']); ?>.html<?php endif; ?>" target="_blank"><?php echo tpl_raw($h['title']); ?></a></h4>
          <span class="gd-top10-hot"><?php if(!empty($h['hot'])): ?><?php echo tpl_raw($h['hot']); ?>°C<?php else: ?>热门<?php endif; ?></span>
        </div>
        <em class="gd-top10-no"><?php echo tpl_raw($k+1); ?></em>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <p class="gd-sidebar-tip">关注<?php echo tpl_raw($site_name); ?>，发现值得买</p>
</aside>
<?php endif; ?>
  </div>
</main>

<!-- 插件公共尾部（可被所有页面复用） -->
<footer class="gd-footer">
  <p>© <?php echo tpl_raw($site_name); ?> · 高性价比网购推荐</p>
  <p class="gd-copy"><?php echo tpl_raw($copyright); if(!empty($beian)): ?> · <a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow"><?php echo tpl_raw($beian); ?></a><?php endif; ?></p>
</footer>
<?php if($show_sidebar != 1): ?><p style="text-align:center;font-size:13px;margin:0 0 18px;"><a href="<?php echo tpl_raw($pc_url); ?>" style="color:#bb0200;">电脑版</a></p><?php endif; ?>


</body>
</html>
