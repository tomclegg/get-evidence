<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Terms of Service";
$gOut["content"] = $gTheTextile->textileThis (<<<EOF
h2. Terms of Service

GET-Evidence is meant for research purposes only. It is available free of charge. In doing so we make no claims or guarantees about ANYTHING, including but not limited to: (1) Privacy, (2) Effectiveness, (3) Reliability.

h3. Privacy

*We do not consider our system to be secure and we do not guarantee the privacy of your data.* Although we currently allow individuals to upload genetic data for private analysis, this system is intended for Personal Genome Project participants to preview their genome analyses and to demonstrate the capabilities of our analysis tool to fellow researchers. We strongly encourage users to create private instances of GET-Evidence to analyze private data.

_By using GET-Evidence you agree that we are not liable for privacy and security breaches that may result in data being shared inappropriately or becoming public._

In addition to genome upload, genomic data privacy may be indirectly breached through a user's edit history. By design, all user edits made to to gene variants in our database are public. Your name will be attached to this data if made available through your OpenID log in.

_By using GET-Evidence you agree that edits to the database are considered public and that we are not liable for any privacy concerns resulting from these contributions._

h3. Effectiveness

*GET-Evidence is a research tool and not intended as a medical diagnostic.* It was created to facilitate the process of whole genome analysis. Much of our data is produced through peer production contributions by users, so our database is by nature far from complete. What data exists may also be flawed, as there may be error in the interpretation. In addition, not all types of genetic variation are analyzed by our system, so critically important genome features may be missed entirely.

_By using GET-Evidence you agree that we are not liable for any errors in analysis or any consequences that may arise as a result of genome interpretation (whether done correctly or incorrectly)._

h3. Reliability

*At any point we may decide to retract our service, in whole or in part.* In particular, we anticipate that the ability to analyze genome data on this site is temporary. We may remove existing genome analyses at any time. We encourage users to create private instances of this genome analysis system for their own data, for both privacy reasons (see above) and to ensure their data is not erased.

_By using GET-Evidence you agree that any data you have given us may be erased at any time._ 
EOF
);

go();

?>
