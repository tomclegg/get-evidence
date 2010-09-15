#!/usr/bin/perl -w
# A slightly modified version of Ian Holmes' GFFTools script <gffsort.pl>

use strict;
use File::Basename;

my $prog = basename($0);
my $usage .= "$prog - sort one or many GFF files, using a specified sort method; ";
$usage .= "default sort is by name, start, and end fields in ascending order\n";
$usage .= "usage: $prog [options] <file1> <file2> ...\n";
$usage .= "Options:\n";
$usage .= "-l, --length        sort by feature length, in ascending order\n";
$usage .= "-m, --minscore      sort by minimum score, in ascending order\n";
$usage .= "-M, --maxscore      sort by maximum score, in descending order\n";
$usage .= "-x, --expr          sort by user-defined expression; use single quotes\n"; 
					
my $sortMethod = "byNameStartEnd";
my $sortExpr = undef;

while (@ARGV) 
{
  last unless $ARGV[0] =~ /^-./; # Loop thru all the command line options.
    my $opt = shift;
    if ($opt eq "-M") 
    { 
        $sortMethod = "byMaxScore"; 
    }
    elsif ($opt eq "--maxscore") 
    { 
        $sortMethod = "byMaxScore"; 
    }
    elsif ($opt eq "-m") 
    { 
        $sortMethod = "byMinScore"; 
    }
    elsif ($opt eq "--minscore") 
    { 
        $sortMethod = "byMinScore"; 
    }
    elsif ($opt eq "-l") 
    { 
        $sortMethod = "byFeatureLength"; 
    }
    elsif ($opt eq "--length") 
    { 
        $sortMethod = "byFeatureLength"; 
    }
    elsif ($opt eq "-x") 
    { 
        $sortMethod = "byExpr";
        $sortExpr = shift || die "$usage\nMissing sort expression.\n";
    }
    elsif ($opt eq "--expr") 
    { 
        $sortMethod = "byExpr";
        $sortExpr = shift || die "$usage\nMissing sort expression.\n";
    }
    else 
    { 
        die "$usage\nUnknown option: $opt\n" 
    }
}
 
if (@ARGV == 0)
{
    die $usage;
}

my (@line, @name, @type, @start, @end, @score);

while (<>) { # Read lines from file(s) specified on command line. Store in $_.
    s/#.*//; # Remove comments from $_.
    next unless /\S/;   # \S matches non-whitespace.  If not found in $_, skip to next line.
    my @f = split /\t/; # Split $_ at tabs separating fields.
    push @line, $_;     # Complete line
    push @name, $f[0];  # Name field
    push @type, $f[2];  # Type field
    push @start, $f[3]; # Start field
    push @end, $f[4];   # End field
    push @score, $f[5]; # Score field
}

foreach my $i (sort $sortMethod 0..$#line) 
{ 
    print $line[$i] 
}

# Sort by name, start, then end, from low to high.
sub byNameStartEnd
{
    $name[$a] cmp $name[$b] or $start[$a] <=> $start[$b] or $end[$a] <=> $end[$b]; 
}

sub byMinScore 
{
    $score[$a] <=> $score[$b] or $name[$a] cmp $name[$b] or $start[$a] <=> $start[$b] 
        or $end[$a] <=> $end[$b]; 
}

sub byMaxScore 
{
    $score[$b] <=> $score[$a] or $name[$a] cmp $name[$b] or $start[$a] <=> $start[$b] 
        or $end[$a] <=> $end[$b]; 
}

sub byFeatureLength
{
    $end[$a]-$start[$a] <=> $end[$b]-$start[$b] or $name[$a] cmp $name[$b] or $start[$a] <=> $start[$b] 
        or $end[$a] <=> $end[$b]; 
}

sub byExpr
{
    eval $sortExpr;
}
