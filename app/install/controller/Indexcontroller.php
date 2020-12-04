<?php
namespace app\install\controller;

error_reporting('0');
class IndexController extends \app\base\controller\BaseController {
	

  public function index(){

    $this->display();
  }
           
  public function detect(){
   $dirList = array(
            'data/cache',
            'data/config',
            'data/log',
            'upload',
        );
   //     $list = [];
        foreach ($dirList as $file) {
            $writeable = is_writeable(ROOT_PATH . $file);
            $list[] = array(
                "dir" => $file,
                "writeable" => $writeable
            );
        }
      
        $this->assign('list', $list);
     
    $this->display();
  }

  public function config(){

    $this->display();
  }
  public function start(){
	   error_reporting('0');
        if (!$this->arg('type')) {
            $this->msg('必选数据库驱动', 1);
        }
		if (!$this->arg('host')) {
            $this->msg('请填写数据库地址！', 1);
        }
        if (!$this->arg('port')) {
            self::msg('请填写数据库端口！', '1');
        }
        if (!$this->arg('dbname')) {
            self::msg('请填写数据库名称！', '1');
        }
        if (!$this->arg('dbuser')) {
            self::msg('请填写数据库用户名！', '1');
        }
		if (!$this->arg('password')) {
            self::msg('请填写数据库密码！', '1');
        }
        if (!$this->arg('admin_user')) {
            self::msg('管理员账号不能为空！', '1');
        }
        if (!$this->arg('admin_pw')) {
            self::msg('管理员密码不能为空！', '1');
        }
        if(!class_exists('Pdo')){
            self::msg('安装失败，请确保您的环境支持pdo扩展！', '1');
        }
        $data = obj('api/Api')->Form($this->POSTarg());
       // $con = mysql_connect($data['host'].':'.$data['port'],$data['username'],$data['password']);
        $config="<?php
       \r\n\$db=array(
                    'DB'=>array(                                 //数据库信息
                          'default'=>array(
                              'DB_TYPE' => '{$data['type']}',             //数据库驱动 (mysqlpdo、mysql)
                              'DB_HOST' => '{$data['host']}',            //数据库地址
                              'DB_USER' => '{$data['dbuser']}',                 //数据库用户
                              'DB_PWD' => '{$data['password']}',                      //数据库密码
                              'DB_PORT' => '{$data['port']}',                   //数据库端口
                              'DB_NAME' => '{$data['dbname']}',                   //数据库名称
                              'DB_CHARSET' => 'utf8mb4',              //数据库编码
                              'DB_PREFIX' => 'yun_',                   //数据表前缀
                              'DB_CACHE' => 'DB_CACHE',            //使用缓存配置
                          ),
                      ),
                          );";
    $fp = @fopen(CONFIG_PATH.'db.php', 'w');
    $fw = @fwrite($fp, $config);
    if (!$fw){
        self::msg('配置文件(db.php)不可写。如果您使用的是Unix/Linux主机，请修改该文件的权限为777。如果您使用的是Windows主机，请联系管理员，将此文件设为可写', '1');
    }
    fclose($fp);
	
	
	$file = CONFIG_PATH . 'zhicms.sql';
	 if (!is_file($file)) {
              self::msg('数据库文件不存在！','1');
    }
	$sqlData = new \ZhiCms\ext\Install;
     $retrun=$sqlData->mysql($file, 'yun_', 'yun_');
     foreach ($retrun as $sql) {
      $rst =obj('api/ApiData')->thisquery($sql);
     if ($rst === false) {
     self::msg('执行失败,请清数据库重试！','1');
     }
     }
	$pass=md5($data['admin_pw'].'yun_manage');
    $adminsql="UPDATE  `yun_manage` SET  `username` =  '{$data['admin_user']}',`password` =  '{$pass}' WHERE  `yun_manage`.`id` =1";
     obj('api/ApiData')->thisquery($adminsql);
	 @touch(CONFIG_PATH.'install.lock');//写入判断是否

	 //self::delDirAndFile(ROOT_PATH . 'app/install',1);
	 //self::delDirAndFile(ROOT_PATH . 'public/install',1);
	 self::msg('安装成功,请记得您的设置的账号密码，为了安全，系统将删除引导安装程序','0');
  }

  public function msg($msg,$error){
    $this->msg=$msg;
    $this->error=$error;
    $this->display("app/install/view/index/install");
    exit;
  }
  
    public function toindex(){
	self::dell();
    $this->redirect("index.php");
    exit;
    }
     public function tomanage(){
	 self::dell();
    $this->redirect("index.php?r=manage");
    exit;
  }
       public function dell(){
	 self::delDirAndFile(ROOT_PATH . 'app/install',1);
	 self::delDirAndFile(ROOT_PATH . 'public/install',1);
  }
  


public function delDirAndFile($path, $delDir = false)
{
if (is_array($path)) {
foreach ($path as $subPath) {
self::delDirAndFile($subPath, $delDir);
}
}
if (is_dir($path)) {
$handle = opendir($path);
if ($handle) {
while (false !== ($item = readdir($handle))) {
if ($item != "." && $item != "..") {
is_dir("$path/$item") ? self::delDirAndFile("$path/$item", $delDir) : unlink("$path/$item");
}
}
closedir($handle);
if ($delDir) {
return rmdir($path);
}
}
} else {
if (file_exists($path)) {
return unlink($path);
} else {
return false;
}
}
}
  public function  ok(){

    echo 'ok';
  }
}