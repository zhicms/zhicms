<template>
  <view class="post-page">
    <view class="form-card">
      <view class="row">
        <text class="label">选择小组</text>
        <picker class="picker" :range="groupNames" @change="onGroupChange">
          <view class="picker-text">{{ selectedGroup ? selectedGroup.groupname : '请选择小组 *' }}</view>
        </picker>
      </view>

      <view v-if="!isLogin" class="row">
        <text class="label">昵称</text>
        <input class="input" v-model="nickname" placeholder="必填（未登录时）" placeholder-class="ph" maxlength="20" />
      </view>

      <view class="row col">
        <text class="label">内容（{{ maxChars - contentLen }}/{{ maxChars }}）</text>
        <textarea
          class="textarea"
          v-model="content"
          :maxlength="maxChars"
          placeholder="说点什么吧，分享好物或经验…"
          placeholder-class="ph"
        />
      </view>

      <view class="row col">
        <text class="label">图片（{{ images.length }}/{{ maxImages }}）</text>
        <view class="imgs">
          <view v-for="(img, idx) in images" :key="idx" class="img-item">
            <image class="img" :src="img" mode="aspectFill" />
            <text class="img-del" @tap="removeImage(idx)">×</text>
          </view>
          <view v-if="images.length < maxImages" class="img-add" @tap="chooseImage">
            <text class="img-add-icon">＋</text>
          </view>
        </view>
      </view>

      <view class="row col">
        <text class="label">商品链接（{{ goods.length }}/{{ maxLinks }}）</text>
        <view class="link-bar">
          <input class="link-input" v-model="linkUrl" placeholder="粘贴淘宝/京东/拼多多/唯品会链接" placeholder-class="ph" />
          <view class="link-parse" @tap="parseLink">解析</view>
        </view>
        <view v-if="goods.length" class="goods-list">
          <view v-for="(g, gi) in goods" :key="gi" class="goods-card">
            <image v-if="g.pic" class="goods-pic" :src="g.pic" mode="aspectFill" />
            <view class="goods-info">
              <text class="goods-title">{{ g.title }}</text>
              <view class="goods-price">
                <text class="goods-platform">{{ g.platformName || g.platform }}</text>
                <text v-if="g.actPrice" class="goods-act">¥{{ g.actPrice }}</text>
                <text v-if="g.coupon" class="goods-coupon">券¥{{ g.coupon }}</text>
              </view>
            </view>
            <text class="goods-del" @tap="removeGoods(gi)">×</text>
          </view>
        </view>
      </view>
    </view>

    <view class="submit-bar">
      <button class="submit-btn" :loading="submitting" @tap="submit">立即发布</button>
    </view>
  </view>
</template>

<script>
import { getList, createPost, uploadImage as apiUpload, parseLink as apiParse } from '../../utils/forum.js';
import { isLogin, getUser } from '../../utils/user.js';

export default {
  data() {
    return {
      groups: [],
      groupNames: [],
      groupIndex: -1,
      selectedGroup: null,
      content: '',
      maxChars: 300,
      maxImages: 6,
      maxLinks: 3,
      images: [],
      goods: [],
      linkUrl: '',
      nickname: '',
      isLogin: false,
      submitting: false,
    };
  },

  computed: {
    contentLen() {
      return Array.from(this.content).length;
    },
  },

  onLoad() {
    this.isLogin = isLogin();
    const u = getUser();
    if (u && u.username) this.nickname = u.username;
    this.loadGroups();
  },

  methods: {
    loadGroups() {
      getList({ bid: 0, page: 1 })
        .then((d) => {
          this.groups = d.groups || [];
          this.groupNames = this.groups.map((g) => g.groupname);
          this.maxChars = d.max_chars || 300;
          this.maxImages = d.max_images || 6;
          this.maxLinks = d.max_links || 3;
        })
        .catch(() => {
          this.groups = [];
          this.groupNames = [];
        });
    },

    onGroupChange(e) {
      this.groupIndex = parseInt(e.detail.value, 10);
      this.selectedGroup = this.groups[this.groupIndex];
    },

    chooseImage() {
      const remain = this.maxImages - this.images.length;
      if (remain <= 0) {
        uni.showToast({ title: '最多上传 ' + this.maxImages + ' 张', icon: 'none' });
        return;
      }
      uni.chooseImage({
        count: remain,
        success: (res) => {
          res.tempFilePaths.forEach((p) => {
            apiUpload(p)
              .then((url) => {
                this.images.push(url);
              })
              .catch((err) => {
                uni.showToast({ title: err.message || '上传失败', icon: 'none' });
              });
          });
        },
      });
    },

    removeImage(idx) {
      this.images.splice(idx, 1);
    },

    parseLink() {
      const url = (this.linkUrl || '').trim();
      if (!url) {
        uni.showToast({ title: '请输入链接', icon: 'none' });
        return;
      }
      if (this.goods.length >= this.maxLinks) {
        uni.showToast({ title: '最多添加 ' + this.maxLinks + ' 个', icon: 'none' });
        return;
      }
      uni.showLoading({ title: '解析中' });
      apiParse(url)
        .then((d) => {
          uni.hideLoading();
          this.goods.push(d.card);
          this.linkUrl = '';
          uni.showToast({ title: '已添加商品', icon: 'success' });
        })
        .catch((err) => {
          uni.hideLoading();
          uni.showToast({ title: err.message || '解析失败', icon: 'none' });
        });
    },

    removeGoods(idx) {
      this.goods.splice(idx, 1);
    },

    submit() {
      if (this.submitting) return;
      const body = this.content.trim();
      if (!body) {
        uni.showToast({ title: '请填写内容', icon: 'none' });
        return;
      }
      if (!this.selectedGroup) {
        uni.showToast({ title: '请选择小组', icon: 'none' });
        return;
      }
      if (!this.isLogin && !this.nickname.trim()) {
        uni.showToast({ title: '请填写昵称', icon: 'none' });
        return;
      }

      const data = {
        gid: this.selectedGroup.id,
        content: body,
      };
      if (this.images.length) data.images = JSON.stringify(this.images);
      if (this.goods.length) data.goods_data = JSON.stringify(this.goods);
      if (!this.isLogin) data.poster = this.nickname.trim();

      this.submitting = true;
      createPost(data)
        .then(() => {
          uni.showToast({ title: '发布成功', icon: 'success' });
          setTimeout(() => {
            uni.switchTab({ url: '/pages/community/community' });
          }, 800);
        })
        .catch((err) => {
          uni.showToast({ title: err.message || '发布失败', icon: 'none' });
        })
        .finally(() => {
          this.submitting = false;
        });
    },
  },
};
</script>

<style scoped>
.post-page { min-height: 100vh; background: #f3f4f6; padding-bottom: 200rpx; }
.form-card {
  background: #fff;
  margin: 24rpx;
  border-radius: 24rpx;
  padding: 10rpx 28rpx 30rpx;
  box-shadow: 0 6rpx 24rpx rgba(20, 21, 26, 0.05);
}
.row {
  display: flex;
  align-items: center;
  padding: 24rpx 0;
  border-bottom: 1rpx solid #f2f3f5;
}
.row.col { flex-direction: column; align-items: stretch; }
.label {
  font-size: 27rpx;
  font-weight: 600;
  color: #4a5063;
  margin-bottom: 4rpx;
}
.row:not(.col) .label { width: 150rpx; flex-shrink: 0; margin-bottom: 0; }
.picker { flex: 1; }
.picker-text {
  font-size: 28rpx;
  color: #14151a;
  background: #f4f5f7;
  border-radius: 14rpx;
  padding: 18rpx 24rpx;
}
.input {
  flex: 1;
  background: #f4f5f7;
  border-radius: 14rpx;
  padding: 18rpx 24rpx;
  font-size: 28rpx;
}
.textarea {
  width: 100%;
  min-height: 200rpx;
  background: #f4f5f7;
  border-radius: 14rpx;
  padding: 20rpx;
  font-size: 28rpx;
  line-height: 1.6;
  box-sizing: border-box;
}
.ph { color: #b4b8c0; }

.imgs { display: flex; flex-wrap: wrap; gap: 16rpx; margin-top: 8rpx; }
.img-item, .img-add {
  width: 150rpx;
  height: 150rpx;
  border-radius: 14rpx;
  overflow: hidden;
  position: relative;
  background: #f2f3f5;
}
.img { width: 100%; height: 100%; }
.img-del {
  position: absolute;
  top: 4rpx;
  right: 4rpx;
  width: 36rpx;
  height: 36rpx;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 28rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}
.img-add {
  border: 2rpx dashed #c4c8d0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.img-add-icon { font-size: 56rpx; color: #b4b8c0; }

.link-bar { display: flex; gap: 14rpx; margin-top: 8rpx; }
.link-input {
  flex: 1;
  background: #f4f5f7;
  border-radius: 14rpx;
  padding: 18rpx 24rpx;
  font-size: 26rpx;
}
.link-parse {
  flex-shrink: 0;
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  color: #fff;
  font-size: 26rpx;
  font-weight: 700;
  padding: 0 30rpx;
  border-radius: 14rpx;
  display: flex;
  align-items: center;
}
.goods-list { margin-top: 16rpx; display: flex; flex-direction: column; gap: 12rpx; }
.goods-card {
  display: flex;
  gap: 14rpx;
  padding: 14rpx;
  border: 1rpx solid #eef0f3;
  border-radius: 14rpx;
  background: #fafbfc;
  position: relative;
}
.goods-pic { width: 96rpx; height: 96rpx; border-radius: 10rpx; flex-shrink: 0; background: #f2f3f5; }
.goods-info { flex: 1; min-width: 0; }
.goods-title { font-size: 25rpx; color: #333; line-height: 1.4; max-height: 70rpx; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.goods-price { display: flex; align-items: baseline; gap: 10rpx; flex-wrap: wrap; margin-top: 8rpx; }
.goods-platform { font-size: 20rpx; color: #1890ff; background: #e6f7ff; padding: 2rpx 10rpx; border-radius: 6rpx; }
.goods-act { color: #ff4d63; font-size: 26rpx; font-weight: 700; }
.goods-coupon { color: #ff4d63; font-size: 20rpx; background: #ffecec; padding: 2rpx 8rpx; border-radius: 6rpx; }
.goods-del {
  position: absolute;
  top: 6rpx;
  right: 6rpx;
  width: 36rpx;
  height: 36rpx;
  border-radius: 50%;
  background: rgba(245, 34, 45, 0.9);
  color: #fff;
  font-size: 28rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.submit-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 60;
  padding: 16rpx 24rpx;
  padding-bottom: calc(16rpx + env(safe-area-inset-bottom));
  background: #fff;
  box-shadow: 0 -6rpx 20rpx rgba(20, 21, 26, 0.06);
}
.submit-btn {
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  color: #fff;
  font-size: 30rpx;
  font-weight: 700;
  border-radius: 44rpx;
  box-shadow: 0 12rpx 26rpx rgba(255, 77, 99, 0.34);
}
.submit-btn::after { border: none; }
</style>
