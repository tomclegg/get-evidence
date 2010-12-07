#!/usr/bin/perl

use strict;
use warnings;

my $knowngene_file = $ARGV[0];
my $canonical_file = $ARGV[1];
my $kgxref_file = $ARGV[2];
my $refflat_file = $ARGV[3];
my $hgnc_gene_names_file = $ARGV[4];

my %refseq_to_gene_sym = ();
my %mRNA_to_gene_sym = ();
my %hgnc_columns = ();
my %canon_sym = ();  # storing as keys for quick search
my %canon_trans = (); # storing as keys for quick seach

# Get canonical transcripts
open(KG_CANON, $canonical_file);
while (<KG_CANON>) {
    chomp;
    my @data = split("\t", $_, -1);
    $canon_trans{$data[4]} = undef;
}
close(KG_CANON);

# Get canonical gene symbols
open(HGNC, $hgnc_gene_names_file) or die;
chomp(my @header = split("\t",<HGNC>));
for (my $i = 0; $i <= $#header; $i++) {
    $hgnc_columns{$header[$i]} = $i;
}
while (<HGNC>) {
    if (/withdrawn/) {
        next;
    }
    chomp;
    my @data = split("\t",$_,-1);
    my $refseq = $data[$hgnc_columns{"RefSeq IDs"}];
    my $mRNA = $data[$hgnc_columns{"Accession Numbers"}];
    my $symbol = $data[$hgnc_columns{"Approved Symbol"}];
    my $chrom = $data[$hgnc_columns{"Chromosome"}];
    if (length($refseq) > 0 and length($symbol) > 0) {
        if ($refseq =~ /^(.*)\..*/) {
            $refseq = $1;
        }
        $refseq_to_gene_sym{$refseq} = $symbol;
    }
    if (length($mRNA) > 0 and length($symbol) > 0) {
        if ($mRNA =~ /^(.*)\..*/) {
            $mRNA = $1;
        }
        $mRNA_to_gene_sym{$mRNA} = $symbol;
    }
    if (length($symbol) > 0 and $chrom =~ /^([12]?[0-9XY])[pq]?/ ) {
        $canon_sym{$symbol} = "chr" . $1;  # store chr for later sanity check
    } elsif (length($symbol) > 0 and $chrom = "mitochondria") {
        $canon_sym{$symbol} = "chrM";
    }
}
close(HGNC);

# Use refFlat to connect refSeq to gene symbols
open(REFFLAT, $refflat_file);
while (<REFFLAT>) {
    chomp;
    my @data = split("\t", $_, -1);
    if ($#data < 1) {
        next;
    }
    unless (exists $mRNA_to_gene_sym{$data[1]} or exists $refseq_to_gene_sym{$data[1]}) {
        if (exists $canon_sym{$data[0]}) {
            if ($canon_sym{$data[0]} eq $data[2]) {     # sanity check
                $refseq_to_gene_sym{$data[1]} = $data[0];
            }
        } else {
            #print "$data[0] $data[1]\n";
        }
    }
}
close(REFFLAT);

# Use kgXref to connect kg IDs to gene symbols
# - first try to use mRNA as given by hgnc's database
# - second try to use refSeq, if refSeq was connected to gene symbol earlier
# - third try to use the gene symbol in kgXref itself, if it exists in hgnc's list and chr match
# if all these fail, use the mRNA as "name" (not the symbol given)
my %ucsc_to_name = ();
my %ucsc_to_name_backup1 = ();
my %ucsc_to_name_backup2 = ();
open(KGXREF, $kgxref_file);
while (<KGXREF>) {
    chomp;
    my @data=split("\t",$_,-1);
    my ($kgID, $mRNA, $spID, $spDisplay, $genesym, $refseq, $protAcc, $desc) = @data;
    unless ( length($kgID) > 0 ) {
        next;
    }
    if (length($mRNA) > 0 and exists $mRNA_to_gene_sym{$mRNA}) {
        $ucsc_to_name{$kgID} = $mRNA_to_gene_sym{$mRNA};    # first
    } elsif (length($refseq) > 0 and exists $refseq_to_gene_sym{$refseq}) {
        $ucsc_to_name{$kgID} = $refseq_to_gene_sym{$refseq};    # second
    } else {
        # third
        if (length($genesym) > 0 and $genesym =~ /^[-A-Za-z0-9]*$/ and exists $canon_sym{$genesym}) {
            $ucsc_to_name_backup1{$kgID} = $genesym;
        }
        # fourth
        if (length($mRNA) > 0) {
            $ucsc_to_name_backup2{$kgID} = $mRNA
        } 
    }
}
close(KGXREF);

open(KG, $knowngene_file);
while (<KG>) {
    chomp;
    my @data=split("\t",$_,-1);
    unless (exists $canon_trans{$data[0]}) {
        next;   # skip if not a canonical transcript
    }
    if (exists $ucsc_to_name{$data[0]}) {
        print $ucsc_to_name{$data[0]} . "\t" . $_ . "\n";
    } elsif (exists $ucsc_to_name_backup1{$data[0]} and $data[1] eq $canon_sym{$ucsc_to_name_backup1{$data[0]}}) {
        print $ucsc_to_name_backup1{$data[0]} . "\t" . $_ . "\n";
    } elsif (exists $ucsc_to_name_backup2{$data[0]}) {
        print $ucsc_to_name_backup2{$data[0]} . "\t" . $_ . "\n";
    } else {
    }
}

