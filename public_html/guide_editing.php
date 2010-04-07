<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: About";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Guide to editing

GET-evidence variant evaluations can be edited by anyone&mdash;our only requirement is that you log in using OpenID to edit using your real name and provide your email address. Editor contributions are critical to GET-evidence's success, we encourage all users of GET-evidence to also edit. Things you can do include: evaluating new variants, correcting errors, and updating evaluations.

h3. Related pages

* "Variant impact score":guide_impact_score: Explains how variant impact score (which is used to determine qualifiers) is generated.

h2. Logging in with OpenID

OpenID is a method for authenticating individuals. There are many different providers of OpenID authentication, including Google and Yahoo. *Anybody with a Google or Yahoo account can log in* to GET-evidence using OpenID. There are many other OpenID providers, you can also log in using the appropriate URL.

h3. Google/Yahoo login

Click on the "Google login" or "Yahoo login" button on the right side of the page. This will bring you to a Google or Yahoo web page. If you aren't already logged in to your account this is a login page (if you already logged in it will skip to the next page). Go ahead and sign in.

Google or Yahoo will then ask if you want to release your email address to GET-Evidence. Click "Allow" and you will be logged in and able to edit.

h2. Where to start?

h3. Which variants need editing?

Two good places to start are the "visualization":vis page and the "reports":report page. 

The "visualization":vis is interactive: you can find variants that appear to lack evaluations in this graph (they should lack summaries) and then open up and edit that variant's page. 

On the "reports":report page you can find various lists of GET-evidence entries. "Summaries needed" lists variants that have been marked pathogenic but lack summaries. "Variants with genome evidence and web search results" lists variants that have been found in one of the published genomes and have web search results (indicating that someone somewhere said something about that variant). These are good starting points for looking for variants that need more evaluation.

h3. Where to find information?

Once editing a variant, you'll need to start reading publications that reported observations relevant to that variant. The "other external references" section at the bottom of a variant page contains useful starting points: OMIM, GeneTests, and web search results. Once you find publications, you should add entries for them to GET-evidence and record the relevant information.

* *Web search*: You should always search for a variant using a search engine (internet search or journal search), if you're lucky you'll find a recent review article that summarizes research relevant to the variant. You can then follow those citations.
* *OMIM*: If available, OMIM is useful for providing a summary of the variant and a few relevant publications. Keep in mind that the OMIM entry may not be up-to-date and might not cite all relevant literature. You can find a link to the relevant OMIM entry in the "other external references" section.
* *GeneTests*: If a gene is in GeneTests, you can try clicking on the GeneTests link in the "other external references" section. Sometimes you will find an associated GeneReviews link that summarizes the disease and treatment. This is especially useful for evaluating clinical importance categories (disease severity and disease treatability).
* *Adding publications*: Once you find relevant publications, add them to the GET-Evidence page using their PMID (at the bottom of the page in the "Publications" section). This brings up a field where you can record information from that publication. Read the paper and record information relevant to the variant's evaluation, including any data that would contribute to the "variant impact score":guide_impact_score criteria.

h2. Things to edit

There are many fields in a GET-evidence variant summary&mdash;the order you'll want to fill these in is probably different from the order they appear on the page. 

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
