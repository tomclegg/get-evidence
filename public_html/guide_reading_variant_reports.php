<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Guide to Reading Variant Reports";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Reading Variant Reports

Variant reports form the core of GET-Evidence. These reports represent an attempt to summarize what a variant's effect is on health and traits. Variant reports can be edited by anyone who logs in to GET-Evidence. They should be considered "research quality" -- not well-established -- and may contain errors in their interpretations, or the original literature may itself be flawed.

To understand the different parts of the variant report, we will use screenshots from the variant "JAK2-V617F":JAK2-V617F.

h2. Variant name and summary

p(. !img/sample_variant_report_1.gif!

h3(. Variant name

p((. The variant report begins with the variant name. Usually this consists of a gene symbol followed by the amino acid change. In this case the gene symbol is "JAK2", which stands for "Janus Kinase 2". This gene produces a protein referred to by the same name.

p((. The amino acid change for this variant is "V617F" or "Val617Phe". This abbreviation means that the reference genome (which usually represents "most genomes") predicts the amino acid valine (Val or V) to occur as the 617th amino acid in the protein. A gene carrying this variation is predicted to have a different amino acid, phenylalanine (Phe or F), to instead be created at this position in the protein. Please see our "variant call nomenclature":guide_amino_acid_calls for more information on how amino acid changes are listed in GET-Evidence.

p((. GET-Evidence focuses on causal variations -- not merely associations. Because of this most variants in our database represent changes to proteins, as these often have a functional impact. Other variants (e.g. those that disrupt splicing) can also have a functional effect. To allow reporting of these, GET-Evidence can also report variants according to their dbSNP ID (rsID). However, returning health/trait impacts for variations found in genome-wide association studies that are _not_ believed to be a causal is strongly discouraged.

h3(. Short summary

p((. Finally, the variant report contains a text summary describing the current knowledge regarding this variant. This summary is intended to be fairly short and is displayed in the "genome report":guide_reading_genome_reports. (The example given here is a bit long because this variant is particularly notable.)

h2. Variant Evidence & Clinical Importance Scores

p(. !img/sample_variant_report_2.gif!

p(. After the summary, the variant report contains a set of "impact scores":guide_impact_scores. These scores are generally entered by people who review the variant and help us automatically interpret the variant when producing reports. 

p(. The scores split into two sections: "variant evidence" and "clinical importance". These two sections reflect two very different aspects to understanding a genetic variant's impact -- how serious the effect is clinically, and how well-established the evidence is for that effect. For example, a variant might have been seen once in a family with a very severe genetic disease. The clinical importance of such a disease could be quite high. However, the strength of evidence supporting that variant as causing the disease could be extremely weak -- it was only seen that once, in one child, and not part of an extensive study comparing many patients and many controls.

p(. When a variant has multiple reported effects, they are scored according to the effect thought to be the "most significant" -- i.e. of the strongest interest to most individuals reading the genome report. This can require a judgement call by the reviewer and should take into account both the severity and evidence for the different reported effects.

h3(. Variant evidence

p((. *Computational*: The computational evidence score is higher the more computational evidence (i.e. outside of laboratory experiments) that exists supporting this variant as having a functional effect. This includes: predicted disruptiveness of the variant according to various computational methods, and whether other variants in the same gene are well-established to have a similar effect on phenotype.

p((. *Functional*: The functional evidence score is higher when laboratory studies that support this variant as having a functional effects. These studies can include both _in_ _vitro_ and _in_ _vivo_ studies.

p((. *Case/control*: Case/control evidence is one of two types of evidence arising from observing the variant in humans. This type of evidence involves surveying a large number of cases and controls, and looking for a difference in the variant frequency between the two groups. A higher score in this category reflects stronger statistical significance.

p((. *Familial*: Familial evidence is the other type of evidence arising from observing the variant in humans. This type of evidence involves studying an extended family in depth and observing that the inheritance of this variant is associated with the inheritance of the genetic disease. A higher score in this category reflects stronger statistical significance.

h3(. Clinical importance

p((. (Note: These scores are considered irrelevant for "benign" variants. Benign variants includes those which affect traits not considered to be medical/health issues, e.g. eye color.)

p((. *Severity*: How severe the disease caused by a variant is, if left untreated. For protective variants this is the severity of the disease the variant _protects_ against, and for pharmacogenetic variants it is the severity of consequences if the variant was not considered when administering a drug.

p((. *Treatability*: How treatable the disease caused by the variant is. A higher score indicates a more treatable effect. Variants which could be treated are considered to have higher clinical importance.

p((. *Penetrance*: How often individuals carrying the variant develop the associated disease. This can range from high penetrance "Mendelian" variants (where nearly 100% of people with the disease-associated genotype develop the disease) to very low penetrance "susceptibility" variants (e.g. a 0.2% increased chance of developing Crohn's disease).

p(. *One final note...*

p((. !img/sample_variant_report_3.gif!

p((. Mousing over variants will display a pop-up text that describes that details the criteria for scoring that category.

h2. Impact and Inheritance pattern

p(. !img/sample_variant_report_4.gif!

h3(. Impact

p((. The variant impact combines what type of impact the variant is evaluated as having with a summary of the above scores. There are four types of impact that can be defined: *pathogenic* (causes disease), *protective* (prevents disease), *pharmacogenetic* (influences drug effects), or *benign* (non-disease trait or no effect).

p((. Based on the "Clinical Importance" scores, a variant is listed as *high clinical importance*, *medium clinical importance*, or *low clinical importance*. This is created automatically based on the scores, to read more about how it is determined see our "information regarding qualifiers":guide_qualifiers.

p((. Finally, the certainty for the variant is automatically qualified depending on the "Variant Evidence" scores. A variant can be listed as *uncertain* (low evidence) or *likely* (moderate evidence). If no qualifier is present (as in this example) that implies the variant is considered *well-established*. For more on how the qualifier is automatically determined see our "information regarding qualifiers":guide_qualifiers.

p((. These combine to create the variant impact field. Examples include: "high clinical importance, pathogenic" (as shown here), "low clinical importance, likely protective", and "moderate clinical importance, uncertain pharmacogenetic".

h3(. Inheritance

p((. The inheritance pattern of a variant is important for determining its effect. *Dominant* variants are expected to have the reported impact when just one copy is inherited (heterozygous). *Recessive* variants are not expected to have the reported impact unless homozygous or compound heterozygous with another variant in the same gene ("compound heterozygous" = two different recessive variants breaking the same gene; GET-Evidence does not currently automatically detect this).

p((. The inheritance pattern may also be defined as *"other"*, which encompasses various possibilities. Some variants have a strong effect when homozygous and a weaker effect when heterozygous, or others only seem to have an effect on traits when another variant is also present.

p((. The scores for a variant should be evaluated according to the genotype with the strongest impact on traits -- i.e. a recessive variant should be evaluated according to the evidence and severity for people homozygous or compound heterozygous for the variant.

h2. Summary of published research, and additional commentary

p(. !img/sample_variant_report_5.gif!

This section is a text section provided for all sorts of written analysis regarding the overall variant interpretation. Unpublished research from sources not added to the publications (see below) can also be added here.

h2. Allele frequency

p(. !img/sample_variant_report_6.gif!

p((. This field lists the frequency of this genetic variant among versions of the gene in the population. It is automatically calculated by GET-Evidence -- in this example GET-Evidence has no information for this variant. This can occur for variants that are so rare that GET-Evidence doesn't have information regarding their existence (as is true here), but can also happen for other reasons (e.g. an amino acid position not automatically found by GET-Evidence processing, or a dbSNP ID not previously stored in the system).

p((. Here is an example of this field for a different variant ("SERPINA1-E288V":SERPINA1-E288V), for which GET-Evidence has allele frequency information:

p((. !img/sample_variant_report_7.gif!

p((. The different sources used by GET-Evidence to calculate allele frequency are listed here. After the percentage, a fraction is listed reporting the number of variant alleles vs. total alleles from a given data source. The source with the greatest total sample size (in this case, GET-Evidence's data with 128 samples) is what is shown in the genome summary reports.

h2. Publications

p(. !img/sample_variant_report_8.gif!

p((. Publications relevant to interpreting a variant can be added to GET-Evidence both automatically and manually using their PubMed ID (PMID). A link to PubMed is provided, allowing users to visit PubMed and, from there, find a copy of this publication.

p((. Interpreters can enter text summarizing what evidence the publication provides regarding this genetic variant. Note -- although some publications report on several different variants, publication summaries on a variant page should be specificly report how the publication is relevant to this particular variant.

p(. *BioNotate annotation*

p((. !img/sample_variant_report_9.gif!

p((. Currently the interpretation of publications is a complicated process and must be done by human reviewers. We are interested, however, in creating systems for automatically or semi-automatically interpreting publications. To this end, GET-Evidence has added integration with *BioNotate*.

p((. When logged in, editors can go to BioNotate and add or modify annotations to a publication's abstract. These annotations show up as highlighted sections in the abstract. These annotations do *not* influence automatic interpretations of the variant in GET-Evidence, but if enough are accumulated we may learn enough to develop algorithms that do this in the future.

h2. Genomes

p(. !img/sample_variant_report_10.gif!

p((. Personal Genome Project genomes and other public genome reports in GET-Evidence carrying this variant are listed and linked to here. The zygosity, chromosome, coordinates, and nucleotide call for the variant in that genome is listed.

p((. An optional region for notes is included underneath the gray line. Empty in this example, this area can be filled in my editors to note additional information that may be available for this individual.

h2. Other external references

p(. !img/sample_variant_report_11.gif!

p(. To assist with interpretation, GET-Evidence automatically finds external sources with information relevant to variant interpretation and links to these. These sources can include the following:

p(. *dbSNP*

p((. If GET-Evidence knows the dbSNP ID (rsID) for a variant, a link to this ID in dbSNP is provided. Note that dbSNP's own database is constantly expanding and not all of these will be present in GET-Evidence; just because a variant is not listed by GET-Evidence does not necessarily mean it does not have an ID.

p(. *GeneTests*

p((. GeneTests is a database of genes that are clinically tested for diseases. If a variant occurs in a gene that is clinically tested, a link to that gene's information in GeneTests is provided here. Presence in a clinically tested gene does not necessarily mean a variant causes a disease or trait -- it is an important clue for interpretation, however, especially for very disruptive variants (nonsense or frameshift).

p(. *OMIM*

p((. Online Mendelian Inheritance in Man (OMIM) is a curated resource providing extensive information regarding genes, their associations with genetic diseases, and summaries of information for published variant-specific observations.

p(. *PharmGKB*

p((. The Pharmacogenomics Knowledge Base (PharmGKB) is a curated resource containing information regarding genetic variations, with a strong focus on the effect of genetic variations on drug response.

p(. *Polyphen-2*

p((. Polyphen-2 is a tool for computationally predicting whether an amino acid substitution will be disruptive to a protein's function. Scores range from 0 (benign) to 1 (disruptive).

p(. *Web search results*

p((. In addition to specific databases, GET-Evidence automatically uses the Yahoo search engine to search the internet for potentially relevant web pages. This can be very valuable, as it can discover publications that mention the variant or unpublished disease-specific databases that list it.

p((. Other times, however, these hits are irrelevant -- for example, an spurious hit to an electronic parts catalog. Editors can assist variant reviews by voting on web hits as "relevant" or "not relevant", displayed in the rectangular boxes to the right of each web hit. In this example, the first two web hits have been voted on as "relevant", and the third is unrated.

h2. Other _in_ _silico_ analyses

p(. !img/sample_variant_report_12.gif!

p(. Other in silico information (referring to automatic / computational data) is listed here.

p(. *NBLOSUM score*

p((. "NBLOSUM" stands for "inverse BLOSUM100" -- this is an inverse of the BLOSUM100 score for an amino acid substitution. BLOSUM100 predicts how disruptive, on average, substitution one type of amino acid with another is. A higher NBLOSUM score corresponds to a substitution more likely to be disruptive.

p(. *GET-Evidence prioritization score*

p((. This is the score used to rank this variant in the "insufficiently evaluated" genome report (if it is considered insufficiently evaluated). Placing the cursor over the score will bring a pop-up box listing the reasons supporting this score.

h2. Edit history

p(. !img/sample_variant_report_13.gif!

p(. Similar to a wiki, GET-Evidence allows anyone logged in through OpenID to edit variant pages and it retains a record of previous versions of the page. 

p(. Editors are listed by their name, and the version of their page created in that edit can be seen by clicking "view". This example also shows an instance of information automatically added by a robot ("Genome Importing Robot", which added a link to the PGP16 genome).

EOF
);

go();

?>
