# CODE RUNNER

@version 0 December 2012
@author Richard Lobb, University of Canterbury, New Zealand.

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


## Installation


CodeRunner requires Moodle version 2.1 or later. Furthermore, to run the testsuite,
a Moodle version >= 2.3 is required, since the tests have been changed from
using SimpleTest to PHPUnit, as required by version 2.3.

There are three stages to installation:

1. Installing the CodeRunner module itself.

1. Installing a least one additional sandbox (not strictly essential but strongly
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
server being securely set up in the first place.

    To use only the NullSandbox, change the file `CodeRunner/coderunner/Sandbox/sandbox_config.php`
to list only `nullsandbox` as an option.

  2. If you are using the LiuSandbox for Python and C, the supplied
`sandbox_config.php` should be correct. However, some configuration of the
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
        php admin/tool/phpunit/cli/init.php
        phpunit --testsuite="qtype_coderunner test suite"

Please [email me](mailto:richard.lobb@canterbury.ac.nz) if you have problems
with the installation.


##Some notes on question types and customisation


CodeRunner support a wide variety of question types and can easily be
extended to support lots more. The file `db/upgrade.php` installs a set of
standard language and question types into the data base. Each question type
defines a programming language, a couple of templates (to be discussed shortly),
a preferred sandbox (normally left blank so that the best one available can
be used) and a preferred validator. The latter is also normally blank as the
default so-called
*BasicValidator* is the only one currently available - it just compares the actual
program output with the expected output and requires an exact match for a
pass, after trailing blank lines and trailing white space on lines has been
removed. An alternative regular expression match could easily be added but I
haven't felt the need for it yet - I prefer students to get answers exactly
right, not roughly right.

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
to write a whole program and/or require write code that reads standard input.

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

##A note on `VBSandbox`

[This section can be skipped by anyone who's not a developer.]

The system is designed with a pluggable sandbox architecture. The two sandboxes
currently provided are those mentioned above: LiuSandbox and NullSandbox.
If you inspect the code you'll see another sandbox is available: VbSandbox,
which runs code in a VirtualBox
within the host. This was originally intended for use with MatLab, which won't run in
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
1. Add the web server user (www-data on Ubuntu) to the group vboxusers.
1. Login as the user www-data (or whoever), e.g. using 'ssh -Y www-data@localhost'
1. Create an Ubuntu server virtual machine (no GUI) called LinuxSandbox
1. Set up a user 'sandbox' with password 'LinuxSandbox' on that VM
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

##Types of programming questions to support

It is instructive to look at the development of the predecessor *pycode* and
*ccode* plug-ins for an understanding of the rationale behind CodeRunner.

The first version of pycode supported just the "write a function" form of
question that CodingBat offered. While excellent for introductory programming
drills, this style of question wasn't sufficient for some areas of our
first year programming course, most notably file i/o, object-oriented-programming
and GUI programming. The last of those is fundamentally difficult, as evidenced
by the paucity of good testing environments for GUI programming. Since GUI
programming is a small part of our first year programming course and occurs
right at the end, the problem of automatically testing such code was put
in the "too hard" basket, and remains there still. The other two problem
areas, files and OOP, are much more tractable. The second incarnation of
pycode allowed teachers to specify text to be used as standard input that could
be read by student code and also provided a file-like object that could be used
to simulate a file system that student code to read from and write to. In this
new system, the student code was run first, followed by the
test code; the output from the entire run was then expected to match the
expected output.
The test code might print the results of calling a function that the student
should have defined, or print the contents of a file the student should have
written, or try to use a class the student should have defined, etc. The test
code might even be completely empty if the students were asked to write an
entire program that processed standard input in some way.

Running the student's Python code followed by the test code is very flexible.
Of course, it still
has limitations - one can't for example ask a student to provide the missing
line in a given program -- but it allows teachers to test a student's ability
in most aspects of basic Python programming.

Adapting pycode for testing C skills - the *ccode* plug-in - introduced
some new complications. A test to see if a student has correctly implemented
a specified function now requires a main program that calls the function and
prints the result. To keep most tests to simple one-liners, while also allowing
for more general and complex tests, ccode allows simple tests like

     printf("%s\n", studentFunc("Input string"))

Such one-line tests are wrapped in a main function, which is placed after
the student's code, which in turn is placed after a set of standard `#include`
lines, to give a single program module to be compiled and executed. The
teacher can still write a multi-line test with a main function and perhaps
additional support functions, but the simple tabular presentation of results
is somewhat disrupted. Alternatively, the student can be asked to write an
entire C program as in Python, in which case the test code is empty and the
output from the student's program must simply match the expected output for
the given standard input.

An obvious efficiency issue arises with wrapping each test case one-liner in
a main function: in C each test case will involve a full compile-and-execute
cycle. Although this may require only around  0.5 seconds, 10 such tests
might still take around 5 seconds, significantly impacting on the desired "instant
feedback" and also increasing the load on the Moodle server. The latter
could certainly be
significant when running quizzes in large labs or for invigilated class tests.
Accordingly, the ccode plug-in attempts to
combine all one-liner tests into a single test program that outputs a
separator line after each test. The output is then split into a set of
test results to yield the desired table of rows with ticks and crosses. If
such a program throws an exception or otherwise fails to produce a full set
of results, ccode falls back to running each test separately.

The optimisation described in the previous paragraph may rightfully be
regarded as a hack. It is obviously possible for individual tests to
interfere with each other by changing the global state in some way and it is
at least theoretically possible for the chosen separator string to be output
by the student's code, breaking the logic of the tester. In practice, we have
not had any such problems, but a more elegant approach would be desirable.