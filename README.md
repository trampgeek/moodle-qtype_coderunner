CODE RUNNER
========

@version 0 December 2012
@author Richard Lobb, University of Canterbury, New Zealand.

Introduction
------------

CodeRunner is a Moodle question type that requests students to submit program code
to some given specification, e.g. a Python function sqr(x) that returns its
parameter squared. The submission is graded by running a series of testcases
of the code in a sandbox, comparing the output with the expected output.
If all testcases pass, the question is deemed correct, otherwise it is
incorrect. CodeRunner is expected to be run in a special adaptive mode, so the
student submits each question one by one repeatedly, until a correct result
is obtained. Their mark for the question is then determined by the number of
submissions and the per-submission penalty set within Moodle in the usual way.

CodeRunner is a derivative of a Python-specific plug-in called *pycode*, which
has been used in 4 stage 1 classes of several hundred students for two years,
and *ccode*, a C version of pycode, which was used in a stage 2 C programming
course of 200 students. The new version is designed to support multiple
languages and question types within a single plug-in. Python, C and MatLab
will be supported in the first version but it is sufficiently general to
accommodate additional languages with very little custom code.


Installation
------------

CodeRunner requires Moodle version 2.1 or later. Furthermore, to run the testsuite,
a Moodle version >= 2.3 is required, since the tests have been changed from
using SimpleTest to PHPUnit, as required by version 2.3.

There are three stages to installation:

1. installing a sandbox, in which
student code can be run in (hopefully) a safe way
2. installing the CodeRunner
module itself.
3. Configuring the sandbox for your own environment and languages

1. Installing the Liu sandbox

   The main sandbox for normal use (Python, C, C++) is the
Liu sandbox. It should be installed first. It can be obtained from
http://sourceforge.net/projects/libsandbox/.  Both the binary and the
Python2 interface need to be installed. Note that CodeRunner does *not*
currently work with the Python3 interface to the sandbox, though it is
quite possible to run Python3 within the sandbox (and indeed that's the most
common mode of operation).  The easiest way to install the Liu sandbox is by
downloaded appropriate .debs or .rpms of both libsandbox and pysandbox (for
Python version 2). Note that the pysandbox download must be the one appropriate
to the installed  version of Python2 (currently typically 2.6 on RHEL systems
on 2.7 on most other flavours of Linux).

2. Installing CodeRunner

   CodeRunner should be installed in the <moodlehome>/local directory as follows

    cd <moodlehome>/local
    git clone https://github.com/trampgeek/CodeRunner.git
    cd CodeRunner
    ./install

    All going well, you should then have symbolic links from <moodlehome>/question/type
and <moodlehome>/question/behaviour to the <moodlehome>/local/CodeRunner/CodeRunner
and <moodlehome>/local/CodeRunner/adaptive_adapted_for_coderunner directories
respectively. There should also be a symbolic link from local/Twig to
local/CodeRunner/Twig.

3. Configuring the Liu Sandbox

   The last step in installation involves configuring the sandbox appropriately
for your particular environment. This involves deciding what subset of the
file system a student programming, executing within the sandbox, is allowed
to see. The simple answer of "nothing" is possible for statically-linked
C programs but most other environments (normally dynamically-linked C,
Python, Matlab, Java etc) will require
dynamic loading of modules at run time from various parts of the file system.
The default downloaded configuration supports standard Linux Python2
and C questions, and Python3 questions if Python3 is installed in the /opt
directory tree (which is where it landed up on the development server for
some reason or other). Other languages will require some tweaking. [TODO:
provide more documentation on this.]
vbsandbox
Assuming you're on a Moodle version >=2.3, you should now be able to test the
CodeRunner installation with phpunit. However, unless you are planning on running
the VirtualBox sandbox and/or Matlab you should first move or remove the two files

    <moodlehome>/local/coderunner/Coderunner/tests/vbsandbox_test.php
    <moodlehome>/local/coderunner/Coderunner/tests/matlabquestions_test.php

You should then be able to run the tests with

    cd <moodlehome>
    php admin/tool/phpunit/cli/init.php
    phpunit --testsuite="qtype_coderunner test suite"

If all that goes well, you should be in business.

Please email me if you have problems with the installation.

More on Sandboxing
------------------

The system is designed with a pluggable sandbox architecture. Two other
sandboxes are currently provided: VbSandbox, which runs code in a VirtualBox
within the host and NullSandbox which isn't a true sandbox at all but runs
code natively on the host, albeit with resource constraints on time,
number of processes, max filesize etc.

The VirtualBox sandbox was intended for use with MatLab, which won't run in
the LiuSandbox as it is multithreaded and very heavy on system calls, leading
to performance issues. The code has been developed and debugged up to the point
where it was ready for production testing, but when it was moved onto the
production server -- a vmware Virtual Machine -- it was discovered that
VirtualBox will not run on another VM. Thus, the VirtualBox sandbox is usable
only on real host, not on a virtualised host. This isn't very useful in our
environment, so further development of the VirtualBox sandbox has been
discontinued. However, the code has been left in the system in case it is
needed by other users or even by ourselves in the future. Some notes on
installing the VirtualBox sandbox are given in the section "Notes on the
VirtualBox Sandbox" below.

The so-called NullSandbox limits the resource usage of a task, which is
sufficient for some low-risk environments where only responsible users with
logins are using the system.  However, it does not provide *any* protection against
malicious programs. In particular, since the submitted code is run by the
web-server user, it can read all the temporary files created while other users
are submitting their CodeRunner code and, worse, it can read the Moodle
and various apache configuration files that contain  such as information
the Moodle database password. In our own University environment we intend
to use the NullSandbox only to support a smallish class of Matlab students,
simply because Matlab doesn't play well in other (real) sandboxes. We will
set aside a dedicated VM for running just the Moodle quizzes for this class.

Use the NullSandbox at your own risk!


Notes on the VirtualBox Sandbox
----------------------

As explained above, the VirtualBox sandbox is not in production use, but the
following brief documentation remains for the benefit of anyone who wishes
to resurrect it.

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


How programming quizzes should work
-----------------------------------

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

Types of programming questions to support
-----------------------------------------
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