// Copyright 2010 Clinical Future, Inc.
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

function evidence_add_variant (gene, aa_change)
{
    var x = {
	    method: 'post',
	    parameters:
	    {
		variant_gene: gene,
		variant_aa_change: aa_change
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
    var x = {
	    method: 'get',
	    parameters:
	    {
		variant_id: variant_id,
		url: voter_element.href,
		score: score
	    },
	    onSuccess: function(transport)
	    {
		if (transport.responseJSON)
		    $$('a.webvoter').each(function(e) {
			    wuid = e.id.replace(/^.*_/,'');
			    if(transport.responseJSON.all[e.href]==1) {
				$('webvoter_all_' + wuid).src = '/img/thumbsup-32.png';
				$('webvoter_all_' + wuid).style.display = 'inline';
			    }
			    else if(transport.responseJSON.all[e.href]==0) {
				$('webvoter_all_' + wuid).src = '/img/thumbsdown-32.png';
				$('webvoter_all_' + wuid).style.display = 'inline';
			    }
			});
	    }
    };
    new Ajax.Request('webvote.php', x);
    return false;
}
