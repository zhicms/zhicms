<?php
/**
 * AI 对话（智能导购）配置
 * 该配置仅在服务端使用，API 不会把 api_key 下发给小程序/前端。
 */
return array(
    'enabled'       => false,                       // 是否开启 AI 对话
    'theme_color'   => '#6C63FF',                   // 小程序端主题色
    'default_role'  => 'shopping',                  // 默认 AI 角色
    'provider'      => 'deepseek',                  // 服务商标识（仅展示）
    'api_url'       => 'https://api.deepseek.com/v1/chat/completions',
    'api_key'       => '',                          // 服务端保管，切勿下发前端
    'model'         => 'deepseek-chat',
    'temperature'   => 0.7,
    'max_tokens'    => 1024,
    'stream'        => false,                       // 小程序端统一非流式
    'token'         => '',                          // 访问令牌：留空则不校验（Bearer 或 ?token=）
);
