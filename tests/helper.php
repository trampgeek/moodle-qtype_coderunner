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
 * Test helpers for the coderunner question type.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2012 .. 2018 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');

qtype_coderunner_testcase::setup_test_sandbox_configuration();

// Special class of exception thrown when the helper is asked to construct
// a CodeRunner question of a type for which no prototype exists.
// This may occur if, say Matlab has been installed in a sandbox but the
// corresponding matlab question types have not been loaded prior to
// phpunit being initialised.
class qtype_coderunner_missing_question_type extends Exception {
}

/**
 * Test helper class for the coderunner question type.
 *
 */
class qtype_coderunner_test_helper extends question_test_helper {
    public function get_test_questions() {
        return ['sqr', 'sqr_pylint', 'printans',
            'hello_func', 'copy_stdin', 'timeout', 'exceptions',
            'sqr_part_marks', 'sqrnoprint',
            'studentanswervar', 'hello_python',
            'generic_python3', 'generic_c',
            'sqr_c', 'sqr_c_single_test', 'sqr_no_semicolons', 'sqr_customised',
            'hello_prog_c', 'copy_stdin_c', 'str_to_upper',
            'sqr_cpp', 'hello_prog_cpp', 'str_to_upper_cpp', 'copy_stdin_cpp',
            'string_delete',
            'sqrmatlab', 'teststudentanswermacro', 'sqroctave',
            'teststudentanswermacrooctave', 'sqrnodejs',
            'sqrjava', 'sqrphp', 'nameclass', 'printsquares', 'printstr',
            'sqr_user_prototype_child',
            'multilang_echo_stdin',
            'demows'];
    }

    /**
     * Makes a coderunner python3 question asking for a sqr() function
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqr($extras = []) {
        return $this->make_coderunner_question_sqr_subtype('python3', $extras);
    }

    public function get_coderunner_question_data_sqr() {
        $qdata = new stdClass();
        test_question_maker::initialise_question_data($qdata);

        $qdata->qtype = 'coderunner';
        $qdata->name = 'BLAH Function to square a number n';
        $qdata->questiontext = 'Write a function sqr(n) that returns n squared';
        $qdata->generalfeedback = 'No feedback available for coderunner questions.';

        $qdata->options = new stdClass();

        $testcases = [
                    ['testcode' => 'print(sqr(0))',
                          'expected' => '0',
                          'mark'     => 1.0],
                    ['testcode' => 'print(sqr(1))',
                          'expected' => '1',
                          'mark'     => 2.0],
                    ['testcode' => 'print(sqr(11))',
                          'expected' => '121',
                          'mark'     => 4.0],
                    ['testcode' => 'print(sqr(-7))',
                          'expected' => '49',
                          'mark'     => 8.0],
                    ['testcode' => 'print(sqr(-6))',
                          'expected' => '36',
                          'display'  => 'HIDE', // The last testcase must be hidden.
                          'mark'     => 16.0],
            ];

        $qdata->options->coderunnertype = 'python3';
        $qdata->options->prototypetype = 0;
        $qdata->options->allornothing = 0;
        $qdata->options->showsource = 0;
        $qdata->options->precheck = 1;
        $qdata->options->hidecheck = 0;
        $qdata->options->answerboxlines = 5;
        $qdata->options->displayfeedback = 1;
        $qdata->options->giveupallowed = 0;
        $qdata->options->extractcodefromjson = 1;
        // Exclude precheck as it defaults to null.
        $qdata->options->useace = 0;
        $qdata->options->penaltyregime = '10, 20, ...';
        // Exclude answer, defaults to null.
        $qdata->options->validateonsave = 1;
        // Ignore a bunch because they default to null.
        $qdata->options->uiplugin = 'none';
        $qdata->options->attachments = 0;
        $qdata->options->attachmentsrequired = 0;
        $qdata->options->maxfilesize = 0;
        $qdata->options->filenamesregex = '';
        $qdata->options->filenamesexplain = '';
        $qdata->options->prototypeextra = '';
        $qdata->options->testcases = self::make_test_cases($testcases);

        return $qdata;
    }


    /**
     * Gets the form data that would come back when the editing form is saved,
     * if you were creating the standard sqr question.
     * @return stdClass the form data.
     */
    public function get_coderunner_question_form_data_sqr() {
        $form = new stdClass();

        $form->coderunnertype = 'python3';
        $form->showsource = 0;
        $form->answerboxlines = 5;
        $form->answerboxcolumns = 100;
        $form->useace = 0;
        $form->precheck = 1;
        $form->hidecheck = 0;
        $form->allornothing = 0;
        $form->penaltyregime = "10, 20, ...";
        $form->templateparams = "";
        $form->hoisttemplateparams = 1;
        $form->extractcodefromjson = 1;
        $form->templateparamslang = 'twig';
        $form->templateparamsevalpertry = 0;
        $form->templateparamsevald = null;
        $form->uiparameters = null;
        $form->twigall = 0;
        $form->prototypetype = 0;
        $form->sandbox = 'DEFAULT';
        $form->language = 'python3';
        $form->acelang = '';
        $form->displayfeedback = 1;
        $form->giveupallowed = 0;
        $form->iscombinatortemplate = 0;
        $form->testsplitterre = '|#<ab@17943918#@>#\n|ms';
        $form->template = "{{ STUDENT_ANSWER }}\n{{ TEST.testcode }}\n";
        $form->name = 'Square function';
        $form->questiontext = ['text' => 'Write a function sqr(n) that returns n squared.', 'format' => FORMAT_HTML];
        $form->defaultmark = 31.0;
        $form->answer = '';
        $form->answerpreload = '';
        $form->globalextra = '';
        $form->generalfeedback = ['text' => 'No feedback available for coderunner questions.', 'format' => FORMAT_HTML];
        $form->testcode = ['print(sqr(0))', 'print(sqr(1))', 'print(sqr(11))', 'print(sqr(-7))', 'print(sqr(-6))'];
        $form->stdin = ['', '', '', '', ''];
        $form->expected = ['0', '1', '121', '49', '36'];
        $form->extra = ['', '', '', '', ''];
        $form->display = ['SHOW', 'SHOW', 'SHOW', 'SHOW', 'HIDE'];
        $form->mark = ['1.0', '2.0', '4.0', '8.0', '16.0'];
        $form->ordering = ['0', '10', '20', '30', '40'];
        $form->sandboxparams = '';
        $form->grader = 'EqualityGrader';
        $form->resultcolumns = '';
        $form->cputimelimitsecs = '';
        $form->memlimitmb = '';
        $form->customise = 1;
        $form->uiplugin = 'none';
        $form->attachments = 0;
        $form->attachmentsrequired = 0;
        $form->maxfilesize = 0;
        $form->filenamesregex = '';
        $form->filenamesexplain = '';
        $form->prototypeextra = '';
        return $form;
    }


    /**
     * Gets the form data that would come back when the editing form is saved,
     * if you were creating a Python3 question with a template that just
     * prints the student answer. This question has no test cases defined.
     * @return stdClass the form data.
     */
    public function get_coderunner_question_form_data_printans() {
        $form = new stdClass();

        $form->coderunnertype = 'python3';
        $form->showsource = 0;
        $form->answerboxlines = 5;
        $form->answerboxcolumns = 100;
        $form->useace = 0;
        $form->precheck = 1;
        $form->hidecheck = 0;
        $form->allornothing = 0;
        $form->penaltyregime = "10, 20, ...";
        $form->templateparams = "";
        $form->hoisttemplateparams = 1;
        $form->extractcodefromjson = 1;
        $form->templateparamslang = 'twig';
        $form->templateparamsevalpertry = 0;
        $form->templateparamsevald = null;
        $form->uiparameters = null;
        $form->twigall = 0;
        $form->prototypetype = 0;
        $form->sandbox = 'DEFAULT';
        $form->language = 'python3';
        $form->acelang = '';
        $form->displayfeedback = 1;
        $form->giveupallowed = 0;
        $form->iscombinatortemplate = 0;
        $form->testsplitterre = '|#<ab@17943918#@>#\n|ms';
        $form->template = "print('{{ STUDENT_ANSWER | e('py')}}\n";
        $form->name = 'Print answer';
        $form->questiontext = ['text' => 'Whatever answer you enter will be printed', 'format' => FORMAT_HTML];
        $form->defaultmark = 1.0;
        $form->generalfeedback = ['text' => 'No feedback available for coderunner questions.', 'format' => FORMAT_HTML];
        $form->testcode = [""];
        $form->stdin = [""];
        $form->expected = ["Fill me in"];
        $form->extra = [""];
        $form->display = ["SHOW"];
        $form->mark = [1];
        $form->ordering = [0];
        $form->sandboxparams = '';
        $form->grader = 'EqualityGrader';
        $form->resultcolumns = '';
        $form->cputimelimitsecs = '';
        $form->memlimitmb = '';
        $form->customise = 1;
        $form->uiplugin = 'none';
        $form->attachments = 0;
        $form->attachmentsrequired = 0;
        $form->maxfilesize = 0;
        $form->filenamesregex = '';
        $form->filenamesexplain = '';
        $form->prototypeextra = '';
        return $form;
    }


    /**
     * Gets the form data that would come back when the editing form is saved,
     * if you were creating a new prototype python3_noace that has Ace disabled.
     *
     * AARGGHHH! Sadly the usual mechanism for loading questions via method named
     * get_{qtype}_question_form_data_{name} doesn't work with CodeRunner/Behat.
     * One should be able to use the Behat step "And the following questions exist"
     * to select individual questions. However the column name 'template' is
     * used to select the question (e.g. python3_noace) but the table data is
     * then passed on to the question definer which proceeds to clobbers the
     * 'template' field of the question. That destroys CodeRunner questions.
     * Design flaw!
     * So this method is useless but is left in as documentation.
     * @return stdClass the form data.
     */
    public function get_coderunner_question_form_data_python3_noace() {
        $form = get_coderunner_question_form_data_sqr();  // Starting point.
        throw new Exception("Got here");
        $form->name = 'PROTOTYPE python3_noace';
        $form->prototypetype = 2;
        $form->typename = 'python3_noace';
        $form->testcode = [];
        $form->stdin = [];
        $form->expected = [];
        $form->extra = [];
        $form->mark = [];
        $form->ordering = [];
        $form->questiontext = ['text' => 'Prototype for a Python3 question that does not use Ace', 'format' => FORMAT_HTML];
        return $form;
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
            [
                    ['expected'  => "Success!\n"],
            ]
        );
    }

    /**
     *  Make a generic C question (should print "Success!")
     */
    public function make_coderunner_question_generic_c() {
        return $this->make_coderunner_question(
            'C_program',
            'GenericName',
            'Generic question',
            [
                    ['expected'  => "Success!\n"],
            ]
        );
    }

    /**
     * Makes a coderunner question asking for a sqr() function.
     * @param $coderunnertype  The type of coderunner function to generate,
     * e.g. 'python3_pylint'.
     * @return qtype_coderunner_question
     */
    private function make_coderunner_question_sqr_subtype($coderunnertype, $extras = []) {
        $coderunner = $this->make_coderunner_question(
            $coderunnertype,
            'Function to square a number n',
            'Write a function sqr(n) that returns n squared',
            [
                    ['testcode' => 'print(sqr(0))',
                          'expected' => '0',
                          'mark'     => 1.0],
                    ['testcode' => 'print(sqr(1))',
                          'expected' => '1',
                          'mark'     => 2.0],
                    ['testcode' => 'print(sqr(11))',
                          'expected' => '121',
                          'mark'     => 4.0],
                    ['testcode' => 'print(sqr(-7))',
                          'expected' => '49',
                          'mark'     => 8.0],
                    ['testcode' => 'print(sqr(-6))',
                          'expected' => '36',
                          'display'  => 'HIDE', // The last testcase must be hidden.
                          'mark'     => 16.0],
            ],
            $extras
        );

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
            [
                    ['testcode' => 'sqr(0)',
                          'expected' => '0',
                          'mark'     => 1.0],
                    ['testcode' => 'sqr(1)',
                          'expected' => '1',
                          'mark'     => 2.0],
                    ['testcode' => 'sqr(11)',
                          'expected' => '121',
                          'mark'     => 4.0],
                    ['testcode' => 'sqr(-7)',
                          'expected' => '49',
                          'mark'     => 8.0],
                    ['testcode' => 'sqr(-6)',
                          'expected' => '36',
                          'display'  => 'HIDE', // The last testcase must be hidden.
                          'mark'     => 16.0],
            ]
        );
        return $coderunner;
    }

    public function make_coderunner_question_sqr_customised() {
        $q = $this->make_coderunner_question_sqr_subtype(
            'python3',
            [
            'template' => "def times(a, b): return a * b\n\n{{STUDENT_ANSWER}}\n\n{{TEST.testcode}}\n",
            'iscombinatortemplate' => false]
        );
        return $q;
    }

    public function make_coderunner_question_sqr_part_marks() {
        // Make a version of the sqr question where testcase[i] carries a
        // mark of i / 2.0 for i in range 1 .. 5.
        $coderunner = $this->make_coderunner_question(
            'python3',
            'Function to square a number n',
            'Write a function sqr(n) that returns n squared',
            [
                    ['testcode' => 'print(sqr(0))',
                          'expected' => '0',
                          'mark'     => 0.5],
                    ['testcode' => 'print(sqr(1))',
                          'expected' => '1',
                          'mark'     => 1.0],
                    ['testcode' => 'print(sqr(11))',
                          'expected' => '121',
                          'mark'     => 1.5],
                    ['testcode' => 'print(sqr(-7))',
                          'expected' => '49',
                          'mark'     => 2.0],
                    ['testcode' => 'print(sqr(-6))',
                          'expected' => '36',
                          'display'  => 'HIDE', // The last testcase must be hidden.
                          'mark'     => 2.5],
            ],
            ['allornothing' => false]
        );
        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that just print 'Hello <name>'
     * This test also tests multiline expressions.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_hello_func() {
        $coderunner = $this->make_coderunner_question(
            'python3',
            'Function to print hello to someone',
            'Write a function sayHello(name) that prints "Hello <name>"',
            [
                    ['testcode' => 'sayHello("")',
                          'expected' => 'Hello '],
                    ['testcode' => 'sayHello("Angus")',
                          'expected' => 'Hello Angus'],
                    ['testcode' => "name = 'Angus'\nsayHello(name)",
                          'expected' => 'Hello Angus'],
                    ['testcode' => "name = \"'Angus'\"\nprint(name)\nsayHello(name)",
                          'expected' => "'Angus'\nHello 'Angus'"],
            ]
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_copy_stdin() {
        $coderunner = $this->make_coderunner_question(
            'python3',
            'Function to copy n lines of stdin to stdout',
            'Write a function copyLines(n) that reads n lines from stdin and writes them to stdout. ',
            [
                    ['testcode' => 'copy_stdin(0)',
                          'expected' => ''],
                    ['testcode' => 'copy_stdin(1)',
                          'stdin'    => "Line1\nLine2\n",
                          'expected' => "Line1\n"],
                    ['testcode' => 'copy_stdin(2)',
                          'stdin'    => "Line1\nLine2\n",
                          'expected' => "Line1\nLine2\n"],
                    ['testcode' => 'copy_stdin(4)',
                        // This example is also designed to test the clean function in
                        // the grader (which should trim white space of the end of
                        // output lines and trim trailing blank lines).
                          'stdin'    => " Line  1  \n   Line   2   \n  \n  \n   ",
                          'expected' => " Line  1\n   Line   2\n"],
                    ['testcode' => 'copy_stdin(3)',
                          'stdin'    => "Line1\nLine2\n",
                          'expected' => "Line1\nLine2\n"], // Irrelevant - runtime error.
            ]
        );

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
            [
                    ['testcode' => 'timeout()'],
            ]
        );

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
            [
                    ['testcode' => 'print(__student_answer__)',
                          'expected'      => "\"\"\"Line1\n\"Line2\"\n'Line3'\nLine4\n\"\"\""],
            ]
        );

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
            'Write a function isOdd(n) that throws a ValueError exception iff n is odd',
            [
                  ['testcode' => 'try:
  checkOdd(91)
  print("No exception")
except ValueError:
  print("Exception")',
                        'expected'      => 'Exception'],
                  ['testcode' => 'for n in [1, 11, 84, 990, 7, 8]:
  try:
     checkOdd(n)
     print("No")
  except ValueError:
     print("Yes")',
                        'expected'      => "Yes\nYes\nNo\nNo\nYes\nNo\n"],
            ]
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a Python3 program that just print 'Hello Python'
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_hello_python() {
        $coderunner = $this->make_coderunner_question(
            'python3',
            'Program to print "Hello Python"',
            'Write a program that prints "Hello Python"',
            [
                    ['testcode' => '',
                          'expected'    => 'Hello Python'],
            ]
        );

        return $coderunner;
    }


    /**
     * Makes a coderunner question that is just to demonstrate the
     * web service (so the actual question is irrelevant - only the
     * description matters).
     */
    public function get_coderunner_question_form_data_demows() {
        $form = new stdClass();

        $form->coderunnertype = 'python3';
        $form->showsource = 0;
        $form->answerboxlines = 5;
        $form->answerboxcolumns = 100;
        $form->useace = 0;
        $form->precheck = 1;
        $form->hidecheck = 0;
        $form->allornothing = 0;
        $form->penaltyregime = "10, 20, ...";
        $form->templateparams = "";
        $form->hoisttemplateparams = 1;
        $form->extractcodefromjson = 1;
        $form->templateparamslang = 'twig';
        $form->templateparamsevalpertry = 0;
        $form->templateparamsevald = null;
        $form->uiparameters = null;
        $form->twigall = 0;
        $form->prototypetype = 0;
        $form->sandbox = 'DEFAULT';
        $form->language = 'python3';
        $form->acelang = '';
        $form->displayfeedback = 1;
        $form->giveupallowed = 0;
        $form->iscombinatortemplate = 0;
        $form->testsplitterre = '|#<ab@17943918#@>#\n|ms';
        $form->template = "print('{{ STUDENT_ANSWER | e('py')}}\n";
        $form->name = 'Demo web service';
        $form->defaultmark = 1.0;
        $form->generalfeedback = ['text' => 'No feedback available for coderunner questions.', 'format' => FORMAT_HTML];
        $form->testcode = [""];
        $form->stdin = [""];
        $form->expected = ["Fill me in"];
        $form->extra = [""];
        $form->display = ["SHOW"];
        $form->mark = [1];
        $form->ordering = [0];
        $form->sandboxparams = '';
        $form->grader = 'EqualityGrader';
        $form->resultcolumns = '';
        $form->cputimelimitsecs = '';
        $form->memlimitmb = '';
        $form->customise = 1;
        $form->uiplugin = 'none';
        $form->attachments = 0;
        $form->attachmentsrequired = 0;
        $form->maxfilesize = 0;
        $form->filenamesregex = '';
        $form->filenamesexplain = '';
        $form->prototypeextra = '';
        $form->questiontext = ['text' => <<<QEND
<button type="button" id="clickme">Click me</button>
<p id="ws-output"></p>
<script>
    var code = 'print("Hello me!")\\nprint("Hello you!")\\n';
    var button = document.getElementById('clickme');
    var output = document.getElementById('ws-output');
    button.onclick = function() {
        require(['core/ajax'], function(ajax) {
            ajax.call([{
                methodname: 'qtype_coderunner_run_in_sandbox',
                args: {
                    contextid: M.cfg.contextid, // Moodle context ID
                    sourcecode: code,
                    language: "python3"
                },
                done: function(responseJson) {
                    output.innerHTML = responseJson;
                },
                fail: function(error) {
                    output.innerHTML = 'ERROR: ' + error.message;
                }
            }]);
        });
    }
</script>
QEND
         , 'format' => FORMAT_HTML];
        return $form;
    }


    /**
     * Makes a coderunner question of type 'sqr_user_prototype_child' to check out
     * inheritance. The prototype (sqr_user_prototype) must have been created
     * before this method can be called.
     */
    public function make_coderunner_question_sqr_user_prototype_child() {
        $coderunner = $this->make_coderunner_question(
            'sqr_user_prototype',
            'Program to test prototype',
            'Answer should (somehow) produce the expected answer below',
            [
                    ['expected'   => "This is data\nLine 2"],
                ],
            ['templateparams' => '{"xxx":1, "zzz":2}']
        );
        return $coderunner;
    }

    /* Now the C-question helper stuff
       =============================== */

    /**
     * Makes a coderunner question asking for a sqr() function.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqr_c() {
        $coderunner = $this->make_coderunner_question(
            'c_function',
            'Function to square a number n',
            'Write a function int sqr(int n) that returns n squared.',
            [
                    ['testcode'       => 'printf("%d", sqr(0));',
                         'expected'        => '0'],
                    ['testcode'       => 'printf("%d", sqr(7));',
                          'expected'       => '49'],
                    ['testcode'       => 'printf("%d", sqr(-11));',
                          'expected'       => '121'],
                    ['testcode'       => 'printf("%d", sqr(-16));',
                          'expected'       => '256'],
            ]
        );

        return $coderunner;
    }

    /**
     * A variant of sqr_c with just a single test. Required for use with
     * the forkbomb test. [Reason is subtle: when running under runguard,
     * output is connected to a pipe, which buffers data in userspace. Forking
     * the process when it has already output some data, results in replicating
     * the buffered data, which is output when the children get closed.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqr_c_single_test() {
        $coderunner = $this->make_coderunner_question(
            'c_function',
            'Function to square a number n',
            'Write a function int sqr(int n) that returns n squared.',
            [
                    ['testcode'       => 'printf("%d", sqr(-11)); fflush(stdout);',
                          'expected'       => '121'],
            ]
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question asking for a sqr() function but without
     * semicolons on the ends of all the printf testcases.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqr_no_semicolons() {
        $coderunner = $this->make_coderunner_question(
            'c_function',
            'Function to square a number n',
            'Write a function int sqr(int n) that returns n squared.',
            [
                    ['testcode'       => 'printf("%d", sqr(0))',
                          'expected'       => '0'],
                    ['testcode'       => 'printf("%d", sqr(7))',
                          'expected'       => '49'],
                    ['testcode'       => 'printf("%d", sqr(-11))',
                          'expected'       => '121'],
                    ['testcode'       => 'printf("%d", sqr(-16))',
                          'expected'       => '256'],
            ]
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a function that just print 'Hello ENCE260'
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_hello_prog_c() {
        $coderunner = $this->make_coderunner_question(
            'c_program',
            'Program to print "Hello ENCE260"',
            'Write a program that prints "Hello ENCE260"',
            [
                    ['testcode' => '',
                          'expected' => 'Hello ENCE260'],
            ]
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question to write a program that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_copy_stdin_c() {
        $coderunner = $this->make_coderunner_question(
            'c_program',
            'Function to copy n lines of stdin to stdout',
            'Write a function copyLines(n) that reads stdin to stdout',
            [
                    ['stdin'    => '',
                          'expected' => ''],
                    ['stdin'    => "Line1\n",
                          'expected' => "Line1\n"],
                    ['stdin'    => "Line1\nLine2\n",
                          'expected' => "Line1\nLine2\n"],
            ]
        );

        return $coderunner;
    }


    public function make_coderunner_question_str_to_upper() {
        $coderunner = $this->make_coderunner_question(
            'c_function',
            'Function to convert string to uppercase',
            'Write a function void str_to_upper(char s[]) that converts s to uppercase',
            [
                    ['testcode' => "
char s[] = {'1','@','a','B','c','d','E',';', 0};
str_to_upper(s);
printf(\"%s\\n\", s);
",
                          'expected' => '1@ABCDE;'],
                    ['testcode' => "
char s[] = {'1','@','A','b','C','D','e',';', 0};
str_to_upper(s);
printf(\"%s\\n\", s);
",
                          'expected'    => '1@ABCDE;'],
            ]
        );

        return $coderunner;
    }



    /**
     * Makes a coderunner question asking for a string_delete() function that
     * deletes from a given string all characters present in another
     * string.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_string_delete() {
        $coderunner = $this->make_coderunner_question(
            'c_function',
            'Function to delete from a source string all chars present in another string',
            'Write a function void string_delete(char *s, const char *charsToDelete) ' .
                'that takes any two C strings as parameters and modifies the ' .
                'string s by deleting from it all characters that are present in charsToDelete.',
            [
                    ['testcode'  => "char s[] = \"abcdefg\";\nstring_delete(s, \"xcaye\");\nprintf(\"%s\\n\", s);",
                          'expected'  => 'bdfg'],
                    ['testcode'  => "char s[] = \"abcdefg\";\nstring_delete(s, \"\");\nprintf(\"%s\\n\", s);",
                          'expected'  => 'abcdefg'],
                    ['testcode'  => "char s[] = \"aaaaabbbbb\";\nstring_delete(s, \"x\");\nprintf(\"%s\\n\", s);",
                          'expected'  => 'aaaaabbbbb'],
            ]
        );

        return $coderunner;
    }

    /* Now the C++-question helper stuff
       =============================== */

    /**
     * Makes a coderunner C++ question asking for a sqr() function.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqr_cpp() {
        $coderunner = $this->make_coderunner_question(
            'cpp_function',
            'Function to square a number n',
            'Write a function int sqr(int n) that returns n squared.',
            [
                    ['testcode'       => 'cout << sqr(0);',
                         'expected'        => '0'],
                    ['testcode'       => 'cout << sqr(7);',
                          'expected'       => '49'],
                    ['testcode'       => 'cout << sqr(-11);',
                          'expected'       => '121'],
                    ['testcode'       => 'cout << sqr(-16);',
                          'expected'       => '256'],
            ]
        );

        return $coderunner;
    }



    /**
     * Makes a coderunner question to write a program that just print 'Hello ENCE260'
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_hello_prog_cpp() {
        $coderunner = $this->make_coderunner_question(
            'cpp_program',
            'Program to print "Hello ENCE260"',
            'Write a program that prints "Hello ENCE260"',
            [
                    ['testcode' => '',
                          'expected' => 'Hello ENCE260'],
            ]
        );

        return $coderunner;
    }

    /**
     * Makes a C++ coderunner question to write a program that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_copy_stdin_cpp() {
        $coderunner = $this->make_coderunner_question(
            'cpp_program',
            'Program to copies stdin to stdout',
            'Write a program that reads stdin to stdout',
            [
                    ['stdin'    => '',
                          'expected' => ''],
                    ['stdin'    => "Line1\n",
                          'expected' => "Line1\n"],
                    ['stdin'    => "Line1\nLine2\n",
                          'expected' => "Line1\nLine2\n"],
            ]
        );

        return $coderunner;
    }


    public function make_coderunner_question_str_to_upper_cpp() {
        $coderunner = $this->make_coderunner_question(
            'cpp_function',
            'Function to convert string to uppercase',
            'Write a function str_to_upper(string s) that converts s to uppercase'
                . 'and returns the ',
            [
                    ['testcode' => "
string s = \"1@aBcdE;\";
cout << str_to_upper(s);
",
                          'expected' => '1@ABCDE;'],
                    ['testcode' => "
string s = \"1@aBcDe;\";
cout << str_to_upper(s);
",
                          'expected'    => '1@ABCDE;'],
            ]
        );

        return $coderunner;
    }


    /* Now the matlab-question helper stuff.
     * =====================================*/

    /**
     * Makes a matlab question asking for a sqr() function.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrmatlab() {
        $coderunner = $this->make_coderunner_question(
            'matlab_function',
            'Function to square a number n',
            'Write a function sqr(n) that returns n squared.',
            [
                ['testcode'       => 'disp(sqr(0));',
                      'expected'       => '     0'],
                ['testcode'       => 'disp(sqr(7));',
                      'expected'       => '    49'],
                ['testcode'       => 'disp(sqr(-11));',
                      'expected'       => '   121'],
                ['testcode'       => 'disp(sqr(-16));',
                     'expected'        => '   256'],
            ]
        );

        return $coderunner;
    }

    /**
     * Makes a coderunner question designed to check if the MATLAB_ESCAPED_STUDENT_ANSWER
     * variable is working and usable within Matlab/Octave
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_teststudentanswermacro() {
        return $this->make_macro_question('matlab_function');
    }

    private function make_macro_question($qtype) {
        $options = [];
        $options['template'] = <<<EOT
function tester()
  ESCAPED_STUDENT_ANSWER =  sprintf('{{MATLAB_ESCAPED_STUDENT_ANSWER}}');
  {{TEST.testcode}};
end

{{STUDENT_ANSWER}}
EOT;
        if ($qtype === 'octave_function') {
            $options['template'] = "1; % Dummy to make a script\n" . $options['template'] . "\ntester()\n";
        }
        $options['iscombinatortemplate'] = false;

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
            [
                ['testcode'       => 'mytest();',
                      'expected'       => "\"Hi!\" he said\n'Hi!' he said"],
                ['testcode'       => 'disp(ESCAPED_STUDENT_ANSWER);',
                      'expected'       => <<<EOT
function mytest()
    s1 = '"Hi!" he said'; % a comment
    s2 = '''Hi!'' he said';
    disp(s1);
    disp(s2);
end
EOT
            ,
                ],
            ],
            $options
        );

        return $coderunner;
    }

    // Now the octave-question helper stuff.
    // ====================================
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
            [
                ['testcode'       => 'disp(sqr(0));',
                      'expected'       => '0'],
                ['testcode'       => 'disp(sqr(7));',
                      'expected'       => '49'],
                ['testcode'       => 'disp(sqr(-11));',
                      'expected'       => '121'],
                ['testcode'       => 'disp(sqr(-16));',
                      'expected'       => '256'],
            ]
        );

        return $coderunner;
    }

    /**
     * Makes an nodejs question asking for a sqr() function.
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrnodejs() {
        $coderunner = $this->make_coderunner_question(
            'nodejs',
            'Function to square a number n',
            'Write a js function sqr(n) that returns n squared.',
            [
                ['testcode'  => 'console.log(sqr(0));',
                      'expected'  => '0'],
                ['testcode'  => 'console.log(sqr(7));',
                      'expected'  => '49'],
                ['testcode'  => 'console.log(sqr(-11));',
                      'expected'  => '121'],
                ['testcode'  => 'console.log(sqr(-16));',
                     'expected'   => '256'],
            ]
        );
        return $coderunner;
    }

    public function make_coderunner_question_teststudentanswermacrooctave() {
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
            [
                    ['testcode'  => 'System.out.println(sqr(0))',
                          'expected'  => '0'],
                    ['testcode'  => 'System.out.println(sqr(7))',
                          'expected'  => '49'],
                    ['testcode'  => 'System.out.println(sqr(-11))',
                          'expected'  => '121'],
                    ['testcode'  => 'System.out.println(sqr(16))',
                          'expected'  => '256'],
            ]
        );

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
            [
                    ['testcode'   => 'System.out.println(new Name("Joe", "Brown"))',
                          'expected'   => 'Joe Brown'],
                    ['testcode'   => 'System.out.println(new Name("a", "b"))',
                          'expected'   => 'a b'],
            ]
        );

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
            [
                    ['stdin'      => "5\n",
                          'expected'   => "1 4 9 16 25\n"],
                    ['stdin'      => "1\n",
                          'expected'   => "1\n"],
            ]
        );
        return $coderunner;
    }

    /**
     *  Make a multilanguage question that echos stdin to stdout.
     */
    public function make_coderunner_question_multilang_echo_stdin() {
        return $this->make_coderunner_question(
            'multilanguage',
            'Multilang Echo',
            'Write a program in your language of choice to echo stdin to stdout',
            [
                    [
                        'stdin'     => "Line1\nLine2",
                        'expected'  => "Line1\nLine2"],
                    ]
        );
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
            [
                  ['testcode' => $code,
                        'stdin'    => "5\n",
                        'expected' => "a0\nb\t\nc\f\nd'This is a string'\n\"So is this\""],
                ],
            ['template' => $template,
                      'iscombinatortemplate' => false]
        );
        return $q;
    }

    /* ============== PHP question support =================================*/

    /**
     * Makes a coderunner question asking for a sqr() function in Php
     * @return qtype_coderunner_question
     */
    public function make_coderunner_question_sqrphp() {
        $coderunner = $this->make_coderunner_question(
            'php',
            'Function to square a number n',
            'Write a function sqr($n) that returns $n squared.',
            [
                    ['testcode'  => 'print(sqr(0))',
                          'expected'  => '0'],
                    ['testcode'  => 'print(sqr(7))',
                          'expected'  => '49'],
                    ['testcode'  => 'print(sqr(-11))',
                          'expected'  => '121'],
                    ['testcode'  => 'print(sqr(16))',
                          'expected'  => '256'],
            ]
        );

        return $coderunner;
    }


    /* ============== SUPPORT METHODS ==================== */

    /* Fill in the option information for a specific question type,
     * by reading it from the database. Can't use questiontype's
     * get_question_options method, as we'd like to, because it requires
     * a form rather than a question and may have a files area with files
     * to upload - too hard to set up :-(
     * However, we use most of the same code by calling set_inherited_fields.
     * The normal get_options method returns all the options in
     * the 'options' field of the object, but CodeRunner then subsequently
     * flattens the options into the question itself. This implementation does
     * both - defining the options object and the flattened version - so the
     * resulting question can be used in the usual CodeRunner context but
     * also in contexts like the question-export test (which expects the options
     * field).
     */
    private function get_options(&$question) {
        global $DB;

        $type = $question->coderunnertype;
        $questiontype = new qtype_coderunner();
        if (
            !$row = $DB->get_record_select(
                'question_coderunner_options',
                "coderunnertype = '$type' and prototypetype != 0"
            )
        ) {
               $error = "TestHelper: failed to load type info for question with type $type";
               throw new qtype_coderunner_missing_question_type($error);
        }

        $question->questionid = $row->questionid;
        $questiontype->set_inherited_fields($question, $row);

        // What follows is a rather horrible hack to support question export
        // testing. Having built the flattened question, we now "unflatten"
        // it back out to the set of options we get from the database.

        $question->options = new StdClass();
        foreach ($question->qtype->extra_question_fields() as $field) {
            if (isset($question->$field)) {
                $question->options->$field = $question->$field;
            } else {
                $question->options->$field = null;
            }
        }

        $question->options->answers = [];  // For compatability with questiontype base.
        $question->options->testcases = $question->testcases;
    }

    // Given an array of tests in which each element has just the bare minimum
    // of info, add in all the other necessary fields to get an array of
    // testcase objects.
    private static function make_test_cases($rawtests) {
        $basictest = ['testtype'           => 0,
                           'testcode'       => '',
                           'stdin'          => '',
                           'extra'          => '',
                           'expected'       => '',
                           'display'        => 'SHOW',
                           'mark'           => 1.0,
                           'hiderestiffail' => 0,
                           'useasexample'   => 0];
        $tests = [];
        foreach ($rawtests as $test) {
            $t = $basictest; // Copy.
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
    private function make_coderunner_question(
        $type,
        $name,
        $questiontext,
        $testcases,
        $otheroptions = []
    ) {
        question_bank::load_question_definition_classes('coderunner');
        $coderunner = new qtype_coderunner_question();
        test_question_maker::initialise_a_question($coderunner);
        $coderunner->qtype = question_bank::get_qtype('coderunner');
        $coderunner->coderunnertype = $type;
        $coderunner->templateparams = '';
        $coderunner->templateparamslang = 'twig';
        $coderunner->templateparamsevalpertry = 0;
        $coderunner->templateparamsevald = null;
        $coderunner->uiparameters = null;
        $coderunner->hoisttemplateparams = 0;
        $coderunner->extractcodefromjson = 1;
        $coderunner->twigall = 0;
        $coderunner->prototypetype = 0;
        $coderunner->name = $name;
        $coderunner->precheck = 0;
        $coderunner->hidecheck = 0;
        $coderunner->questiontext = $questiontext;
        $coderunner->displayfeedback = 1;
        $coderunner->giveupallowed = 0;
        $coderunner->answer = '';
        $coderunner->answerpreload = '';
        $coderunner->globalextra = '';
        $coderunner->allornothing = true;
        $coderunner->student = '';  // Would normally be filled in by question->start_attempt.
        $coderunner->showsource = false;
        $coderunner->generalfeedback = 'No feedback available for coderunner questions.';
        $coderunner->penaltyregime = '10, 20, ...';
        $coderunner->testcases = self::make_test_cases($testcases);
        $coderunner->options = [];
        $coderunner->isnew = true;  // Extra field normally added by save_question.
        $coderunner->context = context_system::instance(); // Use system context for testing.
        $coderunner->attachments = 0;
        $coderunner->attachmentsrequired = 0;
        $coderunner->maxfilesize = 0;
        $coderunner->filenamesregex = '';
        $coderunner->filenamesexplain = '';
        $coderunner->prototypeextra = '';
        $coderunner->parameters = [];  // Normally generated during question initialisation.
        foreach ($otheroptions as $key => $value) {
            $coderunner->$key = $value;
            $coderunner->options[$key] = $value;
        }

        // Filter the question through clean_question_form. This is
        // a hack to allow checking of some of the form processing code without
        // having to use Behat. There's probably a better way, but I don't know it.
        $coderunnertype = new qtype_coderunner();
        $coderunnertype->clean_question_form($coderunner, true);
        $this->get_options($coderunner);
        $coderunner->get_prototype();
        if ($coderunner->prototype === null) {
            debugging("No prototype found in make_coderunner_question");
        }
        return $coderunner;
    }
}
