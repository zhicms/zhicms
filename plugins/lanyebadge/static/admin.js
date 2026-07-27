(function(){
    // 复制弹窗
    var modal = document.getElementById('copyModal');
    var modalUrl = document.getElementById('modalUrl');
    var modalHtml = document.getElementById('modalHtml');
    var closeBtn = document.querySelector('.ly-modal-close');
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.action-copy');
        if(btn){
            modalUrl.value = btn.getAttribute('data-url');
            modalHtml.value = btn.getAttribute('data-html');
            modal.classList.add('active');
        }
    });
    closeBtn.addEventListener('click', function(){ modal.classList.remove('active'); });
    window.addEventListener('click', function(e){ if(e.target === modal) modal.classList.remove('active'); });

    function copyToClipboard(text){
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        } else {
            return new Promise(function(resolve, reject){
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try {
                    document.execCommand('copy');
                    resolve();
                } catch (e) {
                    reject(e);
                }
                document.body.removeChild(textarea);
            });
        }
    }

    document.getElementById('copyUrlBtn').addEventListener('click', function(){
        copyToClipboard(modalUrl.value).then(function(){
            alert('URL 已复制');
            modal.classList.remove('active');
        });
    });
    document.getElementById('copyHtmlBtn').addEventListener('click', function(){
        copyToClipboard(modalHtml.value).then(function(){
            alert('HTML 已复制');
            modal.classList.remove('active');
        });
    });
})();