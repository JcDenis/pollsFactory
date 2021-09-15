/* -- BEGIN LICENSE BLOCK ----------------------------------
 * This file is part of pollsFactory, a plugin for Dotclear 2.
 * 
 * Copyright (c) 2009-2010 JC Denis and contributors
 * jcdenis@gdwd.com
 * 
 * Licensed under the GPL version 2.0 license.
 * A copy of this license is available in LICENSE file or at
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * -- END LICENSE BLOCK ------------------------------------*/

$(function() {
	var pollsForm=$('#setting-form');
	if (pollsForm!=undefined){
		dotclear.jcTools.formFieldsetToMenu(pollsForm);
	}
});