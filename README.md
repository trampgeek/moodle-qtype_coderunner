# CODE RUNNER

Version: 2.4 January 2015

Author: Richard Lobb, University of Canterbury, New Zealand.

NOTE: this documentation is available in a more-easily browsed form,
together with a sample quiz containing a few CodeRunner questions, at
[coderunner.org.nz](http://coderunner.org.nz).

## Introduction

CodeRunner is a Moodle question type that requests students to submit program code
to some given specification. The submission is graded by running a series of tests on
the code in a sandbox, comparing the output with the expected output.
A trivial example might be a Python function *sqr(x)* that returns its
parameter squared, but there is essentially no limit on the complexity of
questions that can be asked.

CodeRunner is intended to be run in an adaptive mode, so that students know
immediately if their code is passing the tests. In the typical
'all-or-nothing' mode, all test cases must pass
if the submission is to be awarded any marks. The mark for a set of questions
in a quiz is then determined primarily by which questions the student is able
to  solve successfully and then secondarily by how many submissions the student
makes on each question. However, it is also possible to run CodeRunner questions
in a traditional quiz mode where the mark is determined by how many of the tests
the code successfully passes.

CodeRunner and its predecessors *pycode* and *ccode* has been in use at the
University of Canterbury for about five years, running tens of thousands of
student quiz submissions in Python, C and Matlab. All laboratory
and assignment work in the
introductory first year Python programming course, which has around 400 students
in the first semester and 200 in the second, is assessed using CodeRunner
questions. The mid-semester test also uses Moodle/CodeRunner and 
the final examination for the second-semester version of the course was
successfully run on Moodle/CodeRunner in November, 2014.

The second year C course of around 200 students makes similar use of CodeRunner
using C questions and a third year Civil Engineering course, taught in Matlab,
uses CodeRunner for all labs and for the mid-semester programming exam. Other
courses using Moodle/CodeRunner include:

1. COSC261 Formal Languages and Compilers 
1. COSC367 Computational Intelligence
1. SENG365 Web Computing Architectures

CodeRunner currently supports Python2 (considered obsolescent), Python3,
C, PHP5, JavaScript (NodeJS), Octave and Matlab. Java support
is also present but has not yet been used in courses. C++ questions are
not built-in but can be easily supported by custom question types.
The architecture allows
easy extension to other languages.

For security and load reasons, it is recommended that CodeRunner be set up
on a special quiz-server rather than on an institution-wide Moodle server.
However, CodeRunner does allow use of a remote
sandbox machine for running all student-submitted code so provided only
that sandbox is enabled, as discussed below, this version should actually
be safe to install
on an institutional server.

A single 4-core server can handle an average quiz question submission rate of
about 60 quiz questions per minute while maintaining a response time of less
than about 3 - 4 seconds, assuming the student code itself runs in a
fraction of a second.

The CodeRunner question type can be installed on any modern Moodle system, on Linux,
Windows and Mac. Windows and Mac installations will need to connect to a
remote Jobe server to run submitted code, but Linux installations have the
additional option of using either the built-in Runguard Sandbox or the
third-party Liu sandbox to run jobs directly on the Moodle server. See the
*Sandbox Configuration* section for details. Note that the Jobe sandbox
runs only on Linux systems.

## Installation

This chapter describes how to install CodeRunner. It assumes the
existence of a working Moodle system, version 2.6 or later.
If you wish to install the optional Runguard
sandbox, you must be running a Linux-based system and you need
administrator privileges.

If you are installing for the first time, jump straight to section 2.2.

### Upgrading from an earlier version

CodeRunner version 2.4 uses different database table names from earlier
versions and some of the column names have been changed too. The script
upgrade.php will make these changes for you, and the code for importing
questions and restoring courses has been extended to handle legacy-format
exports. All the same, because the database is being significantly altered it
is recommended that:

1. You do not attempt such an upgrade during term time.

1. You make a complete backup of your existing server's database before attempting the
   upgrade so that you can, if necessary, revert to the previous version of
   the code.

1. You do a full backup of any existing courses that use CodeRunner questions
   before starting the upgrade.

1. You export all your CodeRunner questions from the question
   database in Moodle XML format.

Also, please feel free to contact the developer (richard.lobb@canterbury.ac.nz)
either to discuss your upgrade beforehand or afterwards if any problems occur.

With all those caveats, upgrading from an earlier version should be as simple
as a raw installation. Move the
existing `<moodleHome>/local/CodeRunner` folder to a backup location, then just
follow the instructions in the next section.

Note that all existing questions in the system `CR_PROTOTYPES` category with names containing the
string `PROTOTYPE_` are deleted by the installer, which then re-loads them
from the file

    local/CodeRunner/coderunner/db/questions-CR_PROTOTYPES.xml

Hence if you have developed your own question prototypes and placed them in
the system CR\_PROTOTYPES category you must export them in Moodle XML format before
upgrading. You can if you wish place that exported file in the 'db' directory
with a name ending in `_PROTOTYPES.xml`; they will then be automatically
loaded by the installer. Alternatively you can import them at your leisure
later on using the usual question-bank import function in the
web interface.

### Installing CodeRunner from scratch

Note: if you're installing CodeRunner on an SELinux system and you wish
to use the Runguard Sandbox you will probably need to disable
SELinux. This can be done with a command like

    sed -i-dist -e 's|SELINUX=enforcing|SELINUX=permissive|' /etc/selinux/config
    setenforce 0

There are three different ways to install CodeRunner, as follows:

1. Download just the raw files `qtype_coderunner.zip` and `qbehaviour_coderunner.zip`
   and unzip them into the directories `<moodlehome>/question/type` and
   `<moodlehome>/question/behaviour` respectively. This installation
   method does not support the use of the RunGuard sandbox (see below).
   It can be used on any Linux, Windows or Mac.

1. Clone the entire repository into any directory you like, say `<somewhere>`
   and then copy the type/coderunner and
   behaviour/adaptive\_adapted\_for\_coderunner subtrees
   into the Moodle question/type and question/behaviour
   directories. The commands to achieve this under Linux are

        cd <somewhere>
        git clone https://github.com/trampgeek/CodeRunner.git
        cd CodeRunner
        sudo ./install

1. Clone the entire repository into the `<moodlehome>/local` directory
   and then make symbolic links from Moodle's question/type and question/behaviour
   directories into the corresponding CodeRunner subtrees.
   The commands to achieve this on a Linux system are are

        cd <moodlehome>/local
        git clone https://github.com/trampgeek/CodeRunner.git
        cd CodeRunner
        sudo ./devinstall

The first of these methods is the more traditional Moodle install, while the
second is equivalent in effect but makes it possible to also install the
RunGuard sandbox (provided you're running a Linux-based Moodle)
and gives you the full CodeRunner source
tree to experiment with. The third method, which also allows
use of the RunGuard sandbox (on Linux systems only), is intended for developers.
Because
it symbolically links to the source code, any changes made to the source in
the `<moodlehome>/local/CodeRunner` subtree will take immediate effect.

Having carried out one of the above methods,
if you have local question prototypes to add to the built-in prototype set you
should now
copy them into the `<moodlehome>/question/type/coderunner/db` folder. They should be
Moodle XML file(s) with names ending in `_PROTOTYPES.xml` (case-sensitive).
[If you don't understand what this paragraph means, then it probably
doesn't concern you ... move on.]

After carrying out one of the above install methods, you can complete the
installation by logging onto the server through the web interface as an
administrator and following the prompts to upgrade the database as appropriate.
Do not interrupt that upgrade process. If you are upgrading from an earlier
version of CodeRunner you will likely receive quite a few warning messages
from the cachestore, relating to files that have been moved or renamed in
the transition from version 2.3. These warnings can be safely
ignored (I hope).

In its initial configuration, CodeRunner is set to use a University of
Canterbury [jobe server](https://github.com/trampgeek/jobe) to run jobs. You are
welcome to use this for a few hours during initial testing, but it is
not intended for production use. Authentication and authorisation
on that server is
via an API-key and the default API-key given with CodeRunner imposes
a limit of 100
per hour over all clients using that key. If you decide that CodeRunner is
useful to you, *please* set up your own sandbox (Jobe or otherwise) as 
described in *Sandbox configuration* below. Alternatively, if you wish to
continue to use our Jobe server, you can apply to the
[developer](mailto://trampgeek@gmail.org) for your own
API key, stating how long you will need to use the key and a reasonable
upper bound on the number of jobs you will need to submit her hour. We
will do our best to accommodate you if we have sufficient capacity.

If you want a few CodeRunner questions to get started with, try importing the
file
`<MoodleHome>/question/type/coderunner/db/demoquestions.xml`. This contains
all the questions from the [demo site](http://www.coderunner.org.nz).

WARNING: at least a couple of users have broken CodeRunner by duplicating
the prototype questions in the System/CR_PROTOTYPES category. `Do not` touch
those special questions until you have read this entire manual and
are familiar with the inner workings of CodeRunner. Even then, you should
proceed with caution. These prototypes are not
for normal use - they are akin to base classes in a prototypal inheritance
system like JavaScript's. If you duplicate a prototype question the question
type will become unusable, as CodeRunner doesn't know which version of the
prototype to use.

### Building the RunGuardSandbox

The RunguardSandbox allows student jobs to be run on the Moodle server itself.
It users a program `runguard`, written
by Jaap Eldering as part of
the programming contest server [DOMJudge](http://domjudge.sourceforge.net/). This
program needs to be 'setuid root', and hence the install script requires
root permissions to set this up.

If you wish to use the RunguardSandbox
you must have used either
the second or third of the installation methods given above. 
Do not proceed until you have read the various security warnings
in the section *The Runguard Sandbox* below. Then, if you still wish to install
RunGuard, type the command

    sudo ./install_runguard

from within the outermost CodeRunner directory.
This will compile and build the *runguard* program and add the user
account *coderunner*,
which is used for running the submitted jobs. 
The install script
may prompt for details like the office and phone
number of the coderunner user - just hit enter to accept the defaults.

All going well, you should finish up with a user 'coderunner'
and a program *runguard* in CodeRunner/coderunner/Sandbox/ with
setuid-root capabilities. *runguard* should be owned by root with the webserver
user as its group. This program should not be accessible to users other than root and the web
server. Note that any subsequent recursive `chown` or `chmod` on the
*question/type/coderunner* directory tree will probably break `runguard` and you'll need to
re-run the runguard installer.


### Sandbox Configuration

You next need to decide what particular sandbox or sandboxes you wish to use
for running the student-submitted jobs. You can configure which sandboxes you wish to use
together with the various sandbox parameters via the Moodle administrator settings for the
CodeRunner plugin, accessed via

    Site administration > Plugins > Question types > CodeRunner.

Available sandboxes are as follows:

1. The JobeSandbox.

    This is the only sandbox enabled by default. It makes use of a 
separate server, developed for use by CodeRunner, called *Jobe*. As explained
at the end of the section on installing CodeRunner from scratch, the initial
configuration uses the Jobe server at the University of Canterbury. This is not
suitable for production use. Please switch
to using your own Jobe server as soon as possible.

    Follow the instructions at
[https://github.com/trampgeek/jobe](https://github.com/trampgeek/jobe)
to build a Jobe server, then use the
Moodle administrator interface for the CodeRunner plug-in to define the Jobe
host name and perhaps port number. Depending on how you've chosen to
configure your Jobe server, you may also need to supply an API-Key through
the same interface. If you intend running unit tests you
will also need to edit `tests/config.php` to set the correct URL for
the Jobe server.

    Assuming you have built *Jobe* on a separate server, the JobeSandbox fully
isolates student code from the Moodle server. However, Jobe *can* be installed
on the Moodle server itself, rather than on a 
completely different machine. This works fine and is a bit more secure
than using the Runguard Sandbox but is much less secure than running Jobe on
a completely separate machine. If a student program manages to break out of
the sandbox when it's running on a separate machine, the worst it can do is
bring the sandbox server down, whereas a security breach on the Moodle server
could be used to hack into the Moodle database, which contains student run results
and marks. That said, our Computer Science department used the even less
secure Runguard Sandbox for some years without any ill effects; Moodle keeps extensive logs
of all activities, so a student deliberately breaching security is taking a
huge risk.


2. The Liu sandbox

    If you wish to run only C or C++ jobs and wish to avoid the complication of setting
up and maintaining a separate Jobe server, you might wish to consider the
Liu sandbox, which can be installed on the Moodle server itself. It runs all
code with *ptrace*, and disallows any system call that might allow escape
from the sandbox, including most file i/o. The job to be run is compiled and
built as a static load module before being passed to the sandbox. While the
possibility of an exploit can never be absolutely disregarded, the Liu
sandbox does offer a high level of protection.

    The Liu sandbox can be obtained
from [here](http://sourceforge.net/projects/libsandbox/).  Both the binary and the
Python2 interface need to be installed. Note that CodeRunner does not
currently work with the Python3 interface to the sandbox.

    The easiest way to install the Liu sandbox is by
downloading appropriate `.deb`s or `.rpm`s of both `libsandbox` and `pysandbox` (for
Python version 2). Note that the `pysandbox` download must be the one appropriate
to the installed  version of Python2 (currently typically 2.6 on RHEL systems
or 2.7 on most other flavours of Linux).

    The Liu sandbox requires that C programs be compiled and built using static
versions of the libraries rather than the usual dynamically-loaded libraries.
Many versions of the C development packages no longer include static libraries
by default, so you may need to download these separately. Before trying to
use the Liu sandbox, check you can
build a statically linked executable with a command like

        gcc -Wall -Werror -std=c99 -static src.c -lm

    It is also possible to use the Liu sandbox to run other languages,
but it must be configured to allow any extra system calls required by those
languages and also to access those parts of the file system that the language
expects to access. These are many and varied so this approach is not
recommended.

3. The RunguardSandbox.

    The RunguardSandbox is the easiest one to use, as it requires no
extra resources apart from whatever languages (Python3, Java etc) you wish
to use in CodeRunner questions. However, the RunguardSandbox is also the least
secure. It runs student submitted jobs on the Moodle server itself, so most
certainly should not be used on an institutional Moodle server, but it
is reasonably
safe if a special-purpose quiz server is being used, assuming that server requires
student login. Our own quiz server at the
University of Canterbury
made extensive use of the RunguardSandbox for two years with no known security
failures. You should be aware that it does not
prevent use of system calls like *socket* that might open connections to
other servers behind your firewall and of course it depends on the Unix
server being securely set up in the first place.

    The RunguardSandbox uses a program `runguard`, written
by Jaap Eldering as part of
the programming contest server [DOMJudge](http://domjudge.sourceforge.net/).
This program enforces various resource limitations, such as on CPU time and
memory use, on the student program. It runs the code as the non-privileged
user *coderunner* so student-submitted code can do even less than a student
with a Linux account can do (as they can't create files outside the `/tmp`
directory and have severe restrictions on cpu time, threads and memory use).


4.  The IdeoneSandbox.
    ideone.com is a compute server that runs
programs submitted either through a browser or through a web-services API in
a huge number of different languages. It is not recommended for production
use, as execution turn-around time is frequently too large (from 10 seconds
to a minute or more) to give a tolerable user experience. An
[Ideone account](http://ideone.com/account/register)
(username and password) is required to access
the Ideone web-services. Runs are free up to a certain number
but you then have to pay for usage.
The IdeoneSandbox was originally developed as a proof of concept of the idea
of off-line execution, but remains (with little or no guarantees) to
support occasional use of unusual languages. As with
the other sandboxes, you can configure the IdeoneSandbox via the administrator
settings panel for CodeRunner.

### Checking security

Until recently the default Moodle install had all files in the <moodlehome> tree
world-readable.
This is BAD, especially if you're running code in the Runguard sandbox,
because the all-important `config.php`, which contains the database password,
can be read by student code. So it's most important that you at very least
ensure that that particular file is not world-readable.

A better fix is to set the group of the entire Moodle subtree to apache
(or www-data depending on what user the web server runs as) and then make it
all not world readable. However, if you do that after installing CodeRunner
you'll break the set-uid-root program that's used to start the Runguard sandbox.
So you then need to re-run the runguard installer to fix it.

### Running the unit tests

If your Moodle installation includes the
*phpunit* system for testing Moodle modules, you might wish to test the
CodeRunner installation. Most tests require that at least python2 and python3
are installed.

Before running any tests you first need to edit the file
`<moodlehome>/question/type/coderunner/tests/config.php` to match
whatever configuration of sandboxes you wish to test and to set the jobe
server URL, if appropriate. You should then initialise
the phpunit environment with the commands

        cd <moodlehome>
        sudo php admin/tool/phpunit/cli/init.php

You can then run the full CodeRunner test suite with one of the following two commands,
depending on which version of phpunit you're using:

        sudo -u apache vendor/bin/phpunit --verbose --testsuite="qtype_coderunner test suite"

or

        sudo -u apache vendor/bin/phpunit --verbose --testsuite="qtype_coderunner_testsuite"

This will almost certainly show lots of skipped or failed tests relating
to the various sandboxes and languages that you have not installed, e.g.
the LiuSandbox,
Matlab, Octave and Java. These can all be ignored unless you plan to use
those capabilities. The name of the failing tests should be sufficient to
tell you if you need be at all worried.

Feel free to [email me](mailto:richard.lobb@canterbury.ac.nz) if you have problems
with the installation.

## The Architecture of CodeRunner

Although it's straightforward to write simple questions using the
built-in question types, anything more advanced than that requires
an understanding of how CodeRunner works.

The block diagram below shows the components of CodeRunner and the path taken
as a student submission is graded.

<img src="http://coderunner.org.nz/pluginfile.php/145/mod_page/content/2/coderunnerarchitecture.png" width="473" height="250" />

Following through the grading process step by step:

1. For each of the test cases, the [Twig template engine](http://twig.sensiolabs.org/) merges the student's submitted answer with
the question's per-test-case template together with code for this particular test case to yield an executable program.
By "executable", we mean a program that can be executed, possibly
with a preliminary compilation step.
1. The executable program is passed into whatever sandbox is configured
   for this question (e.g. the Jobe sandbox). The sandbox compiles the program (if necessary) and runs it,
   using the standard input supplied by the testcase.
1. The output from the run is passed into whatever Grader component is
   configured, as is the expected output specified for the test case. The most common grader is the
   "exact match" grader but other types are available.
1. The output from the grader is a "test result object" which contains
   (amongst other things) "Expected" and "Got" attributes.
1. The above steps are repeated for all testcases, giving an array of
   test result objects (not shown explicitly in the figure).
1. All the test results are passed to the CodeRunner question renderer,
   which presents them in to the user as the Results Table. Tests that pass
   are shown with a green tick and failing ones shown with a red cross.
   Typically the whole table is coloured red if any tests fail or green
   if all tests pass.
       
The above description is somewhat simplified. Firstly, it 
ignores the existence of the "combinator template", which
combines all the test cases into a single executable
program. The per-test template is used only if there is no combinator
template or if each test case has its own standard input stream or if an
exception occurs during execution of the combined program.
This will all be explained later, in the section on templates. 

Secondly, there are several more-advanced features that are ignored by the
above, such as special customised grading templates, which generate an
executable program that does the grading of the student code as well.
A per-test-case template grader can be used to define each
row of the result table, or a combinator template grader can be used to
defines the entire result table. See the section on grading templates for
more information.

## Question types

CodeRunner support a wide variety of question types and can easily be
extended to support others. A CodeRunner question type is defined by a
*question prototype*, which specifies run time parameters like the execution
language and sandbox and also the templates that define how a test program is built from the
question's test-cases plus the student's submission. The prototype also
defines whether the correctness of the student's submission is assessed by use
of an *EqualityGrader*, a *NearEqualityGrader* or *RegexGrader*. The EqualityGrader expects
the output from the test execution to exactly match the expected output
for the testcase. The NearEqualityGrader is similar but is case insensitive
and tolerates variations in the amount of white space (e.g. missing or extra
blank lines, or multiple spaces where only one was expected).
The RegexGrader expects a regular expression match
instead. The EqualityGrader is recommended for all normal use as it
encourages students to get their output exactly correct; they should be able to
resubmit almost-right answers for a small penalty, which is generally a
better approach than trying to award part marks based on regular expression
matches.

Test cases are defined by the question author to check the student's code.
Each test case defines a fragment of test code, the standard input to be used
when the test program is run and the expected output from that run. The
author can also add additional files to the execution environment.

The test program is constructed from the test case information plus the
student's submission using one of two *templates* defined by the prototype.
The *per-test template* defines a different program for each test case.
To achieve higher efficiency with most
question types there is also a *combinator template* that defines a single
program containing *all* the different tests. If this template is defined,
and there is no standard input supplied,
CodeRunner
tries to use it first, but falls back to running the separate per-test-case
programs if any runtime exceptions occur. Templates are discussed in more
detail below.

### An example question type

The C-function question type expects students to submit a C function, plus possible
additional support functions, to some specification. For example, the question
might ask "Write a C function with signature `int sqr(int n)` that returns
the square of its parameter *n*". The author will then provide some test
cases of the form

        printf("%d\n", sqr(-11));

and give the expected output from this test. There is no standard input for
this question type. The per-test template wraps the student's
submission and
the test code into a single program like:

        #include <stdio.h>

        // --- Student's answer is inserted here ----

        int main()
        {
            printf("%d\n", sqr(-11));
            return 0;
        }

which is compiled and run for each test case. The output from the run is
then compared with
the specified expected output (121) and the test case is marked right
or wrong accordingly.

That example ignores the use of the combinator template, which in
the case of the built-in C function question type
builds a program with multiple `printf` calls interleaved with
printing of a special separator. The resulting output is then split
back into individual test case results using the separator string as a splitter.

### Built-in question types

The file `<moodlehome>/question/type/coderunner/db/questions-CR_PROTOTYPES.xml`
is a moodle-xml export format file containing the definitions of all the
built-in question types. During installation, and at the end of any version upgrade,
the prototype questions from that file are all loaded into a category
CR\_PROTOTYPES in the system context. A system administrator can edit
those prototypes but this is not generally recommended as the modified versions
will be lost on each upgrade. Instead, a category LOCAL\_PROTOTYPES
(or other such name of your choice) should be created and copies of any prototype
questions that need editing should be stored there, with the question-type
name modified accordingly. New prototype question types can also be created
in that category. Editing of prototypes is discussed later in this
document.

Built-in question types include the following:

 1. **c\_function**. This is the question type discussed in the above
example. The student supplies
 just a function (plus possible support functions) and each test is (typically) of the form

        printf(format_string, func(arg1, arg2, ..))

 The template for this question type generates some standard includes, followed
 by the student code followed by a main function that executes the tests one by
 one.

 The manner in which a C program is executed is not part of the question
 type definition: it is defined by the particular sandbox to which the
 execution is passed. The Liu Sandbox and the CodeRunner sandbox both use the gcc
 compiler with the language set to
 accept C99 and with both *-Wall* and *-Werror* options set on the command line
 to issue all warnings and reject the code if there are any warnings.
 The Liu sandbox also requires that the executable be statically linked; you
 may need to download the static libraries separately from the default C
 development install to enable this.

 1. **python3**. Used for most Python3 questions. For each test case, the student
code is run first, followed by the test code.

 1. **python3\_w\_output**. A variant of the *python3* question in which the
*input* function is redefined at the start of the program so that the standard
input characters that it consumes are echoed to standard output as they are
when typed on the keyboard during interactive testing. A slight downside of
this question type compared to the *python3* type is that the student code
is displaced downwards in the file so that line numbers present in any
syntax or runtime error messages do not match those in the student's original
code.

 1. **python2**. Used for most Python2 questions. As for python3, the student
code is run first, followed by the sequence of tests. This question type
should be considered to be
obsolescent due to the widespread move to Python3 through the education
community.

 1. **java\_method**. This is intended for early Java teaching where students are
still learning to write individual methods. The student code is a single method,
plus possible support methods, that is wrapped in a class together with a
static main method containing the supplied tests (which will generally call the
student's method and print the results).

 1. **java\_class**. Here the student writes an entire class (or possibly
multiple classes in a single file). The test cases are then wrapped in the main
method for a separate
public test class which is added to the students class and the whole is then
executed. The class the student writes may be either private or public; the
template replaces any occurrences of `public class` in the submission with
just `class`. While students might construct programs
that will not be correctly processed by this simplistic substitution, the
outcome will simply be that they fail the tests. They will soon learn to write
their
classes in the expected manner (i.e. with `public` and `class` on the same
line, separated by a single space)!]

 1. **java\_program**. Here the student writes a complete program which is compiled
then executed once for each test case to see if it generates the expected output
for that test. The name of the main class, which is needed for naming the
source file, is extracted from the submission by a regular expression search for
a public class with a `public static void main` method.

 1. **octave\_function**. This uses the open-source Octave system to process
matlab-like student submissions.

As discussed later, this base set of question types can
be customised or extended in various ways.

C++ isn't available as a built-in type at present, as we don't teach it.
However, if the Jobe server is configured to run C++ jobs (probably using
the language ID 'cpp') you can easily make a custom C++ question
type by starting with the C question type, setting the language to *cpp*
and changing the template to include
*iostream* instead of, or as well as, *stdio.h*. The line

        using namespace std;

 may also be desirable.

### Some more-specialised question types

The following question types used to exist as built-ins but have now been
dropped from the main install as they are intended primarily for University
of Canterbury (UOC) use only. They can be imported, if desired, from the file
**uoclocalprototypes.xml**, located in the CodeRunner/coderunner/db folder.

The UOC question types include:

 1. **python3\_pylint**. This is a Python3 question where the student submission
is passed through the [pylint](http://www.logilab.org/857)
source code analyser. The submission is rejected if pylint gives any errors,
otherwise testing proceeds as normal. Obviously, pylint needs to be installed
on the Jobe server or the Moodle server itself if the RunguardSandbox is
being used. This question type can take two template parameters:

    * `isfunction`: if set and true a dummy module docstring will be inserted at
the start of the program. This is useful in "write a function" questions

    * `pylintoptions`: this should be a JSON list of strings.

   For example, the Template parameters string in the question authoring form
might be set to

        {"isfunction": true, "pylintoptions":["--max-statements=20","--max-args=3"]}

   to generate a dummy module docstring at the start and to set the maximum
   number of
   statements and arguments for each function to 20 and 3 respectively.


 1. **matlab\_function**. This is the only supported matlab question type.
It assumes
matlab is installed on the Jobe or Moodle server and can be run with the shell command
`/usr/local/bin/matlab_exec_cli`.
A ".m" test file is built that contains a main test function, which executes
all the supplied test cases, followed by the student code which must be in the
form of one or more function declarations. That .m file is executed by Matlab,
various Matlab-generated noise is filtered, and the output must match that
specified for the test cases.


## Templates

Templates are the key to understanding how a submission is tested. There are in
general two templates per question type (i.e. per prototype) - a *combinator\_template* and a
*per\_test\_template*. We'll discuss the latter for a start.

The *per\_test\_template* for each question type defines how a program is built from the
student's code and one particular testcase. That program is compiled (if necessary)
and run with the standard input defined in that testcase, and the output must
then match the expected output for the testcase (where 'match' is defined
by the chosen validator: an exact match, a nearly exact match or a
regular-expression match.

The question type template is processed by the
[Twig](http://twig.sensiolabs.org/) template engine. The engine is given both
the template and a variable called
STUDENT\_ANSWER, which is the text that the student entered into the answer box,
plus another called TEST, which is a record containing the test-case
that the question author has specified
for the particular test. The template will typically use just the TEST.testcode
field, which is the "test" field of the testcase, and usually (but not always)
is a bit of code to be run to test the student's answer. As an example,
the question type *c\_function*, which asks students to write a C function,
has the following template:

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

with the expected output of 81. The result of substituting both the student
code and the test code into the template would then be a program like:

        #include <stdio.h>
        #include <stdlib.h>
        #include <ctype.h>

        int sqr(int n) {
            return n * n;
        }

        int main() {
            printf("%d\n", sqr(-9));
            return 0;
        }

When authoring a question you can inspect the template for your chosen
question type by temporarily checking the 'Customise' checkbox. Additionally,
if you check the *Template debugging* checkbox you will get to see
in the output web page each of the
complete programs that gets run during a question submission.

As mentioned earlier, there are actually two templates for each question
type. For efficiency, CodeRunner first tries
to combine all testcases into a single compile-and-execute run using the second
template, called the `combinator_template`. There is a combinator
template for most
question types, except for questions that require students
to write a whole program. However, the combinator template is not used during
testing if standard input is supplied for any of the tests; each test
is then assumed to be independent of the others, with its own input. Also,
if an exception occurs at runtime when a combinator template is being used,
the tester retries all test cases individually using the per-test-case
template so that the student gets presented with all results up to the point
at which the exception occurred.

As mentioned above, both the `per_test_template` and the `combinator_template`
can be edited by the question
author for special needs, e.g. if you wish to provide skeleton code to the
students. As a simple example, if you wanted students to provide the missing
line in a C function that returns the square of its parameter, and you
also wished to hide the *printf* from the students, you could use
a template like:

        #include <stdio.h>
        #include <stdlib.h>
        #include <ctype.h>

        int sqr(int n) {
           {{ STUDENT_ANSWER }}
        }

        int main() {
            printf("%d\n", {{ TEST.testcode }});
            return 0;
        }

The testcode would then just be of the form `sqr(-11)`, and the question text
would need to make it clear
to students what context their code appears in. The authoring interface
allows the author to set the size of the student's answer box, and in a
case like the above you'd typically set it to just one or two lines in height
and perhaps 30 columns in width.

You will need to understand loops and selection in
the Twig template engine if you wish to write your own combinator templates.
For one-off question use, the combinator template doesn't normally offer
sufficient additional benefit to warrant the complexity increase
unless you have a
large number of testcases or are using
a slow-to-launch language like Matlab. However, if you are writing your
own question prototypes you might wish to make use of it.

## Advanced template use

It may not be obvious from the above that the template mechanism allows
for almost any sort of question where the answer can be evaluated by a computer.
In all the examples given so far, the student's code is executed as part of
the test process but in fact there's no need for this to happen. The student's
answer can be treated as data by the template code, which can then execute
various tests on that data to determine its correctness. The Python *pylint*
question type mentioned earlier is a simple example: the template code first
writes the student's code to a file and runs *pylint* over that file before
proceeding with any tests.

The per-test template for such a question type in its
simplest form might be:

    __student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

    import subprocess
    import os

    def check_code(s):

        try:
            source = open('source.py', 'w')
            source.write(s)
            source.close()
            env = os.environ.copy()
            env['HOME'] = os.getcwd()
            cmd = ['pylint', 'source.py']
            result = subprocess.check_output(cmd, stderr=subprocess.STDOUT, env=env)
        except Exception as e:
            result = e.output.decode('utf-8')

        if result.strip():
            print("pylint doesn't approve of your program")
            print(result)
            raise Exception("Submission rejected")

    check_code(__student_answer__)

    {{ STUDENT_ANSWER }}
    {{ TEST.testcode }}

The Twig syntax {{ STUDENT\_ANSWER | e('py') }} results in the student's submission
being filtered by a Python escape function that escapes all
double quote and backslash characters with an added backslash.

The full `Python3_pylint` question type is a bit more complex than the
above. It is given in full in the section on *template parameters*.

Note that in the event of a failure to comply with pylint's style rules,
an exception is raised; this ensures that
further testing is aborted so that the student doesn't receive the same error
for every test case. [As noted above, the tester aborts the testing sequence
when using the per-test-case template if an exception occurs.]

Some other more complex examples that we've
used in practice include:

 1. A Matlab question in which the template code (also Matlab) breaks down
    the student's code into functions, checking the length of each to make
    sure it's not too long, before proceeding with marking.

 1. A Python question where the student's code is actually a compiler for
    a simple language. The template code runs the student's compiler,
    passes its output through an assembler that generates a JVM class file,
    then runs that class with the JVM to check its correctness.

 1. A Python question where the students submission isn't code at all, but
    is a textual description of a Finite State Automaton for a given transition
    diagram; the template code evaluates the correctness of the supplied
    automaton.


### Twig Escapers

As explained above, the Twig syntax {{ STUDENT\_ANSWER | e('py') }} results
in the student's submission
being filtered by a Python escape function that escapes all
all double quote and backslash characters with an added backslash. The
python escaper e('py') is just one of the available escapers. Others are:

 1. e('java'). This prefixes single and double quote characters with a backslash
    and replaces newlines, returns, formfeeds, backspaces and tabs with their
    usual escaped form (\n, \r etc).

 1. e('c').  This is an alias for e('java').

 1. e('matlab'). This escapes single quotes, percents and newline characters.
    It must be used in the context of Matlab's sprintf, e.g.

        student_answer = sprintf('{{ STUDENT_ANSWER | e('matlab')}}');

 1. e('js'), e('html') for use in JavaScript and html respectively. These
    are Twig built-ins. See the Twig documentation for details.

## Template parameters

It is sometimes necessary to make quite small changes to a template over many
different questions. For example, you might want to use the *pylint* question
type given above but change the maximum allowable length of a function in different
questions. Customising the template for each such question has the disadvantage
that your derived questions no longer inherit from the original prototype, so
that if you wish to alter the prototype you will also need to find
and modify all the
derived questions, too.

In such cases a better approach may be to use template parameters.

If the *+Show more* link on the CodeRunner question type panel in the question
authoring form is clicked, some extra controls appear. One of these is
*Template parameters*. This can be set to a JSON-encoded record containing
definitions of variables that can be used by the template engine to perform
local per-question customisation of the template. The template parameters
are passed to the template engine as the object `QUESTION.parameters`.

The full template code for the University of Canterbury *Python3\_pylint*
question type is:

    __student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""

    import subprocess
    import os

    def check_code(s):
    {% if QUESTION.parameters.isfunction %}
        s = "'''Dummy module docstring'''\n" + s
    {% endif %}
        try:
            source = open('source.py', 'w')
            source.write(s)
            source.close()
            env = os.environ.copy()
            env['HOME'] = os.getcwd()
            pylint_opts = []
    {% for option in QUESTION.parameters.pylintoptions %}
            pylint_opts.append('{{option}}')
    {% endfor %}
            cmd = ['pylint', 'source.py'] + pylint_opts
            result = subprocess.check_output(cmd, stderr=subprocess.STDOUT, env=env)
        except Exception as e:
            result = e.output.decode('utf-8')

        if result.strip():
            print("pylint doesn't approve of your program")
            print(result)
            raise Exception("Submission rejected")

    check_code(__student_answer__)

    {{ STUDENT_ANSWER }}
    {{ TEST.testcode }}

The `{% if` and 
`{% for` are Twig control structures that conditionally insert extra data
from the template parameters field of the author editing panel.


## Grading with templates
Using just the template mechanism described above it is possible to write
almost arbitrarily complex questions. Grading of student submissions can,
however, be problematic in some situations. For example, you may need to
ask a question where many different valid program outputs are possible, and the
correctness can only be assessed by a special testing program. Or
you may wish to subject
a student's code to a very large
number of tests and award a mark according to how many of the test cases
it can handle. The usual exact-match
grader cannot handle these situations. For such cases one of the two
template grading options can be used.

### Per-test-case template grading

When the 'Per-test-case template grader' is selected as the grader
the per-test-case template
changes its role to that of a grader for a particular test case.
The combinator template is not used
and the per-test-case template is applied to each test case in turn. The
output of the run is not passed to the grader but is taken as the
grading result for the corresponding row of the result table.
The output from the template-generated program must now
be a JSON-encoded object (such as a dictionary, in Python) containing
at least a 'fraction' field, which is multiplied by TEST.mark to decide how
many marks the test case is awarded. It should usually also contain a 'got'
field, which is the value displayed in the 'Got' column of the results table.
The other columns of the results table (testcode, stdin, expected) can also
be defined by the custom grader and will be used instead of the values from
the test case. As an example, if the output of the program is the string

    {"fraction":0.5, "got": "Half the answers were right!"}

half marks would be
given for that particular test case and the 'Got' column would display the
text "Half the answers were right!".

For even more flexibility the *result_columns* field in the question editing
form can be used to customise the display of the test case in the result
table. That field allows the author to define an arbitrary number of arbitrarily
named result-table columns and to specify using *printf* style formatting
how the attributes of the grading output object should be formatted into those
columns. For more details see the section on result-table customisation.

Writing a grading template that executes the student's code is, however,
rather difficult as the generated program needs to be robust against errors
in the submitted code.

### Combinator-template grading

The ultimate in grading flexibility is achieved by use of the "Combinator
template grading" option. In this mode the per-test template is not used. The
combinator template is passed to the Twig template engine and the output
program is executed in the usual way. Its output must now be a JSON-encoded
object with two mandatory attributes: a *fraction* in the range 0 - 1,
which specifies the fractional mark awarded to the question, and a
*feedbackhtml* that fully defines the specific feedback to be presented
to the student in place of the normal results table. It might still be a
table, but any other HTML-supported output is possible such as paragraphs of
text, canvases or SVG graphics. The *result_columns* field from the
question editing form is ignored in this mode.

Combinator-template grading is intended for use where a result table is just not
appropriate, e.g. if the question does not involve programming at
all. As an extreme example, imagine a question that asks the student to
submit an English essay on some topic and an AI grading program is used
to mark and to generate
a report on the quality of the essay for feedback to the student.
[Would that such AI existed!] 

The combinator-template grader has available to it the full list of all
test cases and their attributes (testcode, stdin, expected, mark, display etc)
for use in any way the question author sees fit. It is highly likely that
many of them will be disregarded or alternatively have some meaning completely
unlike their normal meaning in a programming environment. It is also
possible that a question using a combinator template grader will not
make use of test cases at all.

## An advanced grading-template example
As an example of the use of a per-test-case template grader
consider the following question:

"What single line of code can be inserted into the underlined blank space
in the code below to make the function behave as specified? Your answer
should be just the missing line of code, not the whole function.
It doesn't matter if you indent your code or not in the answer box.
For full marks your answer must be a single line of code. However,
half marks will be awarded if you provide more than one line of code but it
works correctly.


        def nums_in_range(nums, lo, hi):
            '''Given a non-empty list of numbers nums and two numbers
               lo and hi return True if and only if the minimum of the
               numbers in nums is greater than lo and the maximum of
               the numbers in nums is less than hi.'''
            ____________________________


The grader for this question, which needs to check both the number of
lines of code submitted and the correctness, awarding marks and appropriate
feedback accordingly, might be the following:

        import re
        __student_answer__ = """{{ STUDENT_ANSWER | e('py') }}"""
        if __student_answer__.strip().startswith('def'):
            raise Exception("You seem to have pasted the whole function " +
                            "definition. READ THE INSTRUCTIONS!")
        if re.search(r'print *\(.*\)', __student_answer__):
            mark = 0
            got = "BAD CODE: your function should not print anything"
        else:
            # Split the code into lines. Indent if necessary.
            __lines__ = __student_answer__.split('\n')
            __lines__ = ['    ' + line +
                    '\n' for line in __lines__ if line.strip() != '']
            code = 'def nums_in_range(nums, lo, hi):\n' + ''.join(__lines__)
            exec(code)
            num_lines = len(__lines__)

            result = {{TEST.testcode}}
            if result == {{TEST.expected}}:
                if num_lines > 1:
                    mark = 0.5
                    got = repr(result) + r"\n(but more than 1 line of code)"
                else:
                    mark = 1
                    got = repr(result)
            else:
                mark = 0
                if num_lines > 1:
                    got = repr(result) + r"\n(and more than one line of code)"
                else:
                    got = repr(result)

        print('{"fraction":' + str(mark) + ',"got":"' + got + '"}')

If the student submits one line of code that behaves correctly
their grading table looks normal, e.g.
![right answer image](http://coderunner.org.nz/pluginfile.php/56/mod_page/content/7/rightAnswerToGraderExample.png)

If they submit multiple lines of code that behave correctly, their result
table might instead be:
![wrong answer image](http://coderunner.org.nz/pluginfile.php/56/mod_page/content/7/wrongAnswerToGraderExample.png)

In both the above examples the result table has been customised to show the
mark column. Result table customisation is covered in the next section.

Note that the "Got" column contains a customised message in addition to
their output and the customised message varies according to whether their
answer was right or wrong. Note too that the template performs various other
checks on their code, such as whether it contains any print statements or
whether they have pasted an entire function definition.

Obviously, writing questions using custom graders is much harder than
using the normal built-in equality based grader. It is usually possible to
ask the question in a different way that avoids the need for a custom grader.
In the above example, the
student could have been asked to submit their entire function twice,
once to a question that evaluated its
correctness and a second time to one that evaluated its correctness *and*
its length. No custom grader is then required. That is
somewhat clumsy from the student perspective
but is much easier for the author.

## Customising the result table

The output from the standard graders is a list of so-called *TestResult* objects,
each with the following fields (which include the actual test case data):

    testcode      // The test that was run (trimmed, snipped)
    isCorrect     // True iff test passed fully (100%)
    expected      // Expected output (trimmed, snipped)
    mark          // The max mark awardable for this test
    awarded       // The mark actually awarded.
    got           // What the student's code gave (trimmed, snipped)
    stdin         // The standard input data (trimmed, snipped)
    extra         // Extra data for use by some templates


A field called *result_columns* in the question authoring form can be used
to control which of these fields are used, how the columns are headed and
how the data from the field is formatted into the result table.

By default the result table displays
the testcode, stdin, expected and got columns, provided the columns
are not empty. You can change the default, and/or the column headers
by entering a value for *result_columns* (leave blank for the default
behaviour). If supplied, the result_columns field must be a JSON-encoded
list of column specifiers.

Each column specifier is itself a list,
typically with just two or three elements. The first element is the
column header, the second element is the field from the TestResult
object being displayed in the column (one of those values listed above) and the optional third
element is an sprintf format string used to display the field.
Custom-grader templates may add their
own fields, which can also be selected for display. It is also possible
to combine multiple fields into a column by adding extra fields to the
specifier: these must precede the sprintf format specifier, which then
becomes mandatory. For example, to display a Mark Fraction column in the
form `0.74 out of 1.00`, a column format specifier of `["Mark Fraction", "awarded",
"mark", "%.2f out of %.2f"]` could be used. As a further special case, a format
of `%h` means that the test result field should be taken as ready-to-output
HTML and should not be subject to further processing; this is useful
only with custom-grader templates that generate HTML output, such as
SVG graphics. 

The default value of *result_columns* is `[["Test", "testcode"],
["Input", "stdin"], ["Expected", "expected"], ["Got", "got"]]`.



## User-defined question types

NOTE: User-defined question types are very powerful but are not for the faint
of heart. There are some known pitfalls, so please read the following very
carefully.

As explained earlier, each question type is defined by a prototype question,
which is just
another question in the database from which new questions can inherit. When
customising a question, if you open the *Advanced customisation* panel you'll
find the option to save your current question as a prototype. You will have
to enter a name for the new question type you're creating. It is strongly
recommended that you also change the name of your question to reflect the
fact that it's a prototype, in order to make it easier to find. The convention
is to start the question name with
the string PROTOTYPE\_, followed by the type name. For example,
PROTOTYPE\_python3\_OOP. Having a
separate PROTOTYPES category for prototype questions is also strongly recommended.
Obviously the
question type name you use should be unique, at least within the context of the course
in which the prototype question is being used.

CodeRunner searches for prototype questions
just in the current course context. The search includes parent
contexts, typically visible only to an administrator, such as the system
context; the built-in prototypes all reside in that system context. Thus if
a teacher in one course creates a new question type, it will immediately
appear in the question type list for all authors editing questions within
that course but it will not be visible to authors in other courses. If you wish
to make a new question type available globally you should ask a
Moodle administrator
to move the question to the system context, such as a LOCAL\_PROTOTYPES
category, mentioned above.

When you create a question of a particular type, including user-defined
types, all the so-called "customisable" fields are inherited from the
prototype. This means changes to the prototype will affect all the "children"
questions. However, as soon as you customise a child question you copy all the
prototype fields and lose that inheritance.

To reduce the UI confusion, customisable fields are subdivided into the
basic ones (per-test-template, grader, result-table column selectors etc) and
"advanced"
ones. The latter include the language, sandbox, timeout, memory limit and
the "make this question a prototype" feature. The combinator
template is also considered to be an advanced feature.

**WARNING #1:** if you define your own question type you'd better make sure
when you export your question bank
that you include the prototype, or all of its children will die on being imported
anywhere else! 
Similarly, if you delete a prototype question that's actually
in use, all the children will break, giving runtime errors. To recover
from such screw ups you will need to create a new prototype
of the right name (preferably by importing the original correct prototype).
To repeat:
user-defined question types are not for the faint of heart. Caveat emptor.

**WARNING #2:** although you can define test cases in a question prototype
these have no relevance and are silently ignored.

## How programming quizzes should work

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

