<?php

/*
 * This file exists solely to define which sandboxes are active in this
 * installation. The list is ordered "most preferred first", meaning that
 * the call to the private getBestSandbox method in questiontype.php will
 * return the first one found to support the required language.
 */
global $ACTIVE_SANDBOXES;
$ACTIVE_SANDBOXES = array('liusandbox', 'nullsandbox', 'ideonesandbox');
//$ACTIVE_SANDBOXES = array('nullsandbox');  // For running all languages in nullsandbox
//$ACTIVE_SANDBOXES = array('ideonesandbox');  // For running all languages at ideone.com
?>
