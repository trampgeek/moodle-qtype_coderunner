# Example Walkthroughs

This page dissects five of the [example questions](docs.php?page=example_questions.md)
field by field. Each walkthrough follows the author form top to bottom, in the
same order as the [question editor reference](docs.php?page=index.md): the
CodeRunner panels first, then the Moodle *General* section content, the Answer
fields, the test cases, and finally files, attachments and advanced settings.
Import the example, open it in the editor, and read along — every value below
is the exact value in the form.

Fields left blank in these questions are inherited from the built-in prototype
of the selected question type; they are listed as *(blank — inherited)* with a
note on what is actually inherited.

## Contents

1. [Hello, World! — the minimal question](#hello-world-the-minimal-question)
2. [Reverse the Digits — stdin and regex grading](#reverse-the-digits-stdin-and-regex-grading)
3. [Print Array — inspecting the student's source](#print-array-inspecting-the-students-source)
4. [String Reverse — secret constraint tests](#string-reverse-secret-constraint-tests)
5. [Employee — multi-file C via a Python wrapper](#employee-multi-file-c-via-a-python-wrapper)

---

## Hello, World! — the minimal question

Get it: [import into a course](import_example.php?slug=01-hello-world) - [download](docs.php?page=example_questions/01-hello-world.xml)

The student is asked to write a complete C program that prints
`Hi, universe!`. This is the baseline example: every CodeRunner-specific
control is at its default, so it doubles as a reference for what the form
looks like when the built-in question type does all the work.

### Hello, World! — CodeRunner question type panel

| Field | Value | Effect in this question |
|---|---|---|
| Question type | `c_program` | Inherits the built-in prototype: student submits a whole C program; the prototype's template compiles it with gcc and runs it once per test case. |
| Customise | unchecked | The Customisation panel is hidden; template and grader come from the prototype unchanged. |
| Show source | unchecked | Students never see the expanded template. |
| Answer box lines | `18` | Minimum height of the student's answer box (the default). |
| Precheck | `Disabled` | No Precheck button; students only get the full Check. |
| Hide check | unchecked | The Check button is shown normally. |
| Give up | `Never` | No *Stop and read feedback* button. |
| Feedback | `Show` | Test results are always shown after submission, regardless of the quiz's feedback setting. |
| All-or-nothing grading | checked | Full marks only if every test passes — with one test case this simply means pass/fail. |
| Penalty regime | `0, 0, 0, 0, 0` | Written out as five zeros, but equivalent to `0`: resubmitting never costs marks. |
| Template parameters | empty | No Twig parameters are injected. |
| Hoist template params | checked | Default on new questions; a no-op here since there are no parameters. |
| Extract code from JSON | checked | Default; only matters for UI plugins that serialise answers as JSON — Ace does not. |
| Twig all | unchecked | Question fields are not Twig-expanded; only the (inherited) template is. |
| Preprocessor | `None` | The empty Template parameters field is taken literally. |
| UI parameters | empty | The Ace editor runs with default settings. |

### Hello, World! — General section

| Field | Value |
|---|---|
| Question name | `Hello, World!` |
| Question text | "Exercise 1: Hello World!" — write a C program printing `Hi, universe!`, with the compile commands students would use offline. |
| Default mark | `1` |

### Hello, World! — Answer section

| Field | Value | Effect |
|---|---|---|
| Sample answer | a 4-line `main()` calling `printf("Hi, universe!");` | The known-good solution. |
| Validate on save | checked | Every save runs the sample answer against all test cases and blocks the save on failure — typos in *Expected output* are caught at authoring time, not by students. |
| Answer preload | empty | The student's answer box starts blank. |
| Global extra | empty | Not used. |

### Hello, World! — Test cases

One test case:

| Field | Value | Effect |
|---|---|---|
| Test code | empty | Nothing is appended to the program; the `c_program` template just runs the student's compiled program. |
| Standard input | empty | The program reads nothing. |
| Expected output | `Hi, universe!` | Compared by the inherited **EqualityGrader**: exact match after trailing whitespace on each line and trailing blank lines are stripped (see [Grading](docs.php?page=index.md#grading)). |
| Extra | empty | Not used. |
| Use as example | unchecked | No "For example" table is shown above the answer box. |
| Display | `SHOW` | The result row is always visible. |
| Hide rest if fail | unchecked | Irrelevant with one test. |
| Mark | `1.0` | Irrelevant under all-or-nothing. |
| Test type | `Normal` | Runs on Check (there is no Precheck anyway). |

### Hello, World! — Support files, attachments, advanced customisation

No support files. Attachments `No` (required `0`, max size `10240`).
Not a prototype; sandbox, CPU time, memory limit, language and Ace language
all blank — everything inherited from `c_program`.

---

## Reverse the Digits — stdin and regex grading

Get it: [import into a course](import_example.php?slug=04-reverse-the-digits) - [download](docs.php?page=example_questions/04-reverse-the-digits.xml)

The student writes a C program that reads a two-digit number and prints it
reversed, or `Invalid input!` for bad input. Two things differ from
Hello, World!: the grader is overridden, and every test drives the program
through **Standard input**.

### Reverse the Digits — CodeRunner question type panel

Identical to Hello, World! except:

| Field | Value | Effect in this question |
|---|---|---|
| Question type | `c_program` | Same inherited compile-and-run template. |
| Penalty regime | `0` | No resubmission penalties. |
| All-or-nothing grading | checked | All eight tests must pass. |

**Grader** (in this export the grader override rides along even though the
question is not customised): **RegexGrader**. The *Expected output* field of
each test is treated as a PHP regex, and the test passes if it matches
**anywhere** in the actual output (MULTILINE + DOTALL — see
[Grading](docs.php?page=index.md#grading)). This matters because the program
prompts and reads without a newline, so prompt and answer interleave:

```
Enter a two-digit positive integer: The reversal is: 53
```

An exact-match grader would force students to reproduce that spacing
byte-for-byte; the regex match is tolerant of surrounding whitespace and
newlines.

### Reverse the Digits — General section

| Field | Value |
|---|---|
| Question name | `Reverse the digits` |
| Question text | "Exercise 4: Reverse the Digits" — prompt for a two-digit positive integer, print it reversed; invalid input must produce `Invalid input!`. |
| Default mark | `1` |

### Reverse the Digits — Answer section

Sample answer: a full C program using `scanf("%d", ...)`, validating
`0 < input < 100`, and printing either the reversal (via `/10` and `%10`)
or `Invalid input!`. **Validate on save** is checked, so this answer must
pass all eight tests on every save. Answer preload and Global extra empty.

### Reverse the Digits — Test cases

All eight tests share: *Use as example* unchecked, *Display* `SHOW`,
**Hide rest if fail checked**, *Mark* `1.0`, *Test type* `Normal`.
Because every test has *Hide rest if fail* set, the student sees only the
first failing row instead of eight red rows — see
[Test case controls](docs.php?page=index.md#test-case-controls).

Here the *Test code* field is not code at all — with the `c_program` type it
is never compiled, so the author uses it as a visible label for each row:

| # | Test code (label) | Standard input | Expected output (regex) |
|---|---|---|---|
| 1 | `Good test 1` | `35` | `Enter a two-digit positive integer: The reversal is: 53` |
| 2 | `Good test 2` | `67` | `Enter a two-digit positive integer: The reversal is: 76` |
| 3 | `Good test 3` | `18` | `Enter a two-digit positive integer: The reversal is: 81` |
| 4 | `Good edge test 4` | `10` | `Enter a two-digit positive integer: The reversal is: 01` |
| 5 | `Good edge test 5` | `99` | `Enter a two-digit positive integer: The reversal is: 99` |
| 6 | `Bad no number` | `John` | `Enter a two-digit positive integer: Invalid input!` |
| 7 | `Bad negative number` | `-3` | `Enter a two-digit positive integer: Invalid input!` |
| 8 | `Bad too big number` | `156` | `Enter a two-digit positive integer: Invalid input!` |

Note the deliberate edge cases: `10` (leading zero in the reversal, `01`),
`99` (palindrome), and three invalid-input categories.

### Reverse the Digits — Support files, attachments, advanced customisation

All empty/default, as in Hello, World!.

---

## Print Array — inspecting the student's source

Get it: [import into a course](import_example.php?slug=07-print-array) - [download](docs.php?page=example_questions/07-print-array.xml)

The student writes `void print_array(int *ptr, int len)` — a function, not a
program — so this question uses the `c_function` type with a **customised
template**, and awards **part marks** across behaviour and style tests.

### Print Array — CodeRunner question type panel

| Field | Value | Effect in this question |
|---|---|---|
| Question type | `c_function` | Base type for testing a single C function. |
| Customise | checked | The Customisation panel is active; the template below overrides the prototype's. |
| Show source | unchecked | Students don't see the expanded template. |
| Answer box lines | `18` | Default. |
| Precheck / Hide check / Give up | `Disabled` / unchecked / `Never` | Only the Check button. |
| Feedback | `Show` | Results always shown. |
| **All-or-nothing grading** | **unchecked** | Each test contributes its own mark — the point of this question. |
| Penalty regime | `0` | No resubmission penalties. |
| Template parameters | empty | Not used. |
| Hoist template params | checked | No-op (no parameters). |
| Extract code from JSON | checked | Default, inert with Ace. |
| **Twig all** | **checked** | Every question field (question text, test cases, …) is passed through Twig at question initialisation. Nothing here uses Twig variables, so it is inert — but note it is safe even though the C test code is full of braces: Twig only reacts to `{{`, `{%` and `{#`, never to `{1, 2, 3}`. |

### Print Array — Customisation panel

**Template** (customised):

```c
#include <stdio.h>
#include <stdlib.h>
#include <ctype.h>
#include <string.h>
#include <stdbool.h>
#include <math.h>
#include <regex.h>
#define SEPARATOR "#<ab@17943918#@>#"

char* input = "{{ STUDENT_ANSWER | e("c") }}";

{{ STUDENT_ANSWER }}

int main() {
{% for TEST in TESTCASES %}
   {
    {{ TEST.testcode }};
   }
    {% if not loop.last %}printf("%s\n", SEPARATOR);{% endif %}
{% endfor %}
    return 0;
}
```

Line by line:

- `#define SEPARATOR "#<ab@17943918#@>#"` — the default
  [test splitter](docs.php?page=index.md#template-controls) delimiter, printed
  between test outputs so CodeRunner can split the single run back into
  per-test rows.
- `char* input = "{{ STUDENT_ANSWER | e("c") }}";` — the student's answer
  inserted a **first** time, as a C string literal. The
  [`e('c')` escaper](docs.php?page=templating.md#twig-escapers) escapes
  quotes, backslashes and newlines so arbitrary source is a valid literal.
  This gives every test case access to the student's *source text* for
  static analysis.
- `{{ STUDENT_ANSWER }}` — the answer inserted a **second** time, as actual
  code, defining `print_array` for the tests to call.
- The `{% for TEST in TESTCASES %}` loop makes this a **combinator**
  template: one compile, one run, all tests. Each test body gets its own
  `{ ... }` block so tests can declare identically-named locals. The
  *Is combinator template* checkbox is left blank in the export, so the value
  is inherited from the `c_function` prototype — which is a combinator.
- *Allow multiple stdins* and *Test splitter regex* — blank, inherited
  defaults (no stdin anywhere in this question, and the standard splitter
  matches `SEPARATOR`).

**Grader**: blank — inherits **EqualityGrader**. **Result columns**: blank —
default columns. **Student answer UI**: blank — Ace.

### Print Array — General section

| Field | Value |
|---|---|
| Question name | `Print Array` |
| Question text | Write `void print_array(int *ptr, int len)` printing the elements comma-separated; students are told not to use square brackets. |
| Default mark | `2` |

### Print Array — Answer section

Sample answer (validated on save):

```c
void print_array(int* ptr, int len){
	while(len--){
		printf("%d%s", *(ptr++), len ? ", " : "\n");
	}
}
```

Note the sample answer itself avoids `[]` — it must, or it would fail test 2
below and block the save. Answer preload and Global extra empty.

### Print Array — Test cases

All tests: *Use as example* unchecked, *Display* `SHOW`, *Test type* `Normal`.

| # | Test code | Expected output | Mark | Hide rest if fail |
|---|---|---|---|---|
| 1 | `int arr[10] = {1, ..., 10};` `print_array(arr, 10);` | `1, 2, 3, 4, 5, 6, 7, 8, 9, 10` | `0.5` | **checked** |
| 2 | POSIX regex source check (below) | `No square brackets detected!` | `0.5` | unchecked |
| 3 | `int arr[50] = {5, 6, 7, 8, 9, 10, 11};` `print_array(arr, 7);` | `5, 6, 7, 8, 9, 10, 11` | `1.0` | unchecked |
| 4 | 15-element array, `print_array(arr, 15);` | `1, 3, 5, ..., 29` | `1.0` | unchecked |

Test 1 has *Hide rest if fail* checked: if the basic case fails, the
remaining rows are suppressed. With all-or-nothing off, the marks weight
basic behaviour (0.5), style (0.5) and the two larger cases (1 each) out of
the question's default mark of 2.

Test 2 is the one worth stealing — static analysis written in plain C,
running inside the same combinator program, using the `input` string the
template prepared:

```c
regex_t regex;
int reti;
reti = regcomp(&regex, "(\\[|\\])", REG_EXTENDED | REG_NOSUB);
reti = regexec(&regex, input, 0, NULL, 0);
if (!reti) {
    printf("Square bracket detected! ...");
}
else if (reti == REG_NOMATCH) {
    printf("No square brackets detected!\n");
}
regfree(&regex);
```

It prints the expected string only when the student used pointer arithmetic
rather than array indexing. (Test 3's `arr[50]` declaration lives in the
*test case*, not the student's answer — only `input` is searched.)

### Print Array — Support files, attachments, advanced customisation

All empty/default; everything else inherited from `c_function`.

---

## String Reverse — secret constraint tests

Get it: [import into a course](import_example.php?slug=11-string-reverse-in-place) - [download](docs.php?page=example_questions/11-string-reverse-in-place.xml)

The student writes `char *str_rev(char *str)` reversing a string **in
place**, with extra credit for doing it without a temporary array and
without `string.h`. The configuration is almost identical to Print Array —
only the differences are listed here; every field not mentioned has the same
value and effect as in [Print Array](#print-array-inspecting-the-students-source).

### String Reverse — Differences in the question type panel

| Field | Value | Effect |
|---|---|---|
| **All-or-nothing grading** | **checked** | Unlike Print Array: despite the per-test marks (0.5 / 0.5 / 1 / 2), a student must pass *every* test — including the constraint tests — to score at all. The marks only affect the reported breakdown. |
| Default mark | `2` | |

### String Reverse — Customisation panel template

Same structure as Print Array (same includes, `SEPARATOR`, `input` string
via `e('c')`, combinator loop), with one addition — a helper defined
**above** the student's answer:

```c
int str_len(char* str){
	int i;
	for (i = 0; str[i] != '\0'; i++);
	return i;
}
```

Any test case can call `str_len()` without depending on the student having
written a correct length function — the template is the place to put
utility code that test cases need.

### String Reverse — Answer section

Sample answer: an in-place XOR-swap reversal using only pointers — it must
avoid arrays *and* `string.h` to survive its own constraint tests under
validate-on-save.

### String Reverse — Test cases

| # | Test code | Expected | Mark | Display | Hide rest if fail |
|---|---|---|---|---|---|
| 1 | reverse `"Reverse"`, print result and original | `esreveR` twice | `0.5` | `SHOW` | checked |
| 2 | reverse `"Another test"`, print both | `tset rehtonA` twice | `0.5` | `SHOW` | unchecked |
| 3 | regex source check for a local array declaration | `No array detected!` | `1.0` | `SHOW` | unchecked |
| 4 | regex source check for `string.h` / `strlen` | `Okay` | `2.0` | **`HIDE_IF_SUCCEED`** | checked |

Tests 1–2 print both the return value and the original variable — that is
what verifies the reversal happened **in place**, not into fresh memory.

Test 3 compiles the POSIX regex
`[[:space:]]*char[[:space:]]+[[:alpha:]]+\[[[:alnum:]]*\][^\n]*;` against
`input` and prints `No array detected!` only if no local array declaration
is found.

Test 4 searches `input` for `(<string[.]h>|strlen)` and prints `Okay` when
absent. Its *Display* is **HIDE_IF_SUCCEED**: the row is invisible while
the student behaves, and appears — with the output
`Nice Try, but you can't use string.h!` in the *Got* column — only on
violation. Combined with *Hide rest if fail*, a caught student sees exactly
this one row. See
[Test case controls](docs.php?page=index.md#test-case-controls).

---

## Employee — multi-file C via a Python wrapper

Get it: [import into a course](import_example.php?slug=12-employee-struct-multifile) - [download](docs.php?page=example_questions/12-employee-struct-multifile.xml)

Two questions in one file — one asks for `employee.h`, one for `employee.c`
— from a multi-file exercise (a `main()` driver `Exercise10.c` plus the
header/implementation pair). Each question tests **one file** of the
project; the rest is supplied as support files. The striking choice: the
question type is **python3** even though the student writes C.

### Employee — CodeRunner question type panel (both questions)

| Field | Value | Effect in this question |
|---|---|---|
| Question type | `python3` | The template runs as a Python 3 program on Jobe. Python is used as a *build orchestrator*: it writes the student's C to disk, invokes gcc, and runs the result. |
| Customise | checked | The Python template below replaces the python3 prototype's. |
| All-or-nothing grading | checked | Every test must pass. |
| Penalty regime | `0` | No penalties. |
| Twig all | unchecked | No Twig expansion of question fields. |
| All remaining panel fields | defaults | As in Hello, World!. |

### Employee — Customisation panel template

The `employee.c` question's template (the `employee.h` one is identical
except it writes `employee.h`):

```python
import subprocess, sys

student_answer = """{{ STUDENT_ANSWER | e('py') }}"""
with open("employee.c", "w") as src:
    print(student_answer, file=src)

{% if QUESTION.parameters.cflags is defined %}
cflags = """{{ QUESTION.parameters.cflags | e('py') }}"""
{% else %}
cflags = "-std=c99 -Wall -Werror"
{% endif %}
return_code = subprocess.call("gcc {0} -o prog Exercise10.c employee.c".format(cflags).split())
if return_code != 0:
    print("** Compilation failed. Testing aborted **", file=sys.stderr)

if return_code == 0:
    try:
        output = subprocess.check_output(["./prog"], universal_newlines=True)
        print(output)
    except subprocess.CalledProcessError as e:
        ...
        print("** Further testing aborted **", file=sys.stderr)
```

Step by step:

- **`e('py')`** embeds the student's C source inside a Python triple-quoted
  string, escaped so quotes and backslashes can't break out — the Python
  cousin of Print Array's `e('c')` trick, used here to write the answer to
  disk rather than to inspect it.
- **`gcc ... Exercise10.c employee.c`** compiles the freshly written student
  file together with the support files already sitting in the working
  directory.
- **`cflags` with a Twig default**: `{% if QUESTION.parameters.cflags is defined %}`
  lets an author override compiler flags by putting e.g.
  `{"cflags": "-std=c11 -Wall"}` into
  [Template parameters](docs.php?page=index.md#template-parameters) —
  without touching the template. As shipped, the field is empty, so the
  default `-std=c99 -Wall -Werror` applies (note `-Werror`: warnings fail
  the compile).
- **stderr as the error channel**: compile failures and crash signals print
  to stderr, which CodeRunner surfaces as a runtime error on the test with
  the compiler's/signal's message — instead of a silent output mismatch.
- **Per-test execution**: the template contains no `TESTCASES` loop; each
  test's *Standard input* is already wired to stdin when `./prog` runs. The
  *Is combinator template* checkbox is blank (inherited), but because every
  test case defines stdin, CodeRunner falls back to per-test runs anyway —
  the documented behaviour in
  [Template controls](docs.php?page=index.md#template-controls).

**Grader / Result columns / UI**: blank — EqualityGrader, default columns,
Ace.

### Employee — General section

| Field | `Employee.h` question | `Employee.c` question |
|---|---|---|
| Question text | "Please enter the complete code for your employee.h file" | "Please enter the complete code for your employee.c file" |
| Default mark | `2` | `2` |

### Employee — Answer section

Each question's sample answer is the complete correct file (the header with
the `EMPLOYEE` struct, `typedef` and five prototypes; the implementation
with `malloc`-based array creation, input, printing, and highest-paid
search). **Validate on save** checked on both.

### Employee — Test cases

Both questions run the same driver; the test's *Test code* is again used as
a row label, and *Standard input* feeds the driver's prompts
(count, then `first last title salary` per employee):

| Question | # | Label | Stdin (abridged) | Expected (abridged) |
|---|---|---|---|---|
| Employee.h | 1 | `David Bowman` | `1` employee: `David Bowman Astronaut 104898.99` | full formatted record for Bowman |
| Employee.c | 1 | `David Bowman` | same single employee | same |
| Employee.c | 2 | `CS210 Teaching Team` | 3 employees | record of the highest paid (`Levine, John`, `999999.00`) |
| Employee.c | 3 | `The Office` | 13 employees | record of the highest paid of 13 |

All tests: `SHOW`, mark `1.0`, *Hide rest if fail* unchecked, type `Normal`.
The expected outputs are the driver's **exact** full transcript (prompts,
`Employee Record` box, `Employee array deleted!`), compared by
EqualityGrader — meaning the student's file must make the *whole program*
behave, not just one function.

### Employee — Support files

The key to the multi-file setup — each question ships the *other half* plus
the driver (see [Support files](docs.php?page=index.md#support-files)):

| Question | Support files |
|---|---|
| `Employee.h` | `employee.c` (known-good implementation), `Exercise10.c` (driver with `main()`) |
| `Employee.c` | `employee.h` (known-good header), `Exercise10.c` (driver) |

Support files are copied into the sandbox working directory before the run,
which is why the template's `gcc` line finds them by bare filename.

### Employee — Advanced customisation

| Field | Value | Effect |
|---|---|---|
| Is prototype | `No` | Ordinary question. To reuse this pattern across many questions you would instead make one question a user-defined prototype — see [Prototype](docs.php?page=index.md#prototype). |
| Sandbox / CPU time / memory | blank | Inherited from python3 defaults. |
| Language | blank | Inherited: `python3` — what actually executes first. |
| **Ace language** | **`C`** | The one advanced field that is set: the student edits C, so the editor highlights C even though the sandbox language is Python — exactly the split described in [Languages](docs.php?page=index.md#languages). |

---

## See also
- [Github Repo](https://github.com/trampgeek/moodle-qtype_coderunner) - CodeRunner
Github repo
- [Author Forum](https://coderunner.org.nz/mod/forum/view.php?id=51) - an quiz author forum for help requests
- [Example questions](docs.php?page=example_questions.md) — the full
  downloadable list and how to import them.
- [Question editor reference](docs.php?page=index.md) — every field
  mentioned above, in form order.
- [Templating](docs.php?page=templating.md) — Twig variables, escapers,
  combinator templates, and template graders in depth.
