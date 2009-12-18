<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: Status";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Next steps

In expected approximate chronological order:

* make variant_dominance and variant_impact visible/editable
* return to departure page after openid login
* deploy at evidence.personalgenomes.org
* add EB data source to trait-o-matic
* update "genome evidence" using json data from snp.med
* get PMID summary from pubmed when adding and show author/title in PMID section heading
* use blosum score
* show frequency data
* handle edit conflicts
* prevent "you have unsaved changes" from scrolling off the top on long pages

h1. Recent steps

In reverse chronological order:

* add "download latest db"
* fix "PMIDs missing when not logged in"
* make variant summary editable
* add "status" page

EOF
);

go();

?>