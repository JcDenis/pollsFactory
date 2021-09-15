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

$new_version = $core->plugins->moduleInfo('pollsFactory','version');
$old_version = $core->getVersion('pollsFactory');

if (version_compare($old_version,$new_version,'>=')) return;

try
{
	# Check DC version
	if (version_compare(DC_VERSION,'2.2-alpha','<'))
	{
		throw new Exception('pollsFactory requires Dotclear 2.2 or higher.');
	}
	# Tables
	$s = new dbStruct($core->con,$core->prefix);
	$s->post_option
		->option_id ('bigint',0,false)
		->post_id ('bigint',0,false)
		->option_meta ('varchar',255,true,null)
		->option_creadt ('timestamp',0,false,'now()')
		->option_upddt ('timestamp',0,false,'now()')
		->option_type ('varchar',32,false,"''")
		->option_format ('varchar',32,false,"'xhtml'")
		->option_lang ('varchar',5,true,null)
		->option_title ('varchar',255,true,null)
		->option_content ('text',0,true,null)
		->option_content_xhtml ('text',0,false)
		->option_selected ('smallint',0,false,0)
		->option_position ('integer',0,false,0)
		
		->index('idx_post_option_option','btree','option_id')
		->index('idx_post_option_post','btree','post_id')
		->index('idx_post_option_meta','btree','option_meta')
		->index('idx_post_option_type','btree','option_type');
	
	$si = new dbStruct($core->con,$core->prefix);
	$changes = $si->synchronize($s);
	
	# Settings
	$core->blog->settings->addNamespace('pollsFactory');
	$s = $core->blog->settings->pollsFactory;
	$s->put('pollsFactory_active',false,'boolean','Enable extension',false,true);
	$s->put('pollsFactory_user_ident',0,'integer','User identification',false,true);
	$s->put('pollsFactory_public_show',true,'boolean','Show reponse when user votes',false,true);
	$s->put('pollsFactory_public_pos',true,'boolean','Show poll after post content',false,true);
	$s->put('pollsFactory_public_full',true,'boolean','Show full content after post content',false,true);
	$s->put('pollsFactory_public_graph',true,'boolean','Use graphics for results',false,true);
	$s->put('pollsFactory_graph_cache',true,'boolean','Use cache for graphics',false,true);
	$s->put('pollsFactory_graph_trigger',0,'integer','Last change for graph settings',false,true);
	$s->put('pollsFactory_graph_options',serialize(pollsFactoryChart::defaultOptions()),'string','graphic options',false,true);
	$s->put('pollsFactory_public_tpltypes',serialize(array('post')),'string','List of templates types for full description of polls',false,true);
	
	# Version
	$core->setVersion('pollsFactory',$new_version);
	
	return true;
}
catch (Exception $e)
{
	$core->error->add($e->getMessage());
}
return false;
?>