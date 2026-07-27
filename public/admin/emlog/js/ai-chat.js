/**
 * AI 聊天助手 - 前端交互 JS
 * 
 * 功能：
 * - 流式 SSE 消息接收
 * - Markdown 渲染（marked.js）
 * - 思考过程展示（支持 DeepSeek-R1 <think> 标签）
 * - 示例建议快捷输入
 * - 会话历史管理
 */
var AIChat = {
    currentEventSource: null,
    isHistoryLoaded: false,
    emptyChatHtml: '',

    /**
     * 初始化
     */
    init: function () {
        var self = this;
        self.buildEmptyHtml();
        self.bindEvents();
    },

    /**
     * 打开聊天窗口
     */
    open: function () {
        $('#aiChatModal').modal('show');
    },

    /**
     * 构建空聊天引导 HTML
     */
    buildEmptyHtml: function () {
        this.emptyChatHtml =
            '<div class="p-3 text-muted" id="empty-chat-guide">' +
            '<p class="font-weight-bold mb-3"><i class="fas fa-info-circle mr-1"></i> AI 智能助手可以帮你：</p>' +
            '<ul class="list-unstyled pl-0" style="line-height: 1.8;">' +
            '<li class="mb-2 chat-example-suggest" data-text="请帮我写一篇关于人工智能发展趋势的文章大纲" style="cursor: pointer;">' +
            '<i class="fas fa-chevron-right text-primary mr-1"></i> "请帮我写一篇关于人工智能发展趋势的文章大纲"</li>' +
            '<li class="mb-2 chat-example-suggest" data-text="如何优化网站的 SEO 排名？" style="cursor: pointer;">' +
            '<i class="fas fa-chevron-right text-primary mr-1"></i> "如何优化网站的 SEO 排名？"</li>' +
            '<li class="mb-2 chat-example-suggest" data-text="给我生成一个产品详情页的描述模板" style="cursor: pointer;">' +
            '<i class="fas fa-chevron-right text-primary mr-1"></i> "给我生成一个产品详情页的描述模板"</li>' +
            '<li class="mb-2 chat-example-suggest" data-text="解释一下什么是 API 接口" style="cursor: pointer;">' +
            '<i class="fas fa-chevron-right text-primary mr-1"></i> "解释一下什么是 API 接口"</li>' +
            '</ul></div>';
    },

    /**
     * 绑定事件
     */
    bindEvents: function () {
        var self = this;

        // 自适应输入框高度
        $('#chat-input').on('input', function () {
            self.adjustInputHeight();
        });

        // Enter 发送，Shift+Enter 换行
        $('#chat-input').on('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                $('#send-btn').click();
            }
        });

        // 示例建议点击
        $(document).on('click', '.chat-example-suggest', function () {
            var text = $(this).data('text');
            $('#chat-input').val(text).focus();
            self.adjustInputHeight();
        });

        // 模态框显示时加载历史
        $('#aiChatModal').on('shown.bs.modal', function () {
            $('#chat-input').focus();
            if (!self.isHistoryLoaded) {
                self.loadHistory();
            }
        });

        // 清空历史
        $('#clear-chat-btn').click(function () {
            $.post('index.php?r=manage/ai/clearHistory', function () {
                $('#chat-box').html(self.emptyChatHtml);
                self.isHistoryLoaded = true;
            });
        });

        // 发送消息
        $('#chat-form').submit(function (event) {
            event.preventDefault();
            if (self.currentEventSource) {
                self.stopStream();
                return;
            }
            var message = $('#chat-input').val().trim();
            if (!message) return;
            self.sendMessage(message);
            $('#chat-input').val('').css('height', 'auto').css('overflow-y', 'hidden');
            $('#send-btn').css('height', 'auto');
        });
    },

    /**
     * 自适应输入框高度
     */
    adjustInputHeight: function () {
        var $input = $('#chat-input');
        var $btn = $('#send-btn');
        $input.css('height', 'auto');
        $btn.css('height', 'auto');
        var scrollH = $input[0].scrollHeight;
        if (scrollH > 200) {
            scrollH = 200;
            $input.css('overflow-y', 'auto');
        } else {
            $input.css('overflow-y', 'hidden');
        }
        $input.css('height', scrollH + 'px');
        $btn.css('height', scrollH + 'px');
    },

    /**
     * 停止流式输出
     */
    stopStream: function () {
        if (this.currentEventSource) {
            this.currentEventSource.close();
            this.currentEventSource = null;
        }
        var $btn = $('#send-btn');
        $btn.removeClass('btn-danger').addClass('btn-primary').prop('disabled', false).text('发送');
    },

    /**
     * XSS 安全的文本转 HTML（保留换行）
     */
    escapeHtml: function (text) {
        return $('<div>').text(text).html().replace(/\n/g, '<br>');
    },

    /**
     * 过滤思考标签，返回纯回答内容
     */
    getCleanMarkdown: function (text) {
        var clean = text.replace(/<think>[\s\S]*?<\/think>/g, '');
        clean = clean.replace(/<think>[\s\S]*$/g, '');
        return clean;
    },

    /**
     * 渲染 Markdown
     */
    renderMarkdown: function (text) {
        var cleanMd = this.getCleanMarkdown(text);
        if (typeof marked === 'function') {
            return marked(cleanMd);
        }
        return this.escapeHtml(cleanMd);
    },

    /**
     * 加载会话历史
     */
    loadHistory: function () {
        var self = this;
        $('#chat-box').html('<div class="text-center text-muted my-3"><i class="fas fa-spinner fa-spin"></i> 加载历史记录...</div>');
        $.getJSON('index.php?r=manage/ai/getHistory', function (res) {
            $('#chat-box').empty();
            if (res.data && res.data.length > 0) {
                res.data.forEach(function (item) {
                    if (item.role === 'user') {
                        $('#chat-box').append(
                            '<div style="background-color:#69b4ff; color:#FFF; border-radius:10px; padding:10px; margin:5px 0;">' +
                            $('<div>').text(item.content).html() + '</div>'
                        );
                    } else if (item.role === 'assistant') {
                        var html = self.renderMarkdown(item.content);
                        $('#chat-box').append(
                            '<div class="ai-chat-message">' +
                            '<div class="ai-answer-wrap">' +
                            '<div class="ai-answer-content markdown">' + html + '</div>' +
                            '</div></div>'
                        );
                    }
                });
                $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
            } else {
                $('#chat-box').append(self.emptyChatHtml);
            }
            self.isHistoryLoaded = true;
        }).fail(function () {
            $('#chat-box').html('<div class="text-center text-danger my-3">加载历史失败</div>');
        });
    },

    /**
     * 发送消息并流式接收回复
     */
    sendMessage: function (message) {
        var self = this;
        $('#empty-chat-guide').remove();

        // 用户消息
        $('#chat-box').append(
            '<div style="background-color:#69b4ff; color:#FFF; border-radius:10px; padding:10px; margin:5px 0;">' +
            $('<div>').text(message).html() + '</div>'
        );
        $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);

        // 切换发送按钮为停止按钮
        var $btn = $('#send-btn');
        $btn.removeClass('btn-primary').addClass('btn-danger').text('停止');

        // 连接 SSE
        if (self.currentEventSource) {
            self.currentEventSource.close();
        }
        self.currentEventSource = new EventSource('index.php?r=manage/ai/chatStream&message=' + encodeURIComponent(message));

        // AI 消息容器
        var $aiMsg = $(
            '<div class="ai-chat-message">' +
            '<div class="ai-thought-wrap d-none">' +
            '<div class="ai-thought-content"></div>' +
            '</div>' +
            '<div class="ai-answer-wrap">' +
            '<div class="ai-answer-content markdown"></div>' +
            '</div></div>'
        );
        $('#chat-box').append($aiMsg);

        var $thoughtWrap = $aiMsg.find('.ai-thought-wrap');
        var $thoughtContent = $aiMsg.find('.ai-thought-content');
        var $answerContent = $aiMsg.find('.ai-answer-content');
        var hasReasoning = false;
        var rawAnswer = '';

        self.currentEventSource.onmessage = function (event) {
            if (event.data === '[DONE]') {
                if (!hasReasoning) {
                    $thoughtWrap.remove();
                }
                self.stopStream();
            } else {
                try {
                    var data = JSON.parse(event.data);
                    var choice = data.choices && data.choices[0] ? data.choices[0] : {};
                    var delta = choice.delta || {};
                    var chunk = delta.content || '';
                    var rchunk = delta.reasoning_content || delta.reasoning || '';

                    if (chunk || rchunk) {
                        // 推理过程（DeepSeek-R1 / 智谱 thinking）
                        if (typeof rchunk === 'string' && $.trim(rchunk) !== '') {
                            hasReasoning = true;
                            $thoughtWrap.removeClass('d-none');
                            $thoughtContent.html(self.escapeHtml($thoughtContent.text() + rchunk));
                            $thoughtWrap[0].scrollTop = $thoughtWrap[0].scrollHeight;
                        }

                        if (chunk) {
                            rawAnswer += chunk;

                            // 检测 <think> 标签
                            var thinkText = '';
                            var mainText = rawAnswer;

                            if (rawAnswer.indexOf('<think>') >= 0) {
                                hasReasoning = true;
                                $thoughtWrap.removeClass('d-none');
                                var thinkMatch = rawAnswer.match(/<think>([\s\S]*?)(?:<\/think>|$)/);
                                if (thinkMatch) {
                                    thinkText = thinkMatch[1];
                                }
                                mainText = rawAnswer.replace(/<think>[\s\S]*?<\/think>/, '');
                                if (rawAnswer.indexOf('</think>') < 0) {
                                    mainText = '';
                                }
                            }

                            if (thinkText) {
                                $thoughtContent.html(self.escapeHtml(thinkText));
                            }

                            if (mainText) {
                                $answerContent.html(self.renderMarkdown(mainText));
                            }
                        }

                        $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
                    }
                } catch (err) {
                    console.error('SSE parse error:', err);
                }
            }
        };

        self.currentEventSource.onerror = function () {
            if (!hasReasoning) {
                $thoughtWrap.remove();
            }
            var currentHtml = $answerContent.html();
            if (currentHtml && currentHtml.trim() === '') {
                $answerContent.html('<span class="text-danger">连接中断，请重试</span>');
            }
            $('#chat-box').scrollTop($('#chat-box')[0].scrollHeight);
            self.stopStream();
        };
    }
};

// 页面加载完成后初始化
$(document).ready(function () {
    AIChat.init();
});
