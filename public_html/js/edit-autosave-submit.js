// Copyright 2009 President and Fellows of Harvard College
// Author: Tom Clegg

var editable_currently_highlighted = false;
var editable_have_unsaved = false;
var editable_have_unsubmitted = false;
var editable_save_request = false;
var editable_save_result = {};
var editable_monthnames = 'Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec'.split(' ');

function editable_click (e)
{
    if (!$('edited_' + e.id)) {
	e.insert(editable_input(e));
    }
    $('preview_' + e.id).style.display='none';
    $('edited_' + e.id).style.display='inline';
    $('edited_' + e.id).focus();
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
    edited = e;
    preview = $('preview' + e.id.sub('edited',''));
    if (edited && preview) {
	preview.update(superTextile(edited.value));
	preview.style.display='inline';
	edited.style.display='none';
    }
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

    // Build the input (textarea or input, depending on target size)
    ids = 'name="edited_' + e.id + '" id="edited_' + e.id + '" onkeyup="editable_check_unsaved(this)" onblur="editable_preview(this)"';
    if (xy[1] == 1) { ret = '<input ' + ids + ' type="text" size="' + xy[0] + '" value="' + $('orig_'+e.id).value.htmlentities() + '" />'; }
    else { ret = '<textarea ' + ids + ' type="text" rows="' + xy[1] + '" cols="' + xy[0] + '">' + $('orig_'+e.id).value.htmlentities() + '</textarea>'; }

    return ret;
}

function editable_save (submit_flag)
{
    if (editable_save_request) {
	editable_save_request.transport.abort();
	editable_save_request = false;
    }
    params = editable_save_result;
    if (submit_flag)
	params.submit_flag = true;
    params.save_time = (new Date()).getTime();
    editable_save_request = $('mainform').request({
	    onSuccess: function(transport) {
		editable_save_result = transport.responseJSON;
		editable_save_result.last_save_time = transport.request.parameters.save_time;
		editable_check_unsaved_all ();
		if (editable_save_result.please_reload)
		    window.location.href = window.location.href;
	    },
	    parameters: params
	});
}

function editable_check_unsaved (e, norecurse)
{
    if (!e) return;
    if (norecurse && editable_have_unsaved && editable_have_unpublished) return;

    e_orig_value = $(e.id.sub("edited","orig")).value;

    // compare to last version confirmed saved by server -- if no
    // edits have been saved, compare to the original (latest/release)
    // version
    e_saved = $(e.id.sub("edited","saved"));
    if (e_saved)
	e_saved_value = e_saved.value;
    else if (eval ('editable_save_result.' + e.id.sub("edited","saved")))
	e_saved_value = eval ('editable_save_result.' + e.id.sub("edited","saved"));
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

function editable_init ()
{
    $$('span.editable').each(function(e){
	    Event.observe(e, 'click', function () { editable_click (e); });
	    Event.observe(e, 'mouseover', function () { editable_highlight (e, true); });
	    Event.observe(e, 'mouseout', function () { editable_highlight (e, false); });
	});
    new PeriodicalExecuter (editable_update_last_saved, 5);
}
addEvent(window,'load',editable_init);
