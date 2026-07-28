<?php
namespace app\index\controller;

/**
 * 公共数据控制器（兼容层）
 * 实际逻辑已提取到 app\common\DataService
 * 保持 obj("index/global","controller") 调用方式不变
 */
class GlobalController extends \app\base\controller\BaseController {

    private function service() {
        static $svc = null;
        if ($svc === null) {
            $svc = new \app\common\DataService();
        }
        return $svc;
    }

    public function type($lock, $id, $table) {
        return $this->service()->type($lock, $id, $table);
    }

    public function mallType($lock, $id, $table, $type) {
        return $this->service()->mallType($lock, $id, $table, $type);
    }

    public function yqfType($lock, $id, $table, $type) {
        return $this->service()->yqfType($lock, $id, $table, $type);
    }

    public function yqfMallType($lock, $id, $table, $type) {
        return $this->service()->yqfMallType($lock, $id, $table, $type);
    }

    public function duoMaiType($lock, $id, $table, $type) {
        return $this->service()->duoMaiType($lock, $id, $table, $type);
    }

    public function union($lock, $mallId) {
        return $this->service()->union($lock, $mallId);
    }

    public function loadMall($lock, $type) {
        return $this->service()->loadMall($lock, $type);
    }

    public function aType($lock, $id) {
        return $this->service()->aType($lock, $id);
    }

    public function vestList($lock) {
        return $this->service()->vestList($lock);
    }

    public function findUser($lock, $uid, $model = 'null') {
        return $this->service()->findUser($lock, $uid, $model);
    }

    public function getList($table, $lock, $pid) {
        return $this->service()->getList($table, $lock, $pid);
    }

    public function getGlList($table, $lock) {
        return $this->service()->getGlList($table, $lock);
    }

    public function Cid($cid) {
        return $this->service()->Cid($cid);
    }

    public function calendar() {
        return $this->service()->calendar();
    }
}
