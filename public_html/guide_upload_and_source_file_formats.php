<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: File and call format";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Upload & source file format

The genome processing aspect of GET-Evidence interprets GFF formatted files that reports differences of the genome versus reference. Details on requirements, assumptions, and types of interpreted data are described here.

h3. Other guides

* "Guide to editing":guide_editing: Explains how to edit variant evaluations.
* "Autoscore":guide_autoscore: Explains how autoscore prioritization of variants for review is calculated.
* "Variant impact score":guide_impact_score: Explains how the variant impact score is determined.
* "Qualifiers":guide_qualifiers: Explains how the variant impact score determines the description of the variant as “uncertain”, “likely”, etc.
* "Amino acid calls":guide_amino_acid_calls: Explains our annotation for amino acid changes predicted from genetic variants

h3. General instructions

We use a variant of GFF files for genome processing. Files may be uploaded as plain text (with a .gff extension) or compressed with gzip (.gff.gz extension). If you click the "download" option at the top of a genome report you will see an example of input data we have used. We make a lot of assumptions about input data, so please read our descriptions below to be sure your data is processed properly.

*Columns must be tab separated* ("\\t" character) and should have the following data:

# Chromosome (e.g. "chr1", "chr12", "chrX", "chrM". Must be h18 / build 36.)
# Source (ignored)
# Type (e.g. "SNP", "REF", "SUB", "INDEL". *Only "REF" matters - our variant processing skips these rows* - other values are ignored.)
# Start (1-based) - must be hg18 / build 36
# End (1-based) - must be hg18 / build 36
# Score (ignored, "." may be used to leave the field empty)
# Strand (ignored, *we assume to be "+"*)
# Frame (ignored, "." may be used to leave the field empty)
# Attributes: semicolon separated features, described further below

*Currently we assume hg18 / build 36 positions* and are unable to interpret hg19 / build 37 data. 

The variant and reference sequences for each position are contained within the final "Attributes" column. Attributes in this column should be separated by semicolons. Within each, data is separated by whitespace, and the first value is taken to be the variable name.

Example:
@chr14\tCGI\tSNP 93914700\t93914700\t.\t+\t.\talleles C/T;amino_acid SERPINA1 E366K;db_xref dbsnp:rs28929474;ref_allele C@

The "Attributes" column in this row is taken to have the following variables and values:
* alleles = "C/T"
* amino_acid = "SERPINA1 E366K"
* db_xref = "dbsnp:rs28929474"
* ref_allele = "C"

The "alleles" data is the only data needed in an uploaded file. How to annotate use this variable to describe genome variants follows.

h3. Single Nucleotide Substitutions

Single nucleotide substitutions should start and end at the same position. The substituted allele(s) must described using the "alleles" variable name. Heterozygous alleles should be separated by a slash (e.g. "C/G") while homozygous or hemizygous alleles can be reported without a slash. You may also describe the reference allele using the "ref_allele" variable name.

Example rows:
@chr1\tCGI\tSNP\t31844\t31844\t.\t+\t.\talleles G
chr1\tCGI\tSNP\t43069\t43069\t.\t+\t.\talleles C/G
chr1\tCGI\tSNP\t45027\t45027\t.\t+\t.\talleles A@

h3. Multiple Nucleotide Substitutions

These are described similarly to single nucleotide substitutions. Start and end positions should be different and should match the length of sequences given in the alleles variable.

Example rows:
@chr2\tCGI\tSUB\t101087873\t101087876\t.\t+\t.\talleles CACA/GGTG
chr2\tCGI\tSUB\t101210966\t101210967\t.\t+\t.\talleles AC/CA
chr2\tCGI\tSUB\t101351061\t101351062\t.\t+\t.\talleles AG@

h3. Deletions

Start and end position refer to the reference allele position. The "empty value" that replaces reference for the position is described by "-".

Example rows:
@chr3\tCGI\tINDEL\t494450\t494450\t.\t+\t.\talleles -
chr3\tCGI\tINDEL\t502274\t502275\t.\t+\t.\talleles -/TT
chr3\tCGI\tINDEL\t507887\t507887\t.\t+\t.\talleles -/A@

h3. Insertions

To specify the position of insertions in a unique manner (different from single nucleotide substitution positions) we use an "end" value one base before the "start" value. This violates the GFF specification, which requires end positions to always be equal to or after start positions, but we choose to do this to have consistent interpretation of positions. The insertion occurs between the start and end positions.

Example rows:
@chr4\tCGI\tINDEL\t821159\t821158\t.\t+\t.\talleles C
chr4\tCGI\tINDEL\t824865\t824864\t.\t+\t.\talleles ACTT/-
chr4\tCGI\tINDEL\t871712\t871711\t.\t+\t.\talleles CA/-@

h3. Other length changing alleles

As in previous examples, these should have positions which describe the reference sequence positions that are replaced by the variant allele(s).

Example rows:
@chr5\tCGI\tINDEL\t2237775\t2237777\t.\t+\t.\talleles A/CTT
chr5\tCGI\tINDEL\t2336687\t2336688\t.\t+\t.\talleles GTAGGA
chr5\tCGI\tINDEL\t2339000\t2339000\t.\t+\t.\talleles AAA/A@

Note: This last row looks like it should have been an "insertion"? Actually, in this case the reference allele at this position is "C"! Both alleles called here are non-reference.

h3. Coverage information (positions which match reference)

We include regions which have been sequenced and match the reference genome in the GFF source data we make available. These are marked by the value "REF" in the third column ("Type") and our processing system currently ignores these rows when analyzing genomes.

Example rows:
@chr6\tCGI\tREF\t736528\t736790\t.\t+\t.\t.
chr6\tCGI\tREF\t736794\t737031\t.\t+\t.\t.
chr6\tCGI\tSNP\t737032\t737032\t.\t+\t.\talleles C/T;ref_allele C
chr6\tCGI\tREF\t737033\t737283\t.\t+\t.\t.@

In this example there is sequencing coverage for chr6:736528-736790 and chr6:736794-737283, with a heterozygous non-reference call made at position chr6:737032. The three base region chr6:736791-736793 is missing, it has no sequencing call made and is not covered.

h1. Other attributes data

There are other attributes data which we attach to the gff files during processing. If you include this data in an uploaded file it will, for the most part, be ignored and replaced -- an exception is dbSNP data. You may have dbSNP ID's already attached to variant calls and this data may be more thorough than the calls we attempt to make. If you upload data containing dbSNP data please make sure it matches the format we describe here.

Example of pre-processed data:
@chr7\tCGI\tSNP\t82419795\t82419795\t.\t+\t.\talleles C/T
chr7\tCGI\tSNP\t82420782\t82420782\t.\t+\t.\talleles C/T
chr7\tCGI\tSNP\t82423791\t82423791\t.\t+\t.\talleles G/T@

The same positions after processing:
@chr7\tCGI\tSNP\t82419795\t82419795\t.\t+\t.\talleles C/T;amino_acid PCLO A2804T;db_xref dbsnp:rs976714;ref_allele C
chr7\tCGI\tSNP\t82420782\t82420782\t.\t+\t.\talleles C/T;amino_acid PCLO V2475I;db_xref dbsnp:rs10954696;ref_allele C
chr7\tCGI\tSNP\t82423791\t82423791\t.\t+\t.\talleles G/T;amino_acid PCLO Q1472K;ref_allele G@

h3. ref_allele (ignored and overwritten during processing)

The reference allele. It should match the length inferred from start and end positions.

h3. db_xref (we pay attention to this!)

We use this to report dbSNP ID's. dbSNP ID's should be begin with "dbsnp", followed by optional other information, then a colon (":"), then "rs" and the number. When there are multiple dbSNP ID's associated with the position they should be comma separated.

An example of some dbSNP ID's in uploaded data:
@chr8\tCGI\tSNP\t145222820\t145222820\t.\t+\t.\talleles G;db_xref dbsnp.116:rs7820984
chr8\tCGI\tINDEL\t145223654\t145223681\t.\t+\t.\talleles C/GGCAGTGGGCATGTGGAATACTTCTCCA;db_xref dbsnp.130:rs67708571,dbsnp.130:rs73717807
chr8\tCGI\tSNP\t145225126\t145225126\t.\t+\t.\talleles A/G@

Post-processing it looks like this:
@chr8\tCGI\tSNP\t145222820\t145222820\t.\t+\t.\talleles G;amino_acid CYC1 M76V;db_xref dbsnp.116:rs7820984;ref_allele A
chr8\tCGI\tINDEL\t145223654\t145223681\t.\t+\t.\talleles C/GGCAGTGGGCATGTGGAATACTTCTCCA;db_xref dbsnp.130:rs67708571,dbsnp.130:rs73717807;ref_allele GGCAGTGGGCATGTGGAATACTTCTCCA
chr8\tCGI\tSNP\t145225126\t145225126\t.\t+\t.\talleles A/G;db_xref dbsnp:rs13254954;ref_allele A@

h3. amino_acid

For any variants occurring within coding sequence, we check to see if how they change the predicted amino acid sequence. If so we report this in the "amino_acid" variable. Please see our guide to amino acid calls for information on the nomenclature we have chosen.

EOF
);

go();

?>
