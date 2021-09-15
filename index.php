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

# Check user perms
dcPage::check('usage,contentadmin');

# Objects
$s = $core->blog->settings->pollsFactory;
$factory = new pollsFactory($core);

# Default values
$echo = '';
$show_filters = false;
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

$header = 
dcPage::jsLoad('index.php?pf=pollsFactory/js/main.js').
'<script type="text/javascript">'."\n//<![CDATA[\n".
"jcToolsBox.prototype.text_wait = '".html::escapeJS(__('Please wait'))."';\n".
"\n//]]>\n</script>\n".
'<link rel="stylesheet" type="text/css" href="index.php?pf=pollsFactory/style.css" />';

$footer = '<hr class="clear"/><p class="right">
<a class="button" href="'.$p_url.'&amp;tab=setting">'.__('Settings').'</a> - 
pollsFactory - '.$core->plugins->moduleInfo('pollsFactory','version').'&nbsp;
<img alt="'.__('Polls manager').'" src="index.php?pf=pollsFactory/icon.png" />
</p>';

# Messages
$msg = isset($_REQUEST['msg']) ? $_REQUEST['msg'] : '';
$msg_list = array(
	'savesetting' => __('Configuration successfully saved'),
	'createpoll' => __('Poll successfully created'),
	'editpoll' => __('Poll successfully updated'),
	'deletepoll' => __('Poll successfully deleted'),
	'removeentries' => __('Entries successfully removed'),
	'open' => __('Votes successfully opened'),
	'close' => __('Votes successfully closed'),
	'delete' => __('Polls successfully deleted'),
	'publish' => __('Polls status successfully updated'),
	'unpublish' => __('Polls status successfully updated'),
	'scheduled' => __('Polls status successfully updated'),
	'pending' => __('Polls status successfully updated'),
	'selected' => __('Polls successfuly mark as selected'),
	'unselected' => __('Polls successfuly mark as unselected'),
	'deletequery' => __('Queries successfully deleted'),
	'reorderquery' => __('Queries successfully reordered'),
	'createquery' => __('Query successfully created'),
	'editquery' => __('Query successfully updated'),
	'deletenewquery' => __('New query successfully deleted'),
	'deleteselection' => __('Options successfully deleted'),
	'reorderselection' => __('Options successfully reordered'),
	'createselection' => __('Option successfully created'),
	'editselection' => __('Option successfully updated'),
	'selectresponses' => __('Responses succesfully selected'),
	'selectresponse' => __('Response successfully selected'),
	'unselectresponse' => __('Response successfully unselected'),
	'deletepeoples' => __('Users successfully deleted')
);
if (isset($msg_list[$msg])) {
	$msg = sprintf('<p class="message">%s</p>',$msg_list[$msg]);
}

# Pages
$start_tab = $s->pollsFactory_active ? 'polls' : 'setting';
$default_tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : $start_tab;

if (!file_exists(dirname(__FILE__).'/inc/index.'.$default_tab.'.php')) {
	$default_tab = 'setting';
}
include dirname(__FILE__).'/inc/index.'.$default_tab.'.php';

?>