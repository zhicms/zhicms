<?php

/**
 * 基础控制器
 */

namespace app\base\controller;
class BaseController extends \ZhiCms\base\Controller {

	/**
	 * 初始化
	 */
	public function __construct() {

	}
   




   /*通用上传*/
	public function upload(){
		$upload=new \ZhiCms\ext\UploadFile();
		$upload->maxSize=1024*1024*2;
		$upload->allowExts  = explode(',','png');
		$upload->savePath =ROOT_PATH.'/upload/file/images/'.$upload_dir."/";

		if(!$upload->upload())
		       {
		        //捕获上传异常
		       print_r($upload->getErrorMsg());
		       exit;
		      }
		      else 
		      {
		        //取得成功上传的文件信息
		       $file= $upload->getUploadFileInfo();
		       $File=$this->file=$file['0']['savename'];
		       return $File; 
		      }		
	}
  
}