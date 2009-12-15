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
		var id = 'v_'+v+'__a_'+a+'__g_0__p_'+p+'__f_summary_short__70x5__textile';
		if ($(id)) {
		    // TODO: show error -- article already listed
		    editable_click ($(id));
		    return;
		}
		$('article_new').insert('<LI><a href="http://www.ncbi.nlm.nih.gov/pubmed/'+a+'">'+
					'PMID '+a+'</A><BR />'+
					editable_make(id, '(no summary)')+
					'</LI>');
		editable_init_single ($(id));
	    }
    });
}
