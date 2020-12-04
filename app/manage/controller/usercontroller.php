<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
define("yaoqingnum","10"); //邀请人数
class userController extends \app\base\controller\BaseController
{

    public function index()
    {
    	$this->pagetext=array("用户管理","管理用户");
    	$where[] = "1";
    	$baseurl = "index.php?r=manage/user/index";
        $Page = obj('api/ApiData')->page("50", "user", $where, "`id` DESC", $baseurl);
        $this->Page = $Page;
        $this->display();
    }

    public function adduser(){

    	if(!IS_POST){
    		$this->pagetext=array("用户管理","添加用户");
    		$this->display();
    		exit;
    	}else{
            self::CheckForm();
            if(!$this->arg('password')){
             exit(json_encode(array("info" => "请填写密码", "status" => "n")));
            }
            $mobile=$this->arg("mobile");
             if (!preg_match("/1[3458]{1}\\d{9}\$/", $mobile)) {
                exit(json_encode(array("info" => "手机号码错误", "status" => "n")));
            }
            //昵称是否被占用
            $nicksql[] = "  `username` LIKE  '{$this->arg('username')}'";
            $nickdata = obj('api/ApiData')->Data_Select("user", $nicksql);
            if (!empty($nickdata)) {
                    exit(json_encode(array("info" => '昵称被占用', "status" => "n")));
            
            }
            $where[] = "`mobile` LIKE  '{$mobile}'";
            $regcount = obj('api/ApiData')->Data_Count('user', $where);
            if ($regcount >= '1') {
                exit(json_encode(array("info" => "手机号码已被注册", "status" => "n")));
            }

             $data = obj('api/Api')->Form($this->POSTarg());
             $data['password'] = md5($this->arg("password") . 'zhicms');
             $data['date']=date("Y-m-d H:i:s",time());
             obj('api/ApiData')->Inserts('user', $data);
             echo json_encode(array("info" => "保存成功", "status" => "y"));

    	}
    }

    public function editoruser(){
        if(!IS_POST){
            $this->pagetext=array("用户管理","编辑用户");
            $id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("user",$where);
            $this->ret=$ret;
            $this->html='<input type="hidden" name="id" value="'.$ret['id'].'" />';
            $this->display('app/manage/view/user/adduser');
            exit;
        }else{
            self::CheckForm();
            $mobile=$this->arg("mobile");
            $id=$this->arg("id");
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("user",$where);

            if($ret['username']!=$this->arg("username")){

             //昵称是否被占用
            $nicksql[] = "  `username` LIKE  '{$this->arg('username')}'";
            $nickdata = obj('api/ApiData')->Data_Select("user", $nicksql);
            if (!empty($nickdata)) {
              exit(json_encode(array("info" => '昵称被占用', "status" => "n")));
             }
            }

            if($ret['mobile']!=$this->arg("mobile")){
                $where_mobile[] = "`mobile` LIKE  '{$mobile}'";
                $regcount = obj('api/ApiData')->Data_Count('user', $where_mobile);
                if ($regcount >= '1') {
                    exit(json_encode(array("info" => "手机号码已被注册", "status" => "n")));
                  }
            }

            $data = obj('api/Api')->Form($this->POSTarg());
             if($this->arg("password")!=''){
                $data['password'] = md5($this->arg("password") . 'zhicms');
             }else{
                $data['password']=$ret['password'];
             }
            obj("api/Apidata")->Data_Updata("user",$data,$where);
            echo json_encode(array("info" => "保存成功", "status" => "y"));

        }
    }

   public function lockuser(){
        error_reporting('0');
        $id=$this->arg("id");
        $lock=$this->arg("lock");
        $where[]="`id` ={$id}";
        if($lock=="1"){ 
        $data['lock']="1";
        obj("api/Apidata")->Data_Updata("user",$data,$where);
        exit(json_encode(array("info" => "冻结成功", "status" => "y")));
       }elseif($lock=="2"){
        $data['lock']="0";
        obj("api/Apidata")->Data_Updata("user",$data,$where);
        exit(json_encode(array("info" => "解除冻结成功", "status" => "y")));

       }


   }

   public function comment(){
      $this->pagetext=array("评论管理","管理用户评论");
      $where[] = "1";
      $baseurl = "index.php?r=manage/user/comment";
      $Page = obj('api/ApiData')->page("50", "comment", $where, "`id` DESC", $baseurl);
      $this->Page = $Page;
      $this->display();

   }

   public function commenttitle($id,$model){
        $where[]="  `id` ={$id}";
        if($model=='1'){
         $ret=obj("api/Apidata")->Data_Select("forum",$where);
        }else if($model=='2'){
         $ret=obj("api/Apidata")->Data_Select("article",$where);
        }
        echo $ret['title'];
   }

   public function deletecomment(){
     error_reporting('0');
      $id=$this->arg("id");
      $where="  `id` ={$id}";
      obj('api/ApiData')->Deletethis('comment', $where);
      exit(json_encode(array("info" => "删除成功", "status" => "y")));

   }

    public function CheckForm(){
         if(!$this->arg('username')){
             exit(json_encode(array("info" => "请填写用户名", "status" => "n")));
         }

         if($this->arg('vest')=='null'){
             exit(json_encode(array("info" => "请选择身份", "status" => "n")));
         }
         if(!$this->arg('mobile')){
             exit(json_encode(array("info" => "请填写手机号码", "status" => "n")));
         }
         
    }
   


   }
   