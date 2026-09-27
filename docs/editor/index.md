# Coderunner Question Editor


## Introduction

The CodeRunner question editor is where you define programming questions: what the student is asked to write, how it gets tested, and how it gets graded. Each question ties a **language** to a set of **test cases** through a **template** — a small Twig program that wraps the student's answer into a complete, runnable program and sends it to the sandbox (Jobe) for execution.

Most questions are created by selecting a built-in question type (e.g. `python3`, `c_function`) and filling in test cases only. The question type selects a prototype that already defines the language, sandbox settings, and template — you inherit all of that for free. When the built-in prototypes are not enough, checking **Customise** exposes the full template and grading controls so you can override anything.

The editor is organised into panels that roughly follow the lifecycle of a submission:

1. **Question Type** — pick the base type and configure how students interact with the question (submit buttons, penalties, feedback timing).
2. **Customisation** — override the template, choose a grader, configure result columns and the student UI plugin.
3. **Answer** — provide a sample answer for validation and an optional preload shown in the student's answer box.
4. **Test Cases** — define the inputs and expected outputs CodeRunner runs against the student's code.
5. **Support Files** — upload files that are placed in the sandbox working directory before each test run.
6. **Attachment Options** — allow students to upload files alongside their code.
7. **Advanced Customisation** — turn this question into a reusable prototype, or fine-tune sandbox resource limits.

For a deep-dive into how templates work, Twig variables, and template graders, see [Templating](docs.php?page=templating.md).

### More documentation
- [Example questions](docs.php?page=example_questions.md) — downloadable question exports you can import into your question bank and experiment with.
- [Walkthrough questions](docs.php?page=example_walkthroughs.md) - example questions walkthrough for by example education
- [Templating](docs.php?page=templating.md) — Twig variables, per-test and combinator templates, template parameters, randomisation, and template graders.
- [Official CodeRunner Website](https://coderunner.org.nz) - Introductory usage including how quizzes look and are used
- [Deep-dive documentation](https://trampgeek.github.io/moodle-qtype_coderunner/) - official documentation discussing usage and installation
- [Author forum](https://coderunner.org.nz/mod/forum/view.php?id=51) - CodeRunner Author Forum for help and inquiries
- [Github Repo](https://github.com/trampgeek/moodle-qtype_coderunner#code-runner) - CodeRunner repository open for contribution

## Table of Contents
1. [Question Type](#question-type)
   1. [Question type selector](#question-type-selector)
   2. [Customise & Show source](#customise--show-source)
   3. [Answer box lines](#answer-box-lines)
   4. [Submit buttons](#submit-buttons)
   5. [Give up](#give-up)
   6. [Feedback](#feedback)
   7. [Marking](#marking)
   8. [Template parameters](#template-parameters)
   9. [Twig controls](#twig-controls)
   10. [UI parameters](#ui-parameters)
2. [Customisation](#customisation)
   1. [Template](#template)
   2. [Template controls](#template-controls)
   3. [Grading](#grading)
   4. [Result columns](#result-columns)
   5. [UI controls](#ui-controls)
3. [Answer](#answer)
   1. [Sample answer](#sample-answer)
   2. [Answer preload](#answer-preload)
   3. [Global extra](#global-extra)
4. [Test Cases](#test-cases)
   1. [Test code](#test-code)
   2. [Standard input](#standard-input)
   3. [Expected output](#expected-output)
   4. [Extra](#extra)
   5. [Test case controls](#test-case-controls)
   6. [Test type](#test-type)
5. [Support files](#support-files)
6. [Attachment options](#attachment-options)
7. [Advanced customisation](#advanced-customisation)
   1. [Prototype](#prototype)
   2. [Sandbox controls](#sandbox-controls)
   3. [Languages](#languages)


---

## Question Type

### Question type selector

<div id="split-view">

<p>Select the question type from the dropdown. This determines which prototype the question inherits from — the prototype defines the language, sandbox, and default template. This field is required before the form can be saved.</p>

<img src="docs.php?page=/images/editorusage_questiontype.jpg" alt="Coderunner question type selector"/>

</div>

---

### Customise & Show source

<div id="split-view">

<div>
<p><strong>Customise</strong> — enables overriding fields inherited from the prototype (template, grader, sandbox settings, etc.). When unchecked the question uses the prototype exactly as defined. Checking this reveals the Customisation panel below.</p>
<p><strong>Show source</strong> — displays the template source to students alongside their answer. Mainly useful for debugging.</p>
</div>

<img src="docs.php?page=/images/editorusage_customise.jpg" alt="Coderunner customise checkboxes"/>

</div>

---

### Answer box lines

Sets the minimum number of rows for the student's answer box. The width fills the available window. If the answer overflows vertically or horizontally, scrollbars appear. Default is 18 — increase for longer programs, decrease for single-expression questions.

---

### Submit buttons

Controls the **Precheck** button shown to students:

| Option | Behaviour |
|---|---|
| Disabled | No precheck button shown |
| Empty | Precheck runs against an empty response |
| Examples | Precheck runs only test cases marked as examples |
| Selected | Precheck runs test cases whose Test type includes precheck |
| All | Precheck runs all test cases |

**Hide check** — hides the main Check button entirely. Useful in Deferred Feedback quiz contexts, or when you want students to precheck before committing to a final submission.

The template can detect whether a run is a precheck using `{{ IS_PRECHECK }}`, which is `"1"` during precheck runs and `"0"` otherwise.

---

### Give up

Controls whether a **Stop and read feedback** button is shown to students:

| Option | Behaviour |
|---|---|
| Never | Button never shown |
| After max marks | Button shown once the student can no longer improve their mark (due to the penalty regime) |
| Always | Button always shown |

---

### Feedback

Controls when test result feedback is shown to students:

| Option | Behaviour |
|---|---|
| Use quiz setting | Defers to the quiz-level feedback setting |
| Show | Always show feedback after submission |
| Hide | Never show feedback after submission |

---

### Marking

**All or nothing** — when checked, the student receives full marks only if all test cases pass; any failure gives zero. Per-test-case mark weights are disabled when this is on. If using a TemplateGrader that awards part marks per test case, leave this unchecked.

**Penalty regime** — a comma-separated list of penalty percentages (absolute, not cumulative) applied to successive wrong submissions. Spaces may be used instead of commas. The `...` suffix extends the last two values as an arithmetic progression up to 100.

Example: `0, 5, 10, 30, ...` expands to `0, 5, 10, 30, 50, 70, 90, 100`

Set to `0` for no penalties. The penalty regime is ignored entirely when the quiz uses the *Adaptive (no penalties)* behaviour.

The site-wide default penalty regime can be configured by an administrator under *Site administration > Plugins > Question types > CodeRunner*.

---

### Template parameters

A JSON object whose key/value pairs are merged into `QUESTION.parameters` and made available to Twig when the template (and all other fields if **Twig All** is on) is expanded.

```json
{
    "isfunction": true,
    "pylintoptions": ["--disable=missing-final-newline"],
    "errormessage": "Pylint is not happy with your program"
}
```

Use in a template as `{{ QUESTION.parameters.isfunction }}`, or just `{{ isfunction }}` when **Hoist template params** is enabled.

The **Preprocessor** dropdown controls how this field is evaluated before the template runs:

| Preprocessor | How it works |
|---|---|
| None | Field is used as literal JSON |
| **Twig** | Field is run through Twig first; use `random()` here for randomisation. Runs in PHP — fast, no sandbox cost. |
| Python3 / Java / etc. | Field is a program whose stdout must be a JSON string. Runs on the Jobe sandbox — has a cost per student attempt. |

> **Performance warning**: non-Twig preprocessors with *Evaluate per try* checked run a sandbox job for every student when they open the question. In large exams this can overload the Jobe server. Prefer Twig for randomisation.

For randomisation examples and the full preprocessor reference see [Templating — Template parameters](docs.php?page=templating.md#template-parameters).

---

### Twig controls

| Control | Description |
|---|---|
| Hoist template params | Copies every key from `QUESTION.parameters` into the top-level Twig namespace so `{{ mykey }}` works instead of `{{ QUESTION.parameters.mykey }}`. Enabled by default on new questions. |
| Extract code from JSON | Extracts student code from a JSON response. Required when using non-Ace UI plugins (e.g. the Gapfiller UI) that serialise the answer as JSON. |
| Twig all | Runs Twig expansion over **all** question fields — question text, sample answer, answer preload, UI parameters, and all test case fields — using the template parameters as the environment. This expansion happens when the question is **first initialised** (before a student opens it). The template itself is expanded later, when the student submits. Required for randomised questions where question text and test cases must reflect randomised values. |
| Preprocessor | How the Template parameters field is evaluated (`None` = literal JSON, `Twig` = fast PHP-side expansion, `Python3` / `Java` / etc. = sandbox program whose stdout is the JSON). |
| Evaluate per try | Re-evaluates template params on every student attempt. Required for per-attempt randomisation with non-Twig preprocessors. If unchecked with a non-Twig preprocessor, params are computed once at save time — useful for generating static question content but not for randomisation. |

---

### UI parameters

A JSON object of parameters passed to the selected UI plugin. Valid keys depend on the plugin chosen. For example, for the Ace editor:

```json
{"theme": "monokai", "font_size": 14}
```

---

## Customisation

Visible only when **Customise** is checked, found under [Question type](#question-type).

### Template

The Twig template that wraps the student's answer to produce an executable program. The template is expanded by the Twig engine, then the resulting source is compiled (if needed) and run on the sandbox.

Twig syntax:
- `{{ expr }}` — outputs a value
- `{% tag %}` — control flow (`if`, `for`, `set`, etc.)

A minimal C per-test template looks like:

```c
#include <stdio.h>

{{ STUDENT_ANSWER }}

int main() {
    {{ TEST.testcode }};
    return 0;
}
```

The full set of Twig variables available in the template (`TEST`, `STUDENT_ANSWER`, `QUESTION`, `STUDENT`, `QUIZ`, `IS_PRECHECK`) is documented in [Templating — Twig context variables](docs.php?page=templating.md#twig-context-variables).

When inserting `STUDENT_ANSWER` as a string literal rather than raw code, use an escaper to prevent embedded quotes from breaking the syntax — e.g. `{{ STUDENT_ANSWER | e('py') }}` for Python. See [Templating — Twig escapers](docs.php?page=templating.md#twig-escapers).

---

### Template controls

**Is combinator template** — when checked, the template is expanded **once for all test cases together** rather than once per test case. The template receives the full `TESTCASES` list and produces a single program whose output contains all test results separated by a delimiter. This cuts sandbox round-trips and speeds up grading.

A minimal combinator template:

```c
{{ STUDENT_ANSWER }}

int main() {
{% for TEST in TESTCASES %}
    { {{ TEST.testcode }}; }
    {% if not loop.last %}printf("#<ab@17943918#@>#\n");{% endif %}
{% endfor %}
    return 0;
}
```

CodeRunner automatically falls back to per-test mode if any test case has `stdin` defined (unless **Allow multiple stdins** is on). It also falls back if the combined run produces any output to stderr — this ensures the student gets credit for as many valid tests as possible. Combinator TemplateGraders are exempt from the stderr fallback: they must handle all runtime errors themselves and must always return a valid JSON outcome.

**Allow multiple stdins** — allows different `stdin` values per test case in combinator mode.

**Test splitter regex** — the regex used to split the combined output back into per-test-case results. The default delimiter is `#<ab@17943918#@>#`. Only active when combinator mode is on.

---

### Grading

| Grader | Behaviour |
|---|---|
| EqualityGrader | Passes if output exactly matches expected; trailing whitespace stripped from all lines, trailing blank lines removed |
| NearEqualityGrader | Like EqualityGrader but also collapses multiple spaces/tabs to one space, removes all blank lines, and lowercases both sides |
| RegexGrader | Expected field is a PHP regex (no delimiters); passes if a match is found anywhere in the output. Use `\A`…`\Z` to match the full output. Matching uses MULTILINE and DOTALL. |
| TemplateGrader | Template returns a grade and optional feedback rather than just output |

With **TemplateGrader** the template is responsible for both running the student's code and deciding the grade. Instead of producing program output, the template must print a JSON object. The `expected` field of each test case is ignored entirely.

For a **per-test** TemplateGrader the JSON controls one row of the results table:

| Field | Required | Description |
|---|---|---|
| `fraction` | Yes | 0.0–1.0; multiplied by `TEST.mark` to get awarded marks |
| `got` | No | Text shown in the "Got" column |
| `abort` | No | If `true`, marks this test wrong and skips all remaining tests |
| Any other key | No | Appears as an extra column if listed in Result columns |

```python
import json, subprocess

student = """{{ STUDENT_ANSWER | e('py') }}"""
# ... run the code, compute result ...
print(json.dumps({"fraction": 0.5, "got": "Half the tests passed."}))
```

For a **combinator** TemplateGrader the JSON controls the entire feedback panel (`fraction`, `prologuehtml`, `testresults`, etc.). See [Templating — Template graders](docs.php?page=templating.md#template-graders) for the full field reference.

The `TEST.extra` field (set per test case in the [Extra field](#extra)) is how you pass grader-specific data — expected return values, tolerances, and so on — into a TemplateGrader template.


---

### Result columns

A JSON array controlling which columns appear in the test results table and how they are labelled. Leave empty for the default: `testcode`, `stdin`, `expected`, `got` (empty columns are automatically hidden).

Each entry is a list of at least two strings: `["Header label", "field_name"]`. An optional third element is an `sprintf` format string.

Available fields from the test result object:

| Field | Source |
|---|---|
| `testcode` | Test case code |
| `stdin` | Standard input |
| `expected` | Expected output |
| `got` | Actual output from sandbox |
| `extra` | Extra field of the test case |
| `awarded` | Marks awarded for this test |
| `mark` | Maximum marks for this test |

Per-test TemplateGraders may add their own fields, which can also be selected here.

To **combine multiple fields** into one column, add extra field names before the format string (format then becomes mandatory):
```json
[["Mark Fraction", "awarded", "mark", "%.2f/%.2f"]]
```

Use `%h` as the format to treat the field as raw HTML (not escaped) — useful for templates that output SVG or other markup.

> **Note**: Result columns have no effect when using a combinator TemplateGrader. The template is then responsible for formatting the entire result table via the `testresults` field of its JSON output.

---

### UI controls

**Student answer UI** — the interface shown to students for entering their answer:

| Plugin | Description |
|---|---|
| ace | Syntax-highlighted code editor (default) |
| none | Plain text box with no syntax highlighting |
| scratchpad | Ace editor with an in-browser Run button for penalty-free test runs |
| table | Spreadsheet-style grid of text areas (used e.g. by `python3_program_testing`) |
| graph | Visual graph/node editor for FSM and graph-drawing questions |
| gapfiller | Fill-in-the-gap interface driven by HTML in the Global extra field |
| html | Fully custom HTML interface |

The selected UI also applies to the Sample Answer and Answer Preload fields within the editor. Students and authors can toggle all UI plugins on the current page with **Ctrl-Alt-M** (useful for inspecting raw serialisations).

**Use ace** — enables the Ace editor for the Template and Template parameters boxes in the editor itself (not the student UI).

---

## Answer

### Answer

A correct answer to the question. If provided and **Validate on save** is checked, this answer is run against all test cases when the question is saved — the save fails if any test case fails. Also used by the bulk tester script and as the answer when the quiz's "Fill in correct responses" feature is used. Optionally shown to students during review.

---

### Answer preload

Text pre-filled into the student's answer box when the question first loads. Useful for providing a function signature, import statements, or a partial solution.

---

### Global extra

Extra text available to the template as `{{ QUESTION.globalextra }}`. Applies globally across all test cases. Useful for shared setup code or data that the template needs but that isn't part of any individual test case.

---

## Test Cases

Each test case defines one run of the student's code. Tests are executed in **ordering** sequence.

### Test code

Code passed to the template as `{{ TEST.testcode }}`. In a standard (non-combinator) template this typically means calling or invoking the student's function with specific inputs.

---

### Standard input

Text fed to the program's stdin during execution. Leave empty if the program does not read from stdin.

---

### Expected output

The output the program must produce to pass this test case. Compared against actual output by the selected grader. Available in the template as `{{ TEST.expected }}`.

---

### Extra

Additional data passed to the template as `{{ TEST.extra }}`. Ignored by the standard output-comparison graders (Equality, NearEquality, Regex). Use it with a [TemplateGrader](#grading) to pass per-test grader configuration — expected return values, tolerances, scoring rubrics, or anything the template needs beyond the test code and stdin.

---

### Test case controls

| Control | Description |
|---|---|
| Use as example | Marks this test as a visible example shown to students before they submit |
| Display | `SHOW` / `HIDE` / `HIDE_IF_FAIL` / `HIDE_IF_SUCCEED` — controls whether this test row appears in the results table |
| Hide rest if fail | If this test fails, all subsequent test rows are hidden from the student |
| Mark | Per-test weight used when **All or nothing** is off |
| Ordering | Execution order; lower numbers run first. Defaults to 10, 20, 30… |

---

### Test type

Controls which submission button triggers this test case:

| Type | Triggered by |
|---|---|
| Normal | Check only |
| Precheck | Precheck only |
| Both | Check and Precheck |

---

## Support files

Files uploaded here are copied into the sandbox working directory before each test run. Use for data files, helper modules, or any resource the student's code needs to `import` or `open`. When attachments are also allowed, uploaded attachment filenames are available in the template as `{{ ATTACHMENTS }}` (a comma-separated list).

---

## Attachment options

Allows students to upload files alongside their code submission.

| Field | Description |
|---|---|
| Allow attachments | Maximum number of files a student may attach (0 = none, -1 = unlimited). Attached files are copied into the sandbox working directory; their names are available in the template as `{{ ATTACHMENTS }}`. |
| Attachments required | Minimum number of attachments the student must provide before the response is graded |
| Allowed filenames (regex) | A PHP (Perl) regex the uploaded filenames must match, e.g. `.+\.py` for any Python file. Leave empty to allow any filename. Filenames must also contain only alphanumeric characters plus `_`, `-`, `.`; must not start with `__`; and must not clash with support file names. |
| Filename explanation | Plain-language description of the filename rule shown to students. Leave empty to display the regex itself; leave both fields empty to skip regex checking entirely. |
| Max file size | Maximum size per uploaded file in bytes. Large files with large classes can impact performance and disk space on Moodle and Jobe servers. |

> **Warning**: allowing attachments can have performance and disk-space implications. Moodle and pre-2019 Jobe servers store all attachments indefinitely.

---

## Advanced customisation

### Prototype

**Is prototype** — marks this question as a reusable prototype that other questions can inherit from. Set to `Yes (user defined)` and enter a **Type name** to make it available in the question type selector.

The type name must be unique within the course context and cannot duplicate a built-in prototype name. Subsequent changes to a prototype propagate to all derived questions unless those questions have themselves been customised (customisation breaks the inheritance link).

Inheritance is **single-level only**: when a question is saved as a prototype it loses its own connection to its original base type and becomes a new base type in its own right.

> **Export warning**: when exporting questions derived from a custom prototype, you must include the prototype question in the export too, or the derived questions will be orphaned when imported elsewhere. It is strongly recommended to rename prototypes to something like `PROTOTYPE_for_my_type_name` to make maintenance easier.

---

### Sandbox controls

Overrides the sandbox execution environment for this question. Leave blank to inherit from the prototype.

| Field | Description |
|---|---|
| Sandbox | Which sandbox to use (`DEFAULT` uses the site-wide setting) |
| CPU time limit (secs) | Maximum CPU time per test run. Blank uses the sandbox default (typically 5 s). |
| Memory limit (MB) | Maximum RAM per test run, including all libraries, interpreters, and VMs. Blank uses a language-dependent default; `0` means no limit. |
| Sandbox params | JSON object of sandbox-specific parameters (see below) |

The **Parameters** field accepts a JSON object. For the Jobe sandbox, recognised keys include:

| Key | Effect |
|---|---|
| `compileargs` | List of extra compiler flags, e.g. `["-std=c89"]` |
| `linkargs` | Extra linker flags |
| `runargs` | Extra arguments passed to the program at runtime |
| `interpreterargs` | Extra arguments passed to the interpreter |
| `disklimit` | Maximum disk usage (KB) |
| `streamsize` | Maximum output stream size (KB) |
| `numprocs` | Maximum number of processes |
| `jobeserver` | Override the Jobe server for this question, e.g. `"myspecialjobe.com"` |
| `jobeapikey` | API key for the alternative Jobe server |

---

### Languages

**Language** — the sandbox language identifier (e.g. `python3`, `c`, `java`). Required when creating a prototype; inherited from the prototype otherwise. Change with caution — it affects how the sandbox compiles and runs the generated program.

**Ace language** — the language used for syntax highlighting in the Ace editor. Defaults to the sandbox language. Set it to something different only when the template language differs from what the student writes (e.g. a Python template that preprocesses and runs a student's C code).

**Multilanguage questions** — set Ace language to a comma-separated list to let students choose their language from a dropdown (e.g. `C, C++, Java, Python3`). Prefix exactly one language with `*` to make it the default shown on first load (e.g. `C, C++, *Java, Python3`). If no default is specified the dropdown starts empty and the student must choose.

Multilanguage questions require a template that reads `{{ ANSWER_LANGUAGE }}` to decide how to execute the code. The `ANSWER_LANGUAGE` variable is only defined for multilanguage questions. If a sample answer is provided it must be written in the default language (or the first listed language if no default is set).

---

## See also
- [Templating](docs.php?page=templating.md) — Twig variables, per-test and combinator templates, template parameters, randomisation, and template graders.
- [Walkthrough questions](docs.php?page=example_walkthroughs.md) - example questions walkthrough for by example education
- [Example questions](docs.php?page=example_questions.md) — downloadable question exports you can import into your question bank and experiment with.
- [Official CodeRunner Website](https://coderunner.org.nz) - Introductory usage including how quizzes look and are used
- [Deep-dive documentation](https://trampgeek.github.io/moodle-qtype_coderunner/) - official documentation discussing usage and installation
- [Author forum](https://coderunner.org.nz/mod/forum/view.php?id=51) - CodeRunner Author Forum for help and inquiries
- [Github Repo](https://github.com/trampgeek/moodle-qtype_coderunner#code-runner) - CodeRunner repository open for contribution
