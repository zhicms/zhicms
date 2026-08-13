<?php
namespace ZhiCms\ext;

/**
 * 微信支付 V3 Native 下单 + 回调验签解密（独立实现，不依赖第三方 SDK）
 *
 * 商户证书放置：站点根目录 cert/apiclient_cert.pem + apiclient_key.pem
 * 平台证书：首次从微信接口拉取并缓存（ cert/platform_cert.pem ）
 */
class WxPayV3
{
    const API_BASE = 'https://api.mch.weixin.qq.com';
    const CERT_DIR = BASE_PATH . 'cert/';

    private $appid;
    private $mchid;
    private $v3key;
    private $serialNo;

    public function __construct($appid, $mchid, $v3key, $serialNo)
    {
        $this->appid    = $appid;
        $this->mchid    = $mchid;
        $this->v3key    = $v3key;
        $this->serialNo = $serialNo;
    }

    /**
     * Native 下单
     * @param string $description 商品描述
     * @param string $outTradeNo  商户订单号
     * @param int    $total       金额（分）
     * @param string $notifyUrl   回调地址
     * @return array ['code_url'=>原生码, 'prepay_id'=>预支付ID] 或 throws
     */
    public function nativeOrder($description, $outTradeNo, $total, $notifyUrl)
    {
        $body = array(
            'appid'        => $this->appid,
            'mchid'        => $this->mchid,
            'description'  => $description,
            'out_trade_no' => $outTradeNo,
            'notify_url'   => $notifyUrl,
            'amount'       => array('total' => (int)$total, 'currency' => 'CNY'),
        );
        $resp = $this->request('POST', '/v3/pay/transactions/native', $body);
        if (empty($resp['code_url'])) {
            throw new \Exception('下单失败：' . json_encode($resp, JSON_UNESCAPED_UNICODE));
        }
        return $resp;
    }

    /**
     * 构造回调验签所需的平台证书（首次拉取并缓存）
     */
    private function getPlatformCert()
    {
        $cacheFile = self::CERT_DIR . 'platform_cert.pem';
        if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) {
            return file_get_contents($cacheFile);
        }
        // 拉取平台证书列表
        $resp = $this->request('GET', '/v3/certificates');
        if (empty($resp['data'])) {
            throw new \Exception('获取平台证书失败');
        }
        $cert = $resp['data'][0];
        $cipher = $cert['encrypt_certificate'];
        $plain  = $this->decryptAesGcm(
            base64_decode($cipher['ciphertext']),
            $this->v3key,
            $cipher['nonce'],
            $cipher['associated_data']
        );
        file_put_contents($cacheFile, $plain);
        return $plain;
    }

    /**
     * 验证回调签名并解密资源
     * @param string $body    原始请求体
     * @param array  $headers 回调头（含 Wechatpay-Signature / -Timestamp / -Nonce / -Serial）
     * @return array 解密后的通知数据（含 out_trade_no / transaction_id / trade_state）
     */
    public function handleNotify($body, $headers)
    {
        $timestamp = $headers['wechatpay-timestamp'] ?? '';
        $nonce     = $headers['wechatpay-nonce'] ?? '';
        $signature = $headers['wechatpay-signature'] ?? '';

        // 1. 验签：用平台证书公钥验 SHA256-RSA 签名
        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $pubKey  = $this->getPlatformCert();
        $ok = openssl_verify($message, base64_decode($signature), $pubKey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new \Exception('签名验证失败');
        }
        // 可选：校验序列号是否本商户平台证书（略，已用平台证书验签）

        // 2. 解密 resource
        $data = json_decode($body, true);
        if (empty($data['resource'])) {
            throw new \Exception('通知数据缺失');
        }
        $res = $data['resource'];
        $plain = $this->decryptAesGcm(
            base64_decode($res['ciphertext']),
            $this->v3key,
            $res['nonce'],
            $res['associated_data'] ?? ''
        );
        $notify = json_decode($plain, true);
        if (empty($notify)) {
            throw new \Exception('解密失败');
        }
        return $notify;
    }

    /**
     * AES-256-GCM 解密（微信 V3 资源解密）
     */
    private function decryptAesGcm($ciphertext, $key, $nonce, $aad)
    {
        if (function_exists('openssl_decrypt')) {
            $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag = '', $aad);
            unset($tag);
            if ($plain === false) {
                throw new \Exception('AES-GCM 解密失败');
            }
            return $plain;
        }
        throw new \Exception('openssl 扩展不可用');
    }

    /**
     * 发送 V3 请求（带 Authorization 签名）
     */
    private function request($method, $path, $body = null)
    {
        $url = self::API_BASE . $path;
        $bodyStr = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE);
        $token = $this->buildAuthorization($method, $path, $bodyStr);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $headers = array(
            'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: ZhiCms-MiniApp',
        );
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err) {
            throw new \Exception('请求微信失败：' . $err);
        }
        $dec = json_decode($resp, true);
        return is_array($dec) ? $dec : array();
    }

    /**
     * 生成 V3 Authorization 头（RSA-SHA256 签名）
     */
    private function buildAuthorization($method, $path, $bodyStr)
    {
        $timestamp = time();
        $nonce     = uniqid('', true);
        $message   = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $bodyStr . "\n";
        $privateKey = file_get_contents(self::CERT_DIR . 'apiclient_key.pem');
        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $sign = base64_encode($signature);
        return sprintf(
            'mchid="%s",nonce_str="%s",signature="%s",timestamp="%d",serial_no="%s"',
            $this->mchid, $nonce, $sign, $timestamp, $this->serialNo
        );
    }
}
