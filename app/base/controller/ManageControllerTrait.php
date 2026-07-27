<?php

namespace app\base\controller;

/**
 * 管理后台控制器 Trait
 * 提供 Session 检查功能
 */
trait ManageControllerTrait {
    
    /**
     * 检查管理后台 Session
     */
    protected function checkManageSession() {
        $api = new \app\api\model\ApiModel();
        $api->isSession('manage_system', 'index.php?r=manage/login/index');
    }

    /**
     * 全站统一的商品/文章分类（与产品分类保持一致）
     * 文章发布、产品筛选、采集与入库均使用此同一份分类，保证全站分类一致。
     */
    protected function getGoodsCategories() {
        return array(
            '1'  => '女装',
            '2'  => '母婴',
            '3'  => '化妆品',
            '4'  => '居家',
            '5'  => '鞋包配饰',
            '6'  => '美食',
            '7'  => '文体车品',
            '8'  => '数码家电',
            '9'  => '男装',
            '10' => '内衣',
            '11' => '箱包',
            '12' => '配饰',
            '13' => '户外运动',
            '14' => '家装家纺',
            '15' => '珠宝首饰',
            '16' => '奢侈品',
            '17' => '宠物用品',
            '18' => '图书音像',
            '19' => '话费充值',
            '20' => '其他'
        );
    }
}
