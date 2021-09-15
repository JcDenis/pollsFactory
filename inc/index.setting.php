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

$active = (boolean) $s->pollsFactory_active;
$people_ident = (integer) $s->pollsFactory_people_ident;

$public_show = (boolean) $s->pollsFactory_public_show;
$public_pos = (boolean) $s->pollsFactory_public_pos;
$public_full = (boolean) $s->pollsFactory_public_full;
$public_graph = (boolean) $s->pollsFactory_public_graph;
$public_tpltypes = @unserialize($s->pollsFactory_public_tpltypes);
if (!is_array($public_tpltypes)) $public_tpltypes = array();

$graph_options = @unserialize($s->pollsFactory_graph_options);
if (!is_array($graph_options) || empty($graph_options)){
	$graph_options = pollsFactoryChart::defaultOptions();
}

$section = isset($_REQUEST['section']) ? $_REQUEST['section'] : '';

if ($default_tab == 'setting' && $action == 'savesetting')
{
	try
	{
		$s->put('pollsFactory_active',!empty($_POST['active']));
		$s->put('pollsFactory_people_ident', (integer) $_POST['people_ident']);
		$s->put('pollsFactory_public_show',!empty($_POST['public_show']));
		$s->put('pollsFactory_public_pos',!empty($_POST['public_pos']));
		$s->put('pollsFactory_public_full',!empty($_POST['public_full']));
		$s->put('pollsFactory_public_graph',!empty($_POST['public_graph']));
		$s->put('pollsFactory_public_tpltypes',serialize($_POST['public_tpltypes']));
		$s->put('pollsFactory_graph_options',serialize($_POST['graph_options']));

		if ($_POST['graph_options']['width'] != $graph_options['width ']
		 || $_POST['graph_options']['ttcolor'] != $graph_options['ttcolor'] 
		 || $_POST['graph_options']['txcolor'] != $graph_options['txcolor'] 
		 || $_POST['graph_options']['bgcolor'] != $graph_options['bgcolor'] 
		 || $_POST['graph_options']['chcolor'] != $graph_options['chcolor'] 
		 || $_POST['graph_options']['barcolor'] != $graph_options['barcolor'])
		{
			$s->put('pollsFactory_graph_trigger',time());
		}

		$core->blog->triggerBlog();

		http::redirect($p_url.'&tab=setting&msg='.$action.'&section='.$section);
	}
	catch (Exception $e)
	{
		$core->error->add($e->getMessage());
	}
}

$combo_people_ident = array(
	__('cookie') => 0,
	__('cookie and IP') => 1,
	__('IP') => 2
);
$combo_public_show = array(
	__('when poll is finished') => 0,
	__('when people has voted') => 1
);

echo '
<html>
<head><title>'.__('Polls manager').'</title>'.$header.
dcPage::jsColorPicker().
dcPage::jsLoad('index.php?pf=pollsFactory/js/setting.js').
"<script type=\"text/javascript\">\n//<![CDATA[\n".
dcPage::jsVar('jcToolsBox.prototype.section',$section).
"\n//]]>\n</script>\n".
'</head>
<body>
<h2>'.html::escapeHTML($core->blog->name).
' &rsaquo; <a href="'.$p_url.'&amp;tab=polls">'.__('Polls').'</a>'.
' &rsaquo; '.__('Settings').
' - <a class="button" href="'.$p_url.'&amp;tab=poll">'.__('New poll').'</a>'.
'</h2>'.$msg.'
<form id="setting-form" method="post" action="plugin.php">

<fieldset id="setting-plugin"><legend>'. __('Plugin activation').'</legend>
<p class="field"><label class="classic">'.
form::checkbox(array('active'),'1',$active).' '.
__('Enable extension').'</label></p>
</fieldset>

<fieldset id="setting-option"><legend>'. __('General rules').'</legend>
<p class="field">'.__('User identification:').' '.
form::combo(array('people_ident'),$combo_people_ident,$people_ident).'</p>
<p class="field">'.__('Show reponse:').' '.
form::combo(array('public_show'),$combo_public_show,$public_show).'</p>
<p class="field"><label class="classic">'.
form::checkbox('public_graph','1',$public_graph).' '.
__('Use graphic results').'</label></p>
</fieldset>


<fieldset id="setting-graph"><legend>'. __('Graphic results settings').'</legend>
<p class="field">'.__('Width:').' '.
form::field(array('graph_options[width]'),7,4,$graph_options['width']).'</p>
<p class="field">'.__('Title color:').' '.
form::field(array('graph_options[ttcolor]'),7,7,$graph_options['ttcolor'],'colorpicker').'</p>
<p class="field">'.__('Text color:').' '.
form::field(array('graph_options[txcolor]'),7,7,$graph_options['txcolor'],'colorpicker').'</p>
<p class="field">'.__('Background color:').' '.
form::field(array('graph_options[bgcolor]'),7,7,$graph_options['bgcolor'],'colorpicker').'</p>
<p class="field">'.__('Chart color:').' '.
form::field(array('graph_options[chcolor]'),7,7,$graph_options['chcolor'],'colorpicker').'</p>
<p class="field">'.__('Bar color:').' '.
form::field(array('graph_options[barcolor]'),7,7,$graph_options['barcolor'],'colorpicker').'</p>
</fieldset>

<fieldset id="setting-display"><legend>'. __('Polls display').'</legend>
<p>'.__('Show on:').'</p>';
foreach($factory->getPublicUrlTypes($core) as $k => $v)
{
	echo  
	'<p class="field"><label class="classic">'.__($k).' '.
	form::checkbox(array('public_tpltypes[]'),$v,in_array($v,$public_tpltypes)).
	'</label></p>';
}
echo '
<p class="field"><label class="classic">'.__('Content:').' '.
form::combo(array('public_full'),array(__('full content')=>1,__('only link to page of poll')=>0),$public_full).'
</label></p>
<p class="field"><label class="classic">'.__('Place:').' '.
form::combo(array('public_pos'),array(__('after content')=>1,__('before content')=>0),$public_pos).'
</label></p>
</fieldset>

<div class="clear">
<p><input type="submit" name="save" value="'.__('save').'" />'.
$core->formNonce().
form::hidden(array('p'),'pollsFactory').
form::hidden(array('tab'),'setting').
form::hidden(array('action'),'savesetting').'
</p></div>
</form>';
dcPage::helpBlock('pollsFactory');
echo $footer.'</body></html>';
?>