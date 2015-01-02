<?php

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

class qtype_coderunner_prototype_test extends qtype_coderunner_testcase {

    // Test we can create a prototype question then a derived question that
    // inherits a few representative fields.
    public function test_inheritance_from_prototype() {
        $this->make_sqr_user_type_prototype();
        $q2 = $this->make_question('sqr_user_prototype_child');  // Make a derived question
        $this->assertEquals('combinatortemplatevalue', $q2->combinator_template);
        $this->assertEquals('jobesandbox', $q2->sandbox);
    }
     
    // Test any prototype files are also used by child
    public function test_files_inherited() {
        $id = $this->make_sqr_user_type_prototype(true);

        $this->setAdminUser();
        $fs = get_file_storage();

        // Prepare file record object
        $fileinfo = array(
            'contextid' => 1, // ID of context for prototype
            'component' => 'qtype_coderunner',
            'filearea' => 'datafile',
            'itemid' => $id,
            'filepath' => '/',
            'filename' => 'data.txt');

        // Create file (deleting any existing version first)
        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
            $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);
        $file->delete();
        $fs->create_file_from_string($fileinfo, "This is data\nLine 2");
        
        $q = $this->make_question('sqr_user_prototype_child');  // Make a derived question

        $code = "print(open('data.txt').read())"; 
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        list($mark, $grade, $cache) = $result;
        $testOutcome = unserialize($cache['_testoutcome']);

        $this->assertTrue($testOutcome->all_correct());
    }    
    
    
    // Support function to make and save a prototype question.
    // Optionally, prototype has a file attached for testing file inheritance.
    // Returns prototype question id

    private function make_sqr_user_type_prototype($fileAttachmentReqd=false) {
        global $DB;
        $q = $this->make_question('sqr');
        $q->test_cases = array();  // No testcases in a prototype
        $q->customise = 1;
        $q->prototype_type = 2;
        $q->type_name = "sqr_user_prototype";
        $q->cputimelimitsecs = 179; // Silly test value
        $q->combinator_template = 'combinatortemplatevalue';
        $q->sandbox = "jobesandbox";
        
        // Save the prototype to the DB so it has an accessible context for
        // retrieving associated files. All we need is its id and
        // its category, but the DB has other required fields so we dummy
        // up a minimal question containing the right category, at least.
        
        question_bank::load_question_definition_classes('coderunner');
        $row = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($row);
        $catRow = $DB->get_record_select(  // Find the question category for system context (1)
                   'question_categories',
                   "contextid=1 limit 1");
        $row->category = $catRow->id;
        $row->qtype = 'qtype_coderunner';
        $row->contextid = 1;
        foreach (array('id', 'name', 'questiontext', 'generalfeedback') as $key) {
            $row->$key = $q->$key;
        }

        $q->id = $DB->insert_record('question', $row);

        $qtype = new qtype_coderunner();
        $qtype->save_question_options($q);

        if ($fileAttachmentReqd) {
            // Attach a file.
            $fs = get_file_storage();
            $fileinfo = array(
                'contextid' => 1,
                'component' => 'qtype_coderunner',
                'filearea'  => 'datafile',
                'itemid'    => $q->id,
                'filepath'  => '/',
                'filename'  => 'data.txt');

            // Create file
            $fs->create_file_from_string($fileinfo, "This is data\nLine 2");
        }
        return $q->id;
    }
    
}
?>
