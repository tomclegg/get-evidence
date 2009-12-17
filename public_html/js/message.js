// Copyright 2009 Scalable Computing Experts
// Author: Tom Clegg

var message_current = false;

function message_update (newtext)
{
    if (!newtext) {
	$('message').style.display='none';
	$('message').update('');
	message_current = false;
    }
    else {
	message_current = { text: newtext };
	$('message').update('<P>' + newtext.sub(/^<[Pp]>/,"").sub(/<\/[Pp]>$/,"") + '</P>');
	$('message').style.display='block';
    }
}

function message_init()
{
    if ($('message').innerHTML) {
	message_current = { text: $('message_init').innerHTML };
    }
}
addEvent(window,'load',message_init);
