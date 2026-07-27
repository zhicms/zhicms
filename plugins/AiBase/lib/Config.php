<?php
/**
 * 优杰AI连接器 - 配置与加密辅助类
 * @package AiBase
 */

class AiConfigHelper
{
    /**
     * 加密 API Key
     * @param string $data
     * @return string
     */
    public static function encrypt($data)
    {
        global $zbp;
        if (empty($data)) {
            return '';
        }
        
        $key = md5($zbp->guid . 'YoujieAiBaseKeySalt');
        
        if (function_exists('openssl_encrypt')) {
            $iv = substr(md5($key), 0, 16);
            $encrypted = openssl_encrypt($data, 'AES-128-CBC', $key, 0, $iv);
            if ($encrypted !== false) {
                return 'ssl:' . base64_encode($encrypted);
            }
        }
        
        // 兼容降级：若服务器未安装 OpenSSL，使用简易 XOR 混淆
        $result = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $char = substr($data, $i, 1);
            $keychar = substr($key, ($i % strlen($key)) - 1, 1);
            $char = chr(ord($char) ^ ord($keychar));
            $result .= $char;
        }
        return 'xor:' . base64_encode($result);
    }

    /**
     * 解密 API Key
     * @param string $data
     * @return string
     */
    public static function decrypt($data)
    {
        global $zbp;
        if (empty($data)) {
            return '';
        }
        
        $key = md5($zbp->guid . 'YoujieAiBaseKeySalt');
        
        if (strpos($data, 'ssl:') === 0) {
            $encryptedData = base64_decode(substr($data, 4));
            $iv = substr(md5($key), 0, 16);
            $decrypted = openssl_decrypt($encryptedData, 'AES-128-CBC', $key, 0, $iv);
            if ($decrypted !== false) {
                return $decrypted;
            }
        } elseif (strpos($data, 'xor:') === 0) {
            $xorData = base64_decode(substr($data, 4));
            $result = '';
            for ($i = 0; $i < strlen($xorData); $i++) {
                $char = substr($xorData, $i, 1);
                $keychar = substr($key, ($i % strlen($key)) - 1, 1);
                $char = chr(ord($char) ^ ord($keychar));
                $result .= $char;
            }
            return $result;
        }
        
        // 如果没有加密标记，直接返回原数据（支持兼容老版本明文）
        return $data;
    }
}
