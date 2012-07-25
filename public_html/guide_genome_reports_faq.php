<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Genome Reports FAQ";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Genome Reports FAQ

h3. How can I find out whether a genome called a position as matching reference, or if it simply didn't have enough reads for the position to call it at all?

p(. To find out whether a genome considers a particular position to be "matching the reference genome" vs. "not covered", we will use the "Metadata" and "Coverage" tabs of the genome report.

p(. First, you need to find out the chromosome coordinate for the variation you are interested in. For example, you may be interested in the variation "APOE4" for the gene "APOE". This variation exists in a region that some technologies have difficulty sequencing. In GET-Evidence the APOE4 variant is known as "APOE-C130R" (this variation distinguishes it from the reference genome, which is the APOE3 variant). GET-Evidence also lists the variant as having a dbSNP ID of 

p(. To find out the chromosome coordinates, you also need to check which genome build this data matches:

p((. !img/sample_genome_report_6.gif!

p(. The above example is for a data matched with genome build 37. Currently the only other possibility is genome build 36.

p(. Now, look up the position in some other database that contains chromosome coordinate information. For example we can use dbSNP and "look up using the ID rs429358":http://www.ncbi.nlm.nih.gov/projects/SNP/snp_ref.cgi?rs=rs429358. This report lists the chromosome position for the variant in build 37 as chr19:45411941.

p(. Use the coverage tab and find the gene you're interested in:

p((. !img/sample_genome_report_7.gif!

p(. The missing positions listed here include 45411923 and 45411945. Because 45411941 is not listed as missing, it has been covered -- if it is not listed as a variant in the genome report, this implies that both copies of the genome at this position matched referenc.

h3. What about read depth -- the number of sequencing reads that covered a position? 

h3. How can I find out how confident a call is?

p(. GET-Evidence interprets genetic variation information from a variety of technologies -- but one thing it does not do is attempt to assess confidence in those genetic variations. The "number of reads" matched to a position is often used as a measure of confidence -- but this alone is not enough, other factors also play a role (e.g. how uniquely mapped those reads were, or the quality of the raw image data for a given read).

p(. Because assessing such confidence information can be very technology dependent (indeed, it may even change as a particular technology improves), GET-Evidence makes no attempt to determine confidence or report the number of reads for a given position. When loading and interpreting genome data, all variations given to GET-Evidence will be interpreted regardless of confidence or related information.

h3. How can I download the raw genome data?

p(. At the top of each genome report, GET-Evidence provides a link to the original file interpreted by GET-Evidence ("source data"). In addition, GET-Evidence provides an output file you can download that includes some annotations added by GET-Evidence (e.g. amino acid change predictions). For more information on this file see our "upload and annotated file formats":guide_upload_and_annotated_file_formats.

EOF
);

go();

?>
