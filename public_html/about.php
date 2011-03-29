<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: About";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. GET-Evidence

GET-Evidence is a system that assists evaluation of genetic variants in whole genomes.

*Note:* This is a research tool and is not intended for use in the diagnosis or treatment of any disease or medical condition. GET-Evidence has been developed to support work carried out by the "Personal Genome Project":http://www.personalgenomes.org. By using GET-Evidence you agree to our "Terms of Service":tos. GET-Evidence's system is still under development: many variants have not been fully curated and some fields may contain default scoring. 

h2. Genome reports

Whole genome sequencing results in thousands of variants which may have a functional consequence and have published literature findings which require review. Even after review, these findings need to be organized according to severity, impact, and strength of evidence to allow whole genome interpretation.

To facilitate whole genome interpretation, GET-Evidence matches genetic variants against its own and other databases and produces two reports:

* A sorted report of well-evaluated variants from GET-Evidence itself
* A prioritized list of insufficiently evaluated variants still requiring review. 

You can see some examples of "genome reports":genomes from Personal Genome Project participants.

h2. User-updated variant interpretations

Users facilitate the process of genome evaluation by recording and sharing their literature findings publicly. The variant annotation pages are thus a forum in which various researchers can establish consensus interpretations for genetic variants.

We hope that you will consider contributing to variant evaluations; please see our "guide to editing GET-Evidence":guide_editing to learn how to participate.

h2. Data download and source code

User-contributed variant interpretations are accepted and published under CC0 -- i.e., without copyright protection. You are welcome to obtain the data either by means of the web service or by "downloading a copy of the database":download.

The web service software is available under the GNU General Public License, version 3.

* Project home: "https://redmine.clinicalfuture.com/projects/get-evidence":https://redmine.clinicalfuture.com/projects/get-evidence
* Source code repository: "git://git.clinicalfuture.com/get-evidence.git":git://git.clinicalfuture.com/get-evidence.git
* Source code repository mirror: "https://github.com/tomclegg/get-evidence":https://github.com/tomclegg/get-evidence

h2. Community

We have two public email lists potential contributors may wish to join:
* "get-editors":http://lists.freelogy.org/mailman/listinfo/get-editors: Community for editors contributing to variant evaluations on GET-Evidence, discussion of specific variant evaluations and general editing guidelines.
* "get-dev":http://lists.freelogy.org/mailman/listinfo/get-dev: Community for GET-Evidence software development and related computational processing of genome and phenome data.

EOF
);

go();

?>
