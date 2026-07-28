<?php
namespace ZhiCms\ext;
//数据库安装类,用于导入mysql数据库文件
class Install{

    /*
		参数：
		$sql_path:sql文件路径；
		$old_prefix:原表前缀；
		$new_prefix:新表前缀；
		$separator:分隔符 参数可为";\n"或";\r\n"或";\r"
	*/
    static public function mysql($sql_path,$old_prefix="",$new_prefix="",$separator=";\n") 
    {
        $commenter = array('#','--');
		  //判断文件是否存在
        if(!file_exists($sql_path))
            return false;
        
        $content = file_get_contents($sql_path);   //读取sql文件
        $content = str_replace(array($old_prefix, "\r"), array($new_prefix, "\n"), $content);//替换前缀
		
        // 使用状态机分割 SQL，正确处理字符串值内的分号
        $segment = self::splitSql($content);

        //去掉注释和多余的空行
		$data=array();
        foreach($segment as  $statement)
        {
            $sentence = explode("\n",$statement);         
            $newStatement = array();
            foreach($sentence as $subSentence)
            {
                if('' != trim($subSentence))
                {
                    //判断是会否是注释
                    $isComment = false;
                    foreach($commenter as $comer)
                    {
                        if(preg_match("/^(".$comer.")/is",trim($subSentence)))
                        {
                            $isComment = true;
                            break;
                        }
                    }
                    //如果不是注释，则认为是sql语句
                    if(!$isComment)
                        $newStatement[] = $subSentence;                    
                }
            }           
     	    $data[] = $newStatement;		 	
        }

        //组合sql语句
        foreach($data as  $statement)
        {
            $newStmt = '';
            foreach($statement as $sentence)
            {
                $newStmt = $newStmt.trim($sentence)."\n";
            }    
			if(!empty($newStmt))            
          	{ 
				 $result[] = $newStmt;
			}
        }	
		return $result;
    }

    /**
     * 按分号分割 SQL 语句，正确处理字符串值内的分号
     * 只在字符串外部识别分号作为语句结束符
     */
    private static function splitSql($content)
    {
        $statements = array();
        $current = '';
        $inString = false;
        $escaped = false;

        for ($i = 0, $len = strlen($content); $i < $len; $i++) {
            $ch = $content[$i];
            $current .= $ch;

            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($ch === '\\' && $inString) {
                $escaped = true;
                continue;
            }
            if ($ch === "'") {
                $inString = !$inString;
                continue;
            }
            if ($ch === ';' && !$inString) {
                $stmt = trim($current);
                if ($stmt !== '' && $stmt !== ';') {
                    $statements[] = $stmt;
                }
                $current = '';
            }
        }

        $stmt = trim($current);
        if ($stmt !== '' && $stmt !== ';') {
            $statements[] = $stmt;
        }

        return $statements;
    }
}