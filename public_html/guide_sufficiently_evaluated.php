<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Sufficiently vs. Insufficiently Evaluated Classification";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Sufficiently vs. Insufficiently Evaluated Classification

Variants in GET-Evidence will not be listed in the main part of a genome report unless they are considered "sufficiently evaluated". Otherwise, they are listed in the "insufficiently evaluated" variants report, sorted by prioritization score.

For a variant to be sufficiently evaluated, it needs to have "variant impact scores":guide_impact_score evaluated such that it can be automatically ranked in the genome report.

h2. Criteria for "Sufficiently Evaluated"

h3. Variant evidence scores

At least one of either *"Case/control"* OR *"Familial"* scores must be recorded.

Note that a score of zero is acceptable (i.e. if there is no significant evidence in the category). For example, a variant only once observed in a single child -- lacking both case/control and familial segregation data -- could score zero points in both categories.

h3. Clinical impact scores

For "benign" variants, no scores need to be recorded for this section.

For other variants, both *"Severity"* AND *"Penetrance"* must be recorded.

EOF
);

go();

?>
