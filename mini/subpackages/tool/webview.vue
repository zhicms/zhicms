<template>
  <view class="webview-page">
    <!--
      web-view 承载外部商品详情页（主站 detail_url）。
      微信小程序需在后台配置该域名到「业务域名」白名单。
      App 端建议直接用 plus.runtime.openURL（见 utils/openLink.js），
      此页面作为 H5 / 小程序兜底。
    -->
    <web-view :src="src" @message="onMessage"></web-view>

    <!-- 剪贴板识别提示条（全局监听在 App.vue onLaunch 注册） -->
    <clipboard-tip />
  </view>
</template>

<script>
export default {
  data() {
    return {
      src: '',
    };
  },

  onLoad(options) {
    this.src = decodeURIComponent(options.src || '');
  },

  methods: {
    onMessage(e) {
      // 可接收 web-view 内 postMessage
      console.log('[web-view] message:', e.detail);
    },
  },
};
</script>

<style scoped>
.webview-page {
  width: 100%;
  height: 100vh;
}
</style>
