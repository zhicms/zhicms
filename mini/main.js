import Vue from 'vue';
import App from './App';
import AiChatPlugin from './index.js';
import './uni.scss';

Vue.config.productionTip = false;

// 全局注册 <ai-chat /> 组件（任意页面均可直接使用，无需 import）
Vue.use(AiChatPlugin);

App.mpType = 'app';

const app = new Vue({
  ...App,
});
app.$mount();
