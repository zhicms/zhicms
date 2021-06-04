<?php
namespace app\api\model;

class ApiDataModel extends \app\base\model\BaseModel {


	/*-name  Data_Select 查询
	 *-demo  Data_Select($Table,$Where,$Field)
	 -*/
	public function Data_Select($Table,$Where,$Order=null){
	  if(!$Order){
	  	//单条查询
	  	 $data=$this->table($Table)->where($Where)->find();
	  }else{
	  	//多条查询
	  	 $data=$this->table($Table)->where($Where)->order($Order)->select();
	  }
	  return $data;
	}

	/*-name Data_Updata 修改
     *-demo Data_Updata($Table,$data,$condition)
	-*/
	public function Data_Updata($Table,$data,$condition){
		$this->table($Table)->data($data)->where($condition)->update();
	}

   /*查询统计*/
   public function Data_Count($Table,$Where){
   	 return $this->table($Table)->where($Where)->count();

   }

   /*原生态sql*/
   public function thisquery($sql){
     $params = array();
     $thisx=$this->query($sql,$params);
     return $thisx;
   }

   public function executequery($sql){
      $params=array();
      $thisx=$this->execute($sql);
      return $thisx;
   }

   /*插入*/
  public function Inserts($Table,$data){
     $id= $this->table($Table)->data($data)->insert();
     return $id;
   }
   
   /*删除*/
   public function Deletethis($Table,$condition){
	   $sql="DELETE FROM `{pre}{$Table}` WHERE $condition";
	   $params = array();
	   $this->query($sql,$params);

	}
    /*删除缓存*/


   /*前台分页*/
   public function PageIndex($row,$table,$where,$order,$root){
        $page= new \ZhiCms\ext\page_index;
        $url=$root."?page={page}";
        $row=$row;
        $listRows=$row;//每页显示的信息条数
        $cur_page=$page->getCurPage($url);
        $limit_start=($cur_page-1)*$listRows;
        $limit=$limit_start.','.$listRows;
        $count=$this->table($table)->where($where)->count();
        $list=$this->table($table)->where($where)->order($order)->limit($limit)->select(''); 
         //echo $this->Getsql($list);
        $array=array('list'=>$list,'count'=>$count,'page'=> $page->show($url,$count,$listRows));
        return $array;
  }




   /*后台分页*/
   public function Page($row,$table,$where,$order,$root){
        $page= new \ZhiCms\ext\page;
        $url=$root."&page={page}";
        $row=$row;
        $listRows=$row;//每页显示的信息条数
        $cur_page=$page->getCurPage($url);
        $limit_start=($cur_page-1)*$listRows;
        $limit=$limit_start.','.$listRows;
        $count=$this->table($table)->where($where)->count();
        $list=$this->table($table)->where($where)->order($order)->limit($limit)->select(''); 
         //echo $this->Getsql($list);
        $array=array('list'=>$list,'count'=>$count,'page'=> $page->show($url,$count,$listRows));
        return $array;
  }
   

}