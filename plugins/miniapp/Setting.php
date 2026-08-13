<?php
namespace plugins\miniapp;

/**
 * 小程序 & App 插件后台配置
 * 仅保存自营商城所需的微信支付 V3 参数 + 余额支付开关。
 * 所有前端通信走主站 api（api/shop/*），本配置只服务于 ShopController 下单与回调。
 */
class Setting
{
    private $meta;

    public function __construct($meta = array())
    {
        $this->meta = $meta;
    }

    /**
     * 渲染配置表单
     */
    public function view()
    {
        $cfg = \ZhiCms\base\PluginManager::getConfig('miniapp');
        $cfg = is_array($cfg) ? $cfg : array();
        $v = function ($k, $d = '') use ($cfg) {
            return isset($cfg[$k]) ? htmlspecialchars($cfg[$k], ENT_QUOTES) : $d;
        };
        ob_start();
        ?>
        <div class="card">
            <div class="card-header">自营商城 · 微信支付 V3 配置</div>
            <div class="card-body">
                <div class="form-group">
                    <label>微信 AppID</label>
                    <input type="text" name="wx_appid" class="form-control" value="<?php echo $v('wx_appid'); ?>" placeholder="wxxxxxxxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label>微信商户号 MCHID</label>
                    <input type="text" name="wx_mchid" class="form-control" value="<?php echo $v('wx_mchid'); ?>" placeholder="1900000000">
                </div>
                <div class="form-group">
                    <label>商户 APIv3 密钥</label>
                    <input type="text" name="wx_api_v3_key" class="form-control" value="<?php echo $v('wx_api_v3_key'); ?>" placeholder="32位密钥">
                </div>
                <div class="form-group">
                    <label>商户证书序列号</label>
                    <input type="text" name="wx_serial_no" class="form-control" value="<?php echo $v('wx_serial_no'); ?>" placeholder="证书序列号">
                </div>
                <div class="form-group">
                    <label>余额支付</label>
                    <select name="balance_enable" class="form-control">
                        <option value="1" <?php echo $v('balance_enable','1')=='1'?'selected':''; ?>>开启</option>
                        <option value="0" <?php echo $v('balance_enable','1')=='0'?'selected':''; ?>>关闭</option>
                    </select>
                </div>
                <p class="text-muted">证书文件请放置于站点根目录 cert/ 下：apiclient_cert.pem 与 apiclient_key.pem</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 保存配置
     */
    public function save($post)
    {
        return array(
            'wx_appid'       => trim($post['wx_appid'] ?? ''),
            'wx_mchid'       => trim($post['wx_mchid'] ?? ''),
            'wx_api_v3_key'  => trim($post['wx_api_v3_key'] ?? ''),
            'wx_serial_no'   => trim($post['wx_serial_no'] ?? ''),
            'balance_enable' => isset($post['balance_enable']) ? intval($post['balance_enable']) : 1,
        );
    }
}
