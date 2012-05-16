#!/usr/bin/perl

# Copyright 2012 Clinical Future, Inc.
# Authors: see git-blame(1)

$, = "\t";
$\ = "\n";

while(<>)
{
    chomp;
    @F = split;
    my $variant_name = $F[8];
    if ($variant_name =~ /^None$/i) {
	$variant_name = $F[7];
    }
    if ($variant_name =~ /^none$/i) {
	next;
    }
    print ("unknown", $variant_name, $F[0], $F[1], $F[1], $F[2], $F[4], $F[5]);
}
