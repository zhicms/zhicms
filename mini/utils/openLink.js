/**
 * 跨端打开商品 / 外部链接
 *
 * 淘系（淘宝/天猫）链接无法在微信、抖音等小程序中通过 web-view 打开
 * （非业务域名，且淘宝禁止被第三方内嵌），因此：
 *   - App：plus.runtime.openURL 直接唤起淘宝 App（支持淘系链接，体验最佳）
 *   - 微信 / 抖音 / 支付宝 / 百度等小程序 & H5：换取并复制「淘口令」，
 *     引导用户打开手机淘宝自动识别商品（无需配置任何业务域名白名单）
 */

import { getBackendBase } from './appConfig.js';

/**
 * 打开商品（推荐使用）：App 唤起淘宝，其余端复制淘口令
 * @param {Object} product 商品对象（需含 id / platform，可选 tpwd / itemLink）
 */
export function openProduct(product) {
  if (!product) {
    uni.showToast({ title: '商品信息缺失', icon: 'none' });
    return;
  }

  // #ifdef APP-PLUS
  // App 端可直接唤起淘宝 App（支持淘系链接）
  const link = product.itemLink || product.couponLink || product.shortUrl || product.detail_url || '';
  if (link && typeof plus !== 'undefined' && plus.runtime && plus.runtime.openURL) {
    plus.runtime.openURL(link);
    return;
  }
  // #endif

  // 小程序 / H5：优先使用已带的淘口令，否则向后端换取
  if (product.tpwd) {
    copyTpwd(product.tpwd);
    return;
  }
  fetchTpwd(product);
}

/**
 * 获取当前端操作系统：ios / android / other
 * uni.getSystemInfoSync().platform 在较新版本已废弃，优先用 getDeviceInfo().osName
 */
function getOSType() {
  try {
    const info = (typeof uni.getDeviceInfo === 'function')
      ? uni.getDeviceInfo()
      : uni.getSystemInfoSync();
    const os = String(info.osName || info.platform || '').toLowerCase();
    if (os.indexOf('ios') !== -1) return 'ios';
    if (os.indexOf('android') !== -1) return 'android';
  } catch (e) {}
  return 'other';
}

/**
 * 向配套后端换取商品淘口令并复制
 * 安卓使用 tpwd（标准淘口令），苹果使用 longTpwd（长口令，iOS 识别更稳定）
 * @param {Object} product
 */
function fetchTpwd(product) {
  const base = (getBackendBase() || '').replace(/\/+$/, '');
  // 优先使用大淘客商品ID（goodsId），与电脑版转链用的 id 保持一致
  const id = product.goodsId || product.id || '';
  if (!base || !id) {
    // 无法换取淘口令时，退回复制原始推广链接
    const url = product.itemLink || product.couponLink || product.detail_url || '';
    if (url) {
      copyUrl(url);
    } else {
      uni.showToast({ title: '暂无可用链接', icon: 'none' });
    }
    return;
  }

  uni.showLoading({ title: '生成淘口令…', mask: true });
  uni.request({
    url: base + '/index.php',
    method: 'GET',
    data: {
      r: 'api/goods/tpwd',
      id: id,
      goodsSign: product.goodsSign || '',
      platform: product.platform || 'dtk',
      itemLink: product.itemLink || '',
      couponLink: product.couponLink || '',
    },
    success: (res) => {
      const d = (res && res.data) || {};
      // 安卓用 tpwd，苹果用 longTpwd；任一侧缺失时回退到另一侧
      const isIOS = getOSType() === 'ios';
      const tpwdVal = isIOS
        ? (d.longTpwd || d.tpwd)
        : (d.tpwd || d.longTpwd);

      if (d.code === 1 && tpwdVal) {
        copyTpwd(tpwdVal);
      } else if (d.code === 1 && d.url) {
        // 转链成功但无淘口令时（如非淘系），复制可直接打开的淘客链接
        copyUrl(d.url);
      } else {
        uni.showToast({ title: d.message || '淘口令生成失败', icon: 'none' });
      }
    },
    fail: () => uni.showToast({ title: '淘口令生成失败，请重试', icon: 'none' }),
    complete: () => uni.hideLoading(),
  });
}

/**
 * 复制淘口令并弹窗引导
 * @param {string} tpwd
 */
export function copyTpwd(tpwd) {
  if (!tpwd) {
    uni.showToast({ title: '暂无淘口令', icon: 'none' });
    return;
  }
  uni.setClipboardData({
    data: tpwd,
    success: () => {
      uni.showModal({
        title: '淘口令已复制',
        content: '已复制：' + tpwd + '\n\n打开手机淘宝 App 即可自动识别该商品',
        showCancel: false,
        confirmText: '知道了',
      });
    },
    fail: () => uni.showToast({ title: '复制失败，请重试', icon: 'none' }),
  });
}

/**
 * 复制可直接打开的商品链接并引导（转链失败时的兜底）
 * @param {string} url
 */
export function copyUrl(url) {
  if (!url) {
    uni.showToast({ title: '暂无可用链接', icon: 'none' });
    return;
  }
  uni.setClipboardData({
    data: url,
    success: () => {
      uni.showModal({
        title: '链接已复制',
        content: '已复制商品链接：\n' + url + '\n\n打开手机淘宝 App 即可查看商品',
        showCancel: false,
        confirmText: '知道了',
      });
    },
    fail: () => uni.showToast({ title: '复制失败，请重试', icon: 'none' }),
  });
}

/**
 * 打开普通（非淘系）链接：如主站自有页面
 * - App：plus.runtime.openURL
 * - H5：window.open
 * - 小程序：内置 web-view（域名需在后台白名单）
 * - 兜底：复制链接
 * @param {string} url
 */
export function openLink(url) {
  if (!url) {
    uni.showToast({ title: '暂无可用链接', icon: 'none' });
    return;
  }

  // #ifdef APP-PLUS
  if (typeof plus !== 'undefined' && plus.runtime && plus.runtime.openURL) {
    plus.runtime.openURL(url);
    return;
  }
  // #endif

  // #ifdef H5
  if (typeof window !== 'undefined' && window.open) {
    window.open(url, '_blank');
    return;
  }
  // #endif

  // #ifdef MP-WEIXIN || MP-TOUTIAO
  uni.navigateTo({
    url: '/subpackages/tool/webview?src=' + encodeURIComponent(url),
  });
  // #endif

  // #ifndef APP-PLUS || H5 || MP-WEIXIN || MP-TOUTIAO
  uni.setClipboardData({
    data: url,
    success: () => uni.showToast({ title: '链接已复制', icon: 'none' }),
    fail: () => uni.showToast({ title: '无法打开链接', icon: 'none' }),
  });
  // #endif
}
