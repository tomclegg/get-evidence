// Copyright 2009 President and Fellows of Harvard College
// Author: Tom Clegg

var editable_currently_highlighted = false;
var editable_is_dirty = false;
var editable_save_request = false;
var editable_save_result = {};

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
    ids = 'name="edited_' + e.id + '" id="edited_' + e.id + '" onkeyup="editable_checkdirty(this)" onblur="editable_preview(this)"';
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
    params.submit_flag = submit_flag;
    editable_save_request = $('mainform').request({
	    onSuccess: editable_save_success,
	    parameters: params
	});
}

function editable_save_success (response)
{
    editable_save_result = response.responseJSON();
}

function editable_checkdirty (e, norecurse)
{
    if (!e) return;
    if (norecurse && editable_is_dirty) return;

    e_saved = $(e.id.sub("edited","saved"));
    if (!e_saved)
	e_saved = $(e.id.sub("edited","orig"));

    if (e_saved.value == e.value) {
	if (norecurse || !editable_is_dirty) return;
	editable_is_dirty = false;
	$$('span.editable').each(function(x){
		editable_checkdirty ($('edited_' + x.id), true);
	    });
	if (!editable_is_dirty) {
	    message_update (false);
	}
	return;
    }

    editable_is_dirty = true;
    message_update ('<p>You have unsaved edits.</p><p>While you are editing, you should save often in case your connection is interrupted.<br /><button onclick="editable_save()">Save draft</button></p><p>When you have finished editing, submit your edits to the database.<br /><button onclick="editable_save(true)">Submit changes</button></p>');
}

// Bootstrap: attach the click handler to class="editable" spans

function editable_init ()
{
    $$('span.editable').each(function(e){
	    Event.observe(e, 'click', function () { editable_click (e); });
	    Event.observe(e, 'mouseover', function () { editable_highlight (e, true); });
	    Event.observe(e, 'mouseout', function () { editable_highlight (e, false); });
	});
}
addEvent(window,'load',editable_init);
