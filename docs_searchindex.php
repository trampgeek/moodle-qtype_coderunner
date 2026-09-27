<?php

// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Returns the CodeRunner documentation search index as JSON: one entry per
 * section across every documentation page, for the client-side search.
 *
 * @author     Michal Skraburski
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib/docslib.php');

$docsdir = qtype_coderunner_docs_dir();
$index = qtype_coderunner_docs_search_index($docsdir, qtype_coderunner_docs_pages($docsdir));

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
echo json_encode($index);
