/**
 * 微社区接口封装（供小程序 / App 复用，后端 app/api/controller/ForumController）
 *
 * 注意：后端 ApiBaseController::raw() 只读 $_GET + $_POST，
 *       因此 POST 必须带 Content-Type: application/x-www-form-urlencoded。
 */
import { getBackendBase } from './appConfig.js';
import { BACKEND_BASE } from '../config.js';
import { getToken } from './user.js';

function getBase() {
  return (getBackendBase() || BACKEND_BASE || '').replace(/\/+$/, '');
}

function req(r, method, data, header) {
  return new Promise((resolve, reject) => {
    const base = getBase();
    if (!base) {
      reject(new Error('后端地址未配置'));
      return;
    }
    const h = Object.assign({}, header || {});
    if (method === 'POST' && !h['Content-Type']) {
      h['Content-Type'] = 'application/x-www-form-urlencoded';
    }
    uni.request({
      url: base + '/index.php?r=' + r,
      method,
      data,
      header: h,
      success: (res) => {
        const d = res.data || {};
        if (d.code === 1) {
          resolve(d.data !== undefined ? d.data : d);
        } else {
          reject(new Error(d.message || '操作失败'));
        }
      },
      fail: () => reject(new Error('网络请求失败，请稍后重试')),
    });
  });
}

function authHeader() {
  const h = {};
  const t = getToken();
  if (t) h.Authorization = 'Bearer ' + t;
  return h;
}

/** 社区帖子列表（bid 板块筛选） */
export function getList(params) {
  return req('api/forum/index', 'GET', params || {});
}

/** 帖子详情 + 嵌套回复 */
export function getDetail(id) {
  return req('api/forum/view', 'GET', { id });
}

/** 发帖：data 含 gid, content, images(JSON字符串), goods_data(JSON字符串), poster, mail */
export function createPost(data) {
  return req('api/forum/post', 'POST', data, authHeader());
}

/** 回复：data 含 id, pid, mybody, poster, mail */
export function createReply(data) {
  return req('api/forum/reply', 'POST', data, authHeader());
}

/** 点赞：未登录时携带 visitor（设备标识）用于去重 */
export function likePost(id, visitor) {
  const data = { id };
  const t = getToken();
  if (!t && visitor) data.visitor = visitor;
  return req('api/forum/like', 'POST', data, authHeader());
}

/** 商品链接解析 */
export function parseLink(url) {
  return req('api/forum/parse', 'POST', { url });
}

/** 图片上传（multipart），成功后返回相对 URL */
export function uploadImage(filePath) {
  return new Promise((resolve, reject) => {
    const base = getBase();
    if (!base) {
      reject(new Error('后端地址未配置'));
      return;
    }
    uni.uploadFile({
      url: base + '/index.php?r=api/forum/upload',
      filePath,
      name: 'file',
      success: (res) => {
        try {
          const d = JSON.parse(res.data);
          if (d.code === 1) resolve(d.data.url);
          else reject(new Error(d.message || '上传失败'));
        } catch (e) {
          reject(new Error('上传失败'));
        }
      },
      fail: () => reject(new Error('上传失败')),
    });
  });
}

/** 获取设备级访客标识（小程序无 Cookie，用于匿名点赞/发帖去重） */
export function getVisitor() {
  let v = uni.getStorageSync('zhicms_visitor');
  if (!v) {
    v = 'v_' + Date.now() + Math.random().toString(36).slice(2, 8);
    uni.setStorageSync('zhicms_visitor', v);
  }
  return v;
}
