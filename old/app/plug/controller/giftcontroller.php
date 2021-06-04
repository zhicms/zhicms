<?php
namespace app\plug\controller;
error_reporting('0');
class giftController extends \app\base\controller\BaseController
{
    public function index()
    {
        self::CheckMyLikeCookie();
        include CONFIG_PATH . 'seo.php';
        $this->seo_index = $seo_index;
        //加载幻灯
        $where[] = "`type` =0";
        $huan= obj("api/Apidata")->Data_Select("huan", $where,"`id` DESC ");
        $this->huan=$huan;

        //1F
        $a_f_where[]="`f` =1 and `lock` =0";
        $this->a_f_ret=obj("api/Apidata")->Data_Select("plug_gift", $a_f_where,"`id` DESC LIMIT 0 , 20 ");

         //2F
        $b_f_where[]="`f` =2 and `lock` =0";
       $this->b_f_ret=obj("api/Apidata")->Data_Select("plug_gift", $b_f_where,"`id` DESC LIMIT 0 , 20 ");

         //3F
        $c_f_where[]="`f` =3 and `lock` =0";
       $this->c_f_ret=obj("api/Apidata")->Data_Select("plug_gift", $c_f_where,"`id` DESC LIMIT 0 , 20 ");

         //4F
        $d_f_where[]="`f` =4 and `lock` =0";
       $this->d_f_ret=obj("api/Apidata")->Data_Select("plug_gift", $d_f_where,"`id` DESC LIMIT 0 , 20 ");

        //5F
        $e_f_where[]="`f` =5 and `lock` =0";
       $this->e_f_ret=obj("api/Apidata")->Data_Select("plug_gift", $e_f_where,"`id` DESC LIMIT 0 , 20 ");


        //6F
       $f_f_where[]="`f` =6 and `lock` =0";
       $this->f_f_ret=obj("api/Apidata")->Data_Select("plug_gift", $f_f_where,"`id` DESC LIMIT 0 , 20 ");


       //查询攻略
       $where_gl[]="1";
       $this->glret=obj("api/Apidata")->Data_Select("plug_gift_gonglue", $where_gl,"`id` DESC LIMIT 0 , 21 ");



        $this->display();
    }

    public function CheckMyLikeCookie(){
         if(!isset($_COOKIE['mylike'])){
            $arr_str=serialize(array());
            setcookie("mylike",$arr_str); 
         }
       
    }


    public function mylike(){
        error_reporting('0');
        $mylike=$_COOKIE['mylike'];
        $arr = unserialize($mylike);

        $this->arr=$arr;

        //循环喜欢列表
      foreach ($arr as $key => $value) {
         
         $items_arr[]=self::itemsinfo($value,"y");


      }

      //数组排序
      if($this->arg("price")=="up"){

       // $items_arr = self::my_sort($items_arr,'price','SORT_DESC','SORT_NUMERIC'); 
          foreach($items_arr as $val){
            $key_arrays[]=$val['price'];
          }
        array_multisort($key_arrays,SORT_DESC,SORT_NUMERIC,$items_arr);

      }

       if($this->arg("price")=="down"){

         foreach($items_arr as $val){
            $key_arrays[]=$val['price'];
          }
         array_multisort($key_arrays,SORT_ASC,SORT_NUMERIC,$items_arr);
      }

      if($this->arg("like")=="o"){

         foreach($items_arr as $val){
            $key_arrays[]=$val['like'];
          }
         array_multisort($key_arrays,SORT_DESC,SORT_NUMERIC,$items_arr);
      }


        $this->count=count($items_arr);
        $this->items_arr=$items_arr;
        self::CheckMyLikeCookie();
        $this->display();
    }

   
    public function search(){
        $keywords=urldecode($this->arg("key"));

        $px="`id` DESC";
        if( !$this->arg('price') && !$this->arg('like')){
            $px="`id` DESC";
        }
        if($this->arg("price")=="up"){

            $px="`price` DESC ";
        }
        if($this->arg("price")=="down"){

            $px="`price` ASC  ";

        }
        if($this->arg("like")=="o"){
            $px="`like` DESC ";
        }
        $where[]=" `lock` =0 and  `itemstitle` LIKE  '%{$keywords}%'";
        $baseurl = url($route='plug/gift/search/key=<key>', $params=array('key'=>urlencode($this->arg("key"))));
        $Page = obj('api/ApiData')->Page("120", "plug_gift", $where, $px, $baseurl);
        $this->Page = $Page;
        $this->keywords=$keywords;

        
          $this->urldecodekey=$this->arg("key");
       

        
        self::CheckMyLikeCookie();
        $this->display();
    }



    public function itemsinfo($id,$lock){
        if($lock=="y"){

            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("plug_gift",$where);
            return $ret;

        }
    }


    public function findgift(){

         $this->url_a=url($route='plug/gift/findgift', $params=array());
         $baseurl = url($route='plug/gift/findgift', $params=array());

        //如果是小分类
        if($this->arg("id")){
            $id=$this->arg("id");
            if(!is_numeric($id)){
                self::e_404();
                exit;
            }

            //获取分类详情
            $where_pid[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("plug_gift_type", $where_pid);
            $this->ret=$ret;
            $baseurl=url($route='plug/gift/findgift/id=<id>', $params=array('id'=>$id));
            $this->url_a=url($route='plug/gift/findgift/id=<id>', $params=array('id'=>$id));
        }

       if( !$this->arg('price') && !$this->arg('like')){
            $px="`id` DESC";
        }
        if($this->arg("price")=="up"){

            $px="`price` DESC ";
        }
        if($this->arg("price")=="down"){

            $px="`price` ASC  ";

        }
        if($this->arg("like")=="o"){
            $px="`like` DESC ";
        }

        if(!$this->arg("id")){

            $where[]="`lock` =0";
        }else{

            $where[]="`type` ={$id}";
        }

        
        $Page = obj('api/ApiData')->Page("120", "plug_gift", $where, $px, $baseurl);
        $this->Page = $Page;
         self::CheckMyLikeCookie();
        $this->display();
    }

   public function page(){
        $id=$this->arg("id");
        if(!is_numeric($id)){
            self::e_404();
            exit;
        }
        $where_view[] = "`id` ={$id}";
        $viewret = obj("api/Apidata")->Data_Select("page", $where_view);
        if (empty($viewret)) {
           self::e_404();
            exit;
        }
        if($_COOKIE['ZhiCmsUser']!=''){
         $this->uinfo=obj("plug/giftglobal","controller")->finduser("y",$_COOKIE['ZhiCmsUser'],"cookie");
        }
        self::CheckMyLikeCookie();
        $this->viewret=$viewret;
        $this->display();
    }
    public function view(){
        $id=$this->arg("id");
        if(!is_numeric($id)){
            self::e_404();
            exit;
        }

        $where[]="`id` ={$id}";
        $ret=obj("api/Apidata")->Data_Select("plug_gift", $where);

        if(empty($ret)){
           self::e_404();
            exit;
        }

        //获取分类详情
        $where_pid[]="`id` ={$ret['type']}";
        $type=obj("api/Apidata")->Data_Select("plug_gift_type", $where_pid);
        $this->ret=$ret;
        $this->type=$type;

        //猜你喜欢
        $where_like[]="`type` ={$ret['type']} and `lock` =0";
        $likeret=obj("api/Apidata")->Data_Select("plug_gift", $where_like,"`id` DESC LIMIT 0, 40 ");

        $this->likecount=obj("api/Apidata")->Data_Count("plug_gift",$where_like);

        $this->likeret=$likeret;
        self::CheckMyLikeCookie();

        //判断是天猫宝贝还是淘宝宝贝
        preg_match_all('/tmall/', $ret['link'], $matches);

        if(empty($matches['0'])){
          $gobtntxt="淘宝";
        }else{
          $gobtntxt="天猫";
        }

        $this->gobtntxt=$gobtntxt;
        $this->display();
    }


    public function topic(){
        // $this->plug_gift_send=obj("index/global","controller")->getlist('plug_gift_send','y','1');
       //  $this->plug_gift_mudi=obj("index/global","controller")->getlist('plug_gift_mudi','y');
       //  $this->plug_gift_sendtime=obj("index/global","controller")->getlist('plug_gift_sendtime','y');
       //  $this->plug_gift_special=obj("index/global","controller")->getlist('plug_gift_special','y');
         self::CheckMyLikeCookie();
         $this->display();
    }

    public function topiclist(){
        error_reporting('0');
        self::CheckMyLikeCookie();
        //根据type判断
        $type=$this->arg("type");
        $id=$this->arg("id");
        if(!is_numeric($id)){
            self::e_404();
            exit;
        }


        


        if($type=="send"){

          $where[] = "`send` LIKE  '%\"{$id}\"%'";
          $where_pid[]="`id` ={$id}";
          $table="plug_gift_send";
          $type="send";

        }

        if($type=="goal"){

          $where[] = "`mudi` LIKE  '%\"{$id}\"%'";
          $where_pid[]="`id` ={$id}";
          $table="plug_gift_mudi";
          $type="goal";

        }

          if($type=="sendtime"){

          $where[] = "`sendtime` LIKE  '%\"{$id}\"%'";
          $where_pid[]="`id` ={$id}";
          $table="plug_gift_sendtime";
          $type="sendtime";

        }

        if($type=="special"){

          $where[] = "`special` LIKE  '%\"{$id}\"%'";
          $where_pid[]="`id` ={$id}";
          $table="plug_gift_special";
          $type="special";

        }

        if( !$this->arg('price') && !$this->arg('like')){
            $px="`id` DESC";
        }
        if($this->arg("price")=="up"){

            $px="`price` DESC ";
        }
        if($this->arg("price")=="down"){

            $px="`price` ASC  ";

        }
        if($this->arg("like")=="0"){
            $px="`like` DESC ";
        }

        //查询分类信息
        $ret=obj("api/Apidata")->Data_Select($table, $where_pid);
        $this->ret=$ret;

        if($type=="special"){

            if($ret['pid']=='1'){
                $this->giftname="1";
            }
        }

        $baseurl = url($route='plug/gift/topiclist/id=<id>/type=<type>', $params=array('id'=>$id,'type'=>$type));
        $Page = obj('api/ApiData')->Page("120", "plug_gift", $where, $px, $baseurl);
        $this->Page = $Page;

       // print_r($Page);
        $this->display();
    }


    //攻略
    public function strategy(){
       if(!isset($_COOKIE['mylike_gl'])){
           $arr_str=serialize(array("0"));
            setcookie("mylike_gl",$arr_str); 
         }
        $id=$this->arg("id");
        if($id){
            if(!is_numeric($id)){
            self::e_404();
            exit;
          }
        }
        $where[]="1";
        if($this->arg("type")=="send"){

            $where[]="`gonglue_send` ={$id}";
            $table="plug_gift_gonglue_send";
            $navurl=url($route='plug/gift/strategy/id=<id>/type=<type>', $params=array('id'=>$id,'type'=>'send'));

        }
        if($this->arg("type")=="whysend"){

            $where[]="`gonglue_whysend` ={$id}";
            $table="plug_gift_gonglue_whysend";
            $navurl=url($route='plug/gift/strategy/id=<id>/type=<type>', $params=array('id'=>$id,'type'=>'whysend'));

        }
         if($this->arg("type")=="sendtime"){

            $where[]="`gonglue_sendtime` ={$id}";
            $table="plug_gift_gonglue_sendtime";
            $navurl=url($route='plug/gift/strategy/id=<id>/type=<type>', $params=array('id'=>$id,'type'=>'sendtime'));

        }

        $baseurl=url($route='plug/gift/strategy', $params=array());

        if($this->arg("type")){
           $where_pid[]="`id` =$id";
           $type=obj("api/Apidata")->Data_Select($table, $where_pid);
           $this->ret=$type;
           $baseurl = url($route='plug/gift/strategy/id=<id>/type=<type>', $params=array('id'=>$id,'type'=>'send'));
        }

       
        $Page = obj('api/ApiData')->Page("120", "plug_gift_gonglue", $where,"`id` DESC", $baseurl);
        $this->Page = $Page;
        $this->display();
    }

    public function strategy_view(){
      if(!isset($_COOKIE['mylike_gl'])){
           $arr_str=serialize(array("0"));
            setcookie("mylike_gl",$arr_str); 
         }
        $id=$this->arg("id");
        if(!is_numeric($id)){
            self::e_404();
            exit;
          }
        $where[]="`id` ={$id}";
        $ret=obj("api/Apidata")->Data_Select("plug_gift_gonglue", $where);
        $this->ret=$ret;

        //解析商品
       
        $newbody=preg_replace_callback('/\[ZhiCmsID][1-9]\d*\[\/ZhiCmsID]/','self::find_q_items',$ret['mybody']);
        $this->newbody=$newbody;
        //获取喜欢的宝贝
        $where_like[]="`lock` =0";
        $likeret=obj("api/Apidata")->Data_Select("plug_gift", $where_like,"`like` DESC LIMIT 0, 5 ");
        $this->likeret=$likeret;

        //相关推荐算法
        $gonglue_send=$ret['gonglue_send']; //送给谁
        $gonglue_sendtime=$ret['gonglue_sendtime'];//送礼时间
        $gonglue_whysend=$ret['gonglue_whysend'];//为什么送

        if($gonglue_send!='0'){
            $tjsql['0']="`gonglue_send` ={$gonglue_send}";
        }
        if($gonglue_sendtime!='0'){
            $tjsql['1']="`gonglue_sendtime` ={$gonglue_sendtime}";
        }
        if($gonglue_whysend!='0'){
            $tjsql['2']="`gonglue_whysend` ={$gonglue_whysend}";
        }

        $randsql=array_rand($tjsql,1);

        $about_where[]=$$tjsql[$randsql];

        //查询相关礼物
        $aboutitemsret=obj("api/Apidata")->Data_Select("plug_gift_gonglue", $about_where,"`id` DESC LIMIT 0, 6 ");

        $this->aboutitemsret=$aboutitemsret;
        
        $this->display();
    }


    public function question(){
        if(!isset($_COOKIE['mylike_gl'])){
           $arr_str=serialize(array("0"));
            setcookie("mylike_ask",$arr_str); 
         }
        $id=$this->arg("id");

        if($id){
           if(!is_numeric($id)){
            self::e_404();
            exit;
         }
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("plug_gift_wendatype", $where);

            if(empty($ret)){
            self::e_404();
             exit;
            }
             //判断分类是否顶级分类
              if($ret['pid']=="0"){

                $where_q[]="`qid` ={$id}";
                
              }else{
                $where_q[]="`pid` ={$id}";
              }
                $baseurl = url($route='plug/gift/question/id=<id>', $params=array('id'=>$id));
          }else{

             $where_q[]="1";
             $baseurl = url($route='plug/gift/question', $params=array());
          }
        
        $Page = obj('api/ApiData')->Page("60", "plug_question", $where_q, "`id` DESC", $baseurl);
        $this->Page = $Page; 
        $this->ret=$ret;
        $typelist=obj("plug/giftglobal","controller")->getgllist('plug_gift_wendatype','y');
        $cat=new \ZhiCms\ext\Category(array('id','pid','name','cname'));
        $s=$cat->getTree($typelist);//获取分类数据树结构
        $this->s=$s;
        self::CheckMyLikeCookie();

        //获取上级分类
       if($ret['pid']!=0){

         $where_p[]="`id` ={$ret['pid']}";
         $ret_p=obj("api/Apidata")->Data_Select("plug_gift_wendatype", $where_p);

         $this->ret_p=$ret_p;



       }



        $this->display();
    }

    public function questionview(){
      if(!isset($_COOKIE['mylike_gl'])){
           $arr_str=serialize(array("0"));
            setcookie("mylike_ask",$arr_str); 
         }
        $id=$this->arg("id");
        if(!is_numeric($id)){
            self::e_404();
            exit;
          }
        $where[]="`id` ={$id}";
        $ret=obj("api/Apidata")->Data_Select("plug_question", $where);
        $this->ret=$ret;


        $newbody=preg_replace_callback('/\[ZhiCmsID][1-9]\d*\[\/ZhiCmsID]/','self::find_q_items',$ret['mybody']);

       // $this->items=$items;

       $this->newbody=$newbody;


        //获取喜欢的宝贝
        $where_like[]="`lock` =0";
        $likeret=obj("api/Apidata")->Data_Select("plug_gift", $where_like,"`like` DESC LIMIT 0, 5 ");
        $this->likeret=$likeret;

        //其他问题处理
        $qt=$ret['qt'];


        $newqt=explode("\n", $qt);

        $this->newqt=$newqt;

        $this->display();
    }

    public function find_q_items($id){
        error_reporting('0');
        if(!$id){
           self::e_404();
           exit;
        }
        $tbk= new \ZhiCms\ext\Tbk;
    	include CONFIG_PATH . 'siteconfig.php';
        foreach ($id as $key => $value) {
            preg_match_all('/[1-9]\d*/', $value, $itemsid);
            $items= $itemsid['0']['0'];
			$where[]="`id` ={$items}";
            $ret=obj("api/Apidata")->Data_Select("plug_gift", $where);
			if(empty($ret)){
            $info=$tbk->getinfo($Siteinfo['appkey'],$Siteinfo['secretKey'],$items);
            $url=url($route='go/tb/itemiid/id=<id>', $params=array('id'=>$info['num_iid']));
            $price=obj('api/Api')->moneytype($info['zk_final_price']);
			$html="<div class=\"global_gift_box u-items-li\" data=\"{$info['num_iid']}\" >
            <div class=\"global_items_box\">
             <div class=\"global_items_box_pic\">
             <div class=\"poplike popid_{$info['num_iid']}\" itemsid=\"{$info['num_iid']}\" style=\"margin-left:390px; margin-top:20px;\"></div>
              <a href=\"{$url}\" target=\"_blank\"><img src=\"{$info['pict_url']}\" /></a></div>
             <div class=\"global_items_box_price\"><span>价格：<span class=\"global_gift_price\"><span class=\"rmbfh\">￥</span>{$price}</span></span><a href=\"{$url}\"  class=\"global_items_box_infobtn\" target=\"_blank\">查看详情</a></div></div></div>";
            return $html;
            }
			 $url=url($route='plug/gift/view/id=<id>', $params=array('id'=>$ret['id']));
            $price=obj('api/Api')->moneytype($ret['price']);
            $html="<div class=\"global_gift_box u-items-li\" data=\"{$ret['id']}\" >
           <div class=\"global_items_box\">
             <div class=\"global_items_box_pic\">
             <div class=\"poplike popid_{$ret['id']}\" itemsid=\"{$ret['id']}\" style=\"margin-left:390px; margin-top:20px;\"></div>
              <a href=\"{$url}\" target=\"_blank\"><img src=\"{$ret['pic']}\" /></a></div>
             <div class=\"global_items_box_price\"><span>价格：<span class=\"global_gift_price\"><span class=\"rmbfh\">￥</span>{$price}</span></span><a href=\"{$url}\"  class=\"global_items_box_infobtn\" target=\"_blank\">查看详情</a></div></div></div>";

            return $html;
		}	 
        }

	



    public function gongluelike(){
        error_reporting('0');

        $gonglueid=$this->arg("gonglue_id");
        $mylike=$_COOKIE['mylike_gl'];
        $arr = unserialize($mylike);


        //读取数据库商品信息
        $where[]="`id` ={$gonglueid}";
        $ret=obj("api/Apidata")->Data_Select("plug_gift_gonglue", $where);
        if(empty($ret)){
          self::e_404();
            exit;
        }
        if(in_array($gonglueid, $arr)){

           echo $ret['like'];
            exit;

        //没点赞
        }else{

        $newlike=$ret['like']+1;
        $data['like']=$newlike;
        obj("api/Apidata")->Data_Updata("plug_gift_gonglue",$data,$where);
        array_push($arr,$gonglueid);
        $arr = array_unique($arr);
        $arr = array_values($arr);
        $arr_str = serialize($arr);
        setcookie("mylike_gl",$arr_str);
      //  echo json_encode(array("like"=>$newlike));
        echo $newlike;
        exit;

        }
       
    }

    public function asklike(){
       error_reporting('0');

        $ask_id=$this->arg("ask_id");
        $mylike=$_COOKIE['mylike_ask'];
        $arr = unserialize($mylike);


        //读取数据库商品信息
        $where[]="`id` ={$ask_id}";
        $ret=obj("api/Apidata")->Data_Select("plug_question", $where);
        if(empty($ret)){
          self::e_404();
            exit;
        }
        if(in_array($ask_id, $arr)){
           echo $ret['like'];
            exit;

        //没点赞
        }else{

        $newlike=$ret['like']+1;
        $data['like']=$newlike;
        obj("api/Apidata")->Data_Updata("plug_question",$data,$where);
        array_push($arr,$ask_id);
        $arr = array_unique($arr);
        $arr = array_values($arr);
        $arr_str = serialize($arr);
        setcookie("mylike_ask",$arr_str);
      //  echo json_encode(array("like"=>$newlike));
        echo $newlike;
        exit;  
        } 
    }

    public function finditems($id,$lock){

        if($lock=="y"){
            $where[]="`id` ={$id}";
            $ret=obj("api/Apidata")->Data_Select("plug_gift", $where);
            return $ret;

        }

    }
  

    //喜欢
    public function like(){

        $id=$this->arg("id");
        $ac=$this->arg('ac');
        if(!is_numeric($id)){
           self::e_404();
            exit;

        }
        //读取数据库商品信息
        $where[]="`id` ={$id} and `lock` =0";
        $ret=obj("api/Apidata")->Data_Select("plug_gift", $where);

       //添加
       if($ac=='add'){ 
        $newlike=$ret['like']+1;
        $data['like']=$newlike;
        obj("api/Apidata")->Data_Updata("plug_gift",$data,$where);

        $mylike=$_COOKIE['mylike'];
        $arr = unserialize($mylike);
        array_push($arr,$id);
        $arr = array_unique($arr);
        $arr = array_values($arr);
        $arr_str = serialize($arr);
        setcookie("mylike",$arr_str);  
       // print_r(unserialize($mylike));
        exit;
        
        }
        //移除
        if($ac=='remove'){
            $mylike=$_COOKIE['mylike'];
            $arr = unserialize($mylike);
            unset($arr[array_search($id , $arr)]);
            $arr_str = serialize($arr);
            setcookie("mylike",$arr_str);  
            exit;

        }

        
    }

    // x款样式
    public function itemsx($lock,$typeid){
       $where[]="  `type` ={$typeid}";
       $ret=obj("api/Apidata")->Data_Count("plug_gift", $where);
       return $ret;

    }



    //加载喜欢并构造全局
    public function globallike(){
         $mylike=$_COOKIE['mylike'];
         $arr = unserialize($mylike);

         echo count($arr);
    }


    public function finduserinfo($lock, $uid)
    {
        if ($lock == "y") {
            $where[] = "`id` ={$uid}";
            $ret = obj("api/Apidata")->Data_Select("user", $where);
            return $ret;
        }
    }
   
   public function e_404(){

      $this->display('app/plug/view/gift/e_404');
   }

   public function ulink(){
    $where[]="1";
    $ret=obj("api/Apidata")->Data_Select("link", $where,"`px` DESC ");
    return $ret;
   }
}