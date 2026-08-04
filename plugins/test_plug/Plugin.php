<?php
namespace plugins\test_plug;
use ZhiCms\base\plugin\BasePlugin;

class Plugin extends BasePlugin
{
    public function register()
    {
        // 无需额外钩子；展示页由 PlugController 调度到 displayPage()
    }

    public function displayPage($params = array())
    {
        $id = isset($params['id']) ? intval($params['id']) : 0;
        $alias = $this->alias;

        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">';
        echo '<title>插件展示页 - ' . htmlspecialchars($alias) . '</title></head><body>';
        echo '<h1>插件展示页</h1>';
        echo '<p>alias = ' . htmlspecialchars($alias) . '</p>';
        echo '<p>id = ' . $id . '</p>';
        echo '<p>动态链接：<a href="index.php?r=index/plug/view&alias=' . $alias . '&id=123">?r=index/plug/view&alias=' . $alias . '&id=123</a></p>';
        echo '<p>伪静态链接：<a href="plug-' . $alias . '-123.html">plug-' . $alias . '-123.html</a></p>';
        echo '</body></html>';
    }
}
