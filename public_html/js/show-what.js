// Copyright 2010 Scalable Computing Experts
// Author: Tom Clegg

function show_what_click (e)
{
    if (e.hasClassName ('rectangle-speech-border-hidden')) {
	e.removeClassName ('rectangle-speech-border-hidden');
	e.addClassName ('rectangle-speech-border');
    }
    else if (e.hasClassName ('rectangle-speech-border')) {
	if (e.hasClassName ('wanttohide')) {
	    e.removeClassName ('rectangle-speech-border');
	    e.removeClassName ('wanttohide');
	    e.addClassName ('rectangle-speech-border-hidden');
	}
    }
    else {
	while (e = e.parentNode) {
	    if (e.hasClassName ('rectangle-speech-border')) {
		e.addClassName ('wanttohide');
		break;
	    }
	}
    }
    return false;
}

function show_what_init ()
{
    $$('.rectangle-speech-border,.rectangle-speech-border-hidden').each
	(function(e) {
	    Event.observe(e, 'click', function() { show_what_click (e) });
	});
}
addEvent(window,'load',show_what_init);
