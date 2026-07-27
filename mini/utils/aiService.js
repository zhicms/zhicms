/**
 * AI 对话服务
 * 支持 OpenAI 兼容接口（可替换为文心一言、通义千问、DeepSeek 等）
 */

// 全局后端配置（App.vue 启动后写入），用于商品拉取兜底
import { getBackendBase } from './appConfig.js';

// ============================================================
// 默认配置（使用者可通过 initAiService 覆盖）
// ============================================================
let _config = {
  apiUrl: 'https://api.openai.com/v1/chat/completions',
  apiKey: '',
  model: 'gpt-3.5-turbo',
  maxTokens: 1024,
  temperature: 0.7,
  stream: false, // 小程序端建议 false，H5 可开启 true
  backendBase: '', // 配套后端（ZhiCms）站点根地址，用于拉取真实商品
};

/**
 * 初始化 AI 服务配置
 * @param {Object} config
 */
export function initAiService(config = {}) {
  _config = { ..._config, ...config };
}

// ============================================================
// 默认提示词模板
// ============================================================
export const DEFAULT_PROMPT_TEMPLATES = {
  assistant: {
    name: '智能助手',
    icon: '🤖',
    systemPrompt: '你是一个友好、专业的智能助手，能够解答各种问题。当用户询问商品、购物相关话题时，你会主动推荐相关商品。',
  },
  shopping: {
    name: '购物顾问',
    icon: '🛍️',
    systemPrompt: '你是一位专业的购物顾问，熟悉各类商品的特点、价格区间和选购要点。你会根据用户的需求、预算和喜好，精准推荐最适合的商品，并给出专业的选购建议。遇到商品推荐需求时，请在回复末尾加上 [RECOMMEND:关键词] 标记。',
  },
  fashion: {
    name: '时尚达人',
    icon: '👗',
    systemPrompt: '你是一位时尚博主，对穿搭、美妆、潮流趋势了如指掌。你的回复风格活泼、时尚，善用 emoji，能帮助用户找到最适合自己风格的商品。遇到穿搭、美妆推荐需求时，请在回复末尾加上 [RECOMMEND:关键词] 标记。',
  },
  tech: {
    name: '数码专家',
    icon: '💻',
    systemPrompt: '你是一位资深的数码产品专家，精通手机、电脑、智能家居等各类数码产品。你能够客观分析各产品的优缺点，帮助用户做出最明智的购买决策。遇到数码产品推荐需求时，请在回复末尾加上 [RECOMMEND:关键词] 标记。',
  },
  food: {
    name: '美食家',
    icon: '🍜',
    systemPrompt: '你是一位热爱美食的生活达人，对食材、厨具、零食等了解深入。你的建议充满生活气息，能帮助用户发现适合自己的美食好物。遇到食品、厨具推荐需求时，请在回复末尾加上 [RECOMMEND:关键词] 标记。',
  },
};

// ============================================================
// 商品推荐触发关键词映射
// ============================================================
const PRODUCT_KEYWORDS = {
  手机: { category: 'phone', tags: ['智能手机', '5G手机', '旗舰手机'] },
  电脑: { category: 'computer', tags: ['笔记本', '台式机', '平板电脑'] },
  耳机: { category: 'earphone', tags: ['无线耳机', '降噪耳机', '蓝牙耳机'] },
  衣服: { category: 'clothing', tags: ['T恤', '外套', '连衣裙'] },
  裙子: { category: 'clothing', tags: ['连衣裙', '半身裙', '百褶裙'] },
  鞋: { category: 'shoes', tags: ['运动鞋', '休闲鞋', '高跟鞋'] },
  护肤: { category: 'skincare', tags: ['面霜', '精华液', '防晒霜'] },
  口红: { category: 'makeup', tags: ['口红', '唇釉', '唇膏'] },
  零食: { category: 'food', tags: ['饼干', '坚果', '糖果'] },
  咖啡: { category: 'food', tags: ['咖啡豆', '速溶咖啡', '咖啡机'] },
  书: { category: 'book', tags: ['小说', '工具书', '绘本'] },
  包: { category: 'bag', tags: ['背包', '手提包', '斜挎包'] },
  watch: { category: 'watch', tags: ['智能手表', '机械表', '电子表'] },
  手表: { category: 'watch', tags: ['智能手表', '机械表', '电子表'] },
  家电: { category: 'appliance', tags: ['冰箱', '洗衣机', '空调'] },
  健身: { category: 'fitness', tags: ['哑铃', '瑜伽垫', '跑步机'] },
};

/**
 * 从消息中检测需要推荐的商品关键词
 * @param {string} text
 * @returns {Array} 匹配到的关键词数组
 */
export function detectProductKeywords(text) {
  const matched = [];
  for (const [keyword, info] of Object.entries(PRODUCT_KEYWORDS)) {
    if (text.includes(keyword)) {
      matched.push({ keyword, ...info });
    }
  }
  // 解析 AI 回复中的 [RECOMMEND:xxx] 标记
  const recommendMatches = text.match(/\[RECOMMEND:([^\]]+)\]/g);
  if (recommendMatches) {
    recommendMatches.forEach(match => {
      const keyword = match.replace('[RECOMMEND:', '').replace(']', '');
      if (!matched.find(m => m.keyword === keyword)) {
        matched.push({ keyword, category: 'general', tags: [keyword] });
      }
    });
  }
  return matched;
}

/**
 * 商品推荐数据
 * 优先从配套后端（ZhiCms）真实商品接口拉取；未配置后端时返回空数组（由演示模式兜底）。
 * @param {string} keyword
 * @param {string} category
 * @returns {Promise<Array>}
 */
export function getMockProducts(keyword, category) {
  const base = (_config.backendBase || getBackendBase() || '').replace(/\/+$/, '');
  if (!base) {
    return Promise.resolve([]);
  }
  return new Promise((resolve) => {
    uni.request({
      url: base + '/index.php',
      method: 'GET',
      data: {
        r: 'api/goods/search',
        keyword: keyword,
        platform: 'taobao',
        page_size: 6,
      },
      success: (res) => {
        const data = (res && res.data) || {};
        resolve(Array.isArray(data.products) ? data.products : []);
      },
      fail: () => resolve([]),
    });
  });
}

// ============================================================
// 核心 AI 对话调用
// ============================================================

/**
 * 发送消息给 AI
 * @param {Array} messages - 对话历史 [{role, content}]
 * @param {Object} options - 覆盖默认配置
 * @param {Function} onChunk - 流式回调（stream=true 时有效）
 * @returns {Promise<string>} AI 回复内容
 */
export async function sendMessage(messages, options = {}, onChunk = null) {
  const config = { ..._config, ...options };

  if (!config.apiKey) {
    // 未配置 API Key 时返回模拟回复（演示用）
    return simulateAIResponse(messages);
  }

  const body = {
    model: config.model,
    messages,
    max_tokens: config.maxTokens,
    temperature: config.temperature,
    stream: config.stream,
  };

  try {
    if (config.stream && onChunk) {
      return await sendStreamMessage(config, body, onChunk);
    } else {
      return await sendNormalMessage(config, body);
    }
  } catch (err) {
    console.error('[AI Service] 请求失败:', err);
    throw new Error('AI 服务请求失败，请检查网络或 API Key 配置');
  }
}

async function sendNormalMessage(config, body) {
  return new Promise((resolve, reject) => {
    uni.request({
      url: config.apiUrl,
      method: 'POST',
      header: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${config.apiKey}`,
      },
      data: body,
      success: (res) => {
        const content = res.data && res.data.choices && res.data.choices[0]
          && res.data.choices[0].message && res.data.choices[0].message.content;
        if (res.statusCode === 200 && content) {
          resolve(content);
        } else {
          const msg = res.data && res.data.error && res.data.error.message;
          reject(new Error(msg || '未知错误'));
        }
      },
      fail: (err) => reject(err),
    });
  });
}

async function sendStreamMessage(config, body, onChunk) {
  // H5 环境下使用 fetch 实现流式输出
  const response = await fetch(config.apiUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${config.apiKey}`,
    },
    body: JSON.stringify(body),
  });

  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let fullContent = '';

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    const chunk = decoder.decode(value, { stream: true });
    const lines = chunk.split('\n').filter(line => line.startsWith('data: '));

    for (const line of lines) {
      const data = line.replace('data: ', '');
      if (data === '[DONE]') continue;
      try {
        const json = JSON.parse(data);
        const delta = json.choices && json.choices[0] && json.choices[0].delta
          && json.choices[0].delta.content || '';
        if (delta) {
          fullContent += delta;
          onChunk(delta, fullContent);
        }
      } catch {}
    }
  }

  return fullContent;
}

// ============================================================
// 模拟 AI 回复（未配置 API Key 时的演示模式）
// ============================================================
const MOCK_RESPONSES = [
  '您好！我是您的专属AI助手，很高兴为您服务 😊 请问有什么可以帮助您的？',
  '这是个很好的问题！根据您的需求，我建议您可以考虑以下几个方向...',
  '当然可以！我来为您详细介绍一下。',
  '基于您提供的信息，我认为最适合您的方案是...',
  '我理解您的需求，让我为您推荐几款热门商品吧！[RECOMMEND:精选好物]',
];

let _mockIndex = 0;

async function simulateAIResponse(messages) {
  const lastMessage = (messages[messages.length - 1] || {}).content || '';

  await new Promise(resolve => setTimeout(resolve, 800 + Math.random() * 800));

  // 根据关键词返回针对性的模拟回复
  if (lastMessage.includes('推荐') || lastMessage.includes('买') || lastMessage.includes('购买')) {
    return `好的，根据您的需求，我为您精心挑选了几款热门好物！这些商品都经过严格筛选，性价比极高。[RECOMMEND:${lastMessage.slice(0, 4)}]`;
  }
  if (lastMessage.includes('手机')) {
    return '智能手机的选择关键看三点：处理器性能、拍照效果和续航能力。当前旗舰手机中，我特别推荐以下几款，性能出色，口碑很好：[RECOMMEND:手机]';
  }
  if (lastMessage.includes('衣') || lastMessage.includes('穿') || lastMessage.includes('裙')) {
    return '关于穿搭，最重要的是找到适合自己气质的风格！根据您的描述，我推荐以下几款时下流行的单品 ✨ [RECOMMEND:衣服]';
  }
  if (lastMessage.includes('咖啡') || lastMessage.includes('零食') || lastMessage.includes('吃')) {
    return '说到美食，我可太有话说了！☕ 以下这些都是口碑爆棚的美食好物，强烈推荐您试试：[RECOMMEND:咖啡]';
  }

  const response = MOCK_RESPONSES[_mockIndex % MOCK_RESPONSES.length];
  _mockIndex++;
  return response;
}

/**
 * 构建带系统提示词的消息列表
 * @param {string} systemPrompt
 * @param {Array} history
 * @param {string} userMessage
 * @returns {Array}
 */
export function buildMessages(systemPrompt, history, userMessage) {
  const messages = [];
  if (systemPrompt) {
    messages.push({ role: 'system', content: systemPrompt });
  }
  // 最多保留最近 20 条历史（避免超出 token 限制）
  const recentHistory = history.slice(-20);
  messages.push(...recentHistory);
  messages.push({ role: 'user', content: userMessage });
  return messages;
}
