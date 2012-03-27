<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Guide to Editing";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Guide to editing

GET-evidence variant evaluations can be edited by anyone&mdash;our only requirement is that you log in using OpenID to edit using your real name and provide your email address. Editor contributions are critical to GET-evidence's success, we encourage all users of GET-evidence to also edit. Things you can do include: evaluating new variants, correcting errors, and updating evaluations.

h2. Logging in with OpenID

OpenID is a method for authenticating individuals. There are many different providers of OpenID authentication, including Google and Yahoo. *Anybody with a Google or Yahoo account can log in* to GET-evidence using OpenID. There are many other OpenID providers, you can also log in using the appropriate URL.

h3. Google/Yahoo login

Click on the "Google login" or "Yahoo login" button on the right side of the page. This will bring you to a Google or Yahoo web page. If you aren't already logged in to your account this is a login page (if you already logged in it will skip to the next page). Go ahead and sign in.

Google or Yahoo will then ask if you want to release your email address to GET-Evidence. Click "Allow" and you will be logged in and able to edit.

h2. Where to start?

h3. Which variants need editing?

A great place to start is the "public genomes":genomes that we have uploaded. To learn more about how to read these pages please see our "guide to reading genome reports":guide_reading_genome_reports.

If you open a genome report you'll find a tab listing "Insufficiently reviewed variants". These are variants which do not have enough scores and impact information filled in to allow us to automatically sort them. We sort these using "prioritization score":guide_prioritization_score, which prioritizes variants based on their presence in variant-specific lists (indicating there is published literature available), gene-specific lists (indicating potential for that gene to cause disease), and computational evidence (reflecting automatic prediction whether a variant may cause disease).

"Scoring":guide_impact_score is the most important type of information to add to a variant interpretation. Once enough of these scores are added, a variant can be considered "sufficiently evaluated" by GET-Evidence.

h3. What sort of information is relevant?

Try finding some papers that might help you understand the variant. You can enter the Pubmed ID in a box at the bottom of the page to enter that publication into our system. There will be a field there for you to summarize data from that paper relevant to this variant. Read that paper looking for evidence that would contribute to scoring.

You should go read our description of "variant impact scores":guide_impact_score if you haven't already. Relevant information will be anything that contributes to scoring: Case/control numbers, familial inheritance, biochemical studies, etc.

Don't limit yourself to scoring, though -- you should also note other details that may be relevant to interpreting the variant. For example, if a variant is linked to another putatively pathogenic variant&mdash;this isn't captured in the scoring but is relevant to interpreting the variant.

h3. Where to find information?

The "other external references" section at the bottom of a variant page contains useful starting points: OMIM, GeneTests, and web search results. Once you find publications, you should add entries for them to GET-evidence and record the relevant information.

* *Web search*: You should always search for a variant using a search engine (internet search or journal search), if you're lucky you'll find a recent review article that summarizes research relevant to the variant. You can then follow those citations.
* *GeneTests*: If a gene is in GeneTests, you can try clicking on the GeneTests link in the "other external references" section. Sometimes you will find an associated GeneReviews link that summarizes the disease and treatment. This is especially useful for evaluating clinical importance categories (disease severity and disease treatability). If the review mentions this variant, it is a good starting point for finding relevant literature.
* *OMIM*: If available, OMIM is useful for providing a summary of the variant and a few relevant publications. You can find a link to the relevant OMIM entry in the "other external references" section.
* *Adding publications*: Once you find relevant publications, add them to the GET-Evidence page using their PMID (at the bottom of the page in the "Publications" section). This brings up a field where you can record information from that publication. Read the paper and record information relevant to the variant's evaluation, including any data that would contribute to the "variant impact score":guide_impact_score criteria.

Keep in mind that reviews like GeneReviews and OMIM may not be up-to-date and might not reference all the relevant literature. Remember to exhaust all sources to find as much evidence for a variant as possible!

h2. Things to edit

There are many fields in a GET-evidence variant summary&mdash;of course, the order you'll want to fill these in may be different from the order they appear on the page. 

This is the order they appear on the page:

* *Short summary*: This field should be filled in with a brief description of the variant&mdash;the phenotype it is supposed to cause. Other complications may also be noted here&mdash;possibly causal neighboring variants, conflicting evidence, etc. This summary is what appears in the "data visualization":vis. Click on the edit button on the righthand side to edit this field.

* *Variant evidence*: These are four scores that should be filled in according to the "variant impact score criteria":guide_impact_score. Click on the stars to edit this field. When setting the score you should also add a brief explanation for why that score was chosen&mdash;this goes into the field to the right of the stars.

* *Clinical importance*: These are two scores that should be filled in according o the "variant impact score criteria":guide_impact_score. Click on the stars to edit this field. When setting the score you should also add a brief explanation for why that score was chosen&mdash;this goes into the field to the right of the stars.

* *Impact*: This section contains a description of the impact of the variant as well as automatically generated "qualifiers":guide_qualifiers that describe the evidence and clinical importance. You can edit this by clicking the edit button on the righthand side. You can set the impact to one of the following:
** Pathogenic
** Benign
** Protective
** Pharmacogenetic
** Not reviewed

* <p>*Inheritance pattern*: This section describes inheritance pattern of the phenotype associated with the variant. You can edit this by clicking on the edit button on the righthand side.</p><p>For X-linked variants just choose the approprate category from dominant/recessive (i.e. for an X-linked recessive variant choose "Recessive"). GET-evidence should handle the variant appropriately.</p><p>You can set the inheritance pattern to one of the following:</p>
** Dominant
** Recessive
** Other (modifier, co-dominant, incomplete penetrance)
** Undefined in the literature
** Unknown

* <p>*Summary of published research, and additional commentary*: This section is similar to the short summary, but you can use as much space as you want to explain the research related to this variant. You can edit this by clicking on the edit button on the righthand side. </p><p>If you have analyzed case/control numbers you should note here how they are being counted (alleles, homozygotes, carriers, etc.). (See the case/control section in "variant impact score":guide_impact_score for more description on the different things that can be counted.)</p>
** Case/control entries: A field for entering case control data is automatically created for variants within genes that are present in GeneTests. The field that appears in this section should only be used for unpublished evidence; published case/control data should be entered in the section for that publication (farther down on the page).

* *Total cases/controls*: This field is automatically generated by summing all the other case/control entries. You cannot edit it.

* *Allele frequency*: This field lists allele frequency data, if available, from HapMap and 1000 Genomes. You cannot edit it.

* *Publications*: This section contains a list of relevant publications, a link to Pubmed, a section for summarizing relevant information from the publication, and (for some variants) case/control fields that can be filled in.
** *Adding publications*: Publications can be added by typing the PMID (PubMed ID) into the box at the bottom of this section and clicking "Add". The author list, title, and other reference information will automatically be added as a new publication entry on the page.
** *Editing publication summaries*: You can edit or delete a publication summary by clicking the buttons on the righthand side. *Do not copy abstracts*&mdash;this is arguably plagiarism and a copyright violation, and it does not summarize the information in a way that is relevant to GET-Evidence's variant evaluation. Explain what information the paper provides that contributes to variant evaluation, including relevant data like genotype frequencies or LOD scores. You may quote from the papers as long as those sentences are placed within quotation marks.

* *Genomes*: This section lists individuals that have been entered into GET-Evidence that are reported to have this variant. Although you cannot add new genomes here, you can edit these sections to add notes regarding follow-up information for an individual&mdash;whether a variant has been confirmed to be present, whether a phenotype has been confirmed to be present, etc.

* *Other external references*: This section contains links to external sources that may be useful for evaluation of the variant. You cannot edit it. Sources may include:
** dbSNP
** GeneTests
** OMIM
** Web search results

* *Other <i>in silico</i> analysis*: This section contains <i>in silico</i> evidence generated by GET-evidence. Currently this only includes:
** NBLOSUM100 score

EOF
);

go();

?>
