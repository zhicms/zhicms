<?php
namespace ZhiCms\base\compat;

/**
 * Z-BlogPHP 运行环境垫片（全局 $zbp）
 *
 * 仅实现常见插件用到的成员与方法，足以让“注册钩子 + 读写配置/模块 +
 * 简单 DB 查询”类插件在 ZhiCms 下运行。深度依赖 zbp 模板/数据库内部
 * 的插件需自行适配或改写为原生 ZhiCms 插件（详见插件开发手册）。
 */
class ZbpShim
{
    public $host = '';
    public $usersdir = '';
    public $guid = '';
    public $systemdir = '';
    public $table = array();
    public $db;
    public $template;
    public $config = array();

    public function __construct()
    {
        $this->host = defined('\ROOT_URL') ? \ROOT_URL : '/';
        $this->usersdir = \BASE_PATH . 'plugins/_compat/';
        $this->guid = md5(\BASE_PATH);
        $this->systemdir = defined('\ZBP_SYSTEM_DIR') ? \ZBP_SYSTEM_DIR : (\BASE_PATH . 'zb_system/');
        $pre = self::prefix();
        $this->table = array(
            'Article' => $pre . 'article',
            'Page'    => $pre . 'page',
            'Category'=> $pre . 'category',
            'Comment' => $pre . 'comment',
            'Member'  => $pre . 'user',
            'Module'  => $pre . 'module',
            'Tag'     => $pre . 'tag',
            'Post'    => $pre . 'article',
            'Config'  => $pre . 'config',
        );
        $this->db = new ZbpDb($pre);
        $this->template = new ZbpTemplate();
    }

    protected static function prefix()
    {
        $c = \ZhiCms\base\Config::get('DB.default');
        return !empty($c['DB_PREFIX']) ? $c['DB_PREFIX'] : 'yun_';
    }

    /** 通用查询（zbp 习惯：GetListType('Category', $sql)） */
    public function GetListType($type, $sql)
    {
        $r = obj('api/ApiData')->query($sql);
        return is_array($r) ? $r : array();
    }

    public function GetPageList($cols = '*', $w = array())
    {
        $sql = $this->db->sql->Select($this->table['Page'], '*', $w);
        return $this->GetListType('Page', $sql);
    }

    public function GetModuleList($cols = '*', $w = array())
    {
        $sql = $this->db->sql->Select($this->table['Module'], '*', $w);
        return $this->GetListType('Module', $sql);
    }

    public function GetModuleByFileName($name)
    {
        $m = new ZbpModule();
        $m->FileName = $name;
        return $m;
    }

    public function LoadModules()
    {
        return array();
    }

    public function AddBuildModule($mod)
    {
    }

    public function BuildTemplate()
    {
    }

    public function ValidToken($t)
    {
        return !empty($t);
    }

    /** 权限检查（始终返回 true，由 ZhiCms 自行鉴权） */
    public function CheckRights($action) { return true; }
    public function CheckPlugin($name)    { return true; }

    /** 获取 CSRF Token */
    public function GetCSRFToken() { return md5(session_id() ?: 'zhicms'); }

    /** 设置操作提示（Z-Blog 的 SetHint，在 ZhiCms 中通过 session 暂存） */
    public function SetHint($type = 'good', $msg = '') {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $_SESSION['_zbp_hint_type'] = $type;
        $_SESSION['_zbp_hint_msg']  = $msg;
    }

    /** 加载 Z-Blog（兼容某些插件在子页面中的 $zbp->Load() 调用） */
    public function Load() {}

    /**
     * 错误输出（不调用 exit，避免中断 ZhiCms 流程）
     * 某些插件的后台页面（如 Totoro/main.php）会先检查权限再进入，
     * 在兼容层中我们让权限检查通过，不会进入此方法。
     */
    public function ShowError($code, $file = '', $line = 0)
    {
        // 仅记录日志，不输出也不 exit，避免中断 ZhiCms 页面
        error_log("[ZbpShim] ShowError called with code {$code}");
    }

    public function GetArticleByID($id)
    {
        $r = obj('api/ApiData')->query("SELECT * FROM `{$this->table['Article']}` WHERE id=" . intval($id));
        return $r ? $r[0] : array();
    }

    /** $zbp->Config('plugin_id')->key 形式（基于插件配置 JSON） */
    public function Config($key = '')
    {
        return new ZbpConfig($key);
    }

    /** 
     * 持久化插件配置到数据库
     * Z-Blog 中 SaveConfig 将配置写入 XML 文件，在 ZhiCms 中通过 ZbpConfig::__set 已自动写库，
     * 这里只须确保配置不丢失。对于加载了全部配置的对象，__set 均已完成持久化。
     */
    public function SaveConfig($key = '') { /* _set 已自动持久化，不需要额外操作 */ }

    /** 删除插件配置 */
    public function DelConfig($key = '')
    {
        if ($key) {
            \ZhiCms\base\PluginManager::setConfig($key, array());
        }
    }

    /** 获取评论列表 */
    public function GetCommentList($cols = '*', $w = array(), $order = array(), $page = 1, $isPage = false)
    {
        $sql = $this->db->sql->Select($this->table['Comment'], $cols, $w, '', '');
        return $this->GetListType('Comment', $sql);
    }

    /** 获取文章列表（简易） */
    public function GetArticleList($cols = '*', $w = array(), $order = array(), $page = 1, $isPage = false)
    {
        $sql = $this->db->sql->Select($this->table['Article'], $cols, $w, '', '');
        return $this->GetListType('Article', $sql);
    }

    /** 获取分类列表 */
    public function GetCategoryList($cols = '*', $w = array(), $order = array(), $page = null, $isPage = false)
    {
        $sql = $this->db->sql->Select($this->table['Category'], $cols, $w, '', '');
        return $this->GetListType('Category', $sql);
    }

    /** 通过 ID 获取评论 */
    public function GetCommentByID($id)
    {
        $r = obj('api/ApiData')->query("SELECT * FROM `{$this->table['Comment']}` WHERE comm_ID=" . intval($id));
        return $r ? $r[0] : array();
    }

    /** 获取分类 */
    public function GetCategoryByID($id)
    {
        $r = obj('api/ApiData')->query("SELECT * FROM `{$this->table['Category']}` WHERE cate_ID=" . intval($id));
        return $r ? $r[0] : array();
    }

    /** 获取用户 */
    public function GetMemberByID($id)
    {
        $r = obj('api/ApiData')->query("SELECT * FROM `{$this->table['Member']}` WHERE mem_ID=" . intval($id));
        return $r ? $r[0] : array();
    }

    /** 获取标签 */
    public function GetTagByID($id)
    {
        $r = obj('api/ApiData')->query("SELECT * FROM `{$this->table['Tag']}` WHERE tag_ID=" . intval($id));
        return $r ? $r[0] : array();
    }
}

/** 模块对象垫片（LinksManage 等用到 Content / Metas / Save） */
class ZbpModule
{
    public $FileName = '';
    public $Content = '';
    public $Source = 'plugin';
    public $Metas;

    public function __construct()
    {
        $this->Metas = new ZbpMetas();
    }

    public function Save()
    {
    }

    public function GetData()
    {
        return array('FileName' => $this->FileName, 'Content' => $this->Content);
    }
}

/** 元信息对象（动态属性） */
class ZbpMetas
{
    public function __get($n)
    {
        return $this->$n ?? '';
    }

    public function __set($n, $v)
    {
        $this->$n = $v;
    }
}

/** 配置对象垫片（基于 ZhiCms 插件配置 JSON） */
class ZbpConfig
{
    public $key;
    protected $data = array();
    protected $loaded = false;

    public function __construct($k)
    {
        $this->key = $k;
    }

    protected function load()
    {
        if (!$this->loaded) {
            if ($this->key) {
                $cfg = \ZhiCms\base\PluginManager::getConfig($this->key);
                $this->data = $cfg['_zbp_config'] ?? array();
            }
            $this->loaded = true;
        }
    }

    public function __get($n)
    {
        $this->load();
        return $this->data[$n] ?? null;
    }

    public function __set($n, $v)
    {
        $this->load();
        $this->data[$n] = $v;
        if ($this->key) {
            $cfg = \ZhiCms\base\PluginManager::getConfig($this->key);
            $cfg['_zbp_config'] = $this->data;
            \ZhiCms\base\PluginManager::setConfig($this->key, $cfg);
        }
    }

    public function __isset($n)
    {
        $this->load();
        return isset($this->data[$n]);
    }

    public function HasKey($n)
    {
        $this->load();
        return isset($this->data[$n]);
    }

    public function DelKey($n = '')
    {
        if ($n === '') {
            $this->data = array();
        } else {
            unset($this->data[$n]);
        }
        if ($this->key) {
            $cfg = \ZhiCms\base\PluginManager::getConfig($this->key);
            $cfg['_zbp_config'] = $this->data;
            \ZhiCms\base\PluginManager::setConfig($this->key, $cfg);
        }
    }
}

/** 模板对象垫片 */
class ZbpTemplate
{
    public function SetTags($k, $v)
    {
    }

    public function Output($tpl)
    {
        return '';
    }

    public function HasTemplate($name)
    {
        return false;
    }

    public function GetTemplate($name)
    {
        return '';
    }
}

/** 数据库垫片（含 zbp Sql 构造器） */
class ZbpDb
{
    public $sql;
    public $pre;

    public function __construct($pre)
    {
        $this->pre = $pre;
        $this->sql = new ZbpSql($pre);
    }

    public function Query($sql)
    {
        $r = obj('api/ApiData')->query($sql);
        return is_array($r) ? $r : array();
    }

    public function Execute($sql)
    {
        return obj('api/ApiData')->execute($sql);
    }
}

/** 极简 SQL 构造器（支持 zbp 常用写法） */
class ZbpSql
{
    public $pre;

    public function __construct($pre)
    {
        $this->pre = $pre;
    }

    protected function resolve($table)
    {
        return str_replace('{pre}', $this->pre, $table);
    }

    protected function cols($cols)
    {
        if ($cols === '*' || $cols === '') return '*';
        if (is_array($cols)) {
            return implode(',', array_map(function ($c) {
                return '`' . str_replace('`', '', $c) . '`';
            }, $cols));
        }
        return $cols;
    }

    protected function buildWhere($wheres)
    {
        if (empty($wheres)) return '1';
        $parts = array();
        foreach ($wheres as $w) {
            if (!is_array($w)) {
                $parts[] = $w;
                continue;
            }
            $op = strtolower($w[0]);
            if ($op === 'search') {
                $val = addslashes($w[count($w) - 1]);
                $fields = array_slice($w, 1, -1);
                $like = array();
                foreach ($fields as $f) {
                    $like[] = "`{$f}` LIKE '%{$val}%'";
                }
                $parts[] = '(' . implode(' OR ', $like) . ')';
            } else {
                $field = $w[1];
                $val = isset($w[2]) ? $w[2] : '';
                if (is_array($val)) {
                    $val = implode("','", array_map('addslashes', $val));
                    $parts[] = "`{$field}` " . ($op === '<>' ? 'NOT IN' : 'IN') . " ('{$val}')";
                } else {
                    $val = addslashes($val);
                    $parts[] = "`{$field}` {$w[0]} '{$val}'";
                }
            }
        }
        return implode(' AND ', $parts);
    }

    public function Select($table, $cols = '*', $wheres = array(), $order = '', $limit = '', $offset = 0)
    {
        $sql = "SELECT " . $this->cols($cols) . " FROM `{$this->resolve($table)}`";
        if (!empty($wheres)) $sql .= " WHERE " . $this->buildWhere($wheres);
        if ($order) $sql .= " ORDER BY " . $order;
        if ($limit !== '') $sql .= " LIMIT " . ($offset ? intval($offset) . ',' : '') . intval($limit);
        return $sql;
    }

    public function Update($table, $data = array(), $wheres = array())
    {
        $sets = array();
        foreach ($data as $k => $v) {
            $sets[] = "`{$k}`='" . addslashes($v) . "'";
        }
        $sql = "UPDATE `{$this->resolve($table)}` SET " . implode(',', $sets);
        if (!empty($wheres)) $sql .= " WHERE " . $this->buildWhere($wheres);
        return $sql;
    }

    public function Delete($table, $wheres = array())
    {
        $sql = "DELETE FROM `{$this->resolve($table)}`";
        if (!empty($wheres)) $sql .= " WHERE " . $this->buildWhere($wheres);
        return $sql;
    }

    public function Insert($table, $data = array())
    {
        $ks = $vs = array();
        foreach ($data as $k => $v) {
            $ks[] = "`{$k}`";
            $vs[] = "'" . addslashes($v) . "'";
        }
        return "INSERT INTO `{$this->resolve($table)}` (" . implode(',', $ks) . ") VALUES (" . implode(',', $vs) . ")";
    }
}
