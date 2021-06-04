function  FrameBox(obj,w,h,url){
	
   Index=layer.open({
            type: 2,
            title:obj,
			 shift: 0,
            shadeClose: false,
            shade: false,
            maxmin: false, //开启最大化最小化按钮
			skin: 'layui-layer-rim', //加上边框
             area: [w+'px', h+'px'],	
			shade: [0.8, '#393D49'],
			scrollbar: true,
            content: [url, 'no']
			
		   
        });	
  



}

//表单ajax提交
function YunForm(obj,url,text,Title,Frame){
 $(obj).Validform({
	  ajaxPost:true,
	  postonce: true,
	  tiptype: function (msg, o, cssctl) {
		 $(".save").html("<i class=\"fa fa-spinner\"></i> "+text);
		 $(".save").attr("disabled", true); 
		 $('.alert-danger').hide();
		 
		 if (o.type == 3) {
			//toastr.error(msg);
		  }
		},
		callback:function(data){
			if(data.status=='thispage'){
				layer.msg(data.info);
				window.setTimeout(reloadpage,2000); 
			}
		   if(data.status=='y'){
			    if(url!=''){ 
					 //判断是不是pop浮动窗口
					 if(data.windows=='pop'){
						 window.parent.WindowsPop(url);
					 }else if(data.windows=='itemspop'){
						   parent.layer.msg(data.info);
						 window.setTimeout(timeout,2000); 
					 }else{
						  parent.layer.msg(data.info);
						 window.location.href=url;
					 }
				}
				
				if(Frame=='Frame'){
				  parent.layer.msg(data.info);
				  if(data.refurbish=='y'){
					  window.parent.refurbish(); 
				  }
				  var index = F.layer.getFrameIndex(window.name); //先得到当前iframe层的索引
                  parent.layer.close(index);
				}
			   
		   }else if(data.status=='n'){
			   
			    layer.msg(data.info, function(){});
			
		   }
		   if(data.status=='checkcodeerror'){
			     layer.msg(data.info, function(){});
				  var time = new Date().getTime();
                  document.getElementById('verifyImg').src= 'index.php?r=index/account/verificationCode/'+time;
		   }
		
		   
		   $(".save").removeAttr("disabled");
		   $(".save").html(Title);
		}
		
		 
	}); 
}



function ZhiCmsajaxget(url,infotxt){
 layer.msg(infotxt, {icon: 16});
$(".ajaxbt").attr("disabled", true); 
 $.ajax({
         url: url,
         type: 'GET',
         dataType: 'json',
         success: function(data){
			  if(data.status=='n'){
				  layer.msg(data.info, function(){});;
				  $(".ajaxbt").removeAttr("disabled");
				  return false;
			  }else if(data.status=='y'){
				   layer.msg(data.info);
				   window.setTimeout("reloadpage()",3000);
				   $(".ajaxbt").removeAttr("disabled");
			  }else if(data.status=='indexpage'){
				   layer.msg(data.info);
				   $(".ajaxbt").removeAttr("disabled");
			  }
			 
		 }
   });	
	
}


function reloadpage(){
	 location.reload(); 
}


$.fn.smartFloat = function() {
	var position = function(element) {
		var top = element.position().top, pos = element.css("position");
		$(window).scroll(function() {
			var scrolls = $(this).scrollTop();
			if (scrolls > top) {
				if (window.XMLHttpRequest) {
					element.css({
						position: "fixed",
						top: 0
					});	
				} else {
					element.css({
						top: scrolls
					});	
				}
			}else {
				element.css({
					position: pos,
					top: top
				});	
			}
		});
	};
	return $(this).each(function() {
		position($(this));						 
	});
};
