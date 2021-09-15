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

$this->addUserAction(
	/* type */ 'settings',
	/* action */ 'delete_all',
	/* ns */ 'pollsFactory',
	/* description */ __('delete all settings')
);

$this->addUserAction(
	/* type */ 'plugins',
	/* action */ 'delete',
	/* ns */ 'pollsFactory',
	/* description */ __('delete plugin files')
);

$this->addUserAction(
	/* type */ 'versions',
	/* action */ 'delete',
	/* ns */ 'pollsFactory',
	/* description */ __('delete the version number')
);

$this->addUserAction(
	/* type */ 'table',
	/* action */ 'delete',
	/* ns */ 'post_option',
	/* description */ sprintf(__('delete %s table'),'post_option')
);

# Keep table on direct action

$this->addDirectAction(
	/* type */ 'settings',
	/* action */ 'delete_all',
	/* ns */ 'pollsFactory',
	/* description */ sprintf(__('delete all %s settings'),'pollsFactory')
);

$this->addDirectAction(
	/* type */ 'versions',
	/* action */ 'delete',
	/* ns */ 'pollsFactory',
	/* description */ sprintf(__('delete %s version number'),'pollsFactory')
);

$this->addDirectAction(
	/* type */ 'plugins',
	/* action */ 'delete',
	/* ns */ 'pollsFactory',
	/* description */ sprintf(__('delete %s plugin files'),'pollsFactory')
);
?>