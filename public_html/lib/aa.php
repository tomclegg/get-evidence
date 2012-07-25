<?php
  ;

// Copyright: see COPYING
// Authors: see git-blame(1)

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
		"Xaa" => "X",
		"Stop" => "X");
$aa_13 = array_flip ($aa_31);
$aa_13["X"] = "Stop";
$aa_13["*"] = "Stop";

function aa_long__ ($in)
{
  global $aa_13;
  if ($in[0] == "Shift" || $in[0] == "Frameshift" || $in[0] == "Frame" || $in[0] == "fs") return "Shift";
  if (strtolower($in[0]) == "del") return "Del";
  if (strlen($in[0]) == 1) return $aa_13[strtoupper($in[0])];
  else return ucfirst(strtolower($in[0]));
}

function aa_long_form ($in)
{
  global $aa_13;
  if (strlen ($in) == 1) return $aa_13[strtoupper($in)];
  return preg_replace_callback ('/\\*|fs|Frameshift|Shift|[A-Z][a-eg-z]*/', 'aa_long__', $in);
}

function aa_short__ ($in) {
  global $aa_31;
  if ($in[0] == "Shift" || $in[0] == "Frameshift" || $in[0] == "Frame" || $in[0] == "fs") return "Shift";
  if (strtolower($in[0]) == "del") return "Del";
  if (strlen($in[0]) >= 3 && isset($aa_31[$aa=ucfirst(strtolower($in[0]))])) return $aa_31[$aa];
  else if (strlen($in[0]) == 1) return strtoupper($in[0]);
  else return $in[0];
}

function aa_short_form ($in)
{
  global $aa_31;
  return preg_replace_callback ('/\\*|fs|Frameshift|Shift|[A-Z][a-eg-z]*/', 'aa_short__', $in);
}

function aa_sane ($in)
{
  global $aa_31, $aa_13;
  if (ereg ("^([A-Za-z]+)([0-9]+)([A-Za-z\\*]+)$", $in, $regs) &&
      ( (isset($aa_31[ucfirst(strtolower($regs[1]))]) &&
	 isset($aa_31[ucfirst(strtolower($regs[3]))]))
	||
	(isset($aa_13[strtoupper($regs[1])]) &&
	 isset($aa_13[strtoupper($regs[3])]))) )
    return true;
  else
    return false;
}

function aa_indel_sane ($pos, $del, $ins)
{
  global $aa_13;
  if (!preg_match('{^\d+$}', $pos))
    return false;
  if (strlen($del) == 0)
    return false;
  if (strlen($ins) == 0)
    return false;
  $del = aa_short_form ($del);
  $ins = aa_short_form ($ins);
  for ($i=0; $i<strlen($del); $i++)
    if (!array_key_exists($del[$i], $aa_13))
      return false;
  for ($i=0; $i<strlen($ins); $i++) {
    if (($ins[$i] == "X" || $ins[$i] == "*") && $i<strlen($ins)-1)
      return false;
    $remainder = substr ($ins, $i);
    if ($remainder == "Shift" ||
	$remainder == "Frameshift" ||
	$remainder == "fs" ||
	$remainder == "Del")
      break;
    if (array_key_exists($ins[$i], $aa_13))
      continue;
    return false;
  }
  return true;
}

function aa_indel_long_form ($pos, $del, $ins)
{
  $del = aa_short_form ($del);
  $ins = preg_replace ('{del$}i', '', aa_short_form ($ins));
  if (strlen ($del) > 1) {
    $pos2 = $pos + strlen($del) - 1;
    $pos = "{$pos}_{$pos2}";
  }
  return "{$pos}del{$del}ins{$ins}";
}

function aa_test ()
{
  foreach(array("A123AC",
		"A123Del",
		"A123Frameshift",
		"AC123Shift",
		"AC123Cfs",
		"C*123AA",
		"*123Shift") as $a) {
    preg_match ('{(.*?)(\d+)(.*)}', $a, $regs);
    list ($all,$from,$pos,$to) = $regs;
    printf ("%s\t%s\t%s\t%s\t%s\n", $all, aa_short_form($all), aa_long_form($all), aa_indel_sane($pos,$from,$to), aa_indel_long_form($pos,$from,$to));
  }
}

?>
