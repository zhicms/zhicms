<template>
  <view class="demo-page">
    <!-- 自定义导航栏（custom 模式需自行实现返回） -->
    <view class="nav-bar" :style="navStyle">
      <view class="nav-back" @tap="goBack">
        <text class="nav-back-icon">‹</text>
      </view>
      <text class="nav-title">AI 智能对话</text>
    </view>

    <!-- 聊天区（填充导航栏以下区域） -->
    <view class="chat-wrap" :style="chatWrapStyle">
      <ai-chat
        :theme-color="themeColor"
        :default-role="defaultRole"
        :api-key="apiKey"
        :api-url="apiUrl"
        :backend-base="backendBase"
        height="100%"
        @product-tap="onProductTap"
        @buy="onBuy"
        @recommend="onRecommend"
        @settings-change="onSettingsChange"
      />
    </view>

    <!-- 剪贴板识别提示条（全局监听在 App.vue onLaunch 注册） -->
    <clipboard-tip />
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
      // 以下字段会在 onLoad 时尝试从配套后端 /api/config 拉取并覆盖
      apiKey: '',
      apiUrl: '',
      themeColor: '#6C63FF',
      defaultRole: 'shopping',
      backendBase: BACKEND_BASE || '',
      // 导航栏布局
      statusBar: 20,
      navStyle: '',
      chatWrapStyle: '',
    };
  },

  onLoad(options) {
    // 状态栏高度（App / 小程序均有），用于自定义导航栏占位
    try {
      const info = uni.getSystemInfoSync();
      this.statusBar = info.statusBarHeight || 20;
    } catch (e) {
      this.statusBar = 20;
    }
    this.layoutNav();

    // 支持从路由参数传入配置（优先级最高）
    if (options.role) this.defaultRole = options.role;
    if (options.color) this.themeColor = decodeURIComponent(options.color);
    if (options.apiKey) this.apiKey = decodeURIComponent(options.apiKey);

    // 从配套后端拉取站点/AI/主题配置
    const base = (BACKEND_BASE || '').replace(/\/+$/, '');
    if (!base) return;
    uni.request({
      url: base + '/index.php?r=api/config',
      method: 'GET',
      success: (res) => {
        const d = (res && res.data && res.data.data) || {};
        if (d.ai && d.ai.enabled) {
          this.apiUrl = d.ai.api_url || '';
          this.apiKey = d.ai.token || 'miniapp'; // 访问令牌，非提供商密钥
        }
        if (d.ai && d.ai.theme_color) this.themeColor = d.ai.theme_color;
        if (d.ai && d.ai.default_role) this.defaultRole = d.ai.default_role;
        if (d.site && d.site.site_url) {
          this.backendBase = d.site.site_url;
        }
      },
      fail: () => {
        // 拉取失败则退回组件内置演示模式
      },
    });
  },

  methods: {
    layoutNav() {
      const barH = this.statusBar + 44; // 44px ≈ 88rpx 导航主体高度
      this.navStyle = `height: ${barH}px; padding-top: ${this.statusBar}px;`;
      this.chatWrapStyle = `top: ${barH}px;`;
    },

    goBack() {
      // 作为 tab 页或栈内页面都可安全返回/切换
      const pages = getCurrentPages();
      if (pages.length > 1) {
        uni.navigateBack();
      } else {
        uni.reLaunch({ url: '/pages/index/index' });
      }
    },

    onProductTap(product) {
      console.log('[Demo] 点击商品:', product);
      uni.showModal({
        title: product.name,
        content: `¥${product.price}\n已售：${product.sold || 0}人`,
        confirmText: '复制淘口令',
        cancelText: '关闭',
        success: (res) => {
          if (res.confirm) {
            openTaoProduct(product);
          }
        },
      });
    },

    onBuy(product) {
      openTaoProduct(product);
    },

    onRecommend({ keywords, products }) {
      console.log('[Demo] 触发商品推荐:', keywords, products.length, '件商品');
    },

    onSettingsChange(settings) {
      console.log('[Demo] 设置已更新:', settings);
    },
  },
};
</script>

<style scoped>
.demo-page {
  position: relative;
  width: 100%;
  height: 100vh;
  overflow: hidden;
  background: #f5f5f7;
}

.nav-bar {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 50;
  background: linear-gradient(135deg, #6C63FF, #a78bfa);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2rpx 16rpx rgba(108, 99, 255, 0.2);
}

.nav-back {
  position: absolute;
  left: 16rpx;
  bottom: 0;
  height: 44px;
  width: 72rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.nav-back-icon {
  font-size: 52rpx;
  color: #fff;
  line-height: 1;
}

.nav-title {
  font-size: 34rpx;
  font-weight: 700;
  color: #fff;
  line-height: 44px;
}

.chat-wrap {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
}
</style>
