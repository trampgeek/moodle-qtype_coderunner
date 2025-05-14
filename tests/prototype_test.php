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


namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * @coversNothing
 */
class prototype_test extends \qtype_coderunner_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    // Test we can create a prototype question then a derived question that
    // inherits a few representative fields.
    public function test_inheritance_from_prototype() {
        $this->make_sqr_user_type_prototype();
        $q2 = $this->make_question('sqr_user_prototype_child');  // Make a derived question.
        $this->assertEquals('{{ STUDENT_ANSWER }}', $q2->template);
        $this->assertEquals(29, $q2->cputimelimitsecs);
    }

    // Test any prototype files are also used by child.
    public function test_files_inherited() {
        $q = $this->make_parent_and_child();
        $code = "print(open('data.txt').read())";
        $response = ['answer' => $code];
        $result = $q->grade_response($response);
        [, , $cache] = $result;
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertTrue($testoutcome->all_correct());
    }

    // Test that any template parameters defined in the prototype are
    // inherited by the child but can be overridden by it.
    // xxx and yyy are both defined by the parent prototype and the
    // child overrides xxx and adds zzz.
    public function test_params_inherited() {
        $q = $this->make_parent_and_child();
        $q->template = <<<EOTEMPLATE
print( {{QUESTION.parameters.xxx}}, {{QUESTION.parameters.yyy}}, {{QUESTION.parameters.zzz}})
EOTEMPLATE;
        $q->allornothing = false;
        $q->iscombinatortemplate = false;
        $q->testcases = [
                       (object) ['type' => 0,
                         'testcode'       => '',
                         'expected'       => "1 200 2",
                         'stdin'          => '',
                         'extra'          => '',
                         'useasexample'   => 0,
                         'display'        => 'SHOW',
                         'mark'           => 1.0,
                         'hiderestiffail' => 0],
        ];
        $q->allornothing = false;
        $q->iscombinatortemplate = false;
        $code = "";
        $response = ['answer' => $code];
        $q->start_attempt(null);
        $result = $q->grade_response($response);
        [$mark, $grade, $cache] = $result;
        $testoutcome = unserialize($cache['_testoutcome']);
        $this->assertTrue($testoutcome->all_correct());
    }

    // Test exported question does not contain inherited fields.
    public function test_export() {
        $q = $this->make_parent_and_child();
        $q->qtype = $q->qtype->name(); // TODO: Why does qformat_xml expect this field to be a string?!
        $exporter = new \qformat_xml();
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
    <idnumber></idnumber>
    <coderunnertype>sqr_user_prototype</coderunnertype>
    <prototypetype>0</prototypetype>
    <allornothing>1</allornothing>
    <penaltyregime>10, 20, ...</penaltyregime>
    <precheck>0</precheck>
    <hidecheck>0</hidecheck>
    <showsource></showsource>
    <answerboxlines></answerboxlines>
    <answerboxcolumns></answerboxcolumns>
    <answerpreload></answerpreload>
    <globalextra></globalextra>
    <useace></useace>
    <resultcolumns></resultcolumns>
    <template></template>
    <iscombinatortemplate></iscombinatortemplate>
    <allowmultiplestdins></allowmultiplestdins>
    <answer></answer>
    <validateonsave>1</validateonsave>
    <testsplitterre></testsplitterre>
    <language></language>
    <acelang></acelang>
    <sandbox></sandbox>
    <grader></grader>
    <cputimelimitsecs></cputimelimitsecs>
    <memlimitmb></memlimitmb>
    <sandboxparams></sandboxparams>
    <templateparams><![CDATA[{"xxx":1, "zzz":2}]]></templateparams>
    <hoisttemplateparams>0</hoisttemplateparams>
    <extractcodefromjson>1</extractcodefromjson>
    <templateparamslang>twig</templateparamslang>
    <templateparamsevalpertry>0</templateparamsevalpertry>
    <templateparamsevald></templateparamsevald>
    <twigall>0</twigall>
    <uiplugin></uiplugin>
    <uiparameters></uiparameters>
    <attachments>0</attachments>
    <attachmentsrequired>0</attachmentsrequired>
    <maxfilesize>0</maxfilesize>
    <filenamesregex></filenamesregex>
    <filenamesexplain></filenamesexplain>
    <displayfeedback>1</displayfeedback>
    <giveupallowed>0</giveupallowed>
    <prototypeextra></prototypeextra>
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
        $fileinfo = [
            'contextid' => 1, // ID of context for prototype.
            'component' => 'qtype_coderunner',
            'filearea' => 'datafile',
            'itemid' => $id,
            'filepath' => '/',
            'filename' => 'data.txt'];

        // Create file (deleting any existing version first).
        $file = $fs->get_file(
            $fileinfo['contextid'],
            $fileinfo['component'],
            $fileinfo['filearea'],
            $fileinfo['itemid'],
            $fileinfo['filepath'],
            $fileinfo['filename']
        );
        $file->delete();
        $fs->create_file_from_string($fileinfo, "This is data\nLine 2");

        $q = $this->make_question('sqr_user_prototype_child');  // Make a derived question.
        return $q;
    }

    public function assert_same_xml($expectedxml, $xml) {
        $this->assertEquals(
            str_replace("\r\n", "\n", $expectedxml),
            str_replace("\r\n", "\n", $xml)
        );
    }

    // Support function to make and save a prototype question.
    // The prototype question includes template parameters xxx and yyy for
    // testing inheritance of template parameters.
    // Optionally, prototype has a file attached for testing file inheritance.
    // Returns prototype question id.

    private function make_sqr_user_type_prototype($fileattachmentreqd = false) {
        global $DB;
        $q = $this->make_question('sqr');
        $q->name = 'PROTOTYPE_sqr_user_prototype';
        $q->testcases = [];  // No testcases in a prototype.
        $q->prototypetype = 2;
        $q->coderunnertype = "sqr_user_prototype";
        $q->cputimelimitsecs = 29; // Arbitrary test value.
        $q->template = '{{ STUDENT_ANSWER }}';
        $q->iscombinatortemplate = true;
        $q->templateparams = '{"xxx": 100, "yyy": 200}';

        // Save the prototype to the DB so it has an accessible context for
        // retrieving associated files.
        \question_bank::load_question_definition_classes('coderunner');
        $row = new \qtype_coderunner_question();
        \test_question_maker::initialise_a_question($row);
        $catrow = $DB->get_record_select(  // Find the question category for system context (1).
            'question_categories',
            "contextid=1 limit 1"
        );
        $q->category = $catrow->id;
        $row->qtype = 'coderunner';

        $qtype = new \qtype_coderunner();
        $qtype->save_question($row, $q);

        if ($fileattachmentreqd) {
            // Attach a file.
            $fs = get_file_storage();
            $fileinfo = [
                'contextid' => 1,
                'component' => 'qtype_coderunner',
                'filearea'  => 'datafile',
                'itemid'    => $q->id,
                'filepath'  => '/',
                'filename'  => 'data.txt'];

            // Create file.
            $fs->create_file_from_string($fileinfo, "This is data\nLine 2");
        }
        return $q->id;
    }
}
