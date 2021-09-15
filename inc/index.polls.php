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

if (!defined('DC_CONTEXT_ADMIN')){return;}

# Polls lists
class adminPollList extends adminGenericList
{
	public function pollDisplay($page,$nb_per_page,$enclose_block='')
	{
		$echo = '';
		if ($this->rs->isEmpty())
		{
			$echo .= '<p><strong>'.__('No poll').'</strong></p>';
		}
		else
		{
			$this->factory = new pollsFactory($this->core);

			$pager = new pager($page,$this->rs_count,$nb_per_page,10);
			$pager->html_prev = $this->html_prev;
			$pager->html_next = $this->html_next;
			$pager->var_page = 'page';

			$html_block =
			'<table class="clear">'.
			'<tr>'.
			'<th colspan="2">'.__('Title').'</th>'.
			'<th>'.__('Date').'</th>'.
			'<th>'.__('Author').'</th>'.
			'<th class="nowrap">'.__('Related posts').'</th>'.
			'<th>'.__('Votes').'</th>'.
			'<th>'.__('Status').'</th>'.
			'<tr>'.
			'</tr>%s</table>';

			if ($enclose_block) {
				$html_block = sprintf($enclose_block,$html_block);
			}

			$echo .= '<p>'.__('Page(s)').' : '.$pager->getLinks().'</p>';

			$blocks = explode('%s',$html_block);

			$echo .= $blocks[0];

			while ($this->rs->fetch())
			{
				$echo .= $this->pollLine();
			}

			$echo .= $blocks[1];

			$echo .= '<p>'.__('Page(s)').' : '.$pager->getLinks().'</p>';
		}
		return $echo;
	}

	private function pollLine()
	{
		$img = '<img alt="%1$s" title="%1$s" src="images/%2$s" />';
		$p_img = '<img alt="%1$s" title="%1$s" src="index.php?pf=pollsFactory/inc/img/%2$s" />';

		switch ($this->rs->post_status) {
			case 1:
				$status = sprintf($img,__('published'),'check-on.png');
				break;
			case 0:
				$status = sprintf($img,__('unpublished'),'check-off.png');
				break;
			case -1:
				$status = sprintf($img,__('scheduled'),'scheduled.png');
				break;
			case -2:
				$status = sprintf($img,__('pending'),'check-wrn.png');
				break;
		}

		$opened = !$this->rs->post_open_tb ? 
			sprintf($img,__('closed'),'locker.png') : 
			'';

		$selected = $this->rs->post_selected ? 
			sprintf($img,__('selected'),'selected.png') : 
			'';

		$style = $this->rs->post_status < 1 || !$this->rs->post_open_tb ?
			' offline' : 
			'';

		$results = $this->factory->countVotes($this->rs->post_id);
		
		$rel_params['option_type'] = 'pollspost';
		$rel_params['option_meta'] = $this->rs->post_id;
		$related = $this->factory->getOptions($rel_params,true)->f(0);

		$res = 
		'<tr class="line'.$style.'">'.
		'<td class="nowrap">'.form::checkbox(array('entries[]'),$this->rs->post_id).' </td>'.
		'<td class="maximal"><a href="plugin.php?p=pollsFactory&amp;tab=poll&amp;id='.$this->rs->post_id.'" title="'.__('Edit poll').'">'.
		html::escapeHTML($this->rs->post_title).'</a></td>'.
		'<td class="nowrap">'.dt::dt2str(__('%Y-%m-%d %H:%M'),$this->rs->post_dt,$this->rs->core->auth->getInfo('user_tz')).'</td>'.
		'<td class="nowrap">'.$this->rs->user_id.'</td>'.
		'<td class="nowrap">'.$related.'</td>'.
		'<td class="nowrap status">';
		if ($results) {
			$res .= 
			'<a href="plugin.php?p=pollsFactory&amp;tab=result&amp;poll_id='.$this->rs->post_id.'" title="'.__('View results of this poll').'">'.
			'<img src="index.php?pf=pollsFactory/inc/img/icon-result.png" alt="'.__('View results of this poll').'" /></a>';
		}
		$res .= '&nbsp;'.$results.
		'</td>'.
		'<td class="nowrap status">'.$status.' '.$opened.' '.$selected.'</td>'.
		'</tr>';

		return $res;
	}
}
# Actions
if (!empty($action) && !empty($_POST['entries']))
{
	if (isset($_POST['redir']) && strpos($_POST['redir'],'://') === false) {
		$redir = sprint($_POST['redir'],$action);
	}
	else {
		$redir = $p_url.'&tab=polls&msg='.$action;
	}

	foreach ($_POST['entries'] as $k => $v) {
		$entries[$k] = (integer) $v;
	}
	
	$params['sql'] = 'AND P.post_id IN('.implode(',',$entries).') ';
	$params['post_type'] = 'pollsfactory';
	$params['no_content'] = true;

	if (isset($_POST['post_type'])) {
		$params['post_type'] = $_POST['post_type'];
	}
	
	$posts = $core->blog->getPosts($params);
	
	# --BEHAVIOR-- adminPollsFactoryActions
	$core->callBehavior('adminPollsFactoryActions',$core,$posts,$action,$redir);
	
	if (preg_match('/^(publish|unpublish|schedule|pending)$/',$action))
	{
		switch ($action) {
			case 'unpublish' : $status = 0; break;
			case 'pending' : $status = -2; break;
			case 'schedule' : $status = -1; break;
			default : $status = 1; break;
		}
		
		try
		{
			while ($posts->fetch()) {
				$core->blog->updPostStatus($posts->post_id,$status);
			}
			http::redirect($redir);
		}
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}
	elseif ($action == 'selected' || $action == 'unselected')
	{
		try
		{
			while ($posts->fetch()) {
				$core->blog->updPostSelected($posts->post_id,$action == 'selected');
			}
			http::redirect($redir);
		}
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}
	elseif ($action == 'open' || $action == 'close')
	{
		try
		{
			while ($posts->fetch()) {
				$factory->updPostOpened($posts->post_id,$action == 'open');
			}
			http::redirect($redir);
		}
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}
	elseif ($action == 'delete')
	{
		try
		{
			while ($posts->fetch()) {
				if ($posts->isDeletable()) {

					# --BEHAVIOR-- adminBeforePollsFactoryDelete
					$core->callBehavior('adminBeforePollsFactoryDelete',$posts->post_id);				

					$core->blog->delPost($posts->post_id);
				}
			}
			http::redirect($redir);
		}
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}
}


# Getting authors
try {
	$users = $core->blog->getPostsUsers('pollsfactory');
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

# Getting langs
try {
	$langs = $core->blog->getLangs(array('post_type'=>'pollsfactory'));
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}

#Combos 
$users_combo = $categories_combo = array();
$users_combo['-'] = $categories_combo['-'] = '';
while ($users->fetch())
{
	$user_cn = dcUtils::getUserCN($users->user_id,$users->user_name,
	$users->user_firstname,$users->user_displayname);
	
	if ($user_cn != $users->user_id) {
		$user_cn .= ' ('.$users->user_id.')';
	}
	
	$users_combo[$user_cn] = $users->user_id; 
}

$lang_combo['-'] = '';
while ($langs->fetch()) {
	$lang_combo[$langs->post_lang] = $langs->post_lang;
}

$status_combo = array('-' => '');
foreach ($core->blog->getAllPostStatus() as $k => $v) {
	$status_combo[$v] = (string) $k;
}
$selected_combo = array(
	'-' => '',
	__('selected') => '1',
	__('not selected') => '0'
);
$opened_combo = array(
	'-' => '',
	__('opened') => '1',
	__('closed') => '0'
);
$sortby_combo = array(
	__('Date') => 'post_dt',
	__('Title') => 'post_title',
	__('Author') => 'user_id',
	__('Status') => 'post_status',
	__('Selected') => 'post_selected'
);
$order_combo = array(
	__('Descending') => 'desc',
	__('Ascending') => 'asc'
);

$combo_action = array();
if ($core->auth->check('publish,contentadmin',$core->blog->id))
{
	$combo_action[__('Status')][__('Publish')] = 'publish';
	$combo_action[__('Status')][__('Unpublish')] = 'unpublish';
	$combo_action[__('Status')][__('Schedule')] = 'schedule';
	$combo_action[__('Status')][__('Mark as pending')] = 'pending';
	$combo_action[__('Votes')][__('Open voting')] = 'open';
	$combo_action[__('Votes')][__('Close voting')] = 'close';
}
$combo_action[__('Mark')][__('Mark as selected')] = 'selected';
$combo_action[__('Mark')][__('Mark as unselected')] = 'unselected';
if ($core->auth->check('delete,contentadmin',$core->blog->id))
{
	$combo_action[__('Delete')][__('Delete')] = 'delete';
}

# Filters
$user_id = !empty($_GET['user_id']) ?	$_GET['user_id'] : '';
$lang = !empty($_GET['lang']) ?		$_GET['lang'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$selected = isset($_GET['selected']) ?	$_GET['selected'] : '';
$opened = isset($_GET['opened']) ?	$_GET['opened'] : '';
$sortby = !empty($_GET['sortby']) ?	$_GET['sortby'] : 'post_dt';
$order = !empty($_GET['order']) ?		$_GET['order'] : 'desc';

$page = !empty($_GET['page']) ? (integer) $_GET['page'] : 1;
$nb_per_page =  30;

if (!empty($_GET['nb']) && (integer) $_GET['nb'] > 0) {
	if ($nb_per_page != $_GET['nb']) {
		$show_filters = true;
	}
	$nb_per_page = (integer) $_GET['nb'];
}

$params['post_type'] = 'pollsfactory';
$params['limit'] = array((($page-1)*$nb_per_page),$nb_per_page);

# - User filter
if ($user_id !== '' && in_array($user_id,$users_combo)) {
	$params['user_id'] = $user_id;
	$show_filters = true;
}
# - Lang filter
if ($lang !== '' && in_array($lang,$lang_combo)) {
	$params['post_lang'] = $lang;
	$show_filters = true;
}
# - Status filter
if ($status !== '' && in_array($status,$status_combo)) {
	$params['post_status'] = $status;
	$show_filters = true;
}
# - Selected filter
if ($selected !== '' && in_array($selected,$selected_combo)) {
	$params['post_selected'] = $selected;
	$show_filters = true;
}
# - Opened filter
if ($opened !== '' && in_array($opened,$opened_combo)) {
	$params['sql'] = 'AND post_open_tb = '.$opened.' ';
	$show_filters = true;
}
# - Sortby and order filter
if ($sortby !== '' && in_array($sortby,$sortby_combo)) {
	if ($order !== '' && in_array($order,$order_combo)) {
		$params['order'] = $sortby.' '.$order;
	}
	
	if ($sortby != 'post_dt' || $order != 'desc') {
		$show_filters = true;
	}
}

# Get polls
try {
	$polls = $core->blog->getPosts($params);
	$counter = $core->blog->getPosts($params,true);
	$polls_list = new adminPollList($core,$polls,$counter->f(0));
} catch (Exception $e) {
	$core->error->add($e->getMessage());
}


/*
 * Display
 */

if (!$show_filters) {
	$header .= dcPage::jsLoad('js/filter-controls.js');
}

echo '
<html>
<head><title>'.__('Polls manager').'</title>'.$header.'</head>
<body>'.$msg.'
<h2>'.html::escapeHTML($core->blog->name).
' &rsaquo; '.__('Polls').
' - <a class="button" href="'.$p_url.'&amp;tab=poll">'.__('New poll').'</a>'.
'</h2>';

if (!$show_filters) {
	echo '<p><a id="filter-control" class="form-control" href="#">'.
	__('Filters').'</a></p>';
}

echo 
'<form action="'.$p_url.'&amp;tab=polls" method="get" id="filters-form">'.
'<fieldset><legend>'.__('Filters').'</legend>'.
'<div class="three-cols">'.
'<div class="col">'.
'<label>'.__('Status:').
form::combo('status',$status_combo,$status).'</label> '.
'<label>'.__('Selected:').
form::combo('selected',$selected_combo,$selected).'</label> '.
'<label>'.__('Opened:').
form::combo('opened',$opened_combo,$opened).'</label> '.
'</div>'.

'<div class="col">'.
'<label>'.__('Author:').
form::combo('user_id',$users_combo,$user_id).'</label> '.
'<label>'.__('Lang:').
form::combo('lang',$lang_combo,$lang).'</label> '.
'</div>'.

'<div class="col">'.
'<p><label>'.__('Order by:').
form::combo('sortby',$sortby_combo,$sortby).'</label> '.
'<label>'.__('Sort:').
form::combo('order',$order_combo,$order).'</label></p>'.
'<p><label class="classic">'.form::field('nb',3,3,$nb_per_page).' '.
__('Polls per page').'</label> '.
'<input type="submit" value="'.__('filter').'" />'.
form::hidden(array('p'),'pollsFactory').
form::hidden(array('tab'),'polls').
'</p>'.
'</div>'.
'</div>'.
'<br class="clear" />'. //Opera sucks
'</fieldset>'.
'</form>'.
$polls_list->pollDisplay($page,$nb_per_page,
	'<form action="plugin.php" method="post" id="form-entries">'.
	'%s'.
	'<div class="two-cols">'.
	'<p class="col checkboxes-helpers"></p>'.
	'<p class="col right">'.__('Selected polls action:').' '.
	form::combo('action',$combo_action).
	'<input type="submit" value="'.__('ok').'" /></p>'.
	form::hidden(array('user_id'),$user_id).
	form::hidden(array('lang'),$lang).
	form::hidden(array('status'),$status).
	form::hidden(array('selected'),$selected).
	form::hidden(array('sortby'),$sortby).
	form::hidden(array('order'),$order).
	form::hidden(array('page'),$page).
	form::hidden(array('nb'),$nb_per_page).
	form::hidden(array('p'),'pollsFactory').
	form::hidden(array('tab'),'polls').
	$core->formNonce().
	'</div>'.
	'</form>'
);

dcPage::helpBlock('pollsFactory');
echo $footer.'</body></html>';
?>