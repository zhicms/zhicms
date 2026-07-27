<template>
  <view class="tabbar" v-if="tabs.length">
    <view
      v-for="(t, i) in tabs"
      :key="t.pagePath"
      class="tab-item"
      :class="{ active: i === current }"
      @tap="switchTab(t, i)"
    >
      <view class="tab-icon-wrap">
        <text class="tab-icon">{{ t.icon }}</text>
      </view>
      <text class="tab-text">{{ t.text }}</text>
      <view class="tab-indicator"></view>
    </view>
  </view>
</template>

<script>
/**
 * 自定义底部导航栏（tabBar.custom = true 时由本组件渲染）
 * - 社区 Tab 是否展示由后台开关 forum_on 控制（App.vue 拉取 /api/config 后写入 globalData + storage）
 * - 选中态根据当前页面路由自动计算，避免依赖平台相关 getTabBar API，兼容小程序与 App
 */
export default {
  data() {
    return {
      current: 0,
      forumOn: true,
      allTabs: [
        { pagePath: '/pages/index/index', text: '首页', icon: '🏠' },
        { pagePath: '/pages/guide/guide', text: '导购', icon: '🧭' },
        { pagePath: '/pages/community/community', text: '社区', icon: '💬' },
        { pagePath: '/pages/mine/mine', text: '我的', icon: '👤' },
      ],
    };
  },

  computed: {
    tabs() {
      return this.allTabs.filter((t) => {
        if (t.pagePath.indexOf('community') !== -1) return this.forumOn;
        return true;
      });
    },
  },

  created() {
    this.refresh();
    uni.$on('forum-config-changed', this.refresh);
  },

  beforeDestroy() {
    uni.$off('forum-config-changed', this.refresh);
  },

  methods: {
    refresh() {
      const app = getApp();
      let on = true;
      if (app && app.globalData && typeof app.globalData.forumOn !== 'undefined') {
        on = !!app.globalData.forumOn;
      } else {
        on = uni.getStorageSync('zhicms_forum_on') !== '0';
      }
      this.forumOn = on;
      this.syncCurrent();
    },

    syncCurrent() {
      const pages = getCurrentPages();
      const cur = pages[pages.length - 1];
      const route = cur ? '/' + cur.route : '';
      const idx = this.tabs.findIndex((t) => t.pagePath === route);
      this.current = idx >= 0 ? idx : 0;
    },

    switchTab(t, i) {
      if (i === this.current) return;
      uni.switchTab({ url: t.pagePath });
    },
  },
};
</script>

<style scoped>
.tabbar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  display: flex;
  align-items: flex-start;
  height: 104rpx;
  padding: 10rpx 0 env(safe-area-inset-bottom);
  /* 磨砂玻璃质感 + 顶部高光线 */
  background: rgba(255, 255, 255, 0.86);
  backdrop-filter: blur(22px) saturate(180%);
  -webkit-backdrop-filter: blur(22px) saturate(180%);
  border-top: 1rpx solid rgba(255, 255, 255, 0.6);
  box-shadow: 0 -10rpx 30rpx rgba(20, 21, 26, 0.08);
}

.tab-item {
  position: relative;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6rpx;
  padding-top: 8rpx;
  color: #9aa0a6;
  transition: color 0.25s ease;
}

/* 图标容器：激活态悬浮高亮徽标 */
.tab-icon-wrap {
  width: 60rpx;
  height: 60rpx;
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  transition: background 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
    box-shadow 0.3s ease;
}
.tab-icon {
  font-size: 40rpx;
  line-height: 1;
  filter: grayscale(35%);
  opacity: 0.62;
  transform: scale(0.96);
  transition: filter 0.3s ease, opacity 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.tab-text {
  font-size: 21rpx;
  font-weight: 500;
  letter-spacing: 1rpx;
  opacity: 0.7;
  transition: opacity 0.25s ease, font-weight 0.25s ease;
}

/* 激活态 */
.tab-item.active {
  color: #ff4d63;
}
.tab-item.active .tab-icon-wrap {
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  transform: translateY(-6rpx) scale(1.04);
  box-shadow: 0 12rpx 22rpx rgba(255, 77, 99, 0.38);
}
.tab-item.active .tab-icon {
  filter: none;
  opacity: 1;
  transform: scale(1.08);
}
.tab-item.active .tab-text {
  opacity: 1;
  font-weight: 700;
}

/* 激活指示小圆点 */
.tab-indicator {
  position: absolute;
  bottom: 6rpx;
  width: 0;
  height: 6rpx;
  border-radius: 6rpx;
  background: linear-gradient(90deg, #ff4d63, #ff7a4d);
  opacity: 0;
  transition: width 0.3s ease, opacity 0.3s ease;
}
.tab-item.active .tab-indicator {
  width: 36rpx;
  opacity: 1;
}

/* 按压反馈 */
.tab-item:active .tab-icon-wrap {
  transform: scale(0.92);
}
.tab-item.active:active .tab-icon-wrap {
  transform: translateY(-6rpx) scale(0.98);
}
</style>
