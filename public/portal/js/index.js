
//表单ajax提交
function YunForm(obj,url,text,Title,Frame){
 $(obj).Validform({
	  ajaxPost:true,
	  postonce: true,
	  tiptype: function (msg, o, cssctl) {
		 $("#save").html("<i class=\"fa fa-spinner\"></i> "+text);
		 $("#save").attr("disabled", true); 
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
			//采集专用
			if(data.status=='caiji'){

			   if(data.info=='nologin'){
				  Frmeboxbnt("阿里妈妈 (选择复制窗体内json数据)","800","400",data.url,"下一步");
			   }

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
			   
			$('.alert-danger').show();
		    $('.alert-danger_txt').html(data.info);
			window.setTimeout(CloseError,3000); 
		   }
		
		   
		   $("#save").removeAttr("disabled");
		   $("#save").html(Title);
		}
		
		 
	}); 
}

function timeout(){
 var index = parent.layer.getFrameIndex(window.name); //先得到当前iframe层的索引
 parent.layer.close(index); //再执行关闭   
}						

function CloseError(){
 $('.alert-danger').hide();
}


function showalert(Info,Title,url,btn){
	if(btn){
	  btnname=btn;	
	}else{
	  btnname="确认删除";	
	}
    bootbox.dialog({
        message: Title,
        title: Info,
        buttons: {
           
            danger: {
                label: btnname,
                className: "red",
                callback: function() {
                  ajaxget(url);
                }
            },
			 success: {
                label: "取消操作",
                className: "green",
                callback: function() {
                   // alert("great success");
                }
            }
        }
    });
}
//end #demo_7



//ajax get 方式
function ajaxget(url){
 layer.msg('正在执行您的请求', {icon: 16});
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
function locpage(){
 window.location.href='index.php?r=system/Finance/orderlist';	
}


function  FrameBox(obj,w,h,url){
   layer.open({
            type: 2,
            title:obj,
			 shift: 0,
            shadeClose: false,
            shade: false,
            maxmin: false, //开启最大化最小化按钮
            area: [w+'px', h+'px'],
			shade: [0.8, '#393D49'],
			scrollbar: true,
            content: url
			
        });	

}



  