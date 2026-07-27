/**
 * 全局剪贴板识别（小程序端）
 *
 * 监听系统剪贴板变化：当用户从别处复制了淘宝/京东/拼多多/唯品会的
 * 淘口令或商品链接时，自动识别并请求后端转链，随后在底部弹出提示条，
 * 引导用户「点击复制」本站生成的淘口令/推广链接。
 *
 * 关键防护（避免循环）：
 *   - lastWritten：最近一次「我们写入剪贴板」的内容。当用户点了复制、剪贴板变成
 *     本站口令时，再次触发的剪贴板变化会被直接跳过，不会二次识别转链。
 *   - processed：已识别过的「原始外部口令」。识别成功后即记入，原口令被注销（清空），
 *     后续即便剪贴板还是它也不会重复弹窗。
 */

import { getBackendBase } from './appConfig.js';

let started = false;
let lastWritten = '';           // 最近一次我们写入剪贴板的内容（防循环）
const processed = new Set();    // 已识别过的原始内容（注销：不再二次识别）

const LABELS = { taobao: '淘宝', jd: '京东', pdd: '拼多多', vip: '唯品会' };

/** 识别电商平台：淘宝口令 / 京东 / 拼多多 / 唯品会 链接或口令 */
export function detectPlatform(text) {
  if (/[¥￥€¢].+[¥￥€¢]/.test(text)
    || /taobao\.com|tb\.cn|淘宝/.test(text)) {
    return 'taobao';
  }
  if (/jd\.com|jingdong\.com|京东/.test(text)) return 'jd';
  if (/yangkeduo\.com|pinduoduo\.com|拼多多/.test(text)) return 'pdd';
  if (/vip\.com|vipshop\.com|唯品会/.test(text)) return 'vip';
  return '';
}

function handleClipboard(text) {
  const content = (text || '').trim();
  if (!content) return;                       // 空剪贴板：忽略
  if (content === lastWritten) return;        // 我们自己写入的本站口令：跳过（防循环）
  if (processed.has(content)) return;         // 已识别过（已注销）：不二次识别

  const platform = detectPlatform(content);
  if (!platform) return;                        // 非电商口令/链接：忽略

  convertAndNotify(platform, content);
}

function convertAndNotify(platform, content) {
  const base = (getBackendBase() || '').replace(/\/+$/, '');
  if (!base) return;

  uni.request({
    url: base + '/index.php?r=api/goods/convert',
    method: 'POST',
    header: { 'Content-Type': 'application/x-www-form-urlencoded' },
    data: { content: content, platform: platform },
    success: (res) => {
      const d = (res && res.data) || {};
      if (d.code !== 1) return;

      const converted = d.converted || d.tpwd || d.shortUrl || content;
      const label = d.label || LABELS[platform] || platform;

      // 注销原口令：清空剪贴板，避免外部电商口令残留被反复识别
      lastWritten = converted;
      processed.add(content);
      uni.setClipboardData({ data: '' });

      // 通知各页面底部提示条
      uni.$emit('clipboard:recognized', {
        platform,
        label,
        converted,
        title: d.title || '',
      });
    },
    fail: () => {},
  });
}

/** 在 App.vue onLaunch 中调用一次，注册全局剪贴板监听 */
export function initClipboardWatch() {
  if (started) return;
  started = true;
  if (typeof uni.onClipboardDataChange !== 'function') return;

  uni.onClipboardDataChange((res) => {
    let text = (res && (res.data || res.value)) || '';
    if (!text) {
      // 个别平台回调不直接带内容，兜底读取一次
      uni.getClipboardData({
        success: (r) => handleClipboard(r.data || ''),
      });
      return;
    }
    handleClipboard(text);
  });
}

/** 用户点击提示条「复制」：写入本站口令（lastWritten 防护避免循环） */
export function copyConverted(converted) {
  if (!converted) return;
  lastWritten = converted;
  uni.setClipboardData({
    data: converted,
    success: () => uni.showToast({ title: '已复制专属口令', icon: 'none' }),
  });
}
