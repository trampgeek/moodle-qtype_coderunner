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
 * Script to return the spec for the currently-rendered coderunner question,
 * assumed to be present in a zip support file. Since pdfs are potentially
 * binary, the file contents are returned base64 encoded.
 * Designed specifically for use with domjudge or ICPC format problem zips,
 * although in fact it searches all zip files in the current coderunner
 * question looking for the first match of the requested filename (if given
 * and not empty) or the first filename ending in .pdf (otherwise).
 *
 * @package    qtype_coderunner
 * @copyright  2019 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
global $USER;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->libdir . '/questionlib.php');

require_login();
require_sesskey();

$currentqid = required_param('questionid', PARAM_INT);
$reqdfilename = optional_param('filename', '', PARAM_TEXT);

$qids = $USER->coderunnerquestionids;
// Security check: is the current questions being requested, and is it a pdf?
if (
    !in_array($currentqid, $qids) ||
    ($reqdfilename !== '' && strpos($reqdfilename, '.pdf', -4) === false)
) {
    echo('{"Error": "Unauthorised"}');
    die(); // This is not for the current question.
}
$question = question_bank::load_question($currentqid);
$files = $question->get_files();
header('Content-type: application/json; charset=utf-8');

foreach ($files as $filename => $contents) {
    if (substr($filename, -4) === '.zip') {
        $tempdir = make_request_directory();
        $tempfilename = tempnam($tempdir, 'zip');
        if ($tempfilename) {
            file_put_contents($tempfilename, $contents);
            $zippy = new ZipArchive();
            $zippy->open($tempfilename, ZipArchive::RDONLY);
            for ($i = 0; $i < $zippy->numFiles; $i++) {
                $filename = $zippy->getNameIndex($i);
                $base = pathinfo($filename, PATHINFO_BASENAME);
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($base === $reqdfilename || ($reqdfilename === "" && $extension === 'pdf')) {
                    $filecontents = $zippy->getFromIndex($i);
                    $json = json_encode(['filecontentsb64' => base64_encode($filecontents)]);
                    if ($json != null) {
                        echo $json;
                        $zippy->close();
                        unlink($tempfilename); // Note: $tempdir is auto-deleted.
                        die();
                    }
                }
            }
            $zippy->close();
            unlink($tempfilename); // Note: $tempdir is auto-deleted.

            // phpcs:disable      
            /*
            This is the old code so phpcs is diasbled so it doesn't complain about
            source code being included in comment, for now...
            while ($file = zip_read($handle)) {
                $name = zip_entry_name($file);
                $base = basename($name);
                if ($base === $reqdfilename || ($reqdfilename === "" && strpos($base, '.pdf', -4) !== false)) {
                    $filecontents = zip_entry_read($file, zip_entry_filesize($file));
                    $json = json_encode(['filecontentsb64' => base64_encode($filecontents)]);
                    if ($json != null) {
                        echo $json;
                        unlink($tempfilename);
                        zip_close($handle);
                        die();
                    }
                }
            }
            zip_close($handle);
            unlink($tempfilename); // Note: $tempdir is auto-deleted.
            */
            // phpcs:enable   
        }
    }
}
echo json_encode("FILE NOT FOUND");
die();
