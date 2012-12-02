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
 * Test helpers for the coderunner question type.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Test helper class for the coderunner question type.
 *
 */
class qtype_coderunner_test_helper extends question_test_helper {
    public function get_test_questions() {
        return array('sqr', 'helloFunc', 'copyStdin', 'timeout', 'exceptions',
            'sqrC', 'sqrNoSemicolons', 'helloProgC',
            'copyStdinC', 'timeoutC', 'exceptionsC', 'strToUpper',
            'strToUpperFullMain', 'stringDelete');
    }

    /**
     * Makes a coderunner question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqr() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to square a number n';
        $coderunner->questiontext = 'Write a function sqr(n) that returns n squared';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->options = array('coderunner_type' => 'python3_basic');
        $coderunner->testcases = array(
            (object) array('testcode' => 'print(sqr(0))',
                          'output'     => '0',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail'  => 0),
            (object) array('testcode' => 'print(sqr(1))',
                          'output'     => '1',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'print(sqr(11))',
                          'output'     => '121',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'print(sqr(-7))',
                          'output'     => '49',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'print(sqr(-6))',  // The last testcase must be hidden
                           'output'     => '36',
                           'stdin'      => '',
                           'useasexample' => 0,
                           'display' => 'HIDE',
                           'hiderestiffail' =>  0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that just print 'Hello <name>'
     * This test also tests multiline expressions.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_helloFunc() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to print hello to someone';
        $coderunner->options = array('coderunner_type' => 'python3_basic');
        $coderunner->questiontext = 'Write a function sayHello(name) that prints "Hello <name>"';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->testcases = array(
            (object) array('testcode' => 'sayHello("")',
                          'output'      => 'Hello ',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'sayHello("Angus")',
                          'output'      => 'Hello Angus',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0),
            (object) array('testcode' => "name = 'Angus'\nsayHello(name)",
                          'output'      => 'Hello Angus',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0),
            (object) array('testcode' => "name = \"'Angus'\"\nprint(name)\nsayHello(name)",
                          'output'  => "'Angus'\nHello 'Angus'",
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_copyStdin() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->options = array('coderunner_type' => 'python3_basic');
        $coderunner->name = 'Function to copy n lines of stdin to stdout';
        $coderunner->questiontext = 'Write a function copyLines(n) that reads n lines from stdin and writes them to stdout. ';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->testcases = array(
            (object) array('testcode' => 'copyStdin(0)',
                          'stdin'       => '',
                          'output'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'copyStdin(1)',
                          'stdin'       => "Line1\nLine2\n",
                          'output'      => "Line1\n",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'copyStdin(2)',
                          'stdin'       => "Line1\nLine2\n",
                          'output'      => "Line1\nLine2\n",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'copyStdin(3)',
                          'stdin'       => "Line1\nLine2\n",
                          'output'      => "Line1\nLine2\n", # Irrelevant - runtime error
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }

    /**
     * Makes a coderunner question that loops forever, to test sandbox timeout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_timeout() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to generate a timeout';
        $coderunner->options = array('coderunner_type' => 'python3_basic');
        $coderunner->questiontext = 'Write a function that loops forever';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->testcases = array(
            (object) array('testcode' => 'timeout()',
                          'stdin'       => '',
                          'output'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'hiderestiffail' =>  0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }


    /**
     * Makes a coderunner question that requires students to write a function
     * that conditionally throws exceptions
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_exceptions() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to conditionally throw an exception';
        $coderunner->options = array('coderunner_type' => 'python3_basic');
        $coderunner->questiontext = 'Write a function isOdd(n) that throws and ValueError exception iff n is odd';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->testcases = array(
            (object) array('testcode' => 'try:
  checkOdd(91)
  print("No exception")
except ValueError:
  print("Exception")',
                            'stdin'       => '',
                            'output'      => 'Exception',
                            'useasexample' => 0,
                            'display'     => 'SHOW',
                            'hiderestiffail' =>  0),
            (object) array('testcode' => 'for n in [1, 11, 84, 990, 7, 8]:
  try:
     checkOdd(n)
     print("No")
  except ValueError:
     print("Yes")',
                          'stdin'       => '',
                          'output'      => "Yes\nYes\nNo\nNo\nYes\nNo\n",
                          'useasexample' => 0,
                          'display'     => 'SHOW',
                          'hiderestiffail' =>  0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }

    /* Fill in the option information for a specific question type,
     * by reading it from the database.
     */
    private function getOptions(&$question) {
        global $DB;

        $type = $question->options['coderunner_type'];

        if (!$record = $DB->get_record('quest_coderunner_types',
                array('coderunner_type' => $type))) {
            throw new coding_exception("TestHelper: bad call to getOptions");
        }
        foreach ($record as $field=>$value) {
            if ($field === 'per_test_template') {
                $field = 'template';
            }
            $question->$field = $value;
        }
    }


    // Now the C-question helper stuff
    // ===============================

   /**
     * Makes a coderunner question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrC() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to square a number n';
        $coderunner->questiontext = 'Write a function int sqr(int n) that returns n squared.';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->options = array('coderunner_type' => 'c_function');
        $coderunner->testcases = array(
            (object) array('testcode'       => 'printf("%d", sqr(0));',
                           'stdin'          => '',
                           'output'         => '0',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(7));',
                           'output'         => '49',
                            'stdin'          => '',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(-11));',
                           'output'         => '121',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0),
           (object) array('testcode'        => 'printf("%d", sqr(-16));',
                           'output'         => '256',
                           'stdin'          => '',
                           'display'        => 'HIDE',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }

    /**
     * Makes a coderunner question asking for a sqr() function but without
     * semicolons on the ends of all the printf testcases.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrNoSemicolons() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to square a number n';
        $coderunner->questiontext = 'Write a function int sqr(int n) that returns n squared.';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->options = array('coderunner_type' => 'c_function');
        $coderunner->testcases = array(
            (object) array('testcode'       => 'printf("%d", sqr(0))',
                           'output'         => '0',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(7))',
                           'output'         => '49',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(-11))',
                           'output'         => '121',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0),
           (object) array('testcode'        => 'printf("%d", sqr(-16))',
                           'output'         => '256',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }


    /**
     * Makes a coderunner question to write a function that just print 'Hello ENCE260'
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_helloProgC() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Program to print "Hello ENCE260"';
        $coderunner->questiontext = 'Write a program that prints "Hello ENCE260"';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->options = array('coderunner_type' => 'c_program');
        $coderunner->testcases = array(
            (object) array('testcode' => '',
                          'output'    => 'Hello ENCE260',
                          'stdin'     => '',
                          'display'   => 'SHOW',
                          'hiderestiffail' => 0,
                          'useasexample'   => 0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a program that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_copyStdinC() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to copy n lines of stdin to stdout';
        $coderunner->questiontext = 'Write a function copyLines(n) that reads stdin to stdout';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->options = array('coderunner_type' => 'c_program');
        $coderunner->testcases = array(
            (object) array('testcode' => '',
                          'stdin'     => '',
                          'output'    => '',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => '',
                          'stdin'     => "Line1\n",
                          'output'    => "Line1\n",
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => '',
                          'stdin'     => "Line1\nLine2\n",
                          'output'    => "Line1\nLine2\n",
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }


    public function make_coderunner_question_strToUpper() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to convert string to uppercase';
        $coderunner->questiontext = 'Write a function void strToUpper(char s[]) that converts s to uppercase';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->options = array('coderunner_type' => 'c_function_side_effects');
        $coderunner->testcases = array(
            (object) array('testcode' => "
char s[] = {'1','@','a','B','c','d','E',';', 0};
strToUpper(s);
printf(\"%s\\n\", s);
",
                          'stdin'     => '',
                          'output'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => "
char s[] = {'1','@','A','b','C','D','e',';', 0};
strToUpper(s);
printf(\"%s\\n\", s);
",
                          'stdin'     => '',
                          'output'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }


    public function make_coderunner_question_strToUpperFullMain() {
        // A variant of strToUpper where test cases include an actual main func
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to convert string to uppercase';
        $coderunner->questiontext = 'Write a function void strToUpper(char s[]) that converts s to uppercase';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->options = array('coderunner_type' => 'c_full_main_tests');
        $coderunner->testcases = array(
            (object) array('testcode' => "
int main() {
  char s[] = {'1','@','a','B','c','d','E',';', 0};
  strToUpper(s);
  printf(\"%s\\n\", s);
  return 0;
}
",
                          'stdin'     => '',
                          'output'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => "
int main() {
  char s[] = {'1','@','A','b','C','D','e',';', 0};
  strToUpper(s);
  printf(\"%s\\n\", s);
  return 0;
}
",
                          'stdin'     => '',
                          'output'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }

    /**
     * Makes a coderunner question asking for a stringDelete() function that
     * deletes from a given string all characters present in another
     * string
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_stringDelete() {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->name = 'Function to delete from a source string all chars present in another string';
        $coderunner->questiontext = 'Write a function void stringDelete(char *s, const char *charsToDelete) that takes any two C strings as parameters and modifies the string s by deleting from it all characters that are present in charsToDelete.';
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->options = array('coderunner_type' => 'c_function');
        $coderunner->testcases = array(
            (object) array('testcode'       => "char s[] = \"abcdefg\";\nstringDelete(s, \"xcaye\");\nprintf(\"%s\\n\", s);",
                           'output'         => 'bdfg',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => "char s[] = \"abcdefg\";\nstringDelete(s, \"\");\nprintf(\"%s\\n\", s);",
                           'output'         => 'abcdefg',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => "char s[] = \"aaaaabbbbb\";\nstringDelete(s, \"x\");\nprintf(\"%s\\n\", s);",
                           'output'         => 'aaaaabbbbb',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->unitgradingtype = 0;
        $coderunner->unitpenalty = 0.2;
        $this->getOptions($coderunner);
        return $coderunner;
    }
}
