/**
 * ZhiCms 后台 TinyMCE 编辑器初始化脚本
 * 编辑器资源本地化于 /public/manage/tinymce/
 */

// TinyMCE 资源基础路径（远程托管）
var TINYMCE_BASE_URL = 'https://tinymce.vt5.cn';

/**
 * 获取平台样式配置
 */
function getPlatformStyle(url) {
    if (/pinduoduo\.com|yangkeduo\.com/i.test(url)) {
        return { color: '#e02e24', text: '去拼多多购买', icon: '🛒' };
    }
    if (/jd\.com/i.test(url)) {
        return { color: '#e4393c', text: '去京东购买', icon: '🛒' };
    }
    if (/vip\.com|vipshop\.com/i.test(url)) {
        return { color: '#cd2e6b', text: '去唯品会购买', icon: '🛒' };
    }
    if (/taobao\.com|tmall\.com/i.test(url)) {
        return { color: '#ff2e54', text: '去淘宝购买', icon: '🛒' };
    }
    return { color: '#ff5000', text: '去购买', icon: '🛒' };
}

/**
 * 将 [ZhiCmsUrl]链接[/ZhiCmsUrl] 转换为 HTML 锚点（编辑器内预览）
 */
function convertZhiCmsUrlTags(editor) {
    var content = editor.getContent();
    var newContent = content.replace(
        /\[ZhiCmsUrl\]([\s\S]*?)\[\/ZhiCmsUrl\]/gi,
        function(match, url) {
            url = url.trim();
            var style = getPlatformStyle(url);
            return '<a href="' + url + '" class="zhicms-url-tag" target="_blank" style="display:inline-block;margin:8px 0;padding:6px 16px;background:' + style.color + ';color:#fff;border-radius:20px;text-decoration:none;font-size:13px;">' + style.icon + ' ' + style.text + '</a>';
        }
    );
    if (newContent !== content) {
        editor.undoManager.transact(function() {
            editor.setContent(newContent);
        });
    }
}

/**
 * 将 HTML 中的商品链接转换回 [ZhiCmsUrl] 标签（保存前）
 */
function convertHtmlToZhiCmsUrlTags(editor) {
    var content = editor.getContent();
    var tmp = document.createElement('div');
    tmp.innerHTML = content;
    var links = tmp.querySelectorAll('a.zhicms-url-tag');
    links.forEach(function(link) {
        var url = link.getAttribute('href');
        if (url) {
            var text = document.createTextNode('[ZhiCmsUrl]' + url + '[/ZhiCmsUrl]');
            link.parentNode.replaceChild(text, link);
        }
    });
    editor.setContent(tmp.innerHTML);
}

/**
 * 初始化 ZhiCms TinyMCE 编辑器
 * 
 * @param {string} selector - 选择器，如 '#editor'
 * @param {object} options - 可选配置
 */
function initZhiCmsEditor(selector, options) {
    options = options || {};
    
    var uploadType = options.uploadType || 'article';
    var height = options.height || 500;
    
    // 关键：在 init 之前强制指定 baseURL 为本地路径
    if (typeof tinymce !== 'undefined') {
        tinymce.baseURL = TINYMCE_BASE_URL;
        tinymce.suffix = '.min';
    }
    
    tinymce.init({
        selector: selector,
        language: 'zh_CN',
        language_url: TINYMCE_BASE_URL + '/langs/zh_CN.js',
        base_url: TINYMCE_BASE_URL,
        suffix: '.min',
        license_key: 'gpl',
        
        height: height,
        menubar: false,
        branding: false,
        promotion: false,
        statusbar: true,
        
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount codesample',
        
        toolbar: 'undo redo | blocks | bold italic underline forecolor backcolor | ' +
                 'alignleft aligncenter alignright alignjustify | zhicmsurl | ' +
                 'bullist numlist outdent indent | removeformat | link image media table | code preview fullscreen',
        
        toolbar_mode: 'sliding',
        font_size_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt',
        
        // 图片上传配置（统一上传接口：index.php?r=manage/File/upload&type=xxx）
        images_upload_handler: function(blobInfo, progress) {
            return new Promise(function(resolve, reject) {
                var formData = new FormData();
                formData.append('file', blobInfo.blob(), blobInfo.filename());
                
                // 统一上传接口：主站域名 + index.php?r=manage/File/upload&type=xxx
                var uploadUrl = window.location.origin + '/index.php?r=manage/File/upload&type=' + uploadType;
                
                fetch(uploadUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.text();
                })
                .then(function(text) {
                    try {
                        var result = JSON.parse(text);
                        if (result.error === 0 && result.url) {
                            // 规范化 URL，确保不出现双斜杠
                            var cleanUrl = result.url.replace(/^\/+/, '');
                            resolve(window.location.origin + '/' + cleanUrl);
                        } else {
                            reject(new Error(result.message || '上传失败'));
                        }
                    } catch (e) {
                        reject(new Error('返回数据格式错误'));
                    }
                })
                .catch(function(error) {
                    reject(new Error('上传失败: ' + error.message));
                });
            });
        },
        
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; line-height: 1.6; padding: 10px; } img { max-width: 100%; height: auto; }',
        
        setup: function(editor) {
            // 注册购物车图标
            editor.ui.registry.addIcon('cart', '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>');
            
            editor.ui.registry.addButton('zhicmsurl', {
                icon: 'cart',
                tooltip: '插入商品链接 [ZhiCmsUrl]',
                onAction: function() {
                    var selectedText = editor.selection.getContent() || '';
                    var url = prompt('请输入电商产品链接（支持淘宝/京东/拼多多/唯品会等）：', selectedText);
                    if (url) {
                        editor.selection.setContent('[ZhiCmsUrl]' + url.trim() + '[/ZhiCmsUrl]');
                        setTimeout(function() {
                            convertZhiCmsUrlTags(editor);
                        }, 100);
                    }
                }
            });
            
            editor.on('init', function() {
                var content = editor.getContent();
                if (content.indexOf('[ZhiCmsUrl]') !== -1) {
                    setTimeout(function() {
                        convertZhiCmsUrlTags(editor);
                    }, 300);
                }
            });
        }
    });
}

function prepareEditorForSubmit(editorId) {
    if (typeof tinymce !== 'undefined') {
        var editor = tinymce.get(editorId);
        if (editor) {
            convertHtmlToZhiCmsUrlTags(editor);
            editor.save();
        }
    }
}

window.initZhiCmsEditor = initZhiCmsEditor;
window.prepareEditorForSubmit = prepareEditorForSubmit;
window.getPlatformStyle = getPlatformStyle;
window.convertZhiCmsUrlTags = convertZhiCmsUrlTags;
window.convertHtmlToZhiCmsUrlTags = convertHtmlToZhiCmsUrlTags;