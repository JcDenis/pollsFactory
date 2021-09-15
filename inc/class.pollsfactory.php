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

class pollsFactory extends postOption
{
	public $core;
	public $con;
	protected $table;
	protected $blog;

	public function __construct($core)
	{
		$this->core = $core;
		$this->con = $core->con;
		$this->blog = $core->con->escape($core->blog->id);
		$this->table = $core->con->escape($core->prefix.'post_option');
	}


	# Check if a people has voted on a poll
	public function checkVote($poll_id)
	{
		$chk = false;
		$poll_id = (integer) $poll_id;
		$ident = (integer) $this->core->blog->settings->pollsFactory->pollsFactory_people_ident;

		# Cookie
		if($ident < 2)
		{
			$list = isset($_COOKIE['pollsFactoryVotes']) ?
				explode(',',$_COOKIE['pollsFactoryVotes']) : array();

			if(in_array($poll_id,$list)) $chk = true;
		}
		# IP
		if($ident > 0)
		{
			$rs = $this->con->select(
				'SELECT option_id '.
				'FROM '.$this->table.' '.
				'WHERE post_id = '.$poll_id.' '.
				"AND option_title = '".$this->con->escape(http::realIP())."' ".
				"AND option_type = 'pollsresponse' ".
				$this->con->limit(1)
			);

			if (!$rs->isEmpty()) $chk = true;
		}
		return $chk;
	}

	# Get records of peoples that have voted on a poll
	public function getVotes($poll_id,$start=null,$limit=null)
	{
		$poll_id = (integer) $poll_id;
		
		$q = '';
		if ($start !== null) {
			$start = (integer) $start;
			$limit = (integer) $limit;
			$q = $this->con->limit(array($start,$limit));
		}

		return $this->con->select(
			'SELECT option_title, post_id '.
			'FROM '.$this->table.' '.
			'WHERE post_id = '.$poll_id.' '.
			"AND option_type = 'pollsresponse' ".
			'GROUP BY option_title, post_id '.
			'ORDER BY option_title DESC '.
			$q
		);
	}

	# Count peoples have voted on a poll
	public function countVotes($poll_id)
	{
		$poll_id = (integer) $poll_id;

		return $this->con->select(
			'SELECT option_title '.
			'FROM '.$this->table.' '.
			'WHERE post_id = '.$poll_id.' '.
			"AND option_type = 'pollsresponse' ".
			'GROUP BY option_title '
		)->count();
	}

	# Set ident of a people that has voted on a poll
	public function setVote($poll_id)
	{
		$poll_id = (integer) $poll_id;

		# Cookie
		if($this->core->blog->settings->pollsFactory->pollsFactory_people_ident < 2)
		{
			$list = isset($_COOKIE['pollsFactoryVotes']) ?
				explode(',',$_COOKIE['pollsFactoryVotes']) : array();

			$list[] = $poll_id;
			setcookie('pollsFactoryVotes',implode(',',$list),time()+60*60*24*30,'/');
		}
		# Ident
		$ip = $this->core->blog->settings->pollsFactory->pollsFactory_people_ident > 0 ?
			$this->con->escape(http::realIP()) :
			substr(http::browserUID(DC_MASTER_KEY),0,24);

		return $ip;
	}

	# Update post_open_tb (polls opened)
	public function updPostOpened($id,$opened)
	{
		if (!$this->core->auth->check('usage,contentadmin',$this->core->blog->id)) {
			throw new Exception(__('You are not allowed to update polls'));
		}

		$id = (integer) $id;
		$opened = (integer) $opened;

		if (empty($id)) {
			throw new Exception(__('No such poll ID'));
		}

		if (!$this->core->auth->check('contentadmin',$this->core->blog->id))
		{
			$strReq = 'SELECT post_id '.
					'FROM '.$this->core->prefix.'post '.
					'WHERE post_id = '.$id.' '.
					"AND user_id = '".$this->con->escape($this->core->auth->userID())."' ";
			
			$rs = $this->con->select($strReq);
			
			if ($rs->isEmpty()) {
				throw new Exception(__('You are not allowed to edit this poll'));
			}
		}
		
		$cur = $this->con->openCursor($this->core->prefix.'post');
		$cur->post_open_tb = $opened;
		$cur->post_upddt = date('Y-m-d H:i:s');
		$cur->update("WHERE post_id = ".$id." AND blog_id = '".$this->blog."' ");

		$this->trigger();
	}

	# Get list of urls where to show full poll
	public static function getPublicUrlTypes($core)
	{
		$types = array();
		$core->callBehavior('pollsFactoryPublicUrlTypes',$types);

		$types[__('home page')] = 'default';
		$types[__('post pages')] = 'post';
		$types[__('static pages')] = 'pages';
		$types[__('tags pages')] = 'tag';
		$types[__('archives pages')] = 'archive';
		$types[__('category pages')] = 'category';
		$types[__('entries feed')] = 'feed';

		return $types;
	}

	# Get list of queries types
	public static function getQueryTypes()
	{
		return array(
			__('multiple choice list') => 'checkbox',
			__('single choice list') => 'radio',
			__('options box') => 'combo',
			__('text field') => 'field',
			__('text area') => 'textarea'
		);
	}
}
?>