# CODE RUNNER

Version: 1.5Beta November 2013

Author: Richard Lobb, University of Canterbury, New Zealand.

## Introduction


CodeRunner is a Moodle question type that requests students to submit program code
to some given specification, e.g. a Python function sqr(x) that returns its
parameter squared. The submission is graded by running a series of testcases
of the code in a sandbox, comparing the output with the expected output.
CodeRunner is intended to be run in an adaptive mode, so that students know
immediately if their code is passing the tests. In the typical
'all-or-nothing' mode, all test cases must pass
if the submission is to be awarded any marks. The mark for a set of questions
in a quiz is then determined primarily by which questions the student is able
to  solve successfully and then secondarily by how many submissions the student
makes on each question. However, it is also possible to run CodeRunner questions
in a traditional quiz mode where the mark is determined by how many of the test
cases the code successfully passes.

CodeRunner and its predecessors *pycode* and *ccode* has been in use at the
University of Canterbury for about four years, running tens of thousands of
student quiz submissions in Python, C and Matlab. All laboratory work in the
introductory first year Python programming course, which has around 400 students
in the first semester and 200 in the second, is assessed using CodeRunner
questions. The mid-semester test also uses Moodle/CodeRunner and it is intended to
run the final examination on Moodle/Coderunner in the near future.
The second year C course of around 200 students makes similar use of Coderunner
using C questions and a third year Civil Engineering course, taught in Matlab,
also uses Coderunner extensively.

The system currently supports Python2, Python3, C and Matlab. Java support
is also present but has not yet been used in courses. The architecture allows
easy extension to other languages and one lecturer has made
intermittent use of *clojure* questions.

For security and load reasons, CodeRunner in its present form is not suitable
for installing on an institution-wide Moodle server. Instead, it is recommended
that a special quiz server be set up: essentially just a standard Linux install
plus Moodle, CodeRunner and any extra languages required (e.g. Python3, Java).
A single 4-core server can handle an average quiz question submission rate of
about 30 quiz questions per minute while maintaining a response time of less
than about 3 - 4 seconds, assuming the student code itself runs in a
fraction of a second.

Administrator privileges and some Unix skills are needed to install Coderunner.


## Installation


CodeRunner requires Moodle version 2.5 or later.

There are three stages to installation:

1. Installing the CodeRunner module itself.

1. Installing the Liu sandbox if you're planning on running C on the Moodle
server (not strictly necessary but strongly
recommended) to provide more security than Runguard or much better
performance than Ideone.

1. Configuring the system for the particular sandbox(es) and languages
your installation supports.


### Installing CodeRunner

CodeRunner should be installed in the `<moodlehome>/local` directory as follows.

    cd <moodlehome>/local
    git clone https://github.com/trampgeek/CodeRunner.git
    cd CodeRunner
    sudo ./install

The install script sets up symbolic links from the `question/type` and
`question/behaviour` directories to corresponding CodeRunner directories; you
must have configured the webserver to follow symbolic links for this to work.
It also creates a new user called *coderunner* on the system; when using
the *RunguardSandbox* (see below), tests are run with the user ID set
to the coderunner user to minimise the exposure of sensitive web-server
information. The install script may prompt for details like the office and phone
number of the coderunner user -- just hit enter to accept the defaults.
The switch to the coderunner user and the controlled execution of the
submitted program in *RunguardSandbox* is done by a program `runguard`, written
by Jaap Eldering as part of
the programming contest server [DOMJudge](http://domjudge.sourceforge.net/). This
program needs to be 'setuid root', and hence the install script requires
root permissions to set this up.

All going well, you should finish up with a user 'coderunner', albeit one without
a home directory, and symbolic links from within the `<moodlehome>/question/type`
and `<moodlehome>/question/behaviour` directories to the `<moodlehome>/local/CodeRunner/CodeRunner`
and `<moodlehome>/local/CodeRunner/adaptive_adapted_for_coderunner` directories
respectively. There should also be a symbolic link from `local/Twig` to
`local/CodeRunner/Twig`. These can all be set up by hand if desired but read the
install script to see exactly what was expected.

### Installing the Liu sandbox

This step can be skipped if you're not planning on running C, or if you're
happy to use the default Runguard Sandbox for C programs.

The recommended main sandbox for running C is the Liu sandbox. It can be obtained
from [here](http://sourceforge.net/projects/libsandbox/).  Both the binary and the
Python2 interface need to be installed. Note that CodeRunner does *not*
currently work with the Python3 interface to the sandbox.

The easiest way to install the Liu sandbox is by
downloading appropriate `.deb`s or `.rpm`s of both `libsandbox` and `pysandbox` (for
Python version 2). Note that the `pysandbox` download must be the one appropriate
to the installed  version of Python2 (currently typically 2.6 on RHEL systems
or 2.7 on most other flavours of Linux) *regardless of whether or not you
intend to support Python3 as a programming language for submissions*.


### Sandbox Configuration

The last step in installation involves configuring the sandboxes appropriately
for your particular environment. You can set which sandboxes you wish to use,
and other sandbox parameters, via the Moodle administrator settings for the
CodeRunner plugin, accessed via *Site administration > Plugins > Plugins overview*.

By default, only the *RunguardSandbox* is  enabled. If you have installed
the LiuSandbox as described above, you will need to enable it and configure
the username and password via the
administrator settings. You can also enable the use of the
[Ideone compute server](http://ideone.com) and configure the username and password for it, via the
administrator settings. Some notes on the different sandbox options follow.

 1. The RunguardSandbox. This can be used to run all the supported languages
locally on the Moodle server itself.
Assuming the install script successfully created the
user *coderunner* and set the *runguard* program to run as root,
the RunguardSandbox is reasonably safe,
in that it controls memory usage and execution time and limits file access to
those parts of the file system visible to all users. However, it does not
prevent use of system calls like *socket* that might open connections to
other servers behind your firewall and of course it depends on the Unix
server being securely set up in the first place. There are also potential
problems with controlling fork bombs and/or testing of heavily multithreaded
languages or student submissions. That being said, our own quiz server has
been making extensive use of the RunguardSandbox for two years and only
once had a problem when multiple Python submissions attempted to run
the Java Virtual Machine (heavily multithreaded), for which the process limit
previously set for Python was inadequate. That limit has since been multiplied
by 10. To use only the RunguardSandbox, change the file
`CodeRunner/coderunner/Sandbox/sandbox_config.php`
to list only `runguardsandbox` as an option.

 1. The IdeoneSandbox. ideone.com is a compute server that runs
programs submitted either through a browser or through a web-services API in
a huge number of different languages. This is not recommended for production
use, as execution turn-around time is frequently too large (from 10 seconds
to a minute or more) to give a tolerable user experience. An
[Ideone account](http://ideone.com/account/register)
(username and password) is required to access
the Ideone web-services. Runs are free up to a certain number
but you then have to pay for usage.
The IdeoneSandbox is there mainly as a proof of concept of the idea of off-line
execution and to support occasional use of unusual languages.

 1. If you are using the LiuSandbox for running C questions, the C compiler must
must be installed, with the capability to compile and
link statically (no longer part of the default RedHat installation).

### Running the unit tests

If your Moodle installation includes the
*phpunit* system for testing Moodle modules, you might wish to test the
CodeRunner installation. However, unless you are planning on running
Matlab you should first move or remove the file

        <moodlehome>/local/coderunner/Coderunner/tests/matlabquestions_test.php

You should then be able to run the tests with

        cd <moodlehome>
        sudo php admin/tool/phpunit/cli/init.php
        sudo phpunit --testsuite="qtype_coderunner test suite"

Please [email me](mailto:richard.lobb@canterbury.ac.nz) if you have problems
with the installation.


## Question types

CodeRunner support a wide variety of question types and can easily be
extended to support lots more. The file `db/upgrade.php` installs a set of
standard language and question types into the data base. Each question type
defines a programming language, a couple of templates (see the next section),
a preferred sandbox (normally left blank so that the best one available can
be used) and a preferred grader. The latter is also normally blank as the
default
*EqualityGrader* is usually sufficient - it just compares the actual
program output with the expected output and requires an exact match for a
pass, after trailing blank lines and trailing white space on lines has been
removed. An alternative regular expression grader, *RegexGrader*, is available
as an alternative; it isn't used by any of the base question types but can
easily be selected on a per-question basic using the customisation capabilities.
See the next section.

The current set of question types (each of which can be customised by
editing its template and other parameters, as explained in the next section) is
as follows. All
question types currently use the RunguardSandbox except for C
questions, which use the LiuSandbox, if that's installed, or the RunguardSandbox
otherwise.

 1. **python3**. Used for most Python3 questions. For each test case, the student
code is run first, followed by the sequence of tests.

 1. **python2**. Used for most Python2 questions. For each test case, the student
code is run first, e followed by the sequence of tests. This question type
should be considered to be
obsolescent due to the widespread move to Python3 through the education
community.

 1. **python3\_pylint\_func**. This is a special type developed for use in the
University of Canterbury. The student submission is prefixed by a dummy module
docstring and the code is passed through the [pylint](http://www.logilab.org/857)
source code analyser. The submission is rejected if pylint gives any errors,
otherwise testing proceeds as normal. Obviously, pylint needs to be installed
and appropriately configured for this question type to be usable.

 1. **python3\_pylint\_prog**. This is identical to the previous type except that no
dummy docstring is added at the top as the submission is expected to be a
stand-alone program.

 1. **c\_function**. Used for C write-a-function questions where the student supplies
 just a function (plus possible support functions) and each test is (typically) of the form

        printf(format_string, func(arg1, arg2, ..))

 The template for this question type generates some standard includes, followed
 by the student code followed by a main function that executes the tests one by
 one.

 All C question types use the gcc compiler with the language set to
 accept C99 and with both *-Wall* and *-Werror* options set on the command line
 to issue all warnings and reject the code if there are any warnings.
 C++ isn't built in as present, as we don't teach it, but changing C question
 to support C++ is mainly just a matter of changing the
 compile command line, viz., the line "$cmd = ..." in the *compile* methods of
 the  *C\_Task* classes in runguardsandboxtasks.php and liusandboxtasks.php.
 You will probably
 also wish to change the C question type templates a bit, e.g. to include
 *iostream* instead of, or as well as, *stdio.h* by default. The line

        using namespace std;

 may also be desirable.

 1. **c\_program**. Used for C write-a-program questions where the student supplies
 a complete program and the tests simply run this program with supplied standard
 input.

 1. **c\_full\_main_tests**. This is a rarely used special question type where
students write global declarations (types, functions etc) and each test is a
complete C main function that uses the student-supplied declarations.

 1. **matlab_function**. This is the only supported matlab question type and isn't
really intended for general use outside the University of Canterbury. It assumes
matlab is installed on the server and can be run with the shell command
"/usr/local/bin/matlab_exec_cli".
A ".m" test file is built that contains a main test function, which executes
all the supplied test cases, followed by the student code which must be in the
form of one or more function declarations. That .m file is executed by Matlab,
various Matlab-generated noise is filtered, and the output must match that
specified for the test cases.

 1. **java_method**. This is intended for early Java teaching where students are
still learning to write individual methods. The student code is a single method,
plus possible support methods, that is wrapped in a class together with a
static main method containing the supplied tests (which will generally call the
student's method and print the results).

 1. **java_class**. Here the student writes an entire class (or possibly
multiple classes), which must *not* be
public. The test cases are then wrapped in the main method for a separate
public test class which is added to the students class and the whole is then
executed.

 1. **java_program**. Here the students writes a complete program which is compiled
then executed once for each test case to see if it generates the expected output
for that test.

As discussed in the following sections, this base set of question types can
be customised in various ways. The

## Templates

Templates are the key to understanding how a submission is tested. There are in
general two templates per question type - a *combinator_template* and a
*per_test_template* but we'll ignore the former for now and focus on the latter.

The *per_test_template* for each question type defines how a program is built from the
student's code and one particular testcase. That program is compiled (if necessary)
and run with the standard input defined in that testcase, and the output must
then match the expected output for the testcase (where 'match' is defined
by the chosen validator, but only the basic equality-match validator is
currently supplied).

The question type template is processed by a template engine called
[Twig](http://twig.sensiolabs.org/), which is passed a variable called
STUDENT\_ANSWER, which is the text that the student entered into the answer box
and another called TEST, which is a record containing the information
that the question author enters
for the particular test. The template will typically use just the TEST.testcode
field, which is the "test" field of the testcase, and usually (but not always)
is a bit of code to be run to test the student's answer. As an example,
the question type *c_function*, which asks students to write a C function,
looks like:

        #include <stdio.h>
        #include <stdlib.h>
        #include <ctype.h>

        {{ STUDENT_ANSWER }}

        int main() {
            {{ TEST.testcode }};
            return 0;
        }

A typical test (i.e. `TEST.testcode`) for a question asking students to write a
function that
returns the square of its parameter might be:

        printf("%d\n", sqr(-9))

with the expected output of 81.

When authoring a question you can inspect the template for your chosen
question type by temporarily checking the 'Customise' checkbox.

As mentioned earlier, there are actually two templates for each question
type. For efficiency, CodeRunner first tries
to combine all testcases into a single compile-and-execute run using the second
template, called the `combinator_template`. There is a combinator
template for most
question type, except for questions that require students
to write a whole program. However, the combinator template is not used during
testing if standard input is supplied for any of the tests; each test
is then assumed to be independent of the others, with its own input. Also,
if an exception occurs at runtime when a combinator template is being used,
the tester retries all test cases individually using the per-test-case
template so that the student gets presented with all results up to the point
at which the exception occurred.

Because combinator templates are complicated, they are not exposed via
the authorship GUI. If you wish to use them (and the only reason would be
to gain efficiency in questions of a type not currently supported) you will
need to edit `upgrade.php`, set a new plug-in version number, and run the
administrator plug-in update procedure.

As mentioned above, the `per_test_template` can be edited by the question
author for special needs, e.g. if you wish to provide skeleton code to the
students. As a simple example, if you wanted students to provide the missing
line in a C function that returns the square of its parameter, you could use
a template like:

        #include <stdio.h>
        #include <stdlib.h>
        #include <ctype.h>

        int sqr(int n) {
           {{ STUDENT_ANSWER }}
        }

        int main() {
            {{ TEST.testcode }};
            return 0;
        }

Obviously the question text for such a question would need to make it clear
to students what context their code appears in.

Note that if you customise a question type in this way you lose the
efficiency gain that the combinator template offers, although this is probably
not much of a problem unless you have a large number of testcases.

## Advanced template use

It may not be obvious from the above that the template mechanism allows
for almost any sort of question where the answer can be evaluated by a computer.
In all the examples given so far, the student's code is executed as part of
the test process but in fact there's no need for this to happen. The student's
answer can be treated as data by the template code, which can then execute
various tests on that data to determine its correctness. The Python *pylint*
question types given earlier are a simple example: the template code first
writes the student's code to a file and runs *pylint* over that file before
proceeding with any tests. The per-test template for this question type (which must
run in the RunguardSandbox) is:

    __student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

    import subprocess

    def check_code(s):
        try:
            source = open('source.py', 'w')
            source.write(s)
            source.close()
            result = subprocess.check_output(['pylint', 'source.py'], stderr=subprocess.STDOUT)
        except Exception as e:
            result = e.output.decode('utf-8')

        if result.strip():
            print("pylint doesn't approve of your program")
            print(result)
            raise Exception("Submission rejected")

    check_code(__student_answer__)

    {{ STUDENT_ANSWER }}
    {{ TEST.testcode }}

The Twig syntax {{ STUDENT_ANSWER | e('py') }} results in the student's submission
being filtered by a Python escape function that escapes all
all double quote and backslash characters with an added backslash.
Note that in the event of a failure an exception is raised; this ensures that
further testing is aborted so that the student doesn't receive the same error
for every test case. [As noted above, the tester aborts the testing sequence
when using the per-test-case template if an exception occurs.]

Some other more complex examples that we've
used in practice include:

 1. A matlab question in which the template code (also matlab) breaks down
    the student's code into functions, checking the length of each to make
    sure it's not too long, before proceeding with marking.

 1. A python question where the student's code is actually a compiler for
    a simple language. The template code runs the student's compiler,
    passes its output through an assembler that generates a JVM class file,
    then runs that class with the JVM to check its correctness.

 1. A python question where the students submission isn't code at all, but
    is a textual description of a Finite State Automaton for a given transition
    diagram; the template code evaluates the correctness of the supplied
    automaton.


## Grading with templates

Using just the template mechanism described above it is possible to write
almost arbitrarily complex questions. Grading of student submissions can,
however, be problematic in some situations. For example, you may need to
ask a question where many different answers are possible, and the
correctness can only be assessed by a special testing program. Or
you may wish to subject
a student's code to a very large
number of tests and award a mark according to how many of the test cases
it can handle. The usual exact-match
grader cannot handle these situations. For such cases the "template does
grading" checkbox can be used.

When the 'template does grading' checkbox is checked
the output from the run is not passed to the grader but is taken as the
grading result. The output from the template-generated program must now
be a JSON-encoded object (such as a dictionary, in Python) containing
at least a 'fraction' field, which is multiplied by TEST.mark to decide how
many marks the test case is awarded. It should usually also contain a 'got'
field, which is the value displayed in the 'Got' column of the results table.
The other columns of the results table (testcode, stdin, expected) can also
be defined by the custom grader and will be used instead of the values from
the testcase. As an example, if the output of the program is the string

    {"fraction":0.5, "got": "Half the answers were right!"}

half marks would be
given for that particular test case and the 'Got' column would display the
text "Half the answers were right!".

Writing a grading template that executes the student's code is, however,
rather difficult as the generated program needs to be robust against errors
in the submitted code.

##An advanced grading-template example
As an example of the use of a custom grader, consider the following question:

"Write a function *best_pair(nums)* that takes a list of 2 or more integers and
returns any two elements from the list such that the absolute value of the
difference between the two elements is less than or equal to the difference
between all other pairs of elements. An element from the input list can appear
only once in the output pair unless the element is repeated in the input too.

"For example *best_pair([-100, 20, 95, 11, -8, 1])* should return one of the pairs
(11, 20), (20,11), (-8, 1) or (1, -8)."

The following template, with the "template does grading" checkbox checked,
could be used as a grader for this question.

    import json
    import sys

    def sample_solution(nums):
        best = (nums[0], nums[1])
        for i in range(len(nums) - 1):
            for j in range(i + 1, len(nums)):
                if abs(nums[i] - nums[j]) < abs(best[0] - best[1]):
                    best = (nums[i], nums[j])
        return best

    def is_valid(response, nums):
        if not isinstance(response, tuple) or len(response) != 2:
            return False
        if response[0] not in nums or response[1] not in nums:
            return False
        if response[0] == response[1] and nums.count(response[0]) < 2:
            return False
        return True

    nums = {{TEST.testcode}}
    my_soln = sample_solution(nums)
    my_diff = abs(my_soln[0] - my_soln[1])
    expected = 'Pair with difference of ' + str(my_diff) + '\ne.g. ' + str(my_soln)
    saved_stdout = sys.stdout
    saved_stderr = sys.stderr
    sys.stdout = sys.stderr = open('__prog_out__', 'w')

    try:
        exec("""{{STUDENT_ANSWER | e('py')}}""")
        candidate = best_pair(nums[:])
        sys.stdout.close()
        prog_output = open('__prog_out__').read()

        if prog_output != '':
            grading = {
                'fraction':0.0,
                'got': 'Unexpected output from your code : ' + prog_output
            }
        elif not is_valid(candidate, nums):
            grading = {'fraction':0.0, 'got': 'Invalid response: ' + str(candidate)}
        else:
            got = str(candidate)
            got_diff = abs(candidate[0] - candidate[1])
            if got_diff == my_diff:
                mark = 1.0
            else:
                mark = 0.0
                got += '\n(difference of {})'.format(got_diff)
            grading = {'fraction': mark, 'got': got}
    except Exception as e:
        grading = {'fraction':0, 'got': '*** Exception occurred ***\n' + str(e)}

    sys.stdout = saved_stdout
    sys.stderr = saved_stderr
    grading['expected'] = expected
    print(json.dumps(grading))

It is assumed that the "testcases" for this question are just Python lists of
ints, e.g. with 'testcode' like '[10, 6, -11, 21, 3, 4]'. The other testcase
fields (stdin and expected) are unused, as the grading program computes the
expected result.

If the student responds with the plausible but wrong response:

    def best_pair(nums):
        best = (nums[0], nums[1])
        for n1 in nums:
            nums.remove(n1)
            for n2 in nums:
                if abs(n1 - n2) < abs(best[0] - best[1]):
                    best = (n1, n2)
        return best


then, assuming all-or-nothing grading, their result table looks like:

![wrong answer image](wrongAnswerToGraderExample.png)

Note that the "Expected" column contains a customised message, not just a field
from the testcase, and that alternative answers are accepted in the 'Got'
column if they have the required absolute difference.

If instead, the student submits a correct answer,
such as,

    def best_pair(nums):
        best = (nums[0], nums[1])
        for i in range(len(nums) - 1):
            for j in range(i + 1, len(nums)):
                if abs(nums[i] - nums[j]) <= abs(best[0] - best[1]):
                    best = (nums[i], nums[j])
        return best

then they get the following:

![right answer image](rightAnswerToGraderExample.png)

It may be noted that writing questions using custom graders is much harder than
using the normal built-in equality based grader. The above question would have
been much simpler if it had been posed in a way that allowed no ambiguity in the
output. For example, it could have asked simply for the absolute difference
between the elements of the pair or specified unambiguously which pair to
return and the order of the elements of the pair. In that case, no custom grader
would have been required, nor even a custom template.


##How programming quizzes should work

Historical notes and a diatribe on the use of Adaptive Mode questions ...

The original pycode was inspired by [CodingBat](http://codingbat.com), a site where
students submit Python or Java code that implements a simple function or
method, e.g. a function that returns twice the square of its parameter plus 1.
The student code is executed with a series of tests cases and results are
displayed immediately after submission in a simple tabular form showing each
test case, expected answer
and actual answer. Rows where the answer computed by the student's code
is correct receive a large green tick; incorrect rows
receive a large red cross. The code is deemed correct only if all tests
are ticked. If code is incorrect, students can simply correct it and resubmit.

*CodingBat* proves extraordinarily effective as a student training site. Even
experienced programmers receive pleasure from the column of green ticks and
all students are highly motivated to fix their code and retry if it fails one or more
tests. Some key attributes of this success, to be incorporated into *pycode*,
were:

1. Instant feedback. The student pastes their code into the site, clicks
*submit*, and almost immediately receives back their results.

1. All-or-nothing correctness. If the student's code fails any test, it is
wrong. Essentially (thinking in a quiz context) it earns zero marks. Code
has to pass *all* tests to be deemed mark-worthy.

1. Simplicity. The question statement should be simple. The solution should
also be reasonably simple. The display of results is simple and the student
knows immediately what test cases failed. There are no complex regular-expression
failures for the students to puzzle over nor uncertainties over what the
test data was.

1. Rewarding green ticks. As noted above, the colour and display of a correct
results table is highly satisfying and a strong motivation to succeed.

The first two of these requirements are particularly critical. While they can
be accommodated within Moodle by using an *adaptive* quiz behaviour
in conjunction with an all-or-nothing marking scheme, they are not
how many people view a Moodle quiz. Quizzes are
commonly marked only after submission of all questions, and there is usually
a perception that part marks will be awarded for "partially correct" answers.
However, awarding marks to a piece of code according to how many test cases
it passes can give almost meaningless results. For example, a function that
always just returns 0, or the empty list or equivalent, will usually pass several
of the tests, but surely it shouldn't be given *any* marks? Seriously flawed
code, for example a string tokenizing function that works only with alphabetic
data, may get well over half marks if the question-setter was not expecting
such flaws.

Accordingly, a key assumption underlying CodeRunner is that quizzes will always
run in Moodle's adaptive mode, which displays results after each question
is submitted, and allows resubmission for a penalty. The mark obtained in a
programming-style quiz is thus determined by how many of the problems the
student can solve in the given time, and how many submissions the student
needs to make on each question.

