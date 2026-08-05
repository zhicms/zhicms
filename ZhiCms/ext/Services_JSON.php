<?php
/**
 * Services_JSON —— 兼容垫片（shim）。
 *
 * 原 PEAR::Services_JSON（2005）是 PHP 4/5 时代的 JSON 兼容层，其内部使用 PHP 4 风格
 * 构造函数、`each()` 等写法在 PHP 8 下存在致命错误风险。当前 PHP 已内置 json_encode /
 * json_decode，项目业务逻辑也已全部改用原生函数，本类不再需要其完整实现。
 *
 * 此处仅保留对外公开的 API（类名、encode/decode/isError 方法、行为常量），底层委托给
 * PHP 原生函数，确保：
 *   1. 若第三方插件仍动态调用 `new Services_JSON()` / `Services_JSON::isError()`，行为一致；
 *   2. 在 PHP 7.2–8.x 下零兼容性问题。
 *
 * @package     ZhiCms\ext
 * @deprecated  项目已全面使用 json_encode/json_decode，本类仅作向后兼容垫片。
 */
namespace ZhiCms\ext;

// 行为常量（保留原语义，供 LOOSE_TYPE 兼容）
if (!defined('SERVICES_JSON_SLICE'))        define('SERVICES_JSON_SLICE', 1);
if (!defined('SERVICES_JSON_IN_STR'))       define('SERVICES_JSON_IN_STR', 2);
if (!defined('SERVICES_JSON_IN_ARR'))       define('SERVICES_JSON_IN_ARR', 3);
if (!defined('SERVICES_JSON_IN_OBJ'))       define('SERVICES_JSON_IN_OBJ', 4);
if (!defined('SERVICES_JSON_IN_CMT'))       define('SERVICES_JSON_IN_CMT', 5);
if (!defined('SERVICES_JSON_LOOSE_TYPE'))   define('SERVICES_JSON_LOOSE_TYPE', 16);
if (!defined('SERVICES_JSON_SUPPRESS_ERRORS')) define('SERVICES_JSON_SUPPRESS_ERRORS', 32);

/**
 * JSON 编解码兼容垫片。
 */
class Services_JSON
{
    /** @var int 行为标志位（LOOSE_TYPE / SUPPRESS_ERRORS） */
    public $use = 0;

    /**
     * 构造（兼容 PHP 4 风格调用 new Services_JSON($use)）。
     * @param int $use 行为标志位
     */
    public function __construct($use = 0)
    {
        $this->use = (int)$use;
    }

    /**
     * 将变量编码为 JSON 字符串。
     * @param mixed $var
     * @return string|null 失败时返回 null（SUPPRESS_ERRORS 模式）或抛异常
     */
    public function encode($var)
    {
        $opts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($this->use & SERVICES_JSON_SUPPRESS_ERRORS) {
            $opts |= JSON_PARTIAL_OUTPUT_ON_ERROR;
            $ret = @json_encode($var, $opts);
            return ($ret === false) ? null : $ret;
        }
        $ret = json_encode($var, $opts);
        if ($ret === false) {
            return $this->use & SERVICES_JSON_SUPPRESS_ERRORS ? null : $ret;
        }
        return $ret;
    }

    /**
     * 将 JSON 字符串解码为 PHP 变量。
     * @param string $str
     * @return mixed 默认返回 stdClass 对象；LOOSE_TYPE 时返回关联数组
     */
    public function decode($str)
    {
        $assoc = ($this->use & SERVICES_JSON_LOOSE_TYPE) ? true : false;
        $ret = json_decode($str, $assoc);
        if ($ret === null && strtolower(trim($str)) === 'null') {
            return null;
        }
        if ($ret === null && ($this->use & SERVICES_JSON_SUPPRESS_ERRORS)) {
            return null;
        }
        // 原类在解码失败时会返回 Services_JSON_Error 对象
        if ($ret === null && json_last_error() !== JSON_ERROR_NONE) {
            if ($this->use & SERVICES_JSON_SUPPRESS_ERRORS) {
                return null;
            }
            return new Services_JSON_Error(json_last_error_msg());
        }
        return $ret;
    }

    /**
     * 判断值是否为错误对象（兼容原 Services_JSON_Error 约定）。
     * @param mixed $data
     * @return bool
     */
    public static function isError($data)
    {
        return $data instanceof Services_JSON_Error;
    }
}

/**
 * 兼容原类的错误对象。
 */
class Services_JSON_Error
{
    public $message;
    public function __construct($message = '')
    {
        $this->message = $message;
    }
    public function getMessage()
    {
        return $this->message;
    }
    public function toString()
    {
        return (string)$this->message;
    }
    public function __toString()
    {
        return (string)$this->message;
    }
}
