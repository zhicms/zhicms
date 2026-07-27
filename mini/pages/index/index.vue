<template>
  <view class="home">
    <!-- 顶部品牌 + 搜索 -->
    <view class="header">
      <view class="header-deco deco1"></view>
      <view class="header-deco deco2"></view>
      <view class="header-top">
        <view class="brand">
          <view class="brand-mark">值</view>
          <view class="brand-text">
            <text class="brand-name">好价严选</text>
            <text class="brand-sub">发现每一分钱的精明</text>
          </view>
        </view>
        <view class="header-msg" @tap="tapMsg">
          <text class="header-msg-icon">🔔</text>
          <view class="header-msg-dot"></view>
        </view>
      </view>
      <view class="search-bar">
        <text class="search-icon">🔍</text>
        <input
          class="search-input"
          v-model="keyword"
          placeholder="搜索你想买的好物"
          placeholder-class="ph"
          confirm-type="search"
          @confirm="onSearch"
        />
        <text v-if="keyword" class="search-clear" @tap="clearSearch">✕</text>
      </view>
    </view>

    <!-- AI 导购入口 -->
    <view class="ai-banner" @tap="goGuide">
      <view class="ai-banner-icon"><text>🤖</text></view>
      <view class="ai-banner-text">
        <text class="ai-banner-title">AI 导购顾问</text>
        <text class="ai-banner-sub">不知道买什么？问问 AI，智能推荐好物</text>
      </view>
      <text class="ai-banner-go">去聊聊</text>
    </view>

    <!-- 分类导航 -->
    <scroll-view class="cat-nav" scroll-x>
      <view class="cat-list">
        <view
          v-for="c in categories"
          :key="c.id"
          class="cat-chip"
          :class="{ active: activeCat === c.id }"
          @tap="switchCat(c.id)"
        >{{ c.name }}</view>
      </view>
    </scroll-view>

    <!-- 分区标题 -->
    <view class="section-head">
      <view class="section-bar"></view>
      <text class="section-title">好价推荐</text>
      <text class="section-sub">为你精选高性价比好物</text>
    </view>

    <!-- 好价信息流 -->
    <view class="feed">
      <view
        v-for="item in feed"
        :key="item.id"
        class="deal"
        @tap="openProduct(item)"
      >
        <view class="deal-img-wrap">
          <image class="deal-img" :src="item.pic" mode="aspectFill" lazy-load />
          <view v-if="item.discount > 0" class="deal-discount">{{ item.discount }}折</view>
        </view>

        <view class="deal-body">
          <text class="deal-title">{{ item.title }}</text>

          <view class="deal-tags">
            <text class="tag tag-shop">{{ item.shopLabel }}</text>
            <text v-if="item.couponPrice > 0" class="tag tag-coupon">券¥{{ item.couponPrice }}</text>
            <text v-if="item.isChoice" class="tag tag-choice">精选</text>
            <text v-if="item.catName" class="tag tag-cat">{{ item.catName }}</text>
          </view>

          <view class="deal-price">
            <text class="price-symbol">¥</text>
            <text class="price-num">{{ item.price }}</text>
            <text v-if="item.originalPrice > 0" class="price-ori">¥{{ item.originalPrice }}</text>
          </view>

          <view class="deal-footer">
            <view class="worth">
              <view class="worth-bar">
                <view class="worth-fill" :style="{ width: item.worthRate + '%' }"></view>
              </view>
              <text class="worth-text">值率{{ item.worthRate }}%</text>
            </view>
            <text class="deal-sales">月销{{ formatSales(item.monthSales) }}</text>
          </view>
        </view>
      </view>

      <view v-if="feed.length === 0 && !loading" class="empty">{{ tip }}</view>
      <view v-if="feed.length > 0 && !hasMore" class="feed-end">— 没有更多了 —</view>
    </view>

    <!-- 悬浮 AI 按钮 -->
    <view class="fab" @tap="goGuide">
      <text class="fab-icon">🤖</text>
    </view>

    <!-- 剪贴板识别提示条（全局监听在 App.vue onLaunch 注册） -->
    <clipboard-tip />

    <!-- 自定义底部导航 -->
    <app-tabbar ref="tabbar" />
  </view>
</template>

<script>
import { BACKEND_BASE } from '../../config.js';
import { openProduct as openTaoProduct } from '../../utils/openLink.js';
import { getBackendBase } from '../../utils/appConfig.js';

export default {
  data() {
    return {
      feed: [],
      categories: [{ id: 0, name: '精选' }],
      activeCat: 0,
      keyword: '',
      page: 1,
      hasMore: true,
      loading: false,
      tip: '加载中…',
    };
  },

  onLoad() {
    this.loadFeed();
  },

  onPullDownRefresh() {
    this.page = 1;
    this.loadFeed(() => uni.stopPullDownRefresh());
  },

  onReachBottom() {
    if (this.hasMore && !this.loading) {
      this.page += 1;
      this.loadFeed();
    }
  },

  onShow() {
    if (this.$refs.tabbar) this.$refs.tabbar.refresh();
  },

  methods: {
    base() {
      return (getBackendBase() || BACKEND_BASE || '').replace(/\/+$/, '');
    },

    switchCat(id) {
      if (this.activeCat === id) return;
      this.activeCat = id;
      this.keyword = '';
      this.page = 1;
      this.loadFeed();
    },

    onSearch() {
      this.page = 1;
      this.loadFeed();
    },

    clearSearch() {
      this.keyword = '';
      this.page = 1;
      this.loadFeed();
    },

    loadFeed(done) {
      const base = this.base();
      if (!base) {
        this.tip = '请先在 config.js 配置 BACKEND_BASE';
        this.loading = false;
        if (done) done();
        return;
      }
      this.loading = true;
      uni.request({
        url: base + '/index.php?r=api/feed/index',
        method: 'GET',
        data: { cid: this.activeCat, keyword: this.keyword, page: this.page },
        success: (res) => {
          const d = res.data || {};
          if (d.code === 1) {
            const items = d.items || [];
            if (this.page <= 1) this.feed = items;
            else this.feed = this.feed.concat(items);
            if (d.categories && d.categories.length) this.categories = d.categories;
            this.hasMore = items.length >= (d.page_size || 10);
            this.tip = this.feed.length ? '' : '暂无好价爆料';
          } else {
            if (this.page <= 1) this.feed = [];
            this.tip = '暂无数据';
          }
        },
        fail: () => {
          if (this.page <= 1) this.feed = [];
          this.tip = '加载失败，请检查后端配置';
        },
        complete: () => {
          this.loading = false;
          if (done) done();
        },
      });
    },

    formatSales(n) {
      n = parseInt(n, 10) || 0;
      if (n >= 10000) return (n / 10000).toFixed(1) + '万';
      return n;
    },

    openProduct(item) {
      openTaoProduct({
        id: item.goodsId,
        goodsId: item.goodsId,
        goodsSign: item.goodsSign,
        name: item.title,
        price: item.price,
        itemLink: item.itemLink,
        couponLink: item.couponLink,
        platform: item.item_from || 'taobao',
      });
    },

    goGuide() {
      uni.switchTab({ url: '/pages/guide/guide' });
    },

    tapMsg() {
      uni.showToast({ title: '消息通知（即将上线）', icon: 'none' });
    },
  },
};
</script>

<style scoped>
.home {
  min-height: 100vh;
  background: #f3f4f6;
  padding-bottom: 180rpx;
}

/* ===== 顶部 ===== */
.header {
  position: sticky;
  top: 0;
  z-index: 20;
  padding: 28rpx 28rpx 34rpx;
  background: linear-gradient(135deg, #ff4d63 0%, #ff7a4d 100%);
  border-radius: 0 0 36rpx 36rpx;
  box-shadow: 0 14rpx 34rpx rgba(255, 77, 99, 0.28);
  overflow: hidden;
}
.header-deco { position: absolute; border-radius: 50%; filter: blur(40rpx); z-index: 0; }
.deco1 { width: 260rpx; height: 260rpx; background: rgba(255, 255, 255, 0.20); top: -90rpx; right: -50rpx; }
.deco2 { width: 220rpx; height: 220rpx; background: rgba(255, 122, 77, 0.38); bottom: -100rpx; left: -70rpx; }
.header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22rpx;
  position: relative;
  z-index: 1;
}
.brand { display: flex; align-items: center; gap: 12rpx; }
.brand-mark {
  width: 58rpx;
  height: 58rpx;
  border-radius: 18rpx;
  background: #fff;
  color: #ff4d63;
  font-size: 38rpx;
  font-weight: 900;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6rpx 16rpx rgba(0, 0, 0, 0.14);
}
.brand-text { display: flex; flex-direction: column; }
.brand-name { font-size: 38rpx; font-weight: 800; color: #fff; letter-spacing: 1rpx; }
.brand-sub { font-size: 20rpx; color: rgba(255, 255, 255, 0.82); margin-top: 4rpx; letter-spacing: 1rpx; }
.header-msg {
  position: relative;
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
}
.header-msg-icon { font-size: 32rpx; }
.header-msg-dot {
  position: absolute;
  top: 14rpx;
  right: 16rpx;
  width: 14rpx;
  height: 14rpx;
  border-radius: 50%;
  background: #fff;
  border: 3rpx solid #ff4d63;
}
.search-bar {
  background: #fff;
  border-radius: 46rpx;
  padding: 18rpx 28rpx;
  display: flex;
  align-items: center;
  gap: 14rpx;
  box-shadow: inset 0 2rpx 6rpx rgba(0, 0, 0, 0.04);
}
.search-icon { font-size: 28rpx; opacity: 0.55; }
.search-input { flex: 1; font-size: 28rpx; color: #14151a; }
.ph { color: #b4b8c0; }
.search-clear { font-size: 26rpx; color: #b4b8c0; }

/* ===== AI 导购横幅 ===== */
.ai-banner {
  position: relative;
  margin: 24rpx 24rpx 0;
  background: linear-gradient(120deg, #ffffff 55%, #fff3f0 100%);
  border-radius: 28rpx;
  padding: 26rpx 30rpx;
  display: flex;
  align-items: center;
  gap: 22rpx;
  box-shadow: 0 10rpx 30rpx rgba(20, 21, 26, 0.06);
  overflow: hidden;
}
.ai-banner::before {
  content: '';
  position: absolute;
  right: -40rpx;
  top: -40rpx;
  width: 180rpx;
  height: 180rpx;
  background: radial-gradient(circle, rgba(255, 120, 90, 0.16), transparent 70%);
}
.ai-banner-icon {
  width: 92rpx;
  height: 92rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 50rpx;
  background: linear-gradient(135deg, #ff5566, #ff8a5b);
  border-radius: 24rpx;
  box-shadow: 0 10rpx 22rpx rgba(255, 90, 90, 0.34);
}
.ai-banner-text { flex: 1; display: flex; flex-direction: column; }
.ai-banner-title { font-size: 30rpx; font-weight: 800; color: #14151a; }
.ai-banner-sub { font-size: 23rpx; color: #9096a0; margin-top: 8rpx; }
.ai-banner-go {
  font-size: 24rpx;
  color: #ff4d63;
  font-weight: 700;
  background: #fff0ee;
  padding: 12rpx 24rpx;
  border-radius: 30rpx;
}

/* ===== 分类导航 ===== */
.cat-nav {
  white-space: nowrap;
  padding: 26rpx 12rpx 4rpx;
}
.cat-list { display: inline-flex; gap: 14rpx; padding: 0 12rpx; }
.cat-chip {
  display: inline-block;
  padding: 14rpx 34rpx;
  background: #fff;
  border-radius: 40rpx;
  font-size: 26rpx;
  color: #6b7078;
  font-weight: 500;
  box-shadow: 0 4rpx 14rpx rgba(20, 21, 26, 0.04);
}
.cat-chip.active {
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  color: #fff;
  font-weight: 700;
  box-shadow: 0 8rpx 20rpx rgba(255, 77, 99, 0.32);
}

/* ===== 分区标题 ===== */
.section-head {
  display: flex;
  align-items: center;
  gap: 14rpx;
  padding: 24rpx 30rpx 6rpx;
}
.section-bar {
  width: 8rpx;
  height: 30rpx;
  border-radius: 6rpx;
  background: linear-gradient(180deg, #ff4d63, #ff7a4d);
}
.section-title { font-size: 34rpx; font-weight: 800; color: #14151a; letter-spacing: 1rpx; }
.section-sub { font-size: 22rpx; color: #9096a0; margin-left: 4rpx; }

/* ===== 信息流 ===== */
.feed { padding: 10rpx 24rpx; }
.deal {
  display: flex;
  background: #fff;
  border-radius: 28rpx;
  padding: 22rpx;
  margin-bottom: 22rpx;
  box-shadow: 0 8rpx 30rpx rgba(20, 21, 26, 0.05);
}
.deal-img-wrap {
  width: 224rpx;
  height: 224rpx;
  border-radius: 22rpx;
  overflow: hidden;
  position: relative;
  flex-shrink: 0;
  background: #f2f3f5;
}
.deal-img { width: 100%; height: 100%; }
.deal-discount {
  position: absolute;
  left: 12rpx;
  top: 12rpx;
  background: linear-gradient(135deg, #ff4d63, #ff6f43);
  color: #fff;
  font-size: 22rpx;
  font-weight: 800;
  padding: 6rpx 16rpx;
  border-radius: 24rpx;
  box-shadow: 0 6rpx 14rpx rgba(255, 77, 99, 0.4);
}
.deal-body {
  flex: 1;
  margin-left: 22rpx;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.deal-title {
  font-size: 28rpx;
  color: #14151a;
  font-weight: 600;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.deal-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8rpx;
  margin-top: 14rpx;
}
.tag {
  font-size: 20rpx;
  padding: 5rpx 14rpx;
  border-radius: 8rpx;
  line-height: 1.4;
  font-weight: 600;
}
.tag-shop { background: #fff3ec; color: #ff7a45; }
.tag-coupon { background: #ffecec; color: #ff4d63; }
.tag-choice { background: #fff6e0; color: #e0930a; }
.tag-cat { background: #f1f2f5; color: #8a8f99; }

.deal-price {
  display: flex;
  align-items: baseline;
  gap: 10rpx;
  margin-top: auto;
}
.price-symbol { font-size: 24rpx; color: #ff4d63; font-weight: 800; }
.price-num { font-size: 48rpx; color: #ff4d63; font-weight: 900; line-height: 1; letter-spacing: -1rpx; }
.price-ori {
  font-size: 22rpx;
  color: #b4b8c0;
  text-decoration: line-through;
}

.deal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 16rpx;
}
.worth { display: flex; align-items: center; gap: 10rpx; flex: 1; }
.worth-bar {
  flex: 1;
  max-width: 190rpx;
  height: 12rpx;
  background: #f1f2f5;
  border-radius: 12rpx;
  overflow: hidden;
}
.worth-fill {
  height: 100%;
  background: linear-gradient(90deg, #ff7a4d, #ff4d63);
  border-radius: 12rpx;
}
.worth-text { font-size: 20rpx; color: #ff6f43; font-weight: 700; }
.deal-sales { font-size: 22rpx; color: #9096a0; flex-shrink: 0; }

.empty { text-align: center; color: #9096a0; font-size: 26rpx; padding: 100rpx 0; }
.feed-end { text-align: center; color: #c4c8d0; font-size: 24rpx; padding: 36rpx 0; letter-spacing: 2rpx; }

/* ===== 悬浮按钮 ===== */
.fab {
  position: fixed;
  right: 32rpx;
  bottom: 150rpx;
  z-index: 50;
  width: 104rpx;
  height: 104rpx;
  border-radius: 50%;
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 12rpx 30rpx rgba(255, 77, 99, 0.5);
}
.fab-icon { font-size: 52rpx; }
</style>
