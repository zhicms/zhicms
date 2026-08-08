<?php
namespace plugins\kiees;
use ZhiCms\base\plugin\BasePlugin;

/**
 * KIEES 品牌展示站插件入口
 *
 * 展示页访问：
 *   动态： index.php?r=index/plug/view&alias=kiees
 *   伪静态： plug-kiees.html
 *   带参数： index.php?r=index/plug/view&alias=kiees&id=1  /  plug-kiees-1.html
 *
 * 设计要点（满足「以控制器方式读库 + 渲染模板」）：
 *   1. displayPage() 仅做调度，真正的业务逻辑放在 controller/SiteController.php
 *   2. SiteController 继承插件内 KieesController 基类，基类提供 assign/display，
 *      内部复用框架 think-template 引擎，view_path 指向插件私有 view 目录
 *   3. 读库统一通过 obj('api/ApiData')（即 app\api\model\ApiDataModel）
 */
class Plugin extends BasePlugin
{
    public function register()
    {
        // 展示页由 PlugController 调度到 displayPage()，无需额外钩子
    }

    public function displayPage($params = array())
    {
        // 把请求交给插件自有控制器处理（控制器方式）
        $controller = new \plugins\kiees\controller\SiteController();
        $controller->run($params);
        exit;
    }
}
