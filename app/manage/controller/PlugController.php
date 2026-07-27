<?php
namespace app\manage\controller;
error_reporting(0);
use \app\base\controller\ManageControllerTrait;
/**
 * 兼容旧路由：重定向到标准化插件管理控制器
 * @deprecated 请使用 app/manage/controller/PluginController
 */
class PlugController extends \app\base\controller\BaseController
{
    use ManageControllerTrait;

	public function index(){
		$this->checkManageSession();
		$this->redirect('index.php?r=manage/plugin/index');
	}

	public function lists(){
		$this->redirect('index.php?r=manage/plugin/index');
	}
}
