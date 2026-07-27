<template>
  <view class="community">
    <!-- 顶部分类 Tab -->
    <view class="board-bar">
      <scroll-view class="board-scroll" scroll-x :show-scrollbar="false">
        <view class="board-list">
          <view
            class="board-chip"
            :class="{ active: currentBid === 0 }"
            @tap="switchBoard(0)"
          >综合</view>
          <view
            v-for="b in boards"
            :key="b.id"
            class="board-chip"
            :class="{ active: currentBid === b.id }"
            @tap="switchBoard(b.id)"
          >{{ b.name }}</view>
        </view>
      </scroll-view>
    </view>

    <!-- 发帖触发条 -->
    <view class="post-trigger" @tap="goPost">
      <text class="pt-avatar">{{ avatarText }}</text>
      <view class="pt-input">说点什么，分享好物…</view>
      <view class="pt-btn">发帖</view>
    </view>

    <!-- 帖子流 -->
    <view class="feed">
      <view
        v-for="item in list"
        :key="item.id"
        class="card"
        @tap="openDetail(item)"
      >
        <view class="card-head">
          <text class="c-avatar">{{ item.initial }}</text>
          <view class="c-user">
            <text class="c-name">{{ item.poster }}</text>
            <text class="c-time">{{ item.date }}</text>
          </view>
          <text v-if="item.group" class="c-group">{{ item.group.groupname }}</text>
        </view>

        <view class="c-title" v-if="item.title">{{ item.title }}</view>
        <rich-text class="c-content" :nodes="item.content"></rich-text>

        <!-- 图片九宫格 -->
        <view v-if="item.images && item.images.length" class="c-imgs" :class="item.images.length === 1 ? 'c-imgs-single' : ''">
          <image
            v-for="(img, idx) in item.images"
            :key="idx"
            class="c-img"
            :src="img"
            mode="aspectFill"
            @tap.stop="previewImage(item.images, idx)"
          />
        </view>

        <!-- 商品卡片 -->
        <view v-if="item.goods && item.goods.length" class="c-goods">
          <view v-for="(g, gi) in item.goods" :key="gi" class="goods-card" @tap.stop="openGoods(g)">
            <image v-if="g.pic" class="goods-pic" :src="g.pic" mode="aspectFill" />
            <view class="goods-info">
              <text class="goods-title">{{ g.title }}</text>
              <view class="goods-price">
                <text class="goods-platform">{{ g.platformName || g.platform }}</text>
                <text v-if="g.actPrice" class="goods-act">¥{{ g.actPrice }}</text>
                <text v-if="g.origPrice && g.origPrice > g.actPrice" class="goods-ori">¥{{ g.origPrice }}</text>
                <text v-if="g.coupon" class="goods-coupon">券¥{{ g.coupon }}</text>
              </view>
            </view>
          </view>
        </view>

        <view class="c-foot">
          <view class="c-act" @tap.stop="onLike(item)">
            <text :class="item.liked ? 'act-liked' : ''">{{ item.liked ? '♥' : '♡' }}</text>
            <text>{{ item.like }}</text>
          </view>
          <view class="c-act" @tap.stop="openDetail(item)">
            <text>💬</text><text>{{ item.reply_count }}</text>
          </view>
          <view class="c-act" @tap.stop="openDetail(item)">
            <text>👁</text><text>{{ item.view }}</text>
          </view>
        </view>
      </view>

      <view v-if="list.length === 0 && !loading" class="empty">
        <text class="empty-icon">💬</text>
        <text class="empty-text">还没有帖子，快来发布第一条</text>
      </view>
      <view v-if="loading" class="tip">加载中…</view>
      <view v-if="list.length > 0 && !hasMore" class="feed-end">— 没有更多了 —</view>
    </view>

    <!-- 悬浮发帖按钮 -->
    <view class="fab" @tap="goPost">
      <text class="fab-icon">✏️</text>
    </view>

    <!-- 自定义底部导航 -->
    <app-tabbar ref="tabbar" />
  </view>
</template>

<script>
import { getList, likePost, getVisitor } from '../../utils/forum.js';
import { getUser } from '../../utils/user.js';

export default {
  data() {
    return {
      boards: [],
      list: [],
      currentBid: 0,
      page: 1,
      hasMore: true,
      loading: false,
      tip: '',
    };
  },

  computed: {
    avatarText() {
      const u = getUser();
      const name = (u && u.username) || '我';
      return name.substr(0, 1).toUpperCase();
    },
  },

  onLoad() {
    this.loadList();
  },

  onShow() {
    if (this.$refs.tabbar) this.$refs.tabbar.refresh();
  },

  onPullDownRefresh() {
    this.page = 1;
    this.loadList(() => uni.stopPullDownRefresh());
  },

  onReachBottom() {
    if (this.hasMore && !this.loading) {
      this.page += 1;
      this.loadList();
    }
  },

  methods: {
    loadList(done) {
      if (this.loading) {
        if (done) done();
        return;
      }
      this.loading = true;
      getList({ bid: this.currentBid, page: this.page })
        .then((d) => {
          if (this.page <= 1) {
            this.boards = d.boards || [];
            this.list = d.list || [];
          } else {
            this.list = this.list.concat(d.list || []);
          }
          this.hasMore = !!d.has_more;
          this.tip = this.list.length ? '' : '暂无帖子';
        })
        .catch((err) => {
          if (this.page <= 1) this.list = [];
          this.tip = err.message || '加载失败';
          uni.showToast({ title: this.tip, icon: 'none' });
        })
        .finally(() => {
          this.loading = false;
          if (done) done();
        });
    },

    switchBoard(bid) {
      if (this.currentBid === bid) return;
      this.currentBid = bid;
      this.page = 1;
      this.loadList();
    },

    goPost() {
      uni.navigateTo({ url: '/subpackages/community/post' });
    },

    openDetail(item) {
      uni.navigateTo({ url: '/subpackages/community/detail?id=' + item.id });
    },

    previewImage(images, idx) {
      uni.previewImage({ current: images[idx], urls: images });
    },

    openGoods(g) {
      uni.showToast({ title: (g.platformName || g.platform) + '：' + (g.title || ''), icon: 'none' });
    },

    onLike(item) {
      likePost(item.id, getVisitor())
        .then((d) => {
          item.like = d.count;
          item.liked = true;
        })
        .catch((err) => {
          if (String(err.message || '').indexOf('登录') !== -1) {
            uni.showToast({ title: '登录后可点赞', icon: 'none' });
          } else {
            uni.showToast({ title: err.message || '操作失败', icon: 'none' });
          }
        });
    },
  },
};
</script>

<style scoped>
.community {
  min-height: 100vh;
  background: #f3f4f6;
  padding-bottom: 160rpx;
}

/* 顶部分类 */
.board-bar {
  position: sticky;
  top: 0;
  z-index: 20;
  background: #fff;
  border-bottom: 1rpx solid #eef0f3;
}
.board-scroll { white-space: nowrap; }
.board-list { display: inline-flex; gap: 14rpx; padding: 20rpx 24rpx; }
.board-chip {
  padding: 12rpx 30rpx;
  background: #f2f3f5;
  border-radius: 40rpx;
  font-size: 26rpx;
  color: #6b7078;
  font-weight: 500;
}
.board-chip.active {
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  color: #fff;
  font-weight: 700;
}

/* 发帖触发条 */
.post-trigger {
  display: flex;
  align-items: center;
  gap: 16rpx;
  margin: 22rpx 24rpx 6rpx;
  background: #fff;
  border-radius: 20rpx;
  padding: 18rpx 22rpx;
  box-shadow: 0 6rpx 20rpx rgba(20, 21, 26, 0.05);
}
.pt-avatar {
  width: 56rpx;
  height: 56rpx;
  border-radius: 50%;
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  color: #fff;
  font-size: 26rpx;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.pt-input {
  flex: 1;
  color: #b4b8c0;
  font-size: 27rpx;
}
.pt-btn {
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  color: #fff;
  font-size: 24rpx;
  font-weight: 700;
  padding: 12rpx 28rpx;
  border-radius: 30rpx;
}

/* 帖子流 */
.feed { padding: 16rpx 24rpx; }
.card {
  background: #fff;
  border-radius: 24rpx;
  padding: 24rpx;
  margin-bottom: 20rpx;
  box-shadow: 0 6rpx 24rpx rgba(20, 21, 26, 0.05);
}
.card-head { display: flex; align-items: center; }
.c-avatar {
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: #fff;
  font-size: 28rpx;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.c-user { flex: 1; margin-left: 16rpx; min-width: 0; display: flex; flex-direction: column; }
.c-name { font-size: 28rpx; font-weight: 700; color: #14151a; }
.c-time { font-size: 22rpx; color: #9aa0a6; margin-top: 4rpx; }
.c-group {
  font-size: 22rpx;
  color: #ff6f43;
  background: #fff3ec;
  padding: 6rpx 16rpx;
  border-radius: 20rpx;
  flex-shrink: 0;
}
.c-title {
  font-size: 30rpx;
  font-weight: 700;
  color: #14151a;
  margin: 16rpx 0 6rpx;
  line-height: 1.4;
}
.c-content {
  font-size: 28rpx;
  color: #3a3f4a;
  line-height: 1.7;
  word-break: break-word;
}
.c-content ::v-deep img { max-width: 100%; border-radius: 12rpx; margin: 8rpx 0; }

.c-imgs {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10rpx;
  margin: 16rpx 0;
}
.c-imgs-single { grid-template-columns: minmax(0, 360rpx); }
.c-img {
  width: 100%;
  height: 200rpx;
  border-radius: 14rpx;
  background: #f2f3f5;
}
.c-imgs-single .c-img { height: 260rpx; }

.c-goods { margin: 14rpx 0; }
.goods-card {
  display: flex;
  gap: 16rpx;
  padding: 16rpx;
  border: 1rpx solid #eef0f3;
  border-radius: 16rpx;
  background: #fafbfc;
}
.goods-pic {
  width: 120rpx;
  height: 120rpx;
  border-radius: 12rpx;
  background: #f2f3f5;
  flex-shrink: 0;
}
.goods-info { flex: 1; min-width: 0; }
.goods-title {
  font-size: 25rpx;
  color: #333;
  line-height: 1.4;
  max-height: 70rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.goods-price { display: flex; align-items: baseline; gap: 10rpx; flex-wrap: wrap; margin-top: 10rpx; }
.goods-platform {
  font-size: 20rpx;
  color: #1890ff;
  background: #e6f7ff;
  padding: 2rpx 10rpx;
  border-radius: 6rpx;
}
.goods-act { color: #ff4d63; font-size: 28rpx; font-weight: 700; }
.goods-ori { color: #b4b8c0; font-size: 22rpx; text-decoration: line-through; }
.goods-coupon { color: #ff4d63; font-size: 20rpx; background: #ffecec; padding: 2rpx 8rpx; border-radius: 6rpx; }

.c-foot {
  display: flex;
  gap: 40rpx;
  margin-top: 18rpx;
  padding-top: 16rpx;
  border-top: 1rpx solid #f5f6f8;
}
.c-act { display: flex; align-items: center; gap: 8rpx; color: #9aa0a6; font-size: 26rpx; }
.c-act .act-liked { color: #ff4d63; }

.empty { text-align: center; padding: 120rpx 0; }
.empty-icon { font-size: 80rpx; display: block; opacity: 0.5; }
.empty-text { display: block; font-size: 26rpx; color: #9aa0a6; margin-top: 20rpx; }
.tip, .feed-end { text-align: center; color: #c4c8d0; font-size: 24rpx; padding: 30rpx 0; }

/* 悬浮发帖 */
.fab {
  position: fixed;
  right: 32rpx;
  bottom: 140rpx;
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
.fab-icon { font-size: 48rpx; }
</style>
