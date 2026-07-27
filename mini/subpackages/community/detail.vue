<template>
  <view class="detail">
    <view v-if="forum" class="post">
      <view class="post-head">
        <text class="p-avatar">{{ forum.initial }}</text>
        <view class="p-user">
          <text class="p-name">{{ forum.poster }}</text>
          <text class="p-meta">
            {{ forum.date }}
            <text v-if="forum.group" class="p-group"> · {{ forum.group.groupname }}</text>
            <text v-if="forum.board" class="p-board"> · {{ forum.board.name }}</text>
          </text>
        </view>
      </view>

      <view class="p-title" v-if="forum.title">{{ forum.title }}</view>
      <rich-text class="p-content" :nodes="forum.content"></rich-text>

      <view v-if="forum.images && forum.images.length" class="p-imgs" :class="forum.images.length === 1 ? 'p-imgs-single' : ''">
        <image
          v-for="(img, idx) in forum.images"
          :key="idx"
          class="p-img"
          :src="img"
          mode="aspectFill"
          @tap="previewImage(forum.images, idx)"
        />
      </view>

      <view v-if="forum.goods && forum.goods.length" class="p-goods">
        <view v-for="(g, gi) in forum.goods" :key="gi" class="goods-card" @tap="openGoods(g)">
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

      <view class="p-foot">
        <view class="p-act" :class="{ liked: forum.liked }" @tap="onLike">
          <text>{{ forum.liked ? '♥' : '♡' }}</text>
          <text>{{ forum.like }} 赞</text>
        </view>
        <view class="p-act" @tap="focusReply">
          <text>💬</text><text>{{ replyCount }} 评论</text>
        </view>
        <view class="p-act"><text>👁</text><text>{{ forum.view }}</text></view>
      </view>
    </view>

    <!-- 评论区 -->
    <view class="comments">
      <view class="comments-title">评论 {{ replyCount }}</view>
      <view v-if="repliesFlat.length === 0" class="c-empty">暂无评论，来抢沙发</view>
      <view
        v-for="(r, i) in repliesFlat"
        :key="r.id"
        class="comment"
        :style="{ marginLeft: Math.min(r.depth, 3) * 28 + 'rpx' }"
      >
        <text class="cm-avatar">{{ r.initial }}</text>
        <view class="cm-body">
          <view class="cm-head">
            <text class="cm-name">{{ r.poster }}</text>
            <text class="cm-time">{{ r.date }}</text>
          </view>
          <text class="cm-text">{{ r.content }}</text>
          <view class="cm-reply" @tap="focusReply(r)">回复</view>
        </view>
      </view>
    </view>

    <view class="safe-bottom"></view>

    <!-- 底部回复输入条 -->
    <view class="reply-bar">
      <template v-if="!isLogin">
        <input class="rb-name" v-model="nickname" placeholder="昵称" placeholder-class="ph" maxlength="20" />
      </template>
      <input
        class="rb-input"
        v-model="replyText"
        :placeholder="replyTo ? '回复 @' + replyTo.poster : '说点什么…'"
        placeholder-class="ph"
        confirm-type="send"
        @confirm="sendReply"
      />
      <view class="rb-send" @tap="sendReply">发送</view>
    </view>
  </view>
</template>

<script>
import { getDetail, createReply, likePost, getVisitor } from '../../utils/forum.js';
import { isLogin, getUser } from '../../utils/user.js';

function flatten(list, depth, out) {
  list.forEach((node) => {
    out.push(Object.assign({}, node, { depth }));
    if (node.children && node.children.length) {
      flatten(node.children, depth + 1, out);
    }
  });
}

export default {
  data() {
    return {
      forumId: 0,
      forum: null,
      repliesFlat: [],
      replyCount: 0,
      replyText: '',
      replyTo: null,
      nickname: '',
      isLogin: false,
    };
  },

  onLoad(opts) {
    this.forumId = parseInt(opts.id, 10) || 0;
    this.isLogin = isLogin();
    const u = getUser();
    if (u && u.username) this.nickname = u.username;
    this.load();
  },

  methods: {
    load() {
      getDetail(this.forumId)
        .then((d) => {
          this.forum = d.forum;
          this.forum.liked = d.has_liked;
          const out = [];
          flatten(d.replies || [], 0, out);
          this.repliesFlat = out;
          this.replyCount = d.reply_count || 0;
        })
        .catch((err) => {
          uni.showToast({ title: err.message || '加载失败', icon: 'none' });
        });
    },

    previewImage(images, idx) {
      uni.previewImage({ current: images[idx], urls: images });
    },

    openGoods(g) {
      uni.showToast({ title: (g.platformName || g.platform) + '：' + (g.title || ''), icon: 'none' });
    },

    onLike() {
      if (this.forum.liked) {
        uni.showToast({ title: '已点过赞', icon: 'none' });
        return;
      }
      likePost(this.forumId, getVisitor())
        .then((d) => {
          this.forum.like = d.count;
          this.forum.liked = true;
        })
        .catch((err) => {
          uni.showToast({ title: err.message || '操作失败', icon: 'none' });
        });
    },

    focusReply(reply) {
      this.replyTo = reply || null;
      // 滚动到底部输入条并获得焦点（uni 不支持直接 focus，仅做提示）
      uni.pageScrollTo({ scrollTop: 99999, duration: 200 });
    },

    sendReply() {
      const body = (this.replyText || '').trim();
      if (!body) {
        uni.showToast({ title: '请输入回复内容', icon: 'none' });
        return;
      }
      if (!this.isLogin && !this.nickname.trim()) {
        uni.showToast({ title: '请填写昵称', icon: 'none' });
        return;
      }
      const data = {
        id: this.forumId,
        pid: this.replyTo ? this.replyTo.id : 0,
        mybody: body,
      };
      if (!this.isLogin) data.poster = this.nickname.trim();
      uni.showLoading({ title: '发送中' });
      createReply(data)
        .then(() => {
          this.replyText = '';
          this.replyTo = null;
          uni.hideLoading();
          this.load();
          uni.showToast({ title: '回复成功', icon: 'success' });
        })
        .catch((err) => {
          uni.hideLoading();
          uni.showToast({ title: err.message || '发送失败', icon: 'none' });
        });
    },
  },
};
</script>

<style scoped>
.detail { min-height: 100vh; background: #f3f4f6; padding-bottom: 200rpx; }

.post { background: #fff; padding: 28rpx; }
.post-head { display: flex; align-items: center; }
.p-avatar {
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: #fff;
  font-size: 30rpx;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.p-user { flex: 1; margin-left: 16rpx; min-width: 0; display: flex; flex-direction: column; }
.p-name { font-size: 30rpx; font-weight: 700; color: #14151a; }
.p-meta { font-size: 22rpx; color: #9aa0a6; margin-top: 6rpx; }
.p-group { color: #ff6f43; }
.p-board { color: #6c63ff; }

.p-title { font-size: 34rpx; font-weight: 800; color: #14151a; margin: 20rpx 0 8rpx; line-height: 1.4; }
.p-content { font-size: 29rpx; color: #3a3f4a; line-height: 1.8; word-break: break-word; }
.p-content ::v-deep img { max-width: 100%; border-radius: 12rpx; margin: 8rpx 0; }

.p-imgs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10rpx; margin: 16rpx 0; }
.p-imgs-single { grid-template-columns: minmax(0, 380rpx); }
.p-img { width: 100%; height: 210rpx; border-radius: 14rpx; background: #f2f3f5; }

.p-goods { margin: 14rpx 0; }
.goods-card { display: flex; gap: 16rpx; padding: 16rpx; border: 1rpx solid #eef0f3; border-radius: 16rpx; background: #fafbfc; }
.goods-pic { width: 120rpx; height: 120rpx; border-radius: 12rpx; background: #f2f3f5; flex-shrink: 0; }
.goods-info { flex: 1; min-width: 0; }
.goods-title { font-size: 25rpx; color: #333; line-height: 1.4; max-height: 70rpx; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.goods-price { display: flex; align-items: baseline; gap: 10rpx; flex-wrap: wrap; margin-top: 10rpx; }
.goods-platform { font-size: 20rpx; color: #1890ff; background: #e6f7ff; padding: 2rpx 10rpx; border-radius: 6rpx; }
.goods-act { color: #ff4d63; font-size: 28rpx; font-weight: 700; }
.goods-ori { color: #b4b8c0; font-size: 22rpx; text-decoration: line-through; }
.goods-coupon { color: #ff4d63; font-size: 20rpx; background: #ffecec; padding: 2rpx 8rpx; border-radius: 6rpx; }

.p-foot {
  display: flex;
  gap: 30rpx;
  margin-top: 22rpx;
  padding-top: 18rpx;
  border-top: 1rpx solid #f0f1f4;
}
.p-act { display: flex; align-items: center; gap: 8rpx; color: #6b7078; font-size: 26rpx; }
.p-act.liked { color: #ff4d63; }

.comments { background: #fff; margin-top: 18rpx; padding: 24rpx 28rpx 40rpx; }
.comments-title { font-size: 30rpx; font-weight: 800; color: #14151a; margin-bottom: 16rpx; }
.c-empty { text-align: center; color: #b4b8c0; font-size: 26rpx; padding: 50rpx 0; }
.comment { display: flex; gap: 14rpx; padding: 18rpx 0; border-bottom: 1rpx solid #f5f6f8; }
.cm-avatar {
  width: 56rpx;
  height: 56rpx;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: #fff;
  font-size: 24rpx;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.cm-body { flex: 1; min-width: 0; }
.cm-head { display: flex; align-items: center; gap: 14rpx; }
.cm-name { font-size: 26rpx; font-weight: 600; color: #2e3650; }
.cm-time { font-size: 22rpx; color: #b4b8c0; }
.cm-text { display: block; font-size: 27rpx; color: #3a3f4a; line-height: 1.6; margin-top: 6rpx; word-break: break-word; }
.cm-reply { display: inline-block; font-size: 23rpx; color: #9aa0a6; margin-top: 10rpx; }

.safe-bottom { height: env(safe-area-inset-bottom); }

.reply-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  gap: 14rpx;
  padding: 16rpx 24rpx;
  padding-bottom: calc(16rpx + env(safe-area-inset-bottom));
  background: #fff;
  box-shadow: 0 -6rpx 20rpx rgba(20, 21, 26, 0.06);
}
.rb-name {
  width: 140rpx;
  flex-shrink: 0;
  background: #f2f3f5;
  border-radius: 30rpx;
  padding: 18rpx 24rpx;
  font-size: 26rpx;
}
.rb-input {
  flex: 1;
  background: #f2f3f5;
  border-radius: 30rpx;
  padding: 18rpx 28rpx;
  font-size: 27rpx;
}
.ph { color: #b4b8c0; }
.rb-send {
  flex-shrink: 0;
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  color: #fff;
  font-size: 26rpx;
  font-weight: 700;
  padding: 18rpx 34rpx;
  border-radius: 30rpx;
}
</style>
