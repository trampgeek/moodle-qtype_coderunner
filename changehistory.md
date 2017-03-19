# CHANGE HISTORY

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


