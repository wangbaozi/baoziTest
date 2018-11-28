<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Libs;

use App\Services\Util;

/**
 * sendcloud 封装
 *
 */
class Email {

	private $config = [
		'apiUser' => 'baoziwang_test_1Di8pH',
		'apiKey' => 'eVcANpTF88CQLXmP',
		'from' => 'kZxMvrYiEh2pwKX4PX8kfNhvy9OfY8E7.sendcloud.org',
		
	];
	private $send_cloud = array(
		'api_send_url' => 'http://api.sendcloud.net/apiv2/mail/send', //普通发送url
		'api_send_tpl_url' => 'http://api.sendcloud.net/apiv2/mail/sendtemplate', //模板发送url
	);
	private function __construct(array $config = []) {
		if (!empty($config)) {
			$this->config = $config;
		}
	}

	public static function getIns(array $config = []) {
		$key = md5(var_export($config,true));
		static $ins;
		if (empty($ins[$key]) || !($ins[$key] instanceof self)) {
			$ins[$key] = new self($config);
		}

		return $ins[$key];
	}

	/**
	 * 普通发送
	 */
	public function send($to, $subject, $html, $contentSummary = '', $from = '', $fromName = '', $cc = '', $bcc = '', $replyTo = '') {
		if (empty($to) || empty($subject) || empty($html)) {
			return array('statusCode' => '-1', 'message' => '收件人，主题，内容 参数不能为空');
		}
		$from = empty($from) ? $this->config['from'] : $from;
		$fromName = empty($fromName) ? explode('@', $from)[0] : $fromName;
		$request = array(
			'apiUser' => $this->config['apiUser'],
			'apiKey' => $this->config['apiKey'],
			'from' => $from,
			'to' => $to,
			'subject' => $subject,
			'html' => $html,
			'contentSummary' => $contentSummary,
			'fromName' => $fromName,
			'cc' => $cc,
			'bcc' => $bcc,
			'replyTo' => $replyTo, //为空默认from
		);
		$send_rs = Util::request($this->send_cloud['api_send_url'], array('data' => $request));
		return json_decode($send_rs, true);
	}

	/**
	 * 模板发送
	 */
	public function sendTpl($to, $templateInvokeName,array $templateParams=[], $contentSummary = '', $from = '', $fromName = '', $replyTo = '') {
		if (empty($to) || empty($templateInvokeName)) {
			return array('statusCode' => '-1', 'message' => '收件人、模板参数不能为空');
		}
		//兼容处理发件人为数组
		$to = !is_array($to) ? array($to) : $to;
		//兼容处理模板变量为数组
		foreach($templateParams as $k=>$v){
			if(!is_array($v)){
				$templateParams[$k] = array($v);
			}
		}
		$from = empty($from) ? $this->config['from'] : $from;
		$fromName = empty($fromName) ? explode('@', $from)[0] : $fromName;
		
		$request = array(
			'apiUser' => $this->config['apiUser'],
			'apiKey' => $this->config['apiKey'],
			'from' => $from,
			'fromName' => $fromName,
			'replyTo' => $replyTo,
			'templateInvokeName' => $templateInvokeName,
			'contentSummary' => $contentSummary,
			'xsmtpapi'=>json_encode(
						array(
							'to'=>$to,//收件人数组array('to1','to2')
							'sub'=>$templateParams,//参数键值对array('%param1%' => array('to_1value','to2_value','%param2%'=>array('to1_value','to2_value'))
						)
					)
		);
		$send_rs = Util::request($this->send_cloud['api_send_tpl_url'], array('data' => $request));
		return json_decode($send_rs, true);
	}

}
