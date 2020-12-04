<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
class IndexController extends \app\base\controller\BaseController
{
    public function index()
    {
    	$this->pagetext=array("后台首页");

    	
    	//加载公告
		include CONFIG_PATH . 'version.php';
		include CONFIG_PATH . 'siteconfig.php';
        $token= new \ZhiCms\ext\weixin;
    	$ret=obj("api/Api")->object_array(json_decode($token->http($Siteinfo['apiurl']."?app.site.index&v={$v}")));
        $this->v=$ret;
        $this->display();
    }
   


   /*删除缓存*/
    public function delcache(){

        self::deldir(ROOT_PATH . 'data/cache/tpl');
        exit(json_encode(array("info" => "清除缓存成功", "status" => "y")));
     }
	 
public function deldir($dir) {
   //先删除目录下的文件：
   $dh=opendir($dir);
   while ($file=readdir($dh)) {
      if($file!="." && $file!="..") {
         $fullpath=$dir."/".$file;
         if(!is_dir($fullpath)) {
            unlink($fullpath);
         } else {
            deldir($fullpath);
         }
      }
   }
 
   closedir($dh);
   //删除当前文件夹：
   if(rmdir($dir)) {
      return true;
   } else {
      return false;
   }
}


     //文件下载并解压替换
     public function downloadfile(){

        //获取本地版本信息
        include CONFIG_PATH . 'version.php';
        include CONFIG_PATH . 'siteconfig.php';
         $token= new \ZhiCms\ext\weixin;
         $ret=obj("api/Api")->object_array(json_decode($token->http($Siteinfo['apiurl']."?app.site.index&v={$v}")));
         
		 $filename=CONFIG_PATH."zhicms.zip";
         if (!file_exists($filename)) {
         //下载文件到本地
         file_put_contents($filename,file_get_contents($ret['data']['vzip']));
         }

         //加载zip类并解压
         $zip=new \ZhiCms\ext\Zip;
         $file=$zip->decompress($filename);
         $sqlfile=include CONFIG_PATH."sql.php";

         //判断有没有数据库语句
         if(file_exists(CONFIG_PATH."sql.php")){

             //判断语句条数
             if(count($sql)>1){
                foreach ($sql as $key => $value) {
                    
                    obj("api/ApiData")->thisquery($value[$key]);
                }

             }else{
                obj("api/ApiData")->thisquery($sql['0']);

             }

         }

        //删除sql语句文件
        unlink(CONFIG_PATH."sql.php");

        //删除压缩包
        unlink($filename);
        //self::deldir(ROOT_PATH . 'framework');
         exit(json_encode(array("info" => "升级成功", "status" => "y")));

 

     }


   
}