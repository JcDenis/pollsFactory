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

require_once dirname(__FILE__).'/_widgets.php';

$core->addBehavior('publicEntryBeforeContent',array('publicBehaviorPollsFactory','publicEntryBeforeContent'));
$core->addBehavior('publicEntryAfterContent',array('publicBehaviorPollsFactory','publicEntryAfterContent'));

$core->tpl->addValue('PollTitle',array('publicTplPollsFactory','PollTitle'));
$core->tpl->addValue('PollContent',array('publicTplPollsFactory','PollContent'));
$core->tpl->addValue('PollForm',array('publicTplPollsFactory','PollForm'));

# URL handler
class publicUrlPollsFactory extends dcUrlHandlers
{
	# Poll detail
	public static function pollPage($args)
	{
		if ($args == '') {
			self::p404();
			return;
		}

		$_ctx =& $GLOBALS['_ctx'];
		$core =& $GLOBALS['core'];

		# Plugin not active
		if (!$core->blog->settings->pollsFactory->pollsFactory_active) {
			self::p404();
			return;
		}

		# Submit vote for a poll
		if ($args == 'vote' && !empty($_POST['poll']))
		{
			if (empty($_POST['pollquery']) || !is_array($_POST['pollquery']) || empty($_POST['poll'])) {
				self::p404();
				return;
			}
			$poll_id = (integer) $_POST['poll'];
			$redir = !empty($_POST['redir']) ? $_POST['redir'] : $core->blog->url;

			# Get poll
			try {
				$poll_params = array();
				$poll_params['post_id'] = (integer) $poll_id;
				$poll_params['post_type'] = 'pollsfactory';
				$poll_params['sql'] = 'AND post_open_tb = 1 ';

				$poll = $core->blog->getPosts($poll_params);
			}
			catch (Exception $e) {
				self::p404();
				return;
			}
			if ($poll->isEmpty()) {
				self::p404();
				return;
			}
			try {
				$factory = new pollsFactory($core);
			}
			catch (Exception $e) {
				self::p404();
				return;
			}
			# Check if people already voted
			if ($factory->checkVote($poll->post_id)) {
				http::redirect($redir);
			}
			# Get queries
			try {
				$queries_params['option_type'] = 'pollsquery';
				$queries_params['post_id'] = $poll->post_id;
				$queries = $factory->getOptions($queries_params);
			}
			catch (Exception $e) {
				self::p404();
				return;
			}
			if ($queries->isEmpty()) {
				self::p404();
				return;
			}
			# Default values
			$add = false;
			$rsp = array();
			# Loop through queries
			while ($queries->fetch())
			{
				if (isset($_POST['pollquery'][$queries->option_id]))
				{
					$id = (integer) $queries->option_id;
					switch($queries->option_meta) {

						case 'checkbox':
						if (is_array($_POST['pollquery'][$id])) {
							foreach($_POST['pollquery'][$id] as $k => $v) {
								$rsp[] = array($id,(integer) $v);
								$add = true;
							}
						}
						break;

						case 'radio':
						case 'combo':
						$rsp[] = array($id,(integer) $_POST['pollquery'][$id]);
						$add = true;
						break;

						case 'field':
						case 'textarea':
						$rsp[] = array($id,(string) $_POST['pollquery'][$id]);
						$add = true;
						break;

						default:
						break;
					}
				}
			}
			# Add reponse in database
			if ($add && !empty($rsp)) {
				try {
					# --BEHAVIOR-- publicBeforePollsFactoryVote
					$core->callBehavior('publicBeforePollsFactoryVote',$poll);

					$people_id = $factory->setVote($poll->post_id);
					$cur = $factory->open();
					foreach($rsp as $k => $v) {
						$cur->clean();
						$cur->post_id = $poll->post_id;
						$cur->option_type = 'pollsresponse';
						$cur->option_lang = $poll->post_lang;
						$cur->option_meta = $v[0];
						$cur->option_title = $people_id;
						$cur->option_content = $core->con->escape($v[1]);
						$core->auth->sudo(array($factory,'addOption'),$cur);
					}

					# --BEHAVIOR-- publicAfterPollsFactoryVote
					$core->callBehavior('publicAfterPollsFactoryVote',$poll,$people_id);
				}
				catch (Exception $e) {}
			}
			# Redirect after vote
			http::redirect($redir);
		}

		# params
		$params = new ArrayObject();
		$params['post_type'] = 'pollsfactory';
		$params['post_url'] = $args;

		if (!$_ctx->preview) {/* nothing to do now! see publicPollsFactoryForm */}

		$_ctx->posts = $core->blog->getPosts($params);

		if ($_ctx->posts->isEmpty()) {
			self::p404();
			return;
		}

		$core->tpl->setPath($core->tpl->getPath(),dirname(__FILE__).'/default-templates/');

		self::serveDocument('pollsfactory.html','text/html');
	}

	public static function pollPagePreview($args)
	{
		global $core;
		$_ctx = $GLOBALS['_ctx'];

		if (!preg_match('#^(.+?)/([0-9a-z]{40})/(.+?)$#',$args,$m)) {
			# The specified Preview URL is malformed.
			self::p404();
		}
		else
		{
			$user_id = $m[1];
			$user_key = $m[2];
			$poll_url = $m[3];
			if (!$core->auth->checkUser($user_id,null,$user_key)) {
				# The user has no access to the entry.
				self::p404();
			}
			else
			{
				$_ctx->preview = true;
				self::pollPage($poll_url);
			}
		}
	}

	# Query image
	public static function pollChart($args)
	{
		global $core;

		# Plugin not active
		if (!$core->blog->settings->pollsFactory->pollsFactory_active) {
			self::p404();
			return;
		}
		# Get poll and query id
		$ids = explode('/',$args);
		if (empty($ids[0]) || empty($ids[1])) {
			self::p404();
			return;
		}
	
		$_ctx =& $GLOBALS['_ctx'];
		$core =& $GLOBALS['core'];

		if (!$_ctx->exists('pollsFactoryChart')) {
			$_ctx->pollsFactoryChart = new pollsFactoryChart($core);
		}
		echo $_ctx->pollsFactoryChart->serveChart($ids[0],$ids[1]);
		exit(1);
	}
}

# Template
class publicTplPollsFactory
{
	public static function PollTitle($a)
	{
		return '<?php echo '.sprintf($GLOBALS['core']->tpl->getFilters($a),'$_ctx->posts->post_title').'; ?>';
	}

	public static function PollContent($a)
	{
		$url = empty($attr['absolute_urls']) ? 0 : 1;
		return '<?php echo '.sprintf($GLOBALS['core']->tpl->getFilters($a),'$_ctx->posts->getContent('.$url.')').'; ?>';
	}

	public static function PollForm($a)
	{
		return 
		"<?php \n".
		"echo publicPollsFactoryForm(\$core,\$_ctx->posts->post_id,false,false,\$core->blog->settings->pollsFactory->pollsFactory_public_graph); \n".
		"?> \n";
	}
}

# Post behavior
class publicBehaviorPollsFactory
{
	public static function publicEntryBeforeContent($core,$_ctx)
	{
		if ($core->blog->settings->pollsFactory->pollsFactory_public_pos) return;
		return self::publicEntryContent($core,$_ctx);
	}

	public static function publicEntryAfterContent($core,$_ctx)
	{
		if (!$core->blog->settings->pollsFactory->pollsFactory_public_pos) return;
		return self::publicEntryContent($core,$_ctx);
	}

	public static function publicEntryContent($core,$_ctx)
	{
		# Plugin not active or not on post
		if (!$core->blog->settings->pollsFactory->pollsFactory_active 
		 || !$_ctx->exists('posts'))
		{
			return;
		}
		# Not show poll on some pages
		$types = @unserialize($core->blog->settings->pollsFactory->pollsFactory_public_tpltypes);
		if (!is_array($types) || !in_array($core->url->type,$types))
		{
			return;
		}

		# pollsFactor object
		$factory = new pollsFactory($core);

		# Get polls linked to this post
		$posts_params['option_type'] = 'pollspost';
		$posts_params['post_id'] = $_ctx->posts->post_id;
		$posts = $factory->getOptions($posts_params);
		# No poll on this post
		if ($posts->isEmpty())
		{
			return;
		}

		if ($core->blog->settings->pollsFactory->pollsFactory_public_full) {
			while ($posts->fetch())
			{
				# Use common form for tpl and widget
				echo publicPollsFactoryForm($core,$posts->option_meta,true,true,$core->blog->settings->pollsFactory->pollsFactory_public_graph);
			}
		}
		else {
			echo 
			'<div class="pollsfactory pollslist-post"><h3>'.__('Polls').'</h3><ul>';
			while ($posts->fetch())
			{
				$poll_params['post_type'] = 'pollsfactory';
				$poll_params['post_id'] = $posts->option_meta;
				$poll_params['no_content'] = true;
				$poll_params['limit'] = 1;
				$poll = $core->blog->getPosts($poll_params);
				echo '<li><a href="'.$core->blog->url.$core->url->getBase('pollsFactoryPage').'/'.$poll->post_url.'">'.html::escapeHTML($poll->post_title).'</a></li>';
			}
			echo '</ul></div>';
		}
	}
}

# Common function to parse poll form/results
function publicPollsFactoryForm($core,$poll_id,$show_title=false,$show_desc=false,$use_graph=false)
{
	# Start of all: a post id
	$poll_id = (integer) $poll_id;
	# Get poll
	$poll_params['post_type'] = 'pollsfactory';
	$poll_params['post_id'] = $poll_id;
	$poll = $core->blog->getPosts($poll_params);
	# No poll
	if ($poll->isEmpty())
	{
		return;
	}
	# pollsFactor object
	$factory = new pollsFactory($core);
	# Poll title
	$poll_title = '';
	if ($show_title) {
		$poll_title .= '<h3 class="poll-title">'.html::escapeHTML($poll->post_title).'</h3>';
	}
	if ($show_desc) {
		$poll_title .= '<p class="poll-desc">'.context::global_filter($poll->post_content,0,0,0,0,0).'</p>';
	}

	# Check if poll is closed
	$finished = !$poll->post_open_tb;
	# If preview mode or people has not already voted and poll is not finished
	if ($GLOBALS['_ctx']->preview || !$factory->checkVote($poll->post_id) && !$finished)
	{
		$res = 
		'<div class="pollsfactory poll-form">'.$poll_title.
		'<form id="poll'.$poll->post_id.'" method="post" action="'.$core->blog->url.$core->url->getBase('pollsFactoryPage').'/vote">';

		# Poll queries
		$queries_params['option_type'] = 'pollsquery';
		$queries_params['post_id'] = $poll->post_id;
		$queries_params['order'] = 'option_position ASC';
		$queries = $factory->getOptions($queries_params);
		# No query for this poll
		if (!$queries->isEmpty())
		{
			# Loop throught queries of this poll
			while ($queries->fetch())
			{
				# Query selections
				$selections_params['option_type'] = 'pollsselection';
				$selections_params['post_id'] = $poll->post_id;
				$selections_params['option_meta'] = $queries->option_id;
				$selections_params['order'] = 'option_position ASC';
				$selections = $factory->getOptions($selections_params);
				# No option for this query
				if (!$selections->isEmpty())
				{
					# Default values
					$selections_res = $selections_combo = '';
					$selection_name = 'pollquery['.$queries->option_id.']';
					$selection_selected = 1;
					# Loop through options of this query
					while ($selections->fetch())
					{
						$selection_id = 'pollquery['.$queries->option_id.']['.$selections->option_id.']';
						# Parse option by type of query
						switch($queries->option_meta) {
						
							case 'checkbox':
							$selections_res .= 
							'<p class="field"><label for="'.$selection_id.'">'.
							form::checkbox($selection_id,$selections->option_id,0,'poll-checkbox').' '.
							html::escapeHTML($selections->option_title).'</label></p>';
							break;

							case 'radio':
							$selections_res .= 
							'<p class="field"><label for="'.$selection_id.'">'.
							form::radio(array($selection_name,$selection_id),$selections->option_id,$selection_selected,'poll-radio').' '.
							html::escapeHTML($selections->option_title).'</label></p>';
							$selection_selected = 0;
							break;

							case 'combo':
							if ($selection_selected) {
								$selection_combo_selected = $selections->option_id;
								$selection_selected = 0;
							}
							$selections_combo_nid = array($selection_name,$selection_id);
							$selections_combo[$selections->option_title] = $selections->option_id;
							break;

							case 'field':
							$selections_res .= 
							'<p class="field">'.form::field(array($selection_name,$selection_id),30,255,'','poll-field').'</p>';
							break;

							case 'textarea':
							$selections_res .= 
							'<p class="field">'.form::textArea(array($selection_name,$selection_id),35,7,'','poll-textarea').'</p>';
							break;
						
							default:
							break;
						}
					}
					# Parse query options
					if (!empty($selections_res) && $queries->option_meta != 'combo' || !empty($selections_combo) && $queries->option_meta == 'combo')
					{
						$res .= '<div class="poll-query">';

						# Query title
						$res .= '<h4>'.html::escapeHTML($queries->option_title).'</h4>';
						# Query description
						if ('' != $queries->option_content)
						{
							$res .= '<div class="poll-query-desc">'.context::global_filter($queries->option_content,0,0,0,0,0).'</div>';
						}
						# Options (and special content for combo)
						$res .= $queries->option_meta == 'combo' ?
							'<p class="field">'.form::combo($selections_combo_nid,$selections_combo,$selection_combo_selected,'poll-combo').'</p>' :
							$selections_res;

						$res .= '</div>';
					}
				}
			}
			# Form
			return 
			$res. 
			'<div class="poll-submit"><p>'.
			'<input type="hidden" name="poll" value="'.$poll->post_id.'" />'.
			'<input type="hidden" name="redir" value="'.http::getSelfURI().'" />'.
			'<input type="submit" name="submit" value="'.__('Validate').'" />'.
			'</p></div>'.
			'</form></div>';
		}
	}
	# If people has voted and settings say to show reponses or poll is finished
	elseif ($core->blog->settings->pollsFactory->pollsFactory_public_show || $finished)
	{
		$res = '';
		# Count responses
		$count = $factory->countVotes($poll->post_id);
		if ($count > 0)
		{
			# Poll queries
			$queries_params['option_type'] = 'pollsquery';
			$queries_params['post_id'] = $poll->post_id;
			$queries_params['order'] = 'option_position ASC';
			$queries = $factory->getOptions($queries_params);
			# No query for this poll
			if (!$queries->isEmpty())
			{
				# Loop through queries
				while ($queries->fetch())
				{
					# Query selections
					$selections_params['option_type'] = 'pollsselection';
					$selections_params['post_id'] = $poll->post_id;
					$selections_params['option_meta'] = $queries->option_id;
					$selections = $factory->getOptions($selections_params);

					# Query responses
					$responses_params['option_type'] = 'pollsresponse';
					$responses_params['post_id'] = $poll->post_id;
					$responses_params['option_meta'] = $queries->option_id;
					$responses = $factory->getOptions($responses_params);

					# If there's somthing to show
					if (!$selections->isEmpty() && !$responses->isEmpty())
					{
						# If display graphics is on, switch to image for integer responses
						if ($use_graph && in_array($queries->option_meta,array('checkbox','radio','combo'))) {
							$res .= '<p class="poll-chart"><img src="'.
							$core->blog->url.$core->url->getBase('pollsFactoryChart').'/'.
							$poll->post_id.'/'.$queries->option_id.'.png" alt="'.html::escapeHTML($queries->option_title).'" /></p>';
						}
						# Else use html
						else {
							$rs = array();

							# Loop through responses for this query
							while($responses->fetch())
							{
								switch($queries->option_meta) {
								
									case 'checkbox':
									case 'radio':
									case 'combo':
									if (!isset($rs[$responses->option_content])) {
										$rs[$responses->option_content] = 0;
									}
									$rs[$responses->option_content] += 1;
									break;

									case 'field':
									case 'textarea':
									if ($responses->option_selected) {
										$rs[] = $responses->option_content;
									}
									default:
									break;
								}
							}
							# There's something to show
							if (!empty($rs))
							{
								# For integer responses
								if (in_array($queries->option_meta,array('checkbox','radio','combo'))) {
									# Sort responses by number of votes
									$rs_sort = array();
									while($selections->fetch())
									{
										$nb = isset($rs[$selections->option_id]) ? $rs[$selections->option_id] : 0;
										$percent = $nb ? ceil($nb / $count * 100).'% - ' : '';

										if ($nb == 0) {
											$nb_text = __('no vote');
										}
										elseif ($nb == 1) {
											$nb_text = __('one vote');
										}
										else {
											$nb_text = sprintf(__('%s votes'),$nb);
										}

										$rs_sort[] = array(
											'nb'=>$nb,
											'text'=>'<dt>'.$selections->option_title.'</dt><dd>'.$percent.$nb_text.'</dd>'
										);
									}
									$sorted = staticRecord::newFromArray($rs_sort);
									$sorted->sort('nb','desc');

									# Parse responses
									$res .= '<h4>'.html::escapeHTML($queries->option_title).'</h4>';
									if ('' != $queries->option_content) {
			//							$res .= '<p class="poll-query-desc">'.html::escapeHTML($queries->query_desc).'<p>';
									}
									$res .= '<dl>';
									while($sorted->fetch())
									{
										$res .= $sorted->text;
									}
									$res .= '</dl>';
								}
								# For text responses
								else {
									$res .= '<h4>'.html::escapeHTML($queries->option_title).'</h4>';
									if ('' != $queries->option_content) {
			//							$res .= '<p class="poll-query-desc">'.html::escapeHTML($queries->query_desc).'<p>';
									}
									$res .= '<p><em>'.__('Some selected responses:').'</em><br />';
									foreach($rs as $k => $v) {
										$res .= '<blockquote>'.html::escapeHTML($v).'</blockquote>'; //? some bugs on escape strings
									}
									$res .= '</p>';
								}
							}
						}
					}
				}
			}
		}
		# If there are results or nobody has voted
		if (!empty($res) || !$count)
		{
			if ($count == 0) {
				$participate = __('Nobody has participated.');
			}
			elseif ($count == 1) {
				$participate = __('One people has participated.');
			}
			else {
				$participate = sprintf(__('%s people participated.'),$count);
			}

			$closed = $finished ? __('This poll is closed.').'<br />' : '';

			$res =    
			'<div class="pollsfactory poll-result">'.$poll_title.
			'<p class="poll-info">'.$closed.$participate.'</p>'.$res.
			'</div>';
		}
		return $res;
	}
	# If people has voted and settings say not to show responses
	else
	{
		return
		'<div class="pollsfactory poll-wait">'.$poll_title.
		'<p class="poll-info">'.
		__('You have already participated to this poll.').'<br />'.
		__('Please wait the end of this poll to see results.').'<br />'.
		'</p>'.
		'</div>';
	}
}
?>