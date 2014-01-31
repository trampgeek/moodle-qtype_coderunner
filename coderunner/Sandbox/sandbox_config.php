<?php

/*
 * This file exists solely to define the list of possible sandboxes.
 * The list is ordered "most preferred first", meaning that
 * the call to the getBestSandbox method in question.php will
 * return the first one found to support the required language.
 * Sandboxes in this list are enabled via the administrator settings
 * for the plugin. By default only the runguardsandbox is enabled.
 */

global $SANDBOXES;
$SANDBOXES = array('liusandbox', 'runguardsandbox', 'ideonesandbox');

?>
