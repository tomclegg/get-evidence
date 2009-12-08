<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base";

function print_content($x)
{
  print '
<h1>chr2:47496961</h1>
<div class="descr">dbSNP: rs4987188</div>

<p>MSH2 is homologous to the E. coli MutS gene and is involved in DNA mismatch repair.</p>

<ul>Genes:
<li>MSH2 (G322D)
</ul>

<ul>Publications:
<li>PMID 15563510. Alazzouzi, H.; Domingo, E.; Gonzalez, S.; Blanco, I.; Armengol, M.; Espin, E.; Plaja, A.; Schwartz, S.; Capella, G.; Schwartz, S., Jr. : <em>Low levels of microsatellite instability characterize MLH1 and MSH2 HNPCC carriers before tumor diagnosis.</em> Hum. Molec. Genet. 14: 235-239, 2005.</li>
</ul>
';
}

go();

?>
