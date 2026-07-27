<template>
  <view class="ai-chat-container" :style="containerStyle">
    <!-- 顶部工具栏 -->
    <view class="chat-header">
      <view class="header-left">
        <view class="header-avatar">
          <text class="role-icon">{{ currentRole.icon }}</text>
          <view class="online-dot"></view>
        </view>
        <view class="header-info">
          <text class="role-name">{{ currentRole.name }}</text>
          <text class="role-status" :class="{ online: isReady }">
            <text class="status-dot"></text>{{ isReady ? '在线接待中' : '配置中...' }}
          </text>
        </view>
      </view>
      <view class="header-actions">
        <view class="action-btn" @tap="toggleSettings">
          <text class="action-icon">⚙️</text>
        </view>
        <view class="action-btn" @tap="clearChat">
          <text class="action-icon">🗑️</text>
        </view>
      </view>
    </view>

    <!-- 消息列表区域 -->
    <scroll-view
      class="chat-messages"
      scroll-y
      :scroll-top="scrollTop"
      scroll-with-animation
      @scrolltoupper="loadMoreHistory"
    >
      <!-- 欢迎语 -->
      <view v-if="messages.length === 0" class="welcome-area">
        <text class="welcome-icon">{{ currentRole.icon }}</text>
        <text class="welcome-title">{{ currentRole.name }}</text>
        <text class="welcome-desc">{{ welcomeText }}</text>
        <!-- 快捷提问 -->
        <view class="quick-questions">
          <view
            v-for="(q, i) in quickQuestions"
            :key="i"
            class="quick-item"
            @tap="sendQuickQuestion(q)"
          >
            <text>{{ q }}</text>
          </view>
        </view>
      </view>

      <!-- 消息气泡 -->
      <view v-for="(msg, index) in messages" :key="msg.id" :id="`msg-${msg.id}`">
        <!-- 时间分割线 -->
        <view v-if="shouldShowTime(msg, index)" class="time-divider">
          <text>{{ formatTime(msg.timestamp) }}</text>
        </view>

        <!-- 用户消息 -->
        <view v-if="msg.role === 'user'" class="message-row user-row">
          <view class="message-bubble user-bubble">
            <text class="message-text">{{ msg.content }}</text>
          </view>
          <view class="avatar user-avatar">
            <text>{{ userAvatar }}</text>
          </view>
        </view>

        <!-- AI 消息 -->
        <view v-else-if="msg.role === 'assistant'" class="message-row ai-row">
          <view class="avatar ai-avatar">
            <text>{{ currentRole.icon }}</text>
          </view>
          <view class="message-content-wrap">
            <view class="message-bubble ai-bubble" :class="{ streaming: msg.streaming }">
              <text class="message-text">{{ cleanContent(msg.content) }}</text>
              <text v-if="msg.streaming" class="typing-cursor">▌</text>
            </view>
            <!-- 消息操作 -->
            <view v-if="!msg.streaming" class="message-actions">
              <view class="msg-action" @tap="copyMessage(msg.content)">
                <text>复制</text>
              </view>
              <view class="msg-action" @tap="retryMessage(index)">
                <text>重试</text>
              </view>
            </view>

            <!-- 商品推荐卡片 -->
            <view v-if="msg.products && msg.products.length > 0" class="products-section">
              <text class="products-title">🛍️ 为您推荐</text>
              <scroll-view class="products-scroll" scroll-x>
                <view class="products-list">
                  <view
                    v-for="product in msg.products"
                    :key="product.id"
                    class="product-card"
                    @tap="handleProductTap(product)"
                  >
                    <image
                      class="product-image"
                      :src="product.image"
                      mode="aspectFill"
                    />
                    <view class="product-info">
                      <text class="product-name">{{ product.name }}</text>
                      <view class="product-meta">
                        <view class="product-rating" v-if="product.rating">
                          <text class="star">★</text>
                          <text class="rating-text">{{ product.rating }}</text>
                        </view>
                        <text class="sold-text" v-if="product.sold">{{ product.sold }}人购买</text>
                      </view>
                      <view class="product-price-row">
                        <view class="price-area">
                          <text class="price-symbol">¥</text>
                          <text class="price-value">{{ product.price }}</text>
                        </view>
                        <text class="original-price" v-if="product.originalPrice">¥{{ product.originalPrice }}</text>
                      </view>
                      <view class="product-tag" v-if="product.tag">
                        <text>{{ product.tag }}</text>
                      </view>
                      <view class="buy-btn" @tap.stop="handleBuyTap(product)">
                        <text>立即购买</text>
                      </view>
                    </view>
                  </view>
                </view>
              </scroll-view>
            </view>
          </view>
        </view>

        <!-- 错误消息 -->
        <view v-else-if="msg.role === 'error'" class="message-row ai-row">
          <view class="avatar ai-avatar error-avatar">
            <text>⚠️</text>
          </view>
          <view class="message-bubble error-bubble">
            <text class="message-text">{{ msg.content }}</text>
            <view class="retry-btn" @tap="retryLastMessage">
              <text>重试</text>
            </view>
          </view>
        </view>
      </view>

      <!-- AI 思考中动画 -->
      <view v-if="isLoading && !isStreaming" class="message-row ai-row">
        <view class="avatar ai-avatar">
          <text>{{ currentRole.icon }}</text>
        </view>
        <view class="message-bubble ai-bubble loading-bubble">
          <view class="typing-dots">
            <view class="dot dot1"></view>
            <view class="dot dot2"></view>
            <view class="dot dot3"></view>
          </view>
        </view>
      </view>

      <view id="msg-bottom" style="height: 20rpx;"></view>
    </scroll-view>

    <!-- 底部输入区 -->
    <view class="chat-input-area" :style="inputAreaStyle">
      <!-- 快捷功能按钮 -->
      <scroll-view class="shortcut-bar" scroll-x v-if="showShortcuts">
        <view class="shortcut-list">
          <view
            v-for="(sc, i) in shortcuts"
            :key="i"
            class="shortcut-item"
            @tap="useShortcut(sc)"
          >
            <text>{{ sc.icon }} {{ sc.label }}</text>
          </view>
        </view>
      </scroll-view>

      <view class="input-row">
        <view class="shortcut-toggle" @tap="showShortcuts = !showShortcuts">
          <text>{{ showShortcuts ? '▼' : '▲' }}</text>
        </view>
        <textarea
          class="chat-input"
          v-model="inputText"
          placeholder="输入消息..."
          :placeholder-style="placeholderStyle"
          :maxlength="500"
          :auto-height="true"
          :cursor-spacing="20"
          :adjust-position="false"
          @confirm="handleSend"
          @keyboardheightchange="onKeyboardChange"
        />
        <view
          class="send-btn"
          :class="{ 'send-btn-active': inputText.trim(), 'send-btn-stop': isLoading }"
          @tap="isLoading ? stopGenerate() : handleSend()"
        >
          <text class="send-icon">{{ isLoading ? '⏹' : '➤' }}</text>
        </view>
      </view>
    </view>

    <!-- 设置面板（提示词微调） -->
    <view class="settings-overlay" v-if="showSettings" @tap.self="toggleSettings">
      <view class="settings-panel">
        <view class="settings-header">
          <text class="settings-title">✨ 个性化设置</text>
          <view class="close-btn" @tap="toggleSettings">
            <text>✕</text>
          </view>
        </view>

        <!-- AI 角色选择 -->
        <view class="settings-section">
          <text class="section-label">选择 AI 角色</text>
          <view class="role-grid">
            <view
              v-for="(role, key) in roleTemplates"
              :key="key"
              class="role-item"
              :class="{ 'role-active': currentRoleKey === key }"
              @tap="selectRole(key)"
            >
              <text class="role-item-icon">{{ role.icon }}</text>
              <text class="role-item-name">{{ role.name }}</text>
            </view>
          </view>
        </view>

        <!-- 对话风格 -->
        <view class="settings-section">
          <text class="section-label">对话风格</text>
          <view class="style-options">
            <view
              v-for="style in conversationStyles"
              :key="style.key"
              class="style-item"
              :class="{ 'style-active': selectedStyle === style.key }"
              @tap="selectedStyle = style.key"
            >
              <text>{{ style.label }}</text>
            </view>
          </view>
        </view>

        <!-- 语气温度 -->
        <view class="settings-section">
          <text class="section-label">
            回复长度 - {{ lengthLabels[replyLength] }}
          </text>
          <view class="slider-wrap">
            <slider
              class="settings-slider"
              :min="0"
              :max="2"
              :step="1"
              :value="replyLength"
              activeColor="#ff4d63"
              @change="replyLength = $event.detail.value"
            />
            <view class="slider-labels">
              <text>精简</text>
              <text>适中</text>
              <text>详细</text>
            </view>
          </view>
        </view>

        <!-- 自定义提示词 -->
        <view class="settings-section">
          <text class="section-label">自定义系统提示词（选填）</text>
          <textarea
            class="custom-prompt-input"
            v-model="customSystemPrompt"
            placeholder="在这里输入自定义提示词，例如：你是一位专业的法律顾问..."
            :maxlength="500"
            :auto-height="true"
          />
          <text class="input-count">{{ customSystemPrompt.length }}/500</text>
        </view>

        <!-- 商品推荐开关 -->
        <view class="settings-section">
          <view class="switch-row">
            <view>
              <text class="section-label" style="margin-bottom: 0;">智能商品推荐</text>
              <text class="section-desc">根据对话内容自动推荐相关商品</text>
            </view>
            <switch
              :checked="enableRecommend"
              color="#ff4d63"
              @change="enableRecommend = $event.detail.value"
            />
          </view>
        </view>

        <!-- API Key 配置 -->
        <view class="settings-section">
          <text class="section-label">API Key（可选，不填使用演示模式）</text>
          <input
            class="api-key-input"
            v-model="tempApiKey"
            placeholder="sk-..."
            type="text"
            :password="true"
          />
        </view>

        <view class="settings-footer">
          <view class="settings-apply-btn" @tap="applySettings">
            <text>应用设置</text>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import {
  initAiService,
  sendMessage,
  buildMessages,
  detectProductKeywords,
  getMockProducts,
  DEFAULT_PROMPT_TEMPLATES,
} from '../../utils/aiService.js';
import { openProduct as openTaoProduct } from '../../utils/openLink.js';

export default {
  name: 'AiChat',
  props: {
    // 主题色
    themeColor: {
      type: String,
      default: '#6C63FF',
    },
    // 用户头像
    userAvatar: {
      type: String,
      default: '👤',
    },
    // 初始提示词模板 key
    defaultRole: {
      type: String,
      default: 'shopping',
    },
    // 高度（rpx）
    height: {
      type: [String, Number],
      default: '100vh',
    },
    // 初始 API Key
    apiKey: {
      type: String,
      default: '',
    },
    // API 地址
    apiUrl: {
      type: String,
      default: '',
    },
    // 商品点击回调
    onProductTap: {
      type: Function,
      default: null,
    },
    // 配套后端（ZhiCms）站点根地址，用于拉取真实商品
    backendBase: {
      type: String,
      default: '',
    },
  },

  data() {
    return {
      messages: [],
      inputText: '',
      isLoading: false,
      isStreaming: false,
      scrollTop: 0,
      scrollIntoView: '',
      keyboardHeight: 0,
      showSettings: false,
      showShortcuts: false,

      // 角色与提示词
      roleTemplates: DEFAULT_PROMPT_TEMPLATES,
      currentRoleKey: 'shopping',
      customSystemPrompt: '',
      selectedStyle: 'friendly',
      replyLength: 1,
      enableRecommend: true,
      tempApiKey: '',

      // 快捷操作
      shortcuts: [
        { icon: '🛍️', label: '推荐商品', text: '帮我推荐一些热门商品' },
        { icon: '💰', label: '性价比', text: '推荐性价比高的产品' },
        { icon: '🎁', label: '送礼推荐', text: '我想买礼物，有什么推荐？' },
        { icon: '📱', label: '数码产品', text: '推荐最新的数码产品' },
        { icon: '👗', label: '时尚穿搭', text: '推荐时下流行的穿搭' },
        { icon: '🌟', label: '热销榜', text: '最近有什么热卖的好物？' },
      ],

      conversationStyles: [
        { key: 'friendly', label: '😊 亲切友好' },
        { key: 'professional', label: '💼 专业严谨' },
        { key: 'humorous', label: '😄 幽默风趣' },
        { key: 'concise', label: '⚡ 简洁直接' },
      ],

      lengthLabels: { 0: '精简', 1: '适中', 2: '详细' },

      messageIdCounter: 0,
      lastMessageTime: 0,
      _stopFlag: false,
    };
  },

  computed: {
    currentRole() {
      return this.roleTemplates[this.currentRoleKey] || this.roleTemplates.shopping;
    },

    isReady() {
      return true;
    },

    containerStyle() {
      const h = typeof this.height === 'number' ? `${this.height}rpx` : this.height;
      // 铺满场景（100%）不写死 height，交给组件样式 position:absolute;inset:0 处理，
      // 兼容性最好；其它具体高度（如数值 rpx）保持原样
      if (h === '100%' || h === '100vh') {
        return `--theme-color: ${this.themeColor};`;
      }
      return `height: ${h}; --theme-color: ${this.themeColor};`;
    },

    // 输入区底部间距：安全区 + 键盘高度。键盘弹出时把输入区精确顶到键盘正上方，
    // 收起时回落到安全区，保证任意屏幕/输入法下输入区都恒定贴底
    inputAreaStyle() {
      const kb = this.keyboardHeight > 0 ? this.keyboardHeight : 0;
      return `padding-bottom: calc(env(safe-area-inset-bottom) + ${kb}px);`;
    },

    placeholderStyle() {
      return 'color: #AAAAAA; font-size: 28rpx;';
    },

    welcomeText() {
      const texts = {
        assistant: '我是您的智能助手，有任何问题都可以问我！',
        shopping: '我是您的专属购物顾问，帮您找到最适合的商品 🛍️',
        fashion: '时尚就是我的语言！告诉我你的风格，让我帮你搭配 ✨',
        tech: '数码达人在线！无论什么数码产品，我都能给你最专业的建议 💻',
        food: '吃货的世界最快乐！让我来给你推荐好吃的 🍜',
      };
      return texts[this.currentRoleKey] || '你好，有什么可以帮到您的？';
    },

    quickQuestions() {
      const questions = {
        shopping: ['最近有哪些热卖爆款？', '500元预算买什么好？', '推荐送女朋友的礼物'],
        fashion: ['夏天穿什么好看？', '显瘦的穿搭技巧', '推荐口红颜色'],
        tech: ['推荐入门级手机', '轻薄笔记本推荐', '性价比最高的耳机'],
        food: ['办公室必备零食推荐', '咖啡爱好者必买', '减肥期间吃什么'],
        assistant: ['你能做什么？', '推荐一些好物', '最近有什么好活动？'],
      };
      return questions[this.currentRoleKey] || questions.assistant;
    },

    systemPrompt() {
      const basePrompt = this.currentRole.systemPrompt;
      const styleMap = {
        friendly: '请用亲切、温暖的语气交流。',
        professional: '请用专业、严谨的语气交流，提供有据可查的建议。',
        humorous: '请用幽默风趣的语气交流，适当加入有趣的比喻和emoji。',
        concise: '请用简洁直接的语气，回复控制在3-5句话内。',
      };
      const lengthMap = {
        0: '回复请尽量精简，控制在2-3句话。',
        1: '回复长度适中，重点内容详细说明。',
        2: '请详细展开回答，提供尽可能多的有用信息。',
      };
      const stylePrompt = styleMap[this.selectedStyle] || '';
      const lengthPrompt = lengthMap[this.replyLength] || '';
      const customPrompt = this.customSystemPrompt ? `\n额外要求：${this.customSystemPrompt}` : '';

      return `${basePrompt}\n${stylePrompt}\n${lengthPrompt}${customPrompt}`;
    },
  },

  watch: {
    defaultRole: {
      immediate: true,
      handler(val) {
        if (this.roleTemplates[val]) {
          this.currentRoleKey = val;
        }
      },
    },
    apiKey: {
      immediate: true,
      handler(val) {
        if (val) {
          this.tempApiKey = val;
          this.initService();
        }
      },
    },
  },

  mounted() {
    this.initService();
  },

  methods: {
    initService() {
      const config = { apiKey: this.tempApiKey || this.apiKey };
      if (this.apiUrl) config.apiUrl = this.apiUrl;
      if (this.backendBase) config.backendBase = this.backendBase;
      initAiService(config);
    },

    // ── 发送消息 ──────────────────────────────────────────
    async handleSend() {
      const text = this.inputText.trim();
      if (!text || this.isLoading) return;

      this.inputText = '';
      this.addMessage('user', text);
      this.scrollToBottom();

      await this.requestAI(text);
    },

    sendQuickQuestion(question) {
      this.inputText = question;
      this.handleSend();
    },

    useShortcut(sc) {
      this.inputText = sc.text;
      this.showShortcuts = false;
      this.handleSend();
    },

    async requestAI(userText) {
      this.isLoading = true;
      this._stopFlag = false;

      // 构建对话历史（只传 user/assistant 角色）
      const history = this.messages
        .filter(m => m.role === 'user' || m.role === 'assistant')
        .map(m => ({ role: m.role, content: m.content }));
      // 去掉最后一条（就是刚刚加入的 user 消息）
      history.pop();

      const msgs = buildMessages(this.systemPrompt, history, userText);

      try {
        // 先加一条空的 AI 消息（用于流式显示）
        const aiMsgId = this.addMessage('assistant', '');
        this.isStreaming = true;

        const content = await sendMessage(msgs, { apiKey: this.tempApiKey }, (delta, full) => {
          if (this._stopFlag) return;
          this.updateMessage(aiMsgId, full, true);
          this.scrollToBottom();
        });

        if (!this._stopFlag) {
          this.updateMessage(aiMsgId, content, false);
          // 检测商品推荐
          if (this.enableRecommend) {
            this.handleProductRecommend(aiMsgId, content, userText);
          }
        }
      } catch (err) {
        this.addMessage('error', err.message || 'AI 服务暂时不可用，请稍后重试');
      } finally {
        this.isLoading = false;
        this.isStreaming = false;
        this.scrollToBottom();
      }
    },

    async handleProductRecommend(msgId, aiContent, userText) {
      const combinedText = aiContent + ' ' + userText;
      const keywords = detectProductKeywords(combinedText);

      if (keywords.length > 0) {
        const products = await getMockProducts(keywords[0].keyword, keywords[0].category);
        this.updateMessageProducts(msgId, products);
        // 触发外部事件
        this.$emit('recommend', { keywords, products });
      }
    },

    stopGenerate() {
      this._stopFlag = true;
      this.isLoading = false;
      this.isStreaming = false;
      // 标记最后一条消息为非流式
      const lastMsg = this.messages[this.messages.length - 1];
      if (lastMsg && lastMsg.role === 'assistant') {
        lastMsg.streaming = false;
      }
    },

    // ── 消息管理 ─────────────────────────────────────────
    addMessage(role, content) {
      const id = ++this.messageIdCounter;
      const msg = {
        id,
        role,
        content,
        timestamp: Date.now(),
        streaming: role === 'assistant' && this.isStreaming,
        products: [],
      };
      this.messages.push(msg);
      return id;
    },

    updateMessage(id, content, streaming) {
      const msg = this.messages.find(m => m.id === id);
      if (msg) {
        msg.content = content;
        msg.streaming = streaming;
      }
    },

    updateMessageProducts(id, products) {
      const msg = this.messages.find(m => m.id === id);
      if (msg) {
        msg.products = products;
      }
    },

    retryMessage(index) {
      // 找到这条 AI 消息前的最后一条用户消息
      for (let i = index - 1; i >= 0; i--) {
        if (this.messages[i].role === 'user') {
          const userText = this.messages[i].content;
          // 删除这条及之后的消息
          this.messages.splice(index);
          this.requestAI(userText);
          return;
        }
      }
    },

    retryLastMessage() {
      const errorIdx = this.messages.findIndex(m => m.role === 'error');
      if (errorIdx > -1) {
        this.messages.splice(errorIdx, 1);
        this.retryMessage(errorIdx);
      }
    },

    clearChat() {
      uni.showModal({
        title: '清空对话',
        content: '确定要清空所有对话记录吗？',
        success: (res) => {
          if (res.confirm) {
            this.messages = [];
            this.$emit('clear');
          }
        },
      });
    },

    copyMessage(content) {
      // 过滤掉 [RECOMMEND:xxx] 标记
      const cleanText = content.replace(/\[RECOMMEND:[^\]]+\]/g, '').trim();
      uni.setClipboardData({
        data: cleanText,
        success: () => uni.showToast({ title: '已复制', icon: 'success' }),
      });
    },

    cleanContent(content) {
      return content.replace(/\[RECOMMEND:[^\]]+\]/g, '').trim();
    },

    // ── 商品相关 ─────────────────────────────────────────
    handleProductTap(product) {
      this.$emit('product-tap', product);
      // 父级未拦截时，组件自带跨端跳转能力（App 唤起淘宝 / 小程序复制淘口令）
      if (!this.onProductTap) {
        this.openProduct(product);
      }
    },

    handleBuyTap(product) {
      this.$emit('buy', product);
      this.openProduct(product);
    },

    openProduct(product) {
      openTaoProduct(product);
    },

    // ── 设置面板 ─────────────────────────────────────────
    toggleSettings() {
      this.showSettings = !this.showSettings;
    },

    selectRole(key) {
      this.currentRoleKey = key;
    },

    applySettings() {
      this.initService();
      this.showSettings = false;
      // 如果有历史消息，提示用户
      if (this.messages.length > 0) {
        uni.showModal({
          title: '设置已应用',
          content: '新设置将在下一次对话中生效。是否清空当前对话重新开始？',
          confirmText: '清空重开',
          cancelText: '继续对话',
          success: (res) => {
            if (res.confirm) this.messages = [];
          },
        });
      } else {
        uni.showToast({ title: '设置已应用', icon: 'success' });
      }
      this.$emit('settings-change', {
        role: this.currentRoleKey,
        style: this.selectedStyle,
        replyLength: this.replyLength,
        enableRecommend: this.enableRecommend,
        customSystemPrompt: this.customSystemPrompt,
      });
    },

    // ── 工具方法 ─────────────────────────────────────────
    scrollToBottom() {
      this.$nextTick(() => {
        // 递增 scrollTop 确保每次都触发滚动到底部
        // （scroll-into-view 在值不变时不会重复触发，导致新消息停在半路）
        this.scrollTop += 100000;
      });
    },

    shouldShowTime(msg, index) {
      if (index === 0) return true;
      const prevMsg = this.messages[index - 1];
      return msg.timestamp - prevMsg.timestamp > 5 * 60 * 1000; // 5分钟
    },

    formatTime(timestamp) {
      const date = new Date(timestamp);
      const h = date.getHours().toString().padStart(2, '0');
      const m = date.getMinutes().toString().padStart(2, '0');
      return `${h}:${m}`;
    },

    loadMoreHistory() {
      // 可扩展：从本地存储加载更多历史
      this.$emit('load-history');
    },

    onKeyboardChange(e) {
      // 记录键盘高度，由 inputAreaStyle 用 padding 把输入区精确顶到键盘正上方，
      // 不依赖微信自动 adjust-position，避免不同设备/输入法键盘高度不一致导致输入区忽上忽下
      this.keyboardHeight = (e && e.detail && e.detail.height) ? e.detail.height : 0;
      this.scrollToBottom();
    },
  },
};
</script>

<style lang="scss" scoped>
/* ============================================================
   CSS 变量 & 全局
   ============================================================ */
.ai-chat-container {
  --theme-color: #ff5b6e;
  --theme-light: #ffeef0;
  --bg-color: #f5f6fa;
  --white: #ffffff;
  --text-primary: #1a1a2e;
  --text-secondary: #6b7080;
  --text-muted: #b0b4be;
  --border-color: #eef0f4;
  --shadow: 0 2rpx 16rpx rgba(108, 99, 255, 0.08);
  --radius-sm: 12rpx;
  --radius-md: 20rpx;
  --radius-lg: 32rpx;

  display: flex;
  flex-direction: column;
  /* 用绝对定位 inset:0 铺满「已定位的父容器」（导购页为 fixed、demo 为 absolute 包裹层），
     这是微信小程序里最可靠的铺满方式，不依赖 flex:1 或 height:100% 的百分比解析，
     能确保聊天容器填满可视区、输入框沉到可视区底部 */
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--bg-color);
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Helvetica Neue', sans-serif;
}

/* ============================================================
   顶部工具栏
   ============================================================ */
.chat-header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx 28rpx;
  background: linear-gradient(135deg, #ff5b6e 0%, #ff8a5b 100%);
  box-shadow: 0 6rpx 24rpx rgba(255, 90, 110, 0.28);
  z-index: 10;
}

.header-avatar {
  position: relative;
  width: 84rpx;
  height: 84rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.22);
  border: 3rpx solid rgba(255, 255, 255, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.role-icon {
  font-size: 46rpx;
  line-height: 1;
}

/* 在线接待：头像右下角绿色呼吸点 */
.online-dot {
  position: absolute;
  right: -2rpx;
  bottom: -2rpx;
  width: 22rpx;
  height: 22rpx;
  border-radius: 50%;
  background: #34c759;
  border: 4rpx solid #fff;
  animation: pulse 1.8s infinite;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16rpx;
}

.header-info {
  display: flex;
  flex-direction: column;
}

.role-name {
  font-size: 32rpx;
  font-weight: 800;
  color: #fff;
  line-height: 1.2;
}

.role-status {
  font-size: 22rpx;
  color: rgba(255, 255, 255, 0.92);
  margin-top: 6rpx;
  display: flex;
  align-items: center;
  gap: 8rpx;
}

.status-dot {
  width: 12rpx;
  height: 12rpx;
  border-radius: 50%;
  background: #46e36b;
  flex-shrink: 0;
}

.header-actions {
  display: flex;
  gap: 10rpx;
}

.action-btn {
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.18);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;

  &:active {
    background: rgba(255, 255, 255, 0.35);
  }
}

.action-icon {
  font-size: 34rpx;
}

@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(52, 199, 89, 0.55); }
  70% { box-shadow: 0 0 0 12rpx rgba(52, 199, 89, 0); }
  100% { box-shadow: 0 0 0 0 rgba(52, 199, 89, 0); }
}

/* ============================================================
   消息区域
   ============================================================ */
.chat-messages {
  flex: 1;
  padding: 24rpx 24rpx 0;
  overflow: hidden;
}

/* 欢迎区（接待欢迎卡） */
.welcome-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin: 24rpx;
  padding: 48rpx 36rpx;
  background: var(--white);
  border-radius: 28rpx;
  box-shadow: 0 8rpx 30rpx rgba(20, 21, 26, 0.05);
  text-align: center;
}

.welcome-icon {
  width: 120rpx;
  height: 120rpx;
  border-radius: 50%;
  background: linear-gradient(135deg, #ff5b6e, #ff8a5b);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 64rpx;
  line-height: 1;
  margin-bottom: 24rpx;
  box-shadow: 0 10rpx 24rpx rgba(255, 90, 110, 0.28);
}

.welcome-title {
  font-size: 40rpx;
  font-weight: 800;
  color: var(--text-primary);
  margin-bottom: 14rpx;
}

.welcome-desc {
  font-size: 27rpx;
  color: var(--text-secondary);
  line-height: 1.6;
  margin-bottom: 36rpx;
}

.quick-questions {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 14rpx;
}

.quick-item {
  background: var(--theme-light);
  border: 1rpx solid #ffd9df;
  border-radius: 999rpx;
  padding: 20rpx 28rpx;
  font-size: 27rpx;
  color: #ff4d63;
  text-align: left;
  transition: all 0.2s;

  &:active {
    background: #ff4d63;
    border-color: #ff4d63;
    color: var(--white);
    transform: scale(0.98);
  }
}

/* 时间分割线 */
.time-divider {
  text-align: center;
  margin: 24rpx 0;

  text {
    font-size: 22rpx;
    color: var(--text-muted);
    background: rgba(0, 0, 0, 0.04);
    padding: 6rpx 20rpx;
    border-radius: 20rpx;
  }
}

/* 消息行 */
.message-row {
  display: flex;
  margin-bottom: 24rpx;
  align-items: flex-start;
  gap: 16rpx;
}

.user-row {
  flex-direction: row-reverse;
}

/* 头像 */
.avatar {
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36rpx;
  flex-shrink: 0;
  background: var(--white);
  box-shadow: var(--shadow);
}

.ai-avatar {
  position: relative;
  background: linear-gradient(135deg, var(--theme-color), #ff8a5b);

  /* 在线接待：每条 AI 消息头像右下角绿色在线点 */
  &::after {
    content: '';
    position: absolute;
    right: -1rpx;
    bottom: -1rpx;
    width: 18rpx;
    height: 18rpx;
    border-radius: 50%;
    background: #34c759;
    border: 3rpx solid #fff;
  }
}

.user-avatar {
  background: linear-gradient(135deg, #34c759, #30d158);
}

.error-avatar {
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
}

/* 气泡 */
.message-bubble {
  max-width: 72%;
  padding: 22rpx 26rpx;
  border-radius: 26rpx;
  box-shadow: 0 6rpx 20rpx rgba(20, 21, 26, 0.06);
  position: relative;
  line-height: 1.6;
}

.user-bubble {
  background: linear-gradient(135deg, #ff5b6e, #ff8a5b);
  border-bottom-right-radius: 6rpx;

  /* 气泡小尾巴，更像真实对话 */
  &::before {
    content: '';
    position: absolute;
    right: -9rpx;
    top: 26rpx;
    width: 0;
    height: 0;
    border: 12rpx solid transparent;
    border-left-color: #ff7a4d;
    border-right: none;
  }

  .message-text {
    color: var(--white);
  }
}

.ai-bubble {
  background: var(--white);
  border: 1rpx solid #eef0f4;
  border-bottom-left-radius: 6rpx;

  &::before {
    content: '';
    position: absolute;
    left: -9rpx;
    top: 26rpx;
    width: 0;
    height: 0;
    border: 12rpx solid transparent;
    border-right-color: #fff;
    border-left: none;
  }

  .message-text {
    color: var(--text-primary);
  }
}

.error-bubble {
  background: #fff5f5;
  border: 1rpx solid #ffcdd2;
  border-bottom-left-radius: 4rpx;

  .message-text {
    color: #e53e3e;
  }
}

.loading-bubble {
  padding: 24rpx 32rpx;
}

.message-text {
  font-size: 30rpx;
  line-height: 1.7;
  word-break: break-word;
}

.typing-cursor {
  color: var(--theme-color);
  animation: blink 1s infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0; }
}

/* 消息操作 */
.message-content-wrap {
  display: flex;
  flex-direction: column;
  gap: 8rpx;
  max-width: 80%;
}

.message-actions {
  display: flex;
  gap: 16rpx;
  padding: 0 4rpx;
}

.msg-action {
  font-size: 22rpx;
  color: var(--text-muted);
  padding: 4rpx 12rpx;

  &:active {
    color: var(--theme-color);
  }
}

/* 重试按钮 */
.retry-btn {
  margin-top: 16rpx;
  text-align: right;

  text {
    font-size: 26rpx;
    color: #e53e3e;
    text-decoration: underline;
  }
}

/* 加载动画 */
.typing-dots {
  display: flex;
  gap: 8rpx;
  align-items: center;
  height: 32rpx;
}

.dot {
  width: 14rpx;
  height: 14rpx;
  border-radius: 50%;
  background: var(--theme-color);
  opacity: 0.3;
  animation: dotPulse 1.4s infinite ease-in-out;
}

.dot2 { animation-delay: 0.2s; }
.dot3 { animation-delay: 0.4s; }

@keyframes dotPulse {
  0%, 60%, 100% { opacity: 0.3; transform: scale(1); }
  30% { opacity: 1; transform: scale(1.3); }
}

/* ============================================================
   商品推荐卡片
   ============================================================ */
.products-section {
  margin-top: 16rpx;
}

.products-title {
  font-size: 26rpx;
  color: var(--text-secondary);
  font-weight: 600;
  margin-bottom: 16rpx;
  display: block;
}

.products-scroll {
  width: 100%;
}

.products-list {
  display: flex;
  gap: 20rpx;
  padding-bottom: 8rpx;
  width: max-content;
}

.product-card {
  width: 260rpx;
  background: var(--white);
  border-radius: 24rpx;
  overflow: hidden;
  box-shadow: 0 8rpx 28rpx rgba(20, 21, 26, 0.10);
  border: 1rpx solid #f3f4f6;
  flex-shrink: 0;
  transition: transform 0.2s;

  &:active {
    transform: scale(0.97);
  }
}

.product-image {
  width: 100%;
  height: 220rpx;
  background: #f6f7fb;
}

.product-info {
  padding: 16rpx 20rpx 20rpx;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}

.product-name {
  font-size: 26rpx;
  color: var(--text-primary);
  font-weight: 600;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.product-meta {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.product-rating {
  display: flex;
  align-items: center;
  gap: 4rpx;
}

.star {
  color: #ffa200;
  font-size: 22rpx;
}

.rating-text {
  font-size: 22rpx;
  color: #ffa200;
  font-weight: 600;
}

.sold-text {
  font-size: 22rpx;
  color: var(--text-muted);
}

.product-price-row {
  display: flex;
  align-items: baseline;
  gap: 8rpx;
}

.price-area {
  display: flex;
  align-items: baseline;
  gap: 2rpx;
}

.price-symbol {
  font-size: 22rpx;
  color: #ff4757;
  font-weight: 700;
}

.price-value {
  font-size: 40rpx;
  color: #ff4757;
  font-weight: 800;
  line-height: 1;
}

.original-price {
  font-size: 22rpx;
  color: var(--text-muted);
  text-decoration: line-through;
}

.product-tag {
  display: inline-flex;
  align-self: flex-start;

  text {
    font-size: 20rpx;
    color: var(--theme-color);
    background: var(--theme-light);
    padding: 4rpx 12rpx;
    border-radius: 8rpx;
    font-weight: 600;
  }
}

.buy-btn {
  background: linear-gradient(135deg, #ff4d63, #ff7a4d);
  border-radius: var(--radius-lg);
  padding: 16rpx;
  text-align: center;
  margin-top: 8rpx;

  text {
    font-size: 26rpx;
    color: var(--white);
    font-weight: 700;
  }

  &:active {
    opacity: 0.8;
  }
}

/* ============================================================
   底部输入区
   ============================================================ */
.chat-input-area {
  background: var(--white);
  border-top: 1rpx solid var(--border-color);
  /* 不被 flex 压缩，恒等于底部一行 */
  flex-shrink: 0;
  box-sizing: border-box;
  padding-bottom: env(safe-area-inset-bottom);
}

.shortcut-bar {
  padding: 16rpx 24rpx 8rpx;
  border-bottom: 1rpx solid var(--border-color);
}

.shortcut-list {
  display: flex;
  gap: 16rpx;
  width: max-content;
}

.shortcut-item {
  background: var(--theme-light);
  border-radius: var(--radius-lg);
  padding: 12rpx 24rpx;
  white-space: nowrap;

  text {
    font-size: 26rpx;
    color: var(--theme-color);
    font-weight: 500;
  }

  &:active {
    background: var(--theme-color);

    text {
      color: var(--white);
    }
  }
}

.input-row {
  display: flex;
  align-items: flex-end;
  padding: 16rpx 24rpx;
  gap: 16rpx;
}

.shortcut-toggle {
  width: 64rpx;
  height: 64rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--bg-color);
  flex-shrink: 0;

  text {
    font-size: 24rpx;
    color: var(--text-secondary);
  }
}

.chat-input {
  flex: 1;
  background: var(--bg-color);
  border-radius: var(--radius-lg);
  padding: 20rpx 24rpx;
  font-size: 30rpx;
  color: var(--text-primary);
  max-height: 200rpx;
  line-height: 1.5;
}

.send-btn {
  width: 80rpx;
  height: 80rpx;
  border-radius: 50%;
  background: #ffd0d6;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s;

  &.send-btn-active {
    background: linear-gradient(135deg, #ff5b6e, #ff8a5b);
    box-shadow: 0 6rpx 18rpx rgba(255, 90, 110, 0.4);
  }

  &.send-btn-stop {
    background: #ff6b6b;
  }

  &:active {
    transform: scale(0.92);
  }
}

.send-icon {
  font-size: 32rpx;
  color: var(--white);
}

/* ============================================================
   设置面板
   ============================================================ */
.settings-overlay {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 100;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}

.settings-panel {
  background: var(--white);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  padding: 0 0 env(safe-area-inset-bottom);
  max-height: 85vh;
  overflow-y: auto;
}

.settings-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 32rpx 40rpx 24rpx;
  border-bottom: 1rpx solid var(--border-color);
  position: sticky;
  top: 0;
  background: var(--white);
  z-index: 1;
}

.settings-title {
  font-size: 36rpx;
  font-weight: 800;
  color: var(--text-primary);
}

.close-btn {
  width: 64rpx;
  height: 64rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--bg-color);

  text {
    font-size: 28rpx;
    color: var(--text-secondary);
  }
}

.settings-section {
  padding: 32rpx 40rpx;
  border-bottom: 1rpx solid var(--border-color);
}

.section-label {
  display: block;
  font-size: 28rpx;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 20rpx;
}

.section-desc {
  display: block;
  font-size: 24rpx;
  color: var(--text-muted);
  margin-top: 4rpx;
}

/* 角色网格 */
.role-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16rpx;
}

.role-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24rpx 16rpx;
  border-radius: var(--radius-md);
  background: var(--bg-color);
  border: 2rpx solid transparent;
  transition: all 0.2s;

  &.role-active {
    background: var(--theme-light);
    border-color: var(--theme-color);
  }

  &:active {
    transform: scale(0.95);
  }
}

.role-item-icon {
  font-size: 48rpx;
  margin-bottom: 8rpx;
}

.role-item-name {
  font-size: 24rpx;
  color: var(--text-primary);
  font-weight: 600;
}

/* 风格选项 */
.style-options {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
}

.style-item {
  padding: 16rpx 28rpx;
  border-radius: var(--radius-lg);
  background: var(--bg-color);
  border: 2rpx solid transparent;

  text {
    font-size: 26rpx;
    color: var(--text-primary);
  }

  &.style-active {
    background: var(--theme-light);
    border-color: var(--theme-color);

    text {
      color: var(--theme-color);
      font-weight: 600;
    }
  }
}

/* 滑块 */
.slider-wrap {
  padding: 0 8rpx;
}

.settings-slider {
  width: 100%;
}

.slider-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 4rpx;

  text {
    font-size: 22rpx;
    color: var(--text-muted);
  }
}

/* 自定义提示词 */
.custom-prompt-input {
  width: 100%;
  background: var(--bg-color);
  border-radius: var(--radius-md);
  padding: 20rpx 24rpx;
  font-size: 28rpx;
  color: var(--text-primary);
  line-height: 1.6;
  min-height: 120rpx;
  box-sizing: border-box;
}

.input-count {
  display: block;
  text-align: right;
  font-size: 22rpx;
  color: var(--text-muted);
  margin-top: 8rpx;
}

/* 开关行 */
.switch-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* API Key */
.api-key-input {
  width: 100%;
  background: var(--bg-color);
  border-radius: var(--radius-md);
  padding: 20rpx 24rpx;
  font-size: 28rpx;
  color: var(--text-primary);
  box-sizing: border-box;
}

/* 应用按钮 */
.settings-footer {
  padding: 24rpx 40rpx 32rpx;
}

.settings-apply-btn {
  background: linear-gradient(135deg, var(--theme-color), #ff8a5b);
  border-radius: var(--radius-lg);
  padding: 28rpx;
  text-align: center;
  box-shadow: 0 8rpx 24rpx rgba(108, 99, 255, 0.35);

  text {
    font-size: 32rpx;
    color: var(--white);
    font-weight: 700;
  }

  &:active {
    opacity: 0.85;
    transform: scale(0.98);
  }
}
</style>
