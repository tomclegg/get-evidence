// Copyright 2009 President and Fellows of Harvard College
// Author: Tom Clegg

var editable_currently_highlighted = false;

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

function editable_preview (eid)
{
    if ($('edited_' + eid) && $('preview_' + eid)) {
	$('preview_' + eid).update(superTextile($('edited_' + eid).value));
    }
    $('preview_' + eid).style.display='inline';
    $('edited_' + eid).style.display='none';
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
    ids = 'name="edited_' + e.id + '" id="edited_' + e.id + '" onblur="editable_preview(\'' + e.id + '\')"';
    if (xy[1] == 1) { ret = '<input ' + ids + ' type="text" size="' + xy[0] + '" value="' + $('orig_'+e.id).value.htmlentities() + '" />'; }
    else { ret = '<textarea ' + ids + ' type="text" rows="' + xy[1] + '" cols="' + xy[0] + '">' + $('orig_'+e.id).value.htmlentities() + '</textarea>'; }

    return ret;
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
