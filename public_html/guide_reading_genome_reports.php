<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Guide to Reading Genome Reports";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Reading Genome Reports

Genome reports on GET-Evidence are produced when a genome is matched against our database of genetic variants. When you open a genome report, you will see the following header information followed by a set of tabs:

p(. !img/sample_genome_report_1.gif!

The header information contains information about this report, including name, URL, debugging information, and associated files you can download. An explanation of these files in our "guide to downloading genetic data":guide_upload_and_source_file_formats. Below we describe the different tabs associated with the genome interpretation.

You may also wish to visit our "Genome Report FAQ":guide_genome_reports_faq for questions you might have.

h2. Genome report

p. This tab opens up by default and lists interesting variants found in this genome. Only variations which have been sufficiently evaluated are listed here (see section below regarding "insufficiently evaluated variants"). By default variants are listed such that the most pathogenic variants with the strongest supporting evidence are at the top of the report.

p(. !img/sample_genome_report_2.gif!

p. Columns are as follows:

p(. 1. *Variant*: The name of the variant in GET-Evidence. Clicking on this link brings you to the variant report for this variant, see our "guide to reading variant reports" for more information on these. The preferred ID for a variant is, if it exists, a gene & amino acid change (see our "variant call nomenclature":guide_amino_acid_calls for more information on this). If a variant isn't associated with an amino acid change, it may instead be referred to by its dbSNP "rsID".

p(. 2. *Clinical importantance*: Variants can be sorted according to how important GET-Evidence thinks their clinical effect is. This is a combination of how severe a variant's effect is on health and how treatable the resulting condition is.

p(. 3. *Impact*: This column combines a couple pieces of information. First, the strength of evidence for a variant's predicted effect is listed (well-established, likely, or uncertain). Then the impact is listed (pathogenic, protective, benign, or pharmacogenetic). Finally the inheritance pattern is given (recessive, dominant, or other) followed by this genome's zygosity for the variant (homozygous or heterozygous).

p(. 4. *Allele frequency*: This column lists GET-Evidence's allele frequency information for the variant. A "?" indicates GET-Evidence lacks this information. Allele frequency can help understand how serious a variant is -- if many other people have the same variant, it's unlikely to be very harmful. Note that the converse is not true, however: just because a variant is rare doesn't mean it's serious. All genomes have many rare variants, and most of these have no significant effect.

p(. 5. *Summary*: This is a short description regarding this variant's effect on health/traits, taken from the variant page's "short summary".

In addition, on the upper-right you'll see a filter button. By default, a genome report only lists pathogenic variants which are uncommon or rare (allele frequency of less than 10%). To make all variants visible, click the "Show all" button.

p(. !img/sample_genome_report_3.gif!

h2. Insufficiently evaluated variants

p. This tab contains a list of variants which haven't yet had a full interpretation performed within GET-Evidence. Each genome has thousands of variants affecting genes -- most of these do not yet have any interpretation associated with them. Once a variant has enough data recorded such that it is "considered sufficiently evaluated":guide_sufficiently_evaluated, it will appear instead in the "genome report" tab.

p. To assist genome interpretation, GET-Evidence prioritizes insufficiently evaluated variants. When evaluating a new genome, a researcher might want to go to this tab and look for any prioritized variants that might be important to interpret. Once a variant interpretation is recorded it will be re-used by all other genomes that share that variant.

p(. !img/sample_genome_report_4.gif!

p. To give a sample of how this report may look for a newly analyzed genome, the above example was taken from a genome report which had not been specifically reviewed at the time these instructions were written (GS19020, Luhya Kenyan male). Columns are as follows:

p(. 1. *Variant*: The name of the variant in GET-Evidence. Clicking on this link brings you to the variant report for this variant. For "insufficiently variants" these reports are mostly empty, see our "guide to editing variants":guide_editing for information on adding information to variant reports.

p(. 2. *Prioritization score*: Prioritization scores are a maximum of six points and reflect a combination of factors that could make a variant more interesting to review (higher = potentially more interesting). These factors are: (1) presence of variant-specific information in publications or databases (2) whether the affected gene is associated with known diseases (3) computational prediction of how disruptive the variant is. For more information on how this score is calculated, see our "prioritization score guide":guide_prioritization_score.

p(. 3. *Allele frequency*: This column lists GET-Evidence's allele frequency information for the variant (as described above). Some reviewers might decide to review variants with low frequencies, because variants with strong trait effects are usually rare. Others might decide to review common variants, because these variants are found in many genomes (and so such reviews would improve the genome reports for many genomes).

p(. 4. *Number of articles*: This column lists how many articles have been linked to the variant report. These may have been automatically, or they may have been added by other editors to facilitate genome interpretations. The presence of linked articles may make a variant more interesting for a potential reviewer.

p(. 5. *Zygosity and prioritization score reasons*: This contains two pieces of information. First, the zygosity of the variant (heterozygous or homozygous) is given -- this could influence a reviewer's interest. Second, reasons for the prioritization score are given. This includes: (1) Which, if any, variant-specific databases the variant was found in (including OMIM, PharmGKB, and HuGENet). (2) Polyphen score (a computational prediction of disruptiveness) or other indications of disruptiveness (nonsense or frameshift mutations). (3) Whether a gene is listed in GeneTests (indicating it is used in clinical testing for some genetic disease).

h2. Coverage

p. Genetic variations are called by GET-Evidence when a position is different from the "reference genome". One thing missing in the report, however, is whether variants _not_ reported matched reference -- or whether they were simply not successfully sequenced. The coverage report tab is created to address this.

p. This report lists all "coding regions" (i.e. exome regions) that were not successfully sequenced.

p(. !img/sample_genome_report_5.gif!

p. Columns are as follows:

p(. 1. *Gene*: Gene symbol (as assigned by HGNC).

p(. 2. *Chromosome*: Chromosome number/letter (e.g. 1, 2, 3... 21, X, or Y)

p(. 3. *Coverage*: Percentage of this gene's coding regions (exons) that were successfully sequenced and called as either reference or variant.

p(. 4. *Missing*: Number of bases missing (not covered) in this gene's coding regions (exons).

p(. 5. *Length*: Total number of bases in this gene's coding regions (exons).

p(. 6. *Missing regions*: Chromosome positions of the missing bases for this gene's coding regions. Note that these coordinates depend on the genome build, which can be found in the metadata report tab (see below).

h2. Metadata

p. This report lists some general statistics for this particular genome / genetic data set.

p(. !img/sample_genome_report_6.gif!

EOF
);

go();

?>
