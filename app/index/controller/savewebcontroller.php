<?php
namespace app\index\controller;
error_reporting('0');
class savewebController extends \app\base\controller\BaseController
{

	public function index(){

		include CONFIG_PATH . 'siteconfig.php';
        include CONFIG_PATH . 'giftseo.php';

		self::createShortCut($SEO['index_title'].'.url',$Siteinfo['hosturl'],$Siteinfo['hosturl'].'/favicon.ico');

		echo 'save...';

	}

	public function createShortCut($filename, $url, $icon=''){

    // 创建基本代码
    $shortCut = "[InternetShortcut]\r\nIDList=[{000214A0-0000-0000-C000-000000000046}]\r\nProp3=19,2\r\n";
    $shortCut .= "URL=".$url."\r\n";
    if($icon){
        $shortCut .= "IconFile=".$icon."";
    }

    header("content-type:application/octet-stream");

    // 获取用户浏览器
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $encode_filename = rawurlencode($filename);

    // 不同浏览器使用不同编码输出
    if(preg_match("/MSIE/", $user_agent)){
        header('content-disposition:attachment; filename="'.$encode_filename.'"');
    }else if(preg_match("/Firefox/", $user_agent)){
        header("content-disposition:attachment; filename*=\"utf8''".$filename.'"');
    }else{
        header('content-disposition:attachment; filename="'.$filename.'"');
    }

    echo $shortCut;

}

}