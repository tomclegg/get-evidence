<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: About";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. GET-Evidence

GET-Evidence is a system for analyzing genetic variants. All aspects of GET-Evidence are subject to improvement&mdash;we hope that others will contribute to the design of the system as well as to individual variant evaluations. Please see our "guide to editing GET-evidence":guide_editing to learn how to participate.

h2. Disclaimer

GET-evidence is a research tool and not intended for clinical use. The GET-evidence system is still under development, many variants have not been fully curated and some fields may contain "default scoring":guide_preliminary_score.

h2. Variants

Currently GET-evidence only tracks nonsynonymous SNPs (missense and nonsense mutations). The automatic portion of genome analysis takes all reported SNPs and finds all nonsynonymous SNPs (missense and nonsense mutations)—these are then loaded into GET-evidence's database of variants. There variants are specified by gene name followed by amino acid change, for example "NPHP4 R848W":NPHP4-R848W (or NPHP4 Arg848Trp) refers to an amino acid change of arginine to tryptophan at position 848 in the gene product of NPHP4. 

h3. Variant Impact Score

Each variant is graded in six categories, the first four related to evidence and last two related to clinical importance. Each category may be assigned up to five points to reflect the strength of evidence in that category. Zero points reflects no significant evidence in a category, -1 reflects contradicting evidence. These six categories are the "variant impact score":guide_impact_score.
* Computational evidence
* Functional evidence
* Case/control evidence
* Familial evidence
* Disease severity
* Disease treatability

Variant impact is classified into one of four categories:
* pathogenic
* benign
* protective
* pharmacogenetic

The number of points in the variant impact score categories is used to assign "qualifiers":guide_qualifiers that reflect variant evidence and clinical importance. A pathogenic variant with high clinical importance but low evidence is called "high clinical importance, unknown pathogenic".

h3. Resources for variant evaluation

Various databases and tools are included within GET-evidence to assist variant evaluation. These include:

* "Online Mendelian Inheritance in Man":http://www.ncbi.nlm.nih.gov/omim (OMIM): Any variants mentioned in OMIM are noted by GET-evidence.
* "GeneTests":http://www.genetests.org: A list of genes for which genetic testing exists.
* BLOSUM100 is used as a score to reflect how nonconservative an amino acid change is.
* "HapMap":http://hapmap.ncbi.nlm.nih.gov/ and "1000 Genomes":http://www.1000genomes.org: allele frequency information from these projects is recorded.

Some of these internal data sources are used to automatically generate "preliminary variant impact scores":guide_preliminary_score. This focuses attention on variants with potentially clinical relevance; later evaluation of the variants replaces the preliminary scoring.

Relevant publications may be added to a variant's page using that publication's PubMed ID. GET-evidence then generates an entry for that publication on the variant's page with the paper's authors and title. A field is provided below this in which an evaluator can fill out a summary describing the paper's relevance to this variant.

h2. Genomes

GET-Evidence also provides a location for analyzing whole genome data&mdash;currently all publically available genomes are loaded into GET-Evidence. Each variant lists the genomes within which the variant was found.

You can also access individual genomes at "snp.med.harvard.edu":http://snp.med.harvard.edu. If you go to "snp.med.harvard.edu/authenticate":http://snp.med.harvard.edu/authenticate and use the username "getevidence" and the password "demo" you will have access to links to GET-evidence pages from the genome pages.

h2. Access

The data within GET-evidence is free to obtain and use, either by means of the web service or by downloading a copy of the database.

h2. Contributing

We encourage users of GET-Evidence to add, correct, and update variant evaluations, please see our "editing guide":guide_editing to learn how. Contributions are immediately reflected in the downloadable database. All contributions to GET-evidence's variant evaluations must be available for free public distribution under a CC0 license.

If you wish to extend and improve upon the GET-Evidence software, we welcome additional contributors to the project. Please contact us for instructions. General suggestions on other potential improvements are also welcomed.
EOF
);

go();

?>
