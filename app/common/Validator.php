<?php
namespace app\common;

/**
 * 轻量数据校验器（借鉴 ThinkPHP think\Validate 的设计范式）
 *
 * 设计目标：
 *  - API 风格与 think\Validate 保持一致（make()->check()->getError()），
 *    后续若引入 topthink/think-validate，可无缝迁移。
 *  - 零外部依赖，所有规则在框架内实现，不破坏现有运行环境。
 *  - 支持常用规则：require / number / integer / max / min / email / url / in / charset。
 *
 * 使用示例：
 *   $v = Validator::make([
 *       'title'  => 'require',
 *       'navid'  => 'number',
 *       'status' => 'in:0,1',
 *   ], [
 *       'title.require' => '标题不能为空',
 *       'navid.number'  => '分类ID必须为数字',
 *   ]);
 *   if (!$v->check($data)) {
 *       $err = $v->getError(); // 第一条错误信息
 *   }
 */
class Validator
{
    /** @var array 校验规则 */
    protected $rules = [];

    /** @var array 自定义错误消息 */
    protected $messages = [];

    /** @var string|null 最近一次的错误信息 */
    protected $error = null;

    /** @var array 内置规则对应的默认提示 */
    protected static $defaultMsg = [
        'require' => ':attribute 不能为空',
        'number'  => ':attribute 必须是数字',
        'integer' => ':attribute 必须是整数',
        'max'     => ':attribute 长度不能超过 :rule',
        'min'     => ':attribute 长度不能少于 :rule',
        'email'   => ':attribute 格式不正确',
        'url'     => ':attribute 不是合法的URL',
        'in'      => ':attribute 取值不在允许范围内',
        'chs'     => ':attribute 只能是汉字',
    ];

    /** @var array 字段中文名（用于 :attribute 占位） */
    protected $fields = [];

    /**
     * 工厂方法
     */
    public static function make(array $rules = [], array $messages = [], array $fields = [])
    {
        return new self($rules, $messages, $fields);
    }

    public function __construct(array $rules = [], array $messages = [], array $fields = [])
    {
        $this->rules    = $rules;
        $this->messages = $messages;
        $this->fields   = $fields;
    }

    /**
     * 执行校验
     * @param array $data 待校验数据
     * @return bool
     */
    public function check(array $data)
    {
        $this->error = null;
        foreach ($this->rules as $field => $ruleStr) {
            $rules = $this->parseRules($ruleStr);
            $value = $data[$field] ?? null;
            foreach ($rules as $item) {
                $rule = $item['rule'];
                $param = $item['param'];
                // require 规则：空值即失败
                if ($rule === 'require' && $this->isEmpty($value)) {
                    return $this->fail($field, $rule, $param);
                }
                // 非 require 且为空，跳过其余规则（可选字段）
                if ($this->isEmpty($value)) {
                    continue;
                }
                if (!$this->verify($rule, $value, $param)) {
                    return $this->fail($field, $rule, $param);
                }
            }
        }
        return true;
    }

    /**
     * 获取错误信息
     */
    public function getError()
    {
        return $this->error;
    }

    // ---------- 内部实现 ----------

    protected function parseRules($ruleStr)
    {
        $out = [];
        foreach (explode('|', $ruleStr) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (strpos($part, ':') !== false) {
                list($rule, $param) = explode(':', $part, 2);
                $out[] = ['rule' => $rule, 'param' => $param];
            } else {
                $out[] = ['rule' => $part, 'param' => null];
            }
        }
        return $out;
    }

    protected function isEmpty($value)
    {
        return $value === null || $value === '' || $value === [];
    }

    protected function fail($field, $rule, $param)
    {
        $key = $field . '.' . $rule;
        if (isset($this->messages[$key])) {
            $msg = $this->messages[$key];
        } elseif (isset(self::$defaultMsg[$rule])) {
            $msg = self::$defaultMsg[$rule];
        } else {
            $msg = $field . ' 校验失败';
        }
        $attr = $this->fields[$field] ?? $field;
        $msg = str_replace([':attribute', ':rule'], [$attr, $param ?? ''], $msg);
        $this->error = $msg;
        return false;
    }

    protected function verify($rule, $value, $param)
    {
        switch ($rule) {
            case 'number':
                return is_numeric($value);
            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;
            case 'max':
                return mb_strlen((string)$value) <= (int)$param;
            case 'min':
                return mb_strlen((string)$value) >= (int)$param;
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'in':
                $allowed = explode(',', $param);
                return in_array((string)$value, $allowed, true);
            case 'chs':
                return preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', (string)$value) === 1;
            default:
                return true;
        }
    }
}
