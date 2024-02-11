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
 * Test attaching of datafiles to questions.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2011, 2012, 2013 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');

/**
 * Unit tests for attaching datafiles to questions.
 * @coversNothing
 */
class datafile_test extends \qtype_coderunner_testcase {
    // Test loading of files in the jobe sandbox.
    public function test_datafile_jobesandbox() {
        $code = $this->python_solution();
        $this->check_files_in_sandbox('generic_python3', 'jobesandbox', $code);
    }

    private function check_files_in_sandbox($questionname, $sandbox, $code) {
        $this->check_sandbox_enabled($sandbox);
        $q = $this->make_question($questionname);
        $q->sandbox = $sandbox;

        $this->setAdminUser();
        $fs = get_file_storage();

        // Prepare file record object.
        $fileinfo = [
            'contextid' => $q->contextid,
            'component' => 'qtype_coderunner',
            'filearea'  => 'datafile',
            'itemid'    => $q->id,
            'filepath'  => '/',
            'filename'  => 'data.txt'];

        // Create file.
        $fs->create_file_from_string($fileinfo, "This is data\nLine 2");

        // Now test it.

        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [, $grade, ] = $result;
        $this->assertEquals(\question_state::$gradedright, $grade);

        // Clean up by deleting the file again.
        $file = $fs->get_file(
            $fileinfo['contextid'],
            $fileinfo['component'],
            $fileinfo['filearea'],
            $fileinfo['itemid'],
            $fileinfo['filepath'],
            $fileinfo['filename']
        );
        $file->delete();
    }

    // The python3 solution to the problem.
    private function python_solution() {
        $code = <<<EOCODE
import os
files = os.listdir('.')
if 'data.txt' in files:
   data = open('data.txt').read()
   if data.strip() == "This is data\\nLine 2":
       print("Success!")
   else:
       print("Wrong contents")
else:
   print("File not present")
EOCODE;
        return $code;
    }

    // The C solution to the problem.
    private function c_solution() {
        $code = <<<EOCODE
#include <stdio.h>
#include <string.h>
int main() {
    FILE* f = fopen("data.txt", "r");
    char buff[1000];
    int i = 0;
    char c;
    while ((c = fgetc(f)) != EOF && i < 1000) {
        buff[i++] = c;
    }
    if (strcmp(buff, "This is data\\nLine 2") == 0) {
       printf("Success!\\n");
    }
    else {
       printf("Fail!\\n%s\\n", buff);
    }
}
EOCODE;
        return $code;
    }
}
