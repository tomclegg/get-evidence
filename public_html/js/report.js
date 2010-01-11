// Copyright 2009 Scalable Computing Experts
// Author: Tom Clegg

var reportpage_on = false;

function reportpage_goto(p)
{
    $$(reportpage_on
       ? 'tr.reportpage_'+reportpage_on
       : 'tr.reportpage').each(function(e){e.style.display='none';});
    $$('tr.reportpage_'+p).each(function(e){e.style.display='table-row';});
    reportpage_on = p;
    reportpage_update_turnbuttons();
    return false;
}

function reportpage_update_turnbuttons()
{
    $$('a.reportpage_turnbutton').each(function(e){
	    e.style.fontWeight = e.innerHTML == reportpage_on ? 'bold' : 'normal';
	    e.style.color = e.innerHTML == reportpage_on ? 'black' : '';
	    e.style.textDecoration = e.innerHTML == reportpage_on ? 'none' : '';
	});
}

function reportpage_init()
{
    reportpage_on = 1;
    if ($('reportpage_turner_copy'))
	$('reportpage_turner_copy').update($('reportpage_turner').innerHTML);
    reportpage_update_turnbuttons();
}
