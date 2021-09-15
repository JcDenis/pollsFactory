<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
# This file is part of pollsFactory, a plugin for Dotclear 2.
# 
# Copyright (c) 2009-2010 JC Denis and contributors
# jcdenis@gdwd.com
# 
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_RC_PATH')){return;}

class pollsFactoryChart
{
	public $core;
	public $factory;
	protected $cache_dir;
	protected $prop;
	protected $trigger;
	public $cache_folder = 'polls';

	public $api_url = 'http://chart.apis.google.com/chart';
	
	public function __construct($core)
	{
		$this->core = $core;
		$this->factory = new pollsFactory($core);
		# Cache directory
		$this->cache_dir = path::real(DC_TPL_CACHE);
		$use_cache = (boolean) $core->blog->settings->pollsFactory->pollsFactory_graph_cache;
		if (!$use_cache || !is_dir($this->cache_dir) || !is_writable($this->cache_dir)) {
			$this->cache_dir = null;
		}
		# Image properties
		$this->prop = @unserialize($core->blog->settings->pollsFactory->pollsFactory_graph_options);
		if (!is_array($this->prop) || empty($this->prop)){
			$this->prop = self::defaultOptions();
		} else {
			self::cleanOptions($this->prop);
		}
		# Last update
		$this->trigger = (integer) $core->blog->settings->pollsFactory->pollsFactory_graph_trigger;
	}

	public static function defaultOptions()
	{
		return array(
			'width' => '400',
			'ttcolor' => '#000000',
			'txcolor' => '#000000',
			'bgcolor' => '#FFFFFF',
			'chcolor' => '#F8F8F8',
			'barcolor' => '#48D9F9'
		);
	}

	public static function cleanOptions(&$prop)
	{
			$prop['width'] = isset($prop['width']) ? self::size($prop['width'],'400') : '400';
			$prop['ttcolor'] = isset($prop['ttcolor']) ? self::color($prop['ttcolor'],'000000') : '000000';
			$prop['txcolor'] = isset($prop['txcolor']) ? self::color($prop['txcolor'],'000000') : '000000';
			$prop['bgcolor'] = isset($prop['bgcolor']) ? self::color($prop['bgcolor'],'FFFFFF') : 'FFFFFF';
			$prop['chcolor'] = isset($prop['chcolor']) ? self::color($prop['chcolor'],'F8F8F8') : 'F8F8F8';
			$prop['barcolor'] = isset($prop['barcolor']) ? self::color($prop['barcolor'],'4D89F9') : '4D89F9';
	}

	public function serveChart($poll_id,$query_id,$http_cache=true,$http_etag=true)
	{
		$poll_id = (integer) $poll_id;
		$query_id = (integer) $query_id;
		$now = $ts = time();

		# Prepare file path
		$file_md5 = md5($this->core->blog->id.$poll_id.$query_id);
		$file_path = sprintf('%s/%s/%s/%s/%s.png',
			$this->cache_dir,
			$this->cache_folder,
			substr($file_md5,0,2),
			substr($file_md5,2,2),
			$file_md5
		);
		# File modify time
		clearstatcache();
		$file_time = false;
		if (file_exists($file_path)) {
			$file_time = filemtime($file_path);
			$ts = $file_time;
		}
		# Last admin update time
		$trig_time = $this->trigger;
		# Last vote time
		$updt_params['option_type'] = 'pollsresponse';
		$updt_params['limit'] = 1;
		$updt_params['order'] = 'option_upddt DESC ';
		$updt_params['post_id'] = $poll_id;
		$updt = $this->factory->getOptions($updt_params);
		$updt_time = $updt->isEmpty() ? 0 : strtotime($updt->option_upddt);

		# Check if need update
		if (!$file_time || $file_time < $trig_time || $file_time < $updt_time)
		{
			$ts = $now;
			$content = $this->sendRequest($poll_id,$query_id);

			# Use cache
			if ($this->cache_dir !== null)
			{
				files::makeDir(dirname($file_path),true);
				
				if (($fp = @fopen($file_path,'wb')) === false) {
					//throw new Exception('Unable to create cache file');
				}
				else {
					fwrite($fp,$content);
					fclose($fp);
					files::inheritChmod($file_path);
				}
			}
		}
		else {
			$content = file_get_contents($file_path);
		}
		# Parse content
		if (!empty($content)) {
			http::head(200,'OK');
			header('Last-Modified: '.gmdate('D, d M Y H:i:s',$ts).' GMT');
			header('Cache-Control: must-revalidate, max-age=7200');
			header('Pragma:');
			header('Date: '.gmdate('D, d M Y H:i:s',$now).' GMT');
			header('Content-Type: image/png;');
			echo $content;
		}
		# Error occured
		else {
			http::head(404,'Not Found');
			header('Content-Type: text/plain;');
			echo 'file not found';
		}
		exit(1);
	}

	public function sendRequest($poll_id,$query_id)
	{
		$poll_id = (integer) $poll_id;
		$query_id = (integer) $query_id;
		
		# Get query infos
		$query_params['option_type'] = 'pollsquery';
		$query_params['post_id'] = $poll_id;
		$query_params['option_id'] = $query_id;
		$query = $this->factory->getOptions($query_params);
		if ($query->isEmpty()) {
			return false;
		}

		# Get responses to this query
		$responses_params['option_type'] = 'pollsresponse';
		$responses_params['post_id'] = $poll_id;
		$responses_params['option_meta'] = $query_id;
		$responses = $this->factory->getOptions($responses_params);
		if ($responses->isEmpty()) {
			return false;
		}

		$chd = $chxl = array();
		$max = 0;
		# Loop through responses and count results
		while($responses->fetch())
		{
			$key = (integer) $responses->option_content;

			# Bar width (values)
			if (!isset($chd[$key])) {
				$chd[$key] = 0;
			}
			$chd[$key] += 1;
			# Set maximal value
			if ($max < $chd[$key]) {
				$max = $chd[$key];
			}
		}
		# Loop through options
		$selections_params['option_type'] = 'pollsselection';
		$selections_params['post_id'] = $poll_id;
		$selections_params['option_meta'] = $query_id;
		$selections = $this->factory->getOptions($selections_params);
		while($selections->fetch())
		{
			$key = (integer) $selections->option_id;
			# Set bars with no responses
			if (!isset($chd[$key])) {
				$chd[$key] = 0;
			}
			# Bar title
			$chxl[$key] = html::escapeHTML($selections->option_title);
		}
		# Sort every tables to preserve title/responses
		ksort($chd);ksort($chxl);
		array_multisort($chd,$chxl);
		krsort($chd);ksort($chxl);

		# go go go
		$data = array();
		$data['chd'] = 't:'.implode(',',$chd); // Bar values
		$data['chxt'] = 'x,y'; // Show axis
		$data['chxl'] = '1:|'.implode('|',$chxl); // Axis labels
		$data['chxs'] = '0,'.$this->prop['txcolor'].'|1,'.$this->prop['txcolor'];
		$data['chds'] = '0,'.$max; // Scale of max value
		$data['chm'] = 'N*,'.$this->prop['txcolor'].',0,-1,11'; // Bars labels
		$data['chs'] = $this->prop['width'].'x'.(count($chd) * 22 + 50); // Size = width x height(num_bar * bar_width + title_width)
		$data['chbh'] = '20,2'; // Bar width and space
		$data['chtt'] = $query->option_title; // Title = query_title
		$data['chts'] = $this->prop['ttcolor'].',12'; // title size and color
		$data['cht'] = 'bhs'; // Type = Horizontal bar charts
		$data['chco'] = $this->prop['barcolor']; // Bar Color = #4D89F9
		$data['chf'] = 'c,s,'.$this->prop['chcolor'].'|bg,s,'.$this->prop['bgcolor']; // Chart color and background color;

		return $this->send($this->api_url,$data);
	}

	private static function send($url,$data)
	{
		try	{
			$path = '';
			$client = netHttp::initClient($url,$path);
			$client->setUserAgent('pollsFactory - http://dotclear.jcdenis.com/go/pollsFactory');
			$client->useGzip(false);
			$client->setPersistReferers(false);
			$client->post($url,$data);

			$response = $client->getContent();
		}
		catch (Exception $e) {
			throw new Exception('Failed to send request: '.$e->getMessage());
		}
		if ($client->getStatus() != 200) {
			throw new Exception('Failed to get content: '.$client->getContent());
			return false;
		}
		else {
			return $response;
		}
	}

	public static function size($s,$default=350)
	{
		return preg_match('/^([0-9]+)$/',$s) ? $s : $default;
	}

	public static function color($c,$default='CCCCCC')
	{
		if ($c === '') {
			return $default;
		}
		$c = strtoupper($c);

		if (preg_match('/^[A-F0-9]{6}$/',$c)) {
			return $c;
		}
		if (preg_match('/^[A-F0-9]{3}$/',$c)) {
			$e = explode('',$c);
			return $e[0].$e[0].$e[1].$e[1].$e[2].$e[2];
		}
		if (preg_match('/^#[A-F0-9]{6}$/',$c)) {
			return substr($c,1);
		}
		if (preg_match('/^#[A-F0-9]{3}$/',$c)) {
			$e = explode('',$c);
			return $e[1].$e[1].$e[2].$e[2].$e[3].$e[3];
		}
		return $default;
	}
}
?>