<?php
namespace app\manage\controller;
use \app\base\controller\ManageControllerTrait;
class IndexController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;
    public function index(){

    $this->checkManageSession();

        $this->pageText = array("后台首页");

        $this->userCount = obj("api/ApiData")->dataCount("yun_user", array("1"));
        $this->goodsCount = obj("api/ApiData")->dataCount("yun_items", array("1"));
        $this->articleCount = obj("api/ApiData")->dataCount("yun_article", array("1"));
        
        $today = date("Y-m-d");
        $todayWhere[] = "`date` >= '{$today} 00:00:00' AND `date` <= '{$today} 23:59:59'";
        $this->todayCount = obj("api/ApiData")->dataCount("yun_article", $todayWhere);
        
        $goodsTodayWhere[] = "`couponEndTime` >= '{$today}'";
        $this->todayGoodsCount = obj("api/ApiData")->dataCount("yun_items", $goodsTodayWhere);

        $v = \app\common\ConfigStore::load('version', 'version');
        $this->localVersion = $v;

        $updateUrl = 'https://www.zhicms.cc/update_check.php';
        $token = new \ZhiCms\ext\Weixin;
        $ret = obj("api/Api")->objectArray(json_decode($token->http($updateUrl)));
        
        $this->updateAvailable = false;
        if(isset($ret['version']) && version_compare($ret['version'], $v, '>')){
            $this->updateAvailable = true;
            $this->updateInfo = $ret;
        }

        $this->display();
    }   


   public function delCache(){
   


   $this->checkManageSession();


        self::delDir(ROOT_PATH . 'data/cache/tpl');
        exit(json_encode(array("info" => "清除缓存成功", "status" => "y")));
     }
	 
public function delDir($dir){

	 
	$this->checkManageSession();

   $dh=opendir($dir);
   while ($file=readdir($dh)) {
      if($file!="." && $file!="..") {
         $fullpath=$dir."/".$file;
         if(!is_dir($fullpath)) {
            unlink($fullpath);
         } else {
            self::delDir($fullpath);
         }
      }
   }
 
   closedir($dh);
   if(rmdir($dir)) {
      return true;
   } else {
      return false;
   }
}


     public function downloadFile(){



     $this->checkManageSession();

        $v = \app\common\ConfigStore::load('version', 'version');
        
        $updateType = isset($_GET['type']) ? $_GET['type'] : 'hot';
        
        $updateUrl = 'https://www.zhicms.cc/update_check.php';
        $token = new \ZhiCms\ext\Weixin;
        $ret = obj("api/Api")->objectArray(json_decode($token->http($updateUrl)));
        
        if($updateType == 'hot'){
            $zipUrl = isset($ret['hot_zip']) ? $ret['hot_zip'] : '';
        }else{
            $zipUrl = isset($ret['full_zip']) ? $ret['full_zip'] : '';
        }
        
        if(empty($zipUrl)){
            exit(json_encode(array("info" => "未获取到升级包地址", "status" => "n")));
        }

        $zipPath = CONFIG_PATH . 'update.zip';
        $tempDir = CONFIG_PATH . 'update_temp/';

        if(!file_exists($tempDir)){
            mkdir($tempDir, 0755, true);
        }

        try{
            $zipContent = file_get_contents($zipUrl);
            if($zipContent === false){
                throw new Exception('下载升级包失败');
            }
            file_put_contents($zipPath, $zipContent);

            $zip = new \ZipArchive;
            if($zip->open($zipPath) !== true){
                throw new Exception('打开压缩包失败');
            }

            $zip->extractTo($tempDir);
            $zip->close();

            $this->executeSql($tempDir);

            $this->updateFiles($tempDir, ROOT_PATH, $updateType);

            unlink($zipPath);
            $this->delDir($tempDir);

            $newVersion = isset($ret['version']) ? $ret['version'] : $v;
            $versionContent = "<?php\n\$v='{$newVersion}';";
            file_put_contents(CONFIG_PATH . 'version.php', $versionContent);

            $updateTypeName = $updateType == 'hot' ? '热更新' : '整包更新';
            exit(json_encode(array("info" => "{$updateTypeName}成功，当前版本：{$newVersion}", "status" => "y")));

        }catch(Exception $e){
            if(file_exists($zipPath)){
                unlink($zipPath);
            }
            if(file_exists($tempDir)){
                $this->delDir($tempDir);
            }
            exit(json_encode(array("info" => "升级失败：" . $e->getMessage(), "status" => "n")));
        }
    }

    private function executeSql($tempDir){
        $sqlFiles = array('update.sql', 'data/config/update.sql');
        foreach($sqlFiles as $sqlFile){
            $sqlPath = $tempDir . $sqlFile;
            if(file_exists($sqlPath)){
                $sqlContent = file_get_contents($sqlPath);
                $sqlStatements = explode(';', $sqlContent);
                foreach($sqlStatements as $sql){
                    $sql = trim($sql);
                    if(!empty($sql)){
                        obj("api/ApiData")->thisQuery($sql);
                    }
                }
                unlink($sqlPath);
            }
        }
    }

    private function updateFiles($sourceDir, $destDir, $updateType = 'hot'){
        $dir = opendir($sourceDir);
        while(false !== ($file = readdir($dir))){
            if($file != '.' && $file != '..'){
                $source = $sourceDir . $file;
                $dest = $destDir . $file;
                if(is_dir($source)){
                    if(!file_exists($dest)){
                        mkdir($dest, 0755, true);
                    }
                    $this->updateFiles($source . '/', $dest . '/', $updateType);
                }else{
                    $relativePath = substr($dest, strlen(ROOT_PATH));
                    if($relativePath == 'data/config/db.php'){
                        continue;
                    }
                    if($this->isConfigFile($relativePath)){
                        $this->mergeConfigFile($source, $dest);
                    }else{
                        copy($source, $dest);
                    }
                }
            }
        }
        closedir($dir);
    }

    private function isConfigFile($relativePath){
        $configFiles = array(
            'data/config/siteconfig.php',
            'data/config/seo.php',
            'data/config/sms.php',
            'data/config/apiset.php',
            'data/config/rule.php',
            'data/config/global.php',
        );
        return in_array($relativePath, $configFiles);
    }

    private function mergeConfigFile($source, $dest){
        if(!file_exists($dest)){
            copy($source, $dest);
            return;
        }

        include $dest;
        $oldConfig = $this->getConfigVariable($dest);
        
        include $source;
        $newConfig = $this->getConfigVariable($source);

        if($oldConfig && $newConfig){
            $mergedConfig = array_merge($newConfig, $oldConfig);
            $varName = $this->getConfigVarName($dest);
            $content = "<?php\n\${$varName}=" . var_export($mergedConfig, true) . ";\n";
            file_put_contents($dest, $content);
        }else{
            copy($source, $dest);
        }
    }

    private function getConfigVariable($filePath){
        $content = file_get_contents($filePath);
        if(preg_match('/\$(\w+)\s*=\s*array\(/', $content, $matches)){
            $varName = $matches[1];
            include $filePath;
            return $$varName;
        }
        return null;
    }

    private function getConfigVarName($filePath){
        $content = file_get_contents($filePath);
        if(preg_match('/\$(\w+)\s*=\s*array\(/', $content, $matches)){
            return $matches[1];
        }
        return 'config';
    }

   

}