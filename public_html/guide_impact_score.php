<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: About";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Variant Impact Score

Each variant is graded in six categories, the first four related to evidence and last two related to clinical importance. Each category may be assigned up to five points to reflect the strength of evidence in that category. Zero points reflects no significant evidence in a category, -1 reflects contradicting evidence. These six categories are the "variant impact score".

h3. Other guides

* "Guide to editing":guide_editing: Explains how to edit variant evaluations.
* "Preliminary variant impact score":guide_preliminary_score: Explains how automatic preliminary scores are assigned, these should be overwritten by later evaluation.
* "Qualifiers":guide_qualifiers: Explains how the variant impact score determines the description of the variant as “uncertain”, “likely”, etc.

h2. Computational evidence

This category scores the amount of computational and theoretical evidence that supports this variant as having a functional effect. One point is awarded for each of the following:
* NBLOSUM of 3 or greater
* Nonsense mutation
* Other variants in this gene cause similar disease (eg. GeneTests genes)
* Presence in active domain
* PolyPhen damaging prediction
* SIFT "not tolerated" prediction
* GVGD deleterious prediction 

h2. Functional evidence

This category scores experimental evidence supporting the variant having an effect. One point for each piece of experimental evidence that supports this variant as having an effect (maximum of five points). This experiments may include: 
* enzyme extracts
* cell lines
* animal models 

h2. Case/control evidence

This category measures evidence based on the incidence of this variant in people with the phenotype (cases), compared to people who do not have the phenotype but are otherwise similar (controls). Cases should not be related (ie. not from the same family). Case/control numbers have an should be evaluated using Fisher's exact test to get a p value. The odds ratio (OR) and p-value are used to determine the score: 

* 5 points if OR is at least 5 and p-value is no greater than 0.0001
* 4 points if OR is at least 3 and p-value is no greater than 0.01
* 3 points if OR is at least 2 and p-value is no greater than 0.025
* 2 points if OR is at least 1.5 and p-value is no greater than 0.05
* 1 points if OR is greater than 1 and p-value is no greater than 0.1
* 0 points if none of the above conditions are met
* -1 points if the incidence of the allele contradicts 

For "protective" variants the inverse odds ratio should be used (i.e. a protective variant with an OR of 0.37 should be treated as having an OR of 2.7 for the purposes of this scoring).

h3. What are case+ and case-?

"Case+" refers to people with the phenotype that have the genotype being measured. "Case-" refers to people with the phenotype that ''do not'' have the genotype being measured.

What this genotype is can vary. For a variant with a dominant hypothesis, case+ will count all cases that are either heterozygous or homozygous for the variant. For a variant with a recessive hypothesis, case+ will count all homozygous and possibly include compound heterozygous individuals (people with two different recessive alleles).

Alternatively, case+ and case- can count the number of alleles rather than genotypes. In this case, case+ is the number of chromosomes carrying this variant that are associated with the phenotype.

h3. What is an odds ratio?

An odds ratio is a measure of the size of the effect the genetic variant has. For the following case/control numbers: 
* case+: a
* case-: b
* control+: c
* control-: d

The odds ratio is: (a / b) / (c / d)

When the incidence of a variant is very low this number approaches the number for "relative risk" (which indicates the fold-increased likelihood that a person with the variant is to have the associated phenotype). 

An odds ratio is infinite for a fully penetrant disease (c = 0). GET-evidence currently treats these as having c = 0.5 to provide an upper bound estimate.

h2. Familial evidence

This category measures evidence based on the inheritance of this variant in families with the phenotype and how correlated the variant and phenotype are. For this category LOD (log-odds, using base10) scores are used:

* 5 points if LOD is at least 5 and more than one family is evaluated
* 4 points if LOD is at least 3 and more than one family is evaluated
* 3 points if LOD is at least 1.5 and more than one family evaluated
* 2 points if LOD is at least 1.3
* 1 point if LOD is at least 1
* 0 points if no data, or very weak data
* -1 points if familial evidence is contradictory 

h3. What is a LOD score?

LOD scores are often reported in papers that study variants within a family. You can also calculate your own LOD scores, we plan to add a guide to do this soon.

h3. Why do you require multiple families?

Multiple families are required because it is possible that a non-causal variant tracks with the disease within a single family because it is linked&mdash;because we are looking for evidence of causality rather than evidence of linkage we require evidence from multiple families. See "DSPP Arg68Trp":DSPP-Arg68Trp for an example of strong segregation data from within a single family (LOD = 3.6) for a variant that is almost certainly not pathogenic in the dominant manner reported (1000 Genomes allele frequency of 11.6%).

h2. Disease severity

This category measures how severely the variant affects lifespan or quality of life.

* 5 points for very severe effect: early lethal (e.g. Familial adenomatous polypopsis, adrenoleukodystrophy)
* 4 points for severe effect: causes serious disability or reduces life expectancy (e.g. Sickle-cell disease, Stargardt's disease)
* 3 points for moderate effect on quality of life (e.g. Familial mediterranean fever)
* 2 points for mild effect on quality of life or unlikely to be symptomatic (e.g. Cystinuria)
* 1 point for very low expectation of having symptoms for this genotype, very low penetrance (e.g. Susceptibility to Crohn's disease with a 4-fold relative risk&mdash;this is an overall risk of ~.03%)
* 0 points if benign 

h2. Disease treatability

This category measures how treatable the effects of the genetic variant are.
* 5 points for extremely treatable: Well-established treatment essentially eliminates the effect of the disease (e.g. PKU)
* 4 points for treatable: Standard treatment reduces the amount of mortality/morbidity but does not eliminate it (e.g. Sickle-cell disease)
* 3 points for treatable but a significant fraction do not require treatment (e.g. Cystinuria)
* 2 points for potentiall treatable: Treatment is in development or controversial
* 1 point for uncurable: treatment only to alleviate symptoms (e.g. adrenoleukodystrophy)
* 0 points for no clinical evidence supporting intervention (e.g. PAF acetylhydrolase deficiency) 
EOF
);

go();

?>
