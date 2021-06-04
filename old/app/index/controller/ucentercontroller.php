<?php
namespace app\index\controller;
obj('api/Api')->isCookies('ZhiCmsUser','index.php?r=index/index/login');

class ucenterController extends \app\base\controller\BaseController {
  
  public function index(){
    $uinfo=obj("index/global","controller")->finduser("y",$_COOKIE['ZhiCmsUser'],"cookie");
     if(!IS_POST){
       $type=$this->arg("type");
      if(!$type){
      
        $where[] = "`yun_like`.fid=`yun_forum`.id and `yun_like`.uid={$uinfo['id']} and  `yun_like`.`model` LIKE  'index'";
        $baseurl = "ucenter.html";
        $Page = obj('api/ApiData')->pageIndex("50", "like`,`yun_forum", $where, "`yun_forum`.`id` DESC", $baseurl);
        $this->Page=$Page;
    }
    if($type=='article'){

        $where[] = "`yun_like`.fid=`yun_article`.id and `yun_like`.uid={$uinfo['id']} and  `yun_like`.`model` LIKE  'article'";
        $baseurl = "ucenter.html?type=article";
        $Page = obj('api/ApiData')->pageIndex("50", "like`,`yun_article", $where, "`yun_article`.`id` DESC", $baseurl);
        $this->Page=$Page;

    }
       $this->uinfo=$uinfo;
       $this->display();
       exit;
     }else{

        $username=$this->arg("username");
        $password=$this->arg("password");
        if(!$username){
          exit(json_encode(array("info" => '请输入您的昵称', "status" => "n")));
        }
        if(!$password){
          exit(json_encode(array("info" => '请输入您的密码', "status" => "n")));
        }
        //昵称是否被占用
        $nicksql[] = "  `username` LIKE  '{$username}'";
        $nickdata = obj('api/ApiData')->Data_Select("user", $nicksql);
        if (!empty($nickdata)) {
              exit(json_encode(array("info" => '昵称被占用', "status" => "n")));
         }
        $where[]="  `mobile` LIKE  '{$_COOKIE['ZhiCmsUser']}'";
        $data['username'] = $username;
        $data['password'] = md5($password . 'zhicms');
        obj('api/ApiData')->Data_Updata("user", $data,$where);
        exit(json_encode(array("info" => "修改成功", "status" => "y")));

         
     }
  	
  }



  public function like(){
   $id=$this->arg("id");
   if(!is_numeric($id)){
    exit('error');
   }

   if($this->arg("model")=='index'){
    $like_table='like';
    $model_table='forum';
    $data['model']="index";
    $like_data['model']="index";
   }
   if($this->arg("model")=='article'){
     $like_table='like';
     $model_table='article';
     $data['model']="article";
     $like_data['model']="article";
   }
   $uinfo=obj("index/global","controller")->finduser("y",$_COOKIE['ZhiCmsUser'],"cookie");

   //查询是否喜欢过
   $where_like[]="`fid` ={$id} and `model` LIKE  '{$like_data['model']}' and `uid` ={$uinfo['id']}";
   $ret=obj("api/Apidata")->Data_Select($like_table,$where_like);
   if(!empty($ret)){
      exit(json_encode(array("info" => "你已经喜欢过了~", "status" => "n")));
   }

   $where[]="`id` ={$id}";
   $new=$forumret['like']+1;
   $data['like']=$new;
   obj('api/ApiData')->Data_Updata($model_table, $data,$where);
   $like_data['uid']=$uinfo['id'];
   $like_data['fid']=$id;
   $like_data['date']=date("Y-m-d H:i:s",time());
   obj('api/ApiData')->Inserts($like_table, $like_data);
   exit(json_encode(array("info" => "喜欢成功", "status" => "y")));
  }
  
  public function comsend(){
    $body=$_POST['mybody'];
    $preg = "/<script[\s\S]*?<\/script>/i";
    $newbody= preg_replace($preg,"",$body,3); 

    if(!$newbody){
       exit(json_encode(array("info" => "请填写评论", "status" => "n")));
    }

   if($this->arg("model")=='index'){
    $model='1';
   }
   if($this->arg("model")=='article'){
    $model='2';
   }
   if(!$model){
    exit(json_encode(array("info" => "请勿修改模型~", "status" => "n")));
   }

    $uinfo=obj("index/global","controller")->finduser("y",$_COOKIE['ZhiCmsUser'],"cookie");
    $data['uid']=$uinfo['id'];
    $data['aid']=$this->arg("id");
    $data['body']=$newbody;
    $data['date']=date("Y-m-d H:i:s",time());
    $data['model']=$model;
    obj("api/Apidata")->Inserts("comment",$data);
    exit(json_encode(array("info" => "评论成功", "status" => "y")));
  }
  


  
}