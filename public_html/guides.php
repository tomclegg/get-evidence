<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Guides";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. GET-Evidence Guides

You can use GET-Evidence in multiple ways--from genome interpretation to variant editing. These guides will help walk you through using our system.

h2(. "Reading Genome Reports":guide_reading_genome_reports

p((. GET-Evidence was initially created around interpreting genomes. We recommend anyone new to the site check out our "genome reports":genomes.

p((. These genome reports have been created using Personal Genome Project genomes and other publicly released genomes. These reports are _not_ clinical quality: they reflect a large body of research claims and are far from well-established. Many areas of genetics research are poorly understood (and sometimes hotly debated!), and human error is possible at all levels.

p((. To learn more about how to read GET-Evidence's genome reports, see our "guide to reading genome reports":guide_reading_genome_reports.

h2(. "Reading Variant Reports":guide_reading_variant_reports

p((. Each genome report contains links to various genetic variant interpretations. You can also find these interpretations directly by searching our database. Variant interpretations form the core of GET-Evidence. Remember, these interpretations are often flawed&mdash;they are simply our collective efforts at understanding what a genetic variation means.

p((. To learn more about how to read GET-Evidence's variant interpretations, see our "guide to reading variant reports":guide_reading_variant_reports.

h2(. "Editing Variant Reports":guide_editing

p((. Anyone can create an account and edit GET-Evidence. We encourage everyone to contribute and help form a consensus on genetic variant interpretations. All contributions are reshared as public domain, not owned by us: GET-Evidence’s data is shared under a "CC0 license":http://creativecommons.org/publicdomain/zero/1.0/.

p((. To learn more about how to contribute and add to variant reports, see our "editing guide":guide_editing.

h2(. Downloading Genetic Data

p((. Original genome data files (the "source files") interpreted by GET-Evidence are made available as links at the top of each "genome report":genomes. In addition, we provide files containing the same genome data with our own annotations added. To read more about the format of these see our "upload and annotated file formats":guide_upload_and_annotated_file_formats.

h2(. Uploading and Analyzing Genetic Data

p((. We currently allow other researchers to upload and automatically form private genetic interpretations using our system. This process produces "private" versions of the genome reports. As stated above, these are not "clinical quality" -- they are likely to be flawed for various reasons. In addition, although we will not intentionally make such data public, we cannot guarantee the privacy and security of such privately uploaded data. Please contact us if you are interested in a more secure solution.

p((. To upload data you must log in to our system and agree to our "terms of service":tos. The tool for uploading genetic data is at the bottom of the "genomes":genomes page. For information on what file format is required for this upload, see our "upload and annotated file formats":guide_upload_and_annotated_file_formats.

EOF
);

go();

?>
