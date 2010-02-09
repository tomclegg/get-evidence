<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: Status";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Next steps

In expected approximate chronological order:

* include each variant's max odds ratio in download
* keep odds ratio updated when editing individual figures
* include #haplomes in db dump
* find out why NSF-Lys702Asn shows wrong hapmap frequency
* (trait-o-matic) auto-update get-evidence results when db changes
* *submit article*
* find out why NA12878 has no chr:pos on CYP2C9-R144C
* IE test/fix
* (trait-o-matic) add "affects self (hom or dominant)" checkbox (vs "affects offspring") on result page
* import snpedia data
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

* show NBLOSUM100 score using biopython blosum100 matrix
* omim - don't clobber unknown with p.path when importing existing variants
* update variant quality headings/tooltips, and impact options
* add rationale for variant quality ratings
* add "all variants which you have edited" report
* "show next page" and "show all" buttons on "recent edits"
* compress sequences of similar edits on "recent edits"
* note disease names on "recent edits" where applicable
* add 5-axis "strength of evidence" table (computational, molecular/cellular, population/OR, family/LOD, outcomes)
* change "unknown" impact values to "other" if human-edited
* add "other" option to impact/inheritance fields, add "protective" options to impact field
* add "unpublished cases/controls" table
* show overall OR for each disease at top of page
* round off odds ratio to 1/1000, or 1 if >1000
* editable OR figures (cases/controls with/without) for each {variant,disease,publication}
* fix character encodings
* import genenames database
* display genetests results on variant page ("example":HBB-E6V)
* import genetests gene->disease data
* import hapmap frequencies for GWAS variants
* create variants for gwas entries with no local genome evidence
* remove repetitive hapmap figures from genomes section
* include hapmap frequencies in overall frequency calculations
* include max GWAS odds ratio in database dump
* show search results in a paged report
* show more GWAS fields in "external references"
* include more fields in "database dump":download: dbsnp id, overall frequency (currently just 1000-genomes), #genomes, #web hits
* web-hits "reports":report: "all":report?type=web-search, "frequency < 5%":report?type=web-search&rare=1, "without OMIM":report?type=web-search&noomim=1, "without OMIM + freq < 5%":report?type=web-search&noomim=1&rare=1
* display overall frequency in reports
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