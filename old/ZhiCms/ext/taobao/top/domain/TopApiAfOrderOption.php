<?php

/**
 * 入参的对象
 * @author auto create
 */
class TopApiAfOrderOption
{
	
	/** 
	 * pid中的第三段，adzoneId
	 **/
	public $adzone_id;
	
	/** 
	 * pageNo
	 **/
	public $page_no;
	
	/** 
	 * pagesize
	 **/
	public $page_size;
	
	/** 
	 * 处罚状态，0 正常，1 待处罚，2冻结（该字段不再支持，请勿调用）
	 **/
	public $punish_status;
	
	/** 
	 * 渠道关系id
	 **/
	public $relation_id;
	
	/** 
	 * pid中的第二段，siteId
	 **/
	public $site_id;
	
	/** 
	 * 查询时间跨度，不超过30天，单位是天
	 **/
	public $span;
	
	/** 
	 * 会员运营id（该字段不再支持，请勿调用）
	 **/
	public $special_id;
	
	/** 
	 * 查询开始时间，以taoke订单创建时间开始
	 **/
	public $start_time;
	
	/** 
	 * 子订单号
	 **/
	public $tb_trade_id;
	
	/** 
	 * 父订单号（该字段不再支持，请勿调用）
	 **/
	public $tb_trade_parent_id;
	
	/** 
	 * 处罚类型：1 店铺淘客，2其他（该字段不再支持，请勿调用）
	 **/
	public $violation_type;	
}
?>