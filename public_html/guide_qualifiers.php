<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: About";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Qualifiers

Qualifiers are added to this to describe the variant evidence and clinical importance of a variant, these qualifiers are automatically generated from the "variant impact score":guide_impact_score. An example of how qualifiers are attached to variant impact (in this case "pathogenic") follows:

| - |_. low evidence |_. moderate evidence |_. high evidence |
|_. low importance | low clinical importance,<br>uncertain pathogenic | low clinical importance,<br>likely pathogenic | low clinical importance,<br>pathogenic |
|_. moderate importance | moderate clinical importance,<br>uncertain pathogenic | moderate clinical importance,<br>likely pathogenic | moderate clinical importance,<br>pathogenic |
|_. high importance | high clinical importance,<br>uncertain pathogenic | high clinical importance,<br>likely pathogenic | high clinical importance,<br>pathogenic |

h3. Related pages

* "Variant impact score":guide_impact_score: Explains how variant impact score (which is used to determine qualifiers) is generated.

h2. Variant evidence qualifier

The variant evidence qualifier is determined according to the following:

* "high evidence":
## least 4 stars in either "Case/control evidence" or "Familial evidence"
## *and* at least eight stars total

* "moderate evidence":
## at least 3 stars in either "Case/control evidence" or "Familial evidence"
## *and* at least five stars total

* "low evidence": Any variants which do not meet the above requirements

h2. Clinical importance qualifier

Clinical importance is determined according to the following rules:

* "high clinical importance": 
## At least 3 stars in severity and at least 4 stars in treatability
## *or* at least 4 stars in severity

* moderate clinical importance": 
## At least 2 stars in severity and at least 3 stars in treatability
## *or* at least 3 stars in severity

* "low clinical importance": Any variants which do not meet the above requirements
EOF
);

go();

?>
