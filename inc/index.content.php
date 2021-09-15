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

$poll_id = isset($_REQUEST['poll_id']) ? (integer) $_REQUEST['poll_id'] : -1;
if (empty($poll_id)) $poll_id = -1;
$has_poll = 0 < $poll_id;

$query_id = isset($_REQUEST['query_id']) ? (integer) $_REQUEST['query_id'] : -1;
if (empty($query_id)) $query_id = -1;
$has_query = 0 < $query_id;

$selection_id = isset($_REQUEST['selection_id']) ? (integer) $_REQUEST['selection_id'] : -1;
if (empty($selection_id)) $selection_id = -1;
$has_selection = 0 < $selection_id;

$query_title = isset($_POST['query_title']) ? $_POST['query_title'] : '';
$new_query_desc = isset($_POST['new_query_desc']) ? $_POST['new_query_desc'] : '';
$edit_query_desc = isset($_POST['edit_query_desc']) ? $_POST['edit_query_desc'] : '';
$query_type = isset($_POST['query_type']) ? $_POST['query_type'] : '';

$section = isset($_REQUEST['section']) ? $_REQUEST['section'] : '';

$action_redir = $p_url.'&tab=content&poll_id=%s&query_id=%s&selection_id=%s&msg='.$action.'&section=%s';

if (!$has_poll) {
	$core->error->add('No such poll ID');
}

# Get poll
$poll_params = array();
$poll_params['post_type'] = 'pollsfactory';
$poll_params['post_id'] = $poll_id;
$poll = $core->blog->getPosts($poll_params);
if ($poll->isEmpty()) { $poll_id = -1; $has_poll = false; }

# Get old queries
$queries_params['option_type'] = 'pollsquery';
$queries_params['post_id'] = $poll_id;
$queries_params['order'] = 'option_position ASC ';
$old_queries = $factory->getOptions($queries_params);
$has_old_queries = !$old_queries->isEmpty();

# Delete old queries
if ($has_old_queries && $action == 'deletequery' 
 && !empty($_POST['query_del']) && is_array($_POST['query_del']))
{
	try {
		foreach($_POST['query_del'] as $id)
		{
			# Delete selections
			$del_selections_params['option_type'] = 'pollsselection';
			$del_selections_params['post_id'] = $poll_id;
			$del_selections_params['option_meta'] = $id;
			$del_selections = $factory->getOptions($del_selections_params);
			while($del_selections->fetch()) {
				$factory->delOption($del_selections->option_id);
			}
			# Delete responses
			$del_responses_params['option_type'] = 'pollsresponse';
			$del_responses_params['post_id'] = $poll_id;
			$del_responses_params['option_meta'] = $id;
			$del_responses = $factory->getOptions($del_responses_params);
			while($del_responses->fetch()) {
				$factory->delOption($del_responses->option_id);
			}
			# Delete query
			$factory->delOption($id);
		}

		http::redirect(sprintf($action_redir,$poll_id,'','','query-list'));
	}
	catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

# Reorder old queries
if ($has_old_queries && $action == 'reorderquery')
{
	$order = array();
	# Whitout js
	if (empty($_REQUEST['queries_order_js']) && !empty($_REQUEST['queries_order_html'])) {
		$order = $_REQUEST['queries_order_html'];
		asort($order);
		$order = array_keys($order);
	}
	# With js
	elseif (!empty($_REQUEST['queries_order_js'])) {
		$order = explode(',',$_REQUEST['queries_order_js']);
	}
	# There is something to sort
	if (!empty($order)) {
		# Arrange order
		$new_order = array();
		foreach ($order as $pos => $id) {
			$pos = ((integer) $pos)+1;
			if (!empty($pos) && !empty($id)) {
				$new_order[$pos] = $id;
			}
		}
		try {
			# Update order
			foreach($new_order as $pos => $id) {
				$factory->updOptionPosition($id,$pos);
			}

			http::redirect(sprintf($action_redir,$poll_id,'','','query-list'));
		}
		catch (Exception $e) {
			$core->error->add($e->getMessage());
		}
	}
}

# Create or edit query
if ($action == 'createquery' && $has_poll && !$has_query  
 || $action == 'editquery' && $has_poll && $has_query)
{
	$query_title = isset($_POST['query_title']) ? $_POST['query_title'] : '';
	$query_type = isset($_POST['query_type']) ? $_POST['query_type'] : '';

	try {
		if (empty($query_title)) {
			throw new Exception(__('You must specify query title'));
		}

		$cur = $factory->open();
		$cur->post_id = $poll->post_id;
		$cur->option_title = $query_title;

		if ($action == 'createquery') {

			if (empty($query_type)) {
				throw new Exception(__('You must specify query type'));
			}
			$new_query_desc = isset($_POST['new_query_desc']) ? $_POST['new_query_desc'] : '';

			$cur->option_type = 'pollsquery';
			$cur->option_lang = $poll->post_lang;
			$cur->option_meta = $query_type;
			$cur->option_content = $new_query_desc;
			$cur->option_position = $factory->nextPosition($cur->post_id,null,$cur->option_type);

			$query_id = $factory->addOption($cur);

			if (in_array($query_type,array('field','textarea'))) {
				$cur->clean();
				$cur->post_id = $poll->post_id;
				$cur->option_type = 'pollsselection';
				$cur->option_lang = $poll->post_lang;
				$cur->option_meta = $query_id;
				$cur->option_title = '-';
				$cur->option_position = $factory->nextPosition($cur->post_id,$cur->option_meta,$cur->option_type);

				$factory->addOption($cur);
			}
		}
		else {
			$edit_query_desc = isset($_POST['edit_query_desc']) ? $_POST['edit_query_desc'] : '';
			$cur->option_content = $edit_query_desc;
			
			$factory->updOption($query_id,$cur);
		}

		http::redirect(sprintf($action_redir,$poll_id,'','','query-list'));
	}
	catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

# Get query
$query_params = $queries_params;
$query_params['option_id'] = $query_id;
$query = $factory->getOptions($query_params);
if ($query->isEmpty()) {
	$query_id = -1;
	$has_query = false;
}
else {
	$query_id = $query->option_id;
	$has_query = true;
}

# get old selections
$selections_params['option_type'] = 'pollsselection';
$selections_params['post_id'] = $poll_id;
$selections_params['option_meta'] = $query_id;
$selections_params['order'] = 'option_position ASC ';
$old_selections = $factory->getOptions($selections_params);
$has_old_selections = !$old_selections->isEmpty();

# Delete old selections
if ($has_old_selections && $action == 'deleteselection' 
 && !empty($_POST['selection_del']) && is_array($_POST['selection_del']))
{
	try {
		foreach($_POST['selection_del'] as $id)
		{
			$factory->delOption($id);
		}

		http::redirect(sprintf($action_redir,$poll_id,$query_id,'','selection-list'));
	}
	catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

# Reorder old selections
if ($has_old_selections && $action == 'reorderselection')
{
	$order = array();
	# Whitout js
	if (empty($_REQUEST['selections_order_js']) && !empty($_REQUEST['selections_order_html'])) {
		$order = $_REQUEST['selections_order_html'];
		asort($order);
		$order = array_keys($order);
	}
	# With js
	elseif (!empty($_REQUEST['selections_order_js'])) {
		$order = explode(',',$_REQUEST['selections_order_js']);
	}
	# There is something to sort
	if (!empty($order)) {
		# Arrange order
		$new_order = array();
		foreach ($order as $pos => $id) {
			$pos = ((integer) $pos)+1;
			if (!empty($pos) && !empty($id)) {
				$new_order[$pos] = $id;
			}
		}
		try {
			# Update order
			foreach($new_order as $pos => $id) {
				$factory->updOptionPosition($id,$pos);
			}

			http::redirect(sprintf($action_redir,$poll_id,$query_id,'','selection-list'));
		}
		catch (Exception $e) {
			$core->error->add($e->getMessage());
		}
	}
}

# Create or edit selection
if ($action == 'createselection' && $has_poll && $has_query && !$has_selection
 || $action == 'editselection' && $has_poll && $has_query && $has_selection)
{
	$selection_text = isset($_POST['selection_text']) ? $_POST['selection_text'] : '';
	
	try {
		if (empty($selection_text)) {
			throw new Exception(__('You must specify selection text'));
		}

		$cur = $factory->open();
			$cur->post_id = $poll->post_id;
		$cur->option_title = $selection_text;

		if ($action == 'createselection') {
			$cur->option_type = 'pollsselection';
			$cur->option_lang = $poll->post_lang;
			$cur->option_meta = $query_id;
			$cur->option_position = $factory->nextPosition($cur->post_id,$cur->option_meta,$cur->option_type);
			$selection_id = $factory->addOption($cur);
		}
		else {
			$factory->updOption($selection_id,$cur);
		}

		http::redirect(sprintf($action_redir,$poll_id,$query_id,'','selection-list'));
	}
	catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

# Get selection
$selection_params = $selections_params;
$selection_params['option_id'] = $selection_id;
$selection = $factory->getOptions($selection_params);
if ($selection->isEmpty()) {
	$selection_id = -1;
	$has_selection = false;
}
else {
	$selection_id = $selection->option_id;
	$has_selection = true;
}

# Remove entries from poll
if ($action == 'removeentries' && !empty($_POST['entries']) && $has_poll)
{
	try {
		foreach($_POST['entries'] as $k => $id)
		{
			$factory->delPost($id,$poll_id);
		}
		http::redirect(sprintf($action_redir,$poll_id,$query_id,$selection_id,$section));
	}
	catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}


/*
 * Display
 */

echo '<html>
<head><title>'.__('Polls manager').'</title>'.$header.
dcPage::jsModal().
dcPage::jsToolMan().
dcPage::jsToolBar().
dcPage::jsDatePicker().
dcPage::jsModal().
dcPage::jsLoad('index.php?pf=pollsFactory/js/content.js').
"<script type=\"text/javascript\">\n//<![CDATA[\n".
dcPage::jsVar('jcToolsBox.prototype.section',$section).
"\n//]]>\n</script>\n".
'</head>
<body>'.$msg.'
<h2>'.html::escapeHTML($core->blog->name).
' &rsaquo; <a href="'.$p_url.'&amp;tab=polls">'.__('Polls').'</a>'.
' &rsaquo; '.__('Edit content');

if ($has_poll) {
	$preview_url =
	$core->blog->url.$core->url->getBase('pollsFactoryPagePreview').'/'.
	$core->auth->userID().'/'.
	http::browserUID(DC_MASTER_KEY.$core->auth->userID().$core->auth->getInfo('user_pwd')).
	'/'.$poll->post_url;
	echo ' - <a id="poll-preview" href="'.$preview_url.'" class="button nowait">'.__('Preview poll').'</a>';
}

echo 
' - <a class="button" href="'.$p_url.'&amp;tab=poll">'.__('New poll').'</a>'.
'</h2>';

if ($has_poll) {
	echo '<p><a href="'.$core->getPostAdminURL($poll->post_type,$poll->post_id).'">&#171; '.
		sprintf(__('Back to "%s"'),html::escapeHTML($poll->post_title)).'</a></p>';
}

echo
'<h3>'.html::escapeHTML($poll->post_title);
if ($has_query) { echo ' &rsaquo; '.html::escapeHTML($query->option_title); }
if ($has_selection) { echo ' &rsaquo; '.html::escapeHTML($selection->option_title); }
echo '</h3><div id="poll-content">';

# List old queries
if ($has_old_queries)
{
	$lis = '';
	$i = 1;
	while ($old_queries->fetch())
	{
		$img = '<img alt="%1$s" title="%1$s" src="images/%2$s" /> %3$s';
		$query_count = $factory->getOptions(array('option_type'=>'pollsselection','option_meta'=>$old_queries->option_id),true)->f(0);
		$query_complete = $query_count ? 
			sprintf($img,__('complete'),'check-on.png',$query_count) :
			sprintf($img,__('uncomplete'),'check-off.png',$query_count);
		
		$lis .=
		'<tr class="line" id="l_'.$old_queries->option_id.'">'.
		'<td class="handle minimal">'.form::field(array('queries_order_html['.$old_queries->option_id.']'),2,5,$old_queries->option_position,'',2).'</td>'.
		'<td class="minimal">
		'.form::checkbox(array('query_del[]'),$old_queries->option_id,0,'',2).'
		</td>
		<td>'.$old_queries->option_position.' <a href="'.sprintf($action_redir,$poll_id,$old_queries->option_id,'','query-edit').'" title="'.__('Edit query').'">'.html::escapeHTML($old_queries->option_title).'</a></td>
		<td>'.array_search($old_queries->option_meta,$factory->getQueryTypes()).' </td>
		<td>'.$query_complete.'</td>
		</tr>';
		$i++;
	}
	echo addpollform(
		'query-list',__('Queries'),$poll_id,$query_id,$selection_id,
		__('Action on existing queries:'),array(
			__('reorder queries') => 'reorderquery',
			__('delete queries') => 'deletequery'
		),
//		($has_query ? '<p><a href="'.sprintf($action_redir,$poll_id,'','','query-new').'" title="'.__('New query').'">'.__('New query').'</a></p>' : '').
		'<table class="maximal dragable">'.
		'<thead><tr>'.
		'<th colspan="3">'.__('Query').form::hidden('queries_order_js','').'</th>'.
		'<th>'.__('Type').'</th>'.
		'<th>'.__('selections').'</th>'.
		'</tr></thead>'.
		'<tbody id="queries-list">'.
		$lis.
		'</tbody>'.
		'</table>',
		2
	);
}

# New query
if ($has_poll)//show all// && !$has_query)
{
	echo addpollform(
		'query-new',__('New query'),$poll_id,'','',
		__('save'),'createquery','
		<p><label class="required" for="query_title">'.__('Title:').'</label>'.
		form::field('query_title',20,255,html::escapeHTML($query_title),'maximal',2).'</p>
		<p class="area" id="new-query-area"><label for="new_query_desc">'.__('Description:').'</label>'.
		form::textarea(array('new_query_desc','query_desc'),50,5,html::escapeHTML($new_query_desc),'maximal '.$poll->post_format,2).'</p>
		<p class="area"><label for="query_type">'.__('Type:').'</label>'.
		form::combo('query_type',$factory->getQueryTypes(),$query_type,'',2).'</p>',
		2
	);
}
# Edit query
if ($has_poll && $has_query)
{
	echo addpollform(
		'query-edit',__('Edit query'),$poll_id,$query_id,'',
		__('edit'),'editquery','
		<p><label class="required" for="query_title">'.__('Title:').'</label>'.
		form::field('query_title',20,255,html::escapeHTML($query->option_title),'maximal',2).form::hidden(array('query_id'),$query->option_id).'</p>
		<p class="area" id="edit-query-area"><label for="edit_query_desc">'.__('Description:').'</label>'.
		form::textarea(array('edit_query_desc','query_desc'),50,5,html::escapeHTML($query->option_content),'maximal '.$poll->post_format,2).'</p>
		<p class="area"><label for="query_type">'.__('Type:').'</label>'.
		form::combo('query_type_disabled',$factory->getQueryTypes(),$query->option_meta,'',2,true).'</p>',
		2
	);
}

# List old selections
if ($has_old_selections && !in_array($query->option_meta,array('field','textarea')))
{
	$lis = '';
	$i = 1;
	while ($old_selections->fetch())
	{
		$lis .=
		'<tr class="line" id="l_'.$old_selections->option_id.'">'.
		'<td class="handle minimal">'.form::field(array('selections_order_html['.$old_selections->option_id.']'),2,5,$old_selections->option_position,'',2).'</td>'.
		'<td class="minimal">
		'.form::checkbox(array('selection_del[]'),$old_selections->option_id,0,'',2).'
		</td>
		<td>'.$old_selections->option_position.' <a href="'.sprintf($action_redir,$poll_id,$query_id,$old_selections->option_id,'selection-edit').'" title="'.__('Edit this selection').'">'.html::escapeHTML($old_selections->option_title).'</a></td>
		</tr>';
		$i++;
	}
	echo addpollform(
		'selection-list',__('selections'),$poll_id,$query_id,$selection_id,
		__('Action on existing selections:'),array(
			__('reorder selections') => 'reorderselection',
			__('delete selections') => 'deleteselection'
		),
//		($has_selection ? '<p><a href="'.sprintf($action_redir,$poll_id,$query_id,'','selection-new').'" title="'.__('New selection').'">'.__('New selection').'</a></p>' : '').
		'<table class="maximal dragable">'.
		'<thead><tr>'.
		'<th colspan="3">'.__('selection').form::hidden('selections_order_js','').'</th>'.
		'</tr></thead>'.
		'<tbody id="selections-list">'.
		$lis.
		'</tbody>'.
		'</table>',
		2
	);
}

# New selection
if ($has_query && !in_array($query->option_meta,array('field','textarea')))//show all// && !$has_selection)
{
	echo addpollform(
		'selection-new',__('New selection'),$poll_id,$query_id,$selection_id,
		__('save'),'createselection','
		<p><label for="_selection_text">'.__('Text:').'</label>'.
		form::field('selection_text',20,255,'','maximal',2).'</p>',
		2
	);
}

# Edit selection
if ($has_query && $has_selection && !in_array($query->option_meta,array('field','textarea')))
{
	echo addpollform(
		'selection-edit',__('Edit selection'),$poll_id,$query_id,$selection_id,
		__('edit'),'editselection','
		<p><label for="_selection_text">'.__('Text:').'</label>'.
		form::field('selection_text',20,255,html::escapeHTML($selection->option_title),'maximal',2).'</p>',
		2
	);
}
echo '</div>';
dcPage::helpBlock('pollsFactory');
echo $footer.'</body></html>';

function addpollForm($section,$title,$poll_id,$query_id,$selection_id,$submit,$action,$content,$tabindex=2,$accesskey='',$sidebar=false)
{

	if ($sidebar) {
		$h = $title ? '<h3 id="'.$section.'">'.$title.'</h3>%s' : '%s';
	}
	else {
		$id = !is_array($action) ? ' id="'.$action.'" ' : '';
		$h = $title ? '<fieldset id="'.$section.'"><legend'.$id.'>'.$title.'</legend>%s</fieldset>' : '%s';
	}
	$a = $accesskey ? ' accesskey="'.$accesskey.'"' : '';
	$r =  
	'<form method="post" action="plugin.php">'.
	$content.
	'<div class="two-cols">';
	
	if (is_array($action)) {
		$r .= 
		'<p class="col checkboxes-helpers"></p>'.
		'<p class="col right">'.$submit.' '.
		form::combo('action',$action,'',$tabindex).
		'<input type="submit"'.$a.' value="'.__('ok').'" tabindex="'.$tabindex.'"/></p>';
	}
	else {
		$r .= 
		'<p class="col"><input type="submit" name="save"'.$a.' value="'.$submit.'" tabindex="'.$tabindex.'" />'.
		form::hidden(array('action'),$action);
	}
	$r .=
	$GLOBALS['core']->formNonce().
	form::hidden(array('poll_id'),$poll_id).
	form::hidden(array('query_id'),$query_id).
	form::hidden(array('selection_id'),$selection_id).
	form::hidden(array('p'),'pollsFactory').
	form::hidden(array('tab'),'content').
	form::hidden(array('section'),$section).'
	</div>
	</form>';

	return sprintf($h,$r);
}
?>