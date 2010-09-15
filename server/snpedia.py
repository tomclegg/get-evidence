#!/usr/bin/python
# Filename: snpedia.py

# Output tab-separated allelic variant information for each entry in SNPedia
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import re, time, urllib
from xml.etree.ElementTree import parse

categories = ["Is_a_snp", "Is_a_genoset"]
category_members_url = "http://snpedia.com/api.php?format=xml&action=query&list=categorymembers&cmtitle=Category:%s&cmlimit=500&cmcontinue=%s"
content_url = "http://snpedia.com/api.php?format=xml&action=query&prop=revisions&titles=%s&rvprop=content"

association_re = re.compile(r"(associated|association) with \[\[([^\]]+)\]\]")
pmid_re = re.compile(r"\[PMID (\d+)\]")
rsnum_re = re.compile(r"{{ ?[Rr]snum ?[\r\n]*([\S\s]*)[\r\n]*}}")

rsid_re = re.compile(r"\| ?rsid ?= ?(\d+)")
chromosome_re = re.compile(r"\| ?[Cc]hromosome ?= ?(.*)")
position_re = re.compile(r"\| ?position ?= ?(\d+)")

genotypes_re = [
    re.compile(r"\| ?geno1 ?= ?\(([ACGT]+);([ACGT]+)\)"),
    re.compile(r"\| ?geno2 ?= ?\(([ACGT]+);([ACGT]+)\)"),
    re.compile(r"\| ?geno3 ?= ?\(([ACGT]+);([ACGT]+)\)")
]
effects_re = [
    re.compile(r"\| ?effect1 ?= ?(.*)"),
    re.compile(r"\| ?effect2 ?= ?(.*)"),
    re.compile(r"\| ?effect3 ?= ?(.*)")
]

neutral_effects = [
    "average",
    "average risk",
    "common",
    "common form",
    "common/normal",
    "normal",
    "normal/common",
    "normal form",
    "normal risk"
]

def title_and_content(titles):
    url = content_url % titles
    api = parse(urllib.urlopen(url)).getroot()
    for element in api.findall("query/pages/page"):
        title = element.get("title")
        content = element.findtext("revisions/rev")
        yield (title, content)
    
def title_list(category):
    continue_title = "|"
    while continue_title:
        url = category_members_url % (category, continue_title)
        api = parse(urllib.urlopen(url)).getroot()
        for element in api.findall("query/categorymembers/cm"):
            yield element.get("title")
        element = api.find("query-continue/categorymembers")
        if element is not None:
            continue_title = element.get("cmcontinue")
        else:
            continue_title = None

def process_variant_content(content):
    # move on if info in structured tabular format doesn't exist
    try:
        rsnum = re.search(rsnum_re, content).group(1)
    except AttributeError:
        return
    
    # try to parse out PubMed sources
    try:
        pmids = ["pmid:" + p for p in re.findall(pmid_re, content)]
    except AttributeError:
        pmids = []
    
    # try to parse out the effect, useful when the tabular info
    # simply provides "increased/decreased risk"
    try:
        association = re.search(association_re, content).group(2)
    except AttributeError:
        association = None
    
    # move on if we don't have an rs number (can be changed later
    # to behave more intelligently)
    try:
        rsid = "rs" + re.search(rsid_re, rsnum).group(1)
    except AttributeError:
        return
    
    # parse info out of the rsnum structured tabular format
    try:
        chromosome = re.search(chromosome_re, rsnum).group(1)
    except AttributeError:
        chromosome = None
    try:
        position = re.search(position_re, rsnum).group(1)
    except AttributeError:
        position = None
    
    genotype_effects = []
    for i, e_re in enumerate(effects_re):
        # parse effect text, but move on if it's blank or apparently neutral
        try:
            effect = re.search(e_re, rsnum).group(1)
        except:
            continue
        if effect == "" or effect in neutral_effects:
            continue
        # try to provide more descriptive text if it seems lacking
        if effect.endswith("risk") or effect.endswith("x") and association is not None:
            effect += " (%s)" % association
        # parse genotype text, but move on if it's blank
        g_match = re.search(genotypes_re[i], rsnum)
        if g_match is None:
            continue
        genotype = (g_match.group(1), g_match.group(2))
        # append to list
        genotype_effects.append((genotype, effect))
    
    # output
    for g, e in genotype_effects:
        print "%s\t%s\t%s\t%s\t%s\t%s" % (
            chromosome,
            position,
            rsid,
            ";".join(g),
            e,
            ",".join(pmids)
        )

def main():
    temp_title_list = []
    
    for t in title_list(categories[0]):
        temp_title_list.append(t)
        # we query every 10 articles
        if len(temp_title_list) == 10:
            for t, c in title_and_content("|".join(temp_title_list)):
                process_variant_content(c)
            temp_title_list = []
            # then rest for 2 seconds so as not to overload the server
            time.sleep(2)
    
    # now query for the rest of them
    if len(temp_title_list) > 0:
        for t, c in title_and_content("|".join(temp_title_list)):
            process_variant_content(c)

if __name__ == "__main__":
    main()