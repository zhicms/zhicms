function ZhiForm(obj, url, text, Title, Frame) {
	$(obj).Validform({
		ajaxPost: true,
		postonce: true,
		tiptype: function(msg, o, cssctl) {
			$("#save").val(text);
			$("#save").attr("disabled", true);
			//$('.alert-danger').hide();

			if (o.type == 3) {
				//toastr.error(msg);
			}
		},
		callback: function(data) {
			if (data.status == 'y') {
				
						window.location.href = url;
					
				}else if (data.status == 'n') {
				//window.parent.fleshVerify();
			
				
				//layer.msg(data.info, function(){});
				layer.msg(data.info, {icon: 5});
				$("#save").removeAttr("disabled");
			    $("#save").val(Title);
				
				return false;	
			}else if(data.status=='checkcodeerror'){
				layer.msg(data.info, {icon: 5});
				window.parent.fleshVerify(data.domobj)
			}


			$("#save").removeAttr("disabled");
			$("#save").html(Title);
		}


	});
}


function ZhiCmsFrame(title,w,h,url){
 layer.open({
   type: 2,
   title:title,
   shift: 0,
   shadeClose: false,
   skin: 'layui-layer-lan',
   shade: false,
   maxmin: false, //开启最大化最小化按钮
   skin: 'layui-layer-rim', //加上边框
   area: [w+'px', h+'px'],	
   shade: [0.8, '#393D49'],
   scrollbar: true,
   resize:false,
   content: [url, 'no']
}); 
}

//ajax get 方式
function ajaxget(url){
var loadings= layer.msg('请稍等...', {icon: 16});
$(".ajaxbt").attr("disabled", true); 
 $.ajax({
         url: url,
         type: 'GET',
         dataType: 'json',
         success: function(data){
			  if(data.status=='n'){
				  layer.close(loadings);
				  layer.msg(data.info, function(){});
				  return false;
			  }else if(data.status=='y'){
				 layer.close(loadings);
				 layer.msg(data.info);
				 window.setTimeout("reloadpage()",3000);
				   $(".ajaxbt").removeAttr("disabled");
			  }else if(data.status=='indexpage'){
				  layer.close(loadings);
				 layer.msg(data.info);
				  $(".ajaxbt").removeAttr("disabled");
			  }
			 
		 }
   });	
	
}

function reloadpage(){
	 location.reload(); 
}