<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Autoscore";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Autoscore

Every genome has millions of variants, thousands of which are potentially interesting. To help sort through these and prioritize variants for review, an "autoscore" is automatically calculated. This score combines two different reasons for prioritizing variants -- we want to prioritize variants which we think have some relevant literature, and we want to prioritize variants for which computational methods predict a damaging effect.

h3. Other guides

* "Guide to editing":guide_editing: Explains how to edit variant evaluations.
* "Variant impact score":guide_impact_score: Explains how the variant impact score is determined.
* "Qualifiers":guide_qualifiers: Explains how the variant impact score determines the description of the variant as “uncertain”, “likely”, etc.
* "Amino acid calls":guide_amino_acid_calls: Explains our annotation for amino acid changes predicted from genetic variants
* "Upload and source file format":guide_upload_and_source_file_formats: Explains file format used by our genome processing and provided genome data downloads 

h2. How autoscore is calculated

Autoscores can be a total of six points, with a maximum of two points coming from each of three different categories: *(1)* pre-existing variant-specific knowledge, *(2)* pre-existing gene-specific knowledge, *(3)* computational prediction of variant disruptiveness.

h3. Pre-existing variant-specific knowledge

Presence in variant specific databases and potential or confirmed webpage matches contributes to our score for pre-existing variant-specific knowledge. This component of score reflects our desire to prioritize variants which have some relevant published information for review.

* +2 for presence in Online Mendelian Inheritance in Man (OMIM) - this database is given more points than other sources because it tends to contain rare, severe disease variants -- of course, it also has benign and milder variants, users must look at OMIM itself to find out more.
* +1 for presence in the Pharmacogenomics Knowledge Base (PharmGKB) - this database includes "susceptibility" variants and variants that may affect drug metabolism
* +1 for presence in the Human Genome Epidemiology Network (HuGENet) database - this database contains many "susceptibility" variants that come from genome-wide association studies (GWAS).
* +1 if any unevaluated or confirmed positive webpage hits are present (+0 if all webpage hits are either confirmed false or have a tied vote) 

Although these can add up to more than two, this score has a cut-off maximum of two points.

h3. Pre-existing gene-specific knowledge

Not all genes are linked to human disease and phenotypes -- this part of the score prioritizes review for variants occurring in genes believed to have a phenotype impact. We use the GeneTests database, which contains data about genetic diseases and gene testing.

* 1 point if a gene has clinical testing available
* 2 points if a gene has clinical testing available *and* is linked to a GeneReviews article about a genetic disease

h3. Computational prediction of variant disruptiveness

Some variants might be predicted to cause disease even though they have not been reported upon specifically in the literature. This part of the score prioritizes variants where there is some prediction of disruptive effect that exists, independent of publications. For this, we use Polyphen 2, a tool which predicts functional effects for single nucleotide substitutions.

* 1 point if Polyphen 2 predicts "possibly damaging" (score is < 0.85 and >= 0.2)
* 2 points if Polyphen 2 predicts "probably damaging" (score is >= 0.85)
* 1 point for a multiple amino acid substitution, in-frame insertion or deletion, or Polyphen 2 score is otherwise unknown.
* 2 points for a nonsense or frameshift mutation

In addition, if this part of the score is nonzero and the allele frequency of the variant is 5% or greater (in GET-Evidence's set of PGP and public genomes), 1 point is subtracted.

h2. What autoscore is not

_Autoscore is *not* a prediction of a variant as having an effect_ -- Although this is a component of the score, autoscore is intended to prioritize variants for review. There may be variants with no functional effect that are highly reported-upon, thereby getting a higher autoscore. There may be undiscovered variants with severe effect

_Autoscore is *not* a summary of a variant's evaluation_ -- Although voting for web hits can adjust autoscore, autoscore merely reflects whether a variant is prioritized for review. It does not change in response to most aspects of variant review (users classifying a variant as pathogenic or benign, or adding variant impact score data).

EOF
);

go();

?>
