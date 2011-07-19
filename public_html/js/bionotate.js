// -*- mode: java; c-basic-offset: 4; tab-width: 8; indent-tabs-mode: nil; -*-
// Copyright 2011 Clinical Future, Inc.
// Authors: see git-blame(1)

var bionotate_schema_xml = '<?xml version="1.0" ?><schema><entities><entity><name>gene</name><caption>GENE</caption><nOccurrences>0</nOccurrences><color>1</color><description>continuous, minimal chunk of text identifying a gene</description></entity><entity><name>variant</name><caption>VARIANT</caption><nOccurrences>0</nOccurrences><color>2</color><description>continuous, minimal chunk of text identifying a variant</description></entity><entity><name>phenotype</name><caption>PHENOTYPE</caption><nOccurrences>0</nOccurrences><color>3</color><description>continuous, minimal chunk of text identifying a disease phenotype</description></entity><entity><name>computational-evidence</name><caption>COMPUTATIONAL EVIDENCE</caption><nOccurrences>0</nOccurrences><color>4</color><description>protein structure modeling, evolutionary conservation, etc.</description></entity><entity><name>functional-evidence</name><caption>FUNCTIONAL EVIDENCE</caption><nOccurrences>0</nOccurrences><color>5</color><description>expression in recombinant cell lines, animal model, etc.</description></entity><entity><name>case-control-evidence</name><caption>CASE/CONTROL EVIDENCE</caption><nOccurrences>0</nOccurrences><color>6</color><description>observation of variant incidence in a set of cases, may also include controls</description></entity><entity><name>familial-evidence</name><caption>FAMILIAL EVIDENCE</caption><nOccurrences>0</nOccurrences><color>7</color><description>pedigree information, familial inheritance</description></entity><entity><name>sporadic-evidence</name><caption>SPORADIC OBSERVATION</caption><nOccurrences>0</nOccurrences><color>8</color><description>Sporadic observation containing neither case/control nor familial information</description></entity></entities><questions><question><id>variance-disease-relation</id><text>What is the paper\'s conclusion regarding the variant\'s relationship with the disease/phenotype you highlighted?</text><answers><answer><value>causality</value><text> Causes the disease/phenotype</text><required></required></answer><answer><value>positive-association</value><text> Is positively associated with the disease/phenotype (exacerbating modifier effect or increased susceptibility)</text><required></required></answer><answer><value>uncertain-association</value><text> May be associated with the disease/phenotype (unknown significance) </text><required></required></answer><answer><value>unrelated-association</value><text> Is not associated with the disease/phenotype (benign polymorphism or effect is unrelated)</text><required></required></answer><answer><value>negative-association</value><text> Is negatively associated with the disease/phenotype (protective effect or decreased susceptibility)</text><required></required></answer><answer><value>other</value><text> Don\'t know / Can\'t tell / Other</text><required></required></answer></answers></question></questions></schema>';

var bionotate_sample_annotation = '<?xml version=\'1.0\' encoding=\'utf-8\'?><feed ><link rel=\'self\' type=\'text/html\' href=\'\'/><snippetID>12101866-KRT14-A413T</snippetID><source><name>Pubmed</name><sourceId>12101866</sourceId></source><location>/var/www/bionotate/retriever/snippets_GETE_20110706//12101866-KRT14-A413T.xml</location><author>carloscanogutierrez</author><text>Epidermolysis bullosa simplex (EBS) is a group of hereditary bullous diseases characterized by intraepidermal blistering due to mechanical stress-induced degeneration of basal keratinocytes. Three major subtypes have been identified with autosomal dominant inheritance: the Weber-Cockayne type, the Köbner type (EBS-K), and the Dowling-Meara type. All three EBS subtypes are caused by mutations in either keratin 5 or keratin 14, the major keratins expressed in the basal layer of the epidermis. We describe a 25-year-old male with easy blistering after trauma over the whole body from the age of 4 to 5 years. According to the clinicopathologic findings, EBS-K was diagnosed. Mutational analysis revealed a novel keratin 14 mutation (1237G-->A) that produces a conservative amino acid change (alanine to threonine) at position 413 (A413T) of the 2B helix. </text><EoIs></EoIs><annotations><question><id>variance-disease-relation</id><answer>uncertain-association</answer></question><entry><refId></refId><range>1.0 3.7</range><summary>Epidermolysis bullosa simplex</summary><type>phenotype</type></entry><entry><refId></refId><range>4.1 4.4</range><summary>EBS</summary><type>phenotype</type></entry><entry><refId></refId><range>38.0 39.4</range><summary>Köbner type</summary><type>phenotype</type></entry><entry><refId></refId><range>40.1 40.6</range><summary>EBS-K</summary><type>phenotype</type></entry><entry><refId></refId><range>47.0 47.3</range><summary>EBS</summary><type>phenotype</type></entry><entry><refId></refId><range>58.0 59.2</range><summary>keratin 14</summary><type>gene</type></entry><entry><refId></refId><range>98.0 98.5</range><summary>EBS-K</summary><type>phenotype</type></entry><entry><refId></refId><range>101.0 123.7</range><summary>Mutational analysis revealed a novel keratin 14 mutation (1237G-->A) that produces a conservative amino acid change ( alanine to threonine) at position 413 ( A413T )</summary><type>functional-evidence</type></entry><entry><refId></refId><range>106.0 107.2</range><summary>keratin 14</summary><type>gene</type></entry><entry><refId></refId><range>117.1 122.3</range><summary>alanine to threonine) at position 413</summary><type>variant</type></entry><entry><refId></refId><range>123.1 123.6</range><summary>A413T</summary><type>variant</type></entry></annotations></feed>';

(function($){
    $(function(){
            var schema = $.parseXML (bionotate_schema_xml);
            var $schema = $(schema);

            var bionotate_color = {};
            $schema.find('schema>entities>entity').each(function(i,e){
                    bionotate_color[$(e).find('name').text()] = $(e).find('color').text();
                });

            $('.bionotate').each(function(i,div){
                    var bnkey = $(div).attr('bnkey');
                    if (bnkey == '12101866-KRT14-A413T') {
                        var $annot = $($.parseXML (bionotate_sample_annotation));
                        var text = $annot.find('feed>text').text();
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
                        $(div).addClass('bionotate_visible');
                    }
                });
        });
})(jQuery);
