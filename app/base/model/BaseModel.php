<?php

namespace app\base\model;

class BaseModel extends \ZhiCms\base\Model {
   
   /**
    * 所有网站配置已统一存入 {pre}config 表（JSON 格式），
    * 通过 app\common\ConfigStore 读写，自动兼容旧文件兜底。
    * 保留 db.php / global.php / rule.php 为文件（框架级引导配置）。
    */

   /* 重写载入各种插件配置 */
   public function SET($name, $obj) {
       $data = \app\common\ConfigStore::load('plug_' . $name);
       if (empty($data)) {
           // 插件配置回退文件
           if (is_file(CONFIG_PATH . '/' . $name . '.php')) {
               $data = include(CONFIG_PATH . '/' . $name . '.php');
           } else {
               $data = [];
           }
       }
       return isset($data[$obj]) ? $data[$obj] : null;
   }

   /* 载入网站配置（从 DB，对标 emlog 的 Option::get） */
   public function SiteConfig($obj){
       $data = \app\common\ConfigStore::load('site');
       return isset($data[$obj]) ? $data[$obj] : null;
   }

   /* 载入seo配置（从 DB） */
   public function SEO($obj){
       $data = \app\common\ConfigStore::load('seo');
       return isset($data[$obj]) ? $data[$obj] : null;
   }

   /* 载入seo推送配置（从 DB） */
   public function SeoPush(){
       return \app\common\ConfigStore::load('seopush');
   }

   /**
    * 通用配置读取（按 group.key 格式）
    * @param string $group 配置组名（site/seo/api/sms/aichat/seopush/weixin/ai）
    * @param string|null $key 配置项，null 返回整组
    * @return mixed
    */
   public function Config($group, $key = null) {
       return \app\common\ConfigStore::load($group, $key);
   }


   /* 中文字符截取 */
   public function msubstr($str, $start=0, $length=0, $charset="utf-8", $suffix=true){
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