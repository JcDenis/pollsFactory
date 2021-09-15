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

class postOption
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

	public function table()
	{
		return $this->table;
	}

	public function open()
	{
		return $this->con->openCursor($this->table);
	}
	
	public function lock()
	{
		$this->con->writeLock($this->table);
	}
	
	public function unlock()
	{
		$this->con->unlock();
	}

	public function trigger()
	{
		$this->core->blog->triggerBlog();
	}

	public function nextID()
	{
		return (integer) $this->con->select(
			'SELECT MAX(option_id) FROM '.$this->table 
		)->f(0) + 1;
	}

	public function nextPosition($post_id=null,$option_meta=null,$option_type=null)
	{
		$q = array();
		if ($post_id !== null) {
			$q[] = 'post_id = '.((integer) $post_id).' ';
		}
		if ($option_meta !== null) {
			$q[] = "option_meta = '".$this->con->escape($option_meta)."' ";
		}
		if ($option_type !== null) {
			$q[] = "option_type = '".$this->con->escape($option_type)."' ";
		}
		if (empty($q)) return 0;

		return $this->con->select(
			'SELECT MAX(option_position) '.
			'FROM '.$this->table.' '.
			'WHERE '.implode('AND ',$q)
		)->f(0) + 1;
	}

	public function checkAuth(&$cur,$post_id=null)
	{
		if (!$this->core->auth->check('usage,contentadmin',$this->blog)) {
			throw new Exception(__('You are not allowed to edit post option'));
		}

		$post_id = is_int($post_id) ? $post_id : $cur->post_id;

		if (empty($post_id)) {
			throw new Exception(__('No such post ID'));
		}

		if (!$this->core->auth->check('contentadmin',$this->blog))
		{
			$strReq = 'SELECT post_id '.
					'FROM '.$this->core->prefix.'post '.
					'WHERE post_id = '.$post_id.' '.
					"AND user_id = '".$this->con->escape($this->core->auth->userID())."' ";
			
			$rs = $this->con->select($strReq);
			
			if ($rs->isEmpty()) {
				throw new Exception(__('You are not allowed to edit this post option'));
			}
		}
	}

	public function getOptions($params=array(),$count_only=false)
	{
		if ($count_only) {
			$q = 'SELECT count(O.option_id) ';
		}
		else {
			$q = 'SELECT O.option_id, O.post_id, O.option_meta, ';

			if (!empty($params['columns']) && is_array($params['columns'])) {
				$q .= implode(', ',$params['columns']).', ';
			}
			$q .= 
			'O.option_creadt, O.option_upddt, O.option_type, O.option_format, '.
			'O.option_title, O.option_content, O.option_content_xhtml, '.
			'O.option_selected, O.option_position, '.
			'P.blog_id, P.post_type, P.post_title ';
		}

		$q .= 
		'FROM '.$this->table.' O '.
		'LEFT JOIN '.$this->core->prefix.'post P ON P.post_id = O.post_id ';

		if (!empty($params['from'])) {
			$q .= $params['from'].' ';
		}
		
		$q .= "WHERE P.blog_id = '".$this->blog."' ";

		# option_type
		if (isset($params['option_type'])) {
			if (is_array($params['option_type']) && !empty($params['option_type'])) {
				$q .= 'AND O.option_type '.$this->con->in($params['option_type']);
			} elseif ($params['option_type'] != '') {
				$q .= "AND O.option_type = '".$this->con->escape($params['option_type'])."' ";
			} else {
				$q .= "AND O.option_type != '' ";
			}
		}
		else {
			$q .= "AND O.option_type = '' ";
		}
		# option_id
		if (!empty($params['option_id'])) {
			if (is_array($params['option_id'])) {
				array_walk($params['option_id'],create_function('&$v,$k','if($v!==null){$v=(integer)$v;}'));
			} else {
				$params['option_id'] = array((integer) $params['option_id']);
			}
			$q .= 'AND O.option_id '.$this->con->in($params['option_id']);
		}
		# post_id
		if (!empty($params['post_id'])) {
			if (is_array($params['post_id'])) {
				array_walk($params['post_id'],create_function('&$v,$k','if($v!==null){$v=(integer)$v;}'));
			} else {
				$params['post_id'] = array((integer) $params['post_id']);
			}
			$q .= 'AND O.post_id '.$this->con->in($params['post_id']);
		}
		# option_meta
		if (isset($params['option_meta'])) {
			if (is_array($params['option_meta']) && !empty($params['option_meta'])) {
				$q .= 'AND O.option_meta '.$this->con->in($params['option_meta']);
			} elseif ($params['option_meta'] != '') {
				$q .= "AND O.option_meta = '".$this->con->escape($params['option_meta'])."' ";
			} else {
				$q .= "AND O.option_meta IS NULL ";
			}
		}
		# option_selected
		if (isset($params['option_selected'])) {
			$q .= 'AND O.option_selected = '.(integer) $params['option_selected'].' ';
		}
		# option_title
		if (!empty($params['option_title'])) {
			$q .= "AND O.option_title = '".$this->con->escape($params['option_title'])."' ";
		}
		# sql
		if (!empty($params['sql'])) {
			$q .= $params['sql'].' ';
		}
		# order
		if (!$count_only) {
			if (!empty($params['order'])) {
				$q .= 'ORDER BY '.$this->con->escape($params['order']).' ';
			}
			else {
				$q .= 'ORDER BY O.option_id ASC ';
			}
		}
		# limit
		if (!$count_only && !empty($params['limit'])) {
			$q .= $this->con->limit($params['limit']);
		}

		$rs = $this->con->select($q);
		$rs->postOption = $this;

		return $rs;
	}

	public function addOption($cur)
	{
		$this->lock();
		try
		{
			$cur->option_id = $this->nextID();
			$cur->option_creadt = date('Y-m-d H:i:s');
			$cur->option_upddt = date('Y-m-d H:i:s');

			$this->getOptionContent($cur,$cur->option_id);
			$this->checkAuth($cur);

			$cur->insert();
			$this->unlock();
		}
		catch (Exception $e)
		{
			$this->unlock();
			throw $e;
		}

		$this->trigger();
		return $cur->option_id;
	}
	
	public function updOption($id,$cur)
	{
		$id = (integer) $id;
		
		if (empty($id)) {
			throw new Exception(__('No such option ID'));
		}
		
		$this->getOptionContent($cur,$id);
		$this->checkAuth($cur);
		
		$cur->option_upddt = date('Y-m-d H:i:s');
		
		$cur->update('WHERE option_id = '.$id.' ');
		$this->trigger();
	}

	private function updOptionField($id,$field,$value)
	{
		$id = (integer) $id;
		if (empty($id)) {
			throw new Exception(__('No such option ID'));
		}

		$cur = $this->open();
		$cur->setField($field,$value);
		$cur->option_upddt = date('Y-m-d H:i:s');
		$cur->update("WHERE option_id = ".$id);

		$this->trigger();
	}

	public function updOptionPosition($id,$val)
	{
		$val = (integer) $val;
		$this->updOptionField($id,'option_position',$val);
	}

	public function updOptionSelected($id,$val)
	{
		$val = (integer) $val;
		$this->updOptionField($id,'option_selected',$val);
	}

	public function delOption($id=null,$type=null,$post_id=null,$meta=null)
	{
		$q = array();
		if ($id != '') {
			$id = (integer) $id;
			$q[] = "option_id = '".$id."'";
		}
		if ($type != '') {
			$type = $this->con->escape((string) $type);
			$q[] = "option_type = '".$type."'";
		}
		if ($post_id != '') {
			$post_id = (integer) $post_id;
			$q[] = "post_id = '".$post_id."'";
		}
		if ($meta != '') {
			$meta = $this->con->escape((string) $meta);
			$q[] = "option_meta = '".$meta."'";
		}

		if (empty($q)) {
			throw new Exception(__('Invalid request'));
		}

		$this->con->execute(
			'DELETE FROM '.$this->table.' WHERE '.implode(' AND ',$q)
		);

		$this->trigger();
	}

	private function getOptionContent(&$cur,$option_id)
	{
		$option_content = $cur->option_content;
		$option_content_xhtml = $cur->option_content_xhtml;

		$this->setOptionContent(
			$option_id,$cur->option_format,$cur->option_lang,
			$option_content,$option_content_xhtml
		);

		$cur->option_content = $option_content;
		$cur->option_content_xhtml = $option_content_xhtml;
	}

	public function setOptionContent($option_id,$format,$lang,&$content,&$content_xhtml)
	{
		if ($format == 'wiki')
		{
			$this->core->initWikiPost();
			if (strpos($lang,'fr') === 0) {
				$this->core->wiki2xhtml->setOpt('active_fr_syntax',1);
			}
		}

		if ($content) {
			$content_xhtml = $this->core->callFormater($format,$content);
			$content_xhtml = $this->core->HTMLfilter($content_xhtml);
		} else {
			$content_xhtml = '';
		}

		# --BEHAVIOR-- coreAfterPostOptionContentFormat
		$this->core->callBehavior('coreAfterPostOptionContentFormat',array(
			'content' => &$content,
			'content_xhtml' => &$content_xhtml
		));
	}
}
?>