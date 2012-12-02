<?php

require_once('upgrade.php');

function xmldb_qtype_coderunner_install() {
    xmldb_qtype_coderunner_upgrade(0);
}

?>