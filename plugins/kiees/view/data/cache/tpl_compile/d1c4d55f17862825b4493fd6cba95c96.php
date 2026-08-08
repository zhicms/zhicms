<?php /*a:5:{s:50:"D:\phpstudy_pro\WWW\plugins/kiees/view\cheaps.html";i:1786175880;s:56:"D:\phpstudy_pro\WWW\plugins/kiees/view\inc\seo_head.html";i:1786175857;s:50:"D:\phpstudy_pro\WWW\plugins/kiees/view\header.html";i:1786161529;s:51:"D:\phpstudy_pro\WWW\plugins/kiees/view\sidebar.html";i:1786164907;s:50:"D:\phpstudy_pro\WWW\plugins/kiees/view\footer.html";i:1786175690;}*/ ?>
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
<nav class="kc-header">
  <div class="kc-header-inner">
    <a class="kc-logo" href="<?php echo tpl_raw($plug_url); ?>" title="<?php echo tpl_raw($site_name); ?>">
      <?php if(!empty($site_logo)): ?><img src="<?php echo tpl_raw($site_logo); ?>" alt="<?php echo tpl_raw($site_name); ?>"><?php else: ?><span><?php echo tpl_raw($site_name); ?></span><?php endif; ?>
    </a>
    <ul class="kc-nav">
      <li<?php if(empty($is_search)): ?> class="active"<?php endif; ?>><a href="<?php echo tpl_raw($plug_url); ?>">首页</a></li>
      <li><a href="<?php echo tpl_raw($nav_cheaps); ?>">优惠券</a></li>
      <li><a href="<?php echo tpl_raw($nav_brand); ?>">大牌</a></li>
      <li><a href="<?php echo tpl_raw($nav_rank); ?>">风云榜</a></li>
    </ul>
    <form class="kc-search" method="get" action="<?php echo tpl_raw($plug_url); ?>">
      <input type="text" name="kw" placeholder="搜索优惠好物" value="<?php if(!empty($keyword)): ?><?php echo tpl_raw(htmlspecialchars($keyword)); ?><?php endif; ?>" />
      <button type="submit">搜索</button>
    </form>
  </div>
</nav>


<main class="kc-main">
  <div class="kc-wrap">
    <div class="kc-content">
      <div class="kc-box">
        <div class="kc-sectitle"><h3>优惠券中心 · 海量优惠券 超值折扣</h3></div>
        <?php if(!empty($list)): ?>
        <div class="kc-grid">
          <?php foreach($list as $item): ?>
          <article class="kc-card">
            <?php if(!empty($item['img'])): ?><a class="kc-card-cover" href="<?php echo tpl_raw($item['buy_url']); ?>" target="_blank"><img src="<?php echo tpl_raw($item['img']); ?>" alt="<?php echo tpl_raw($item['title']); ?>" loading="lazy"></a><?php endif; ?>
            <div class="kc-card-body">
              <?php if(!empty($item['coupon'])): ?><span class="kc-coupon">领券减¥<?php echo tpl_raw($item['coupon']); ?></span><?php endif; ?>
              <h3 class="kc-card-title"><a href="<?php echo tpl_raw($item['buy_url']); ?>" target="_blank"><?php echo tpl_raw($item['title']); ?></a></h3>
              <div class="kc-price">
                <?php if(!empty($item['price'])): ?><span class="kc-now">¥<?php echo tpl_raw($item['price']); ?></span><?php endif; if(!empty($item['origin'])): ?><span class="kc-old">¥<?php echo tpl_raw($item['origin']); ?></span><?php endif; ?>
              </div>
              <a class="kc-gobuy" href="<?php echo tpl_raw($item['buy_url']); ?>" target="_blank" rel="nofollow">立即领券</a>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
        <?php echo tpl_raw($pager); else: ?>
        <div class="kc-empty">暂无优惠券，请稍后再来查看！</div>
        <?php endif; ?>
      </div>
    </div>

    <?php if($show_sidebar): ?><!-- 插件公共右侧栏：热门榜（可被所有页面复用） -->
<aside class="kc-sidebar">
  <?php if(!empty($app_iphone) || !empty($app_android)): ?>
  <div class="kc-app">
    <?php if(!empty($app_iphone)): ?><a class="kc-app-btn" href="<?php echo tpl_raw($app_iphone); ?>" rel="nofollow" target="_blank">iPhone APP 下载</a><?php endif; if(!empty($app_android)): ?><a class="kc-app-btn" href="<?php echo tpl_raw($app_android); ?>" target="_blank">Android APP 下载</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="kc-widget">
    <h3 class="kc-widget-title">热度比较高的商品</h3>
    <ul class="kc-top10">
      <?php foreach($hot as $k=>$h): ?>
      <li class="kc-top10-item">
        <a class="kc-top10-img" href="<?php if(!empty($h['itemLink'])): ?><?php echo tpl_raw($h['itemLink']); else: ?><?php echo tpl_raw($plug_base); ?>-<?php echo tpl_raw($h['id']); ?>.html<?php endif; ?>" target="_blank">
          <?php if(!empty($h['mainPic'])): ?><img src="<?php echo tpl_raw($h['mainPic']); ?>" alt="<?php echo tpl_raw($h['title']); ?>" /><?php else: ?><span class="kc-img-empty">值</span><?php endif; ?>
        </a>
        <div class="kc-top10-body">
          <h4 class="kc-top10-title"><a href="<?php if(!empty($h['itemLink'])): ?><?php echo tpl_raw($h['itemLink']); else: ?><?php echo tpl_raw($plug_base); ?>-<?php echo tpl_raw($h['id']); ?>.html<?php endif; ?>" target="_blank"><?php echo tpl_raw($h['title']); ?></a></h4>
          <span class="kc-top10-hot"><?php if($h['view'] > 0): ?><?php echo tpl_raw($h['view']); ?>°C<?php else: ?>热门<?php endif; ?></span>
        </div>
        <em class="kc-top10-no"><?php echo tpl_raw($k+1); ?></em>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <p class="kc-sidebar-tip">关注<?php echo tpl_raw($site_name); ?>，发现值得买</p>
</aside>
<?php endif; ?>
  </div>
</main>

<!-- 插件公共尾部（可被所有页面复用） -->
<footer class="kc-footer">
  <p>© 发现值得买 · 高性价比网购推荐</p>
  <p class="kc-copy"><?php echo tpl_raw($copyright); if(!empty($beian)): ?> · <a href="https://beian.miit.gov.cn/" target="_blank" rel="nofollow"><?php echo tpl_raw($beian); ?></a><?php endif; ?></p>
</footer>
<?php if($show_sidebar != 1): ?><p style="text-align:center;font-size:13px;margin:0 0 18px;"><a href="<?php echo tpl_raw($pc_url); ?>" style="color:#bb0200;">电脑版</a></p><?php endif; ?>


</body>
</html>
