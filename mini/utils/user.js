/**
 * 用户登录 / 注册 / 鉴权（与 ZhiCms 后端共用 yun_user 用户体系）
 *
 * 后端实现：app/api/controller/UserController.php
 *   - 登录：POST {base}/index.php?r=api/user/login       参数 mobile、password
 *   - 注册：POST {base}/index.php?r=api/user/register    参数 mobile、username、password
 *   - 资料：GET  {base}/index.php?r=api/user/info         Header: Authorization: Bearer <token>
 *
 * 注意：后端 raw() 只读取 $_GET + $_POST（不解析 JSON 请求体），
 *       因此 POST 必须带 Content-Type: application/x-www-form-urlencoded，
 *       否则 mobile/password 读不到，会返回「请输入手机号和密码」。
 */

import { getBackendBase } from './appConfig.js';
import { BACKEND_BASE } from '../config.js';

const TOKEN_KEY = 'zhicms_user_token';
const USER_KEY = 'zhicms_user_info';

/** 取后端根地址：优先运行时配置（App.vue 拉取的 /api/config），兜底静态配置 */
function getBase() {
  const base = (getBackendBase() || BACKEND_BASE || '').replace(/\/+$/, '');
  return base;
}

/** 统一 POST 表单请求（form-urlencoded） */
function postForm(r, data) {
  return new Promise((resolve, reject) => {
    const base = getBase();
    if (!base) {
      reject(new Error('后端地址未配置'));
      return;
    }
    uni.request({
      url: base + '/index.php?r=' + r,
      method: 'POST',
      header: { 'Content-Type': 'application/x-www-form-urlencoded' },
      data: data,
      success: (res) => {
        const d = res.data || {};
        if (d.code === 1) {
          resolve(d);
        } else {
          reject(new Error(d.message || '操作失败'));
        }
      },
      fail: () => reject(new Error('网络请求失败，请稍后重试')),
    });
  });
}

/** 统一 GET 请求 */
function getJson(r, header) {
  return new Promise((resolve, reject) => {
    const base = getBase();
    if (!base) {
      reject(new Error('后端地址未配置'));
      return;
    }
    uni.request({
      url: base + '/index.php?r=' + r,
      method: 'GET',
      header: header || {},
      success: (res) => {
        const d = res.data || {};
        if (d.code === 1) {
          resolve(d);
        } else {
          reject(new Error(d.message || '操作失败'));
        }
      },
      fail: () => reject(new Error('网络请求失败，请稍后重试')),
    });
  });
}

/** 是否已登录（本地存在 token 即认为已登录，过期由 fetchUserInfo 兜底清理） */
export function isLogin() {
  return !!uni.getStorageSync(TOKEN_KEY);
}

/** 读取本地缓存的用户信息 */
export function getUser() {
  return uni.getStorageSync(USER_KEY) || null;
}

/** 登录：mobile + password，成功后缓存 token 与用户信息 */
export function login(mobile, password) {
  return postForm('api/user/login', { mobile: mobile, password: password }).then((d) => {
    if (d.token) uni.setStorageSync(TOKEN_KEY, d.token);
    const user = d.user || {};
    uni.setStorageSync(USER_KEY, user);
    return user;
  });
}

/** 注册并登录：mobile + username + password */
export function register(mobile, username, password) {
  return postForm('api/user/register', {
    mobile: mobile,
    username: username,
    password: password,
  }).then((d) => {
    if (d.token) uni.setStorageSync(TOKEN_KEY, d.token);
    const user = d.user || {};
    uni.setStorageSync(USER_KEY, user);
    return user;
  });
}

/** 用 token 拉取最新用户信息；token 失效则清理本地登录态 */
export function fetchUserInfo() {
  const token = uni.getStorageSync(TOKEN_KEY);
  if (!token) return Promise.resolve(null);
  return getJson('api/user/info', { Authorization: 'Bearer ' + token })
    .then((d) => {
      const user = d.user || {};
      uni.setStorageSync(USER_KEY, user);
      return user;
    })
    .catch((err) => {
      // 鉴权失败（token 过期/未登录，错误消息含「登录」）才清理本地登录态；
      // 纯网络错误不应误清登录态
      if (String(err.message || '').indexOf('登录') !== -1) {
        uni.removeStorageSync(TOKEN_KEY);
        uni.removeStorageSync(USER_KEY);
      }
      return null;
    });
}

/** 退出登录 */
export function logout() {
  uni.removeStorageSync(TOKEN_KEY);
  uni.removeStorageSync(USER_KEY);
}

/** 获取当前 token（供其它接口携带） */
export function getToken() {
  return uni.getStorageSync(TOKEN_KEY) || '';
}
