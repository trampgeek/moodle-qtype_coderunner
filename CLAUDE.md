# CLAUDE.md

Guidance for Claude Code (claude.ai/code) working in the CodeRunner question type.

## What this plugin is

CodeRunner lets quiz authors write programming questions. Students submit code, it is executed
against test cases on a Jobe sandbox server, and a grader scores the result. Multiple languages,
a Twig templating layer, and a set of answer-box UIs written as AMD modules.

## CodeRunner 6

Development of version 6 happens on the `Version6` branch, and the design is specified in
`docs/v6/coderunner6-design.md`, with technical notes and open items in
`docs/v6/coderunner6-implementation-notes.md` and the running record of what is built in
`docs/v6/coderunner6-code-checks-and-milestones.md`. **The design doc is the specification; read
it and the notes before planning work.** The UI mockups under `docs/v6/mockups/` are illustrative
only and are not a specification of the field set.

What changed, and what most of the new code is about:

- **A prototype is no longer a question.** Prototypes live in the companion plugin
  `local_coderunner_manager`, which `version.php` declares as a dependency. A question names one
  in its `prototypeid` column and records only its own differences from it, as a JSON document in
  `overrides`. The built-in types are still defined in `db/builtin_PROTOTYPES.xml` here, but it is
  the manager's `prototype_installer` that reads the file and installs them.
- **A prototype can inherit from another prototype**, so a question resolves its settings along a
  chain of layers rather than from a single parent. `classes/v6/prototype_chain.php` resolves it;
  most fields take the nearest layer that sets them, while the collected fields (template
  parameters, UI parameters, support files) merge along the chain.
- **`classes/v6/field_registry.php` is the single source of truth** for the question's settings:
  each field's type, which section of the form it appears in, whether it is inheritable, whether it
  merges, and its default. Code that needs to know something about a field asks the registry rather
  than hard-coding a list. A field name the registry does not know is refused rather than silently
  dropped, which is what makes a typo in a test or a generator an error.
- **The author form is built from the registry** by `classes/v6/settings_form.php`, which the
  manager's prototype editor uses as well, so a question and a prototype are edited by the same
  component. Each inheritable field renders as its value beside an *Inherit* checkbox, in a group.

## Architecture

### Data flow

1. A student submits code through a UI plugin (Ace editor, scratchpad, table, graph, HTML, …).
2. `question.php` builds the program to run by expanding a Twig template.
3. `classes/jobrunner.php` sends it to a sandbox, normally `classes/jobesandbox.php`.
4. A grader scores the result — `classes/grader.php` and its `equality_grader`, `regex_grader`
   and `template_grader` subclasses.
5. `classes/testing_outcome.php` and `test_result.php` package the result, and `renderer.php`
   displays it.

### Key source files

- `questiontype.php` — the Moodle question-type API entry point: save, load, validate, backup.
- `question.php` — the run-time question object, and the grading entry point.
- `edit_coderunner_form.php` — the author form. The settings sections come from
  `classes/v6/settings_form.php`; this class owns the test cases, the sample answer, the
  prototype picker and validation.
- `renderer.php` — the attempt page, including the data attributes a UI plugin reads.
- `classes/twig.php`, `classes/twigmacros.php` — Twig expansion.
- `classes/ui_plugins.php` — discovers the UI plugins and their parameter schemas.

### UI plugins

Each is a pair under `amd/src/`: a JS module `ui_<name>.js` and a JSON schema `ui_<name>.json`
describing its parameters. Ace is the default. A UI reads the text it lays out from data attributes
on the textarea it manages — `data-globalextra`, `data-prototypeextra`, `data-test0` — which
`renderer.php` sets on the attempt page and `edit_coderunner_form.php` sets on the author form.
**Which UI runs is the server's decision alone**, taken from the resolved settings when the page
is built. An `answerboxui.js` once re-took that decision in the browser on every load, and after
the prototype picker rebuilt the form the two disagreed; there is deliberately no JavaScript in
that path now. Both the question type menu and the *Student answer* menu instead **rebuild the
form through a hidden no-submit button**, so the server decides again with the new choice in
hand. A no-submit press neither saves nor validates, so a half-written question survives the
round trip exactly as it was typed.

### Twig templates

Templates turn test-case data into a runnable program. The context includes `STUDENT_ANSWER`,
`TEST.testcode`, `TEST.stdin`, `TEST.extra`, `QUESTION.*`, `STUDENT.*` and `QUIZ.*`. A combinator
template merges every test case into one run; a per-test template runs one case at a time.

## Running the tests

### PHPUnit

From the Moodle root, after initialising the PHPUnit environment:

```bash
vendor/bin/phpunit --testsuite="qtype_coderunner_testsuite"
vendor/bin/phpunit --testsuite="qtype_coderunner_testsuite" --filter=ClassName
```

Tests that run code need a reachable Jobe server, configured in
`tests/fixtures/test-sandbox-config.php` (copy the `-dist` file beside it). Those tests skip
rather than fail when it is absent.

### Behat

The suite is written against the CodeRunner 6 author form and needs these local steps, defined in
`tests/behat/behat_qtype_coderunner.php`:

- `I override the CodeRunner fields "a, b"` — clears each field's *Inherit* box, expanding the
  fieldsets first. **An inherited field is greyed by `disabledIf`, so it cannot be set until this
  has run**, and the override lasts only as long as the page: a field set after a reload needs
  either a fresh override or to have been overridden and saved earlier.
- `I choose the CodeRunner prototype "python3"` — picks a type and waits for the form it rebuilds.
- `I add a CodeRunner question on "python3" filling the form with:` — replaces core's
  *I add a "CodeRunner" question filling the form with:*, which cannot express this form in one
  table, because choosing a prototype rebuilds it and discards whatever was filled in first. It
  overrides whichever fields its own table names, and it **saves the form**, so a scenario that
  must interact with the form before saving cannot use it.
- `I set the ace field "x" to:` and `I should see "..." in the ace field "x"` — Ace hides the
  textarea it manages, so core's field steps cannot see it.

A feature that needs a prototype to exist but is not testing how one is made should say so as
data rather than build one through the editor:

```gherkin
And the following "qtype_coderunner > prototypes" exist:
  | name          | parent  | course | iscombinatortemplate | templatefile      |
  | python3_mine  | python3 | C1     | 1                    | my_template.py    |
```

Every column other than `name`, `parent`, `course` and `summary` becomes an override, validated
against the field registry. A Gherkin cell cannot hold a newline dependably, so a `<field>file`
column reads that field's value from `tests/fixtures/`.

Two checks worth running before paying for a full Behat run, which takes a long time:

- Parse every feature with the vendored `behat/gherkin` — about a second, and it catches a
  malformed table or a mis-indented step.
- Read the features for a field that is set without being overridden anywhere in its scenario.
  This finds the commonest conversion fault, but it is a lead generator and not an oracle: a field
  already overridden *and saved* stays editable on the next page load, which the text cannot show.

### Code style and JavaScript

```bash
phpcs --standard=moodle question/type/coderunner/   # or moodle-plugin-ci codechecker
npx grunt amd                                       # amd/src → amd/build
```

The Grunt build needs the Node version Moodle itself requires, run from this directory. A newly
added AMD module will not load until Moodle's caches are purged, because `lib/requirejs.php`
serves one cached bundle of every module: the symptom is RequireJS reporting *No define call for
qtype_coderunner/yourmodule*, which looks like a build error and is not one.

## Diagnosing browser-side bugs

**Reproduce a browser-side symptom headlessly before asking a human to test it.** Nothing
initialising, a page that "hangs", a UI plugin that never appears: drive the page yourself, with
whatever headless browser and DevTools Protocol client the environment offers, and collect thrown
exceptions, console messages and any network request that never finishes. A queue of "try this,
then try that" is the expensive failure mode.

When the report is "no JavaScript runs and no console errors" — which is nearly
self-contradictory, since broken JS is loud:

- **Suspect the document never became a DOM.** Compare the served HTML with the live DOM:
  `document.querySelectorAll('script').length` against the count in the response, and check
  `typeof require`, `typeof ace`, the `.ace_editor` count and `M.util.pending_js`.
- **Check the markup's own invariants.** Count `<!--` against `-->`, and look for an unclosed
  `textarea`, `script` or `style`. An author-supplied value rendered unescaped can comment out the
  rest of the page, every remaining script tag with it, in complete silence. Anything that puts a
  question's stored value into the page must escape it.
- A rendered page served from another origin is not a faithful reproduction: CORS blocks the AJAX
  and the fonts and fills the console with noise. Drive the real URL.

## Moodle conventions

- **Frankenstyle** naming: `qtype_coderunner`, and `local_coderunner_manager` for the companion.
- **Strings** live in `lang/en/qtype_coderunner.php`; no user-visible English in PHP or JS. A new
  string renders as `[[mystringname]]` until the caches are purged.
- **Schema** in `db/install.xml` (XMLDB), migrations in `db/upgrade.php`, capabilities in
  `db/access.php`, web services in `db/services.php`.
- **PHPDoc** on every class and public method, with `@package`, `@copyright` and `@license`.
- Classes under `classes/` are namespaced `qtype_coderunner\`, and the CodeRunner 6 additions sit
  in `qtype_coderunner\v6\`.
