// Copyright: see COPYING
// Authors: see git-blame(1)

function evidence_add_article (v, a)
{
    new Ajax.Request('add.php', {
	    method: 'post',
		parameters: { variant_id: v, article_pmid: a },
		onSuccess: function(transport) {
		var a = transport.request.parameters.article_pmid;
		var v = transport.request.parameters.variant_id;
		var p = transport.responseJSON.latest_edit_id;
		var e_id = transport.responseJSON.e_id;
		if ($(e_id)) {
		    // TODO: show error -- article already listed
		    editable_click ($(e_id));
		    return;
		}
		$('article_new').insert(transport.responseJSON.html);
		editable_init_single ($(e_id));
		editable_click ($(e_id));
	    }
    });
    return false;
}

function evidence_add_variant (gene, aa_change, rsid)
{
    var x = {
	    method: 'post',
	    parameters:
	    {
		variant_gene: gene,
		variant_aa_change: aa_change,
		rsid: rsid
	    },
	    onSuccess: function(transport)
	    {
		if (transport.responseJSON.variant_id)
		    window.location.reload();
	    }
    };
    new Ajax.Request('add.php', x);
    return false;
}

var evidence_articles_list = new Array();
function evidence_list_articles ()
{
    evidence_articles_list = new Array();
    $$('.toolbar_title').each(function(e) {
	    var regs = /__a_(.+?)__/.exec(e.parentNode.id);
	    if (!regs) return;
	    var article_id = regs[1];
	    if (article_id == 0 || article_id == '0') return;
	    var author_regs = /(.+?)([\.,])/.exec (e.innerHTML);
	    if (!author_regs) return;
	    var authors = author_regs[1];
	    if (author_regs[2] == ',')
		authors = authors + ' et al.';
	    var plainsummary = e.innerHTML.replace(/<.*?>/g, '');
	    plainsummary = plainsummary.replace(/[\t\n]/g,' ');
	    var year_regs = /.*?\..*?\..*?\. (\d\d\d\d) /.exec(plainsummary);
	    var year = year_regs ? year_regs[1] : '';
	    evidence_articles_list.push ({ id: article_id,
			shortcitation: authors + ' ' + year,
			citation: e.innerHTML });
	});
}

function evidence_article_citation_tip (e, article_id)
{
    evidence_list_articles();
    for (var i=0; i<evidence_articles_list.length; i++)
	if (evidence_articles_list[i].id == article_id) {
	    Tip (evidence_articles_list[i].citation,
		 BALLOON,true,
		 FIX,[e,0,0],
		 FOLLOWMOUSE,false,
		 ABOVE,true,
		 WIDTH,-240);
	    return;
	}
}

function evidence_rationale_form (base_id)
{
    evidence_list_articles ();
    var orig = false;
    if (editable_save_result) {
	savekey = 'saved__'
	    + (/__p_([a-z0-9A-Z_]+?)__/.exec(base_id))[1]
	    + '__'
	    + (/__f_([a-z0-9A-Z_]+?)__/.exec(base_id))[1]
	    + '_text';
	oid = (/__o_([a-z0-9A-Z_]+?)__/.exec(base_id))[1];
	if (editable_save_result[savekey] &&
	    editable_save_result[savekey][oid])
	    orig = editable_save_result[savekey][oid];
    }
    if (!orig)
	orig = evidence_rationale_orig (base_id);
    var html = '<TEXTAREA id="'+base_id+'__text" name="'+base_id+'__text" cols="30" rows="3" onmouseover="Tip(\'Explain why this rating is suitable.\',BALLOON,true,FIX,[this,0,0],FOLLOWMOUSE,false,ABOVE,true,WIDTH,-240);" onmouseout="UnTip();">'+orig.text.htmlentities()+'</TEXTAREA>';
    for (var index=0; index<evidence_articles_list.length; index++) {
	var a = evidence_articles_list[index];
	id = base_id + '__check_' + a.id;
	var checked = (orig.seealso && orig.seealso.indexOf(a.id) >= 0)
	    ? 'checked' : '';
	html += '<BR><SPAN onmouseover="evidence_article_citation_tip(this,\''+a.id+'\'));"" onmouseout="UnTip();"><INPUT type="checkbox" id="'+id+'" name="'+id+'" '+checked+'/>&nbsp;'+a.shortcitation+' ('+a.id+')</SPAN>\n';
    }
    var checked = (orig.seealso && orig.seealso.indexOf('0') >= 0)
	? 'checked' : '';
    html += '<BR><INPUT type="checkbox" id="'+base_id+'__check_0" name="'+base_id+'__check_0" '+checked+'/>&nbsp;Unpublished research (below)\n';
    return html;
}

function evidence_rationale_orig (base_id)
{
    var no_rationale = { text: '', seealso: [] };
    var regs = /__o_(\d+)__/.exec (base_id);
    if (!regs) return no_rationale;
    var oindex = parseInt(regs[1]);
    orig = $('orig_'+base_id.replace(/__o_.*/,'_text__'));
    if (!orig) return no_rationale;
    if (!orig.value) return no_rationale;
    orig = orig.value.evalJSON();
    if (!orig || !orig[oindex]) return no_rationale;
    return orig[oindex];
}

function evidence_rationale_compose ()
{
    var all_rationale = new Object ();
    $$('.5star').each(function (star_e) {
	    var base_id = star_e.id;
	    if (!$('rationale_'+base_id)) return;

	    var regs = /^(.*?)__o_(\d+)__/.exec (base_id);
	    if (!regs) return;
	    var text_id = regs[1] + '_text__';
	    var oindex = parseInt(regs[2]);

	    if (!all_rationale[text_id])
		all_rationale[text_id] = new Array();

	    if (!$(base_id+'__text')) {
		all_rationale[text_id][oindex]
		    = evidence_rationale_orig (base_id);
		return;
	    }
	    var seealso = Array();
	    evidence_articles_list.each(function (a) {
		    var e = $(base_id+'__check_'+a.id);
		    if (!e) return;
		    if (!e.checked) return;
		    seealso.push (a.id);
		});
	    if ($(base_id+'__check_0') && $(base_id+'__check_0').checked)
		seealso.push ('0');
	    var rationale = new Object();
	    rationale.text = $(base_id+'__text').value;
	    rationale.seealso = seealso;

	    all_rationale[text_id][oindex] = rationale;
	});
    return all_rationale;
}

function object_data_equal (a, b)
{
    if (Object.isString(a) || Object.isNumber(a) || Object.isUndefined(a))
	return a == b;
    if (Object.isArray(a)) {
	if (!Object.isArray(b)) return false;
	if (a.length != b.length) return false;
	for (var i=0; i<a.length; i++)
	    if (!object_data_equal (a[i], b[i])) return false;
	return true;
    }
    isequal = true;
    Object.keys(a).each(function(k){
	    if (!isequal) return;
	    if (!object_data_equal (a[k], b[k])) isequal = false;
	});
    Object.keys(b).each(function(k){
	    if (!isequal) return;
	    if (!object_data_equal (a[k], b[k])) isequal = false;
	});
    return isequal;
}

function evidence_web_vote (variant_id, voter_element, score)
{
    var url = null;
    if (voter_element)
	url = jQuery(voter_element).attr('vote-url');
    var x = {
	    method: 'get',
	    parameters:
	    {
		'variant_id': variant_id,
		'url': url,
		'score': score
	    },
	    onSuccess: function(transport)
	    {
		var response = transport.responseJSON;
		if (!response) return;
		$$('button.webvoter_result').each(function(e) {
			var wuid = e.id.replace(/^.*_/,'');
			var url = jQuery(e).attr('vote-url');
			var vote = response.all[url];
			var icons = {};
			var color = '#ddd';
			if(vote==1) {
			    icons = {'primary': 'ui-icon-circle-check'};
			    color = '#beb';
			}
			else if(vote==0) {
			    icons = {'primary': 'ui-icon-close'};
			    color = '#ebb';
			}
			var plus = parseInt(response.all['+'+url]);
			var minus = parseInt(response.all['-'+url]);
			if (!(plus >= 0)) plus=0;
			if (!(minus >= 0)) minus=0;
			var label = '+' + plus + ' -' + minus;
			if (label == '+0 -0')
			    label = 'unrated';
			var voteresult = jQuery('#webvoter_all_' + wuid);
			var oldlabel = voteresult.button('option', 'label');
			if (oldlabel != label) {
			    voteresult.button('option', {'icons':icons,'label':label});
			    voteresult.effect('highlight', {}, 500);
			}
			voteresult.find('.ui-button-text').
			    css('background-color', color).
			    css('background-image', 'none');
		    });
		$$('button.webvoter').each(function(e) {
			var url = jQuery(e).attr('vote-url');
			var vote = response.my[url];
			var iscurrent = false;
			if (jQuery(e).hasClass('plus'))
			    iscurrent = vote==1;
			else if (jQuery(e).hasClass('minus'))
			    iscurrent = vote==0;
			else
			    iscurrent = vote==null;
			if (iscurrent) jQuery(e).addClass('ui-state-highlight');
			else jQuery(e).removeClass('ui-state-highlight');
		    });
		if (response.autoscore)
		    $('autoscore_v_'+variant_id).update(response.autoscore);
	    }
    };
    new Ajax.Request('webvote.php', x);
    return false;
}
function evidence_web_vote_setup () {
    jQuery('.webvoter').filter('.plus').button({icons:{primary:"ui-icon-plus"},text:false});
    jQuery('.webvoter').filter('.minus').button({icons:{primary:"ui-icon-minus"},text:false});
    jQuery('.webvoter').filter('.cancel').button({icons:{primary:"ui-icon-close"},text:false});
    jQuery('.webvoter_result').each(function(){
	    jQuery(this).button({ "icons":{primary:jQuery(this).attr('icon')}, "disabled":true});
	});
    jQuery('.webvoter').css('height','15px').css('width','15px');
    var r = jQuery('.webvoter_result');
    if (r)
	evidence_web_vote (r.attr('variant_id'), null, null);
    jQuery('.webvoter_result').addClass('ui-state-hover').removeClass('ui-state-disabled').css('min-width', '80px');
}
jQuery(document).ready(evidence_web_vote_setup);

function variant_report_progress_update()
{
    var x = {
	method: 'get',
	parameters:
	{
	    'display_genome_id': $('display_genome_id').value,
	    'access_token': $('access_token').value,
	    'json': true
	},
	onSuccess: function(transport)
	{
	    var j = transport.responseJSON;
	    if (j && j.status) {
		if (typeof j.status.progress != 'undefined')
		    jQuery('#variant_report_progress').progressbar('value', 100*j.status.progress);
		if (typeof j.status.status != 'undefined') {
		    if (j.status.status == 'finished')
			window.location.href = window.location.href;
		    else
			jQuery('#variant_report_status').html(j.status.status);
		}
		if (j.log) {
		    jQuery('#debuginfotext').html(('Log file: '+j.logfilename+'\n\n'+j.log+'\n\n').escapeHTML());
		}
	    }
	}
    };
    new Ajax.Request('genomes', x);
}
function variant_report_progress_setup()
{
    var val = 0;
    var div = '#variant_report_progress';
    if (!jQuery(div).length)
	return;
    if (jQuery(div).attr('initial-value'))
	val = parseFloat(jQuery(div).attr('initial-value'));
    jQuery(div).progressbar();
    jQuery(div).progressbar('value', val * 100);
    jQuery(div).css('width', '100px');
    setInterval(variant_report_progress_update, 10000);
}
jQuery(document).ready(variant_report_progress_setup);

function release_status_setup()
{
    jQuery('button.release-status-yes').
	button({icons:{primary:'ui-icon-circle-check'}}).
	css('background-color','#dfd');
    jQuery('button.release-status-no').
	button({icons:{primary:'ui-icon-alert'}}).
	css('background-color','#ffb');
    jQuery('button.release-status-yes,button.release-status-no').
	css('background-image','none').
	button('option', 'disabled', true).
	addClass('ui-state-hover').
	removeClass('ui-state-disabled').
	click(function(){return false;});
    jQuery('div#release-status').
	css('background-color','white').
	css('background-image','none');
}
jQuery(document).ready(release_status_setup);

function curator_signoff(ids)
{
    var x = {
	'method': 'POST',
	'parameters':
	{'edit_ids': ids,
	 'json': true },
	'onSuccess': function(){window.location.reload();}
    };
    new Ajax.Request('signoff', x);
}
function curator_powers_setup()
{
    jQuery('a#curator-signoff-orig').
	button({icons:{primary:'ui-icon-circle-check'}}).
	click(function(){
		curator_signoff(jQuery(this).attr('edit-ids'));
		return false;
	    });
    jQuery('a#curator-signoff-edited').
	button({icons:{primary:'ui-icon-circle-check',
			secondary:'ui-icon-circle-arrow-e'}}).
	click(function(){
		editable_save({submit:true,signoff:true});
		return false;
	    });
}
jQuery(document).ready(curator_powers_setup);

function closeKeepAlive()
{
    if (/AppleWebKit|MSIE/.test(navigator.userAgent)) {
	new Ajax.Request("/about", { asynchronous:false });
    }
}
