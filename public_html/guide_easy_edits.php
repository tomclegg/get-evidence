<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Easy edits";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Easy edits

GET-Evidence can be intimidating, so we've put together a list of easy edits you can do for variant pages. These small contributions are still quite valuable as they focus the attention of experienced editors, helping prioritize which variants could benefit from more review.

h2. Add PMIDs for articles to variant pages

At the bottom of the "publications" section is a small textbox to add in PubMed IDs of relevant literature. If you can find any papers which mention this variant, you use this to add them to the variant page. Later editors will see that the variant has some relevant publications they can review.

Places to look:
* OMIM: If there is an OMIM link on a variant page, it may contain references to papers mentioning the variant. Go to that OMIM page and search for this variant (it will probably be at the bottom of the page).
* Web search: Go to Google or any other web search tool and search on the variant -- you can use either the variant's gene name and predicted amino acid change (e.g. "SERPINA1 E366K") or dbSNP ID (e.g. "rs28929474").

Adding publications is a great way to help bring attention to variants! Once a variant has publications, another editor may see this and decide to follow up on that article, to give it an in-depth review.

h2. Vote on webpage hits

"Other external references" contains a section for "Web search results" -- these may be papers, meeting abstracts, or unpublished online databases that specifically mention the variant. Unfortunately... they might also be car parts, an electronics catalog, or strangely formatted garbage from pdf documents. We need your help to figure out which of these actually contain a mention of the variant that would be useful for variant evaluation.

If you can clearly tell the webpage match is irrelevant, you can vote against the hit. If it looks like it might be relevant, try to confirm it by clicking on the link. You might want to try follow-up web searches if the original link is unavailable. There may be some borderline cases (for example: a large list of variations that has no associated additional data) -- use your common sense judgement to guess whether a web page hit might be useful for interpreting the variant's potential effect.

Variants with unevaluated or positive webpage hits have 1 additional autoscore point. If all the webpages are voted negative, the variant autoscore will decrease by 1, lowering prioritization of the variant because it turns out, once it was checked, that there wasn't any useful webpage data. Weeding these variants out will help bring attention to other variants which actually _do_ have relevant literature.

EOF
);

go();

?>
