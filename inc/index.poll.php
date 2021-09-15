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

dcPage::check('usage,contentadmin');

$redir_url = $p_url.'&tab=poll';

$post_id = '';
$post_dt = '';
$post_format = $core->auth->getOption('post_format');
$post_url = '';
$post_lang = $core->auth->getInfo('user_lang');
$post_title = '';
$post_excerpt = '';
$post_excerpt_xhtml = '';
$post_content = '';
$post_content_xhtml = '';
$post_status = $core->auth->getInfo('user_post_status');
$post_selected = false;
$post_open_tb = false; //closed poll

$page_title = __('New poll');

$can_view_page = true;
$can_edit_post = $core->auth->check('usage,contentadmin',$core->blog->id);
$can_publish = $core->auth->check('publish,contentadmin',$core->blog->id);
$can_delete = false;

$post_headlink = '<link rel="%s" title="%s" href="'.$p_url.'&amp;tab=poll&amp;id=%s" />';
$post_link = '<a href="'.$p_url.'&amp;tab=poll&amp;id=%s" title="%s">%s</a>';

$next_link = $prev_link = $next_headlink = $prev_headlink = null;

# If user can't publish
if (!$can_publish) {
	$post_status = -2;
}

# Status combo
foreach ($core->blog->getAllPostStatus() as $k => $v) {
	$status_combo[$v] = (string) $k;
}

# Formaters combo
foreach ($core->getFormaters() as $v) {
	$formaters_combo[$v] = $v;
}

# Languages combo
$rs = $core->blog->getLangs(array('order'=>'asc'));
$all_langs = l10n::getISOcodes(0,1);
$lang_combo = array('' => '', __('Most used') => array(), __('Available') => l10n::getISOcodes(1,1));
while ($rs->fetch()) {
	if (isset($all_langs[$rs->post_lang])) {
		$lang_combo[__('Most used')][$all_langs[$rs->post_lang]] = $rs->post_lang;
		unset($lang_combo[__('Available')][$all_langs[$rs->post_lang]]);
	} else {
		$lang_combo[__('Most used')][$rs->post_lang] = $rs->post_lang;
	}
}
unset($all_langs);
unset($rs);


# Get entry informations
if (!empty($_REQUEST['id']))
{
	$params['post_type'] = 'pollsfactory';
	$params['post_id'] = $_REQUEST['id'];
	
	$post = $core->blog->getPosts($params);
	
	if ($post->isEmpty())
	{
		$core->error->add(__('This poll does not exist.'));
		$can_view_page = false;
	}
	else
	{
		$post_id = $post->post_id;
		$post_dt = date('Y-m-d H:i',strtotime($post->post_dt));
		$post_format = $post->post_format;
		$post_url = $post->post_url;
		$post_lang = $post->post_lang;
		$post_title = $post->post_title;
		$post_excerpt = '';
		$post_excerpt_xhtml = '';
		$post_content = $post->post_content;
		$post_content_xhtml = $post->post_content_xhtml;
		$post_status = $post->post_status;
		$post_selected = (boolean) $post->post_selected;
		$post_open_tb = (boolean) $post->post_open_tb;
		
		$can_edit_post = $post->isEditable();
		$can_delete= $post->isDeletable();
		
		$next_rs = $core->blog->getNextPost($post,1);
		$prev_rs = $core->blog->getNextPost($post,-1);
		
		if ($next_rs !== null) {
			$next_link = sprintf($post_link,$next_rs->post_id,
				html::escapeHTML($next_rs->post_title),__('next poll').'&nbsp;&#187;');
			$next_headlink = sprintf($post_headlink,'next',
				html::escapeHTML($next_rs->post_title),$next_rs->post_id);
		}
		
		if ($prev_rs !== null) {
			$prev_link = sprintf($post_link,$prev_rs->post_id,
				html::escapeHTML($prev_rs->post_title),'&#171;&nbsp;'.__('previous poll'));
			$prev_headlink = sprintf($post_headlink,'previous',
				html::escapeHTML($prev_rs->post_title),$prev_rs->post_id);
		}
	}
}

# Format excerpt and content
if (!empty($_POST) && $can_edit_post)
{
	$post_format = $_POST['post_format'];
	$post_excerpt = '';
	$post_content = $_POST['post_content'];
	
	$post_title = $_POST['post_title'];
		
	if (isset($_POST['post_status'])) {
		$post_status = (integer) $_POST['post_status'];
	}
	
	if (empty($_POST['post_dt'])) {
		$post_dt = '';
	} else {
		$post_dt = strtotime($_POST['post_dt']);
		$post_dt = date('Y-m-d H:i',$post_dt);
	}
	
	$post_selected = !empty($_POST['post_selected']);
	$post_open_tb = !empty($_POST['post_open_tb']);
	$post_lang = $_POST['post_lang'];
	
	if (isset($_POST['post_url'])) {
		$post_url = $_POST['post_url'];
	}

	$core->blog->setPostContent(
		$post_id,$post_format,$post_lang,
		$post_excerpt,$post_excerpt_xhtml,$post_content,$post_content_xhtml
	);
}

# Create or update post
if (!empty($_POST) && !empty($_POST['save']) && $can_edit_post)
{
	# remove relation between poll and posts
	if (!empty($_POST['removeentries']))
	{
		try {
			foreach ($_POST['removeentries'] as $k => $v) {
				$removeentries[$k] = (integer) $v;
			}
			$core->con->execute(
				'DELETE FROM '.$core->prefix.'post_option '.
				"WHERE option_meta = ".$post_id." AND option_type = 'pollspost' ".
				'AND '.implode(' OR post_id = ',$removeentries)
			);
		} catch (Exception $e) {
			$core->error->add($e->getMessage());
		}
	}

	$cur = $core->con->openCursor($core->prefix.'post');
	
	$cur->post_type = 'pollsfactory';
	$cur->post_title = $post_title;
	$cur->post_dt = $post_dt ? date('Y-m-d H:i:00',strtotime($post_dt)) : '';
	$cur->post_format = $post_format;
	$cur->post_lang = $post_lang;
	$cur->post_title = $post_title;
	$cur->post_excerpt = '';
	$cur->post_excerpt_xhtml = '';
	$cur->post_content = $post_content;
	$cur->post_content_xhtml = $post_content_xhtml;
	$cur->post_status = $post_status;
	$cur->post_selected = (integer) $post_selected;
	$cur->post_open_tb = (integer) $post_open_tb;
	
	if (isset($_POST['post_url'])) {
		$cur->post_url = $post_url;
	}
	if ($cur->post_content == '') {
		$cur->post_content = ' ';
	}
	
	# Update post
	if ($post_id)
	{
		try
		{
			# --BEHAVIOR-- adminBeforePollsFactoryUpdate
			$core->callBehavior('adminBeforePollsFactoryUpdate',$cur,$post_id);
			
			$core->blog->updPost($post_id,$cur);
			
			# --BEHAVIOR-- adminAfterPollsFactoryUpdate
			$core->callBehavior('adminAfterPollsFactoryUpdate',$cur,$post_id);
			
			http::redirect($p_url.'&tab=poll&id='.$post_id.'&msg=editpoll');
		}
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}
	else
	{
		$cur->user_id = $core->auth->userID();
		
		try
		{
			# --BEHAVIOR-- adminBeforePollsFactoryCreate
			$core->callBehavior('adminBeforePollsFactoryCreate',$cur);
			
			$return_id = $core->blog->addPost($cur);
			
			# --BEHAVIOR-- adminAfterPollsFactoryCreate
			$core->callBehavior('adminAfterPollsFactoryCreate',$cur,$return_id);
			
			http::redirect($p_url.'&tab=poll&id='.$return_id.'&msg=createpoll');
		}
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}
}

# delete post
if (!empty($_POST['delete']) && $can_delete)
{
	try {

		# --BEHAVIOR-- adminBeforePollsFactoryDelete
		$core->callBehavior('adminBeforePollsFactoryDelete',$post_id);

		$core->blog->delPost($post_id);
		http::redirect($p_url.'&tab=polls&msg=deletepoll');
	} catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}


/* DISPLAY
-------------------------------------------------------- */

echo '<html>
<head><title>'.__('Polls manager').'</title>'.$header.
dcPage::jsDatePicker().
dcPage::jsToolBar().
dcPage::jsModal().
dcPage::jsLoad('js/_post.js').
dcPage::jsPageTabs('edit-entry').
dcPage::jsConfirmClose('entry-form').
dcPage::jsLoad('index.php?pf=pollsFactory/js/poll.js').
"<script type=\"text/javascript\">\n//<![CDATA[\n".
dcPage::jsVar('pollsFactoryAddEditor.prototype.text_title',__('Related posts')).
dcPage::jsVar('pollsFactoryAddEditor.prototype.text_remove_post',__('Are you sure you want to remove this post?')).
"\n//]]>\n</script>\n".

# --BEHAVIOR-- adminPollsFactoryHeaders
$core->callBehavior('adminPollsFactoryHeaders').

$next_headlink."\n".$prev_headlink.
'</head>
<body>'.$msg.'
<h2>'.html::escapeHTML($core->blog->name).
' &rsaquo; <a href="'.$p_url.'&amp;tab=polls">'.__('Polls').'</a>';
if ($post_id) {
	$preview_url = $core->blog->url.$core->url->getBase('pollsFactoryPagePreview').'/'.$core->auth->userID().'/'.http::browserUID(DC_MASTER_KEY.$core->auth->userID().$core->auth->getInfo('user_pwd')).'/'.$post->post_url;

	echo ' &rsaquo; '.__('Edit poll').
	' - <a id="poll-preview" href="'.$preview_url.'" class="button nowait">'.__('Preview poll').'</a>'.
	' - <a class="button" href="'.$p_url.'&amp;tab=poll">'.__('New poll').'</a>';
}
else {
	echo ' &rsaquo; '.__('New poll');
}
echo '</h2>';

# XHTML conversion
if (!empty($_GET['xconv']))
{
	$post_excerpt = $post_excerpt_xhtml;
	$post_content = $post_content_xhtml;
	$post_format = 'xhtml';
	
	echo '<p class="message">'.__('Don\'t forget to validate your XHTML conversion by saving your poll.').'</p>';
}
# nav link
if ($post_id)
{
	echo '<p>';
	if ($prev_link) { echo $prev_link; }
	if ($next_link && $prev_link) { echo ' - '; }
	if ($next_link) { echo $next_link; }
	
	# --BEHAVIOR-- adminPollsFactoryNavLinks
	$core->callBehavior('adminPollsFactoryNavLinks',isset($post) ? $post : null);
	
	echo '</p>';
}

# Exit if we cannot view page
if (!$can_view_page) {
	dcPage::helpBlock('core_post');
	dcPage::close();
	exit;
}


/* Post form if we can edit post
-------------------------------------------------------- */
if ($can_edit_post)
{
	echo '<div class="multi-part" title="'.__('Poll').'" id="edit-entry">';
	echo '<form action="'.$p_url.'&amp;tab=poll" method="post" id="entry-form">';
	echo '<div id="entry-sidebar">';
	
	echo	
	'<p><label>'.__('Poll status:').
	form::combo('post_status',$status_combo,$post_status,'',3,!$can_publish).
	'</label></p>'.
	
	'<p><label>'.__('Published on:').
	form::field('post_dt',16,16,$post_dt,'',3).
	'</label></p>'.

	'<p><label>'.__('Text formating:').
	form::combo('post_format',$formaters_combo,$post_format,'',3).
	($post_id && $post_format != 'xhtml' ? '<a href="post.php?id='.$post_id.'&amp;xconv=1">'.__('Convert to XHTML').'</a>' : '').
	'</label></p>'.
	
	'<p><label class="classic">'.form::checkbox('post_selected',1,$post_selected,'',3).' '.
	__('Selected poll').'</label></p>'.
	
	'<p><label class="classic">'.form::checkbox('post_open_tb',1,$post_open_tb,'',3).' '.
	__('Opened poll').'</label></p>'.
	
	'<p><label>'.__('Poll lang:').
	form::combo('post_lang',$lang_combo,$post_lang,'',5).
	'</label></p>'.
	
	'<div class="lockable">'.
	'<p><label>'.__('Basename:').
	form::field('post_url',10,255,html::escapeHTML($post_url),'maximal',3).
	'</label></p>'.
	'<p class="form-note warn">'.
	__('Warning: If you set the URL manually, it may conflict with another poll.').
	'</p>'.
	'</div>';

	# Posts linked to this poll
	$rels_params['option_type'] = 'pollspost';
	$rels_params['option_meta'] = $post_id;
	$rels = $factory->getOptions($rels_params);
	if (!$rels->isEmpty()) {
		$c = '';
		while ($rels->fetch()) {
			$rel_params['no_content'] = true;
			$rel_params['post_id'] = $rels->post_id;
			$rel_params['post_type'] = '';
			$rel_params['limit'] = 1;
			$rel = $core->blog->getPosts($rel_params);
			if (!$rel->isEmpty()) {
				$c .= '<li>'.form::checkbox(array('removeentries[]'),$rel->post_id,0,3).' <a title="'.__('edit entry').'" href="'.$core->getPostAdminURL($rel->post_type,$rel->post_id).'">'.html::escapeHTML($rel->post_title).'</a> ('.__($rel->post_type).')</li>';
			}
		}
		if (!empty($c)) {
			echo 
			'<div id="pollsfactory-entries">'.
			'<h3>'.__('Related posts').'</h3>'.
			'<p class="form-note">'.__('Uncheck post to remove').'</p>'.
			'<ul>'.$c.'</ul>'.
			'</div>';
		}
	}
	
	# --BEHAVIOR-- adminPollsFactoryFormSidebar
	$core->callBehavior('adminPollsFactoryFormSidebar',isset($post) ? $post : null);
	
	echo '</div>';		// End #entry-sidebar
	
	echo '<div id="entry-content"><fieldset class="constrained">';
	
	echo
	'<p class="col"><label class="required" title="'.__('Required field').'">'.__('Title:').
	form::field('post_title',20,255,html::escapeHTML($post_title),'maximal',2).
	'</label></p>'.
	
	'<p class="area"><label for="post_content">'.__('Description:').'</label> '.
	form::textarea('post_content',50,$core->auth->getOption('edit_size'),html::escapeHTML($post_content),'',2).
	'</p>';
	
	# --BEHAVIOR-- adminPollsFactoryForm
	$core->callBehavior('adminPollsFactoryForm',isset($post) ? $post : null);
	
	echo
	'<p>'.
	($post_id ? form::hidden('id',$post_id) : '').
	'<input type="submit" value="'.__('save').' (s)" tabindex="4" '.
	'accesskey="s" name="save" /> '.
	($can_delete ? '<input type="submit" value="'.__('delete').'" name="delete" />' : '').
	$core->formNonce().
	'</p>';
	
	echo '</fieldset></div>';		// End #entry-content
	echo '</form>';
	echo '</div>';

	if ($post_id) {
		echo '<a class="multi-part" href="'.$p_url.'&amp;tab=content&amp;poll_id='.$post->post_id.'" title="'.__('Edit queries').'">'.__('Content').'</a> ';
		$count = $factory->countVotes($post->post_id);
		if ($count) {
			echo '<a class="multi-part" href="'.$p_url.'&amp;tab=result&amp;poll_id='.$post->post_id.'" title="'.__('Show results').'">'.__('Results').'</a> ';
		}
	}
}

dcPage::helpBlock('pollsFactory','core_wiki');
echo $footer.'</body></html>';
?>