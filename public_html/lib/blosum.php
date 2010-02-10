<?php

  // Copyright 2010 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

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

$gBLOSUM100 = "A A 5
B A -3
B B 4
B C -5
B D 4
B E 0
B F -5
B G -2
B H -1
B I -5
B K -1
B L -5
B M -4
B N 4
B P -3
B Q -1
B R -2
B S -1
B T -2
B V -5
B W -6
B Y -4
C A -1
C C 9
C D -5
C N -4
C R -5
D A -3
D D 7
D N 1
D R -3
E A -2
E C -6
E D 1
E E 6
E N -1
E Q 1
E R -2
F A -4
F C -3
F D -5
F E -5
F F 7
F G -5
F H -2
F I -1
F K -4
F L 0
F M -1
F N -5
F Q -4
F R -4
G A -1
G C -5
G D -3
G E -4
G G 6
G N -2
G Q -3
G R -4
H A -3
H C -5
H D -2
H E -1
H G -4
H H 9
H N 0
H Q 0
H R -1
I A -3
I C -2
I D -6
I E -5
I G -6
I H -5
I I 5
I N -5
I Q -4
I R -4
K A -2
K C -5
K D -2
K E 0
K G -3
K H -2
K I -4
K K 6
K L -4
K N -1
K Q 1
K R 2
L A -3
L C -3
L D -6
L E -5
L G -5
L H -4
L I 1
L L 5
L N -5
L Q -3
L R -4
M A -2
M C -3
M D -5
M E -4
M G -5
M H -3
M I 1
M K -2
M L 2
M M 8
M N -4
M Q -1
M R -2
N A -2
N N 7
N R -1
P A -1
P C -5
P D -3
P E -3
P F -5
P G -4
P H -3
P I -4
P K -2
P L -4
P M -4
P N -4
P P 8
P Q -2
P R -3
Q A -1
Q C -5
Q D -2
Q N -1
Q Q 7
Q R 0
R A -2
R R 7
S A 1
S C -2
S D -1
S E -1
S F -3
S G -1
S H -2
S I -4
S K -1
S L -4
S M -3
S N 0
S P -2
S Q -1
S R -2
S S 6
T A -1
T C -2
T D -2
T E -2
T F -3
T G -3
T H -3
T I -2
T K -2
T L -3
T M -2
T N -1
T P -3
T Q -2
T R -2
T S 1
T T 6
V A -1
V C -2
V D -5
V E -3
V F -2
V G -5
V H -5
V I 2
V K -4
V L 0
V M 0
V N -4
V P -4
V Q -3
V R -4
V S -3
V T -1
V V 5
V W -4
V Y -3
W A -4
W C -5
W D -7
W E -5
W F 0
W G -5
W H -3
W I -4
W K -5
W L -4
W M -3
W N -6
W P -6
W Q -3
W R -4
W S -4
W T -5
W W 11
X A -1
X B -2
X C -3
X D -3
X E -2
X F -3
X G -3
X H -2
X I -2
X K -2
X L -2
X M -2
X N -2
X P -3
X Q -2
X R -2
X S -1
X T -1
X V -2
X W -4
X X -2
X Y -3
X Z -2
Y A -4
Y C -4
Y D -5
Y E -4
Y F 3
Y G -6
Y H 1
Y I -3
Y K -4
Y L -3
Y M -3
Y N -3
Y P -5
Y Q -3
Y R -3
Y S -3
Y T -3
Y W 1
Y Y 8
Z A -2
Z B 1
Z C -6
Z D 0
Z E 5
Z F -5
Z G -4
Z H -1
Z I -4
Z K 0
Z L -4
Z M -3
Z N -1
Z P -3
Z Q 3
Z R -1
Z S -1
Z T -2
Z V -3
Z W -4
Z Y -4
Z Z 4";

function blosum100 ($aa1, $aa2)
{
    $aa1 = aa_short_form ($aa1);
    $aa2 = aa_short_form ($aa2);
    if ($aa1 == "X" || $aa2 == "X")
	return -10;

    global $gBLOSUM100;
    if (!is_array ($gBLOSUM100)) {
	$text = $gBLOSUM100;
	$gBLOSUM100 = Array();
	foreach (preg_split ('/\n/', $text) as $x) {
	    $x = preg_split ('/ /', $x);
	    $gBLOSUM100["$x[0] $x[1]"] = $x[2];
	}
    }

    $x = $gBLOSUM100["$aa1 $aa2"];
    if (!$x)
	$x = $gBLOSUM100["$aa2 $aa1"];
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