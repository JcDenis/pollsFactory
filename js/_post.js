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

$(function(){$('#edit-entry').onetabload(function(){
	var target=$('#pollsfactory-form');
	var post_id=$('#id');
	post_id=(post_id.length>0)?post_id.get(0).value:0;
	
	if(target.length>0){
		var pfEdit = new pollsFactoryPostEditor(target,post_id);
		$(target).empty();
		pfEdit.displayPostPollsList();
		pfEdit.displayAddPollLink();
	}
})});

function pollsFactoryPostEditor(target,post_id){this.target=target;this.post_id=post_id;};

pollsFactoryPostEditor.prototype={
	target:'',
	post_id:0,
	poll_url:'plugin.php?p=pollsFactory&tab=poll&id=',
	text_title:'Polls manager',
	text_confirm_remove_poll:'Are you sure you want to remove this poll?',
	text_choose_poll:'Choose from list',
	text_edit_poll:'Edit poll',
	text_add_poll:'Add poll',
	text_remove_poll:'Remove poll',
	text_no_poll:'No more poll',
	service_uri:'services.php',

	displayPostPollsList:function(){
		var This=this;
		This.poll_url=This.poll_url.replace(/&amp;/g,"&");
		$('.postPollList').remove();
		This.polls_list=$('<ul></ul>').addClass('postPollList');
		This.target.prepend(This.polls_list);
		if(This.post_id!=0) {
			$.get(This.service_uri,{f: 'getPollsOfPost',id: This.post_id},function(data){
				data=$(data);
				if(data.find('rsp').attr('status')=='ok'){
					data.find('poll').each(function(){
						var id=$(this).attr('id');
						var title=$(this).text();
						if(id.length>0){if(title.length>0){
							This.addPollLine(id,title);
						}}
					});
				}else{
					alert(data.find('message').text());
				}
			});
		}else{
			var test=$('.pollsFormTitle');
			if(test.length==0){
				This.target.before($('<h3></h3>').addClass('pollsFormTitle').text(This.text_title));
			}
			$('.postPollInput').each(function(){
				var id=$(this).attr('value');
				var title=$(this).attr('title');
				if (id.length>0){if(title.length>0){
					This.addPollLine(id,title);
				}}
			});
		}
	},

	addPollLine:function(id,title){
		var This=this;
		$(This.polls_list).append($('<li></li>').addClass('postPollLine-'+id).attr('id',id).append($('<a></a>').text(title).attr({'href':This.poll_url+id,'title':This.text_edit_poll})).append(' ').append($('<a></a>').text('[x]').attr({'href':'#','title':This.text_remove_poll}).addClass('postPollRemove').click(function(){This.removePoll(this,id);return false;})));
	},

	removePoll:function(target,id){
		var This=this;
		if(window.confirm(This.text_confirm_remove_poll)){
			if(This.post_id!=0){
				$.post(This.service_uri,{f:'removePollPost', xd_check:dotclear.nonce, post_id:This.post_id, poll_id:id},function(data){
					data=$(data);
					if(data.find('rsp').attr('status')=='ok'){
					}else{
						alert(data.find('message').text());
					}
				});
			}
			$(target).parent().remove();
			$('.postPollInput[value='+id+']').remove();
			This.displayAddPollLink();
		}
	},

	displayAddPollLink:function(){
		var This=this;
		$('.postPollAdd').remove();
		$('.pollsList').remove();
		$(This.target).after($('<p></p>').append($('<a></a>').text(This.text_choose_poll).attr({'href':'#','title':This.text_add_poll}).addClass('postPollAdd').click(function(){This.displayAllPollsList(this);return false;})));
	},

	displayAllPollsList:function(target){
		var This=this;
		$('.pollsList').remove();
		This.addpolls_list=undefined;
		$.get(This.service_uri,{f: 'getOtherPolls',id: This.post_id},function(data){
			data=$(data);
			if(data.find('rsp').attr('status')=='ok'){
				data.find('poll').each(function(){
					var id=$(this).attr('id');
					var title=$(this).text();
					if(id.length>0){if(title.length>0){
						var exists_poll=$('.postPollLine-'+id).attr('id');
						if(exists_poll==undefined){
							if(This.addpolls_list==undefined){
								This.addpolls_list=$('<ul></ul>').addClass('pollsList');
								$(target).after(This.addpolls_list).after($('<p></p>').text(This.text_choose_poll).addClass('postPollAdd'));
							}
							$(This.addpolls_list).append($('<li></li>').addClass('pollsLine-'+id).attr('id',id).append($('<a></a>').text(title).attr('href','#').click(function(){This.addPoll(this,id,title);return false;})));
						}
					}}
				});
				if(This.addpolls_list==undefined){
					$(target).after($('<p></p>').text(This.text_no_poll).addClass('postPollAdd'));
				}
				$(target).remove();
			}else{
				alert(data.find('message').text());
			}
		});
	},

	addPoll:function(target,id,title){
		var This=this;
		if(This.post_id!=0){
			$.post(This.service_uri,{f:'addPollPost', xd_check:dotclear.nonce, post_id:This.post_id, poll_id:id},function(data){
				data=$(data);
				if(data.find('rsp').attr('status')=='ok'){
				} else {
					alert(data.find('message').text());
				}
			});
		}
		This.target.append($('<input/>').attr({'type':'hidden','title':title,'name':'pollspostlist[]','value':id}).addClass('postPollInput'));
		$(target).parent().remove();
		This.displayPostPollsList();
		This.displayAddPollLink();
	}
}