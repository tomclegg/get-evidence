<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: Status";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Next steps

In expected approximate chronological order:

* fix "PMIDs missing when not logged in"
* add "download latest db"
* make variant_dominance and variant_impact visible/editable
* update "genome evidence" using json data from snp.med
* get PMID summary from pubmed when adding and show first author in PMID section heading?
* use blosum score
* show frequency data
* handle edit conflicts

h1. Recent steps

In reverse chronological order:

* make variant summary editable
* add "status" page

EOF
);

go();

?>