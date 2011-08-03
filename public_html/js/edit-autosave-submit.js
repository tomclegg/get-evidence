// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

var editable_currently_highlighted = false;
var editable_have_unsaved = false;
var editable_have_unsubmitted = false;
var editable_save_request = false;
var editable_save_result = {};
var editable_monthnames = 'Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec'.split(' ');

function editable_5star_click (eid, newrating)
{
    if (!$('edited_' + eid))
	$(eid).insert('<INPUT type="hidden" name="edited_'+eid+'" id="edited_'+eid+'" value="">');
    $('edited_' + eid).value = newrating;
    $('preview_'+eid).innerHTML = newrating;
    for (i=-1; i<=5; i++) {
	var starimg = $('star'+(i<0 ? 'N'+(-i) : i)+'_'+eid);
	if (!starimg || i==0) continue;
	if (i < 0) color = (i <= newrating && newrating < 0) ? 'red' : 'white';
	if (i > 0) color = (i <= newrating) ? 'blue' : 'white';
	starimg.src = '/img/star-' + color + '16.png';
	if (newrating != 0)
	    starimg.removeClassName ('halfthere');
	else
	    starimg.addClassName ('halfthere');
    }
    if($('rationale_' + eid) && !$(eid+'__text')) {
	$('rationale_' + eid).update (evidence_rationale_form (eid));
	$(eid+'__text').style.width = '100%';
    }
}

function editable_make (id, content)
{
    return '<SPAN id="'+id+'" class="editable"><SPAN id="preview_'+id+'">'+content+'</SPAN><INPUT type="hidden" id="orig_'+id+'" value="'+content.htmlentities()+'"/>&nbsp;</SPAN>';
}

function editable_decorate (e)
{
    if ($('ebutton_'+e.id))
	return;
    if (!$('toolbar_'+e.id))
	e.insert({top: '<DIV id="toolbar_'+e.id+'" class="toolbar"></DIV>'});
    $('toolbar_'+e.id).className = 'toolbar';
    var toolbar_html = '<P class="toolbar_span">';
    if (e.hasClassName ('editable')) {
	toolbar_html += '<A href="#" id="pbutton_'+e.id+'" onclick="return editable_preview($(\''+e.id+'\'))" style="display:none;" class="toolbar_tab">Preview</A>';
	toolbar_html += '<A href="#" id="ebutton_'+e.id+'" onclick="return editable_click($(\''+e.id+'\'))" class="toolbar_tab">Edit</A>';
    }
    if (e.hasClassName ('editable') &&
	!/_a_0_/.exec(e.id) &&
	/_f_summary_short/.exec(e.id)) {
	toolbar_html += '<A href="#" id="ebutton_'+e.id+'" onclick="return editable_delete($(\''+e.id+'\'))" class="toolbar_tab">Delete</A>';
    }
    if (/_f_talk_text/.exec(e.id)) {
	toolbar_html += '<A href="#" id="hbutton_'+e.id+'" onclick="return show_what_click($(\''+e.id+'\'))" class="toolbar_tab">Hide</A>';
    }
    toolbar_html += '</P>';
    $('toolbar_'+e.id).insert (toolbar_html);
}

function editable_delete (e)
{
    if (!confirm ("Are you sure you want to delete this item?"))
	return;
    v = (/_v_([0-9]+?)__/.exec('_'+e.id))[1];
    a = (/_a_([0-9]+?)__/.exec('_'+e.id))[1];
    g = (/_g_([0-9]+?)__/.exec('_'+e.id))[1];
    d = (/_d_([0-9]+?)__/.exec('_'+e.id))[1];
    var x = {
	method: "post",
	parameters: { v: v, a: a, g: g, d: d, e_id: e.id },
	onSuccess: function(transport) {
	    if (transport.responseJSON.deleted) {
		$$('.delete_with_v'+v+'_a'+a+'_g'+g).each(function(dw){
			dw.remove();
		    });
		e.remove();
	    }
	}
    };
    new Ajax.Request ('/delete.php', x);
    return false;
}

function editable_click (e)
{
    editable_highlight (e, false);
    if (!$('edited_' + e.id)) {
	e.insert(editable_input(e));
	if ($('tip_' + e.id)) {
	    Event.observe($('edited_' + e.id), 'mouseover',
			  function () { TagToTip('tip_' + e.id,
						 FIX, ['edited_'+e.id, 0, 0],
						 FOLLOWMOUSE, false,
						 BALLOON, true,
						 ABOVE, true,
						 WIDTH, -240); });
	    Event.observe($('edited_' + e.id), 'mouseout',
			  function () { UnTip(); });
	}
    }
    $('preview_' + e.id).style.display='none';
    $('edited_' + e.id).style.display='';
    $('edited_' + e.id).parentNode.style.display='';
    $('edited_' + e.id).focus();
    if ($('pbutton_' + e.id)) {
	$('pbutton_' + e.id).style.display='';
	$('pbutton_' + e.id).className='toolbar_tab';
	$('ebutton_' + e.id).className='toolbar_tab toolbar_tab_current';
    }
    if ($('edited_' + e.id).nodeName != 'SELECT')
	$('edited_' + e.id).style.width='100%';
    return false;
}

function editable_highlight (e, flag)
{
    if ($('preview_' + e.id).style.display == 'none')
	flag = false;

    if(editable_currently_highlighted) {
	editable_currently_highlighted.style.backgroundColor = '';
	editable_currently_highlighted.descendants().each(function(e) {
		e.style.backgroundColor = '';
	    });
    }
    if (flag) {
	e.style.backgroundColor = '#ffb';
	e.descendants().each(function(ee) {
		ee.style.backgroundColor = '#ffb';
	    });
	editable_currently_highlighted = e;
    }
    else {
	editable_currently_highlighted = false;
    }
}

function editable_preview (e)
{
    UnTip();
    preview = $('preview_' + e.id.replace(/^edited_/,''));
    edited = $('edited_' + e.id.replace(/^edited_/,''));
    e = $(e.id.replace(/^edited_/,''));

    editable_check_unsaved_all ();
    if (editable_have_unsaved) {
	editable_save (false, preview);
	// TODO: say "updating preview..." or something
    }

    if (edited && preview) {
	preview.style.display='';
	edited.style.display='none';
	if (edited.parentNode.nodeName == 'P')
	    edited.parentNode.style.display='none';
	$('pbutton_' + e.id).className='toolbar_tab toolbar_tab_current';
	$('ebutton_' + e.id).className='toolbar_tab';
    }
    return false;
}

function editable_unfocus (e)
{
    // editable_preview(e);
}

String.prototype.htmlentities = function ()
{
    return this.replace('&','&amp;').replace('"','&quot;').replace('<','&lt;').replace('>','&gt;');
}

function editable_input (e)
{
    // Figure out the requested size, make sure it's reasonable
    xy = /([0-9]+)x([0-9]+)/.exec(e.id);
    if (!xy) { xy = [0,70,1]; }
    xy = [xy[1], xy[2]];
    if (xy[0] < 8) { xy[0] = 70; }
    if (xy[1] < 1) { xy[1] = 1; }

    if (!editable_save_result ||
	!(saved_value = eval ('editable_save_result.saved__'
			      + (/__p_([a-z0-9A-Z_]+?)__/.exec(e.id))[1]
			      + '__'
			      + (/__f_([a-z0-9A-Z_]+?)__/.exec(e.id))[1])))
	saved_value = $('orig_'+e.id).value;

    // Build the input (textarea or input, depending on target size)
    ids = 'name="edited_' + e.id + '" id="edited_' + e.id + '" onkeyup="editable_check_unsaved(this)" onblur="editable_unfocus(this)"';
    if (xy[1] == 1) {
	ret = '<input ' + ids + ' type="text" size="' + xy[0] + '" value="' + saved_value.htmlentities() + '" />';
    }
    else {
	ret = '<textarea ' + ids + ' type="text" rows="' + xy[1] + '" cols="' + xy[0] + '">' + saved_value.htmlentities() + '</textarea>';
    }

    if (xy[1] > 1)
	ret = '<p>' + ret + '</p>';

    return ret;
}

function editable_save (submit_flag, want_preview)
{
    var options = {};
    if (typeof submit_flag == "object") {
	// called as editable_save({...})
	options = submit_flag;
    } else {
	// called as editable_save(submitflag,previewflag) (deprecated)
	options.submit = submit_flag;
	options.preview = want_preview;
    }
    if (editable_save_request) {
	editable_save_request.transport.abort();
	editable_save_request = false;
    }
    params = editable_save_result;
    if (!params)
	params = {};
    if (options.submit)
	params.submit_flag = true;
    if (options.preview)
	params.want_preview_id = options.preview.id;
    else
	delete params.want_preview_id;
    params.signoff_flag = !!options.signoff;
    params.save_time = (new Date()).getTime();

    // copy keys from variant quality rationale
    r = evidence_rationale_compose();
    Object.keys(r).each(function(rkey){
	    params['edited_'+rkey] = Object.toJSON (r[rkey]);
	});

    editable_save_request = $('mainform').request({
	    onSuccess: function(transport) {
		if (!transport.responseJSON)
		    // TODO: show error in message box
		    return;
		editable_save_result = transport.responseJSON;
		editable_save_result.last_save_time = transport.request.parameters.save_time;
		editable_check_unsaved_all ();
		if (editable_save_result.please_signoff)
		    curator_signoff(editable_save_result.please_signoff);
		else if (editable_save_result.please_reload)
		    window.location.reload();
		// TODO: show errors (if any) in message box

		$$('.editable').each(function(e){
			p = eval('transport.responseJSON.preview_'+e.id);
			if (p)
			    $('preview_'+e.id).update(p);
		    });
	    },
	    onFailure: function(transport) {
		// TODO: show error in message box
	    },
	    parameters: params
	});
}

function editable_get_draft ()
{
    if (editable_save_result.last_save_time || editable_save_request || !$('mainform'))
	return;

    var edit_ids = new Hash();
    $$('.editable').each(function(e){
	    if ((r = /_p_([a-z0-9A-Z_]+?)__/.exec(e.id)))
		edit_ids.set(r[1], 1);
	});
    new Ajax.Request ('getdraft.php', {
	    method: 'get',
	    parameters: { edit_ids: edit_ids.keys().join('-') },
	    onSuccess: function(transport) {
		if (transport.responseJSON)
		    editable_save_result = transport.responseJSON;
		editable_check_unsaved_all ();
		$$('.editable').each(function(e){
			var eid = (/__p_([a-z0-9A-Z_]+?)__/.exec(e.id))[1]
			    + '__'
			    + (/__f_([a-z0-9A-Z_]+?)__/.exec(e.id))[1];
			var splitfield = /__o_([a-z0-9A-Z_]+?)__/.exec(e.id);
			draft_id = eid;
			if (splitfield)
			    draft_id += '__' + splitfield[1];
			if ((saved = editable_save_result['saved__' + draft_id])) {
			    if (saved != $('orig_'+e.id).value) {
				if (e.hasClassName ("5star"))
				    editable_5star_click (e.id, saved);
				else if (e.hasClassName ("editable-bionotate"))
				    jQuery('div.bionotate[bnkey='+jQuery(e).attr('bnkey')+']').trigger('bionotate-render',{xml:saved});
				else
				    editable_click(e);
				if ($('edited_'+e.id))
				    $('edited_'+e.id).value = saved;
			    }
			}
			if (e.hasClassName ("5star") && splitfield) {
			    // Check for saved rationale
			    if ((saved_text = editable_save_result['saved__'+eid+'_text'])) {
				if (!object_data_equal (saved_text[splitfield[1]],
							evidence_rationale_orig (e.id))) {
				    editable_5star_click (e.id, saved);
				}
			    }
			}
			p = eval('editable_save_result.preview__' + draft_id);
			if (p)
			    $('preview_'+e.id).update(p);
		    });
	    }
	});
}

function editable_check_unsaved (e, norecurse)
{
    if (!e) return;
    if (norecurse && editable_have_unsaved && editable_have_unsubmitted) return;

    e_orig_value = $(e.id.replace("edited","orig")).value;

    save_result_id = 'saved__'
	+ (/__p_([a-z0-9A-Z_]+?)__/.exec(e.id))[1]
	+ '__'
	+ (/__f_([a-z0-9A-Z_]+?)__/.exec(e.id))[1];
    var extra = /__o_([a-z0-9A-Z_]+?)__/.exec(e.id);
    if (extra && extra[1])
	save_result_id = save_result_id + '__' + extra[1];

    // compare to last version confirmed saved by server -- if no
    // edits have been saved, compare to the original (latest/release)
    // version
    e_saved = $(e.id.replace("edited","saved"));
    if (e_saved)
	e_saved_value = e_saved.value;
    else if (editable_save_result &&
	     (e_saved_value = eval ('editable_save_result.' + save_result_id)))

	;
    else
	e_saved_value = e_orig_value;

    if (e_saved_value != e.value) {
	// field has been changed since last save (or since original
	// if no save yet)
	editable_have_unsaved = editable_have_unsubmitted = true;
	if (!norecurse)
	    editable_update_unsaved_message ();
    }
    else if (e_orig_value != e.value) {
	// field has been saved but does not match original entry;
	// offer to submit saved changes
	editable_have_unsubmitted = true;
	if (!norecurse)
	    editable_update_unsaved_message ();
    }
    else if (norecurse)
	// "search for unsaved" is in progress, this is !dirty, so
	// nothing to do.
	;
    else if (!editable_have_unsaved && !editable_have_unsubmitted)
	// only checking this field (during onKeyUp), saved state is
	// correct, nothing to do.
	;
    else
	// only checking this field, saved state is dirty, this field
	// is !dirty; perhaps this was the only dirty field and the
	// "you have unsaved changes" box should be removed.  check
	// all fields.
	editable_check_unsaved_all ();
}

function editable_check_unsaved_all ()
{
    editable_have_unsaved = false;
    $$('.editable').each(function(x){
	    editable_check_unsaved ($('edited_' + x.id), true);
	});

    if (!editable_have_unsaved) {
	r = evidence_rationale_compose();

	Object.keys(r).each(function(rkey){
		savekey = 'saved__'
		    + (/__p_([a-z0-9A-Z_]+?)__/.exec(rkey))[1]
		    + '__'
		    + (/__f_([a-z0-9A-Z_]+?)__/.exec(rkey))[1];
		if (editable_save_result &&
		    editable_save_result[savekey])
		    saved = editable_save_result[savekey];
		else if ($('orig_'+rkey))
		    saved = $('orig_'+rkey).value.evalJSON();
		else
		    saved = [{text:'',seealso:[]},{text:'',seealso:[]},{text:'',seealso:[]},{text:'',seealso:[]},{text:'',seealso:[]}];

		orig = $('orig_'+rkey).value;
		if (orig == '')
		    orig = [{text:'',seealso:[]},{text:'',seealso:[]},{text:'',seealso:[]},{text:'',seealso:[]},{text:'',seealso:[]}];
		else
		    orig = orig.evalJSON();

		if (!object_data_equal (r[rkey], saved)) {
		    editable_have_unsaved = true;
		    editable_have_unsubmitted = true;
		}
		else if (!object_data_equal (saved, orig))
		    editable_have_unsubmitted = true;
	    });
    }

    editable_update_unsaved_message ();
}

function editable_update_unsaved_message ()
{
    if (editable_have_unsaved || editable_have_unsubmitted) {
	message = 'While you are editing, you should save often in case your connection is interrupted.<br /><button id="_editable_save" onclick="editable_save()" class="ui-state-highlight">Save draft</button><br /><span id="editable_last_saved" style="font-size: .8em">' + editable_last_saved() + '</span>';
	if (editable_have_unsubmitted) {
	    message += '<br /><br />When you have finished editing, submit your edits to the database.<br /><button id="_editable_submit" onclick="editable_save(true)" class="ui-state-highlight">Submit changes</button>';
	}
	message_update (message);
	jQuery("#_editable_save").button({icons:{primary:'ui-icon-disk'}});
	jQuery("#_editable_submit").button({icons:{primary:'ui-icon-circle-arrow-e'}});
	// $('_editable_save').disabled = !editable_have_unsaved;
	jQuery("#_editable_save").button("option", "disabled", !editable_have_unsaved);
	jQuery("#_editable_save").toggleClass("ui-state-highlight", editable_have_unsaved);
	jQuery("#curator-signoff-edited").closest("span").show();
	jQuery("#curator-signoff-orig").button("option","disabled",true);
    }
    else
	message_update (false);
}

function editable_last_saved ()
{
    if (editable_save_result && editable_save_result.last_save_time) {
	var s = ((new Date()).getTime() - editable_save_result.last_save_time) / 1000;
	if (s <= 5) return "Saved a few seconds ago";
	if (s < 60) return "Saved " + (5*Math.floor(s/5)) + " seconds ago";
	d = new Date(editable_save_result.last_save_time);
	var t = "Saved "
	    + (d.getHours() % 12 == 0 ? 12 : (d.getHours() % 12)) + ':'
	    + (d.getMinutes() < 10 ? '0' : '') + d.getMinutes() + ' '
	    + (d.getHours() < 12 ? 'am' : 'pm');
	if (s > 3600 * 12)
	    t += ' '
		+ (editable_monthnames[d.getMonth()]) + ' '
		+ d.getDate();
	return t;
    }
    return "&nbsp;";
}

function editable_update_last_saved ()
{
    if ($('editable_last_saved'))
	$('editable_last_saved').update(editable_last_saved());
}

// Bootstrap: attach the click handler to class="editable" spans

function editable_init_single (e)
{
    if (e.hasClassName ('clicktoedit')) {
	Event.observe(e, 'click', function () { editable_click (e); });
	Event.observe(e, 'mouseover', function () { editable_highlight (e, true); });
	Event.observe(e, 'mouseout', function () { editable_highlight (e, false); });
    }
    else if (!e.hasClassName ('5star'))
	editable_decorate (e);
}

function editable_init ()
{
    if (!$('mainform')) return;
    $$('.editable,.uneditable').each(function(e){ editable_init_single (e); });
    new PeriodicalExecuter (editable_update_last_saved, 5);
    new Form.Observer ($('mainform'), 3, editable_check_unsaved_all);
    editable_get_draft();
}
addEvent(window,'load',editable_init);
