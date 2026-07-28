
message = new MyMessage.message({
				/*默认参数，下面为默认项*/
				iconFontSize: "20px", //图标大小,默认为20px
				messageFontSize: "12px", //信息字体大小,默认为12px
				showTime: 3000, //消失时间,默认为3000
				align: "center", //显示的位置类型center,right,left
				positions: { //放置信息距离周边的距离,默认为10px
					top: "50px",
					bottom: "10px",
					right: "10px",
					left: "10px"
				},
				message: "这是一条消息", //消息内容,默认为"这是一条消息"
				type: "normal", //消息的类型，还有success,error,warning等，默认为normal
			});


function error(title){

message.add(title, "error");



}

function ZhiForm(obj, url, text, Title, Frame) {
	$(obj).Validform({
		ajaxPost: true,
		postonce: true,
		tiptype: function(msg, o, cssctl) {
			$("#save").html(text);
			$("#save").attr("disabled", true);
			//$('.alert-danger').hide();

			if (o.type == 3) {
				//toastr.error(msg);
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
function ajaxget(url){
var loadings= layer.msg('正在执行您的请求...', {icon: 16});
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
				 message.add(data.info, "success");
				 window.setTimeout("reloadpage()",3000);
				   $(".ajaxbt").removeAttr("disabled");
			  }else if(data.status=='indexpage'){
				  layer.close(loadings);
				 message.add(data.info, "success");
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



  

