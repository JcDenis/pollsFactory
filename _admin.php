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

require_once dirname(__FILE__).'/_widgets.php';

$_menu['Blog']->addItem(
	__('Polls manager'),
	'plugin.php?p=pollsFactory','index.php?pf=pollsFactory/icon.png',
	preg_match('/plugin.php\?p=pollsFactory(&.*)?$/',$_SERVER['REQUEST_URI']),
	$core->auth->check('admin',$core->blog->id)
);

# Posts actions
if ($core->blog->settings->pollsFactory->pollsFactory_active 
 && $core->auth->check('admin',$core->blog->id))
{
	$core->addBehavior('adminPostFormSidebar',array('adminPollsFactory','adminPostFormSidebar'));
	$core->addBehavior('adminPageFormSidebar',array('adminPollsFactory','adminPostFormSidebar'));
	$core->addBehavior('adminGalleryFormSidebar',array('adminPollsFactory','adminPostFormSidebar'));

	$core->addBehavior('adminPostHeaders',array('adminPollsFactory','adminPostHeaders'));
	$core->addBehavior('adminPageHeaders',array('adminPollsFactory','adminPostHeaders'));
	$core->addBehavior('adminGalleryHeaders',array('adminPollsFactory','adminPostHeaders'));

	$core->addBehavior('adminAfterPostCreate',array('adminPollsFactory','adminAfterPostCreate'));
	$core->addBehavior('adminAfterPageCreate',array('adminPollsFactory','adminAfterPostCreate'));
	$core->addBehavior('adminAfterGalleryCreate',array('adminPollsFactory','adminAfterPostCreate'));

	$core->addBehavior('adminAfterPostUpdate',array('adminPollsFactory','adminAfterPostUpdate'));
	$core->addBehavior('adminAfterPageUpdate',array('adminPollsFactory','adminAfterPostUpdate'));
	$core->addBehavior('adminAfterGalleryUpdate',array('adminPollsFactory','adminAfterPostUpdate'));

	$core->addBehavior('adminPostsActionsCombo',array('adminPollsFactory','adminPostsActionsCombo'));
	$core->addBehavior('adminPagesActionsCombo',array('adminPollsFactory','adminPostsActionsCombo'));
	$core->addBehavior('adminGalleriesActionsCombo',array('adminPollsFactory','adminPostsActionsCombo'));

	$core->addBehavior('adminPostsActionsContent',array('adminPollsFactory','adminPostsActionsContent'));
	// plugin pages is common with post
	// plugin gallery is common with post

	$core->addBehavior('adminPostsActions',array('adminPollsFactory','adminPostsActions'));
	$core->addBehavior('adminPagesActions',array('adminPollsFactory','adminPostsActions'));
	$core->addBehavior('adminGalleriesActions',array('adminPollsFactory','adminPostsActions'));
}
$core->addBehavior('adminBeforePostDelete',array('adminPollsFactory','adminBeforePostDelete'));
$core->addBehavior('adminBeforePageDelete',array('adminPollsFactory','adminBeforePostDelete'));
// plugin gallery has no delete behavoir: used adminGalleriesActions

# Extra poll
$core->addBehavior('adminBeforePollsFactoryCreate',array('adminPollsFactory','adminBeforePollSave'));
$core->addBehavior('adminBeforePollsFactoryUpdate',array('adminPollsFactory','adminBeforePollSave'));
$core->addBehavior('adminBeforePollsFactoryDelete',array('adminPollsFactory','adminBeforePollDelete'));

# Rest methods
$core->rest->addFunction('getPollsOfPost',array('pollsFactoryRestMethods','getPollsOfPost'));
$core->rest->addFunction('getPostsOfPoll',array('pollsFactoryRestMethods','getPostsOfPoll'));
$core->rest->addFunction('getOtherPolls',array('pollsFactoryRestMethods','getOtherPolls'));
$core->rest->addFunction('addPollPost',array('pollsFactoryRestMethods','addPollPost'));
$core->rest->addFunction('removePollPost',array('pollsFactoryRestMethods','removePollPost'));

# Admin posts/polls actions behaviors
class adminPollsFactory
{
	# Check extra field for post to poll conversion
	public static function adminBeforePollSave(&$cur,$post_id=null)
	{
		if ($cur->post_type != 'pollsfactory') return;

		if ($cur->post_dt === '') {
			$offset = dt::getTimeOffset($GLOBALS['core']->auth->getInfo('user_tz'));
			$now = time() + $offset;
			$cur->post_dt = date('Y-m-d H:i:00',$now);
		}
		if ($cur->post_content === '') {
			$cur->post_content = null;
		}
	}

	# Delete options related to a poll
	public static function adminBeforePollDelete($post_id)
	{
		global $core;
		$id = (integer) $post_id;

		$factory = new pollsFactory($core);
		$factory->delOption(null,'pollsquery',$id);
		$factory->delOption(null,'pollsselection',$id);
		$factory->delOption(null,'pollsresponse',$id);
		$factory->delOption(null,'pollspost',null,$id);
	}

	# List of polls link to a post in post page sidabar
	public static function adminPostFormSidebar($post)
	{
		# Always display something in order to place js features
		if ($post === null ) {
			echo '<div id="pollsfactory-form"></div>';
		}
		else {
			global $core;

			$factory = new pollsFactory($core);
			$opts_params['option_type'] = 'pollspost';
			$opts_params['post_id'] = $post->post_id;
			$opts = $factory->getOptions($opts_params);

			echo '<h3>'.__('Polls manager').'</h3><div id="pollsfactory-form">';
			if (!$opts->isEmpty()) {
				echo '<ul>';
				while($opts->fetch()) {
					$poll_params['no_content'] = true;
					$poll_params['post_type'] = 'pollsfactory';
					$poll_params['post_id'] = $opts->option_meta;
					$poll_params['limit'] = 1;
					$poll = $core->blog->getPosts($poll_params);

					if (!$poll->isEmpty())
					{
						echo 
						'<li>'.
						form::hidden(array('oldpollspostlist[]'),$poll->post_id).
						'<label class="classic">'.
						form::checkbox(array('pollspostlist[]'),$poll->post_id,1,'',3).' '.
						$poll->post_title.'</label></li>';
					}
				}
				echo '</ul>';
			}
			echo 
			'<p><a title="'.__('add polls').'" '.
			'href="plugin.php?p=pollsFactory&amp;tab=post&amp;post_id='.
			$post->post_id.'">'.__('add polls').'</a></p></div>';
		}
	}

	# JS of post page
	public static function adminPostHeaders()
	{
		return 
		dcPage::jsLoad('index.php?pf=pollsFactory/js/_post.js').
		"<script type=\"text/javascript\">\n//<![CDATA[\n".
		dcPage::jsVar('pollsFactoryPostEditor.prototype.poll_url','plugin.php?p=pollsFactory&tab=poll&id=').
		dcPage::jsVar('pollsFactoryPostEditor.prototype.text_title',__('Polls manager')).
		dcPage::jsVar('pollsFactoryPostEditor.prototype.text_confirm_remove_poll',__('Are you sure you want to remove this poll?')).
		dcPage::jsVar('pollsFactoryPostEditor.prototype.text_choose_poll',__('Choose from list')).
		dcPage::jsVar('pollsFactoryPostEditor.prototype.text_edit_poll',__('edit poll')).
		dcPage::jsVar('pollsFactoryPostEditor.prototype.text_add_poll',__('add poll')).
		dcPage::jsVar('pollsFactoryPostEditor.prototype.text_remove_poll',__('remove poll')).
		dcPage::jsVar('pollsFactoryPostEditor.prototype.text_no_poll',__('No more poll')).
		"\n//]]>\n".
		"</script>\n".
		'<link rel="stylesheet" type="text/css" href="index.php?pf=pollsFactory/style.css" />';
	}

	# On new post create polls/post relation
	public static function adminAfterPostCreate($cur,$post_id)
	{
		if (empty($_POST['pollspostlist'])) return;

		global $core;
		$factory = new pollsFactory($core);

		$cur = $factory->open();
		foreach($_POST['pollspostlist'] as $k => $poll_id) {
			$cur->clean();
			$cur->option_type = 'pollspost';
			$cur->post_id = $post_id;
			$cur->option_meta = $poll_id;
			$factory->addOption($cur);
		}
	}

	# If javascript is disabled, update polls/post relation
	public static function adminAfterPostUpdate($cur,$post_id)
	{
		if (empty($_POST['oldpollspostlist'])) return;
		$pollentries = !empty($_POST['pollspostlist']) ? $_POST['pollspostlist'] : array();

		global $core;
		$factory = new pollsFactory($core);

		foreach($_POST['oldpollspostlist'] as $k => $poll_id) {
			if (!in_array($poll_id,$pollentries)) {
				$factory->delOption(null,'pollspost',$post_id,$poll_id);
			}
		}
	}

	# Delete relation between post and polls when a post is deleted
	public static function adminBeforePostDelete($post_id)
	{
		if ($post_id === null) return;

		global $core;

		$factory = new pollsFactory($core);
		$factory->delOption(null,'pollspost',$post_id);
	}

	# Actions can be made on posts list
	public static function adminPostsActionsCombo($args)
	{
		global $core;

		if ($core->auth->check('publish,contentadmin',$core->blog->id))
		{
			$args[0][__('Polls manager')][__('Add polls')] = 'addpolls';
			$args[0][__('Polls manager')][__('Remove polls')] = 'removepolls';
			$args[0][__('Polls manager')][__('Open voting')] = 'openpolls';
			$args[0][__('Polls manager')][__('Close voting')] = 'closepolls';
			$args[0][__('Polls manager')][__('Publish')] = 'publishpolls';
			$args[0][__('Polls manager')][__('Unpublish')] = 'unpublishpolls';
			$args[0][__('Polls manager')][__('Mark as pending')] = 'pendingpolls';
		}
		$args[0][__('Polls manager')][__('Mark as selected')] = 'selectedpolls';
		$args[0][__('Polls manager')][__('Mark as unselected')] = 'unselectedpolls';
		if ($core->auth->check('delete,contentadmin',$core->blog->id))
		{
			$args[0][__('Polls manager')][__('Delete')] = 'deletepolls';
		}
	}

	# Advanced option on posts list actions
	public static function adminPostsActionsContent($core,$action,$hidden_fields)
	{
		$entries = is_array($hidden_fields['entries']) ? $hidden_fields['entries'] : array();
		$msg = $c = '';
		$factory = new pollsFactory($core);

		switch($action) {

			# Add polls to selected entries
			case 'addpolls':

			$polls_params['post_type'] = 'pollsfactory';
			$polls = $core->blog->getPosts($polls_params);

			while($polls->fetch())
			{
				$c .= '<li><label class="classic">'.form::checkbox(array('pollentries[]'),$polls->post_id,0).' '.$polls->post_title.'</label></li>';
			}
			if (empty($c)) {
				$msg = '<p>'.__('There is no polls').'</p>';
			}
			else {
				$c = '<h2>'.__('add polls to selected entries').'</h2><ul>'.$c.'</ul>';
			}
			break;
			
			# Remove polls from selected entries
			case 'removepolls':

			$rels_params = array();
			$rels_params['option_type'] = 'pollspost';
			$rels_params['post_id'] = $entries;
			$rels = $factory->getOptions($rels_params);

			while($rels->fetch())
			{
				$post_params['post_id'] = $rels->post_id;
				$post_params['no_content'] = true;
				$post_params['limit'] = 1;
				$post = $core->blog->getPosts($post_params);

				$poll_params['post_type'] = 'pollsfactory';
				$poll_params['post_id'] = $rels->option_meta;
				$poll_params['no_content'] = true;
				$poll_params['limit'] = 1;
				$poll = $core->blog->getPosts($poll_params);

				if (!$post->isEmpty() && !$poll->isEmpty()) {
					$c .= '<li><label class="classic">'.form::checkbox(array('pollentries['.$post->post_id.']'),$poll->post_id,0).' '.$post->post_title.' : '.$poll->post_title.'</label></li>';
				}
			}
			if (empty($c)) {
				$msg = '<p>'.__('There is no poll for selected entries').'</p>';
			}
			else {
				$c = '<h2>'.__('select polls to remove').'</h2><ul>'.$c.'</ul>';
			}
			break;
			
			# Publish polls related to selected entries
			case 'publishpolls':

			$rels_params = array();
			$rels_params['option_type'] = 'pollspost';
			$rels_params['post_id'] = $entries;
			$rels_params['sql'] = 'GROUP BY option_meta ';
			$rels_params['order'] = 'option_meta ASC';
			$rels = $factory->getOptions($rels_params);

			while($rels->fetch())
			{
				$poll_params['post_type'] = 'pollsfactory';
				$poll_params['post_id'] = $rels->option_meta;
				$poll_params['sql'] = "AND post_status != '1' ";
				$poll_params['no_content'] = true;
				$poll_params['limit'] = 1;
				$poll = $core->blog->getPosts($poll_params);

				if (!$poll->isEmpty()) {
					$c .= '<li><label class="classic">'.form::checkbox(array('pollentries[]'),$poll->post_id,0).' '.$poll->post_title.'</label></li>';
				}
			}
			if (empty($c)) {
				$msg = '<p>'.__('There is no poll that can be published for selected entries').'</p>';
			}
			else {
				$c = '<h2>'.__('select polls to publish').'</h2><ul>'.$c.'</ul>';
			}
			break;
			
			# Unpublish polls related to selected entries
			case 'unpublishpolls':

			$rels_params = array();
			$rels_params['option_type'] = 'pollspost';
			$rels_params['post_id'] = $entries;
			$rels_params['sql'] = 'GROUP BY option_meta ';
			$rels_params['order'] = 'option_meta ASC';
			$rels = $factory->getOptions($rels_params);

			while($rels->fetch())
			{
				$poll_params['post_type'] = 'pollsfactory';
				$poll_params['post_id'] = $rels->option_meta;
				$poll_params['sql'] = "AND post_status != '0' ";
				$poll_params['no_content'] = true;
				$poll_params['limit'] = 1;
				$poll = $core->blog->getPosts($poll_params);

				if (!$poll->isEmpty()) {
					$c .= '<li><label class="classic">'.form::checkbox(array('pollentries[]'),$poll->post_id,0).' '.$poll->post_title.'</label></li>';
				}
			}
			if (empty($c)) {
				$msg = '<p>'.__('There is no poll that can be unpublished for selected entries').'</p>';
			}
			else {
				$c = '<h2>'.__('select polls to unpublish').'</h2><ul>'.$c.'</ul>';
			}
			break;
			
			# Mark as pending polls related to selected entries
			case 'pendingpolls':

			$rels_params = array();
			$rels_params['option_type'] = 'pollspost';
			$rels_params['post_id'] = $entries;
			$rels_params['sql'] = 'GROUP BY option_meta ';
			$rels_params['order'] = 'option_meta ASC';
			$rels = $factory->getOptions($rels_params);

			while($rels->fetch())
			{
				$poll_params['post_type'] = 'pollsfactory';
				$poll_params['post_id'] = $rels->option_meta;
				$poll_params['sql'] = "AND post_status != '-2' ";
				$poll_params['no_content'] = true;
				$poll_params['limit'] = 1;
				$poll = $core->blog->getPosts($poll_params);

				if (!$poll->isEmpty()) {
					$c .= '<li><label class="classic">'.form::checkbox(array('pollentries[]'),$poll->post_id,0).' '.$poll->post_title.'</label></li>';
				}
			}
			if (empty($c)) {
				$msg = '<p>'.__('There is no poll that can be marked as pending for selected entries').'</p>';
			}
			else {
				$c = '<h2>'.__('select polls to mark as pending').'</h2><ul>'.$c.'</ul>';
			}
			break;
			
			# Open polls related to selected entries
			case 'openpolls':

			$rels_params = array();
			$rels_params['option_type'] = 'pollspost';
			$rels_params['post_id'] = $entries;
			$rels_params['sql'] = 'GROUP BY option_meta ';
			$rels_params['order'] = 'option_meta ASC';
			$rels = $factory->getOptions($rels_params);

			while($rels->fetch())
			{
				$poll_params['post_type'] = 'pollsfactory';
				$poll_params['post_id'] = $rels->option_meta;
				$poll_params['sql'] = "AND post_open_tb = 0 ";
				$poll_params['no_content'] = true;
				$poll_params['limit'] = 1;
				$poll = $core->blog->getPosts($poll_params);

				if (!$poll->isEmpty()) {
					$c .= '<li><label class="classic">'.form::checkbox(array('pollentries[]'),$poll->post_id,0).' '.$poll->post_title.'</label></li>';
				}
			}
			if (empty($c)) {
				$msg = '<p>'.__('There is no poll that can be opened for selected entries').'</p>';
			}
			else {
				$c = '<h2>'.__('select polls to open').'</h2><ul>'.$c.'</ul>';
			}
			break;
			
			# Close polls related to selected entries
			case 'closepolls':

			$rels_params = array();
			$rels_params['option_type'] = 'pollspost';
			$rels_params['post_id'] = $entries;
			$rels_params['sql'] = 'GROUP BY option_meta ';
			$rels_params['order'] = 'option_meta ASC';
			$rels = $factory->getOptions($rels_params);

			while($rels->fetch())
			{
				$poll_params['post_type'] = 'pollsfactory';
				$poll_params['post_id'] = $rels->option_meta;
				$poll_params['sql'] = "AND post_open_tb = 1 ";
				$poll_params['no_content'] = true;
				$poll_params['limit'] = 1;
				$poll = $core->blog->getPosts($poll_params);

				if (!$poll->isEmpty()) {
					$c .= '<li><label class="classic">'.form::checkbox(array('pollentries[]'),$poll->post_id,0).' '.$poll->post_title.'</label></li>';
				}
			}
			if (empty($c)) {
				$msg = '<p>'.__('There is no poll that can be closed for selected entries').'</p>';
			}
			else {
				$c = '<h2>'.__('select polls to close').'</h2><ul>'.$c.'</ul>';
			}
			break;
			
			# Delete polls related to selected entries
			case 'deletepolls':

			$rels_params = array();
			$rels_params['option_type'] = 'pollspost';
			$rels_params['post_id'] = $entries;
			$rels_params['sql'] = 'GROUP BY option_meta ';
			$rels_params['order'] = 'option_meta ASC';
			$rels = $factory->getOptions($rels_params);

			while($rels->fetch())
			{
				$poll_params['post_type'] = 'pollsfactory';
				$poll_params['post_id'] = $rels->option_meta;
				$poll_params['no_content'] = true;
				$poll_params['limit'] = 1;
				$poll = $core->blog->getPosts($poll_params);

				if (!$poll->isEmpty()) {
					$c .= '<li><label class="classic">'.form::checkbox(array('pollentries[]'),$poll->post_id,0).' '.$poll->post_title.'</label></li>';
				}
			}
			if (empty($c)) {
				$msg = '<p>'.__('There is no poll on selected entries').'</p>';
			}
			else {
				$c = '<h2>'.__('select polls to delete').'</h2><ul>'.$c.'</ul>';
			}
			break;
			
		}

		if (empty($msg) && !empty($c)) {
			echo 
			'<form method="post" action="posts_actions.php">'.
			$c.
			'<p>'.
			$hidden_fields.
			$core->formNonce().
			form::hidden(array('action'),$action).
			'<input type="submit" value="'.__('save').'" /></p>'.
			'</form>';
		}
		elseif(!empty($msg)) {
			echo $msg;
		}
	}

	# Do actions on posts list actions
	public static function adminPostsActions($core,$posts,$action,$redir)
	{
		//special for gallery delete
		if ($action == 'delete' && !$posts->isEmpty()) {
			try {
				while($posts->fetch())
				{
					if ($posts->post_type == 'gal') {
						self::adminBeforePollDelete($posts->post_id);
					}
				}
			}
			catch (Exception $e) {
				$core->error->add($e->getMessage());
			}
			return;
		}

		$pollentries = isset($_POST['pollentries']) && is_array($_POST['pollentries']) ? $_POST['pollentries'] : array();
		foreach ($pollentries as $k => $id) {
			$pollentries[(integer) $k] = (integer) $id;
		}

		if (empty($pollentries)) return;

		try {
			$factory = new pollsFactory($core);

			switch($action) {

				# Add polls to selected entries
				case 'addpolls':
				while ($posts->fetch()) {

					# Add relations selected polls to entries
					$cur = $factory->open();
					foreach($pollentries as $k => $id) {

						# First delete relations between post and polls if exists
						$factory->delOption(null,'pollspost',$posts->post_id,$id);

						$cur->clean();
						$cur->option_type = 'pollspost';
						$cur->post_id = $posts->post_id;
						$cur->option_meta = $id;
						$factory->addOption($cur);
					}
				}
				http::redirect($redir);
				break;

				# Remove selected polls from selected entries
				case 'removepolls':
				foreach($pollentries as $k => $id) {
					$factory->delOption(null,'pollspost',$k,$id);
				}
				http::redirect($redir);
				break;

				# Opened selected polls
				case 'openpolls':
				foreach($pollentries as $k => $id) {
					$factory->updPostOpened($id,1);
				}
				http::redirect($redir);
				break;

				# Closed selected polls
				case 'closepolls':
				foreach($pollentries as $k => $id) {
					$factory->updPostOpened($id,0);
				}
				http::redirect($redir);
				break;

				# Published selected polls
				case 'publishpolls':
				foreach($pollentries as $k => $id) {
					$core->blog->updPostStatus($id,1);
				}
				http::redirect($redir);
				break;

				# Unpublished selected polls
				case 'unpublishpolls':
				foreach($pollentries as $k => $id) {
					$core->blog->updPostStatus($id,0);
				}
				http::redirect($redir);
				break;

				# Marked as pending selected polls
				case 'pendingpolls':
				foreach($pollentries as $k => $id) {
					$core->blog->updPostStatus($id,-2);
				}
				http::redirect($redir);
				break;
			}
		}
		catch (Exception $e) {
			$core->error->add($e->getMessage());
		}
	}
}

# Admin rest methods
class pollsFactoryRestMethods
{
	# Get polls that is not link to a post
	public static function getOtherPolls()
	{
		global $core;

		$rsp = new xmlTag('polls');
		$polls_params['sql'] = '';
		$polls_params['post_type'] = 'pollsfactory';

		# can set it to 0 to have all polls (when post was not created)
		$id = isset($_GET['id']) ? $_GET['id'] : 0;
		$id  = abs((integer) $id);

		# Get existing posts
		if (0 < $id)
		{
			$factory = new pollsFactory($core);
			$opt_params['option_type'] = 'pollspost';
			$opt_params['post_id'] = $id;
			$opts = $factory->getOptions($opt_params);
			while($opts->fetch())
			{
				$polls_params['sql'] .= 'AND P.post_id != '.((integer) $opts->option_meta).' ';
			}
		}

		# Get polls
		$polls = $core->blog->getPosts($polls_params);

		if ($polls->isEmpty()) return $rsp;

		while($polls->fetch())
		{
			$xp = new xmlTag('poll');
			$xp->id = $polls->post_id;
			$xp->CDATA($polls->post_title);
			$rsp->insertNode($xp);
		}

		return $rsp;
	}

	# Get lists of polls related to a post
	public static function getPollsOfPost()
	{
		global $core;

		$id = isset($_GET['id']) ? $_GET['id'] : 0;
		$id  = abs((integer) $id);

		if (1 > $id) {
			throw new Exception(__('No ID given')); 
		}

		$rsp = new xmlTag('polls');
		$factory = new pollsFactory($core);

		$polls_params['option_type'] = 'pollspost';
		$polls_params['post_id'] = $id;
		$polls = $factory->getOptions($polls_params);

		if ($polls->isEmpty()) return $rsp;

		while($polls->fetch())
		{
			$poll_params['no_content'] = true;
			$poll_params['post_type'] = 'pollsfactory';
			$poll_params['post_id'] = $polls->option_meta;
			$poll_params['limit'] = 1;
			$poll = $core->blog->getPosts($poll_params);

			if (!$poll->isEmpty())
			{
				$xp = new xmlTag('poll');
				$xp->id = $poll->post_id;
				$xp->CDATA($poll->post_title);
				$rsp->insertNode($xp);
			}
		}

		return $rsp;
	}

	# Get list of posts related to a poll
	public static function getPostsOfPoll()
	{
		global $core;

		$id = isset($_GET['id']) ? $_GET['id'] : 0;
		$id  = abs((integer) $id);

		if (1 > $id) {
			throw new Exception(__('No ID given')); 
		}

		$rsp = new xmlTag('polls');
		$factory = new pollsFactory($core);

		$posts_params['option_type'] = 'pollspost';
		$posts_params['option_meta'] = $id;
		$posts = $factory->getOptions($posts_params);

		if ($posts->isEmpty()) return $rsp;

		while ($posts->fetch())
		{
			$post_params['no_content'] = true;
			$post_params['post_id'] = $posts->post_id;
			$post_params['post_type'] = '';
			$post_params['limit'] = 1;
			$post = $core->blog->getPosts($post_params);

			if (!$post->isEmpty())
			{
				$xp = new xmlTag('post');
				$xp->id = $post->post_id;
				$xp->url = $core->getPostAdminURL($post->post_type,$post->post_id);
				$xp->type = $post->post_type;
				$xp->CDATA($post->post_title);
				$rsp->insertNode($xp);
			}
		}

		return $rsp;
	}

	# Remove relation between a poll and a post
	public static function removePollPost()
	{
		global $core;

		$post_id = isset($_POST['post_id']) ? $_POST['post_id'] : 0;
		$post_id  = abs((integer) $post_id);
		$poll_id = isset($_POST['poll_id']) ? $_POST['poll_id'] : 0;
		$poll_id  = abs((integer) $poll_id);

		if (1 > $post_id || 1 > $poll_id) {
			throw new Exception(__('No ID given'));
		}

		$rsp = new xmlTag('rsp');
		$factory = new pollsFactory($core);

		$opt_params['option_type'] = 'pollspost';
		$opt_params['post_id'] = $post_id;
		$opt_params['option_meta'] = $poll_id;

		$opts = $factory->getOptions($opt_params);

		if ($opts->isEmpty()) {
			throw new Exception(__('No such relation'));
		}
		$factory->delOption($opts->option_id);

		return $rsp;
	}

	# Add relation between a poll and a post
	public static function addPollPost()
	{
		global $core;

		$post_id = isset($_POST['post_id']) ? $_POST['post_id'] : 0;
		$post_id  = abs((integer) $post_id);
		$poll_id = isset($_POST['poll_id']) ? $_POST['poll_id'] : 0;
		$poll_id  = abs((integer) $poll_id);

		if (1 > $post_id || 1 > $poll_id) {
			throw new Exception(__('No ID given'));
		}

		$rsp = new xmlTag('rsp');

		$factory = new pollsFactory($core);
		$cur = $factory->open();
		$cur->post_id = $post_id;
		$cur->option_type = 'pollspost';
		$cur->option_meta = $poll_id;
		$factory->addOption($cur);
		
		return $rsp;
	}
}
?>