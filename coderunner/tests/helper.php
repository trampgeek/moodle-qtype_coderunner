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
        return array('sqr', 'sqr_pylint',
            'helloFunc', 'copyStdin', 'timeout', 'exceptions',
            'sqrPartMarks', 'sqrnoprint',
            'studentanswervar', 'helloPython',
            'generic_python3', 'generic_c',
            'sqrC', 'sqrNoSemicolons', 'sqrCustomised',
            'helloProgC',
            'copyStdinC', 'timeoutC', 'exceptionsC', 'strToUpper',
            'strToUpperFullMain', 'stringDelete',
            'sqrmatlab', 'testStudentAnswerMacro',
            'sqrjava', 'nameclass', 'printsquares', 'printstr');
    }

    /**
     * Makes a coderunner python3 question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqr() {
        return $this->make_coderunner_question_sqr_subtype('python3');
    }

    /**
     * Makes a coderunner python3-pylint-func question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqr_pylint() {
        return $this->make_coderunner_question_sqr_subtype('python3_pylint_func');
    }


    /**
     *  Make a generic Python3 question
     */
    public function make_coderunner_question_generic_python3() {
        return $this->makeCodeRunnerQuestion(
                'python3',
                'GenericName',
                'Generic question'
        );
    }

    /**
     * Makes a coderunner question asking for a sqr() function.
     * @param $coderunner_type  The type of coderunner function to generate,
     * e.g. 'python3-pylint-func'.
     * @return qtype_coderunner_question
     */
    private function make_coderunner_question_sqr_subtype($coderunner_type) {
        $coderunner = $this->makeCodeRunnerQuestion(
                $coderunner_type,
                'Function to square a number n',
                'Write a function sqr(n) that returns n squared'
        );

        $coderunner->testcases = array(
            (object) array('testcode' => 'print(sqr(0))',
                          'expected'     => '0',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0, 'hiderestiffail'  => 0),
            (object) array('testcode' => 'print(sqr(1))',
                          'expected'     => '1',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 2.0, 'hiderestiffail' =>  0),
            (object) array('testcode' => 'print(sqr(11))',
                          'expected'     => '121',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 4.0, 'hiderestiffail' =>  0),
            (object) array('testcode' => 'print(sqr(-7))',
                          'expected'     => '49',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 8.0, 'hiderestiffail' =>  0),
            (object) array('testcode' => 'print(sqr(-6))',  // The last testcase must be hidden
                           'expected'     => '36',
                           'stdin'      => '',
                           'useasexample' => 0,
                           'display' => 'HIDE',
                           'mark' => 16.0, 'hiderestiffail' =>  0)
        );
        $this->getOptions($coderunner);
        return $coderunner;
    }


   /**
     * Makes a coderunner question asking for a sqr() function.
     * This version uses testcases that don't print the result, for use in
     * testing custom grader templates.

     */
    public function make_coderunner_question_sqrnoprint() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'python3',
                'Function to square a number n',
                'Write a function sqr(n) that returns n squared'
        );

        $coderunner->testcases = array(
            (object) array('testcode' => 'sqr(0)',
                          'expected'     => '0',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0, 'hiderestiffail'  => 0),
            (object) array('testcode' => 'sqr(1)',
                          'expected'     => '1',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 2.0, 'hiderestiffail' =>  0),
            (object) array('testcode' => 'sqr(11)',
                          'expected'     => '121',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 4.0, 'hiderestiffail' =>  0),
            (object) array('testcode' => 'sqr(-7)',
                          'expected'     => '49',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 8.0, 'hiderestiffail' =>  0),
            (object) array('testcode' => 'sqr(-6)',  // The last testcase must be hidden
                           'expected'     => '36',
                           'stdin'      => '',
                           'useasexample' => 0,
                           'display' => 'HIDE',
                           'mark' => 16.0, 'hiderestiffail' =>  0)
        );
        $this->getOptions($coderunner);
        return $coderunner;
    }

    public function make_coderunner_question_sqrCustomised() {
        $q = $this->make_coderunner_question_sqr();
        $q->customise = true;
        $q->per_test_template = "def times(a, b): return a * b\n\n{{STUDENT_ANSWER}}\n\n{{TEST.testcode}}\n";
        return $q;
    }

    public function make_coderunner_question_sqrPartMarks() {
        // Make a version of the sqr question where testcase[i] carries a
        // mark of i / 2.0 for i in range 1 .. ?
        $q = $this->make_coderunner_question_sqr();
        $q->all_or_nothing = false;
        for ($i = 0; $i < count($q->testcases); $i++) {
            $q->testcases[$i]->mark = ($i + 1) / 2.0;
        }
        return $q;
    }

    /**
     * Makes a coderunner question to write a function that just print 'Hello <name>'
     * This test also tests multiline expressions.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_helloFunc() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'python3',
                'Function to print hello to someone',
                'Write a function sayHello(name) that prints "Hello <name>"'
        );
        $coderunner->testcases = array(
            (object) array('testcode' => 'sayHello("")',
                          'expected'      => 'Hello ',
                          'stdin'       => '',
                          'useasexample' => 0,
                          'display'     => 'SHOW',
                          'mark'        => 1.0,
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'sayHello("Angus")',
                          'expected'      => 'Hello Angus',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display'     => 'SHOW',
                                'mark' => 1.0,
                          'hiderestiffail' =>  0),
            (object) array('testcode' => "name = 'Angus'\nsayHello(name)",
                          'expected'      => 'Hello Angus',
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark'    => 1.0,
                          'hiderestiffail' =>  0),
            (object) array('testcode' => "name = \"'Angus'\"\nprint(name)\nsayHello(name)",
                          'expected'  => "'Angus'\nHello 'Angus'",
                          'stdin'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0)
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_copyStdin() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'python3',
                'Function to copy n lines of stdin to stdout',
                'Write a function copyLines(n) that reads n lines from stdin and writes them to stdout. '
        );
        $coderunner->testcases = array(
            (object) array('testcode' => 'copyStdin(0)',
                          'stdin'       => '',
                          'expected'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'copyStdin(1)',
                          'stdin'       => "Line1\nLine2\n",
                          'expected'      => "Line1\n",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'copyStdin(2)',
                          'stdin'       => "Line1\nLine2\n",
                          'expected'      => "Line1\nLine2\n",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'copyStdin(4)',
                // This example is also designed to test the clean function in
                // the grader (which should trim white space of the end of
                // output lines and trim trailing blank lines).
                          'stdin'       => " Line  1  \n   Line   2   \n  \n  \n   ",
                          'expected'      => " Line  1\n   Line   2\n",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0),
            (object) array('testcode' => 'copyStdin(3)',
                          'stdin'       => "Line1\nLine2\n",
                          'expected'      => "Line1\nLine2\n", # Irrelevant - runtime error
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0)
        );

        return $coderunner;
    }


    /**
     * Makes a coderunner question that loops forever, to test sandbox timeout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_timeout() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'python3',
                'Function to generate a timeout',
                'Write a function that loops forever'
        );
        $coderunner->testcases = array(
            (object) array('testcode' => 'timeout()',
                          'stdin'       => '',
                          'expected'      => '',
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0)
        );

        return $coderunner;
    }


    /**
     * Makes a coderunner question that's just designed to show if the
     * __student_answer__ variable is correctly set within each test case.
     */
    public function make_coderunner_question_studentanswervar() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'python3',
                'Function to generate a timeout',
                'Write a bit of code'
        );
        $coderunner->testcases = array(
            (object) array('testcode' => 'print(__student_answer__)',
                          'stdin'       => '',
                          'expected'      => "\"\"\"Line1\n\"Line2\"\n'Line3'\nLine4\n\"\"\"",
                          'useasexample' => 0,
                          'display' => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0)
        );

        return $coderunner;
    }


    /**
     * Makes a coderunner question that requires students to write a function
     * that conditionally throws exceptions
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_exceptions() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'python3',
                'Function to conditionally throw an exception',
                'Write a function isOdd(n) that throws and ValueError exception iff n is odd'
        );
        $coderunner->testcases = array(
            (object) array('testcode' => 'try:
  checkOdd(91)
  print("No exception")
except ValueError:
  print("Exception")',
                            'stdin'       => '',
                            'expected'      => 'Exception',
                            'useasexample' => 0,
                            'display'     => 'SHOW',
                            'mark' => 1.0,
                            'hiderestiffail' =>  0),
            (object) array('testcode' => 'for n in [1, 11, 84, 990, 7, 8]:
  try:
     checkOdd(n)
     print("No")
  except ValueError:
     print("Yes")',
                          'stdin'       => '',
                          'expected'      => "Yes\nYes\nNo\nNo\nYes\nNo\n",
                          'useasexample' => 0,
                          'display'     => 'SHOW',
                          'mark' => 1.0,
                          'hiderestiffail' =>  0)
        );

        return $coderunner;
    }



    /**
     * Makes a coderunner question to write a Python3 program that just print 'Hello Python'
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_helloPython() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'python3',
                'Program to print "Hello Python"',
                'Write a program that prints "Hello Python"'
        );
        $coderunner->testcases = array(
            (object) array('testcode' => '',
                          'expected'    => 'Hello Python',
                          'stdin'     => '',
                          'display'   => 'SHOW',
                          'mark'      => 1.0,
                          'hiderestiffail' => 0,
                          'useasexample'   => 0)
        );

        return $coderunner;
    }


    // Now the C-question helper stuff
    // ===============================


    /**
     *  Make a generic C question
     */
    public function make_coderunner_question_generic_c() {
        return $this->makeCodeRunnerQuestion(
                'C_program',
                'GenericName',
                'Generic question'
        );
    }

   /**
     * Makes a coderunner question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrC() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'c_function',
                'Function to square a number n',
                'Write a function int sqr(int n) that returns n squared.'
        );
        $coderunner->testcases = array(
            (object) array('testcode'       => 'printf("%d", sqr(0));',
                           'stdin'          => '',
                           'expected'         => '0',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(7));',
                           'expected'         => '49',
                            'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(-11));',
                           'expected'         => '121',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0),
           (object) array('testcode'        => 'printf("%d", sqr(-16));',
                           'expected'         => '256',
                           'stdin'          => '',
                           'display'        => 'HIDE',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question asking for a sqr() function but without
     * semicolons on the ends of all the printf testcases.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrNoSemicolons() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'c_function',
                'Function to square a number n',
                'Write a function int sqr(int n) that returns n squared.'
        );

        $coderunner->testcases = array(
            (object) array('testcode'       => 'printf("%d", sqr(0))',
                           'expected'         => '0',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(7))',
                           'expected'         => '49',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(-11))',
                           'expected'         => '121',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0),
           (object) array('testcode'        => 'printf("%d", sqr(-16))',
                           'expected'         => '256',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that just print 'Hello ENCE260'
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_helloProgC() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'c_program',
                'Program to print "Hello ENCE260"',
                'Write a program that prints "Hello ENCE260"'
        );
        $coderunner->testcases = array(
            (object) array('testcode' => '',
                          'expected'    => 'Hello ENCE260',
                          'stdin'     => '',
                          'display'   => 'SHOW',
                          'mark'      => 1.0,
                          'hiderestiffail' => 0,
                          'useasexample'   => 0)
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a program that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_copyStdinC() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'c_program',
                'Function to copy n lines of stdin to stdout',
                'Write a function copyLines(n) that reads stdin to stdout'
        );
        $coderunner->testcases = array(
            (object) array('testcode' => '',
                          'stdin'     => '',
                          'expected'    => '',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'mark'      => 1.0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => '',
                          'stdin'     => "Line1\n",
                          'expected'    => "Line1\n",
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'mark'      => 1.0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => '',
                          'stdin'     => "Line1\nLine2\n",
                          'expected'    => "Line1\nLine2\n",
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'mark'      => 1.0,
                          'hiderestiffail' => 0)
        );

        return $coderunner;
    }


    public function make_coderunner_question_strToUpper() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'c_function',
                'Function to convert string to uppercase',
                'Write a function void strToUpper(char s[]) that converts s to uppercase'
        );
        $coderunner->testcases = array(
            (object) array('testcode' => "
char s[] = {'1','@','a','B','c','d','E',';', 0};
strToUpper(s);
printf(\"%s\\n\", s);
",
                          'stdin'     => '',
                          'expected'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'mark'      => 1.0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => "
char s[] = {'1','@','A','b','C','D','e',';', 0};
strToUpper(s);
printf(\"%s\\n\", s);
",
                          'stdin'     => '',
                          'expected'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'mark'      => 1.0,
                          'hiderestiffail' => 0)
        );

        return $coderunner;
    }


    public function make_coderunner_question_strToUpperFullMain() {
        // A variant of strToUpper where test cases include an actual main func
        $coderunner = $this->makeCodeRunnerQuestion(
                'c_full_main_tests',
                'Function to convert string to uppercase',
                'Write a function void strToUpper(char s[]) that converts s to uppercase'
        );
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
                          'expected'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'mark'      => 1.0,
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
                          'expected'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'mark'      => 1.0,
                          'hiderestiffail' => 0)
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question asking for a stringDelete() function that
     * deletes from a given string all characters present in another
     * string
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_stringDelete() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'c_function',
                'Function to delete from a source string all chars present in another string',
                'Write a function void stringDelete(char *s, const char *charsToDelete) '.
                'that takes any two C strings as parameters and modifies the ' .
                'string s by deleting from it all characters that are present in charsToDelete.'
        );
        $coderunner->testcases = array(
            (object) array('testcode'       => "char s[] = \"abcdefg\";\nstringDelete(s, \"xcaye\");\nprintf(\"%s\\n\", s);",
                           'expected'         => 'bdfg',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => "char s[] = \"abcdefg\";\nstringDelete(s, \"\");\nprintf(\"%s\\n\", s);",
                           'expected'         => 'abcdefg',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => "char s[] = \"aaaaabbbbb\";\nstringDelete(s, \"x\");\nprintf(\"%s\\n\", s);",
                           'expected'         => 'aaaaabbbbb',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );

        return $coderunner;
    }

 // Now the matlab-question helper stuff
    // ===============================

   /**
     * Makes a coderunner question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrmatlab() {
        $coderunner = $this->makeCodeRunnerQuestion(
            'matlab_function',
            'Function to square a number n',
            'Write a function sqr(n) that returns n squared.'
        );

        $coderunner->testcases = array(
            (object) array('testcode'       => 'disp(sqr(0));',
                           'stdin'          => '',
                           'expected'         => '     0',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'disp(sqr(7));',
                           'expected'         => '    49',
                            'stdin'         => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'disp(sqr(-11));',
                           'expected'         => '   121',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0),
           (object) array('testcode'        => 'disp(sqr(-16));',
                           'expected'         => '   256',
                           'stdin'          => '',
                           'display'        => 'HIDE',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );

        return $coderunner;
    }

   /**
     * Makes a coderunner question designed to check if the MATLAB_ESCAPED_STUDENT_ANSWER
    *  variable is working and usable within Matlab
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_testStudentAnswerMacro() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'matlab_function',
                'Matlab escaped student answer tester'
        );
        $coderunner->questiontext = <<<EOT
 Enter the following program:

 function mytest()
     s1 = '"Hi!" he said';
     s2 = '''Hi!'' he said';
     disp(s1);
     disp(s2);
end
EOT;
        $coderunner->testcases = array(
            (object) array('testcode'       => 'mytest();',
                           'stdin'          => '',
                           'expected'         => "\"Hi!\" he said\n'Hi!' he said",
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'disp(ESCAPED_STUDENT_ANSWER);',
                           'expected'         => <<<EOT
function mytest()
    s1 = '"Hi!" he said'; % a comment
    s2 = '''Hi!'' he said';
    disp(s1);
    disp(s2);
end
EOT
,                          'stdin'         => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );

        $coderunner->customise = 1;
        $coderunner->per_test_template = <<<EOT
function tester()
  ESCAPED_STUDENT_ANSWER =  sprintf('{{MATLAB_ESCAPED_STUDENT_ANSWER}}');
  {{TEST.testcode}};quit();
end

{{STUDENT_ANSWER}}
EOT;
        return $coderunner;
    }


/* Now Java questions
 * ==================
 */
    /**
     * Makes a coderunner question asking for a sqr() method in Java
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrjava() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'java_method',
                'Method to square a number n',
                'Write a method int sqr(int n) that returns n squared.'
        );
        $coderunner->testcases = array(
            (object) array('testcode'       => 'System.out.println(sqr(0))',
                           'stdin'          => '',
                           'expected'         => '0',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'System.out.println(sqr(7))',
                           'expected'         => '49',
                            'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'System.out.println(sqr(-11))',
                           'expected'         => '121',
                           'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0),
           (object) array('testcode'        => 'System.out.println(sqr(16))',
                           'expected'         => '256',
                           'stdin'          => '',
                           'display'        => 'HIDE',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );


        return $coderunner;
    }

   /**
     * Makes a coderunner question asking for a Java 'Name' class
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_nameclass() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'java_class',
                'Name class',
                'Write a class Name with a constructor ' .
                'that has firstName and lastName parameters with a toString ' .
                'method that returns firstName space lastName'
        );

        $coderunner->testcases = array(
            (object) array('testcode'       => 'System.out.println(new Name("Joe", "Brown"))',
                           'stdin'          => '',
                           'expected'         => 'Joe Brown',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'System.out.println(new Name("a", "b"))',
                           'expected'         => 'a b',
                            'stdin'          => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1)
        );

        return $coderunner;
    }

   /**
     * Makes a coderunner question asking for a program that prints squares
     * of numbers from 1 up to and including a value read from stdin.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_printsquares() {
        $coderunner = $this->makeCodeRunnerQuestion(
                'java_program',
                'Name class',
                'Write a program squares that reads an integer from stdin and prints ' .
                'the squares of all integers from 1 up to that number, all on one line, space separated.');

        $coderunner->testcases = array(
            (object) array('testcode'       => '',
                           'stdin'          => "5\n",
                           'expected'         => "1 4 9 16 25\n",
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => '',
                           'stdin'          => "1\n",
                           'expected'         => "1\n",
                           'display'        => 'SHOW',
                           'mark'           => 1.0, 'hiderestiffail' => 0,
                           'useasexample'   => 1)
        );

        return $coderunner;
    }


    /**
     * Makes a coderunner question in which the testcode is just a Java literal
     * string and the template makes a program to use that value and print it.
     * The output should then be identical to the input string.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_printstr() {
        $q = $this->makeCodeRunnerQuestion(
                'java_program',
                'Print string',
                'No question answer required');

        $q->testcases = array(
            (object) array('testcode'       => <<<EOTEST
a0
b\t
c\f
d'This is a string'
"So is this"
EOTEST
,                          'stdin'          => "5\n",
                           'expected'       => "a0\nb\t\nc\f\nd'This is a string'\n\"So is this\"",
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 1)
        );
        $q->customise = true;
        $q->per_test_template = <<<EOPROG
public class Test
{
    public static void main(String[] args) {
        System.out.println("{{TEST.testcode|e('java')}}");
    }
}
EOPROG;
        return $q;
    }


    // ============== SUPPORT METHODS ====================

    /* Fill in the option information for a specific question type,
     * by reading it from the database.
     */
    private function getOptions(&$question) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');
        $qtype = new qtype_coderunner();

        $type = $question->options['coderunner_type'];

        if (!$record = $DB->get_record('quest_coderunner_options',
                array('coderunner_type' => $type, 'prototype_type' => 1))) {
            throw new coding_exception("TestHelper: bad call to getOptions with type $type");
        }
        foreach ($record as $field=>$value) {
            $question->$field = $value;
        }

        if (!isset($question->per_test_template)) {
            $question->per_test_template = '';
        }

        $question->customise = trim($question->per_test_template) != '';

        if (!isset($question->sandbox)) {
            $question->sandbox = $qtype->getBestSandbox($question->language);
        }
        if (!isset($question->grader)) {
            $question->grader = 'EqualityGrader';
        }
    }


    // Return an empty CodeRunner question
    private function makeCodeRunnerQuestion($type, $name='', $questionText='') {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->options = array('coderunner_type' => $type);
        $coderunner->name = $name;
        $coderunner->questiontext = $questionText;
        $coderunner->all_or_nothing = true;
        $coderunner->show_source = false;
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->showtest = TRUE;
        $coderunner->showstdin = TRUE;
        $coderunner->showexpected = TRUE;
        $coderunner->showoutput = TRUE;
        $coderunner->showmark = FALSE;
        $coderunner->unitpenalty = 0.2;
        $coderunner->customise = FALSE;
        $coderunner->contextid = 1;   // HACK. Needed when requesting data files.
        $this->getOptions($coderunner);
        return $coderunner;
    }

}

