<template>
  <view v-if="visible" class="cb-tip" @tap="onCopy">
    <view class="cb-icon">🔗</view>
    <view class="cb-body">
      <text class="cb-title">检测到{{ payload.label }}口令</text>
      <text class="cb-sub">{{ payload.platform === 'taobao' ? '点击复制专属淘口令' : '点击复制链接（当前支持淘宝转链）' }}</text>
    </view>
    <view class="cb-btn">复制</view>
    <view class="cb-close" @tap.stop="hide">✕</view>
  </view>
</template>

<script>
import { copyConverted } from '../../utils/clipboard.js';

export default {
  data() {
    return {
      visible: false,
      payload: { label: '', platform: '', converted: '', title: '' },
      timer: null,
    };
  },

  created() {
    uni.$on('clipboard:recognized', this.show);
  },

  beforeDestroy() {
    uni.$off('clipboard:recognized', this.show);
    if (this.timer) clearTimeout(this.timer);
  },

  methods: {
    show(payload) {
      this.payload = payload;
      this.visible = true;
      if (this.timer) clearTimeout(this.timer);
      // 12 秒后自动收起，避免长期遮挡
      this.timer = setTimeout(() => { this.visible = false; }, 12000);
    },

    hide() {
      this.visible = false;
      if (this.timer) clearTimeout(this.timer);
    },

    onCopy() {
      copyConverted(this.payload.converted);
      this.hide();
    },
  },
};
</script>

<style scoped>
.cb-tip {
  position: fixed;
  left: 24rpx;
  right: 24rpx;
  /* 停在原生 tabBar 上方，不被遮挡 */
  bottom: calc(var(--window-bottom) + 24rpx);
  z-index: 9999;
  display: flex;
  align-items: center;
  padding: 20rpx 24rpx;
  background: #fff;
  border-radius: 24rpx;
  box-shadow: 0 12rpx 40rpx rgba(20, 21, 26, 0.18);
  border: 1rpx solid #f0f1f4;
}

.cb-icon {
  font-size: 40rpx;
  margin-right: 16rpx;
  flex-shrink: 0;
}

.cb-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.cb-title {
  font-size: 28rpx;
  font-weight: 700;
  color: #1a1a2e;
  line-height: 1.3;
}

.cb-sub {
  font-size: 22rpx;
  color: #9096a0;
  margin-top: 4rpx;
}

.cb-btn {
  flex-shrink: 0;
  background: linear-gradient(135deg, #ff5b6e, #ff8a5b);
  color: #fff;
  font-size: 26rpx;
  font-weight: 700;
  padding: 14rpx 32rpx;
  border-radius: 30rpx;
  margin-left: 16rpx;
}

.cb-close {
  flex-shrink: 0;
  margin-left: 12rpx;
  width: 48rpx;
  height: 48rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #b0b4be;
  font-size: 28rpx;
}
</style>
