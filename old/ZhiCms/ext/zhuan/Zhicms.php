<?php
class Zhicms {
	public function zhuanlian($apiurl,$token,$pid,$itemiid){
		$url = $apiurl."?s=App.getunionurl.tb&code={$token}&pid={$pid}&goodsId={$itemiid}";
		$ret = $this->dCurl($url);
		    $ret = json_decode($ret,true);
		    return $ret;
	}
	
	
	public function dCurl($url,$post_data='',$cookie_file=''){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0); //不返回header部分
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if($cookie_file!=''){
			curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookie_file); //存储cookies	
		}
		if(!empty($post_data)){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));	
		}
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
}
