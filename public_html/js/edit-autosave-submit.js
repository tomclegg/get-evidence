// Copyright 2009 Scalable Computing Experts
// Author: Tom Clegg

var editable_currently_highlighted = false;
var editable_have_unsaved = false;
var editable_have_unsubmitted = false;
var editable_save_request = false;
var editable_save_result = {};
var editable_monthnames = 'Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec'.split(' ');

function editable_make (id, content)
{
    return '<SPAN id="'+id+'" class="editable"><SPAN id="preview_'+id+'">'+content+'</SPAN><INPUT type="hidden" id="orig_'+id+'" value="'+content.htmlentities()+'"/>&nbsp;</SPAN>';
}

function editable_decorate (e)
{
    if ($('ebutton_'+e.id))
	return;
    if (!$('toolbar_'+e.id))
	e.insert({top: '<P id="toolbar_'+e.id+'" class="toolbar"></P>'});
    $('toolbar_'+e.id).className = 'toolbar';
    $('toolbar_'+e.id).insert
	('<SPAN class="toolbar_span">'
	 + '<A href="#" id="pbutton_'+e.id+'" onclick="return editable_preview($(\''+e.id+'\'))" style="display:none;" class="toolbar_tab">Preview</A>'
	 + '<A href="#" id="ebutton_'+e.id+'" onclick="return editable_click($(\''+e.id+'\'))" class="toolbar_tab">Edit</A>'
	 + ((/_a_0_/.exec(e.id) && /_g_0_/.exec(e.id))
	    ? ''
	    : '<A href="#" id="ebutton_'+e.id+'" onclick="return editable_delete($(\''+e.id+'\'))" class="toolbar_tab">Delete</A>')
	 + '</SPAN>');
}

function editable_delete (e)
{
    if (!confirm ("Are you sure you want to delete this item?"))
	return;
    v = (/_v_([0-9]+?)__/.exec('_'+e.id))[1];
    a = (/_a_([0-9]+?)__/.exec('_'+e.id))[1];
    g = (/_g_([0-9]+?)__/.exec('_'+e.id))[1];
    var x = {
	method: "post",
	parameters: { v: v, a: a, g: g, e_id: e.id },
	onSuccess: function(transport) {
	    if (transport.responseJSON.deleted)
		e.remove();
	}
    };
    new Ajax.Request ('/delete.php', x);
    return false;
}

function editable_click (e)
{
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
    $('pbutton_' + e.id).style.display='';
    $('pbutton_' + e.id).className='toolbar_tab';
    $('ebutton_' + e.id).className='toolbar_tab toolbar_tab_current';
    if ($('edited_' + e.id).nodeName != 'SELECT')
	$('edited_' + e.id).style.width='100%';
    return false;
}

function editable_highlight (e, flag)
{
    e = $('preview_' + e.id);
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
    unTip();
    preview = $('preview_' + e.id.sub('^edited_',''));
    edited = $('edited_' + e.id.sub('^edited_',''));
    e = $(e.id.sub('^edited_',''));

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
    return this.sub('&','&amp;').sub('"','&quot;').sub('<','&lt;').sub('>','&gt;');
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

    return '<p>' + ret + '</p>';
}

function editable_save (submit_flag, want_preview)
{
    if (editable_save_request) {
	editable_save_request.transport.abort();
	editable_save_request = false;
    }
    params = editable_save_result;
    if (!params)
	params = {};
    if (submit_flag)
	params.submit_flag = true;
    if (want_preview)
	params.want_preview_id = want_preview.id;
    params.save_time = (new Date()).getTime();
    editable_save_request = $('mainform').request({
	    onSuccess: function(transport) {
		if (!transport.responseJSON)
		    // TODO: show error in message box
		    return;
		editable_save_result = transport.responseJSON;
		editable_save_result.last_save_time = transport.request.parameters.save_time;
		editable_check_unsaved_all ();
		if (editable_save_result.please_reload)
		    window.location.reload();
		// TODO: show errors (if any) in message box

		$$('span.editable').each(function(e){
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
    $$('span.editable').each(function(e){
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
		$$('span.editable').each(function(e){
			var draft_id = (/__p_([a-z0-9A-Z_]+?)__/.exec(e.id))[1]
			    + '__'
			    + (/__f_([a-z0-9A-Z_]+?)__/.exec(e.id))[1];
			if ((saved = eval ('editable_save_result.saved__' + draft_id))) {
			    if (saved != $('orig_'+e.id).value) {
				if ($('edited_'+e.id))
				    $('edited_'+e.id).value = saved;
				editable_click(e);
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

    e_orig_value = $(e.id.sub("edited","orig")).value;

    // compare to last version confirmed saved by server -- if no
    // edits have been saved, compare to the original (latest/release)
    // version
    e_saved = $(e.id.sub("edited","saved"));
    if (e_saved)
	e_saved_value = e_saved.value;
    else if (editable_save_result &&
	     (e_saved_value = eval ('editable_save_result.saved__'
				    + (/__p_([a-z0-9A-Z_]+?)__/.exec(e.id))[1]
				    + '__'
				    + (/__f_([a-z0-9A-Z_]+?)__/.exec(e.id))[1])))
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
    $$('span.editable').each(function(x){
	    editable_check_unsaved ($('edited_' + x.id), true);
	});
    editable_update_unsaved_message ();
}

function editable_update_unsaved_message ()
{
    if (editable_have_unsaved || editable_have_unsubmitted) {
	message = '<p>While you are editing, you should save often in case your connection is interrupted.<br /><button id="_editable_save" onclick="editable_save()">Save draft</button><br /><span id="editable_last_saved" style="font-size: .8em">' + editable_last_saved() + '</span></p>';
	if (editable_have_unsubmitted) {
	    message += '<p>When you have finished editing, submit your edits to the database.<br /><button onclick="editable_save(true)">Submit changes</button>';
	}
	message_update (message);
	$('_editable_save').disabled = !editable_have_unsaved;
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
    /*
      p = $('preview_'+e.id);
      Event.observe(p, 'click', function () { editable_click (e); });
      Event.observe(p, 'mouseover', function () { editable_highlight (e, true); });
      Event.observe(p, 'mouseout', function () { editable_highlight
      (e, false); });
    */
    editable_decorate (e);
}

function editable_init ()
{
    if (!$('mainform')) return;
    $$('span.editable').each(function(e){ editable_init_single (e); });
    new PeriodicalExecuter (editable_update_last_saved, 5);
    new Form.Observer ($('mainform'), 3, editable_check_unsaved_all);
    editable_get_draft();
}
addEvent(window,'load',editable_init);
