<?php
namespace ZhiCms\base;
class Template {
	protected $config =array();
	protected $label = null;
	protected $vars = array();
	protected $cache = null;
	
	public function __construct($config) {
		$this->config = $config;
		$this->assign('__Template', $this);
		$this->label = array(         
			/**raw php block —— 由 compile() 中的 parsePhpBlock() 使用花括号配对处理，
			 * 以支持块内包含多个 } 的情况（如 if/foreach 的闭合花括号）
				{php echo $x;}  =>  <?php echo $x;?>
				{php $a=1; echo $a; } => <?php $a=1; echo $a; ?>
			*/
			// '{php}' 规则已移入 parsePhpBlock()，见 compile()
			/**variable label
				{$name} => <?php echo $name;?>
				{$user['name']} => <?php echo $user['name'];?>
				{$user.name}    => <?php echo $user['name'];?>
			*/  
			'/{(\\$[a-zA-Z_]\w*(?:\[[\w\.\"\'\[\]\$]+\])*)}/i' => '<?php echo $1; ?>',
			'/\$(\w+)\.(\w+)\.(\w+)\.(\w+)/is' => "\$\\1['\\2']['\\3']['\\4']",
			'/\$(\w+)\.(\w+)\.(\w+)/is' => "\$\\1['\\2']['\\3']",
			'/\$(\w+)\.(\w+)/is' => "\$\\1['\\2']",
			
			/**constance label
			{CONSTANCE} => <?php echo CONSTANCE;?>
			*/
			'/\{([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)\}/s' => "<?php echo \\1;?>",
            
			/**msubstr label
			{musbstr str="test"  min="0" max="20"}   msubstr($str, 0, 20);
			   **/
			'/{musbstr\s*str=(\S+)\+min=\"(.*)\"\+max=\"(.*)\"}/i'=>"<?php echo\\1;echo\\2;echo\\3;?>",

			
			/**if label
				{if $name==1}       =>  <?php if ($name==1){ ?>
				{elseif $name==2}   =>  <?php } elseif ($name==2){ ?>
				{else}              =>  <?php } else { ?>
				{/if}               =>  <?php } ?>
			*/              
			'/\{if\s+(.+?)\}/' => "<?php if(\\1) { ?>",
			'/\{else\}/' => "<?php } else { ?>",
			'/\{elseif\s+(.+?)\}/' => "<?php } elseif (\\1) { ?>",
			'/\{\/if\}/' => "<?php } ?>",
			
			/**for label
				{for $i=0;$i<10;$i++}   =>  <?php for($i=0;$i<10;$i++) { ?>
				{/for}                  =>  <?php } ?>
			*/              
			'/\{for\s+(.+?)\}/' => "<?php for(\\1) { ?>",
			'/\{\/for\}/' => "<?php } ?>",
			
			/**foreach label
				{foreach $arr as $vo}           =>  <?php $n=1; if (is_array($arr) foreach($arr as $vo){ ?>
				{foreach $arr as $key => $vo}   =>  <?php $n=1; if (is_array($array) foreach($arr as $key => $vo){ ?>
				{/foreach}                  =>  <?php $n++;}unset($n) ?> 
			*/
			'/\{foreach\s+(\S+)\s+as\s+(\S+)\}/' => "<?php \$n=1;if(is_array(\\1)) foreach(\\1 as \\2) { ?>", 
			'/\{foreach\s+(\S+)\s+as\s+(\S+)\s*=>\s*(\S+)\}/' => "<?php \$n=1; if(is_array(\\1)) foreach(\\1 as \\2 => \\3) { ?>",
			'/\{\/foreach\}/' => "<?php \$n++;}unset(\$n); ?>",
			
			/**function label
				{date('Y-m-d H:i:s')}   =>  <?php echo date('Y-m-d H:i:s');?> 
				{$date('Y-m-d H:i:s')}  =>  <?php echo $date('Y-m-d H:i:s');?> 
			*/
			'/\{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff:]*\(([^{}]*)\))\}/' => "<?php echo \\1;?>",
			'/\{(\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff:]*\(([^{}]*)\))\}/' => "<?php echo \\1;?>", 
        );
		
		$this->cache = new Cache( $this->config['TPL_CACHE'] );
	}
	
	public function assign($name, $value = '') {
		if( is_array($name) ){
			foreach($name as $k => $v){
				$this->vars[$k] = $v;
			}
		} else {
			$this->vars[$name] = $value;
		}
	}

	public function display($tpl = '', $return = false, $isTpl = true ) {
		if( $return ){
			if ( ob_get_level() ){
				ob_end_flush();
				flush(); 
			} 
			ob_start();
		}
		
		extract($this->vars, EXTR_OVERWRITE);
		try {
			eval('?>' . $this->compile( $tpl, $isTpl));
		} catch (\Throwable $e) {
			// eval 内的语法错误在 PHP8 下抛 ParseError(Error 子类)，普通 catch(Exception) 抓不到。
			// 统一包装成 Exception 并保留原异常链，便于外层 try/catch 与日志定位模板问题。
			throw new \Exception('模板渲染失败：' . $tpl . ' → ' . $e->getMessage(), 0, $e);
		}
		
		if( $return ){
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}
	}	
		
	public function compile( $tpl, $isTpl = true ) {
		if( $isTpl ){
			$tplFile = $this->config['TPL_PATH'] . $tpl . $this->config['TPL_SUFFIX'];
			if ( !file_exists($tplFile) ) {
				throw new \Exception("Template file '{$tplFile}' not found", 500);
			}
			$tplKey = md5(realpath($tplFile));				
		} else {
			$tplKey = md5($tpl);
		}

		$ret = unserialize( $this->cache->get( $tplKey ) );	
		if ( empty($ret['template']) || ($isTpl&&filemtime($tplFile)>($ret['compile_time'])) ) {
			$template = $isTpl ? file_get_contents( $tplFile ) : $tpl;
			if( false === Hook::listen('templateParse', array($template), $template) ){
				// 先处理 {php ... } 块（花括号配对，支持块内含多个 }）
				$template = $this->parsePhpBlock($template);
				foreach ($this->label as $key => $value) {
					$template = preg_replace($key, $value, $template);
				}
				// 展开 {include file="app/.../xxx"} —— legacy 引擎原生不支持 include，需在此递归展开
				$template = $this->parseInclude($template, $isTpl);
			}
			$ret = array('template'=>$template, 'compile_time'=>time());
			$this->cache->set( $tplKey, serialize($ret), 86400*365);
			}	
			return $ret['template'];
			}

			/**
			* 展开模板中的 {include file="app/xxx/yyy"} 标签（legacy 引擎原生不支持 include）
			* 将子模板内容读入并同样经过 label 编译，支持嵌套 include。
			*/
			protected function parseInclude($template, $isTpl = true) {
			$self = $this;
			$template = preg_replace_callback('/\{\s*include\s+file=["\']?([^"\']+)["\']?\s*\}/i', function($m) use ($self, $isTpl) {
			$incFile = $self->config['TPL_PATH'] . $m[1] . $self->config['TPL_SUFFIX'];
			if (!file_exists($incFile)) {
				return '<!-- include not found: ' . $m[1] . ' -->';
			}
			$sub = file_get_contents($incFile);
			// 子模板同样走 label 编译
			foreach ($self->label as $key => $value) {
				$sub = preg_replace($key, $value, $sub);
			}
			// 递归展开子模板内的 include（防止无限递归：最多 10 层）
			static $depth = 0;
			if ($depth < 10) {
				$depth++;
				$sub = $self->parseInclude($sub, $isTpl);
				$depth--;
			}
			return $sub;
		}, $template);
		return $template;
	}

	/**
	 * 处理 {php ... } 原始 PHP 块标签。
	 * 使用花括号配对（栈计数），避免块内 if/foreach 的闭合 } 被非贪婪正则提前截断，
	 * 导致尾部 PHP 代码泄露为纯文本。
	 */
	protected function parsePhpBlock($template) {
		$result = '';
		$offset = 0;
		$len = strlen($template);
		while (($pos = stripos($template, '{php', $offset)) !== false) {
			// 把 {php 之前的内容原样保留
			$result .= substr($template, $offset, $pos - $offset);
			// 从 {php 之后开始配对花括号
			$i = $pos + 4; // 跳过 {php
			// 跳过 {php 后的空白
			while ($i < $len && ctype_space($template[$i])) $i++;
			$depth = 1; // 已经遇到一个 {
			$start = $i;
			$end = false;
			while ($i < $len) {
				$ch = $template[$i];
				if ($ch === '{') {
					$depth++;
				} elseif ($ch === '}') {
					$depth--;
					if ($depth === 0) {
						$end = $i;
						break;
					}
				}
				$i++;
			}
			if ($end === false) {
				// 没有配对的 }，原样保留
				$result .= substr($template, $pos);
				$offset = $len;
				break;
			}
			$phpCode = substr($template, $start, $end - $start);
			$result .= '<?php ' . $phpCode . ' ?>';
			$offset = $end + 1; // 跳过 }
		}
		$result .= substr($template, $offset);
		return $result;
	}
}