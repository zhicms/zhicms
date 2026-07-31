/* Manage nav fix - collapse + active (use Bootstrap collapse API) */
(function () {
    window.__NAV_FIX_LOADED = 2;

    function parseRoute() {
        var s = location.search || '';
        var m = s.match(/r=([^&]+)/);
        if (!m) return {ctrl:'', act:''};
        var parts = decodeURIComponent(m[1]).split('/');
        return {
            ctrl: (parts[1] || '').toLowerCase(),
            act:  (parts[2] || '').toLowerCase()
        };
    }

    var MAP = {
        '#collapseArticles': 'find',
        '#collapseForum':    'forum',
        '#collapseAd':       'ad'
    };

    function fixCollapse() {
        var route = parseRoute();
        var $ = window.jQuery;
        var anchors = document.querySelectorAll('a.nav-link[data-toggle="collapse"]');
        for (var i = 0; i < anchors.length; i++) {
            var a = anchors[i];
            var target = a.getAttribute('data-target');
            var mapped = MAP[target];
            if (!mapped) continue;
            var panel = document.querySelector(target);
            if (!panel) continue;
            var isCurrent = (mapped.toLowerCase() === route.ctrl);

            if (isCurrent) {
                a.classList.remove('collapsed');
                a.setAttribute('aria-expanded', 'true');
                panel.classList.add('show');
                if ($) { $(target).collapse('show'); }
            } else {
                a.classList.add('collapsed');
                a.setAttribute('aria-expanded', 'false');
                panel.classList.remove('show');
                if ($) { $(target).collapse('hide'); }
            }
        }
    }

    function fixActive() {
        var route = parseRoute();
        if (!route.ctrl) return;

        // 1) 顶级跳转 a.nav-link（非折叠触发器）→ 父 li.nav-item 加 active
        var topAnchors = document.querySelectorAll('ul#accordionSidebar > li.nav-item > a.nav-link');
        for (var i = 0; i < topAnchors.length; i++) {
            var ta = topAnchors[i];
            if (ta.getAttribute('data-toggle') === 'collapse') continue;
            var href = ta.getAttribute('href') || '';
            if (href.indexOf('r=') < 0) continue;
            var hm = href.match(/r=([^&]+)/);
            if (!hm) continue;
            var hparts = decodeURIComponent(hm[1]).split('/');
            var hctrl = (hparts[1] || '').toLowerCase();
            if (hctrl !== route.ctrl) continue;
            var li = null;
            if (ta.closest) { li = ta.closest('li.nav-item'); }
            if (li) li.classList.add('active');
        }

        // 2) 子菜单 a.collapse-item → 自己加高亮样式，父 li.nav-item 加 active
        var subAnchors = document.querySelectorAll('#accordionSidebar a.collapse-item');
        for (var j = 0; j < subAnchors.length; j++) {
            var sa = subAnchors[j];
            var shref = sa.getAttribute('href') || '';
            if (shref.indexOf('r=') < 0) continue;
            var shm = shref.match(/r=([^&]+)/);
            if (!shm) continue;
            var sparts = decodeURIComponent(shm[1]).split('/');
            var sctrl = (sparts[1] || '').toLowerCase();
            var sact  = (sparts[2] || '').toLowerCase();
            // ctrl 必须相等；如果链接写了 act 就要求 act 匹配
            var matched = (sctrl === route.ctrl);
            if (matched && sact) matched = (sact === route.act);
            if (!matched) continue;
            // 子菜单自身高亮（添加 active，CSS 若不生效再附加样式）
            sa.classList.add('active');
            // 增加边框 + 底色视觉提示（若样式表未定义 .collapse-item.active）
            try {
                sa.style.setProperty('background', '#E8F4FF', 'important');
                sa.style.setProperty('color', '#1677CB', 'important');
                sa.style.setProperty('border-left', '4px solid #1677CB');
                sa.style.setProperty('padding-left', '18px');
                sa.style.setProperty('border-radius', '2px');
            } catch (_) { /* noop */ }
            // 祖先 li.nav-item（父菜单）加 active
            var p = null;
            if (sa.closest) p = sa.closest('li.nav-item');
            if (p) p.classList.add('active');
        }
    }

    function run() {
        try { fixCollapse(); } catch (e1) { if (window.console) console.warn(e1); }
        try { fixActive();   } catch (e2) { if (window.console) console.warn(e2); }
    }

    function schedule() {
        // Run once, scheduled after all DOMContentLoaded handlers (incl sb-admin-2 init)
        setTimeout(run, 0);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', schedule);
    } else {
        schedule();
    }
})();
