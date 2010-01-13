<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: Status";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Next steps

In expected approximate chronological order:

* display 1000-genomes frequency in reports
* include more fields in dump: dbsnp id, frequency (hapmap+1000-genomes), OR (gwas), #genomes, #haplomes, #web hits
* web-hits reports: all, frequency < 5%, without OMIM, without OMIM + freq < 5%
* find out why NA12878 has no chr:pos on CYP2C9-R144C
* update genetests gene list
* import snpedia data
* omim - don't clobber unknown with p.path when importing existing variants
* (trait-o-matic) auto-update get-evidence results when db changes
* (trait-o-matic) add "affects self (hom or dominant)" checkbox (vs "affects offspring") on result page
* curator sign-off on latest version... and add link to download resulting "release" snapshot
* "curator's report": pages with un-signed-off edits
* handle edit conflicts
* prevent "you have unsaved changes" from scrolling off the top on long pages
* auto-complete and sanity-check gene names based on refFlat
* graph frequency vs. odds ratio
* support publications without PMIDs (other namespaces? original contributions? OWW?)
* fix character encoding in pubmed summaries
* figure out better solution to HNF1A-Ser574Gly (genomes) vs. HNF1A-Gly574Ser (omim)
* create separate impact category for susceptibility variants?

h1. Recent steps

In reverse chronological order:

* import 1000-genomes frequency data
* link to ncbi dbsnp page if rsid known
* show total # matching variants in reports, and allow paging
* subtract local hits (snp.med and here) from hitcount stats
* show the first few web search hits right on the variant page
* exclude web search hits at snp.med.harvard.edu and here
* fix "[]" links on reports for variants with no genome evidence
* incorporate "web search results" using yahoo search API
* fix 100% C reported as 0% C in MYOT-K74Q
* import GWAS database
* inspect edit history for each page/item
* spell out hapmap population names
* (trait-o-matic) show get-evidence + pharmgkb results to authenticated / local users ('getevidence' user)
* add "external data" section to variant page
* add OMIM Importing Robot (adds variants, changes impact of new/existing from unknown to p.path, and stores summary/url in yet-undisplayed "variant_external" table)
* make sure compound hets are imported/displayed correctly
* update hapmap frequency data when importing from snp.med
* basic edit history / contributor list on variant page
* offer reports without "het SNP at recessive variant" occurrences
* indicate "hom" SNPs in reports
* import het/hom field so inheritance pattern can be matched against snp type
* rearrange report format, link genome ids to anchors on variant page
* "actionable for population" report: pages with impact=[p]pathogenic
* "suggested edits" report: pages with impact=[p]pathogenic and no short summary?
* get summary from pubmed when adding publication; show author/title in PMID section heading
* link to multiple job-ids per genome, if applicable
* delete variant_occurs rows if necessary during import_genomes
* link to a specific job-id on snp.med instead of just human-id (currently GMC links go to the PGP result which doesn't have HFE H63D for example)
* fix "search is case-sensitive"
* (trait-o-matic) make sure "update results" works on snp-dev2 and snp.med
* add {rsid,variant} table, update when importing from snp.med
* update "genome evidence" using "/browse/allsnps" report from snp.med
* update snp.med.harvard.edu so it has EB data source, mark as "beta"
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