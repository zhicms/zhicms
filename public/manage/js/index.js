
// MyMessage 初始化（容错处理，失败时不影响后续代码执行）
try {
    if (typeof MyMessage !== 'undefined' && MyMessage.message) {
        message = new MyMessage.message({
            iconFontSize: "20px",
            messageFontSize: "12px",
            showTime: 3000,
            align: "center",
            positions: {
                top: "50px",
                bottom: "10px",
                right: "10px",
                left: "10px"
            },
            message: "这是一条消息",
            type: "normal"
        });
    } else {
        message = null;
    }
} catch(e) {
    message = null;
}

// 容错的消息提示函数
function showMessage(title, type) {
    if (window.message && typeof message.add === 'function') {
        message.add(title, type || 'normal');
    } else {
        alert(title);
    }
}

function error(title) {
    showMessage(title, 'error');
}

function success(title) {
    showMessage(title, 'success');
}

function ZhiForm(obj, url, text, Title, Frame) {
	$(obj).Validform({
		ajaxPost: true,
		postonce: true,
		tiptype: function(msg, o, cssctl) {
			$("#save").html(text);
			$("#save").attr("disabled", true);

			if (o.type == 3) {
			}
		},
		callback: function(data) {
			
			if (data.status == 'y') {
				 if(data.windows=="pop"){
			      window.parent.resthispage();	
			      }else{
					window.location.href = url;
				  }
				}else if (data.status == 'n') {
				error(data.info);
				$("#save").removeAttr("disabled");
			    $("#save").html(Title);
				
				return false;	
			}


			$("#save").removeAttr("disabled");
			$("#save").html(Title);
		}


	});
}


function CloseError(){
 $('.alert-danger').hide();
}


//ajax get 方式
function ajaxget(url, infotxt){
var loadings= layer.msg(infotxt ? infotxt : '正在执行您的请求...', {icon: 16});
$(".ajaxbt").attr("disabled", true); 
 $.ajax({
         url: url,
         type: 'GET',
         dataType: 'json',
         success: function(data){
			  if(data.status=='n'){
				  layer.close(loadings);
				  error(data.info);
				  return false;
			  }else if(data.status=='y'){
				 layer.close(loadings);
				 success(data.info);
				 window.setTimeout("reloadpage()",3000);
				   $(".ajaxbt").removeAttr("disabled");
			  }else if(data.status=='indexpage'){
				  layer.close(loadings);
				 success(data.info);
				  $(".ajaxbt").removeAttr("disabled");
			  }
			 
		 }
   });	
	
}

function reloadpage(){
	 location.reload(); 
}
function locpage(){
 window.location.href='index.php?r=system/Finance/orderlist';	
}


//弹出层
function LayerFrame(title,w,h,url,anim,shade='null'){
layer.open({
  type: 2,
  skin: 'layui-layer-rim',
  anim: anim,
  title: title,
  shadeClose: false,
  shade: 0.7,
  area: [w+'px', h+'px'],
  content: url //iframe的url
}); 
}

// ZhiCms 通用图片上传函数（使用 fetch API，统一接口 + WebP 自动转换）
// type: 上传类型（article/page/items/manage/huan 等）
// elementId: file 输入框的 id
// callback: 成功回调函数 (url) => {}
function zhicmsUpload(type, elementId, callback) {
    var fileInput = document.getElementById(elementId);
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
        alert('请选择文件');
        return;
    }
    
    var file = fileInput.files[0];
    var formData = new FormData();
    formData.append('file', file);
    
    // 显示加载层（兼容 layer 未加载的情况）
    var loading = null;
    if (typeof layer !== 'undefined') {
        loading = layer.load(2);
    }
    
    // 统一上传接口：主站域名 + index.php?r=manage/File/upload&type=xxx
    var uploadUrl = window.location.origin + '/index.php?r=manage/File/upload&type=' + type;
    
    fetch(uploadUrl, {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.text();
    })
    .then(function(text) {
        if (loading !== null) layer.close(loading);
        console.log('[zhicmsUpload response length]', text ? text.length : 0);
        console.log('[zhicmsUpload response raw]', text);
        try {
            var data = JSON.parse(text);
            if (data.error === 0 && data.url) {
                callback(data.url);
            } else {
                alert(data.message || '上传失败');
            }
        } catch (e) {
            alert('上传失败：返回数据格式错误\n\n原始响应：' + (text ? text.substring(0, 500) : '(空响应)'));
        }
    })
    .catch(function(error) {
        if (loading !== null) layer.close(loading);
        alert('上传失败：' + error.message);
    });
}

// 兼容旧的 Uploadthis 函数（使用 fetch API 重写）
// obj: 目标输入框的 id
// thisid: file 输入框的 id
// 可通过 window.ZhiCmsUploadType 在页面顶部指定上传类型
function Uploadthis(obj, thisid) {
    var fileInput = document.getElementById(thisid);
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
        return;
    }
    
    // 优先使用全局指定的上传类型
    var type = window.ZhiCmsUploadType || 'items';
    
    zhicmsUpload(type, thisid, function(url) {
        $('#' + obj).val(url);
        // 如果有预览图元素
        $('.' + obj).html('<img src="' + url + '">');
    });
}

// ZhiCmsConfirm(询问内容,确定按钮名称,取消按钮名称,点击确定后执行url,执行等待提示语);
function ZhiCmsConfirm(title,btna,btnb,url,infotxt,shade=0.8){
layer.confirm(title, {
	skin: 'layui-layer-rim',
	icon:3,
	anim: 6,
	  shade: shade,
  btn: [btna,btnb] //按钮
}, function(){
  ajaxget(url,infotxt);
}, function(){
 
});
}
