<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: About";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. GET-Evidence

GET-Evidence is a system for analyzing genetic variants -- it is a research tool and is not intended for use in the diagnosis or treatment of any disease or medical condition. GET-Evidence has been developed to support work carried out by the "Personal Genome Project":http://www.personalgenomes.org. By using GET-Evidence you agree to our "Terms of Service":tos.

GET-Evidence's system is still under development: many variants have not been fully curated and some fields may contain default scoring. We hope that others will contribute to the design of the system as well as to individual variant evaluations. Please see our "guide to editing GET-evidence":guide_editing to learn how to participate.

h2. Variant data

GET-Evidence assists genome interpretation by organizing and analyzing variant-specific information. Our genome analysis tool automatically finds and prioritizes genetic variants found in whole genome data.

Currently GET-evidence only analyzes substitution variants which differ relative to the reference genome. Within these, we focus on variants predicted to cause a change to protein structure (missense and nonsense mutations). There variants are specified by gene name followed by amino acid change, for example "NPHP4 R848W":NPHP4-R848W (or NPHP4 Arg848Trp) refers to an amino acid change of arginine to tryptophan at position 848 in the gene product of NPHP4.

In addition, we perform some analysis of variants on the basis of dbSNP ID, these variants are specified by the letters "rs" followed by a dbSNP number, for example "

h3. Variant Impact Score

Users may score a variant in seven categories, these scores then facilitate later analyses performed by all users. The first four related to evidence and last three related to clinical importance. Each category may be assigned up to five points to reflect the strength of evidence in that category. Zero points reflects no significant evidence in a category, -1 reflects contradicting evidence. These seven categories are the "variant impact score":guide_impact_score.
* Computational evidence
* Functional evidence
* Case/control evidence
* Familial evidence
* Disease severity
* Disease treatability
* Penetrance

Variant impact is classified into one of four categories:
* pathogenic
* benign
* protective
* pharmacogenetic

The number of points in the variant impact score categories is used to assign "qualifiers":guide_qualifiers that reflect variant evidence and clinical importance. A pathogenic variant with high clinical importance but low evidence is called "high clinical importance, unknown pathogenic".

h3. Autoscore prioritization

Insufficiently reviewed variants are reported on the basis of their classification by "autoscore". This score combines two goals in prioritization: (1) Prioritization of variants which have published findings (2) Prioritization of variants which are predicted to have a pathogenic effect.

Currently this score consists of:
* up to 2 points for presence in variant-specific databases (OMIM, HuGENet, PharmGKB)
* up to 2 points for presence in a gene-specific database (GeneTests - genes with clinical testing available)
* up to 2 points for computational evidence supporting a pathogenic effect

h3. Associated literature

Relevant publications may be added to a variant's page using that publication's PubMed ID. GET-evidence then generates an entry for that publication on the variant's page with the paper's authors and title. A field is provided below this in which an evaluator can fill out a summary describing the paper's relevance to this variant.

h2. Genomes

GET-Evidence also provides a location for analyzing whole genome data&mdash;currently all publically available genomes from the Personal Genome Project are loaded into GET-Evidence. We currently allow users to upload and analyze personal data, but we do not guarantee privacy and strongly encourage users to create their own private instances of GET-Evidence or to contact us for other options.

h2. Access

The data within GET-evidence is free to obtain and use, either by means of the web service or by downloading a copy of the database. The source code is shared under a GPL v3 license and is available on github: "https://github.com/tomclegg/get-evidence"(https://github.com/tomclegg/get-evidence).

h2. Contributing

We encourage users of GET-Evidence to add, correct, and update variant evaluations, please see our "editing guide":guide_editing to learn how. Contributions are immediately reflected in the downloadable database. All contributions to GET-evidence's variant evaluations must be available for free public distribution under a CC0 license.

In addition, if you wish to extend and improve upon the GET-Evidence software, we welcome additional contributors to our software project. General suggestions on other potential improvements are also welcomed.

We have two public email lists potential contributors may wish to join:
* "get-editors":http://lists.freelogy.org/mailman/listinfo/get-editors: Community for editors contributing to variant evaluations on GET-Evidence, discussion of specific variant evaluations and general editing guidelines.
* "get-dev":http://lists.freelogy.org/mailman/listinfo/get-dev: Community for GET-Evidence software development and related computational processing of genome and phenome data.

h2. Contact

For now, try using the public mailing lists. We will fill in a private email address here later.

EOF
);

go();

?>
