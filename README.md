# CODE RUNNER

Version: 1.0Beta June 2013

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

CodeRunner is a derivative of a Python-specific plug-in called *pycode*, which
has been used in four first-year university classes of several hundred students for two years,
and *ccode*, a C version of pycode, which was used in a second-year C programming
course of 200 students. The new version is designed to support multiple
languages and question types within a single plug-in. Currently Python (versions
2 and 3), C, Java and Matlab are all supported, but the architecture is
sufficiently general to accommodate additional languages with very little
extra code.

This current version, Coderunner V1.0 beta, has been used extensively 
over one semester at the University of Canterbury, New Zealand. The various
Python quiz questions have been used with a class of over 400 students who
have made many tens of thousands of submissions in quizzes, assignments and
in their mid-semester test. Matlab questions were used in a similar manner
in another course of around 180 students with many thousands of submissions.
C questions have not yet received much use -- the C course starts in a few
weeks -- but there is very little code that hasn't been extensively exercised
either by the Python and Matlab tests or by the *ccode* plug-in last year.
Of the currently built-in question types, only the Java questions have not
been heavily exercised: they should be regarded as alpha-level (though again
most of the code is common to the other question types).

Administrator privileges and some Unix skills are needed to install Coderunner.


## Installation


CodeRunner requires Moodle version 2.1 or later. Furthermore, to run the testsuite,
a Moodle version >= 2.3 is required, since the tests have been changed from
using SimpleTest to PHPUnit, as required by version 2.3.

There are three stages to installation:

1. Installing the CodeRunner module itself.

1. Installing an additional sandbox (not strictly necessary but strongly
recommended) to provide more security .

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
the so-called *NullSandbox* (see below), tests are run with the user ID set
to the coderunner user to minimise the exposure of sensitive web-server
information. The install script may prompt for details like the office and phone
number of the coderunner user -- just hit enter to accept the defaults.
The switch to the coderunner user and the controlled execution of the
submitted program in *NullSandbox* is done by a program `runguard`, taken
from the programming contest server [DOMJudge](http://domjudge.sourceforge.net/). This
program needs to be 'setuid root', and hence the install script requires
root permissions to set this up.

All going well, you should finish up with a user 'coderunner'
, albeit one without
a home directory, and symbolic links from within the `<moodlehome>/question/type`
and `<moodlehome>/question/behaviour` directories to the `<moodlehome>/local/CodeRunner/CodeRunner`
and `<moodlehome>/local/CodeRunner/adaptive_adapted_for_coderunner` directories
respectively. There should also be a symbolic link from `local/Twig` to
`local/CodeRunner/Twig`. These can all be set up by hand if desired but read the
install script to see exactly what was expected.

### Installing the Liu sandbox

The recommended main sandbox for running Python and C is the
Liu sandbox. It can be obtained from
[here](http://sourceforge.net/projects/libsandbox/).  Both the binary and the
Python2 interface need to be installed. Note that CodeRunner does *not*
currently work with the Python3 interface to the sandbox, though it is
quite possible to run Python3 within the sandbox.

The easiest way to install the Liu sandbox is by
downloading appropriate `.deb`s or `.rpm`s of both `libsandbox` and `pysandbox` (for
Python version 2). Note that the `pysandbox` download must be the one appropriate
to the installed  version of Python2 (currently typically 2.6 on RHEL systems
or 2.7 on most other flavours of Linux) *regardless of whether or not you
intend to support Python3 as a programming language for submissions*.

### Configuration

The last step in installation involves configuring the sandboxes appropriately
for your particular environment.

  1. If you haven't succeeded in installing the LiuSandbox correctly, you
can try running all your code via *runguard* in the so-called *NullSandbox*.
Assuming the install script successfully created the user *coderunner* and
set the *runguard* program to run as root, the nullsandbox is reasonably safe,
in that it limits execution time resource usage and limits file access to
those parts of the file system visible to all users. However, it does not
prevent use of system calls like *socket* that might open connections to
other servers behind your firewall and of course it depends on the Unix
server being securely set up in the first place. There are also potential
problems with controlling fork bombs and/or testing of heavily multithreaded
languages or student submissions. That being said, our own quiz server has
been making extensive use of the NullSandbox for a full semester and the only
problem that occurred was when multiple Python submissions attempted to run
the Java Virtual Machine (heavily multithreaded), for which the process limit
previously set for Python was inadequate. That limit has since been multiplied
by 10, but this is not a satisfactory long-term solution.

    To use only the NullSandbox, change the file `CodeRunner/coderunner/Sandbox/sandbox_config.php`
to list only `nullsandbox` as an option.

  2. If you are using the LiuSandbox for Python and C, the supplied
`sandbox_config.php` should be correct. Obviously the appropriate languages
must be installed including, in the case of C, the capability to compile and
link statically (no longer part of the default RedHat installation).
However, some configuration of the
file `liusandbox.php` may be required. Try it first 'out of the box', but
if, when running a submitted quiz question, you get 'Illegal function call'
due to opening an inaccessible file, you will have to configure
the various `LanguageTask` subclasses so that each one returns a suitable
list of accessible subtrees when its `readableDirs()` method is called.
Some experimentation may be needed and/or use of the Linux `strace` command
to determine what bits of the file system your installed Python (say) requires
access to. This should not be necessary with C, as the C programs are compiled
outside the sandbox and statically linking so shouldn't need to access
*any* bits of the file system at runtime (unless you wish to allow this,
of course).

If you're running a Moodle version >=2.3, and have installed the
*phpunit* system for testing, you might wish to test the
CodeRunner installation with phpunit. However, unless you are planning on running
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
be used) and a preferred validator. The latter is also normally blank as the
default so-called
*BasicValidator* is the only one currently available - it just compares the actual
program output with the expected output and requires an exact match for a
pass, after trailing blank lines and trailing white space on lines has been
removed. An alternative regular expression match could easily be added but I
haven't felt the need for it yet - I prefer students to get answers exactly
right, not roughly right.

The current set of question types (each of which can be customised by
editing its template, as explained in the next section) is:

 1. **python3**. Used for most Python3 questions. For each test case, the student
code is run first, e followed by the sequence of tests.

 1. **python2**. Used for most Python2 questions. For each test case, the student
code is run first, e followed by the sequence of tests.

 1. **python3\_pylint\_func**. This is a special type developed for use in the
University of Canterbury. The student submission is prefixed by a dummy module
docstring and the code is passed through the [pylint](http://www.logilab.org/857)
source code analyser. The submission is rejected if pylint gives any errors,
otherwise testing proceeds as normal. Obviously, pylint needs to be installed
and appropriately configured for this question type to be usable. Note, too,
that this type of question runs in the so-called NullSandbox, rather than in the
Liu sandbox, in order to allow execution of an external program during testing.

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
 C++ isn't build in as present, as we don't teach it, but changing C question
 to support C++ is mainly just a matter of changing the
 compile command line, viz., the line "$cmd = ..." in the *compile* methods of
 *C\_ns\_Task* in nullsandbox.php and *C_Task* in liusandbox.php. You will probably
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
matlab is installed on the server at the path "/usr/local/Matlab2012a/bin/glnxa64/MATLAB".
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
STUDENT\_ANSWER and another called TEST.testcode that the question author enters
for the particular test. As an example,
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

A typical test (i.e. `TEST.testcode`) for a question asking students to write a function that
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
administator plug-in update procedure.

As mentioned above, the `per_test_template` can be edited by the question
author for special needs, e.g. if you wish to provide skeleton code to the
students. As a simple example, if you wanted students to fill in the missing
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
run in the NullSandbox) is:

    __student_answer__ = """{{ ESCAPED_STUDENT_ANSWER }}"""

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

The template variable ESCAPED\_STUDENT\_ANSWER is the student's submission with
all double quote and backslash characters escaped with an added backslash.
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

##A note on `VBSandbox`

This section can be ignored by almost everyone. It's of relevance only
to a developer who may be looking to run CodeRunner on a non-virtualised
server and who wants a sandbox that's more secure than DomJudge's *runguard*
environment (which is used by the somewhat unfairly-named *NullSandbox*)
but is for some reason, such as the need to support a language that requires
multithreading, is unable to use the Liu sandbox. It is "work in progress",
not production code.

CodeRunner is designed with a pluggable sandbox architecture. The two sandboxes
currently provided are those mentioned above: LiuSandbox and NullSandbox. A
sandbox that uses the [ideone.com](http://ideone.com) server, which supports
a huge range of programming languages, is also planned.

If you inspect the code you'll see code for another sandbox, VbSandbox,
which runs code in am Oracle VirtualBox virtual machine
within the host. To test a student's submission, a virtual box VM is started
if it's not already running. Then VBoxManage commands are used to add the
file(s) for testing to the VM's file system and then to execute the code. This
is very secure as the VM is a self-contained sandbox with no access to the host
resources.

This sandbox was originally intended for use with MatLab, which won't run in
the LiuSandbox as it is multithreaded and very heavy on system calls, leading
to performance issues. The code has been developed and debugged up to the point
where it was ready for production testing, but when it was moved onto the
production server -- a vmware Virtual Machine -- it was discovered that
VirtualBox will not run on another VM. Thus, the VirtualBox sandbox is usable
only on real host, not on a virtualised host. This isn't very useful in our
environment, so further development of the VirtualBox sandbox has been
discontinued. However, the code has been left in the system in case it is
needed by other users or even by ourselves in the future. Some notes on
installing the VirtualBox sandbox follow.

Installing the VirtualBox sandbox is somewhat complicated. The following gives
just the general idea:

1. Install VirtualBox on the server, including the GuestAdditions.
1. Add the web server user (www-data on Ubuntu) to the group vboxusers so that
   the web server is able to manage the VirtualBox VM(s).
1. Log in to the Moodle server as the user www-data (or whoever),
   e.g. using 'ssh -Y www-data@localhost'
1. Use VirtualBox to create an Ubuntu server virtual machine (no GUI) called
   LinuxSandbox.
1. Set up a user 'sandbox' with password 'LinuxSandbox' on that new VM
1. Copy the file <moodlehome>/local/CodeRunner/coderunner/Sandbox/vbrunner into
   that user's home directory. Make it executable.
1. Install and configure any languages you wish to use in the sandbox. Python2
   is the only one that will run "out of the box".
1. Add to the file vbsandbox.php a class Lang_VbTask for each Language 'Lang'
   you need to support. Use the existing Python2_VbTask and Matlab_VbTask classes
   as templates.
1. If necessary, add appropriate question-type entries to db/update.php.

Note that when testing the virtualbox sandbox, with code vbsandbox_test.php,
you must be logged in as the web-server user, i.e. www-data (Ubuntu) or apache
(Red Hat, Fedora etc).
