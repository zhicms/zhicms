<?php

/**
 * 订单列表
 * @author auto create
 */
class Result
{
	
	/** 
	 * 订单结算时间
	 **/
	public $earning_time;
	
	/** 
	 * 维权金额
	 **/
	public $refund_fee;
	
	/** 
	 * 维权创建(淘客结算回执) 4,维权成功(淘客结算回执) 2,维权失败(淘客结算回执) 3,发生多次维权，待处理      11,从淘客处补扣（钱已结给淘客） 等待扣款 12,从淘客处补扣（钱已结给淘客） 扣款成功 13,从卖家处补扣（钱已结给卖家） 等待扣款 14,从卖家处补扣（钱已结给卖家） 扣款成功 15
	 **/
	public $refund_status;
	
	/** 
	 * 渠道关系id
	 **/
	public $relation_id;
	
	/** 
	 * 会员关系id
	 **/
	public $special_id;
	
	/** 
	 * 宝贝标题
	 **/
	public $tb_auction_title;
	
	/** 
	 * 订单创建时间
	 **/
	public $tb_trade_create_time;
	
	/** 
	 * 淘宝子订单编号
	 **/
	public $tb_trade_id;
	
	/** 
	 * 淘宝订单编号
	 **/
	public $tb_trade_parent_id;
	
	/** 
	 * 第三方推广者memberid
	 **/
	public $tk3rd_pub_id;
	
	/** 
	 * 第三方应该返还的佣金
	 **/
	public $tk_commission_fee_refund3rd_pub;
	
	/** 
	 * 第二方应该返还的佣金(不包括技术服务费)
	 **/
	public $tk_commission_fee_refund_pub;
	
	/** 
	 * 推广者memberid
	 **/
	public $tk_pub_id;
	
	/** 
	 * 维权完成时间
	 **/
	public $tk_refund_suit_time;
	
	/** 
	 * 维权创建时间
	 **/
	public $tk_refund_time;
	
	/** 
	 * 第三方应该返还的补贴
	 **/
	public $tk_subsidy_fee_refund3rd_pub;
	
	/** 
	 * 第二方应该返还的补贴(不包括技术服务费)
	 **/
	public $tk_subsidy_fee_refund_pub;	
}
?>