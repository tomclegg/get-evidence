<?php

include "lib/setup.php";

evidence_create_tables();

if (!getCurrentUser("is_admin"))
  {
    die ("yours is not an admin account.");
  }


if ($_REQUEST["test-insert"])
   {
     header ("Content-type: text/plain");


     $variant_id = evidence_get_variant_id ("NPHP4", 848, "Arg", "Trp", true);
     $e = evidence_edit_id_generate (null, $variant_id);



     evidence_save_draft ($e, array ("variant_impact" => "unknown",
				     "variant_dominance" => "unknown",
				     "summary_short" => "This variant has been found together with R682X as a compound heterozygote in three nephronophthisis and retinitis pigmentosa, (Senior-Loken syndrome) patients from one family."));

     print "after evidence_save_draft\n";
     print_r (theDb()->getRow ("SELECT * FROM edits WHERE edit_id=?", array($e)));



     evidence_submit ($e);

     print "after evidence_submit\n";
     print_r (evidence_get_report ("latest", $variant_id));


     evidence_signoff ($e);

     $a = evidence_edit_id_generate (null, $variant_id);
     evidence_save_draft ($a, array ("article_pmid" => 12205563,
				     "summary_short" => "Otto, E. et al. A gene mutated in nephronophthisis and retinitis pigmentosa encodes a novel protein, nephroretinin, conserved in evolution. Am J Hum Genet 71, 1161-1167, doi:S0002-9297(07)60408-X [pii]"));
     evidence_submit ($a);
     evidence_signoff ($a);

     $a = evidence_edit_id_generate (null, $variant_id);
     evidence_save_draft ($a, array ("article_pmid" => 9734597,
				     "summary_short" => "Lemmink, H. H. et al. Novel mutations in the thiazide-sensitive NaCl cotransporter gene in patients with Gitelman syndrome with predominant localization to the C-terminal domain. Kidney Int 54, 720-730, doi:10.1046/j.1523-1755.1998.00070.x (1998)."));
     evidence_submit ($a);

     print "release:\n";
     print_r (evidence_get_report ("release", $variant_id));

     print "latest:\n";
     print_r (evidence_get_report ("latest", $variant_id));

   }
 else
   {
     header ("Location: /");
   }
?>
