<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Variant Impact Scores";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Variant Impact Scores

To facilitate automatic reporting of variants, we ask that users score variants in various categories. There are seven impact scores that are recorded, described below.

For a variant to be considered "sufficiently evaluated" (and thus appear in a genome interpretation) some minimum number of these categories must be recorded. See our "guide to sufficiently vs. insufficiently variants":guide_sufficiently_evaluated for the specific requirements.

h2. Variant evidence vs. clinical importance

Of the seven impact scores, the first four reflect variant evidence (how "real" a variant effect is) while the last three reflect clinical importance (how severe and/or treatable the effect is). It is important to distinguish between these two sets of scores. Some variants may be predicted to have a very severe effect but have weak supporting evidence because the variant's rarity makes it hard to collect statistically significant data - such a variant would have high clinical importance scores but low evidence scores. On the other hand, some variants may have strong statistical significance but are common and have a weak impact on disease (for example, variants found through genome-wide association studies of common SNPs) - such variants would have high evidence scores but low clinical importance.

Thus, there are many types of "pathogenic" variants. We combine variant impact scores from these two categories to automatically generate impact qualifiers that more accurately describe a variant. For example: the rare, severe variant with little supporting evidence would be called "high clinical importance, uncertain pathogenic", while the common variant with significant evidence supporting a weak effect would be called "low clinical importance, pathogenic". An explanation for these descriptions is in our "guide to qualifiers":guide_qualifiers.

h2. Variant evidence scores

h3. Computational evidence

This category scores the amount of computational and theoretical evidence that supports this variant as having a functional effect. One point is awarded for each of the following:
* NBLOSUM of 3 or greater
* Nonsense mutation
* Other variants in this gene cause similar disease (eg. GeneTests genes)
* Presence in active domain
* PolyPhen damaging prediction
* SIFT "not tolerated" prediction
* GVGD deleterious prediction 

h3. Functional evidence

This category scores experimental, variant-specific evidence supporting the variant having an effect. One point for each variant-specific experiment supporting the result, and penalize one point for conflicting results. Experiments must be variant-specific recombinant sequences, not merely from patient-derived cell lines (which may carry another causal variant). Ignore general data regarding gene function and importance. Examples:
* enzyme activity
* binding affinity
* cellular localization
* animal models

h3. Case/control evidence

This category measures evidence based on the incidence of this variant in people with the phenotype (cases), compared to people who do not have the phenotype but are otherwise similar (controls). Cases should not be related (ie. not from the same family). Case/control numbers have an should be "evaluated using Fisher's exact test to get a p value":calculators, which determines the score:

* 5 points if the significance (p-value) is less than 0.0001
* 4 points if the significance (p-value) is less than 0.01
* 3 points if the significance (p-value) is less than 0.025
* 2 points if the significance (p-value) is less than 0.05
* 1 points if the significance (p-value) is less than than 0.1
* 0 points if none of the above conditions are met
* -1 points if the frequency of the allele contradicts a highly pathogenic hypothesis

h4. What are case+ and case-?

You'll see our "Fisher's exact test calculator":calculators requires you to provide numbers for these. "Case+" refers to people or chromosomes that have both the variant and the phenotype being studied. "Case-" refers to people or chromosomes that have the phenotype, but ''do not'' have the genotype being measured.

Exactly what is being counted can vary. 
* Recessive hypothesis: Only homozygotes for the variant are counted as case+, heterozygotes and non-carriers are case-
* Dominant hypothesis: Homozygous and heterozygous carriers are counted as case+, non-carriers are case-
* Counting chromosomes: Alleles rather than genotypes are counted, case+ is the number of chromosomes carrying the variant, case- is the number of chromosomes without it.

h4. Do NOT combine data from different studies to increase statistical significance

Publication bias is a serious issue that makes pooling data from multiple studies problematic. Pooled data from different studies fails to account for other studies where that particular variant was not observed, or was not reported upon (because it failed to have a strong assocation). 

Data from multiple studies should not be pooled if it strengthens the statistical significance of evidence supporting a variant. You may find cases where such pooling has been done in a variant evaluation. These cases may predate the creation of this guideline and should be corrected when found. You might, with discretion, combine data from other studies in a manner that weakens a hypothesis.

In general, "meta-analysis" that combines and analyzes case/control data from different sources should be from peer-reviewed meta-analysis publications, not performed within GET-Evidence.

h3. Familial evidence

This category measures evidence based on the inheritance of this variant in families with the phenotype and how correlated the variant and phenotype are. For this category LOD (log-odds, using base10) scores are used:

* 5 points if LOD is at least 5 and more than one family is evaluated
* 4 points if LOD is at least 3 and more than one family is evaluated
* 3 points if LOD is at least 1.5 and more than one family evaluated
* 2 points if LOD is at least 1.3
* 1 point if LOD is at least 1
* 0 points if no data, or very weak data
* -1 points if familial evidence is contradictory 

h4. What is a LOD score?

LOD scores are often reported in papers that study variants within a family. You can also calculate your own LOD scores, we plan to add a guide to do this soon.

h4. Why do you require multiple families?

Multiple families are required because it is possible that a non-causal variant tracks with the disease within a single family because it is linked&mdash;because we are looking for evidence of causality rather than evidence of linkage we require evidence from multiple families. See "DSPP Arg68Trp":DSPP-Arg68Trp for an example of strong segregation data from within a single family (LOD = 3.6) for a variant that is almost certainly not pathogenic in the dominant manner reported (1000 Genomes allele frequency of 11.6%).

h2. Clinical importance

Clinical importance categories reflect the clinical consequences of a variant. These do *not* reflect how well existing evidence supports this effect, but how important this effect is *if* it is true. Variants should always be evaluated according to their _published hypotheses_ regarding severity and penetrance. 

h2. Severity

This category measures how severely the disease/phenotype associated with this variant affects lifespan or quality of life. This score assumes the variant causes disease.

* 5 points for very severe effect: early lethal (e.g. Familial adenomatous polypopsis, adrenoleukodystrophy)
* 4 points for severe effect: causes serious disability or reduces life expectancy (e.g. Sickle-cell disease, Stargardt's disease)
* 3 points for moderate effect on quality of life (e.g. Familial mediterranean fever)
* 2 points for mild effect on quality of life and/or usually not symptomatic (e.g. Cystinuria)
* 1 point for rarely having any effect on health (e.g. small increased susceptibility to infections -- either choose this or a low penetrance score, not both)
* 0 points if benign 

h2. Disease treatability

This category measures how treatable the effects of the disease/phenotype are.

* 5 points for extremely treatable: Well-established treatment essentially eliminates the effect of the disease (e.g. PKU)
* 4 points for treatable: Standard treatment significantly reduces the amount of mortality/morbidity but does not eliminate it
* 3 points for somewhat treatable: Standard treatment, but only a small or moderate improvement of mortality/morbidity
* 2 points for potentially treatable: Treatment is in development or controversial
* 1 point for uncurable: treatment only to alleviate symptoms (e.g. adrenoleukodystrophy)
* 0 points for no clinical evidence supporting intervention (e.g. PAF acetylhydrolase deficiency) 

h2. Penetrance

This category measures how likely the variant is to cause the associated disease/phenotype. Points are assigned based on "increased attributable risk". For example, if the average individual has a 5% lifetime risk of colon cancer and an individual with a pathogenic variant genotype has a 6.5% lifetime risk of colon cancer, this translates to a 1.5% attributable risk. This scores 2 points according to our current system. 

* 5 points for  50% attributable risk (complete or highly penetrant)
* 4 points for >= 20% attributable risk (moderately high penetrance)
* 3 points for >= 5% attributable risk (moderate penetrance)
* 2 points for >= 1% attributable risk (low penetrance)
* 1 point for >= 0.1% attributable risk (very low penetrance)
* 0 points for < 0.1% attributable risk (extremely low penetrance)

h2. 
EOF
);

go();

?>
