<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: About";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Overview

Evidence Base is a public database of human genome variations of **clinical interest**.

h1. Data

Variants are specified by gene, amino acid coordinate, and AA change (for example, "NPHP4 R848W").

Each variant is stored with:

* a description of the variant's clinical relevance;
* a list of publications (with Pub Med IDs) that support the description;
* a summary of how each publication supports the description;
* a list of publicly available individual genomes exhibiting the variant;
* an assessment of how each individual genome supports the description (if available).

h1. Access

The data is free to obtain and use, either by means of the web service or by downloading a copy of the database.

h1. Contributing

Everyone may contribute.

All contributions are listed in the "latest" database snapshot as soon as they are submitted.

Contributions are then considered by curators for inclusion in the "release" database snapshot.

All submissions must be available for free public distribution under a CC0 license.
EOF
);

go();

?>