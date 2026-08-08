// KIEES 插件演示 - 前端交互
(function () {
    // 导航锚点平滑滚动
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var id = this.getAttribute('href');
            if (id.length > 1) {
                var el = document.querySelector(id);
                if (el) {
                    e.preventDefault();
                    el.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });

    // 滚动时 Header 阴影增强
    var header = document.querySelector('.site-header');
    if (header) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 10) {
                header.style.boxShadow = '0 6px 24px rgba(0,0,0,.3)';
            } else {
                header.style.boxShadow = 'none';
            }
        });
    }
})();
