<?php
  ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

require_once ("lib/aa.php");

$gBLOSUM62 = Array
      ("A" => "4  0 -2 -1 -2  0 -2 -1 -1 -1 -1 -2 -1 -1 -1  1  0  0 -3 -2",
       "C" =>    "9 -3 -4 -2 -3 -3 -1 -3 -1 -1 -3 -3 -3 -3 -1 -1 -1 -2 -2",
       "D" =>       "6  2 -3 -1 -1 -3 -1 -4 -3  1 -1  0 -2  0 -1 -3 -4 -3",
       "E" =>          "5 -3 -2  0 -3  1 -3 -2  0 -1  2  0  0 -1 -2 -3 -2",
       "F" =>             "6 -3 -1  0 -3  0  0 -3 -4 -3 -3 -2 -2 -1  1  3",
       "G" =>                "6 -2 -4 -2 -4 -3  0 -2 -2 -2  0 -2 -3 -2 -3",
       "H" =>                   "8 -3 -1 -3 -2  1 -2  0  0 -1 -2 -3 -2  2",
       "I" =>                      "4 -3  2  1 -3 -3 -3 -3 -2 -1  3 -3 -1",
       "K" =>                         "5 -2 -1  0 -1  1  2  0 -1 -2 -3 -2",
       "L" =>                            "4  2 -3 -3 -2 -2 -2 -1  1 -2 -1",
       "M" =>                               "5 -2 -2  0 -1 -1 -1  1 -1 -1",
       "N" =>                                  "6 -2  0  0  1  0 -3 -4 -2",
       "P" =>                                     "7 -1 -2 -1 -1 -2 -4 -3",
       "Q" =>                                        "5  1  0 -1 -2 -2 -1",
       "R" =>                                           "5 -1 -1 -3 -3 -2",
       "S" =>                                              "4  1 -2 -3 -2",
       "T" =>                                                 "5  0 -2 -2",
       "V" =>                                                    "4 -3 -1",
       "W" =>                                                      "11  2",
       "Y" =>                                                          "7");

function blosum100 ($aa1, $aa2)
{
  if (strstr ($aa2, "fs"))
    return -10; // frame shift

  $aa1 = aa_short_form ($aa1);
  $aa2 = aa_short_form ($aa2);

  if (!strstr($aa1, "X") && strstr($aa2, "X"))
    return -10; // nonsense mutation
  if (strstr($aa1, "X") && !strstr($aa2, "X"))
    return -4; // stop to read-through is not as bad
  if (strstr($aa1, "X") && strstr($aa2, "X"))
    return 10; // this should never happen anyway.

  if (strlen($aa1) != strlen($aa2) || strstr($aa2, "del"))
    return -4;
  if (strlen($aa1) > 1) {
    $min = 10;
    for ($i=0; $i<strlen($aa1); $i++) {
      $x = blosum100 ($aa1[$i], $aa2[$i]);
      if ($x < $min) $min = $x;
    }
    return $min;
  }

  global $blosum100_array;

  if ($blosum100_array)
    return $blosum100_array["$aa1 $aa2"];

  // blosum100.txt is from 
  // ftp://ftp.ncbi.nih.gov/blast/matrices/BLOSUM100
  $blosum100_file = "lib/blosum100.txt";
  $fh = fopen($blosum100_file, 'r');

  $blosum100_array = array();
  $top_line;
  $line_count = 0;
  while ($line = fgets($fh)) {
    $line = trim($line);
    if (!preg_match('/^#/', $line)) {
      if ($line_count == 0) {
        $top_line = preg_split ('/ +/', $line);
      } else {
        $data = preg_split ('/ +/', $line);
        for ($i = 1; $i < count($data); $i++) {
          $index = $data[0] . " " . $top_line[$i-1];
          $blosum100_array[$index] = $data[$i];
        }
      }
      $line_count++;
    }
  }

  $x = $blosum100_array["$aa1 $aa2"];
  return $x;
}

function blosum62 ($aa1, $aa2)
{
    $aa1 = aa_short_form ($aa1);
    $aa2 = aa_short_form ($aa2);
    if ($aa1 == "X" || $aa2 == "X")
	return -10;

    global $gBLOSUM62;
    if (!is_array ($gBLOSUM62["A"])) {
	$keys = array_keys ($gBLOSUM62);
	foreach ($gBLOSUM62 as $a => &$b) {
	    $b = array_combine ($keys, preg_split ('/  ?/', $b));
	    array_shift ($keys);
	}
    }

    if ($aa1 <= $aa2)
	return $gBLOSUM62[$aa1][$aa2];
    else
	return $gBLOSUM62[$aa2][$aa1];
}

?>
