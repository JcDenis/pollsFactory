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

# List of polls
$core->addBehavior('initWidgets',array('widgetPollsFactory','adminPollsList'));
# Selected polls
$core->addBehavior('initWidgets',array('widgetPollsFactory','adminPollsSelected'));
# Polls linked to an entry
$core->addBehavior('initWidgets',array('widgetPollsFactory','adminPollsPost'));

class widgetPollsFactory
{
	public static function adminPollsList($w)
	{
		$w->create('pollfactlist',
			__('Polls list'),
			array('widgetPollsFactory','publicPollsList')
		);
		# Title
		$w->pollfactlist->setting('title',
			__('Title:'),__('Polls list'),'text'
		);
		# Limit
		$w->pollfactlist->setting('limit',
			__('Polls limit:'),10,'text'
		);
		# Sort type
		$w->pollfactlist->setting('sortby',
			__('Order by:'),'poll_strdt','combo',
			array(
				__('Date') => 'post_dt',
				__('Title') => 'post_title'
			)
		);
		# Sort order
		$w->pollfactlist->setting('sort',
			__('Sort:'),'desc','combo',
			array(
				__('Ascending') => 'asc',
				__('Descending') => 'desc'
			)
		);
		# Period
		$w->pollfactlist->setting('period',
			__('Vote:'),'desc','combo',
			array(
				__('All') => '-',
				__('Opened') => 'opened',
				__('Closed') => 'closed'
			)
		);
		# Home only
		$w->pollfactlist->setting('homeonly',
			__('Home page only'),1,'check'
		);
	}

	public static function adminPollsPost($w)
	{
		$w->create('pollfactpost',
			__('Polls of an entry'),
			array('widgetPollsFactory','publicPollsPost')
		);
		$w->pollfactpost->setting('title',
			__('Title:'),__('Related polls'),'text'
		);
		$w->pollfactpost->setting('showdesc',
			__('Show description'),0,'check'
		);
		$w->pollfactpost->setting('usegraph',
			__('Use graphic results'),0,'check'
		);
	}

	public static function adminPollsSelected($w)
	{
		$w->create('pollfactselected',
			__('Selected polls'),
			array('widgetPollsFactory','publicPollsSelected')
		);
		$w->pollfactselected->setting('title',
			__('Title:'),__('Selected polls'),'text'
		);
		$w->pollfactselected->setting('limit',
			__('Polls limit:'),1,'text'
		);
		$w->pollfactselected->setting('showdesc',
			__('Show description'),0,'check'
		);
		$w->pollfactselected->setting('usegraph',
			__('Use graphic results'),0,'check'
		);
		$w->pollfactselected->setting('homeonly',
			__('Home page only'),1,'check'
		);
	}

	public static function publicPollsList($w)
	{
		global $core; 

		if (!$core->blog->settings->pollsFactory->pollsFactory_active 
		 || $w->homeonly && $core->url->type != 'default') return;

		$params = array(
			'post_type' => 'pollsfactory',
			'no_content' => true,
			'sql' => ''
		);
		# order
		$params['order'] = in_array($w->sortby,array('post_dt','post_title')) ?
			$w->sortby : 'post_dt ';
		$params['order'] .= $w->sort == 'asc' ? ' asc' : ' desc';
		# limit
		$params['limit'] = abs((integer) $w->limit);
		# period
		if ($w->period == 'opened') {
			$params['sql'] .= 'AND post_open_tb = 1 ';
		}
		elseif ($w->period == 'closed') {
			$params['sql'] .= 'AND post_open_tb = 0 ';
		}

		$polls = $core->blog->getPosts($params);

		if ($polls->isEmpty()) return;

		$res = '';
		while($polls->fetch())
		{
			$res .= '<li><a href="'.$core->blog->url.$core->url->getBase('pollsFactoryPage').'/'.$polls->post_url.'">'.html::escapeHTML($polls->post_title).'</a></li>';
		}
		return 
		'<div class="pollsfactory pollsselected-widget">'.
		($w->title ? '<h2>'.$w->title.'</h2>' : '').
		'<ul>'.$res.'</ul>'.
		'</div>';
	}

	public static function publicPollsPost($w)
	{
		global $core, $_ctx; 

		if (!$core->blog->settings->pollsFactory->pollsFactory_active 
		 || !$_ctx->exists('posts')) return;


		$params['option_type'] = 'pollspost';
		$params['post_id'] = $_ctx->posts->post_id;

		$obj = new pollsfactory($core);
		$rs = $obj->getOptions($params);

		if ($rs->isEmpty()) return;

		$res = '';
		while($rs->fetch())
		{
			$res .= publicPollsFactoryForm($core,$rs->option_meta,true,$w->showdesc,$w->usegraph);
		}
		return 
		'<div class="pollsfactory pollspost-widget">'.
		($w->title ? '<h2>'.$w->title.'</h2>' : '').
		$res.
		'</div>';
	}

	public static function publicPollsSelected($w)
	{
		global $core; 

		if (!$core->blog->settings->pollsFactory->pollsFactory_active 
		 || $w->homeonly && $core->url->type != 'default') return;

		$params['no_content'] = true;
		$params['post_type'] = 'pollsfactory';
		$params['post_selected'] = 1;
		$params['limit'] = abs((integer) $w->limit);
		$params['order'] = 'post_dt ASC ';
		$params['from'] = $core->con->driver() == 'pgsql' ? // postgresql cast compatibility
			 'LEFT JOIN '.$core->prefix.'post_option O ON P.post_id = O.option_meta::bigint ' :
			 'LEFT JOIN '.$core->prefix.'post_option O ON O.option_meta = P.post_id ';
		$params['sql'] = "AND O.option_type = 'pollspost' ";

		$rs = $core->blog->getPosts($params);

		if ($rs->isEmpty()) return;

		$res = '';
		while($rs->fetch())
		{
			$res .= publicPollsFactoryForm($core,$rs->post_id,true,$w->showdesc,$w->usegraph);
		}
		return 
		'<div class="pollsfactory pollsselected-widget">'.
		($w->title ? '<h2>'.$w->title.'</h2>' : '').
		$res.
		'</div>';
	}
}
?>