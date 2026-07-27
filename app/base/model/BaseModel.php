<?php

namespace app\base\model;

class BaseModel extends \ZhiCms\base\Model {
   
   /* 重写载入各种插件配置 */
   public function SET($name,$obj) {
    $data = array();
    if (is_file(CONFIG_PATH . '/' . $name . '.php')) {
        $data = include (CONFIG_PATH . '/' . $name . '.php');
        if (empty($data)) {
            $data = array();
        }
    }
    return $data[$obj];
}


   
   /* 载入网站配置 */
   public function SiteConfig($obj){
   	  include(CONFIG_PATH.'/siteconfig.php');
   	  return $Siteinfo[$obj];

   }

    /* 载入seo配置 */
   public function SEO($obj){
      include(CONFIG_PATH.'/seo.php');
      return $SEO[$obj];

   }
   


   /* 载入统计配置 */
   public function Tj(){
      $tongji=include(CONFIG_PATH.'/codejs/tongji.zhicms');
      return $tongji;
   }


   /* 中文字符截取 */
   public function msubstr($str, $start=0, $length, $charset="utf-8", $suffix=true){
    if(mb_strlen($str,$charset)>$length)
    {
        if(function_exists("mb_substr")){
            if($suffix)
                return mb_substr($str, $start, $length, $charset)."...";
            else
                return mb_substr($str, $start, $length, $charset);
        }elseif(function_exists('iconv_substr')) {
            if($suffix)
                return iconv_substr($str,$start,$length,$charset)."...";
            else
                return iconv_substr($str,$start,$length,$charset);
        }
        $re['utf-8'] = "/[x01-x7f]|[xc2-xdf][x80-xbf]|[xe0-xef][x80-xbf]{2}|[xf0-xff][x80-xbf]{3}/";
        $re['gb2312'] = "/[x01-x7f]|[xb0-xf7][xa0-xfe]/";
        $re['gbk'] = "/[x01-x7f]|[x81-xfe][x40-xfe]/";
        $re['big5'] = "/[x01-x7f]|[x81-xfe]([x40-x7e]|xa1-xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
        if($suffix) return $slice."…";
        return $slice;
    }
    else
    {
        return $str;
    }
  }
 
}