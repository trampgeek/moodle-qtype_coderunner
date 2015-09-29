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

// Special class of exception thrown when the helper is asked to construct
// a CodeRunner question of a type for which no prototype exists.
// This may occur if, say Matlab has been installed in a sandbox but the
// corresponding matlab question types have not been loaded prior to
// phpunit being initialised.
class qtype_coderunner_missing_question_type extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}


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
            'stringDelete',
            'sqrmatlab', 'teststudentanswermacro', 'sqroctave',
            'teststudentanswermacrooctave', 'sqrnodejs',
            'sqrjava', 'nameclass', 'printsquares', 'printstr',
            'sqr_user_prototype_child');
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
        return $this->make_coderunner_question_sqr_subtype('python3_pylint');
    }


    /**
     *  Make a generic Python3 question  (should print "Success!")
     */
    public function make_coderunner_question_generic_python3() {
        return $this->make_coderunner_question(
                'python3',
                'GenericName',
                'Generic question',
                array(
                    array('expected'  => "Success!\n")
                ));
    }
    
    
    /**
     *  Make a generic C question (should print "Success!")
     */
    public function make_coderunner_question_generic_c() {
        return $this->make_coderunner_question(
                'C_program',
                'GenericName',
                'Generic question',
                array(
                    array('expected'  => "Success!\n")
                ));
    }

    /**
     * Makes a coderunner question asking for a sqr() function.
     * @param $coderunnertype  The type of coderunner function to generate,
     * e.g. 'python3_pylint'.
     * @return qtype_coderunner_question
     */
    private function make_coderunner_question_sqr_subtype($coderunnertype, $extras = array()) {
        $coderunner = $this->make_coderunner_question(
                $coderunnertype,
                'Function to square a number n',
                'Write a function sqr(n) that returns n squared',
                array(
                    array('testcode' => 'print(sqr(0))',
                          'expected' => '0'),
                    array('testcode' => 'print(sqr(1))',
                          'expected' => '1',
                          'mark'     => 2.0),
                    array('testcode' => 'print(sqr(11))',
                          'expected' => '121',
                          'mark'     => 4.0),
                    array('testcode' => 'print(sqr(-7))',
                          'expected' => '49',
                          'mark'     => 8.0),
                    array('testcode' => 'print(sqr(-6))',
                          'expected' => '36',
                          'display'  => 'HIDE', // The last testcase must be hidden
                          'mark'     => 16.0)
        ), $extras);

        return $coderunner;
    }


   /**
     * Makes a coderunner question asking for a sqr() function.
     * This version uses testcases that don't print the result, for use in
     * testing custom grader templates.

     */
    public function make_coderunner_question_sqrnoprint() {
        $coderunner = $this->make_coderunner_question(
                'python3',
                'Function to square a number n',
                'Write a function sqr(n) that returns n squared',
                array(
                    array('testcode' => 'sqr(0)',
                          'expected' => '0'),
                    array('testcode' => 'sqr(1)',
                          'expected' => '1',
                          'mark'     => 2.0),
                    array('testcode' => 'sqr(11)',
                          'expected' => '121',
                          'mark'     => 4.0),
                    array('testcode' => 'sqr(-7)',
                          'expected' => '49',
                          'mark'     => 8.0),
                    array('testcode' => 'sqr(-6)',
                          'expected' => '36',
                          'display'  => 'HIDE', // The last testcase must be hidden
                          'mark'     => 16.0)
        ));
        return $coderunner;
    }

    public function make_coderunner_question_sqrCustomised() {
        $q = $this->make_coderunner_question_sqr_subtype('python3', 
          array(
            'pertesttemplate' => "def times(a, b): return a * b\n\n{{STUDENT_ANSWER}}\n\n{{TEST.testcode}}\n",
            'enablecombinator' => False)
          );
        return $q;
    }

    public function make_coderunner_question_sqrPartMarks() {
        // Make a version of the sqr question where testcase[i] carries a
        // mark of i / 2.0 for i in range 1 .. 5
        $coderunner = $this->make_coderunner_question(
                'python3',
                'Function to square a number n',
                'Write a function sqr(n) that returns n squared',
                array(
                    array('testcode' => 'print(sqr(0))',
                          'expected' => '0',
                          'mark'     => 0.5,),
                    array('testcode' => 'print(sqr(1))',
                          'expected' => '1',
                          'mark'     => 1.0),
                    array('testcode' => 'print(sqr(11))',
                          'expected' => '121',
                          'mark'     => 1.5),
                    array('testcode' => 'print(sqr(-7))',
                          'expected' => '49',
                          'mark'     => 2.0),
                    array('testcode' => 'print(sqr(-6))',
                          'expected' => '36',
                          'display'  => 'HIDE', // The last testcase must be hidden
                          'mark'     => 2.5)
        ), array('allornothing' => false));
        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that just print 'Hello <name>'
     * This test also tests multiline expressions.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_helloFunc() {
        $coderunner = $this->make_coderunner_question(
                'python3',
                'Function to print hello to someone',
                'Write a function sayHello(name) that prints "Hello <name>"',
                array(
                    array('testcode' => 'sayHello("")',
                          'expected' => 'Hello '),
                    array('testcode' => 'sayHello("Angus")',
                          'expected' => 'Hello Angus'),
                    array('testcode' => "name = 'Angus'\nsayHello(name)",
                          'expected' => 'Hello Angus'),
                    array('testcode' => "name = \"'Angus'\"\nprint(name)\nsayHello(name)",
                          'expected' => "'Angus'\nHello 'Angus'")
        ));

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_copyStdin() {
        $coderunner = $this->make_coderunner_question(
                'python3',
                'Function to copy n lines of stdin to stdout',
                'Write a function copyLines(n) that reads n lines from stdin and writes them to stdout. ',
                array(
                    array('testcode' => 'copyStdin(0)',
                          'expected' => ''),
                    array('testcode' => 'copyStdin(1)',
                          'stdin'    => "Line1\nLine2\n",
                          'expected' => "Line1\n"),
                    array('testcode' => 'copyStdin(2)',
                          'stdin'    => "Line1\nLine2\n",
                          'expected' => "Line1\nLine2\n"),
                    array('testcode' => 'copyStdin(4)',
                        // This example is also designed to test the clean function in
                        // the grader (which should trim white space of the end of
                        // output lines and trim trailing blank lines).
                          'stdin'    => " Line  1  \n   Line   2   \n  \n  \n   ",
                          'expected' => " Line  1\n   Line   2\n"),
                    array('testcode' => 'copyStdin(3)',
                          'stdin'    => "Line1\nLine2\n",
                          'expected' => "Line1\nLine2\n") # Irrelevant - runtime error
        ));

        return $coderunner;
    }


    /**
     * Makes a coderunner question that loops forever, to test sandbox timeout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_timeout() {
        $coderunner = $this->make_coderunner_question(
                'python3',
                'Function to generate a timeout',
                'Write a function that loops forever',
                array(
                    array('testcode' => 'timeout()')
        ));

        return $coderunner;
    }


    /**
     * Makes a coderunner question that's just designed to show if the
     * __student_answer__ variable is correctly set within each test case.
     */
    public function make_coderunner_question_studentanswervar() {
        $coderunner = $this->make_coderunner_question(
                'python3',
                'Function to generate a timeout',
                'Write a bit of code',
                array(
                    array('testcode' => 'print(__student_answer__)',
                          'expected'      => "\"\"\"Line1\n\"Line2\"\n'Line3'\nLine4\n\"\"\"")
        ));

        return $coderunner;
    }


    /**
     * Makes a coderunner question that requires students to write a function
     * that conditionally throws exceptions
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_exceptions() {
        $coderunner = $this->make_coderunner_question(
                'python3',
                'Function to conditionally throw an exception',
                'Write a function isOdd(n) that throws and ValueError exception iff n is odd',
                array(
                  array('testcode' => 'try:
  checkOdd(91)
  print("No exception")
except ValueError:
  print("Exception")',
                        'expected'      => 'Exception'),
                  array('testcode' => 'for n in [1, 11, 84, 990, 7, 8]:
  try:
     checkOdd(n)
     print("No")
  except ValueError:
     print("Yes")',
                        'expected'      => "Yes\nYes\nNo\nNo\nYes\nNo\n")
        ));

        return $coderunner;
    }



    /**
     * Makes a coderunner question to write a Python3 program that just print 'Hello Python'
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_helloPython() {
        $coderunner = $this->make_coderunner_question(
                'python3',
                'Program to print "Hello Python"',
                'Write a program that prints "Hello Python"',
                array(
                    array('testcode' => '',
                          'expected'    => 'Hello Python')
        ));

        return $coderunner;
    }
    
    /**
     * Makes a coderunner question of type 'sqr_user_prototype' to check out
     * inheritance. The prototype must have been created before this method
     * can be called.
     */
    public function make_coderunner_question_sqr_user_prototype_child() {
        $coderunner = $this->make_coderunner_question(
                'sqr_user_prototype',
                'Program to test prototype',
                'Answer should (somehow) produce the expected answer below',
                array(
                    array('expected'   => "This is data\nLine 2")
                )
        );
        return $coderunner;
    }


    // Now the C-question helper stuff
    // ===============================


   /**
     * Makes a coderunner question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrC() {
        $coderunner = $this->make_coderunner_question(
                'c_function',
                'Function to square a number n',
                'Write a function int sqr(int n) that returns n squared.',
                array(
                    array('testcode'       => 'printf("%d", sqr(0));',
                         'expected'        => '0'),
                    array('testcode'       => 'printf("%d", sqr(7));',
                          'expected'       => '49'),
                    array('testcode'       => 'printf("%d", sqr(-11));',
                          'expected'       => '121'),
                    array('testcode'       => 'printf("%d", sqr(-16));',
                          'expected'       => '256')
        ));

        return $coderunner;
    }

    /**
     * Makes a coderunner question asking for a sqr() function but without
     * semicolons on the ends of all the printf testcases.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrNoSemicolons() {
        $coderunner = $this->make_coderunner_question(
                'c_function',
                'Function to square a number n',
                'Write a function int sqr(int n) that returns n squared.',
                array(
                    array('testcode'       => 'printf("%d", sqr(0))',
                          'expected'       => '0'),
                    array('testcode'       => 'printf("%d", sqr(7))',
                          'expected'       => '49'),
                    array('testcode'       => 'printf("%d", sqr(-11))',
                          'expected'       => '121'),
                    array('testcode'       => 'printf("%d", sqr(-16))',
                          'expected'       => '256')
        ));

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that just print 'Hello ENCE260'
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_helloProgC() {
        $coderunner = $this->make_coderunner_question(
                'c_program',
                'Program to print "Hello ENCE260"',
                'Write a program that prints "Hello ENCE260"',
                array(
                    array('testcode' => '',
                          'expected' => 'Hello ENCE260')
        ));

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a program that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_copyStdinC() {
        $coderunner = $this->make_coderunner_question(
                'c_program',
                'Function to copy n lines of stdin to stdout',
                'Write a function copyLines(n) that reads stdin to stdout',
                array(
                    array('stdin'    => '',
                          'expected' => ''),
                    array('stdin'    => "Line1\n",
                          'expected' => "Line1\n"),
                    array('stdin'    => "Line1\nLine2\n",
                          'expected' => "Line1\nLine2\n")
        ));

        return $coderunner;
    }


    public function make_coderunner_question_strToUpper() {
        $coderunner = $this->make_coderunner_question(
                'c_function',
                'Function to convert string to uppercase',
                'Write a function void strToUpper(char s[]) that converts s to uppercase',
                array(
                    array('testcode' => "
char s[] = {'1','@','a','B','c','d','E',';', 0};
strToUpper(s);
printf(\"%s\\n\", s);
",
                          'expected' => '1@ABCDE;'),
                    array('testcode' => "
char s[] = {'1','@','A','b','C','D','e',';', 0};
strToUpper(s);
printf(\"%s\\n\", s);
",
                          'expected'    => '1@ABCDE;')
        ));

        return $coderunner;
    }



    /**
     * Makes a coderunner question asking for a stringDelete() function that
     * deletes from a given string all characters present in another
     * string
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_stringDelete() {
        $coderunner = $this->make_coderunner_question(
                'c_function',
                'Function to delete from a source string all chars present in another string',
                'Write a function void stringDelete(char *s, const char *charsToDelete) '.
                'that takes any two C strings as parameters and modifies the ' .
                'string s by deleting from it all characters that are present in charsToDelete.',
                array(
                    array('testcode'  => "char s[] = \"abcdefg\";\nstringDelete(s, \"xcaye\");\nprintf(\"%s\\n\", s);",
                          'expected'  => 'bdfg'),
                    array('testcode'  => "char s[] = \"abcdefg\";\nstringDelete(s, \"\");\nprintf(\"%s\\n\", s);",
                          'expected'  => 'abcdefg'),
                    array('testcode'  => "char s[] = \"aaaaabbbbb\";\nstringDelete(s, \"x\");\nprintf(\"%s\\n\", s);",
                          'expected'  => 'aaaaabbbbb')
        ));

        return $coderunner;
    }

 // Now the matlab-question helper stuff
    // ===============================

   /**
     * Makes a matlab question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrmatlab() {
        $coderunner = $this->make_coderunner_question(
            'matlab_function',
            'Function to square a number n',
            'Write a function sqr(n) that returns n squared.',
            array(
                array('testcode'       => 'disp(sqr(0));',
                      'expected'       => '     0'),
                array('testcode'       => 'disp(sqr(7));',
                      'expected'       => '    49'),
                array('testcode'       => 'disp(sqr(-11));',
                      'expected'       => '   121'),
                array('testcode'       => 'disp(sqr(-16));',
                     'expected'        => '   256')
        ));

        return $coderunner;
    }

   /**
     * Makes a coderunner question designed to check if the MATLAB_ESCAPED_STUDENT_ANSWER
    *  variable is working and usable within Matlab/Octave
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_teststudentanswermacro() {
        return $this->make_macro_question('matlab_function');
    }
    
    
    private function make_macro_question($qtype) {
        $options = array();
        $options['pertesttemplate'] = <<<EOT
function tester()
  ESCAPED_STUDENT_ANSWER =  sprintf('{{MATLAB_ESCAPED_STUDENT_ANSWER}}');
  {{TEST.testcode}};quit();
end

{{STUDENT_ANSWER}}
EOT;
        if ($qtype === 'octave_function') {
            $options['pertesttemplate'] .= "\n\ntester()\n";
        }
        $options['enablecombinator'] = false;
        
        $questiontext = <<<EOT
 Enter the following program:

 function mytest()
     s1 = '"Hi!" he said';
     s2 = '''Hi!'' he said';
     disp(s1);
     disp(s2);
end
EOT;
        $coderunner = $this->make_coderunner_question(
            $qtype,
            'Matlab/Octave escaped student answer tester',
            $questiontext,
            array(
                array('testcode'       => 'mytest();',
                      'expected'       => "\"Hi!\" he said\n'Hi!' he said"),
                array('testcode'       => 'disp(ESCAPED_STUDENT_ANSWER);',
                      'expected'       => <<<EOT
function mytest()
    s1 = '"Hi!" he said'; % a comment
    s2 = '''Hi!'' he said';
    disp(s1);
    disp(s2);
end
EOT
                )
        ), $options);

        return $coderunner;
    }

    
    // Now the octave-question helper stuff
    // ===================================
    // An edited version of Matlab helper stuff.
    // Has to handle the difference in the behaviour of disp.

   /**
     * Makes an octave question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqroctave() {
        $coderunner = $this->make_coderunner_question(
            'octave_function',
            'Function to square a number n',
            'Write a function sqr(n) that returns n squared.',
            array(
                array('testcode'       => 'disp(sqr(0));',
                      'expected'       => '0'),
                array('testcode'       => 'disp(sqr(7));',
                      'expected'       => '49'),
                array('testcode'       => 'disp(sqr(-11));',
                      'expected'       => '121'),
                array('testcode'       => 'disp(sqr(-16));',
                      'expected'       => '256')
        ));

        return $coderunner;
    }
    
    
   /**
     * Makes an nodejs question asking for a sqr() function.
     * Nodejs is not a built-in type.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrnodejs() {
        $coderunner = $this->make_coderunner_question(
            'python3',
            'Function to square a number n',
            'Write a js function sqr(n) that returns n squared.',
            array(
                array('testcode'  => 'console.log(sqr(0));',
                      'expected'  => '0'),
                array('testcode'  => 'console.log(sqr(7));',
                      'expected'  => '49'),
                array('testcode'  => 'console.log(sqr(-11));',
                      'expected'  => '121'),
                array('testcode'  => 'console.log(sqr(-16));',
                     'expected'   => '256'),
             ),
             array('language'          => 'nodejs',
                   'sandboxparams'    => '{"memorylimit": 1000000}',
                   'pertesttemplate' => "{{STUDENT_ANSWER}}\n{{TEST.testcode}}\n",
                   'enablecombinator' => False)
        );
        return $coderunner;
    }
    
    public function make_coderunner_question_teststudentanswermacrooctave()
    {
        return $this->make_macro_question('octave_function');
    }

   

/* Now Java questions
 * ==================
 */
    /**
     * Makes a coderunner question asking for a sqr() method in Java
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrjava() {
        $coderunner = $this->make_coderunner_question(
                'java_method',
                'Method to square a number n',
                'Write a method int sqr(int n) that returns n squared.',
                array(
                    array('testcode'  => 'System.out.println(sqr(0))',
                          'expected'  => '0'),
                    array('testcode'  => 'System.out.println(sqr(7))',
                          'expected'  => '49'),
                    array('testcode'  => 'System.out.println(sqr(-11))',
                          'expected'  => '121'),
                    array('testcode'  => 'System.out.println(sqr(16))',
                          'expected'  => '256')
        ));


        return $coderunner;
    }

   /**
     * Makes a coderunner question asking for a Java 'Name' class
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_nameclass() {
        $coderunner = $this->make_coderunner_question(
                'java_class',
                'Name class',
                'Write a class Name with a constructor ' .
                'that has firstName and lastName parameters with a toString ' .
                'method that returns firstName space lastName',
                array(
                    array('testcode'   => 'System.out.println(new Name("Joe", "Brown"))',
                          'expected'   => 'Joe Brown'),
                    array('testcode'   => 'System.out.println(new Name("a", "b"))',
                          'expected'   => 'a b')
        ));

        return $coderunner;
    }

   /**
     * Makes a coderunner question asking for a program that prints squares
     * of numbers from 1 up to and including a value read from stdin.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_printsquares() {
        $coderunner = $this->make_coderunner_question(
                'java_program',
                'Name class',
                'Write a program squares that reads an integer from stdin and prints ' .
                'the squares of all integers from 1 up to that number, all on one line, space separated.',
                array(
                    array('stdin'      => "5\n",
                          'expected'   => "1 4 9 16 25\n"),
                    array('stdin'      => "1\n",
                          'expected'   => "1\n")
        ));
        return $coderunner;
    }


    /**
     * Makes a coderunner question in which the testcode is just a Java literal
     * string and the template makes a program to use that value and print it.
     * The output should then be identical to the input string.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_printstr() {
        $code = <<<EOTEST
a0
b\t
c\f
d'This is a string'
"So is this"
EOTEST;
        $template = <<<EOPROG
public class Test
{
    public static void main(String[] args) {
        System.out.println("{{TEST.testcode|e('java')}}");
    }
}
EOPROG;
        $q = $this->make_coderunner_question(
                'java_program',
                'Print string',
                'No question answer required',
                array(
                  array('testcode' => $code,
                        'stdin'    => "5\n",
                        'expected' => "a0\nb\t\nc\f\nd'This is a string'\n\"So is this\"")
                ),
                array('pertesttemplate' =>  $template,
                      'enablecombinator' => false)
        );
        return $q;
    }


    // ============== SUPPORT METHODS ====================

    /* Fill in the option information for a specific question type,
     * by reading it from the database. Can't use questiontype's
     * get_question_options method, as we'd like to, because it requires
     * a form rather than a question and may have a files area with files
     * to upload - too hard to set up :-(
     * The normal get_options method returns all the options in 
     * the 'options' field of the object, but CodeRunner then subsequently
     * flattens the options into the question itself. This implementation does
     * both - defining the options object and the flattened version - so the
     * resulting question can be used in the usual CodeRunner context but
     * also in contexts like the question-export tes (which expects the options
     * field).
     */
    private function get_options(&$question) {
        global $CFG, $DB;
        
        $type = $question->coderunnertype;

        if (!$row = $DB->get_record_select(
                   'question_coderunner_options',
                   "coderunnertype = '$type' and prototypetype != 0")) {
               throw new qtype_coderunner_missing_question_type("TestHelper: failed to load type info for question with type $type");
        }
        
        $noninherited = qtype_coderunner::noninherited_fields();
        foreach ($row as $field=>$value) {
            if (!in_array($field, $noninherited)) {
                $question->$field = $value;
            }
        }
        
        
        foreach ($question->options as $key => $value) {
            $question->$key = $value;
        }

        
        // What follows is a rather horrible hack to support question export
        // testing. Having built the flattened question, we now "unflatten"
        // it back out to the set of options we get from the database.
        
        $question->options = new StdClass();
        foreach ($question->qtype->extra_question_fields() as $field) {
            if (isset($question->$field)) {
                $question->options->$field = $question->$field;
            } else {
                $question->options->$field = NULL;
            }
        }
        
        $question->options->answers = array();  // For compatability with questiontype base
        $question->options->testcases = $question->testcases;
        

        if (!isset($question->sandbox)) {
            $question->sandbox = $question->get_best_sandbox($question->language);
            if ($question->sandbox === null) {
                throw new exception("TestHelper: no sandbox available for language {$question->language}");
            }
        }
        if (!isset($question->grader)) {
            $question->grader = 'EqualityGrader';
        }
    }
    
    
    // Given an array of tests in which each element has just the bare minimum
    // of info, add in all the other necessary fields to get an array of
    // testCase objects.
    private static function make_test_cases($rawtests) {
        $basicTest = array('testcode'       => '',
                           'stdin'          => '',
                           'extra'          => '',
                           'expected'       => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0);
        $tests = array();
        foreach ($rawtests as $test) {
            $t = $basicTest; // Copy
            foreach ($test as $key => $value) {
                $t[$key] = $value;
            }
            $tests[] = (object) $t;
        }
        return $tests;      
    }


    // Return a CodeRunner question of a given (sub)type with given testcases
    // and other options. Further fields might be added by
    // coderunnertestcase::make_question (q.v.).
    
    private function make_coderunner_question($type, $name='', $questionText='', 
            $testcases, $otherOptions = array()) {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->coderunnertype = $type;
        $coderunner->prototypetype = 0;
        $coderunner->name = $name;
        $coderunner->useace = true;
        $coderunner->questiontext = $questionText;
        $coderunner->allornothing = true;
        $coderunner->showsource = false;
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->unitpenalty = 0.2;
        $coderunner->customise = false;
        $coderunner->testcases = self::make_test_cases($testcases);
        $coderunner->options = array();
        $coderunner->isnew = true;  // Extra field normally added by save_question
        $coderunner->context = context_system::instance(); // Use system context for testing
        foreach ($otherOptions as $key => $value) {
            $coderunner->options[$key] = $value;
        }
        $this->get_options($coderunner);
        return $coderunner;
    }
}

