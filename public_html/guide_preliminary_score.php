<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: About";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Preliminary variant impact scores

To sort through variants automatically and find the variants more likely to have clinically relevant effects, we assign "preliminary variant impact scores":guide_impact_score on the basis of information in our databases. This focuses attention on variants; these scores should be replaced when the variant is evaluated.

h3. Other guides

* "Guide to editing":guide_editing: Explains how to edit variant evaluations.
* "Variant impact score":guide_impact_score: Explains how the variant impact score is determined.
* "Qualifiers":guide_qualifiers: Explains how the variant impact score determines the description of the variant as “uncertain”, “likely”, etc.

h2. Preliminary score criteria

Scores are generated in the following manner:
*  *NBLOSUM100* score is calculated for the substitution; this score reflects how nonconservative a particular amino acid change is. 
** An NBLOSUM score of *3 or more* means that *one point* is assigned to computational evidence.
** A *score of 10* is instead given *two points* (this indicates a nonsense mutation).
* *Presence in a gene in the GeneTests* means a gene generally has pathogenic potential.
** If a variant is *in a GeneTests gene*, *one additional point* is added the computational evidence score.
** If this gene also has an associated *GeneReviews and clinical testing available* this is instead *two additional points*.
* *Presence in OMIM* indicates that a variant (not the associated gene) has been reported on within OMIM. If this is true, a variant is assigned *two points of evidence*, one to case/control and one to familial.

Note that these scores are chosen such that these variants will always have an "unknown" "qualifier":guide_qualifiers.

h3. What about benign OMIM variants?

OMIM is an especially useful resource for us because it provides variant-specific information (eg. "MYL2 A13T") rather than gene-specific information (just "MYL2"). Although there are benign variants listed in OMIM, this is a preliminary score intended to bring variants to attention for evaluation&mdash;evaluation should update the score and classification of a variant. 

EOF
);

go();

?>
