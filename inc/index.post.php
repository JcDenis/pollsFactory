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

$redir = $hidden_fields = '';
$posts_ids = array();
if (!empty($_GET['post_id'])) {
	$posts_ids[] = (integer) $_GET['post_id'];
	$hidden_fields .= form::hidden(array('entries[]'),$_GET['post_id']);
}
elseif (!empty($_POST['entries'])) {
	foreach($_POST['entries'] as $k => $id) {
		$posts_ids[] = (integer) $id;
		$hidden_fields .= form::hidden(array('entries[]'),$id);
	}
}
if (empty($posts_ids)) {
	$core->error->add(__('no post ID'));
}
$posts = $core->blog->getPosts(array('post_id'=>$posts_ids,'post_type'=>''));

if ($posts->post_type == 'post') {
	$redir = $posts->count() > 1  ? 'posts.php' : 'post.php?id='.$posts->post_id;
}
elseif ($posts->post_type == 'page') {
	$redir = $posts->count() > 1 ? 'plugin.php?p=pages&act=list' : 'plugin.php?p=pages&act=page&id='.$posts->post_id;
}
//todo post gal

# Action
if ($action == 'addpollstoposts' && !empty($_POST['pollspostlist']))
{
	try {
		while ($posts->fetch()) {
			# Delete relations between post and polls
			$core->con->execute(
				'DELETE FROM '.$core->prefix.'post_option '.
				"WHERE option_type = 'pollspost' ".
				"AND post_id = '".$posts->post_id."' "
			);
			# Add relations selected polls to entries
			$cur = $factory->open();
			foreach($_POST['pollspostlist']  as $k => $id) {
				$cur->clean();
				$cur->option_type = 'pollspost';
				$cur->post_id = $posts->post_id;
				$cur->option_meta = $id;
				$factory->addOption($cur);
			}
		}
		http::redirect($_POST['redir']);
	}
	catch (Exception $e) {
		$core->error->add($e->getMessage());
	}
}

# Display
echo '
<html>
<head><title>'.__('Polls manager').'</title>'.$header.'</head>
<body>';

$polls = $core->blog->getPosts(array('post_type'=>'pollsfactory'));
if ($polls->isEmpty()) {
	echo 
	'<p>'.__('There is no polls').'</p>'.
	'<p><a href="'.$redir.'">'.__('go back').'</a></p>';
}
else {
	echo '
	<h2>'.__('Add polls to posts').'</h2>
	<form action="plugin.php" method="post">
	<div class="two-cols">
	<div class="col">
	<h3>'.__('Polls').'</h3>
	<ul>';

	while($polls->fetch()) {
		echo '<li><label class="classic">'.form::checkbox('pollspostlist[]',$polls->post_id,0,'').' '.$polls->post_title.'</label></li>';
	}
	echo '
	</ul>
	</div>
	<div class="col">
	<h3>'.__('Entries').'</h3>
	<ul>';
	while ($posts->fetch()) {
		echo '<li>'.html::escapeHTML($posts->post_title).'</li>';
	}
	echo '
	</ul>
	</div></div>
	<p class="clear"><input type="submit" name="save" value="'.__('save').'" />'.
	form::hidden(array('p'),'pollsFactory').
	form::hidden(array('tab'),'post').
	form::hidden(array('action'),'addpollstoposts').
	form::hidden(array('redir'),$redir).
	$core->formNonce().
	$hidden_fields.'
	</p>
	</form>';
}
dcPage::helpBlock('pollsFactory');
echo $footer.'</body></html>';
?>