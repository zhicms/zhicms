<template>
  <view class="mine">
    <!-- 已登录：沉浸式会员头部 -->
    <view v-if="user" class="hero">
      <view class="hero-deco deco-1"></view>
      <view class="hero-deco deco-2"></view>

      <view class="hero-user">
        <view class="avatar">
          <text class="avatar-text">{{ avatarText }}</text>
        </view>
        <view class="hero-meta">
          <view class="nickname">
            {{ user.username || '未命名用户' }}
            <text class="vip-tag">VIP</text>
          </view>
          <view class="mobile">{{ maskMobile(user.mobile) }}</view>
        </view>
        <view class="edit-btn" @tap="onPlaceholder('编辑资料')">编辑</view>
      </view>
    </view>

    <!-- 未登录：欢迎头部 -->
    <view v-else class="hero hero-guest">
      <view class="hero-deco deco-1"></view>
      <view class="hero-deco deco-2"></view>
      <view class="guest-head">
        <view class="avatar guest-avatar">👋</view>
        <view class="guest-title">嗨，欢迎来到好价精选</view>
        <view class="guest-sub">登录后同步收藏、爆料与浏览足迹</view>
      </view>
    </view>

    <!-- 已登录：悬浮数据统计卡 -->
    <view v-if="user" class="stats-card">
      <view class="stat" @tap="onPlaceholder('我的收藏')">
        <text class="stat-num">{{ stats.collect }}</text>
        <text class="stat-label">收藏</text>
      </view>
      <view class="stat-divider"></view>
      <view class="stat" @tap="onPlaceholder('我的爆料')">
        <text class="stat-num">{{ stats.post }}</text>
        <text class="stat-label">爆料</text>
      </view>
      <view class="stat-divider"></view>
      <view class="stat" @tap="onPlaceholder('浏览历史')">
        <text class="stat-num">{{ stats.history }}</text>
        <text class="stat-label">足迹</text>
      </view>
    </view>

    <!-- 未登录：登录/注册卡片 -->
    <view v-else class="auth-card">
      <view class="auth-tabs">
        <view class="auth-tab" :class="{ active: mode === 'login' }" @tap="mode = 'login'">登录</view>
        <view class="auth-tab" :class="{ active: mode === 'register' }" @tap="mode = 'register'">注册</view>
      </view>

      <view class="auth-form">
        <input
          v-if="mode === 'register'"
          class="auth-input"
          v-model="form.username"
          placeholder="请输入昵称"
          placeholder-class="ph"
        />
        <input
          class="auth-input"
          v-model="form.mobile"
          type="number"
          maxlength="11"
          placeholder="请输入手机号"
          placeholder-class="ph"
        />
        <input
          class="auth-input"
          v-model="form.password"
          password
          placeholder="请输入密码（至少6位）"
          placeholder-class="ph"
        />
        <button class="auth-btn" :loading="submitting" @tap="submit">{{ mode === 'login' ? '登录' : '注册并登录' }}</button>
        <text class="auth-tip">登录即表示同意《用户协议》，账号与网站互通</text>
      </view>
    </view>

    <!-- 功能菜单 -->
    <view class="section">
      <view class="section-title">常用工具</view>
      <view class="menu">
        <view
          v-for="(m, i) in menus"
          :key="i"
          class="menu-item"
          @tap="onPlaceholder(m.name)"
        >
          <text class="menu-icon" :style="{ background: m.bg }">{{ m.icon }}</text>
          <text class="menu-name">{{ m.name }}</text>
          <text class="menu-arrow">›</text>
        </view>
      </view>
    </view>

    <button v-if="user" class="logout-btn" @tap="doLogout">退出登录</button>
    <view class="safe-bottom"></view>

    <!-- 剪贴板识别提示条（全局监听在 App.vue onLaunch 注册） -->
    <clipboard-tip />

    <!-- 自定义底部导航 -->
    <app-tabbar ref="tabbar" />
  </view>
</template>

<script>
import {
  isLogin,
  getUser,
  login,
  register,
  logout,
  fetchUserInfo,
} from '../../utils/user.js';

export default {
  data() {
    return {
      user: null,
      mode: 'login',
      submitting: false,
      form: { username: '', mobile: '', password: '' },
      // 以下为后续接入真实数据预留的占位统计
      stats: { collect: '-', post: '-', history: '-' },
      menus: [
        { name: '我的收藏', icon: '⭐', bg: '#fff4e0' },
        { name: '我的爆料', icon: '📢', bg: '#ffe9e9' },
        { name: '我的评论', icon: '💬', bg: '#e7f3ff' },
        { name: '浏览历史', icon: '🕘', bg: '#eef0f5' },
        { name: '消息通知', icon: '🔔', bg: '#fff0e6' },
        { name: '系统设置', icon: '⚙️', bg: '#eaf7ee' },
      ],
    };
  },

  computed: {
    // 头像字母：登录后随 user 变化实时更新（原先写在 data getter 里不会刷新）
    avatarText() {
      const name = (this.user && this.user.username) || '我';
      return name.substr(0, 1).toUpperCase();
    },
  },

  onShow() {
    this.refresh();
    if (this.$refs.tabbar) this.$refs.tabbar.refresh();
  },

  methods: {
    refresh() {
      if (isLogin()) {
        // 优先用缓存；同时静默刷新最新资料
        this.user = getUser();
        fetchUserInfo().then((u) => {
          if (u) this.user = u;
        });
      } else {
        this.user = null;
      }
    },

    maskMobile(m) {
      if (!m || m.length < 11) return m || '';
      return m.substr(0, 3) + '****' + m.substr(7);
    },

    submit() {
      if (this.submitting) return;
      const { username, mobile, password } = this.form;
      if (!/^1\d{10}$/.test(mobile)) {
        uni.showToast({ title: '请输入正确的手机号', icon: 'none' });
        return;
      }
      if (this.mode === 'register' && !username) {
        uni.showToast({ title: '请输入昵称', icon: 'none' });
        return;
      }
      if (!password || password.length < 6) {
        uni.showToast({ title: '密码至少 6 位', icon: 'none' });
        return;
      }

      this.submitting = true;
      const task = this.mode === 'login'
        ? login(mobile, password)
        : register(mobile, username, password);

      task
        .then(() => {
          uni.showToast({ title: this.mode === 'login' ? '登录成功' : '注册成功', icon: 'success' });
          this.refresh();
        })
        .catch((err) => {
          uni.showToast({ title: err.message || '操作失败', icon: 'none' });
        })
        .finally(() => {
          this.submitting = false;
        });
    },

    doLogout() {
      uni.showModal({
        title: '提示',
        content: '确定退出登录？',
        success: (res) => {
          if (res.confirm) {
            logout();
            this.user = null;
            uni.showToast({ title: '已退出', icon: 'none' });
          }
        },
      });
    },

    // 占位：后续接入真实数据
    onPlaceholder(name) {
      if (!this.user) {
        uni.showToast({ title: '请先登录', icon: 'none' });
        return;
      }
      uni.showToast({ title: name + '（即将上线）', icon: 'none' });
    },
  },
};
</script>

<style scoped>
.mine {
  min-height: 100vh;
  background: #f6f7fb;
  padding-bottom: 160rpx;
}

/* ===== 沉浸式头部 ===== */
.hero {
  position: relative;
  padding: 72rpx 40rpx 104rpx;
  background: linear-gradient(135deg, #ff4d63 0%, #ff7a4d 100%);
  overflow: hidden;
}
.hero-deco {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
}
.deco-1 {
  width: 300rpx;
  height: 300rpx;
  top: -110rpx;
  right: -70rpx;
}
.deco-2 {
  width: 200rpx;
  height: 200rpx;
  bottom: -60rpx;
  left: -50rpx;
  background: rgba(255, 255, 255, 0.10);
}

.hero-user {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
}
.avatar {
  width: 128rpx;
  height: 128rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.22);
  border: 4rpx solid rgba(255, 255, 255, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.avatar-text {
  font-size: 58rpx;
  font-weight: 800;
  color: #fff;
}
.hero-meta {
  flex: 1;
  margin-left: 24rpx;
  min-width: 0;
}
.nickname {
  font-size: 40rpx;
  font-weight: 800;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 14rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.vip-tag {
  font-size: 20rpx;
  font-weight: 700;
  color: #b9701a;
  background: linear-gradient(135deg, #ffe7a3, #ffd16b);
  padding: 3rpx 14rpx;
  border-radius: 20rpx;
  flex-shrink: 0;
}
.mobile {
  font-size: 26rpx;
  color: rgba(255, 255, 255, 0.85);
  margin-top: 12rpx;
  letter-spacing: 1rpx;
}
.edit-btn {
  font-size: 24rpx;
  color: #fff;
  font-weight: 600;
  border: 1rpx solid rgba(255, 255, 255, 0.6);
  padding: 10rpx 26rpx;
  border-radius: 30rpx;
  flex-shrink: 0;
}

/* 未登录头部 */
.hero-guest {
  padding-bottom: 96rpx;
}
.guest-head {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.guest-avatar {
  background: rgba(255, 255, 255, 0.22);
  font-size: 60rpx;
}
.guest-title {
  font-size: 38rpx;
  font-weight: 800;
  color: #fff;
  margin-top: 24rpx;
}
.guest-sub {
  font-size: 26rpx;
  color: rgba(255, 255, 255, 0.85);
  margin-top: 12rpx;
}

/* ===== 悬浮数据统计卡 ===== */
.stats-card {
  position: relative;
  z-index: 5;
  margin: -64rpx 24rpx 0;
  background: #fff;
  border-radius: 28rpx;
  padding: 38rpx 0;
  display: flex;
  align-items: center;
  box-shadow: 0 16rpx 40rpx rgba(255, 77, 99, 0.16);
}
.stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.stat-num {
  font-size: 44rpx;
  font-weight: 800;
  color: #1a1a2e;
  line-height: 1.1;
}
.stat-label {
  font-size: 25rpx;
  color: #9096a0;
  margin-top: 10rpx;
}
.stat-divider {
  width: 1rpx;
  height: 56rpx;
  background: #f0f1f4;
}

/* ===== 登录/注册卡 ===== */
.auth-card {
  position: relative;
  z-index: 5;
  margin: -52rpx 24rpx 0;
  background: #fff;
  border-radius: 28rpx;
  padding: 44rpx 36rpx;
  box-shadow: 0 12rpx 40rpx rgba(20, 21, 26, 0.06);
}
.auth-tabs {
  display: flex;
  gap: 48rpx;
  border-bottom: 1rpx solid #f0f1f4;
  padding-bottom: 22rpx;
}
.auth-tab {
  font-size: 36rpx;
  color: #9096a0;
  font-weight: 600;
  position: relative;
  padding-bottom: 14rpx;
}
.auth-tab.active {
  color: #14151a;
  font-weight: 800;
}
.auth-tab.active::after {
  content: '';
  position: absolute;
  left: 50%;
  bottom: 0;
  transform: translateX(-50%);
  width: 52rpx;
  height: 6rpx;
  border-radius: 6rpx;
  background: linear-gradient(90deg, #ff4d63, #ff7a4d);
}
.auth-form {
  margin-top: 36rpx;
}
.auth-input {
  background: #f4f5f7;
  border-radius: 20rpx;
  padding: 26rpx 30rpx;
  font-size: 32rpx;
  font-weight: 500;
  color: #14151a;
  margin-bottom: 22rpx;
}
.ph {
  color: #b4b8c0;
}
.auth-btn {
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  color: #fff;
  font-size: 32rpx;
  font-weight: 700;
  border-radius: 20rpx;
  margin-top: 16rpx;
  box-shadow: 0 12rpx 26rpx rgba(255, 77, 99, 0.34);
}
.auth-btn::after { border: none; }
.auth-tip {
  display: block;
  text-align: center;
  font-size: 24rpx;
  color: #b4b8c0;
  margin-top: 24rpx;
}

/* ===== 菜单 ===== */
.section {
  margin: 24rpx 24rpx 0;
}
.section-title {
  font-size: 26rpx;
  color: #9096a0;
  font-weight: 600;
  margin: 8rpx 8rpx 16rpx;
}
.menu {
  background: #fff;
  border-radius: 28rpx;
  overflow: hidden;
  box-shadow: 0 12rpx 40rpx rgba(20, 21, 26, 0.05);
}
.menu-item {
  display: flex;
  align-items: center;
  padding: 28rpx 32rpx;
  border-bottom: 1rpx solid #f5f6f8;
}
.menu-item:last-child {
  border-bottom: none;
}
.menu-item:active {
  background: #fafbfc;
}
.menu-icon {
  width: 72rpx;
  height: 72rpx;
  border-radius: 22rpx;
  font-size: 36rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.menu-name {
  flex: 1;
  font-size: 31rpx;
  color: #1a1a2e;
  font-weight: 600;
  margin-left: 24rpx;
}
.menu-arrow {
  font-size: 38rpx;
  color: #c4c8d0;
}

/* ===== 退出 ===== */
.logout-btn {
  margin: 28rpx 24rpx 0;
  background: #fff;
  color: #ff4d63;
  font-size: 31rpx;
  font-weight: 600;
  border-radius: 20rpx;
  box-shadow: 0 8rpx 24rpx rgba(20, 21, 26, 0.05);
}
.logout-btn::after { border: none; }

.safe-bottom {
  height: env(safe-area-inset-bottom);
}
</style>
