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

$(function(){
	if(!document.getElementById){return;}
	/* Posts list actions */
	var postTarget=$('#pollsfactory-entries');
	var poll_id=$('input[name=id]');
	poll_id=(poll_id.length>0)?poll_id.get(0).value:false;
	if(postTarget.length>0){
		var pfEdit = new pollsFactoryAddEditor(postTarget,poll_id);
		if(poll_id!=false){
			$('#pollsfactory-entries').empty();
			pfEdit.displayPollPostsList()
		}
	}
	/* poll page preview */
	$('#poll-preview').modalWeb($(window).width()-40,$(window).height()-40);
});

function pollsFactoryAddEditor(target,poll_id){
	this.target=target;
	this.poll_id=poll_id;
};

pollsFactoryAddEditor.prototype={

	target:'',
	poll_id:false,
	text_title:'Related posts',
	text_remove_post:'Are you sure you want to remove this post?',
	service:'services.php',

	displayPollPostsList:function(){
		var This=this;
		$('.postPoll').remove();
		$('.postPollList').remove();

		This.posts_list=$('<ul></ul>').addClass('postPollList');
		This.target.append($('<h3></h3>').addClass('postPoll').append(This.text_title)).after(This.posts_list);

		$.get(This.service,{f: 'getPostsOfPoll',id: This.poll_id},function(data){
			data=$(data);
			if(data.find('rsp').attr('status')=='ok'){
				data.find('post').each(function(){
					var id=$(this).attr('id');
					var url=$(this).attr('url');
					url=url.replace(/&amp;/g,"&");
					var type=$(this).attr('type');
					var title=$(this).text();
					if (id.length>0){if(title.length>0){
						var cross = $('<a></a>').text('[x]').attr('href','#').addClass('postPollRemove').click(function(){This.removePost(this,id);return false;});
						$(This.posts_list).append(
							$('<li></li>').addClass('postPollLine-'+id).attr('id',id).append(
								$('<a></a>').text(title).attr('href',url).attr('title',type)
							).append(' ').append(cross)
						);
					}}
				});
			} else {
				alert(data.find('message').text());
			}
		});
	},

	removePost:function(target,id){
		var This=this;
		if(window.confirm(This.text_remove_post)){
			$.post(This.service,{f:'removePollPost', xd_check:dotclear.nonce, poll_id:This.poll_id, post_id:id},function(data){
				data=$(data);
				if(data.find('rsp').attr('status')=='ok'){
					$(target).parent().remove();
					This.displayPollPostsList();
				} else {
					alert(data.find('message').text());
				}
			});
		}
	}
}