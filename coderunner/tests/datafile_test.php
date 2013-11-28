<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Test attaching of datafiles to questions.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2011, 2012, 2013 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/question.php');
require_once($CFG->dirroot . '/local/Twig/Autoloader.php');



class qtype_coderunner_datafile_test extends advanced_testcase {
    protected function setUp() {
        $this->qtype = new qtype_coderunner_question();
    }


    protected function tearDown() {
        $this->qtype = null;
    }

    public function test_datafile_runguardsandbox() {
        $this->resetAfterTest(true);
        $q = test_question_maker::make_question('coderunner', 'generic_python3');
        $q->testcases = array(
            (object) array(
                'testcode'  => '',
                'expected'  => "Success!\n",
                'stdin'     => '',
                'useasexample' => 0,
                'display'   => 'SHOW',
                'mark'      => 1.0,
                'hiderestiffail'  => 0
                )
            );
        $q->contextid = 1;      // HACK. Hopefully this is a valid contextid
        $q->id = 1101;          // Another random id
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
        $this->setAdminUser();
        $fs = get_file_storage();

        // Prepare file record object
        $fileinfo = array(
            'contextid' => 1, // ID of context
            'component' => 'qtype_coderunner',
            'filearea' => 'datafile',
            'itemid' => $q->id,
            'filepath' => '/',
            'filename' => 'data.txt');

        // Create file
        $fs->create_file_from_string($fileinfo, "This is data\nLine 2");

        // Now test it

        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(question_state::$gradedright, $grade);

        // Clean up by deleting the file again
        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
            $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
        $file->delete();
    }


    public function test_datafile_liusandbox() {
        $this->resetAfterTest(true);
        $q = test_question_maker::make_question('coderunner', 'generic_c');
        $q->testcases = array(
            (object) array(
                'testcode'  => '',
                'expected'  => "Success!\n",
                'stdin'     => '',
                'useasexample' => 0,
                'display'   => 'SHOW',
                'mark'      => 1.0,
                'hiderestiffail'  => 0
                )
            );
        $q->contextid = 1;
        $q->id = 1101;                            // Random question id
        $q->sandbox = 'LiuSandbox';
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
        $this->setAdminUser();
        $fs = get_file_storage();

        // Prepare file record object
        $fileinfo = array(
            'contextid' => 1, // ID of context
            'component' => 'qtype_coderunner',
            'filearea' => 'datafile',
            'itemid' => $q->id,
            'filepath' => '/',
            'filename' => 'data.txt');

        // Create file
        $fs->create_file_from_string($fileinfo, "This is data\nLine 2");

        // Now test it

        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $this->assertEquals(question_state::$gradedright, $grade);

        // Clean up by deleting the file again
        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
            $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
        $file->delete();
    }

}

