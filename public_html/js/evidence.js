// Copyright 2009 President and Fellows of Harvard College
// Author: Tom Clegg

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
