// -*- mode: java; c-basic-offset: 4; tab-width: 8; indent-tabs-mode: nil; -*-
// Copyright 2011 Clinical Future, Inc.
// Authors: see git-blame(1)

var bionotate_schema_xml = '<?xml version="1.0" ?><schema><entities><entity><name>gene</name><caption>GENE</caption><nOccurrences>0</nOccurrences><color>1</color><description>continuous, minimal chunk of text identifying a gene</description></entity><entity><name>variant</name><caption>VARIANT</caption><nOccurrences>0</nOccurrences><color>2</color><description>continuous, minimal chunk of text identifying a variant</description></entity><entity><name>phenotype</name><caption>PHENOTYPE</caption><nOccurrences>0</nOccurrences><color>3</color><description>continuous, minimal chunk of text identifying a disease phenotype</description></entity><entity><name>computational-evidence</name><caption>COMPUTATIONAL EVIDENCE</caption><nOccurrences>0</nOccurrences><color>4</color><description>protein structure modeling, evolutionary conservation, etc.</description></entity><entity><name>functional-evidence</name><caption>FUNCTIONAL EVIDENCE</caption><nOccurrences>0</nOccurrences><color>5</color><description>expression in recombinant cell lines, animal model, etc.</description></entity><entity><name>case-control-evidence</name><caption>CASE/CONTROL EVIDENCE</caption><nOccurrences>0</nOccurrences><color>6</color><description>observation of variant incidence in a set of cases, may also include controls</description></entity><entity><name>familial-evidence</name><caption>FAMILIAL EVIDENCE</caption><nOccurrences>0</nOccurrences><color>7</color><description>pedigree information, familial inheritance</description></entity><entity><name>sporadic-evidence</name><caption>SPORADIC OBSERVATION</caption><nOccurrences>0</nOccurrences><color>8</color><description>Sporadic observation containing neither case/control nor familial information</description></entity></entities><questions><question><id>variance-disease-relation</id><text>What is the paper\'s conclusion regarding the variant\'s relationship with the disease/phenotype you highlighted?</text><answers><answer><value>causality</value><text> Causes the disease/phenotype</text><required></required></answer><answer><value>positive-association</value><text> Is positively associated with the disease/phenotype (exacerbating modifier effect or increased susceptibility)</text><required></required></answer><answer><value>uncertain-association</value><text> May be associated with the disease/phenotype (unknown significance) </text><required></required></answer><answer><value>unrelated-association</value><text> Is not associated with the disease/phenotype (benign polymorphism or effect is unrelated)</text><required></required></answer><answer><value>negative-association</value><text> Is negatively associated with the disease/phenotype (protective effect or decreased susceptibility)</text><required></required></answer><answer><value>other</value><text> Don\'t know / Can\'t tell / Other</text><required></required></answer></answers></question></questions></schema>';

(function($){
    $(function(){
            var schema = $.parseXML (bionotate_schema_xml);
            var $schema = $(schema);

            var bionotate_color = {};
            $schema.find('schema>entities>entity').each(function(i,e){
                    bionotate_color[$(e).find('name').text()] = $(e).find('color').text();
                });

            $('.bionotate-button').button().click(function(e){
                    var $form = $('form.bionotate-form');
                    var $div = $(e.target).parents('div[bnkey]');
                    var bnkey = $div.attr('bnkey');
                    var variant_id = $div.attr('variant_id');
                    var article_pmid = $div.attr('article_pmid');
                    $form.find('input[name=oid]').attr('value',$div.attr('oid'));
                    $form.find('input[name=oidcookie]').attr('value',$div.attr('oidcookie'));
                    $form.find('input[name=save_to_url]').attr('value',document.location.href.replace(/([^\/])\/([^\/].*)?$/, '$1/bionotate-save.php?variant_id='+variant_id+'&article_pmid='+article_pmid));
                    $form.attr('action', 'http://genome2.ugr.es/bionotate2/GET-Evidence/annotate/'+bnkey);
                    $form.submit();
                    return false;
                });
            $('.bionotate').bind('bionotate-render', function(event, data){
                    var div = this;
                    var bnkey = $(div).attr('bnkey');
                    var xml;
                    if (data && data.xml)
                        xml = data.xml;
                    else if ($(div).hasClass('bionotate_visible'))
                        // Already rendered.
                        return;
                    else
                        xml = $(div).attr('snippet_xml');
                    var $annot = $($.parseXML (xml));
                    var text = $annot.find('feed text').text();
                    var annots = [];
                    $annot.find('annotations entry').each(function(i,e){
                            var $e = $(e);
                            var annot = $e.find('range').text().split(' ');
                            annot.summary = $e.find('summary').text();
                            annot.type = $e.find('type').text();
                            annots.push(annot);
                        });
                    annots.sort(function(a,b){return b[0]-a[0]});
                    var lastx = null;
                    for(var i=0; i<annots.length; i++) {
                        var annot = annots[i];
                        if (lastx && parseFloat(annot[1]) > lastx)
                            continue;

                        lastx = parseFloat(annot[0]);
                        var startword = annot[0].split('.')[0]-1;
                        var startchar = annot[0].split('.')[1];
                        var stopword = annot[1].split('.')[0]-1;
                        var stopchar = annot[1].split('.')[1];
                        var stopre = new RegExp ('^((\\S+\\s+){'+stopword+'}\\S{'+stopchar+'})');
                        var text_halfdone = text.replace(stopre, '$1</span>');
                        var startre = new RegExp ('^((\\S+\\s+){'+startword+'}\\S{'+startchar+'})');
                        var text_done = text_halfdone.replace(startre, '$1<span color="'+bionotate_color[annot.type]+'">');
                        if (text_done != text_halfdone && text_halfdone != text)
                            text = text_done;
                    }
                    $(div).html('<span><p>'+text+'</p></span>');
                    $(div).addClass('bionotate_visible').show();
                });
            $('body').append('<form class="bionotate-form" action="#" method="GET"><input type="hidden" name="oid" value=""/><input type="hidden" name="oidcookie" value=""/><input type="hidden" name="save_to_url" value=""/></form>');

            $('.bionotate').each(function(i,div){$(div).trigger('bionotate-render')});
        });
})(jQuery);
