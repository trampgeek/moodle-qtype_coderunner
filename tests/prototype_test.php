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
 * Unit tests for the coderunner question prototype capability
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2013 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/coderunnertestcase.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

class qtype_coderunner_prototype_test extends qtype_coderunner_testcase {

    // Test we can create a prototype question then a derived question that
    // inherits a few representative fields.
    public function test_inheritance_from_prototype() {
        $this->make_sqr_user_type_prototype();
        $q2 = $this->make_question('sqr_user_prototype_child');  // Make a derived question.
        $this->assertEquals('combinatortemplatevalue', $q2->template);
        $this->assertEquals(179, $q2->cputimelimitsecs);
    }

    // Test any prototype files are also used by child.
    public function test_files_inherited() {
        $q = $this->make_parent_and_child();
        $code = "print(open('data.txt').read())";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $testoutcome = unserialize($cache['_testoutcome']);

        $this->assertTrue($testoutcome->all_correct());
    }

    // Test exported question does not contain inherited fields.
    public function test_export() {
        $q = $this->make_parent_and_child();
        $q->qtype = $q->qtype->name(); // TODO: Why does qformat_xml expect this field to be a string?!
        $exporter = new qformat_xml();
        $xml = $exporter->writequestion($q);
        $bits = preg_split("/<!-- question: [0-9]*  -->/", $xml);
        $xmlnoline1 = $bits[1];
        $expectedxml = '
  <question type="coderunner">
    <name>
      <text>Program to test prototype</text>
    </name>
    <questiontext format="html">
      <text>Answer should (somehow) produce the expected answer below</text>
    </questiontext>
    <generalfeedback format="html">
      <text>No feedback available for coderunner questions.</text>
    </generalfeedback>
    <defaultgrade>1</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <coderunnertype>sqr_user_prototype</coderunnertype>
    <prototypetype>0</prototypetype>
    <allornothing>1</allornothing>
    <penaltyregime></penaltyregime>
    <precheck>0</precheck>
    <showsource></showsource>
    <answerboxlines></answerboxlines>
    <answerboxcolumns></answerboxcolumns>
    <useace>1</useace>
    <resultcolumns></resultcolumns>
    <template></template>
    <iscombinatortemplate></iscombinatortemplate>
    <answer></answer>
    <validateonsave>0</validateonsave>
    <testsplitterre></testsplitterre>
    <language></language>
    <acelang></acelang>
    <sandbox></sandbox>
    <grader></grader>
    <cputimelimitsecs></cputimelimitsecs>
    <memlimitmb></memlimitmb>
    <sandboxparams></sandboxparams>
    <templateparams></templateparams>
    <testcases>
      <testcase testtype="0" useasexample="0" hiderestiffail="0" mark="1.0000000" >
      <testcode>
                <text></text>
      </testcode>
      <stdin>
                <text></text>
      </stdin>
      <expected>
                <text>This is data
Line 2</text>
      </expected>
      <extra>
                <text></text>
      </extra>
      <display>
                <text>SHOW</text>
      </display>
    </testcase>
    </testcases>
  </question>
';
        $this->assert_same_xml($expectedxml, $xmlnoline1);
    }

    // Support function to make a parent and its child.
    private function make_parent_and_child() {
        $id = $this->make_sqr_user_type_prototype(true);

        $this->setAdminUser();
        $fs = get_file_storage();

        // Prepare file record object.
        $fileinfo = array(
            'contextid' => 1, // ID of context for prototype.
            'component' => 'qtype_coderunner',
            'filearea' => 'datafile',
            'itemid' => $id,
            'filepath' => '/',
            'filename' => 'data.txt');

        // Create file (deleting any existing version first).
        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
            $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
        $file->delete();
        $fs->create_file_from_string($fileinfo, "This is data\nLine 2");

        $q = $this->make_question('sqr_user_prototype_child');  // Make a derived question.
        return $q;
    }

    public function assert_same_xml($expectedxml, $xml) {
        $this->assertEquals(str_replace("\r\n", "\n", $expectedxml),
                str_replace("\r\n", "\n", $xml));
    }

    // Support function to make and save a prototype question.
    // Optionally, prototype has a file attached for testing file inheritance.
    // Returns prototype question id.

    private function make_sqr_user_type_prototype($fileattachmentreqd = false) {
        global $DB;
        $q = $this->make_question('sqr');
        $q->test_cases = array();  // No testcases in a prototype.
        $q->prototypetype = 2;
        $q->typename = "sqr_user_prototype";
        $q->cputimelimitsecs = 179; // Silly test value.
        $q->template = 'combinatortemplatevalue';
        $q->isprototypetemplate = true;

        // Save the prototype to the DB so it has an accessible context for
        // retrieving associated files. All we need is its id and
        // its category, but the DB has other required fields so we dummy
        // up a minimal question containing the right category, at least.

        question_bank::load_question_definition_classes('coderunner');
        $row = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($row);
        $catrow = $DB->get_record_select(  // Find the question category for system context (1).
                   'question_categories',
                   "contextid=1 limit 1");
        $row->category = $catrow->id;
        $row->qtype = 'qtype_coderunner';
        $row->contextid = 1;
        foreach (array('id', 'name', 'questiontext', 'generalfeedback') as $key) {
            $row->$key = $q->$key;
        }

        $q->id = $DB->insert_record('question', $row);

        $qtype = new qtype_coderunner();
        $qtype->save_question_options($q);

        if ($fileattachmentreqd) {
            // Attach a file.
            $fs = get_file_storage();
            $fileinfo = array(
                'contextid' => 1,
                'component' => 'qtype_coderunner',
                'filearea'  => 'datafile',
                'itemid'    => $q->id,
                'filepath'  => '/',
                'filename'  => 'data.txt');

            // Create file.
            $fs->create_file_from_string($fileinfo, "This is data\nLine 2");
        }
        return $q->id;
    }
}
