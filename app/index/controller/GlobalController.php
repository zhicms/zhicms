<?php
namespace app\index\controller;

class GlobalController extends \app\base\controller\BaseController {

	public function type($lock,$id,$table){
		if($lock=="y"){
		 if($id!='null'){
			$where[]=" `id` ={$id}";
			$ret=obj("api/ApiData")->dataSelect($table,$where);
			}else{
			$where[]="1";
			$ret=obj("api/ApiData")->dataSelect($table,$where,"`id` ASC");
			}
			
			return $ret;
		}
	}
	public function mallType($lock,$id,$table,$type){
		if($lock=="y"){
		 if($type!='null'){
			$where[]=" `mallType` ={$id}";
			$ret=obj("api/ApiData")->dataSelect($table,$where);
			}else{
			$where[]=" `mallType` ={$id}";
			$ret=obj("api/ApiData")->dataSelect($table,$where,"`id` ASC");
			}
			
			return $ret;
		}
	}
	
	public function yqfType($lock,$id,$table,$type){
		if($lock=="y"){
		 if($type!='null'){
			$where[]=" `mallType` ={$id}";
			$ret=obj("api/ApiData")->dataSelect($table,$where);
			}else{
			$where[]=" `mallType` ={$id}";
			$ret=obj("api/ApiData")->dataSelect($table,$where,"`id` ASC");
			}
			
			return $ret;
		}
	}
	
	public function yqfMallType($lock,$id,$table,$type){
		if($lock=="y"){
		 if($type!='null'){
			$where[]=" `id` ={$id}";
			$ret=obj("api/ApiData")->dataSelect($table,$where);
			}else{
			$where[]=" `id` ={$id}";
			$ret=obj("api/ApiData")->dataSelect($table,$where,"`id` ASC");
			}
			
			return $ret;
		}
	}
	
	public function duoMaiType($lock,$id,$table,$type){
		if($lock=="y"){
			// 安全转义$id防止SQL注入
			$safeId = addslashes($id);
		 if($type!='null'){
			$where[]=" `country` !='".$safeId."'";
			$ret=obj("api/ApiData")->dataSelect($table,$where," RAND() DESC LIMIT 20");
			}else{
			$where[]="`country` ='".$safeId."'";
			$ret=obj("api/ApiData")->dataSelect($table,$where," RAND() DESC LIMIT 20");
			}
			
			return $ret;
		}
	}
	public  function union($lock,$mallId){

		if($lock=="y"){
			$where[] = "  `id` ={$mallId} ";
		    $retMall = obj("api/ApiData")->dataSelect("yun_mall", $where);
		    if($retMall['union']==""){
			exit('该商城未指定联盟类型!');
		    }
		    $whereUnion[]="`id` ={$retMall['union']}";
		    $retUnion=obj("api/ApiData")->dataSelect("yun_union", $whereUnion);
		    return $retUnion["type"];

		}
	}
    

	  public function loadMall($lock,$type){

  	  if($lock=="y"){

  	  	$where[]="`yun_home_mall`.union =  `yun_union`.id and `view` ={$type}";
  	  	$ret=obj("api/ApiData")->dataSelect("yun_home_mall`,`yun_union",$where,"`px` ASC");

  	  	foreach ($ret as $key => $value) {

  	  		$newLink=str_replace(array("[TOLINK]"),array($value['link']),base64_decode($value['code']));
  	  		
  	  		$data['link']=$value['link'];
  	  		$data['pic']=$value['pic'];
  	  		$data['code']=$newLink;

  	  		$newData[]=$data;


  	  	}

  	  	return $newData;
  	  	
  	  }
  }
  
  

	public function aType($lock,$id){
		if($lock=="y"){
			if($id!='null'){
				$where[]=" `id` ={$id}";
				$nav = obj("api/ApiData")->dataSelect("yun_nav", $where);
			}else{

				$where[]=" 1";
				$nav = obj("api/ApiData")->dataSelect("yun_nav", $where,"`id` DESC ");
			}
			 
             
             return $nav;
		}
	}

	public function vestList($lock){
		if($lock=="y"){
		$where[]="`vest` =1";
		$ret=obj("api/ApiData")->dataSelect("yun_user",$where,"`id` DESC");
		return $ret;
		}
	}

	public function findUser($lock,$uid,$model='null'){
		if($lock=="y"){
		   if($model=='cookie'){
		   	 // 安全转义手机号防止LIKE SQL注入
		   	 $safeUid = str_replace(['%', '_', '\\'], ['\%', '\_', '\\\\'], $uid);
		   	 $where[]="  `mobile` LIKE  '{$safeUid}'";
		   	}else{
		   	  $where[]=" `id` ={$uid}";
		   	}
		   $ret=obj("api/ApiData")->dataSelect("yun_user",$where);
		   return $ret;
		}
	}

   public function getList($table,$lock,$pid){

   	  if($lock=="y"){
   	  	$where[]="`pid` ={$pid}";
		$send = obj("api/ApiData")->dataSelect($table, $where,"`id` ASC  ");

	    return $send;
   	  }
   }

   public function getGlList($table,$lock){
   	 if($lock=="y"){
   	  	$where[]="1";
		$send = obj("api/ApiData")->dataSelect($table, $where,"`id` ASC  ");

	    return $send;
   	  }
   }
      public function cid($cid){

    	 if($cid=="1"){
    	 	return "女装";
    	 }
    	  if($cid=="2"){
    	 	return "母婴";
    	 }
    	  if($cid=="3"){
    	 	return "化妆品";
    	 }
    	  if($cid=="4"){
    	 	return "居家";
    	 }
    	  if($cid=="5"){
    	 	return "鞋包配饰";
    	 }
    	  if($cid=="6"){
    	 	return "美食";
    	 }
    	  if($cid=="7"){
    	 	return "文体车品";
    	 }
    	  if($cid=="8"){
    	 	return "数码家电";
    	 }
    	  if($cid=="9"){
    	 	return "男装";
    	 }
    	   if($cid=="10"){
    	 	return "内衣";
    	 }
		 if($cid=="12"){
            return "配饰";
         }
         if($cid=="11"){
            return " 箱包";
         }
         if($cid=="14"){
            return '家装家纺';
         }
          if($cid=="13"){
            return '户外运动';
         }
    }

    /**
     * 日历挂件（移植自 emlog Calendar，适配本项目 yun_article 表）
     * - 高亮有文章的日期
     * - 点击有文章的日期/月份跳转到该月归档
     * @return string
     */
    public function calendar(){
        $ym = $this->arg('ym');
        if (preg_match('/^\d{6}$/', $ym)) {
            $year  = (int)substr($ym, 0, 4);
            $month = (int)substr($ym, 4, 2);
        } else {
            $year  = (int)date('Y');
            $month = (int)date('m');
        }
        $year  = max(2000, min(2100, $year));
        $month = max(1, min(12, $month));

        // 取出已有文章的日期（Ymd）
        $logdate = array();
        $rows = obj("api/ApiData")->thisQuery(
            "SELECT DATE_FORMAT(`date`,'%Y%m%d') AS d FROM `{pre}article` WHERE `date` <= NOW()"
        );
        if ($rows) {
            foreach ($rows as $r) { $logdate[] = $r['d']; }
        }

        $today  = (int)date('Ymd');
        $ymStr  = sprintf('%04d%02d', $year, $month);

        // 上/下个月
        $prevMonth = $month - 1; $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1; $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        $prevYm = sprintf('%04d%02d', $prevYear, $prevMonth);
        $nextYm = sprintf('%04d%02d', $nextYear, $nextMonth);

        $base = url($route='index/index/index', $params=array());
        $sep  = (strpos($base, '?') !== false) ? '&' : '?';

        $html  = '<table class="calendartop" cellspacing="0"><tr>';
        $html .= '<td colspan="2"><span class="cal-year">' . $year . ' 年 </span><span class="cal-month">' . $month . ' 月</span></td>';
        $html .= '</tr></table>';
        $html .= '<table class="calendar" cellspacing="0">';
        $html .= '<tr><td class="week">一</td><td class="week">二</td><td class="week">三</td><td class="week">四</td><td class="week">五</td><td class="week">六</td><td class="sun">日</td></tr>';

        $week = (int)date('w', mktime(0, 0, 0, $month, 1, $year));
        if ($week == 0) { $week = 7; }
        $lastday   = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        $lastweek  = (int)date('w', mktime(0, 0, 0, $month, $lastday, $year));

        $j = 1; $w = 7; $isend = false;
        for ($i = 1; $i <= 6; $i++) {
            if ($isend || ($i == 6 && $lastweek == 0)) { break; }
            $html .= '<tr>';
            for ($j; $j <= $w; $j++) {
                if ($j < $week) {
                    $html .= '<td>&nbsp;</td>';
                } elseif ($j <= 7) {
                    $r = $j - $week + 1;
                    $n_time = sprintf('%04d%02d%02d', $year, $month, $r);
                    $html .= $this->calCell($r, $n_time, $logdate, $today, $ymStr, $base);
                } else {
                    $t = $j - ($week - 1);
                    if ($t > $lastday) {
                        $isend = true;
                        $html .= '<td>&nbsp;</td>';
                    } else {
                        $n_time = sprintf('%04d%02d%02d', $year, $month, $t);
                        $html .= $this->calCell($t, $n_time, $logdate, $today, $ymStr, $base);
                    }
                }
            }
            $html .= '</tr>';
            $w += 7;
        }
        $html .= '</table>';
        return $html;
    }

    private function calCell($day, $n_time, $logdate, $today, $ymStr, $base){
        if (in_array($n_time, $logdate) && $n_time == $today) {
            return '<td class="day"><em>' . $day . '</em></td>';
        } elseif (in_array($n_time, $logdate)) {
            return '<td class="day2"><em>' . $day . '</em></td>';
        } elseif ($n_time == $today) {
            return '<td class="day"><em>' . $day . '</em></td>';
        }
        return '<td>' . $day . '</td>';
    }

}