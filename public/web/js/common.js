/* =========================================================
 * ZhiCms 公共脚本（前端所有页面共享）
 * 整合自：public/footer.html 的全局函数
 * 加载位置：</body> 之前（非阻塞内容渲染，符合 SEO/性能最佳实践）
 * ========================================================= */
(function () {
    'use strict';

    /* 用户退出登录（AJAX） */
    window.userLogout = function () {
        if (!confirm('确定退出登录？')) return;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'index.php?r=index/login/logout&ajax=1', true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            try {
                var res = JSON.parse(xhr.responseText);
                if (res && res.status === 'y') {
                    location.href = 'index.php?r=index/index/index';
                } else {
                    alert((res && res.info) ? res.info : '退出失败');
                }
            } catch (e) {
                location.href = 'index.php?r=index/index/index';
            }
        };
        xhr.send();
    };

    /* 轻提示 Toast（顶部右侧浮层） */
    window.showToast = function (message, type) {
        type = type || 'success';
        var wrap = document.getElementById('ucToastWrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'ucToastWrap';
            wrap.className = 'uc-toast-wrap';
            document.body.appendChild(wrap);
        }
        var toast = document.createElement('div');
        toast.className = 'uc-toast ' + type;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<span class="uc-toast-body">' + message + '</span>' +
            '<button type="button" class="uc-toast-close" onclick="this.parentElement.remove()">✕</button>';
        wrap.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('show'); });
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
        }, 2500);
    };

})();
