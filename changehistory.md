# CHANGE HISTORY

### 29 October 2018. 3.5.3

 * Bug fix: installing CodeRunner on Moodle 3.5 with error debug messages
   enabled resulted in messages "Error: mdb->get_record() found more than one record!"
   when browsing the question bank. Alternatively, if CodeRunner was
   installed on a freshly built Moodle 3.5, the CR_PROTOTYPES category
   became a proxy for the Top category in the system category and its name
   was hidden.

### 18 October 2018. 3.5.2+

* Fix broken PHP question type and add test cases for it.
* Fix GraphUI plugin to prevent new arcs from exactly overlying old ones
* Bug fix: nodejs question type not working with strict mode. Also,
  renamed question type from nodejs-2 to just nodejs (as it used to be).
* Add trivial nodejs question sample to samples folder.
* Add the UOC Python3 tkinter question type to the samples folder.
* Improve error message when duplicate question prototypes are found.
* Bug fix: behat export test not working with latest Moodle versions (Thanks
  Tim Hunt).


### 14 August 2018. 3.5.1

* Fix error in Privacy Provider (failing with PHP versions 7.x).Thanks to Sam Marshall.

### 10 July 2018. 3.5.0

* Refactoring to allow repair of questions with missing prototypes
  via the usual author editing form. Plus improved error messages in questions
  that use such broken prototypes.
* Addition of a TableUI user interface plugin that presents students with
  a table to fill in rather than a single text area. This supports a new
  experimental python3_testing question, where the student must supply a set
  of tests, given a specification.
* Improve error messages if a UI plugin doesn't load in time (usually the Ace
  editor).
* Implement Moodle privacy API for GDPR compatibility. This involves simply
  implementing the "null provider" class, essentially
  declaring that CodeRunner does not record any personal data about users.
* Add an experimental administrator script that deletes entire empty question
  category subtrees.
* Use language strings to document built-in questions rather than the question
  text to enable translation via AMOS.
* Reduce time for which the GraphUI displays the serialised answer during question
  submission.
* Various documentation tweaks.
* Allow submission of questions with no test cases (e.g. when template does all
  the testing).
* Miscellaneous bug fixes including:
  * Questions with missing prototypes were breaking the display of the list of all
    questions in a quiz in Moodle 3.5.
  * Bug in skool-is-kool take 2 sample question.
  * Column header in the 'For example' table did not use a proper language string,
    so not subject to translation.
  * Author editing form Ace editor panels were too narrow in Moodle 3.5 with
    Clean theme (and probably other non-Boost themes)
  * Prototypes for java-method, octave-function, python2 and python3 questions
    were all broken on Windows-based Moodle servers.
  * Some combinator template graders were failing with PHP 7.2.


### May 2018 3.4.1
This is a maintenance release.
* Bug fix: Show Differences button was not displayed on non-English-language
  sites.
* Minor tweaks and documentation updates.


### 28 April 2018. 3.4.0
* Add randomisation capabilities so that students can be presented with
  a randomly generated question variant when they start a question in a quiz.
  Randomisation is achieved by the use of Twig expansion of the template
  parameters field (assumed to include at least one call to the Twig *random*
  function) followed by Twig expansion of all other question fields using
  the expanded template parameters as a Twig environment.
* Add a *Reset answer* button to the student question answer page if the answer
  box contains preloaded content.
* Add function *set_random_seed* to Twig for use with the question randomisation, e.g. to ensure
  that a student always sees the same variant of a question no matter how often
  they attempt it.
* Add *id* field to the Twig STUDENT variable, e.g. for use with the above.
* Use Ace editor for template-parameters field.
* Add *Twig All* and *Hoist template parameters* checkboxes to the authoring interface for
  use with randomisation and to simplify template authoring in general.
* Add *fontsize* parameter to the GraphUI plugin.
* Fix a long-standing bug that caused questions to be flagged as incomplete after
  earlier having been marked correct even though the answer had not been altered.
  This turned out to occur if the student's answer began with a blank line.
* Improve question author feedback in the event of Twig errors.
* Miscellaneous code-cleaning and minor bug fixes.

### 18 February 2018. 3.3.0
* Add multilanguage program question type to base set, plus various changes
  to UI-handling code to allow student to select a language in such questions.
* Allow UTF-8 output from programs, if Jobe server is configured to
allow this.
* Implement UI plugin architecture to allow different JavaScript plugins
  to manage the question answer textarea and related textareas in the
  question authoring form.
* Incorporate the GraphUI plugin from Emily Price into the new plugin architecture
  (thanks Emily).
* Add directed and undirected graph prototypes to the built-in prototype set.
* Fix bug with the auto-correcting of test cases that fail during validation.
  If the author changed the ordering of testcases via the "ordering" field, the
  wrong test case was getting updated. Resolved by preventing re-ordering during
  validation, deferring it until question is finally saved.
* Fixed bug in IdeoneSandbox - language name strings were no longer appropriate.
  However, this sandbox should be regarded as deprecated and will be removed
  some time in the future.

### 3 December 2017. 3.2.2
* Incorporate changes from abautu (Andrei Bautu) to allow question authors
to update test case 'expected' fields directly from a table of
test failures generated by running the sample answer during validation
of the question author form. Thanks Andrei.

* Added an experimental script <coderunnerhome>/question/type/coderunner/downloadquizattempts.php
to dump the database info on all student quiz activity to a spreadsheet.
The data includes the submitted answers to both prechecks and checks, with
timestamps. The export format is experimental and may change in the future.
A Python module *quizattempts.py* is included too and is the recommended way
to deal with the quiz attempt download spreadsheet.

* Bug fix: a submitted answer of '0' (an edge case that might be possible
when student answer is not code) was being rejected as an empty response, due
to the use of PHP's *empty* function. Thanks to David Bowes for the fixes here.

* Added SQL prototype and 2 simple samples (experimental).

* Bug fix: testsplitterre and allowmultiplestdin fields of author form were not
  being correctly initialised when a new combinator question type was downloaded
  with AJAX.

### 22 August 2017. 3.2.1

* Bug fix: result table cells not being sanitised since commit of 19/5/17
* Bug fix: combinator template grader result tables were not hiding hidden
  rows from students but merely colouring them darker.

### 6 August 2017. Version 3.2.0

* Add allow_multiple_stdins option for advanced use of combinator templates.
  This option disables the usual behaviour of running combinator templates
  once for each test when any tests have standard input defined. When enabled,
  the combinator is given all testcases (as when standard input is not present)
  and must itself manage the switching of standard inputs between tests.
* Bug fix: All-or-nothing checkbox was not labelled when using Clean theme
* Bug fix: All-or-nothing grading was not working with per-test-case template
  graders - students were getting partial marks.
* Generate a validate-on-save error message when using a combinator template
  grader that has a test-results table.
* Change **Runtime Error** message to just **Error** since it's not always
  clear what is runtime versus compile time.
* Change DB type of templateparams to text (was char(255)) to allow for
  more elaborate template parameters.
* Use text area for template params rather than a one-line entry field in
  order to cater for longer multi-line template parameters
* Update the uocprototypes.xml file in the samples directory to the latest
  version.
* Incorporate style changes from Open University (thanks Mahmoud Kassaei) for
  improved accessibility of the Ace editor.
* Fix bug in display of "For example" table when question has customised
  columns/headers.
* Improve various tests, e.g. fork bomb.
* Some documentation updates.


### 23 May 2017. Version 3.1.5

* Major bug fix: when grading of questions when precheck enabled, if a student's
  last submission to a question prior to closing the quiz was a precheck that passed,
  the question would be marked correct.
* Bug fix: sample answer and answer preload fields of author form were not
  using the correct Ace language when the question type was first set.
* Bug fix: validate on save not working with support files on the first
  save of the question.
* Bug fix: Show Differences button was not working in Edge browser.
* Bug fix: questions with precheck=selected were not being correctly saved
  in course backup.
* Various documentation and error message tweaks + code tidying.

### 19 March 2017. Version 3.1.4.

* Fix broken code in some of the sample files, update others.

### 2 March 2017. Version 3.1.3

* Fix occasional mishandling of whitespace by Show Differences button.
* Fix bug (introduced in 3.1) in display of hidden test cases to students
      (rather than being suppressed, the hidden test cases were being displayed
      as repeated versions of the preceding visible test).
* Refine handling of Jobe sandbox errors: improve error messages and ensure
      no penalty is applied.
* Replace term "Pre-check" with "Precheck" throughout.

### 1 February 2017. Version 3.1.2+.

* A couple more tweaks to improve appearance with Boost theme
* Add administrator script to analyse prototype usages in a course
      (run script <moodlehome>/question/type/coderunner/prototypeusagesindex.php)
* Fix crash if user attempts to validate a new prototype question.

### 26 January 2017. Version 3.1.2.

Minor updates and bug fixes including:

* Fix broken layout of question authoring form with Boost theme (V3.1.1)
* Fix bugs in PHP and NodeJS question types
* Add Twig STUDENT variable (thanks David Bowes)
* Fix ACE editor gutter showing through Moodle help popups (thanks Tim Hunt)
* Various documentation updates.

### 6 January 2017. Version 3.1.0.
Another major refactoring with some significant new features including:

* A 'Precheck' capability, which presents students with an extra button
      (beside the 'Check' button) that gives a penalty-free submission with
      limited checking as defined by the question author.
* An answerbox preload capability, allowing the question author to define
      some initial text to appear in the question answer box.
* Question authors can request that the sample answer be validated whenever
      a question is saved.
* Simplification of the template mechanism, combining the combinator template
      and the pre-test template into a single template plus an 'iscombinator'
      boolean.
* Reworking of the Show differences button, so it's now implemented entirely
      in JavaScript, removing the complication of having to specify it via
      the column header.
* Addition of C++ 'write-a-function' and 'write-a-program' question types
* Improved accessibility for visually-impaired students (thanks to Tim Hunt).
      The tab key now moves focus through all fields in the question-answering
      form until the student types or clicks in a field. The Ace editor can be
      switched off with CTRL/M.
* A bulk tester allows administrators and authors to check that all question
      sample answers pass all tests (copied, with modifications, from the
      Stack question type).
* The 'Multiple tries' section of the authoring form has been removed and
      a penalty regime is now mandatory. This eliminates the confusion between
      the standard Moodle static question penalty (now hidden) and the formerly
      optional penalty regime. The behaviour of legacy questions is unaffected.

### 6 January 2017. Version 3.0.2.
* Add nodejs question type to built-ins.
* Fix bug in regular-expression grader when Expected has trailing new lines.

### 15 July 2016. Version 3.0.1.
Minor bug fixes, including:

* Use of Show Differences button with questions containing significant
      white space output resulted in premature line truncation and/or
      invalid html output
* Ace editor was not doing syntax highlighting for nodejs questions
* Several panels in question authoring form had monospace labels in
      Moodle 3.1

Also, the documentation for custom template grading has been rewritten.

### 8 February 2016. Version 3.0.0.
A restructured version of the code to conform to Moodle standards. The question behaviour has been deleted from
this project, and is now a separate github project
moodle-qbehaviour_adaptive_adapted_for_coderunner. The
moodle-qtype_coderunner project now contains
just the question type code, which has been moved up the file hierarchy to the
top level.

Discontinued features:

* The runguard sandbox and the Liu sandbox have both been dropped from
      this version. Only the Jobe sandbox is officially supported. The
      ideone sandbox remains as a proof of concept, only. It has never been
      officially supported.
* Support for upgrading from CodeRunner versions prior to 2.4 has been
      dropped.

New features:

* Built-in difference-checker to allow students to see how their output
      differs from the expected output (experimental feature)
* Updated documentation.

### 23 October 2015. Version 2.5.0.
Added a feature to display help on the
selected CodeRunner question type to the question author in an unfoldable
section on the question author form. The displayed help information is the
question text from the prototype question that defines the question text.

Also added a feature that allows the author of a question using a template
grader to abort the test process, e.g. if a pre-run check on the student's
submission failed.

### 15 September 2015. Version 2.4.2.
Various bug fixes, most notably to fix broken
export of custom question prototypes. Other minor changes and bug fixes include:

* Minor documentation tweaks, e.g. a warning on the perils of duplicating
      question prototypes and correction to regular expression grader help.
* Fix broken styling of result table with Moodle 2.9 (odd/even row
      colours weren't happening).
* Improve the error message issued if a prototype fetch fails.
* Fix bug that caused datafiles to get lost when a question was moved to
      from the special "Default fo quiz" category.
* Improve error message from JobeSandbox e.g. from network failures.
* Fix incompatibility with older (pre 5.4) versions of PHP
* Fix wrong error message issued on inconsistent test cases.
* Fix bug in initialisation of 'ordering' form fields with >15 test cases.
* Remove some obsolete question types.
* Add some new question types to built-ins and to U of Canterbury set.
* Add some demo questions for new users.
* Fix PostgreSQL incompatibility (thanks Arnaud Trouvé)
* Fix bug in question export when multiple prototypes with the same type
      name exist in different contexts.

### 29/1/15. Version 2.4.1.

* Added code to support use of an API-key when accessing a
jobe server. Fixed bug in advanced question authoring interface - combinator
template was no longer being disabled when per-test template was edited.

### Dec 2014/Jan 2015. Version 2.4.0.
Refactored code to conform to Moodle style
guidelines. Added functionality:

* Files can now be attached to prototypes
* An 'ordering' field associated with each test case allows easy reordering
      of test cases
* Use of Ace code editor for template editing
* Prompt for disabling combinator template when per-test-case template
      altered
* Improved ability to pass compile and run parameters to Jobe sandbox


