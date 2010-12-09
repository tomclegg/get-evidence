<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: File and call format";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Variant call nomenclature

In the process of interpreting a genome we match dbSNP IDs and predict amino acid changes in order to assist interpretation and allow comparison against other variant databases. Internally these are stored within a GFF file's "Attributes" column using "db_xref" and "amino_acid" variable names. 

Any variants which occur within coding region is analyzed to see if it causes an amino acid change. We examined "other standard nomenclature":http://www.hgvs.org/mutnomen/disc.html for calling amino acid changes, but we wanted something more concise and standardized for amino acid insertions and deletions. In general it follows this pattern: two strings seperated by a space, the first is the gene name, the second string combines three data: reference sequence, first reference position, variant sequence (entirely replacing the reference).

h3. Other guides

* "Guide to editing":guide_editing: Explains how to edit variant evaluations.
* "Variant impact score":guide_impact_score: Explains how the variant impact score is determined.
* "Qualifiers":guide_qualifiers: Explains how the variant impact score determines the description of the variant as “uncertain”, “likely”, etc.
* "Amino acid calls":guide_amino_acid_calls: Explains our annotation for amino acid changes predicted from genetic variants

h3. Single nucleotide substitutions causing missense mutations

These are the most common type of amino acid change. We follow typical nomenclature and describing these with the gene name follow by reference_aa/aa_position/variant_aa. Reference and variant amino acids can be single letter or three letter codes.

Examples: 
* "SERPINA1 E366K" or "SERPINA1 Glu366Lys" - In the SERPINA1 gene, the 366th amino acid, glutamic acid, is changed to lysine.
* "C3 R102G" or "C3 Arg102Gly" - In the C3 gene, the 102nd amino acid, arginine, is change to glycine.

h3. Single nucleotide substitutions causing nonsense mutations (stop codon)

These are often reported in the literature with "&#42". We sometimes instead report these with "X" for convenience (because the "&#42" character is used as a wildcard in regular expressions and UNIX commands). When using three letter abbreviations for amino acids we used the word "Stop".

Examples:
* "TLR5 R392*", "TLR5 R392X", or "TLR5 Arg392Stop" - In the TLR5 gene, the 392nd amino acid, arginine, is changed to a stop codon.
* "FUT2 W154*", "FUT2 W154X", or "FUT2 Trp154Stop" - In the FUT2 gene, the 154th amino acid, tryptophan, is changed to a stop codon.

h3. Longer substitutions causing changes at more than one amino acid

Occassionally there are substitutions of more than one nucleotide, and occassionally these result in multiple, neighboring amino acid changes. We report reference amino acids, followed by the position of the first reference amino acid, then the variant amino acids that are replacing the reference.

Examples:
* "DLC1 QN254HD" or "DLC1 GlnAsn254HisAsp" - In the DLC1 gene the 254th and 255th amino acids, glutamine and asparagine, are changed to histidine and aspartic acid.
* "OR4C3 TY217MH" or "OR4C3 ThrTyr217MetHis" - In the OR4C3 gene the 217th and 218th amino acids, threonine and tyrosine, are changed to methionine and histidine.

h3. In-frame insertions

Our reporting of in-frame insertions begins by describing the reference amino-acid position after which the insertion occurs, followed by that amino acid's position, then the variant amino-acid sequence starting at that position (including the reference amino acid). This repetition of the reference amino acid is redundant but maintains consistency in our annotation system (all sequence given before the position is replaced by all sequence after the position).

Examples:
* "EME1 K137KQ" or "EME1 Lys137LysGln" - In the EME1 gene after the 137th amino acid, lysine, there is an insertion of a single amino acid: glutamine.
* "GPRIN2 E240EMRE" or "GPRIN2 Glu240GluMetArgGlu" - In the GPRIN2 gene after the 240th amino acid, glutamic acid, three amino acids are inserted: methionine, arginine, and glutamic acid.

h3. In-frame deletions

Our reporting of in-frame deletions begins by describing the reference amino-acid positions that are deleted, followed by the first position of these, followed by "Del".

Examples:
* "ESR2 N181Del" or "ESR Asn181Del" - In the ESR2 gene the 181st amino acid, asparagine, is deleted.
* "ZNF283 EM139Del" or "ZNF283 GluMet139Del" - In the ZNF283 gene the 139th and 140th amino acids, glutamic acid and methionine, are deleted.

h3. Other in-frame, length changing amino acid changes

The reference sequence that is replaced is first given, followed by the position of the first amino acid in that sequence, followed by the variant amino acid sequence replacing it.

Examples:
* "ABCA13 EI2582D" or "ABCA13 GluIle2582Asp" - In the ABCA13 gene the 2582nd and 2583rd amino acids, glutamic acid and isoleucine, are deleted and replaced by a single amino acid, aspartic acid.
* "RP1L1 EGTEG1308TE" or "RP1L1 GluGlyThrGluGly1308ThrGlu" - In the RP1L1 gene the 1308th, 1309th, 1310th, 1311th, and 1312th amino acids (glutamic acid, glycine, threonine, glutamic acid, and glycine) are replaced by two amino acids (threonine and glycine).

h3. Frameshift mutations

To describe frameshift mutations, we report the first amino acid which has been affected by the frameshift, followed by "Shift".

Examples:
* "ZNF717 N673Shift" or "ZNF717 Asn673Shift" - In the gene ZNF717 a framshift occurs from the 673rd amino acid (asparagine) onwards.
* "BTNL2 H151Shift" or "BTNL2 His151Shift" - In the gene BTNL2 a frameshift occurs from the 151st amino acid (histidine) onwards.

EOF
);

go();

?>
