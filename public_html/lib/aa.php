<?php

$aa_31 = array ("Ala" => "A",
		"Arg" => "R",
		"Asn" => "N",
		"Asp" => "D",
		"Cys" => "C",
		"Gln" => "Q",
		"Glu" => "E",
		"Gly" => "G",
		"His" => "H",
		"Ile" => "I",
		"Leu" => "L",
		"Lys" => "K",
		"Met" => "M",
		"Phe" => "F",
		"Pro" => "P",
		"Ser" => "S",
		"Thr" => "T",
		"Trp" => "W",
		"Tyr" => "Y",
		"Val" => "V",
		"Stop" => "*");
$aa_13 = array_flip ($aa_31);

function aa_long__ ($in)
{
  global $aa_13;
  if (strlen($in[0]) == 1) return $aa_13[strtoupper($in[0])];
  else return ucfirst(strtolower($in[0]));
}

function aa_long_form ($in)
{
  global $aa_13;
  if (strlen ($in) == 1) return $aa_13[$in];
  return preg_replace_callback ('/[A-Za-z]+/', 'aa_long__', $in);
}

function aa_short__ ($in) {
  global $aa_31;
  if (strlen($in[0]) == 3) return $aa_31[ucfirst(strtolower($in[0]))];
  else return strtoupper($in[0]);
}

function aa_short_form ($in)
{
  global $aa_31;
  return preg_replace_callback ('/[A-Za-z]+/', 'aa_short__', $in);
}

?>
