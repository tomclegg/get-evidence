#!/usr/bin/perl

=head1 SYNOPSIS

Cross-reference PharmGKB database against dbSNP, flip alleles to +
strand where appropriate, and check consistency with dbSNP observed
alleles.

=head1 USAGE

To process all the SNPs regardless of drug or not, and like:

 ./pharmgkb_stage1.pl Variant_annotation_filtered_allele.txt SNP130.txt.gz \
      > Variant_annotation_filtered_allele_flipped.txt \
      2> Variant_annotation_filtered_allele_flipped_errs.txt

To produce output for entries containing drug annotations only:

 ./pharmgkb_stage1.pl Variant_annotation_filtered_allele.txt SNP130.txt.gz Y \
      > Variant_annotation_filtered_allele_flipped_drugs.txt \
      2> Variant_annotation_filtered_allele_flipped_drugs_errs.txt

=cut

my $drugflag = 0;

my ($pharmfile, $rsfile, $drugflag) = @ARGV;

print STDERR "Command line: $0 $pharmfile $rsfile $drugflag\n";

my $pharmhash = readpharm($pharmfile);

print "\nNucleotide Records\n";

my $rshash = readrsfile($rsfile, $pharmhash);

sub readrsfile {
	my ($rsfile, $pharmhash) = @_;
	my %rshash = ();

	my $fspec = "< $rsfile";
	$fspec = "zcat $rsfile |" if ($rsfile =~ /\.gz$/);

	open(RS, $fspec) || die("Can't open $rsfile\n");

	my $totalrs = 0;
	my $badrs = 0;
	my $notskipped = 0;
	my $goodpharm = 0;
	my $badpharm = 0;

	while (<RS>) {
		$_ =~ s/[\r\n]//;

		my $rsrec = $_;

		$totalrs++;

		my @f = split /\t/, $_;
		$f[2]++;

		my $key = "$f[1]\t$f[2]\t$f[4]";

		if (($f[3] - $f[2]) != 0) {
			next;
			}

		my $pat = '';

		$f[9] .= '/';

		while ($f[9] =~ s/([ACGT]+)\///) {
			next if (length($1) > 1);
			$pat .= "$1";
			}

		next if ($pat eq '');

		$notskipped++;

		my $pluspat = $pat;

		if ($f[6] eq '-') {
			$pluspat =~ tr/ACGT/TGCA/;
			}

		elsif ($f[6] ne '+') {
			print STDERR "Error parsing: $_\n";
			}

		$pluspat = "\[$pluspat\]";

		my $minuspat = $pluspat;
		$minuspat =~ tr/ACGT/TGCA/;

		if ($f[7] !~ /$pluspat/) {
#			print STDERR "Disagreement in ref and SNP : $_\n";
			$badrs++;
			next;
			}

		if (exists $$pharmhash{$key}) {
			my @cols = split /\t/, $$pharmhash{$key};

			my $allele = shift @cols;

			if ($$pharmhash{$key} =~ /^$pluspat\s+/) {
				print "$allele\t$pluspat\t", join("\t", @cols), "\n";
				$goodpharm++;
				}

			elsif ($$pharmhash{$key} =~ /^$minuspat\s+/) {
				$allele =~ tr/ACGT/TGCA/;

				if ($allele !~ /^$pluspat/) {
					die("Weird problem with $$pharmhash{$key} and $pluspat\n");
					}

				print "$allele (flipped)\t$pluspat\t", join("\t", @cols), "\n";
				$flippedpharm++;
				}

			else	{
				print STDERR "EXPECTED: $pluspat\t$$pharmhash{$key}\n\t$rsrec\n";
				$badpharm++;
				}

			delete $$pharmhash{$key};
			}
		}

	close(RS);

#	print STDERR "Read in " . scalar(keys %rshash) . " rs numbers\n";

	print STDERR "GOOD = $goodpharm, FLIPPED = $flippedpharm, BAD = $badpharm, TOTALRS = $totalrs, NOTSKIPPEDRS = $notskipped, BADRS = $badrs\n"; 

	print STDERR "\nLEFTOVERS:\n";
	foreach my $p (sort keys %$pharmhash) {
		print STDERR "$$pharmhash{$p}\n";
		}

#	\%rshash;
}

sub readpharm {
	my ($pharmfile) = @_;
	my %pharmhash = ();
	my $ambiguous = 0;

	my $unparsed = '';

	my $fspec = "< $pharmfile";
	$fspec = "zcat $pharmfile |" if ($pharmfile =~ /\.gz$/);

	open(PH, $fspec) || die("Can't open $pharmfile\n");

	my $header = <PH>;

	while (<PH>) {
		$_ =~ s/[\r\n]//;
		my $l = $_;

		my @f = split /\t/, $l;

#		print STDERR "DRUG: $f[5]\n";

		next if ($drugflag ne '' && $f[5] =~ /^\s*$/);

#for(my $i = 0; $i < @f ; $i++) {
#	print "$i\t$f[$i]\n";
#	}

		my ($chr, $pos, $rsid, $allele) = ();

		if ($f[0] =~ /^(chr.*?)\:(\d+)\s/) {
			$chr = $1;
			$pos = $2;
			}
		else	{
			print STDERR "Can't parse chr and loc:\n$_\n";
			next;
			}

		if ($f[0] =~ /\((rs\d+)\)/) {
			$rsid = $1;
			}
		else	{
			print STDERR "Can't parse rsid:\n$_\n";
			next;
			}

		if ($l =~ /Risk\s+Allele:\s+(rs\d+)\-([ACGT])/i) {
			my $rs = $1;
			$allele = $2;

			if ($rs ne $rsid) {
				print STDERR "RSID $rsid <> $rs - skipping\n";
				next;
				}
			}

		elsif($l =~ /Risk Allele:\s+([ACGT])/i) {
			$allele = $1;
			}

		elsif ($l =~ /risk allele=([ACGT])/i) {
			$allele = $1;
			}

		elsif ($l =~ /\s(rs\d+)\s*=\s*([ACGT])\s+/) {
			my $rs = $1;
			$allele = $2;

			if ($rs ne $rsid) {
				print STDERR "RSID $rsid <> $rs - skipping\n";
				next;
				}			
			}

		elsif ($l =~ /[\s\(]([ACGT])[\s\-]+allele/i) {
			$allele = $1;
			}

		elsif ($l =~ /[\s\(]allele[\s\-]+([ACGT])[\s\)]/i) {
			$allele = $1;
			}

		elsif ($l =~ /allele\s*\(([ACGT])\)/) {
			$allele = $1;
			}

		elsif ($l =~ /Risk.{0,20}:.{0,20}(rs\d+)\-([ACGT])\)/i) {
			my $rs = $1;
			$allele = $2;

			if ($rs ne $rsid) {
				print STDERR "RSID $rsid <> $rs - skipping\n";
				next;
				}			
			}

#		print "$chr\t$pos\t$rsid\t$allele\n";

		$allele =~ s/\s+//g;
		$rsid =~ s/\s+//g;
		$chr =~ s/\s+//g;
		$pos =~ s/\s+//g;

		if ($rsid ne '' && $allele ne '') {
#			print "$allele\t$_\n";
			$pharmhash{"$chr\t$pos\t$rsid"} = "$allele\t$l";
			}

		elsif ($l =~ /rs\d+\-\?/) {
			$ambiguous++;
			}

		elsif ($l =~ /(ala|arg|asn|asp|cys|glu|gln|gly|his|ile|leu|lys|met|phe|pro|ser|thr|try|tyr|val)(\d+)(ala|arg|asn|asp|cys|glu|gln|gly|his|ile|leu|lys|met|phe|pro|ser|thr|try|tyr|val)/i) {
			my $aachange = "$1$2$3";
			$pharmhashaa{"$chr\t$pos\t$rsid"} = "$aachange\t\t$l";
			}

		else	{
			$unparsed .= "$l\n";
			$unparsable++;
			}
		}

	close(PH);

	print STDERR "Filtering for drug related records = $drugflag\n";
	print STDERR "Read in " . scalar(keys %pharmhash) . " pharm SNPs; $ambiguous ambiguous SNPs\n";
	print STDERR "Read in " . scalar(keys %pharmhashaa) . " pharm AA changes\n";
	print STDERR "$unparsable Not parsed as follows:\n\n$unparsed\n";

	print "Amino Acid Records\n";

	foreach my $p (sort keys %pharmhashaa) {
		print "$pharmhashaa{$p}\n";
		}

	\%pharmhash;
}



__END__
Position (RSID) Name(s) Evidence        Annotation      Genes   Drugs   Diseases
chr1:12345678 (rs1234) GENE:Val123Met  Web Resource:http://www.pharmgkb.org/      This variant is responsible for .....    GENE

SNP database

#bin	chrom	chromStart	chromEnd	name	score	strand	refNCBI	refUCSC	observed	molType	class	valid	avHet	avHetSE	func	locType	weight
1	chr1	16775073	16781350	rs72059099	0	+	( 6277bp insertion )	( 6277bp insertion )	(LARGEDELETION)/-	genomic	named	unknown	0	0	unknown	range	1
1	chr1	16775613	16788198	rs71260122	0	-	( 12585bp insertion )	( 12585bp insertion )	ACA/GCG	genomic	mnp	unknown	0	0	unknown	range	2
1	chr1	16776311	16782082	rs72496645	0	-	( 5771bp insertion )	( 5771bp insertion )	-/TCAG	genomic	in-del	unknown	0	0	frameshift	rangeInsertion	1
2	chr1	100658226	100667832	rs71808286	0	+	( 9606bp insertion )	( 9606bp insertion )	(LARGEDELETION)/-	genomic	named	unknown	0	0	frameshift	range	1
14	chr1	45080556	45089100	rs71711725	0	+	( 8544bp insertion )	( 8544bp insertion )	(LARGEDELETION)/-	genomic	named	unknown	0	0	untranslated-5	range	1
26	chr1	149940941	149948891	rs71775793	0	+	( 7950bp insertion )	( 7950bp insertion )	(LARGEDELETION)/-	genomic	named	unknown	0	0	unknown	range	1
30	chr1	177209325	177209347	rs71794609	0	+	TGTGTGTGTGTGTGTGTGTGTG	TGTGTGTGTGTGTGTGTGTGTG	-/TGTGTGTGTGTGTGTGTGTGTG	genomic	deletion	unknown	0	0	unknown	range	1
33	chr1	202372482	202380096	rs72451924	0	+	( 7614bp insertion )	( 7614bp insertion )	(LARGEDELETION)/-	genomic	named	unknown	0	0	frameshift	range	1
35	chr1	220190850	220208380	rs72487224	0	-	( 17530bp insertion )	( 17530bp insertion )	-/TGTTGTCCAA	genomic	in-del	unknown	0	0	near-gene-3	rangeInsertion	1

...

585	chr1	18839	18840	rs60412817	0	-	G	G	C/T	genomic	single	unknown	0	0	unknown	exact	3
585	chr1	18847	18848	rs4118001	0	+	A	A	A/G	genomic	single	unknown	0	0	unknown	exact	3
585	chr1	18850	18851	rs3878858	0	+	C	C	C/T	genomic	single	unknown	0	0	unknown	exact	3
585	chr1	18850	18851	rs62028220	0	-	C	C	A/G	genomic	single	unknown	0	0	unknown	exact	3
585	chr1	18876	18877	rs4118011	0	+	G	G	C/G	genomic	single	unknown	0	0	unknown	exact	3
585	chr1	18935	18936	rs708634	0	-	G	G	C/T	genomic	single	by-cluster	0	0	unknown	exact	3
585	chr1	18935	18936	rs16990161	0	+	G	G	A/G	genomic	single	unknown	0	0	unknown	exact	3
585	chr1	19000	19001	rs62028219	0	-	G	G	C/G	genomic	single	unknown	0	0	unknown	exact	3
585	chr1	19026	19027	rs2748064	0	-	C	C	C/G	genomic	single	unknown	0	0	unknown	exact	3
585	chr1	19027	19028	rs3871716	0	-	C	C	A/G	genomic	single	by-cluster	0	0	unknown	exact	3
