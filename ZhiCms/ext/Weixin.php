<?php
namespace ZhiCms\ext;
error_reporting('0');
class weixin
{
  //Token验证
  public function index($token){
    $timestamp = $_GET['timestamp'];  
    $nonce = $_GET['nonce'];   
    $signature = $_GET['signature'];  
    $array = array($timestamp,$nonce,$token);  
    sort($array);  
    $tmpstr = implode('',$array);  
    $tmpstr = sha1($tmpstr);  
      
    if($tmpstr == $signature)  
    {  
        echo $_GET['echostr'];  
        exit;  
    }  

  }

  //发起登录请求
  public function loginuser($backurl){
   $host=$_SERVER['HTTP_HOST'];
   $toarr=rand(1,100);
   $array=self::WeChatSet('lock','http%3a%2f%2f'.$host.'/index.php%3Fr%3Dindex/index/GetWechatUser%26toarr%3D'.$toarr.'%26gourl%3D'.$backurl);
   $url ="https://open.weixin.qq.com/connect/oauth2/authorize?appid={$array['appid']}&redirect_uri={$array['redirect_uri']}&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect";  
        echo "<script language='javascript' type='text/javascript'>";  
        echo "window.location.href='$url'";  
        echo "</script>"; 
  }


 //获取登录信息
  public function GetUserInfo(){
      include CONFIG_PATH . 'apicache/weixin.php';
      $appid = $weixin['appid'];  
      $secret = $weixin['appsecret'];  
      $code = $_GET["code"];  
      $get_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appid.'&secret='.$secret.'&code='.$code.'&grant_type=authorization_code';  

      $res=self::http($get_token_url);
      $json_obj = json_decode($res,true);  
      
      //根据openid和access_token查询用户信息  
      $access_token = $json_obj['access_token'];  
      $openid = $json_obj['openid'];  
      $get_user_info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';  
      $ares=self::http($get_user_info_url);

        
      //解析json  
      $user_obj = json_decode($ares,true);  
      $_SESSION['user'] = $user_obj;  


      $ar_uf=$user_obj;
      $openid=$user_obj['openid'];
      $wxarray=array("nickname"=>$ar_uf['nickname'],"openid"=>$openid,"headimgurl"=>$ar_uf['headimgurl']);
      setcookie("nickname", $wxarray['nickname'], time()+3600);
      setcookie("headimgurl",$wxarray['headimgurl'], time()+3600);      
      setcookie("openid", $wxarray['openid'], time()+3600);
      setcookie("islogin", '1', time()+3600);  
      return $wxarray;
  }


  //返回首页
  public function loginbackindex($backurl){
      if($backurl==''){
        $url="index.php?r=index/index/index";

      }else{
        $url=$backurl;
      }

       echo "<script language='javascript' type='text/javascript'>";  
       echo "window.location.href='$url'";  
       echo "</script>";     
  }



//公众号配置
 public function WeChatSet($lock,$redirect_uri){
       if($lock!='lock'){
         exit('error');
        }
        include CONFIG_PATH . 'apicache/weixin.php';
        $array=array("appid"=>"{$weixin['appid']}","redirect_uri"=>$redirect_uri,"secret"=>"{$weixin['appsecret']}");
        return $array;
    
    }

  //CURL 方法 支持 https
  public function http($url, $data='', $method='GET'){   

   $curl = curl_init(); // 启动一个CURL会话  
    curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址  
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查  
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在  
    curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器  
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转  
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer  
    if($method=='POST'){  
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求  
        if ($data != ''){  
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包  
        }  
    }  
    curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环  
    curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容  
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回  
    $tmpInfo = curl_exec($curl); // 执行操作  
    curl_close($curl); // 关闭CURL会话  
    return $tmpInfo; // 返回数据 

}  

  
}