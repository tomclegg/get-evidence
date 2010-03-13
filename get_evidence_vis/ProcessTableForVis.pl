#!/usr/bin/perl

use strict;
use warnings;

my $data_file = $ARGV[0];
my $snp_freq_file = $ARGV[1];

my $MIN_SCORE = 1;

my $snp_freq_ref = readSNPfreq($snp_freq_file);
processData($data_file, $snp_freq_ref);

sub readSNPfreq {
  my $file = shift;
  open(SNPDATA, $file);
  my %freq;
  while (<SNPDATA>) {
    chomp;
    my @data=split(/\t/,$_,-1);
    my @snp_data = split(/;/,$data[8]);
    my $n_oth = -1;
    my $n_ref = -1;
    my $aa_data_line = "";
    for (my $i = 0; $i <= $#snp_data; $i++) {
      if ($snp_data[$i] =~ /oth_count ([0-9]*)$/) {
        $n_oth = $1;
      } elsif ($snp_data[$i] =~ /ref_count ([0-9]*)$/) {
        $n_ref = $1;
      } elsif ($snp_data[$i] =~ /amino_acid (.*)$/) {
        $aa_data_line = $1;
      } else {
        #print "No match for $snp_data[$i]\n";
      }
    }
    unless ($n_oth == -1 or $n_ref == -1 or $aa_data_line eq "") {
      my @aa_data = split("/", $aa_data_line);
      foreach my $var (@aa_data) {
        my @var_data = split(' ',$var);
        if ($var_data[1] =~ /([A-Z\*])([0-9]*)([A-Z\*])/) {
          my ($aa1, $pos, $aa2) = ($1, $2, $3);
          my $ID = $var_data[0] . "-" . convert($aa1) . $pos . convert($aa2);
          if ($n_ref == 0) {
            $n_ref += 0.5;
          }
          if ($n_oth == 0) {
            $n_oth += 0.5;
          }
          $freq{$ID} = $n_oth / $n_ref;
        }
      }
    }
  }
  return \%freq;
}
    

sub processData {
  my $file = shift;
  my $snp_freq_ref = shift;
  my %freq = %{$snp_freq_ref};

  open DATA, '<', "$file" or die "Can't open $data_file for read!";
  my $header_line = <DATA>;
  chomp($header_line);
  my @header = split(/\t/,$header_line,-1);
  my %columns = %{getColumns(\@header)};
  printData(\@header, \%columns);
  while (<DATA>) {
    # Split on tabs
    chomp;
    my @data = split(/\t/,$_,-1);
    if ($#data < $#header) {
      next;
    }
    # Add stars
    @data = addStars(\@data, \%columns);
    @data = adjustFreq(\@data, \%columns);
    #$data[$columns{"certainty"}] = substr($data[$columns{"certainty"}], 0, 1);
    # Print if freq > 0 and freq < 1 and total quality score > 0
    my $is_freq_good = ( ($data[$columns{"overall_frequency"}] ne "") and
                      ($data[$columns{"overall_frequency"}] > 0) and
                      ($data[$columns{"overall_frequency"}] < 1) );
    if ($is_freq_good) {
      my $total_quality_score = getTotalQualityScore(\@data,\%columns);
      if ($total_quality_score >= $MIN_SCORE) {
        printData(\@data, \%columns);
      }
    } else {
      my $ID = $data[$columns{"gene"}] . "-" . $data[$columns{"aa_change"}];
      if (exists $freq{$ID}) {
        $data[$columns{"overall_frequency"}] = $freq{$ID};
        my $total_quality_score = getTotalQualityScore(\@data,\%columns);
        if ($total_quality_score >= $MIN_SCORE) {
          printData(\@data, \%columns);
        }
      }
    }
  }
}
close(DATA);

sub getTotalQualityScore {
  my $data_ref = shift;
  my $columns_ref = shift;
  my @data = @{$data_ref};
  my %columns = %{$columns_ref};
  my $total = 0;
  if ($data[$columns{"qualityscore_in_silico"}] ne "-" and $data[$columns{"qualityscore_severity"}] ne "!") {
    $total += $data[$columns{"qualityscore_in_silico"}];
  }
  if ($data[$columns{"qualityscore_in_vitro"}] ne "-" and $data[$columns{"qualityscore_in_vitro"}] ne "!") {
    $total += $data[$columns{"qualityscore_in_vitro"}];
  }
  if ($data[$columns{"qualityscore_case_control"}] ne "-" and $data[$columns{"qualityscore_case_control"}] ne "!") {
    $total += $data[$columns{"qualityscore_case_control"}];
  }
  if ($data[$columns{"qualityscore_familial"}] ne "-" and $data[$columns{"qualityscore_familial"}] ne "!") {
    $total += $data[$columns{"qualityscore_familial"}];
  }
  if ($data[$columns{"qualityscore_severity"}] ne "-" and $data[$columns{"qualityscore_severity"}] ne "" and $data[$columns{"qualityscore_severity"}] ne "!") {
    $total += $data[$columns{"qualityscore_severity"}];
  }
  if ($data[$columns{"qualityscore_treatability"}] ne "-" and $data[$columns{"qualityscore_severity"}] ne "" and $data[$columns{"qualityscore_severity"}] ne "!") {
    $total += $data[$columns{"qualityscore_treatability"}];
  }
  
  return $total;
}

sub printData {
  my $data_ref = shift;
  my $columns_ref = shift;
  my @data = @{$data_ref};
  my %columns = %{$columns_ref};
  my @printed_header = ("gene", "aa_change", "impact", "inheritance", 
                          "overall_frequency", "in_omim", "n_genomes", 
                          "qualityscore_in_silico", "qualityscore_in_vitro", 
                          "qualityscore_case_control", "qualityscore_familial", 
                          "qualityscore_severity", "qualityscore_treatability", 
                          "max_or_case_pos", "max_or_case_neg", 
                          "max_or_control_pos", "max_or_control_neg", 
                          "max_or_or", "max_or_disease_name", "variant_evidence", 
                          "clinical_importance", "summary_short");
  print "$data[$columns{$printed_header[0]}]";
  for (my $i = 1; $i <= $#printed_header; $i++) {
    print "\t$data[$columns{$printed_header[$i]}]";
  }
  print "\n";
}

sub adjustFreq {
  my ($data_ref, $columns_ref) = @_;
  my @data = @{$data_ref};
  my %columns = %{$columns_ref};

  my $freq = $data[$columns{"overall_frequency"}];
  my $pos = $data[$columns{"max_or_control_pos"}];
  my $neg = $data[$columns{"max_or_control_neg"}];

  if ($freq eq "") {
    if ($pos ne "" and $neg ne "" and ($pos > 0 or $neg > 0)) {
      if ($pos == 0) {
        $pos += 0.5;
      }
      if ($neg == 0) {
        $neg += 0.5;
      }
      $data[$columns{"overall_frequency"}] = $pos / ($pos + $neg);
    }
  } else {
    my $freq_n = $data[$columns{"overall_frequency_n"}];
    my $freq_d = $data[$columns{"overall_frequency_d"}];
    if ($pos ne "" and $neg ne "" and ($pos > 0 or $neg > 0)) {
      $freq_n += $pos;
      $freq_d += $pos + $neg;
    }
    if ($freq_n == 0) {
      $freq_n += 0.5;
    }
    if ($freq_n == $freq_d) {
      $freq_d += 0.5;
    }
    $data[$columns{"overall_frequency"}] = $freq_n / $freq_d;
  }

  return(@data);
}
      
    
      

sub addStars {
  my ($data_ref, $columns_ref) = @_;
  my @data = @{$data_ref};
  my %columns = %{$columns_ref};

  # assign in_omim points
  my $is_not_curated = ( $data[$columns{"qualityscore_case_control"}] eq "-" 
                      and $data[$columns{"qualityscore_familial"}] eq "-" );
  if ($is_not_curated) {
    if ($data[$columns{"in_omim"}] eq "Y") {
      $data[$columns{"qualityscore_case_control"}] = 1;
      $data[$columns{"qualityscore_familial"}] = 1;
    }
  }
  
  # assign genetests & nblosum points
  my $in_silico_min = 0;
  if ($data[$columns{"gene_in_genetests"}] eq "Y") {
    $in_silico_min++;
  }
  if ($data[$columns{"nblosum100>2"}] eq "Y") {
    $in_silico_min++;
  }
  my $is_unassigned_or_less_than = ( $in_silico_min > 0 and
                                  ( $data[$columns{"qualityscore_in_silico"}] eq "-" 
                                    or $data[$columns{"qualityscore_in_silico"}] < $in_silico_min ) );
  if ($is_unassigned_or_less_than) {
    $data[$columns{"qualityscore_in_silico"}] = $in_silico_min;
  }
  return(@data);
}

sub getColumns {
  my $header_ref = shift;
  my @header = @{$header_ref};
  my %columns;
  for (my $i = 0; $i <= $#header; $i++) {
    $columns{ ${$header_ref}[$i] }  = $i;
  }
  return \%columns;
}


sub convert {
 my $letter = shift;
 my %conversion = (
  A => 'Ala',
  R => 'Arg',
  N => 'Asn',
  D => 'Asp',
  B => 'Asx',
  C => 'Cys',
  E => 'Glu',
  Q => 'Gln',
  Z => 'Glx',
  G => 'Gly',
  H => 'His',
  I => 'Ile',
  L => 'Leu',
  K => 'Lys',
  M => 'Met',
  F => 'Phe',
  P => 'Pro',
  S => 'Ser',
  T => 'Thr',
  W => 'Try',
  Y => 'Tyr',
  V => 'Val',
  "*" => 'Stop',
 );
 if (exists $conversion{$letter}) {
  return($conversion{$letter});
 } else {
  print "ERROR unable to map $letter\n";
 }
}

