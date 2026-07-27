<template>
  <view class="guide-page">
    <!-- 导购 = 内嵌 AI 智能购物顾问 -->
    <ai-chat
      :theme-color="themeColor"
      :default-role="defaultRole"
      :api-key="apiKey"
      :api-url="apiUrl"
      :backend-base="backendBase"
      height="100%"
      @product-tap="onProductTap"
      @buy="onBuy"
    />

    <!-- 剪贴板识别提示条（全局监听在 App.vue onLaunch 注册） -->
    <clipboard-tip />

    <!-- 自定义底部导航 -->
    <app-tabbar ref="tabbar" />
  </view>
</template>

<script>
import AiChat from '../../components/ai-chat/ai-chat.vue';
import { BACKEND_BASE } from '../../config.js';
import { openProduct as openTaoProduct } from '../../utils/openLink.js';

export default {
  components: { AiChat },

  data() {
    return {
      apiKey: '',
      apiUrl: '',
      themeColor: '#ff4d63', // 与首页现代珊瑚红保持一致
      defaultRole: 'shopping',
      backendBase: BACKEND_BASE || '',
    };
  },

  onLoad() {
    const app = getApp();
    const g = (app && app.globalData) || {};
    const base = (g.backendBase || BACKEND_BASE || '').replace(/\/+$/, '');
    this.backendBase = base;

    const ai = g.ai || {};
    if (ai.enabled) {
      this.apiUrl = ai.api_url || '';
      this.apiKey = ai.token || 'miniapp';
    }
    if (ai.theme_color) this.themeColor = ai.theme_color;
    if (ai.default_role) this.defaultRole = ai.default_role;

    // 后端配置未随启动拉取时，主动补拉一次
    if (!this.apiUrl && base) {
      uni.request({
        url: base + '/index.php?r=api/config',
        method: 'GET',
        success: (res) => {
          const d = (res && res.data && res.data.data) || {};
          if (d.ai && d.ai.enabled) {
            this.apiUrl = d.ai.api_url || '';
            this.apiKey = d.ai.token || 'miniapp';
          }
          if (d.ai && d.ai.theme_color) this.themeColor = d.ai.theme_color;
          if (d.ai && d.ai.default_role) this.defaultRole = d.ai.default_role;
        },
      });
    }
  },

  onShow() {
    if (this.$refs.tabbar) this.$refs.tabbar.refresh();
  },

  methods: {
    onProductTap(p) {
      uni.showModal({
        title: p.name,
        content: `¥${p.price}\n已售：${p.sold || 0}人`,
        confirmText: '复制淘口令',
        cancelText: '关闭',
        success: (r) => {
          if (r.confirm) openTaoProduct(p);
        },
      });
    },
    onBuy(p) {
      openTaoProduct(p);
    },
  },
};
</script>

<style scoped>
.guide-page {
  /* 自定义 tabBar（custom:true）后原生 tabBar 已隐藏，
     容器 bottom 需停在自定义 tabBar 上方（约 110rpx + 安全区）。 */
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: calc(110rpx + env(safe-area-inset-bottom));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #f3f4f6;
}
</style>
