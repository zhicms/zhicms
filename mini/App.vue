<script>
import { initAiService } from './utils/aiService.js';
import { setAppConfig } from './utils/appConfig.js';
import { fetchUserInfo } from './utils/user.js';
import { initClipboardWatch } from './utils/clipboard.js';
import { BACKEND_BASE } from './config.js';

export default {
  globalData: {
    backendBase: '',
    ai: {},
    site: {},
    forumOn: true,
  },

  onLaunch() {
    this.loadConfig();
    this.bootstrapUser();
    // 全局监听系统剪贴板：识别外部电商口令/链接并转链提示
    initClipboardWatch();
  },

  methods: {
    // 若本地已有登录 Token，则静默刷新最新用户资料（与网站共用 yun_user）
    bootstrapUser() {
      fetchUserInfo().catch(() => {});
    },

    // 启动即从配套后端拉取站点 / AI / 主题配置，全局初始化 AI 服务
    loadConfig() {
      const base = (BACKEND_BASE || '').replace(/\/+$/, '');
      if (!base) return;

      uni.request({
        url: base + '/index.php?r=api/config',
        method: 'GET',
        success: (res) => {
          const d = (res && res.data && res.data.data) || {};
          const backendBase = (d.site && d.site.site_url)
            ? d.site.site_url.replace(/\/+$/, '')
            : base;
          const cfg = {
            backendBase,
            ai: d.ai || {},
            site: d.site || {},
          };
          this.globalData.backendBase = backendBase;
          this.globalData.ai = cfg.ai;
          this.globalData.site = cfg.site;
          // 社区总开关：写入 globalData + 本地存储，并广播给自定义 tabBar
          const forumOn = !!(d.forum && d.forum.on);
          this.globalData.forumOn = forumOn;
          uni.setStorageSync('zhicms_forum_on', forumOn ? '1' : '0');
          uni.$emit('forum-config-changed');
          setAppConfig(cfg);

          if (cfg.ai && cfg.ai.enabled) {
            initAiService({
              apiUrl: cfg.ai.api_url || '',
              apiKey: cfg.ai.token || 'miniapp',
              backendBase,
            });
          } else {
            initAiService({ backendBase });
          }
        },
        fail: () => {
          // 拉取失败：仍以本地 BACKEND_BASE 初始化商品服务
          const forumOn = uni.getStorageSync('zhicms_forum_on') !== '0';
          this.globalData.forumOn = forumOn;
          uni.$emit('forum-config-changed');
          setAppConfig({ backendBase: base });
          initAiService({ backendBase: base });
        },
      });
    },
  },
};
</script>

<style>
/* 全局基础样式 */
page {
  height: 100%;
  background-color: #f3f4f6;
  font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Helvetica Neue', sans-serif;
  color: #1a1a2e;
}

view,
text,
button,
input,
textarea {
  box-sizing: border-box;
}
</style>
