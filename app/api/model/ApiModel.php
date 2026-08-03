<?php

namespace app\api\model;

class ApiModel extends \app\base\model\BaseModel {

    public function isSession($session, $url) {
        if (!isset($_SESSION[$session]) || empty($_SESSION[$session])) {
            // AJAX 请求（如后台表单提交、列表刷新）session 失效时返回 JSON，
            // 避免前端收到 302 跳转后的 HTML 导致 "请求异常"（JSON 解析失败）。
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                exit(json_encode(array("info" => "登录已过期，请重新登录", "status" => "n", "need_login" => true)));
            }
            header("location:{$url}");
            exit;
        }
    }

    public function isCookies($key, $url) {
        if (!isset($_COOKIE[$key]) || empty($_COOKIE[$key])) {
            // 验证重定向URL仅允许站内相对路径，防止开放重定向
            $url = trim($url);
            if(preg_match('#^(https?:)?//#i', $url)){
                $parsed = parse_url($url);
                if(isset($parsed['host']) && isset($_SERVER['HTTP_HOST']) && $parsed['host'] !== $_SERVER['HTTP_HOST']){
                    error_log('Blocked external redirect in isCookies: ' . $url);
                    exit('invalid redirect');
                }
            }
            header("location:{$url}");
            exit;
        }
    }

    public function unsetSession($session) {
        if (isset($_SESSION[$session])) {
            unset($_SESSION[$session]);
        }
        session_unset();
    }

    public function Form($str) {
        $data = array();
        foreach ($str as $key => $value) {
            $data[$key] = $value;
        }
        return $data;
    }

    public function moneyType($num) {
        return sprintf("%.2f", substr(sprintf("%.3f", $num), 0, -2));
    }

    public function getLastMonth() {
        $date = date("Y-m-d");
        $timestamp = strtotime($date);
        return date('Y-m', strtotime(date('Y', $timestamp) . '-' . (date('m', $timestamp) - 1) . '-01'));
    }

    public function zhiCmsCurlGet($url) {
        $cu = curl_init();
        curl_setopt($cu, CURLOPT_URL, $url);
        curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($cu);
        curl_close($cu);
        return $ret;
    }

    public function curPageUrl() {
        $pageUrl = 'http';
        if ($_SERVER["HTTPS"] == "on") {
            $pageUrl .= "s";
        }
        $pageUrl .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageUrl .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        } else {
            $pageUrl .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }
        return $pageUrl;
    }

    public function objectArray($array) {
        if (is_object($array)) {
            $array = (array)$array;
        }
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = self::objectArray($value);
            }
        }
        return $array;
    }

    public function randFloat($min = 0, $max = 1) {
        return number_format($min + mt_rand() / mt_getrandmax() * ($max - $min), 2);
    }

    public function formatDate($time) {
        date_default_timezone_set('PRC');
        $time = strtotime($time);
        if ($time === false) {
            return '未知时间';
        }
        $nowTime = time();
        $difference = $nowTime - $time;

        if ($difference <= 2) {
            $msg = '刚刚';
        } elseif ($difference < 60) {
            $msg = $difference . '秒前';
        } elseif ($difference < 3600) {
            $msg = floor($difference / 60) . '分钟前';
        } elseif ($difference < 86400) {
            $msg = floor($difference / 3600) . '小时前';
        } elseif ($difference < 2592000) {
            $msg = floor($difference / 86400) . '天前';
        } elseif ($difference < 7776000) {
            $msg = floor($difference / 2592000) . '个月前';
        } else {
            $msg = '很久以前';
        }
        return $msg;
    }

    public function mDate($date) {
        return $this->formatDate($date);
    }

    public function moneyNum($str) {
        return number_format($str, 2);
    }

    private static $categoryMap = array(
        '1' => '女装',
        '2' => '母婴',
        '3' => '化妆品',
        '4' => '居家',
        '5' => '鞋包配饰',
        '6' => '美食',
        '7' => '文体车品',
        '8' => '数码家电',
        '9' => '男装',
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

    public function getCategoryName($cid) {
        return isset(self::$categoryMap[$cid]) ? self::$categoryMap[$cid] : '';
    }

    public function cid($cid) {
        return $this->getCategoryName($cid);
    }

    public function getCategoryList() {
        return self::$categoryMap;
    }
}
