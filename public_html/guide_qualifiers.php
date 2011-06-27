<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Qualifiers";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Qualifiers

Qualifiers are added to this to describe the variant evidence and clinical importance of a variant, these qualifiers are automatically generated from the "variant impact score":guide_impact_score. An example of how qualifiers are attached to variant impact (in this case "pathogenic") follows:

| - |_. low evidence |_. moderate evidence |_. high evidence |
|_. low importance | low clinical importance,<br>uncertain pathogenic | low clinical importance,<br>likely pathogenic | low clinical importance,<br>pathogenic |
|_. moderate importance | moderate clinical importance,<br>uncertain pathogenic | moderate clinical importance,<br>likely pathogenic | moderate clinical importance,<br>pathogenic |
|_. high importance | high clinical importance,<br>uncertain pathogenic | high clinical importance,<br>likely pathogenic | high clinical importance,<br>pathogenic |

h3. Other guides

* "Guide to editing":guide_editing: Explains how to edit variant evaluations.
* "Autoscore":guide_autoscore: Explains how autoscore prioritization of variants for review is calculated.
* "Variant impact score":guide_impact_score: Explains how the variant impact score is determined.
* "Amino acid calls":guide_amino_acid_calls: Explains our annotation for amino acid changes predicted from genetic variants
* "Upload and source file format":guide_upload_and_source_file_formats: Explains file format used by our genome processing and provided genome data downloads

h2. Insufficiently evaluated qualifier

A variant is considered insufficiently evaluated if not enough scoring fields have been filled in. To be considered sufficiently evaluated a variant must have:
# At least one of either "case/control" or "familial" evidence categories filled in
# The "severity" and "penetrance" categories in clinical importance categories filled in. (Not necessary for benign or protective variants.)

h2. Variant evidence qualifier

The variant evidence qualifier is determined according to the following:

* "high evidence":
## least 4 points in either "Case/control evidence" or "Familial evidence"
## *and* at least eight points total in evidence categories

* "moderate evidence":
## at least 3 points in either "Case/control evidence" or "Familial evidence"
## *and* at least five stars total in evidence categories

* "low evidence": Any variants which do not meet the above requirements

h2. Clinical importance qualifier

Clinical importance is determined according to the following rules:

* "high clinical importance": 
## At least 4 points in penetrance (high-moderate penetrance / >= 20% attributable risk)
## and either:
### At least 3 stars in severity and at least 4 stars in treatability
### *or* at least 4 stars in severity

* moderate clinical importance": 
## At least 3 points in penetrance (moderate penetrance / >= 5% attributable risk)
## and either:
### At least 2 stars in severity and at least 3 stars in treatability
### *or* at least 3 stars in severity

* "low clinical importance": Any variants which do not meet the above requirements
EOF
);

go();

?>
