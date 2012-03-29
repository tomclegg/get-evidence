<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: About";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF


p(((. !img/GET-Evidence_summary_drawing.png!

*GET-Evidence* is a collaborative research tool for understanding how genetic variants impact human traits and diseases. As such, it is subject to many sources of error, including: 
* human error in interpretations
* errors or uncertainty in literature
* errors in automatic/computational processing

GET-Evidence is *not* a "clinical quality" tool for diagnosis or treatment of any disease or medical condition. 

By using GET-Evidence you agree to our "Terms of Service":tos. 

h2. What is GET-Evidence for?

Whole genome sequencing results in thousands of variants which may have a functional consequence and have published literature findings which require review. Even after review, these findings need to be organized according to severity, impact, and strength of evidence to allow whole "genome interpretation":guide_reading_genome_reports. GET-Evidence was created to address this problem.

GET-Evidence is also a public forum for forming consensus. GET-Evidence uses a "peer production" model for "variant interpretation":guide_reading_variant_reports: all users who log in may "edit":guide_editing variant interpretations. This model facilitates the updating and correction of interpretations, facilitating the creation of a consensus from various publications and interpretations. 

Finally, GET-Evidence is a public resource. Variant interpretations are shared freely (without copyright restriction). As such, GET-Evidence is a fully public method which others may build upon and against which private interpretations methods may be compared.

GET-Evidence was developed to support work carried out by the "Personal Genome Project":http://www.personalgenomes.org. You can see some examples of "genome reports":genomes from Personal Genome Project participants and other public genomes. 

We also have various "guides":guides available for using and editing our system, including a "guide to reading genome reports":guide_reading_genome_reports and a "guide to reading variant reports":guide_reading_variant_reports.

h2. Downloading data or source code

User-contributed variant interpretations are accepted and published under CC0 -- i.e., without copyright protection. You are welcome to obtain the data either by means of the web service or by "downloading a copy of the database":download.

The source files for genome data, along with GET-Evidence's annotations, are provided alongside genome reports. See our "file format descriptions":upload_and_source_file_formats for more information on the format we use.

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
