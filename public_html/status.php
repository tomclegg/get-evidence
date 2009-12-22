<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: Status";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Next steps

In expected approximate chronological order:

* update snp.med.harvard.edu so it has EB data source, mark as "beta"
* add {rsid,variant} table
* update "genome evidence" using json data from snp.med -- so all nsSNPs on snp.med are listed (and {rsid,variant} mappings and hapmap frequency data are updated)
* update omim/snpedia sections of long summary using latest omim/snpedia databases (use p.pathogenic for omim results; unknown for other T-o-m results; p.benign for the rest)
* update "web search results" section of long summary using yahoo search API
* better edit history / stats
** show list of contributors on variant page
** show "top contributors"
* get summary from pubmed when adding publication; show author/title in PMID section heading
* handle edit conflicts
* prevent "you have unsaved changes" from scrolling off the top on long pages
* auto-complete and sanity-check gene names based on refFlat
* graph frequency vs. odds ratio
* support publications without PMIDs (other namespaces? original contributions? OWW?)
* curator sign-off on latest version... and add link to download resulting "release" snapshot

h1. Recent steps

In reverse chronological order:

* add EB data source to trait-o-matic -- snp-dev2.freelogy.org
* add hints about what you should enter in each field
* put notice on -dev site that this is a sandbox so don't do real editing here
* deploy at evidence.personalgenomes.org
* return to departure page instead of front page after openid login
* add "summary_long" field
* "dominance" => "inheritance"
* when reloading page with saved draft, only open "edit" tabs where content has changed
* make variant_dominance and variant_impact visible/editable
* add "download latest db"
* fix "PMIDs missing when not logged in"
* make variant summary editable
* add "status" page

EOF
);

go();

?>