<?php

/**
 * resultList
 * @author auto create
 */
class MapData
{
	
	/** 
	 * 商品信息-叶子类目id
	 **/
	public $category_id;
	
	/** 
	 * 商品信息-叶子类目名称
	 **/
	public $category_name;
	
	/** 
	 * 商品信息-佣金比率。1550表示15.5%
	 **/
	public $commission_rate;
	
	/** 
	 * 商品信息-佣金类型。MKT表示营销计划，SP表示定向计划，COMMON表示通用计划
	 **/
	public $commission_type;
	
	/** 
	 * 优惠券信息-优惠券面额。如：满299元减20元
	 **/
	public $coupon_amount;
	
	/** 
	 * 优惠券信息-优惠券结束时间
	 **/
	public $coupon_end_time;
	
	/** 
	 * 优惠券信息-优惠券id
	 **/
	public $coupon_id;
	
	/** 
	 * 优惠券信息-优惠券满减信息
	 **/
	public $coupon_info;
	
	/** 
	 * 优惠券信息-优惠券剩余量
	 **/
	public $coupon_remain_count;
	
	/** 
	 * 链接-宝贝+券二合一页面链接
	 **/
	public $coupon_share_url;
	
	/** 
	 * 优惠券信息-优惠券起用门槛，满X元可用。如：满299元减20元
	 **/
	public $coupon_start_fee;
	
	/** 
	 * 优惠券信息-优惠券开始时间
	 **/
	public $coupon_start_time;
	
	/** 
	 * 优惠券信息-优惠券总量
	 **/
	public $coupon_total_count;
	
	/** 
	 * 商品信息-是否包含定向计划
	 **/
	public $include_dxjh;
	
	/** 
	 * 商品信息-是否包含营销计划
	 **/
	public $include_mkt;
	
	/** 
	 * 商品信息-定向计划信息
	 **/
	public $info_dxjh;
	
	/** 
	 * 商品信息-宝贝描述(推荐理由)
	 **/
	public $item_description;
	
	/** 
	 * 商品信息-宝贝id
	 **/
	public $item_id;
	
	/** 
	 * 链接-宝贝地址
	 **/
	public $item_url;
	
	/** 
	 * 拼团专用-拼团几人团
	 **/
	public $jdd_num;
	
	/** 
	 * 拼团专用-拼团拼成价，单位元
	 **/
	public $jdd_price;
	
	/** 
	 * 商品信息-一级类目ID
	 **/
	public $level_one_category_id;
	
	/** 
	 * 商品信息-一级类目名称
	 **/
	public $level_one_category_name;
	
	/** 
	 * 锁住的佣金率
	 **/
	public $lock_rate;
	
	/** 
	 * 锁佣结束时间
	 **/
	public $lock_rate_end_time;
	
	/** 
	 * 锁佣开始时间
	 **/
	public $lock_rate_start_time;
	
	/** 
	 * 店铺信息-卖家昵称
	 **/
	public $nick;
	
	/** 
	 * 商品信息-宝贝id(该字段废弃，请勿再用)
	 **/
	public $num_iid;
	
	/** 
	 * 拼团专用-拼团结束时间
	 **/
	public $oetime;
	
	/** 
	 * 拼团专用-拼团一人价（原价)，单位元
	 **/
	public $orig_price;
	
	/** 
	 * 拼团专用-拼团开始时间
	 **/
	public $ostime;
	
	/** 
	 * 商品信息-商品主图
	 **/
	public $pict_url;
	
	/** 
	 * 商品信息-宝贝所在地
	 **/
	public $provcity;
	
	/** 
	 * 商品邮费
	 **/
	public $real_post_fee;
	
	/** 
	 * 商品信息-商品一口价格
	 **/
	public $reserve_price;
	
	/** 
	 * 拼团专用-拼团已售数量
	 **/
	public $sell_num;
	
	/** 
	 * 店铺信息-卖家id
	 **/
	public $seller_id;
	
	/** 
	 * 店铺信息-店铺dsr评分
	 **/
	public $shop_dsr;
	
	/** 
	 * 店铺信息-店铺名称
	 **/
	public $shop_title;
	
	/** 
	 * 商品信息-商品短标题
	 **/
	public $short_title;
	
	/** 
	 * 商品信息-商品小图列表
	 **/
	public $small_images;
	
	/** 
	 * 拼团专用-拼团剩余库存
	 **/
	public $stock;
	
	/** 
	 * 商品信息-商品标题
	 **/
	public $title;
	
	/** 
	 * 商品信息-月支出佣金(该字段废弃，请勿再用)
	 **/
	public $tk_total_commi;
	
	/** 
	 * 商品信息-淘客30天推广量
	 **/
	public $tk_total_sales;
	
	/** 
	 * 营销-天猫营销玩法
	 **/
	public $tmall_play_activity_info;
	
	/** 
	 * 拼团专用-拼团库存数量
	 **/
	public $total_stock;
	
	/** 
	 * 链接-宝贝推广链接
	 **/
	public $url;
	
	/** 
	 * 店铺信息-卖家类型。0表示集市，1表示天猫
	 **/
	public $user_type;
	
	/** 
	 * 预售专用-预售数量
	 **/
	public $uv_sum_pre_sale;
	
	/** 
	 * 商品信息-30天销量
	 **/
	public $volume;
	
	/** 
	 * 商品信息-商品白底图
	 **/
	public $white_image;
	
	/** 
	 * 链接-物料块id(测试中请勿使用)
	 **/
	public $x_id;
	
	/** 
	 * 商品信息-商品折扣价格
	 **/
	public $zk_final_price;	
}
?>