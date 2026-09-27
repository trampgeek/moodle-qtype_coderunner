# CodeRunner Templating
### Back to the [index](docs.php?page=index.md)

## Table of Contents
1. [Overview](#overview)
2. [Per-test templates](#per-test-templates)
3. [Combinator templates](#combinator-templates)
4. [Twig context variables](#twig-context-variables)
   1. [STUDENT_ANSWER](#student_answer)
   2. [TEST](#test)
   3. [TESTCASES](#testcases)
   4. [IS_PRECHECK](#is_precheck)
   5. [STUDENT](#student)
   6. [QUESTION](#question)
   7. [QUIZ](#quiz)
5. [Twig escapers](#twig-escapers)
6. [Template parameters](#template-parameters)
   1. [Hoisting](#hoisting)
   2. [Preprocessors](#preprocessors)
   3. [Twig All](#twig-all)
   4. [Randomisation](#randomisation)
7. [Template graders](#template-graders)
   1. [Per-test grader output](#per-test-grader-output)
   2. [Combinator grader output](#combinator-grader-output)

---

## Overview

Every CodeRunner question has a **template** — a [Twig](https://twig.symfony.com/) program that wraps the student's answer and each test case into a complete, executable program. That program is sent to the sandbox (Jobe), compiled if needed, run, and its output compared against the expected output.

Twig syntax:
- `{{ expr }}` — outputs a value
- `{% tag %}` — control flow (`if`, `for`, `set`, etc.)

---

## Per-test templates

The default. The template is expanded **once per test case** and each expansion produces one program that is run separately in the sandbox. The template receives a single `TEST` variable representing the current test case.

```
#include <stdio.h>

{{ STUDENT_ANSWER }}

int main() {
    {{ TEST.testcode }};
    return 0;
}
```

---

## Combinator templates

Checked with **Is combinator template** in the Customisation panel. The template is expanded **once for all test cases together**, producing a single program. Test outputs are separated by a delimiter string (default: `#<ab@17943918#@>#`). CodeRunner then splits the output back into per-test results.

```
{{ STUDENT_ANSWER }}

int main() {
{% for TEST in TESTCASES %}
    { {{ TEST.testcode }}; }
    {% if not loop.last %}printf("#<ab@17943918#@>#\n");{% endif %}
{% endfor %}
    return 0;
}
```

CodeRunner automatically falls back to per-test mode if:
- Any test case has stdin defined (unless **Allow multiple stdins** is checked).
- The student's code causes a runtime abort (so all results up to the failure are shown).

---

## Twig context variables

### STUDENT_ANSWER

The raw text the student typed into the answer box. If using a non-Ace UI plugin this is the *serialised* form (e.g. a JSON list of gap values for the Gapfiller UI).

```
{{ STUDENT_ANSWER }}
```

### TEST

Available in **per-test** templates only. Attributes:

| Attribute | Description |
|---|---|
| `TEST.testcode` | The test code for this test case |
| `TEST.extra` | Extra field text (for TemplateGrader use) |
| `TEST.stdin` | Standard input text |
| `TEST.expected` | Expected output |
| `TEST.mark` | Mark weight for this test |
| `TEST.display` | `SHOW`, `HIDE`, `HIDE_IF_FAIL`, or `HIDE_IF_SUCCEED` |
| `TEST.useasexample` | `1` if the "Use as example" checkbox is set |
| `TEST.hiderestiffail` | `1` if "Hide rest if fail" is checked |
| `TEST.testtype` | `0` = Check only, `1` = Precheck only, `2` = Both |

### TESTCASES

Available in **combinator** templates only. A list of TEST objects (same attributes as above). Iterate with `{% for TEST in TESTCASES %}`.

### IS_PRECHECK

`1` (true) when the student clicked **Precheck**, `0` otherwise. Use this to give different feedback during a precheck versus a full check.

```
{% if IS_PRECHECK %}
    # Precheck mode — lighter feedback
{% endif %}
```

### STUDENT

Information about the currently logged-in student.

| Attribute | Description |
|---|---|
| `STUDENT.id` | Internal Moodle user ID |
| `STUDENT.username` | Username |
| `STUDENT.firstname` | First name |
| `STUDENT.lastname` | Last name |
| `STUDENT.email` | Email address |

### QUESTION

The full question object. Most useful attributes:

| Attribute | Description |
|---|---|
| `QUESTION.parameters` | Object of all evaluated template parameters |
| `QUESTION.uiparameters` | Object of UI parameters (not hoisted) |
| `QUESTION.answer` | The sample answer, or `null` |
| `QUESTION.globalextra` | The Global extra field |
| `QUESTION.answerpreload` | The Answer preload field |
| `QUESTION.questiontext` | The question body text |
| `QUESTION.language` | Sandbox language, e.g. `python3` |
| `QUESTION.stepinfo` | Object with `numchecks`, `numprechecks`, `fraction`, `graderstate` |
| `QUESTION.iscombinatortemplate` | `true` if combinator mode is on |
| `QUESTION.allornothing` | `true` if all-or-nothing grading is on |
| `QUESTION.precheck` | Precheck setting: 0=disabled, 1=empty, 2=examples, 3=selected, 4=all |
| `QUESTION.sandbox` | Sandbox in use, e.g. `jobesandbox` |
| `QUESTION.grader` | Grader class, e.g. `EqualityGrader` |
| `QUESTION.cputimelimitsecs` | CPU time limit (null if not set) |
| `QUESTION.memlimitmb` | Memory limit in MB (null if not set) |

Access template parameters as `{{ QUESTION.parameters.mykey }}`, or just `{{ mykey }}` if [Hoisting](#hoisting) is enabled.

### QUIZ

Information about the quiz the question is running in.

| Attribute | Description |
|---|---|
| `QUIZ.name` | Quiz name, or empty string if not in a quiz |
| `QUIZ.tags` | Array of normalised quiz tags |

---

## Twig escapers

Use an escaper whenever a Twig variable is inserted as a **string literal** inside the template's host language, to prevent embedded quotes or backslashes from breaking the syntax.

```
__answer__ = """{{ STUDENT_ANSWER | e('py') }}"""
```

| Escaper | Use for |
|---|---|
| `e('py')` | Python — escapes `"` and `\` |
| `e('java')` / `e('c')` | Java / C — escapes quotes, newlines, tabs (`\n`, `\r`, etc.) |
| `e('matlab')` | Matlab — escapes `'`, `%`, and newlines |
| `e('js')` | JavaScript (Twig built-in) |
| `e('html')` | HTML (Twig built-in) |

Do **not** use an escaper when the variable is expanded directly into executable code (not a string literal).

---

## Template parameters

A JSON object entered in the **Template parameters** field. Its key/value pairs are merged into `QUESTION.parameters` and made available to Twig when expanding the template (and all other fields if [Twig All](#twig-all) is enabled).

```json
{
    "isfunction": true,
    "pylintoptions": ["--disable=missing-final-newline"],
    "errormessage": "Pylint is not happy with your program"
}
```

Use in template:

```
{% if QUESTION.parameters.isfunction %}...{% endif %}
print("{{ QUESTION.parameters.errormessage | e('py') }}")
```

### Hoisting

Enabling **Hoist template parameters** copies every key from `QUESTION.parameters` into the top-level Twig namespace, so `{{ mykey }}` works instead of `{{ QUESTION.parameters.mykey }}`. Enabled by default on new questions.

### Preprocessors

The **Preprocessor** dropdown controls how the Template parameters field is evaluated before the template runs.

| Preprocessor | How it works |
|---|---|
| None | Field is used as literal JSON |
| **Twig** | Field is run through Twig first; use `random()` here for randomisation. Runs in PHP — fast, no sandbox cost. |
| Python3 / Java / etc. | Field is a program whose stdout must be a JSON string. Runs on the Jobe sandbox — has a cost per student attempt. |

**Evaluate per try** — when using a non-Twig preprocessor, check this so the preprocessor runs fresh for each student attempt (required for per-attempt randomisation). Leave it unchecked to compute params once at save time.

> **Performance warning**: non-Twig preprocessors with *Evaluate per try* run a sandbox job for every student when they open the question. In large exams this can overload the Jobe server. Prefer Twig for randomisation.

### Twig All

When **Twig All** is checked, all text fields of the question — question text, test case code, expected outputs, sample answer — are processed by Twig after the template parameters are resolved. Required for randomised questions where the question text and test cases must reflect the randomised values.

### Randomisation

Randomisation is done entirely in the Template parameters field using the Twig preprocessor and `random()`.

**Basic random choice:**
```json
{ "name": "{{ random(['Bob', 'Carol', 'Ted', 'Alice']) }}" }
```

**Random integer in range** (5–12 inclusive):
```json
{ "number": {{ 5 + random(7) }} }
```

**Per-student (same variant every attempt):**
```
{{- set_random_seed(STUDENT.id) -}}
{ "name": "{{ random(['Bob', 'Carol', 'Ted']) }}" }
```
Call `set_random_seed` before any `random()` call. Using `STUDENT.id` as the seed means a given student always sees the same variant.

**Coupled variables** (animal + sound always match):
```
{
    {% set obj = random([
        {'name': 'Dog', 'sound': 'Woof'},
        {'name': 'Cat', 'sound': 'Miaow'},
        {'name': 'Cow', 'sound': 'Moo'}
    ]) %}
    "animal": "{{ obj.name }}",
    "sound":  "{{ obj.sound }}"
}
```

> **Warning**: once a quiz is live, never change the randomisation logic in the template parameters. The seed is stored but the params are rebuilt on each load — changing the order or values of `random()` calls will give students different questions than they originally saw, invalidating their answers.

---

## Template graders

Select **TemplateGrader** in the Grading dropdown (requires Customise to be checked). In this mode the template is responsible for both running the student's code and deciding the grade. The template's output must be a JSON object — not plain program output.

### Per-test grader output

When using a per-test template with TemplateGrader, the output must be a JSON object defining one result-table row:

| Field | Required | Description |
|---|---|---|
| `fraction` | Yes | A value 0.0–1.0; multiplied by `TEST.mark` to get the awarded marks |
| `got` | No | Text shown in the "Got" column |
| `abort` | No | If `true`, this test is marked wrong and all remaining tests are skipped |
| Any other key | No | Appears as an extra column if listed in Result columns |

```python
import json, subprocess

student = """{{ STUDENT_ANSWER | e('py') }}"""
# ... run the code, compute result ...
print(json.dumps({"fraction": 0.5, "got": "Half the tests passed."}))
```

To abort testing early (e.g. on a syntax error):
```python
print(json.dumps({"fraction": 0.0, "got": "Syntax error in submission.", "abort": True}))
```

### Combinator grader output

When **Is combinator template** is also checked, the JSON object controls the entire feedback panel:

| Field | Description |
|---|---|
| `fraction` | Total mark 0.0–1.0 |
| `prologuehtml` | HTML displayed before the result table |
| `epiloguehtml` | HTML displayed after the result table |
| `instructorhtml` | HTML visible only to instructors/markers |
| `testresults` | List of lists forming a result table; first row is column headers. Special headers: `iscorrect` (tick/cross), `ishidden` (hide row from students) |
| `columnformats` | List of format strings per column: `%s` (safe pre-formatted text) or `%h` (raw HTML) |
| `showoutputonly` | If `true`, suppresses the pass/fail banner; useful for sandbox/experiment questions |
| `showdifferences` | If `true`, shows a "Show differences" button when the student doesn't get full marks |
| `graderstate` | Arbitrary string stored in the DB and passed back as `QUESTION.stepinfo.graderstate` on the next attempt |
| `files` | JSON object mapping filenames to base64-encoded content; URLs are substituted into `src=` and `href=` attributes in HTML fields |

```python
import json

# ... run all test cases, build rows ...
result = {
    "fraction": 0.8,
    "prologuehtml": "<p>4 of 5 tests passed.</p>",
    "testresults": [
        ["Test", "Expected", "Got", "iscorrect"],
        ["sqr(3)", "9",  "9",  1],
        ["sqr(-2)", "4", "5",  0],
    ]
}
print(json.dumps(result))

```


### Back to the [index](docs.php?page=index.md)
