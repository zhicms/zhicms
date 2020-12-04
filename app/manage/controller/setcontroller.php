<?php
namespace app\manage\controller;
obj('api/Api')->isSession('manage_system','index.php?r=manage/login/index');
class  setController extends \app\base\controller\BaseController
{

	public function index(){

		if(!IS_POST){
			$this->pagetext=array("基础设置","网站设置");
			include CONFIG_PATH . 'siteconfig.php';
			$this->ret=$Siteinfo;
		    $this->display();
		}else{
		 $sitename=$_POST['sitename'];
		 $hosturl=$_POST['hosturl'];
		 $logo=$_POST['logo'];
		 $ewm=$_POST['ewm'];
		 $pid=$_POST['pid'];
		 $appkey=$_POST['appkey'];
		 $secretKey=$_POST['secretKey'];
		  $apiurl=$_POST['apiurl'];
		  $code=$_POST['code'];
		 $zhuan=$_POST['zhuan'];
        $download=$_POST['download'];
       
         $content="<?php
  
         \r\n\$Siteinfo=array(
              'sitename'=>'{$sitename}',
              'hosturl'=>'{$hosturl}',
              'logo'=>'{$logo}',
              'ewm'=>'{$ewm}',
              'pid'=>'{$pid}',
			   'appkey'=>'{$appkey}',
			    'secretKey'=>'{$secretKey}',
				'apiurl'=>'{$apiurl}',
				'code'=>'{$code}',
              'zhuan'=>'{$zhuan}',
              'download'=>'{$download}',

        );";
           $of = fopen(CONFIG_PATH . 'siteconfig.php', 'w');
              if ($of) {
                  fwrite($of, $content);
              }
              fclose($of);
              echo json_encode(array("info" => "设置成功", "status" => "y"));
		}

	}

	public function url(){

		if(!IS_POST){
			$this->pagetext=array("基础设置","自定义rul");

			include CONFIG_PATH . 'rule.php';
			$keys=array_keys($rule['REWRITE_RULE']);




			$this->DEBUG=$rule['DEBUG'];
			$this->REWRITE_ON=$rule['REWRITE_ON'];
			$this->moren=$rule['moren'];
			$this->ret=$keys;

		    $this->display();
		}else{

			$DEBUG=$_POST['DEBUG'];
			$REWRITE_ON=$_POST['REWRITE_ON'];
			$moren=$_POST['moren'];

			 $content="<?php
       \r\n\$rule=array(
       'ENV' => 'global',                      //调试配置文件名
    'DEBUG' =>{$DEBUG},                             //是否显示详细错误信息
    'LOG_ON' => false,                           //错误日志记录，需要自己扩展appHook
    'LOG_PATH' => ROOT_PATH . 'data/log/',       //日志记录目录
    'TIMEZONE'=> 'PRC',                          //时间区域
	 'moren' => '".$moren."',                       //默认首页访问模块
    'REWRITE_ON' => {$REWRITE_ON},                       //伪静态开关，需要放入对应环境的伪静态规则

     'REWRITE_RULE' => array(
      'index.html'=>'index/index/index',
      'view-<id>.html'=>'index/index/view/id=<id>',
      'brand.html'=>'index/brand/index',
      'brand-view-<id>.html'=>'index/brand/view/id=<id>',
      'cheaps.html'=>'index/cheaps/index',
      'cheaps-<id>.html'=>'index/cheaps/index/id=<id>',
      'detail-<id>.html'=>'index/view/detail/id=<id>',
      'vip-<id>.html'=>'index/view/vip/id=<id>',
      'product-<type>-<id>.html'=>'index/view/view/type=<type>/id=<id>',
      'rank.html'=>'index/rank/index', 
      'm.html'=>'index/m/index',
     'm-search-<key>.html'=>'index/m/search/key=<key>' , 
		'gotb.html'=>'go/tb/itemiid',
		'goto.html'=>'go/to/url',
		'go.html'=>'go/to/wjp',
		'so.html'=>'index/search/index',
		'app.html'=>'index/page/app',
		'side.html'=>'index/page/side',
        

    ),
      );
       ";
	  $of = fopen(CONFIG_PATH . 'rule.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

       echo json_encode(array("info" => "设置成功", "status" => "y"));
     


		}
	}


	public function seo(){

		if(!IS_POST){
			$this->pagetext=array("基础设置","SEO设置");
			include CONFIG_PATH . 'seo.php';
			$this->ret=$SEO;
			$this->display();
			exit;
		}else{
      $index_title=$_POST['index_title'];
      $index_keywords=$_POST['index_keywords'];
      $index_dec=$_POST['index_dec'];
      $brand_title=$_POST['brand_title'];
      $brand_keywords=$_POST['brand_keywords'];
      $brand_dec=$_POST['brand_dec'];
      
      $rank_title=$_POST['rank_title'];
      $rank_keywords=$_POST['rank_keywords'];
      $rank_dec=$_POST['rank_dec'];
      
      $cheaps_title=$_POST['cheaps_title'];
      $cheaps_keywords=$_POST['cheaps_keywords'];
      $cheaps_dec=$_POST['cheaps_dec'];
      
      $banquan=$_POST['banquan'];
	  $about=$_POST['about'];
	  $beian=$_POST['beian'];
     
			 $content="<?php
       \r\n\$SEO=array(
              'index_title'=>'{$index_title}',
              'index_keywords'=>'{$index_keywords}',
              'index_dec'=>'{$index_dec}',
              'brand_title'=>'{$brand_title}',
              'brand_keywords'=>'{$brand_keywords}',
              'brand_dec'=>'{$brand_dec}',
              'rank_title'=>'{$rank_title}',
              'rank_keywords'=>'{$rank_keywords}',
              'rank_dec'=>'{$rank_dec}',
              'cheaps_title'=>'{$cheaps_title}',
               'cheaps_keywords'=>'{$cheaps_keywords}',
                'cheaps_dec'=>'{$cheaps_dec}',
              'banquan'=>'{$banquan}',
              'about'=>'{$about}',
              'beian'=>'{$beian}'



        );";

        $of = fopen(CONFIG_PATH . 'seo.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

       echo json_encode(array("info" => "设置成功", "status" => "y"));

		}
	}


   public function api(){

      if(!IS_POST){
        $this->pagetext=array("基础设置","生成高佣API");
        include CONFIG_PATH . 'apiset.php';
        $this->ret=$api;
        $this->display();
        exit;
      }else{


        $appKey=$_POST['appKey'];
		$appSecret=$_POST['appSecret'];
		$Apikey=$_POST['Apikey'];
		$app_key=$_POST['app_key'];
		$app_secret=$_POST['app_secret'];
		 $siteid=$_POST['siteid'];
        $hash=$_POST['hash'];
       $content="<?php
       \r\n\$api=array(
              'appKey'=>'{$appKey}',
			  'appSecret'=>'{$appSecret}',
			   'Apikey'=>'{$Apikey}',
			    'app_key'=>'{$app_key}',
				 'app_secret'=>'{$app_secret}',
			    'siteid'=>'{$siteid}',
              'hash'=>'{$hash}',
        );";

        $of = fopen(CONFIG_PATH . 'apiset.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

       echo json_encode(array("info" => "设置成功", "status" => "y"));

      }
   }

    public function sms(){

      if(!IS_POST){
        $this->pagetext=array("基础设置","短信通道");
        include CONFIG_PATH . 'sms.php';
        $this->ret=$sms;
        $this->display();
        exit;
      }else{

        $smsurl=$_POST['smsurl'];
        $reg_sms=$_POST['reg_sms'];
       $content="<?php
       \r\n\$sms=array(
            'smsurl'=>'{$smsurl}',
            'reg_sms'=>'{$reg_sms}',
      );";

        $of = fopen(CONFIG_PATH . 'sms.php', 'w');
            if ($of) {
                fwrite($of, $content);
            }
            fclose($of);

       echo json_encode(array("info" => "设置成功", "status" => "y"));

      }
   }
    /*生成 js代码*/
    public function js()
    {
        if (!IS_POST) {
            $this->pagetext=array("基础设置","统计代码");
            header("Content-Type:text/html;charset=utf-8");

            $this->tongji = file_get_contents(CONFIG_PATH . 'codejs/tongji.zhicms');

            $this->display();
            exit;
        } else {

            $tongji = fopen(CONFIG_PATH . 'codejs/tongji.zhicms', 'w');
            if ($tongji) {
                fwrite($tongji, $_POST['tongji']);
            }

            fclose($tongji);
            echo json_encode(array("info" => "设置成功", "status" => "y"));
        }
    }

}