<?php

/*
 * This file exists to define the available sandboxes and the search order
 * that CodeRunner uses when looking for a sandbox that supports a particular
 * language.
 * The list is ordered "most preferred first", meaning that
 * the call to the getBestSandbox method in question.php will
 * return the first one found to support the required language.
 * Sandboxes in this list are enabled via the administrator settings
 * for the plugin. By default only the jobesandbox is enabled.
 */

global $SANDBOXES;
$SANDBOXES = array('jobesandbox', 'liusandbox', 'runguardsandbox', 'ideonesandbox');

?>
