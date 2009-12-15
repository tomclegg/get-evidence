// Copyright 2009 President and Fellows of Harvard College
// Author: Tom Clegg

function evidence_add_article (v, a)
{
    var id = 'v_'+v+'__a_'+a+'__g_0__p_v'+v+'a'+a+'__f_summary_short__70x5__textile';
    if ($(id)) {
	// TODO: show error -- article already listed
	return;
    }
    $('article_new').insert('<LI><a href="http://www.ncbi.nlm.nih.gov/pubmed/'+a+'">'+
			    'PMID '+a+'</A><BR />'+
			    editable_make(id, '(no summary)')+
			    '</LI>');
    editable_init_single ($(id));
}
