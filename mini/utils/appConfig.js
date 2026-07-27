/**
 * 运行时全局配置存储
 * App.vue 在 onLaunch 拉取 /api/config 后写入，各页面/组件读取。
 */
let _appConfig = {
  backendBase: '',
  ai: {},
  site: {},
};

export function setAppConfig(cfg = {}) {
  _appConfig = Object.assign({}, _appConfig, cfg);
}

export function getAppConfig() {
  return _appConfig;
}

export function getBackendBase() {
  return _appConfig.backendBase || '';
}
