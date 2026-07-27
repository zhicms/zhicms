/**
 * uni-ai-chat-plugin 插件入口
 *
 * 用法：
 *   import AiChatPlugin from '@/uni-ai-chat-plugin';
 *   Vue.use(AiChatPlugin);
 *
 * 或直接引入组件：
 *   import AiChat from '@/uni-ai-chat-plugin/components/ai-chat/ai-chat.vue';
 */

import AiChat from './components/ai-chat/ai-chat.vue';
import { initAiService, DEFAULT_PROMPT_TEMPLATES } from './utils/aiService.js';

const AiChatPlugin = {
  install(Vue, options = {}) {
    // 全局注册组件
    Vue.component('AiChat', AiChat);

    // 初始化服务配置
    if (options.apiKey || options.apiUrl) {
      initAiService({
        apiKey: options.apiKey || '',
        apiUrl: options.apiUrl || 'https://api.openai.com/v1/chat/completions',
        model: options.model || 'gpt-3.5-turbo',
        temperature: options.temperature || 0.7,
        maxTokens: options.maxTokens || 1024,
        stream: options.stream || false,
      });
    }
  },
};

export { AiChat, initAiService, DEFAULT_PROMPT_TEMPLATES };
export default AiChatPlugin;
