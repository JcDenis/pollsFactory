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

# Polls voters lists
class adminPeopleList extends adminGenericList
{
	public function peopleDisplay($page,$nb_per_page,$enclose_block='')
	{
		$echo = '';
		if ($this->rs->isEmpty())
		{
			$echo .= '<p><strong>'.__('No user').'</strong></p>';
		}
		else
		{
			$pager = new pager($page,$this->rs_count,$nb_per_page,10);
			$pager->html_prev = $this->html_prev;
			$pager->html_next = $this->html_next;
			$pager->var_page = 'u_page';

			$html_block =
			'<table class="clear">'.
			'<tr>'.
			'<th colspan="2">'.__('Ip').'</th>'.
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
				$echo .= $this->peopleLine();
			}

			$echo .= $blocks[1];

			$echo .= '<p>'.__('Page(s)').' : '.$pager->getLinks().'</p>';
		}
		return $echo;
	}

	private function peopleLine()
	{
		$u_page = isset($_REQUEST['u_page']) ? abs((integer) $_REQUEST['u_page']) : 1;
		$u_limit = isset($_REQUEST['u_limit']) ? abs((integer) $_REQUEST['u_limit']) : 20;

		$res = 
		'<tr class="line">'.
		'<td class="nowrap">'.form::checkbox(array('entries[]'),$this->rs->option_title).' </td>'.
		'<td class="maximal"><a href="plugin.php?p=pollsFactory&amp;tab=result&amp;sub=user&amp;poll_id='.$this->rs->post_id.'&amp;people_id='.$this->rs->option_title.'&amp;section=responses&amp;u_page='.$u_page.'&amp;u_limit='.$u_limit.'" title="'.__('Show user').'">'.
		html::escapeHTML($this->rs->option_title).'</a></td>'.
		'</tr>';

		return $res;
	}
}

$section = isset($_REQUEST['section']) ? $_REQUEST['section'] : '';
$sub = isset($_REQUEST['sub']) && in_array($_REQUEST['sub'],array('query','user')) ? $_REQUEST['sub'] : 'query';

$poll_id = isset($_REQUEST['poll_id']) ? $_REQUEST['poll_id'] : -1;
$poll_id = (integer) $poll_id;

$poll_params['post_type'] = 'pollsfactory';
$poll_params['post_id'] = $poll_id;
$poll_params['no_content'] = true;
$poll_params['limit'] = 1;
$poll = $core->blog->getPosts($poll_params);

# No poll
if ($poll->isEmpty()) {
	$core->error->add(__('no such poll'));
	
	echo 
	'<html>'.
	'<head><title>'.__('Polls manager').'</title>'.$header.'</head>'.
	'<body>'.
	'<h2>'.html::escapeHTML($core->blog->name).
	' &rsaquo; <a href="'.$p_url.'&amp;tab=polls">'.__('Polls').'</a>'.
	' &rsaquo; '.__('Poll result').
	' - <a class="button" href="'.$p_url.'&amp;tab=poll">'.__('New poll').'</a>'.
	'</h2>'.
	'<p><a href="'.$p_url.'&amp;tab=polls" title="'.__('select poll').'">'.
	__('select poll to view from polls list').'</a></p>';
}
# Results by user
elseif ($sub == 'user')
{
	$people_id = isset($_REQUEST['people_id']) ? $_REQUEST['people_id'] : null;
	$u_page = isset($_REQUEST['u_page']) ? abs((integer) $_REQUEST['u_page']) : 1;
	$u_limit = isset($_REQUEST['u_limit']) ? abs((integer) $_REQUEST['u_limit']) : 20;

	if (!empty($_POST['response_id']) && in_array($action,array('selectresponse','unselectresponse')))
	{
		try {
			$id = (integer) $_POST['response_id'];

			$factory->updOptionSelected($id,($action == 'selectresponse'));

			http::redirect($p_url.'&tab=result&poll_id='.$poll_id.'&people_id='.$people_id.'&u_page='.$u_page.'&u_limit='.$u_limit.'&sub='.$sub.'&section='.$section.'&msg='.$action);
		}
		catch (Exception $e) {
			$core->error->add($e->getMessage());
		}
	}
	if (!empty($_POST['entries']) && $action == 'deletepeoples')
	{
		try {
			foreach($_POST['entries'] as $k => $id)
			{
				$del_peoples_params['option_type'] = 'pollsresponse';
				$del_peoples_params['post_id'] = $poll_id;
				$del_peoples_params['option_title'] = $id;
				$del_peoples = $factory->getOptions($del_peoples_params);
				
				while($del_peoples->fetch())
				{
					$factory->delOption($del_peoples->option_id);
				}
			}

			http::redirect($p_url.'&tab=result&poll_id='.$poll_id.'&people_id=&u_page='.$u_page.'&u_limit='.$u_limit.'&sub='.$sub.'&section='.$section.'&msg='.$action);
		}
		catch (Exception $e) {
			$core->error->add($e->getMessage());
		}
	}

	# Get peoples
	try {
		$peoples = $factory->getVotes($poll_id,(($u_page-1)*$u_limit),$u_limit);
		$counter = $factory->getVotes($poll_id);
		$peoples_list = new adminPeopleList($core,$peoples,$counter->count());
	} catch (Exception $e) {echo $e->getMessage();exit(1);
		$core->error->add($e->getMessage());
	}

	echo '
	<html>
	<head><title>'.__('Polls manager').'</title>'.$header.'</head>
	<body>'.$msg.'
	<h2>'.html::escapeHTML($core->blog->name).
	' &rsaquo; <a href="'.$p_url.'&amp;tab=polls">'.__('Polls').'</a>'.
	' &rsaquo; '.__('Results by user').
	' - <a class="button" href="'.$p_url.'&amp;tab=poll">'.__('New poll').'</a>'.
	'</h2>'.
	'<p><a href="'.$core->getPostAdminURL($poll->post_type,$poll->post_id).'">&#171; '.
		sprintf(__('Back to "%s"'),html::escapeHTML($poll->post_title)).'</a>'.
	' - <a href="'.$p_url.'&amp;tab=result&amp;poll_id='.$poll_id.'&amp;sub=query">'.__('Results by query').'</a></p>';

	if ($people_id != '') {
		echo '<div class="two-cols"><div class="col">';
	}
	echo '
	<fieldset id="users"><legend>'.__('Users').'</legend>'.
	$peoples_list->peopleDisplay($u_page,$u_limit,
		'<form action="plugin.php" method="post" id="form-entries">'.
		'%s'.
		'<div class="two-cols">'.
		'<p class="col checkboxes-helpers"></p>'.
		'<p class="col right">'.
		'<input type="submit" value="'.__('delete selected users') .'" /></p>'.
		form::hidden(array('action'),'deletepeoples').
		form::hidden(array('poll_id'),$poll_id).
		form::hidden(array('u_page'),$u_page).
		form::hidden(array('u_limit'),$u_limit).
		form::hidden(array('section'),'users').
		form::hidden(array('sub'),'user').
		form::hidden(array('tab'),'result').
		form::hidden(array('p'),'pollsFactory').
		$core->formNonce().
		'</div>'.
		'</form>'
	).
	'</fieldset>';

	if ($people_id != '') {
		echo '</div><div class="col"><fieldset id="responses"><legend>'.__('Responses').'</legend>';

		$queries_params['option_type'] = 'pollsquery';
		$queries_params['post_id'] = $poll_id;
		$queries_params['order'] = 'option_position ASC';
		$queries = $factory->getOptions($queries_params);
		if (!$queries->isEmpty()) {
			while ($queries->fetch()) {

				echo '<h3>'.html::escapeHTML($queries->option_title).'</h3><p><em>('.array_search($queries->option_meta,$factory->getQueryTypes()).')</em></p>';

				$rsp_params['option_type'] = 'pollsresponse';
				$rsp_params['post_id'] = $poll_id;
				$rsp_params['option_meta'] = $queries->option_id;
				$rsp_params['option_title'] = $people_id;
				$rsp = $factory->getOptions($rsp_params);
				
				if ($rsp->isEmpty() || $rsp->option_content == '') {
					echo '<p>'.__('User has not answered this query').'</p>';
				}
				else {
					if (in_array($queries->option_meta,array('field','textarea'))) {
						echo 
						'<form action="plugin.php" method="post"><p>'.
						html::escapeHTML($rsp->option_content).'</p><p>';
						if ($rsp->option_selected) {
							echo 
							'<input type="submit" value="'.__('Remove this response from public side').'" />'.
							form::hidden(array('action'),'unselectresponse');
						}
						else {
							echo 
							'<input type="submit" value="'.__('Show this response on public side').'" />'.
							form::hidden(array('action'),'selectresponse');
						}
						echo 
						form::hidden(array('response_id'),$rsp->option_id).
						form::hidden(array('poll_id'),$poll_id).
						form::hidden(array('people_id'),$people_id).
						form::hidden(array('u_page'),$u_page).
						form::hidden(array('u_limit'),$u_limit).
						form::hidden(array('section'),'responses').
						form::hidden(array('sub'),'user').
						form::hidden(array('tab'),'result').
						form::hidden(array('p'),'pollsFactory').
						$core->formNonce().
						'</p></form>';
					}
					else {
						while($rsp->fetch()) {
							echo '<ul>';
							$selection_params['option_type'] = 'pollsselection';
							$selection_params['post_id'] = $poll_id;
							$selection_params['option_id'] = $rsp->option_content;
							$selection_params['limit'] = 1;
							$selection = $factory->getOptions($selection_params);
							if ($selection->isEmpty()) {
								echo '<li>'.__('Failed to retrieve option title').'</li>';
							}
							else {
								echo '<li>'.html::escapeHTML($selection->option_title).'</li>';
							}
							echo '</ul>';
						}
					}
				}
				
				echo '</p>';
			}
		}
		echo '</fieldset></div></div>';
	}
}
# Results by query
else
{
	$query_id = isset($_REQUEST['query_id']) ? $_REQUEST['query_id'] : -1;
	$r_page = isset($_REQUEST['r_page']) ? abs((integer) $_REQUEST['r_page']) : 1;
	$r_limit = isset($_REQUEST['r_limit']) ? abs((integer) $_REQUEST['r_limit']) : 20;
	$count = (integer) $factory->countVotes($poll->post_id);

	if ($action == 'selectresponses')
	{
		try {
			foreach($_POST['responses_list'] as $k => $id)
			{
				$factory->updOptionSelected($id,$_POST['responses_sel'][$id]);
			}
			http::redirect($p_url.'&tab=result&poll_id='.$poll_id.'&query_id='.$query_id.'&r_page='.$r_page.'&r_limit='.$r_limit.'&sub='.$sub.'&msg='.$action);
		}
		catch (Exception $e) {
			$core->error->add($e->getMessage());
		}
	}

	echo '

	<html>
	<head><title>'.__('Polls manager').'</title>'.$header.'</head>
	<body>'.$msg.'
	<h2>'.html::escapeHTML($core->blog->name).
	' &rsaquo; <a href="'.$p_url.'&amp;tab=polls">'.__('Polls').'</a>'.
	' &rsaquo; '.__('Results by query').
	' - <a class="button" href="'.$p_url.'&amp;tab=poll">'.__('New poll').'</a>'.
	'</h2>'.
	'<p><a href="'.$core->getPostAdminURL($poll->post_type,$poll->post_id).'">&#171; '.
		sprintf(__('Back to "%s"'),html::escapeHTML($poll->post_title)).'</a>'.
	' - <a href="'.$p_url.'&amp;tab=result&amp;poll_id='.$poll_id.'&amp;sub=user">'.__('Results by user').'</a></p>';


	# Queries
	$queries_params['option_type'] = 'pollsquery';
	$queries_params['post_id'] = $poll_id;
	$queries_params['order'] = 'option_position ASC';
	$queries = $factory->getOptions($queries_params);

	# Query
	$query_params['option_type'] = 'pollsquery';
	$query_params['post_id'] = $poll_id;
	$query_params['option_id'] = $query_id;
	$query = $factory->getOptions($query_params);


	if (!$query->isEmpty()) {
		echo '<div class="two-cols"><div class="col">';
	}

	echo '<fieldset id="queries"><legend>'.__('Queries').'</legend><ul>';

	while ($queries->fetch())
	{
		if ($query_id == $queries->option_id) {
			echo '<li>'.html::escapeHTML($queries->option_title).'<br /><em>('.array_search($queries->option_meta,$factory->getQueryTypes()).')</em></li>';
		}
		else {
			echo '<li><a href="'.$p_url.'&amp;tab=result&amp;section=responses&amp;poll_id='.$poll_id.'&amp;query_id='.$queries->option_id.'">'.html::escapeHTML($queries->option_title).'</a><br /><em>('.array_search($queries->option_meta,$factory->getQueryTypes()).')</em></li>';
		}
	}
	echo '</ul></fieldset>';

	if (!$query->isEmpty()) {

		echo '</div><div class="col"><fieldset id="responses"><legend>'.__('Responses').'</legend>';

		$selections_params['option_type'] = 'pollsselection';
		$selections_params['post_id'] = $poll_id;
		$selections_params['option_meta'] = $query_id;
		
		$selections = $factory->getOptions($selections_params);
		if ($query->isEmpty() || $selections->isEmpty()) {
			echo '<p>'.__('There is no response for this query.').'</p>';
		}
		else {
			$rs = array();
			$responses_params = array('option_meta' => $query->option_id);
			$responses_params['option_type'] = 'pollsresponse';
			$responses_params['post_id'] = $poll_id;

			# limit results for numeric (id) responses
			if (in_array($query->option_type,array('checkbox','radio','combo'))) {
				$responses = $factory->getResponses($responses_params);
			}
			# limit results for text responses
			else {
				$r_count = $factory->getOptions($responses_params,true)->f(0);
				$responses_params['limit'] = array(($r_page * $r_limit - $r_limit),$r_limit);

				$responses = $factory->getOptions($responses_params);

				if ($r_count > $responses->count()) {
					$pager = new pager($r_page,$r_count,$r_limit);
					$pager->var_page = 'r_page';
					$pager->base_url = $p_url.'&amp;tab=result&amp;section=responses&amp;poll_id='.$poll_id.'&amp;query_id='.$queries->option_id.'&amp;r_limit='.$r_limit.'&amp;r_page=%s';
					echo $pager->getLinks();
				}
			}

			# Loop through responses for this query
			while($responses->fetch())
			{
				switch($query->option_meta) {
				
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
					if ('' != $responses->option_content) {
						$rs[] =  
						'<tr class="line">'.
						'<td class="minimal nowrap">'.
						form::hidden(array('responses_list[]'),$responses->option_id).
						form::checkbox(array('responses_sel['.$responses->option_id.']'),1,$responses->option_selected).
						'</td>'.
						'<td class="maximal">'.html::escapeHTML(substr($responses->option_content,0,350)).'</td>'.
						'</tr>';
					}
					default:
					break;
				}
			}
			# There's something to show
			if (!empty($rs))
			{
				# for numeric (id) responses
				if (in_array($query->option_meta,array('checkbox','radio','combo'))) {
					# Sort responses by number of votes
					$rs_sort = array();
					while($selections->fetch())
					{
						$nb = isset($rs[$selections->option_id]) ? $rs[$selections->option_id] : 0;
						$percent = $count ? ceil($nb / $count * 100).'%' : '';

						$rs_sort[] = array(
							'nb'=>$nb,
							'text'=>'<tr class="line"><td class="maximal">'.html::escapeHTML($selections->option_title).'</td><td class="nowrap"> '.$percent.'</td><td class="nowrap">'.sprintf(__('%s votes'),$nb).'</td></tr>'
						);
					}
					$sorted = staticRecord::newFromArray($rs_sort);
					$sorted->sort('nb','desc');

					# Parse responses
					echo '<table>';
					while($sorted->fetch())
					{
						echo $sorted->text;
					}
					echo '</table>';
				}
				# for text responses
				else {
					echo 
					'<table>'.
					'<form method="post" action="plugin.php">'.
					implode('',$rs).
					'</table>'.
					'<p>'.
					'<input type="submit" name="save" value="'.__('Show selected responses on public side').'" />'.
					$GLOBALS['core']->formNonce().
					form::hidden(array('action'),'selectresponses').
					form::hidden(array('poll_id'),$poll_id).
					form::hidden(array('query_id'),$query_id).
					form::hidden(array('r_limit'),$r_limit).
					form::hidden(array('r_page'),$r_page).
					form::hidden(array('p'),'pollsFactory').
					form::hidden(array('tab'),'result').
					form::hidden(array('section'),'queries').
					'</form>';
				}
			}
			else {
				echo '<p>'.__('There is no response for this query.').'</p>';
			}
		}
		echo '</fieldset></div></div>';
	}
}
dcPage::helpBlock('pollsFactory');
echo $footer.'</body></html>';
?>