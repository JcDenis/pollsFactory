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

var dragsort = ToolMan.dragsort();

$(function(){
	if(!document.getElementById){return;}
	/* Queries sortable */
	dragsort.makeTableSortable($("#queries-list").get(0),
	dotclear.sortable.setHandle,dotclear.sortable.saveQueriesOrder);
	/* Options sortable */
	dragsort.makeTableSortable($("#selections-list").get(0),
	dotclear.sortable.setHandle,dotclear.sortable.saveSelectionsOrder);
	/* new queries desc toobar */
	var newQueryDesc=$('textarea[name=new_query_desc]').get(0);
	if(newQueryDesc!=undefined){
		var newQueryTb=new jsToolBar(newQueryDesc);
		if ($('textarea[name=new_query_desc]').hasClass('wiki')){newQueryTb.switchMode('wiki');}else{newQueryTb.switchMode('xhtml');}
	}
	/* edit queries desc toobar */
	var editQueryDesc=$('textarea[name=edit_query_desc]').get(0);
	if(editQueryDesc!=undefined){
		var editQueryTb=new jsToolBar(editQueryDesc);
		if ($('textarea[name=edit_query_desc]').hasClass('wiki')){editQueryTb.switchMode('wiki');}else{editQueryTb.switchMode('xhtml');}
	}
	/* fieldset to menu */
	var pollsForm=$('#poll-content');
	if (pollsForm!=undefined){
		dotclear.jcTools.formFieldsetToMenu(pollsForm);
	}
	/* poll page preview */
	$('#poll-preview').modalWeb($(window).width()-40,$(window).height()-40);

});

dotclear.sortable = {
	setHandle: function(item) {
		var handle = $(item).find('td.handle').get(0);
		while (handle.firstChild) {
			handle.removeChild(handle.firstChild);
		}

		item.toolManDragGroup.setHandle(handle);
		handle.className = handle.className+' handler';
	},

	saveQueriesOrder: function(item) {
		var group = item.toolManDragGroup;
		var order = document.getElementById('queries_order_js');
		group.register('dragend', function() {
			order.value = '';
			items = item.parentNode.getElementsByTagName('tr');

			for (var i=0; i<items.length; i++) {
				order.value += items[i].id.substr(2)+',';
			}
		});
	},

	saveSelectionsOrder: function(item) {
		var group = item.toolManDragGroup;
		var order = document.getElementById('selections_order_js');
		group.register('dragend', function() {
			order.value = '';
			items = item.parentNode.getElementsByTagName('tr');

			for (var i=0; i<items.length; i++) {
				order.value += items[i].id.substr(2)+',';
			}
		});
	}
};