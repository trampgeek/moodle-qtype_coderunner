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
 * Unit tests for the coderunner question type class.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2011 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/coderunner/edit_coderunner_form.php');



/**
 * Unit tests for the coderunner question type class.
 *
 * Just a few trivial sanity checks. If this fails, something's seriously broken.
 *
 * @copyright  2012 Richard Lobb, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_coderunner_test extends advanced_testcase {
    protected $qtype;

    protected function setUp() {
        $this->qtype = new qtype_coderunner();
    }

    protected function tearDown() {
        $this->qtype = null;
    }

    protected function get_test_question_data() {
        $q = new stdClass();
        $q->id = 1;

        return $q;
    }

    public function test_name() {
        $this->assertEquals('coderunner', $this->qtype->name());
    }


    public function test_get_random_guess_score() {
        $q = $this->get_test_question_data();
        $this->assertEquals(0, $this->qtype->get_random_guess_score($q));
    }

    public function test_get_possible_responses() {
        $q = $this->get_test_question_data();
        $this->assertEquals(array(), $this->qtype->get_possible_responses($q));
    }
    
    public function test_question_saving_multichoice_ui() {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // gets the data from helper.php method get_coderunner_question_data_sqr
        $questiondata = test_question_maker::get_question_data('coderunner');
        
        // gets the data from helper.php method get_coderunner_question_form_data_sqr
        $formdata = test_question_maker::get_question_form_data('coderunner');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category(array());
        
        // Mock submit a form, with the form data (NOT the question data)
        $formdata->category = "{$cat->id},{$cat->contextid}";
        qtype_coderunner_edit_form::mock_submit((array)$formdata);

        // Returns an instance of the qtype_coderunner_edit_form class?
        $form = qtype_coderunner_test_helper::get_question_editing_form($cat, $questiondata);

        $this->assertTrue($form->is_validated());
        
        $fromform = $form->get_data();
        
       
        // this part saves a coderunner question using the data from the form, and the question data (???)
        $returnedfromsave = $this->qtype->save_question($questiondata, $fromform);
        print_r($returnedfromsave);
        // returnedfromsave has some of the data from the FORM data
        
        // so something goes funky in here
        $actualquestionsdata = question_load_questions(array($returnedfromsave->id));
        $actualquestiondata = end($actualquestionsdata);
                    
        foreach ($questiondata as $property => $value) {
            if (!in_array($property, array('id', 'version', 'timemodified', 'timecreated', 'options', 'testcases'))) {
                $this->assertAttributeEquals($value, $property, $actualquestiondata);
                echo($property."\n");
            }
        }
        
        echo("======\n");
        //print_r($questiondata);
        foreach ($questiondata->options as $optionname => $value) {
            if ($optionname != 'testcases') {
                echo($optionname."\n");
                $this->assertAttributeEquals($value, $optionname, $actualquestiondata->options);
            }
        }
        
        //test that the testcases match - still todo

    }
}
