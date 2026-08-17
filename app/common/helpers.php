<?php

/**
 * 通用辅助函数库（对标 emlog include/lib/common.php 的精华）
 *
 * 设计原则：
 *  1. 全部用 function_exists 包裹，避免与框架/插件已有同名函数冲突；
 *  2. 不依赖任何全局常量（如 EMLOG_ROOT），改用 ZhiCms 的 ROOT_PATH / 运行时探测；
 *  3. 吸收 emlog 经过多年验证的实用函数：subString / smartDate / extractHtmlData /
 *     getFirstImage / getGravatar / getIp / getUA / getRandStr。
 *
 * 调用方式：在基类 __construct 或 bootstrap 阶段 require_once 本文件即可全局可用。
 */

if (!function_exists('zc_sub_string')) {
    /**
     * 中文字符串安全截取（基于 mb_ 系列，自动补省略号）
     * @param string $strings 原始字符串
     * @param int    $start   起始位置
     * @param int    $length  截取长度
     * @param string $dot     超出时追加的省略符
     * @return string
     */
    function zc_sub_string($strings, $start, $length, $dot = '...') {
        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            $sub = mb_substr($strings, $start, $length, 'UTF-8');
            return mb_strlen($sub, 'UTF-8') < mb_strlen($strings, 'UTF-8') ? $sub . $dot : $sub;
        }
        $sub = substr($strings, $start, $length);
        return strlen($sub) < strlen($strings) ? $sub . $dot : $sub;
    }
}

if (!function_exists('zc_smart_date')) {
    /**
     * 友好时间显示（X 秒前 / X 分钟前 / X 天前 ...），超过 5 年回退为日期
     * @param int    $timestamp Unix 时间戳
     * @param string $format    回退日期格式
     * @return string
     */
    function zc_smart_date($timestamp, $format = 'Y-m-d H:i') {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) return '';
        $sec = time() - $timestamp;
        if ($sec < 60) {
            $op = $sec . ' 秒前';
        } elseif ($sec < 3600) {
            $op = floor($sec / 60) . ' 分钟前';
        } elseif ($sec < 3600 * 24) {
            $op = floor($sec / 3600) . ' 小时前';
        } elseif ($sec < 3600 * 24 * 30) {
            $days = floor($sec / (3600 * 24));
            $op = $days . ' 天前';
        } elseif ($sec < 3600 * 24 * 365) {
            $months = floor($sec / (3600 * 24 * 30));
            $op = $months . ' 个月前';
        } elseif ($sec < 3600 * 24 * 365 * 5) {
            $years = floor($sec / (3600 * 24 * 365));
            $op = $years . ' 年前';
        } else {
            $op = date($format, $timestamp);
        }
        return $op;
    }
}

if (!function_exists('zc_extract_html_data')) {
    /**
     * 从可能含 HTML/Markdown 的内容中萃取纯文本摘要
     * @param string $data 原始内容
     * @param int    $len  摘要长度
     * @return string
     */
    function zc_extract_html_data($data, $len) {
        $data = zc_sub_string(strip_tags($data), 0, $len + 30);
        $search = array(
            "/([\r\n])[\s]+/",
            "/&(quot|#34);/i",
            "/&(amp|#38);/i",
            "/&(lt|#60);/i",
            "/&(gt|#62);/i",
            "/&(nbsp|#160);/i",
            "/&(iexcl|#161);/i",
            "/&(cent|#162);/i",
            "/&(pound|#163);/i",
            "/&(copy|#169);/i",
            "/\"/i",
        );
        $replace = array(' ', '"', '&', ' ', ' ', '', chr(161), chr(162), chr(163), chr(169), '');
        $data = trim(zc_sub_string(preg_replace($search, $replace, $data), 0, $len));
        return $data;
    }
}

if (!function_exists('zc_get_first_image')) {
    /**
     * 从 HTML/Markdown 内容中提取第一张图片地址
     * @param string $content 原始内容
     * @return string|null
     */
    function zc_get_first_image($content) {
        if (empty($content)) return null;
        // Markdown 图片语法
        if (preg_match('/!\[.*?\]\((.*?)\)/', $content, $matches) && !empty($matches[1])) {
            return trim($matches[1]);
        }
        // HTML <img>
        if (class_exists('DOMDocument')) {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($content);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);
            $imgNode = $xpath->query('//img')->item(0);
            if ($imgNode) {
                $src = $imgNode->getAttribute('src');
                return trim($src, '\\"');
            }
        }
        return null;
    }
}

if (!function_exists('zc_get_gravatar')) {
    /**
     * 生成 Gravatar / Cravatar 头像地址（国内用 cravatar.cn 镜像，更稳定）
     * @param string $email 邮箱（用于生成 hash）
     * @param int    $s     头像尺寸
     * @return string
     */
    function zc_get_gravatar($email, $s = 120) {
        $hash = md5(strtolower(trim((string) $email)));
        return '//cravatar.cn/avatar/' . $hash . '?s=' . (int) $s;
    }
}

if (!function_exists('zc_get_ip')) {
    /**
     * 获取客户端真实 IP（兼容 Cloudflare / 代理）
     * @return string
     */
    function zc_get_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($list[0]);
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '';
        }
        return $ip;
    }
}

if (!function_exists('zc_get_ua')) {
    /**
     * 获取 User-Agent
     * @return string
     */
    function zc_get_ua() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    }
}

if (!function_exists('zc_get_rand_str')) {
    /**
     * 生成随机字符串
     * @param int  $length        长度
     * @param bool $special_chars 是否包含特殊字符
     * @param bool $numeric_only  仅数字
     * @return string
     */
    function zc_get_rand_str($length = 12, $special_chars = true, $numeric_only = false) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($numeric_only) {
            $chars = '0123456789';
        } elseif ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
        $str = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, $max)];
        }
        return $str;
    }
}

if (!function_exists('zc_laiyuan_to_platform')) {
    /**
     * 将商品来源数字(laiyuan)映射为转链平台标识(tb/jd/pdd/vip)
     * 约定：1=淘宝 2=pdd 3=vip 4=京东（与采集库一致），未知默认 tb。
     * @param mixed $laiyuan
     * @return string
     */
    function zc_laiyuan_to_platform($laiyuan) {
        switch ((int) $laiyuan) {
            case 1:  return 'tb';
            case 2:  return 'pdd';
            case 3:  return 'vip';
            case 4:  return 'jd';
            default: return 'tb';
        }
    }
}

if (!function_exists('zc_platform_label')) {
    /**
     * 平台中文名（用于侧栏/卡面角标）
     * @param string $platform
     * @return string
     */
    function zc_platform_label($platform) {
        $map = array(
            'tb'  => '淘宝',
            'jd'  => '京东',
            'pdd' => '拼多多',
            'vip' => '唯品会',
        );
        return isset($map[$platform]) ? $map[$platform] : '淘宝';
    }
}
