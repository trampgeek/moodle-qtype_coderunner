# CHANGE HISTORY

### 11 February 2024. 5.3.0

 * Significant refactoring to improve PHP8.2 compatibility, particularly with regard to dynamic attributes (thanks Anupama).
 * Improve code to identify Java main class (thanks zupanibla).
 * Bug fix: ace-gapfiller UI did not allow non-ASCII alphabetic characters (e.g. Maori macrons)

### 20 December 2023. 5.2.4

 * Extensive code tidying to conform to latest Moodle PHP coding standards.
 * Issue #145: some testcases didn't check if the sandbox were available before running the
   test, causing test failure.
 * Bug fix: locked_cell functionality in table UI was not working (regression mid-year)
 * Improve error reporting when Jobe request fails. 
 * Issue #182: LaTeX embedded in question feedback was not being processed by MathJax
 * Extended the copy-got-to-expected functionality when a saved question failed validation
   to include combinator graders under certain specified conditions.
 * Criterion to delete prototypes from system context tighten to delete only prototypes with
   the string BUILT_IN in their names.
 * Issue #181: Scratchpad UI errors were displayed as JavaScript alerts. Changed to show inline.
 * Issue #179: Multilanguage question type extended to handle Perl, Ruby, C# and Golang
 * Improve twig error messages
 * Strip white space from node and edge labels in GraphUI

### 18 September 2023. 5.2.2

 * Upgrade from MATURITY_RELEASE_CANDIDATE to MATURITY_STABLE

### 8 September 2023. 5.2.1

 * Major change: add scratchpad UI (thanks James Napier). This provides students
   with a mini IDE within each question, where they can test their code without
   making actual Moodle submissions. Requires the coderunner web service to be
   enabled.
 * Added several UI parameters to Ace editor: auto_switch_light_dark, font_size,
   import_from_scratchpad, live_autocompletion, theme.
 * Better error messages for missing/duplicate prototypes.
 * Changes to better support the ace-inline filter (e.g. language checking
   to improve error message if question author has a typo).
 * Make Ace user changes to theme (via Ctrl + ',') sticky.
 * Reduce sync interval time in Ace UI from 5 secs to 2 secs to reduce data loss
   if a quiz times out. Also, reduce default timeout for all UIs from 10s to 5s.
 * Use HTML input elements in the Table UI rather than textareas when there is
   only 1 row per cell to reduce confusion when student hits Enter.
 * Set specific column widths for SQL questions for compatibility with latest
   sqlite3.
 * Change multilanguage question type so that answer code cannot be entered
   until a language has been selected but the user can step through
   the question (unanswered) without being required to select a language.
 * Prevent grading of an unchanged preloaded answer.
 * Add instructorhtml functionality to combinator grader so that a teacher can
   see HTML feedback that's hidden from student.
 * Changed implementation of per-user rate throttling for web-service traffic
   to reduce the risk of log-manager SQL queries causing hangs (if that was
   indeed happening - problem was never fully diagnosed).
 * Issue a specific "URL blocked" error message when Moodle HTML security
   is blocking outgoing HTTP requests.
 * Some changes for PHP 8.1/8.2 compatibility.
 * Various code tidying.
 * Bug fix: the UI parameters were not being loaded correctly for non-Ace UIs
   when the question type was first selected.
 * Bug fix: the UI parameters from the prototype should be ignored if
    the UI has changed from that of the prototype.
 * Bug fix: sample answer attachments were not being included when previewing
   or bulk testing.

### 9 November 2022. 5.1.1

 * Tweak to AJAX code to allow CodeRunner to run in Docker Desktop on Linux
   (as distinct from Docker Engine).
 * Refactoring of phpunit tests to facilitate testing of individual classes
   or methods.

### 5 November 2022. 5.1.0

 * On review page, hide question author's answer by default with a link to
   click to show it.
 * Improve error message when the Moodle security settings are blocking the
   Jobe connection.
 * Increase size of Ace window when displaying more lines of code than will
   fit the specified or default text area size.
 * Bug fix: questions with student file attachments were sometimes not displaying
   correctly in Moodle 4.0
 * Bug fix: GraphUI plugin wasn't working in the question authoring page in Moodle 4.0
 * Bug fix: Octave function question type was producing unhelpful "error sourcing
   file" when the answer contained a syntax error and a more recent version
   of Octave was being used.
 * Improve testing by turning off alerts  when behat is running, plus other
   tweaks.
 * Some documentation tweaks.

### 5 September 2022. 5.0.1

 * Add uninstall instructions to Readme.md

### 22 August 2022. 5.0.0

 * Release candidate for Moodle 4.0.
 * Document the 'columnformats' field used by some combinator-template graders.
 * Improve output of prototype-usage script.
 * Make Behat tests a bit faster and more robust (Thanks Tim Hunt)
 * Bug fix: exporting of questions with missing prototypes was failing.


### 26 February 2022. 4.2.3+

 * Add specific capability (qtype/coderunner:viewhiddentestcases) for viewing
   hidden test cases (default to existing built-in moodle/grade:viewhidden).
   Thanks Tim Hunt.

### 27 January 2022. 4.2.3

 * Implement missing failMessage() method in HTML UI plugin, which resulted
   in an alert plus a JavaScript error message on the console rather than the
   expected warning message on the answer box.

### 21 January 2022. 4.2.1+

 * Stop-and-read-feedback option added.
 * New experimental web service added that allows AJAX access to the
   CodeRunner sandbox (usually Jobe). Disabled by default. Supports the new
   experimental ace_inline_code filter (find it on github).
 * Allow a comma-separated list of Jobe servers, from which one is randomly
   selected for any given job.
 * Improve error handling when a combinator template grader fails, particularly
   with PostgreSQL servers.
 * Plug security hole in Twig that could be exploited to allow question authors
   to run code on the Moodle server itself.
 * Add graderstate functionality for use by combinator template graders, allowing
   them to customise grade and feedback according to prior submissions.
 * Add field coderunnerversion to stepinfo.
 * In TableUI make num_rows = 2 an explicit default.
 * Bug fix: an erroneous assert statement was causing PHP warnings to be logged.
   regarding prototype already loaded if assert checking was turned on.
 * Visibility of failing questions in bulk tester improved.
 * Various code refactoring to conform to moodle standards.
 * Various documentation tweaks.

### 26 September 2021. 4.1.0+

 * Bug fix: ace_gapfiller UI doesn't display warning if Ace editor not loaded.
 * Change default value of Evaluate-per-student when using Jobe sandbox to
   evaluate template parameters from True to False. Generate warning message
   only when author changes the checkbox to True.
 * Various documentation tweaks (main documentation + in-line).

### 28 August 2021. 4.1.0

 * New feature (experimental): A new UI, the Ace gapfiller, allows gap-filler questions in
   which code is displayed by the Ace editor with gaps for the students to fill in.
 * New feature: allow use of support files by Jobe-based template-parameter preprocessors.
 * New feature: add /ace/ext-static_highlight.js to the list of Ace scripts to
   include. This allows for the possibility of using Ace to statically highlight code,
    e.g. when embedded in question specifications.
 * Remove PHP code from language string file which was raising an exception in AMOS.
 * Add missing Twig text area macro for using in HTML-UI questions
   (documented but never actually implemented).
 * With combinator template graders, only render the result table if there is at least
   one non-header row to display.
 * Change the default value for 'Evaluate per student' when using Jobe languages
   to evaluate the template parameters from True to False.
 * Various documentation and error message tweaks.
 * Deleted various autotag scripts which were potentially unsafe in various ways
   and shouldn't ever have been part of the distribution anyway.
 * Bug fix: existing gapfiller UI was not working correctly with textarea gaps.
 * Bug fix: saving a question with an undefined question type could crash.
 * Bug fix: students with spaces or apostrophes in their names were breaking
   question that used Jobe-based template-parameter preprocessors.
 * Bug fix: sample answer for multilanguage questions were not being correctly
   displayed.
 * Bug fix: multilanguage questions were not being validated using the 'answer_language'
   template parameter but were instead using the default language.


### 9 May 2021. 4.0.2
 * Added a `lines_per_cell` parameter to the table UI.
 * Bug fix: template preprocessor runs broke if a student had an apostrophe or space
   within their first or last names.
 * Bug fix in 4.0.0: Ace editor hung in a render loop when displaying template parameter
   field.
 * Bug fix: PHP errors were generated if the template parameters were bad, e.g.
   if a template parameter preprocessor run failed.
 * Bug fix: the sample answer for multilanguage questions was not being
   correctly displayed in a quiz review.


### 2 March 2021. 4.0.0
 * Add template parameter preprocessing capability that allows uses of languages
   other than Twig for generating the JSON template parameter set.
 * Separate UI-plugin parameters from template parameters and provide an improved UI
   that lists all available UI parameters and their meanings for the currently
   selected UI.
 * Update Twig to the latest version (3.1).
 * Add QUESTION.stepinfo to the Twig environment. This is a record with attributes
   preferredbehaviour, numchecks, numprechecks and fraction allowing authors
   to provide more elaborate feedback according to quiz mode and previous submissions.
 * Add a macro \_\_\_textareaId\_\_\_ to the HTML-UI that gets replaced by the id
   of the textarea element that the HTML-UI is operating on.
 * Add special \_\_twigprefix\_\_ that, if defined in a question's prototype,
   provides content (e.g. Twig macros) that is inserted at the start of all
   Twig-expanded question fields.
 * Reduce unnecessary calls to the Jobe server to get its list of supported
   languages when there is only one sandbox available (the usual case nowadays).
 * Bug fix: nodejs programs in ESM style were breaking. So change filename extension to .js
 * Bug fix: Ace plugin was generating duplicate (and wrong) ids when multiple
    ace editors were present in a form.
 * Bug fix: Ace editor was not being initialised to the correct language with multilanguage
   questions for which an explicit default language was specified.
 * Bug fix: some non-inherited fields were being mistakenly loaded from the
    prototype when changing question type via Ajax.
 * Bug fix: %h formats for columns were being ignored in the "For example" table.

### 15 October 2020. 3.7.9+

 * Bug fix: built-in prototypes for directed-graph and undirected graph give
   Python exception if user drags edge labels.
 * Workaround for issue #103 - CodeRunner upgrade failing with Moodle versions above 3.9.1+
 * Minor documentation tweaks.

### 3 July 2020. 3.7.9

 * Several graphUI enhancements: undo/redo, cursor movement with arrow keys when
   editing text, dragging of link label text, adding of a Clear button (thanks Eric Song).
 * Removed now-defunct linklabelreldist template parameter from graphUI.
 * Improved (I hope) display of bulk test categories.
 * Remove "Experimental" tag from various established features.
 * Bug fix: embedded example code in the author form's on-line help was not being displayed
   in Moodle 3.9.
 * Bug fix: files attached to a question as part of the sample answer were not being
   copied into the course backup.
 * Bug fix: html\_UI questions were not displaying the author's sample answer.
 * Bug fix: customising a question to use a non-standard Jobe server did not
   work if that server required an API key.
 * Several documentation tweaks.


### 26 June 2020. 3.7.8

 * Correct faulty documentation of import of html module within Twig and
   misuse of htmlentities in documentation.
 * Bug fix: displaying the question author's solution to a question with the
   UI plugin explicitly set to None generates a PHP warning regarding an undefined
   constant fieldid.
 * Bug fix: The Show Differences button was comparing the wrong two columns
   in Moodle 3.9.
 * Add a linklabelreldist template parameter to the GraphUI to allow positioning
   of link labels at relative distances other than 0.5 along the link. Supported
   only with straight links.
 * Two tweaks to the test suite.

### 11 May 2020. 3.7.7

 * Bug fix: viewing of combinator grader outputs from previous versions of
   CodeRunner gave Undefined property: $outputonly PHP Notices.
 * Add graphui demo question to samples.

### 19 April 2020. 3.7.6

 * Add a 'showoutputonly' option to combinator template graders for use
   in 'sandpit' questions that allow students to experiment with code
   and see text and/or image output without penalty.
 * Include a demonstration of the showoutputonly option in the samples folder.
 * Bug fix: Prevent PHP Notice Undefined property: qtype_coderunner_question::$parameters
   when viewing a question without template parameters.
 * Bug fix: java main method declarations with static public main rather than
   public static main were not being accepted.

### 3 March 2020. 3.7.5+.

 * Add a 'textoffset' template parameter to GraphUI base question types.
 * Update documentation of GraphUI.
 * Display a message 'Run on University of Canterbury's Jobe server' when
   this is being used with a custom API key.
 * Bug fix: Show differences button was not being rendered correctly as a button
   in Moodle 3.8.
 * Bug fix: In graphUI, self-links, i.e. edges that start and end at the same node,
    could not be labelled.
 * Require latest version of qbehaviour_adaptive_adapted_for_coderunner

### 25 January 2020. 3.7.5

 * Display a warning message whenever a question is run using the default
   University of Canterbury Jobe server, which is intended only for initial
   CodeRunner testing, not production use.
 * Display sample answer using the selected user-interface wrapper (e.g. Ace)
   rather than just showing the straight text version.
 * Replace Ace editor code with the latest full source version rather than
   an older minimised version. This turns out to greatly speed up the Ace
   editor loading, because the Moodle JavaScript minimiser choked when
   re-minimising the code.
 * Bug fix: combinator template grader questions were being run in multiple
   Jobe submissions - one per test - when standard inputs were provided
   to the tests and Allow multiple stdins was not checked.
 * Bug fix: Questions using gapfiller_ui did not allow editing of sample
   answer when the html code source was the first test case rather
   than globalextra.
 * Bug fix: answer preload button did not have correct CSS class.
 * Bug fix: Show Differences button was not working when Result Table had no
   Test column.

### 19 November 2019. 3.7.4+

 * Change testcase numbering to 10, 20, ... to simplify insertion at start.
 * Fix error in documentation relating to column formats with combinator template
   grader.
 * Incorporate Tim Hunt's behat test updates for Moodle 3.8 (thanks Tim).
 * Change default for validateonsave from false to true.
 * Re-order Support files and Attachment options sections in question editing form.
 * Bug fix: Questions using gapfiller_ui did not allow editing of sample
   answer when the html code source was the first test case rather
   than globalextra.
 * Bug fix: bulk tester was not correctly testing multilanguage questions
   in which the sample answer was not the default i.e. was not recognising
   the answer_language template parameter.

### 7 November 2019. 3.7.3

 * Regression fix: questions using the Twig STUDENT variable were not able
   to be correctly reviewed by a teacher after submission by student.

### 1 November 2019. 3.7.2

 * Regression: upgraded Twig barfs on questions with null (as opposed to
   empty string) template parameters.

### 30 September 2019. 3.7.1

 * Add a new experimental Ajax service that allows a question to display the
   question specification from a pdf file within a .zip support file, such as
   a standard ICPC exported programming contest question. Alpha version,
   still undocumented.
 * Update Twig to the latest version of the 1.n branch.
 * Allow specification of https protocol for communication to Jobe, say if
   it's behind a reverse proxy to terminate the SSL connection (thanks Eric
   Villard).
 * Fix missing MoodleQuickForm::hideIf method for Moodle versions < 3.4 (thanks
   Eric Villard)
 * Add new experimental user interface (ui_gapfiller) to support "fill in the
   gaps" questions.
 * Fix broken nodejs prototype.
 * Various tweaks to documentation.
 * Lots of minor edits to reduce style-checker warnings

### 14 August 2019. 3.7.0

 * Addition of a globalextra field to all questions for use by question authors
   as a parameter global to all tests.
 * Various enhancements and refactoring of TableUI including addition of a
   table-row-labels template parameter.
 * Modify the still experimental and undocumented HtmlUI to get the raw HTML
   from the new globalextra field rather than via a template parameter.
 * Bug fix: UI plugins that depend on template parameters, notable TableUI,
   broke if Twig code was used within the template parameters, e.g. for
   randomisation.

### 28 July 2019. 3.6.1+

 * Add table_locked_cells template parameter to Table UI
 * Bug fix: student file attachments don't work in conjunction with
   author-supplied support files.
 * Bug fix: attaching files after submitting a question without attachments gave
   a runtime error

### 22 July 2019. 3.6.1

 * Bug fix: if a prototype and a derived question had support files with
   the same name, the prototype file was being used instead of the override
   file in the child.
 * Add "locknodes" and "lockedges" template parameters to GraphUI to allow
   question authors to preload answerbox with a particular graph and prevent
   students altering the topology.
 * Add behat test for TableUI

### 21 June 2019. 3.6.0+

 * Fix wrong default for useace in upgrade.php (thanks Mahmoud Kassaei).
 * Fix snip method when displaying overly-long output containing multibyte strings
   (thanks Ivan Marichev).

### 26 February 2019. 3.6.0+

 * Bug fix: the Reset Answer button was not working correctly with TableUI
   questions.
 * Improve detection of syntax errors in penalty regime and extend syntax to
   allow space separation as well as comma separation.
 * Prevent annoying flashing of GraphUI help screen.
 * Document linkargs in C program question type language string.
 * Add youtube video links to Readme.md
 * Styling improvements for input areas (thanks Tim Hunt).

### 30 January 2018. 3.6.0

 * Add a new experimental feature that allows students to attach files to
   their submissions. The attached files are loaded into the working directory
   during the run.
 * Add a *Feedback* dropdown to the question authoring form that allows the
   question author to display or hide the result table regardless of the
   behaviour mode chosen for the quiz.
 * Fix bug in missing prototype error message (extraneous junk included, due
   to an exception being thrown).

### 18 November 2018. 3.5.3+

 * Bug fix: answerbox preloads of greater than ~1k generated debugging error
   messages (if developer-level messages were enabled). Thanks Tim Hunt.
 * Bug fix: Twig-all was not being applied to the question's General Feedback.
 * Improve handling of a failed unserialise of a legacy question attempt, which
   can (rarely) occur if a complete course, including student activity attempts,
   is moved between sites with different Moodle versions or database charsets.
 * Improve handling of Twig errors when editing questions that use TwigAll in
   conjunction with ValidateOnSave.

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


