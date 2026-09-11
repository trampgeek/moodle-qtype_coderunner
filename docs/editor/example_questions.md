# Example Questions
### Back to the [index](docs.php?page=index.md)

This page collects ready-made CodeRunner questions that you can download and
import into your own question bank to play around with. Each download is a
standard Moodle XML question export.


## Example walkthroughs available
Five of these are dissected in detail in the
[Example walkthroughs](docs.php?page=example_walkthroughs.md) — start there
if you want to understand how the questions are built rather than just try
them.

## Available examples
Click on a link to download the corresponding question example, or use
*import into a course* to add it straight to the question bank of one of
your courses (you will be asked which course; the questions land in a
"CodeRunner examples" category there).

<!-- EXAMPLE_QUESTIONS_LIST -->

## How to import an example

1. Download one of the example files above.
2. In your course, open the **Question bank** (*Course administration > Question bank*),
   or go to *Site administration > Question bank* to import at site level.
3. Choose **Import** from the question bank menu.
4. Select **Moodle XML format** as the file format.
5. Under *Import questions from file*, choose or drop the downloaded `.xml` file.
6. Click **Import**. Moodle shows a preview of the imported questions —
   click **Continue** to add them to the question bank.

The imported questions appear in the category encoded in the export file (or
the currently selected category, depending on the *Import category* setting).
You can now open any of them in the question editor to inspect how they are
built, preview them as a student would see them, or copy them as a starting
point for your own questions.

## Things to be aware of

- **Prototypes first**: if an example depends on a custom question type
  (a prototype), the prototype question must exist before the derived
  questions will work. Exports listed here either use built-in question types
  or bundle the prototype in the same file — in the latter case import the
  whole file as-is and don't delete the `PROTOTYPE_`-named question.
- **A working sandbox is required**: to actually run or validate the examples
  your site needs a configured Jobe sandbox
  (*Site administration > Plugins > Question types > CodeRunner*).
- **Safe to experiment**: imported questions are ordinary question bank
  entries. You can edit, break, and delete them freely without affecting
  anything else — re-import the file to get a fresh copy.

## How to Contribute
You can contribute to this list under the GitHub repo. If you feel that your question is a particularly useful or interesting example you can open a pull request to add it to the list at [CodeRunner_Github_Repo/docs/editor/example_questions](https://github.com/trampgeek/moodle-qtype_coderunner/tree/master/docs/editor/example_questions).
If you do not feel comfortable contributing directly, you can open a GitHub issue and someone more experienced can contribute for you, given you provide the example.

### How to export an example
1. In your course, open the **Question bank** (*Course administration > Question bank*),
   or go to *Site administration > Question bank* to import at site level.
2. Choose **Export** from the question bank menu.
3. Select **Moodle XML format** as the file format.
4. Select the **Category** your question belongs to.
5. Click **Export questions to file**.

In the case that your category contains more than the question(s) you wish to add, you need to open and edit the XML file using an editor. Unfortunately, at this time there is no quick way to export a single question. To edit the file correctly, you can delete every question you wish to redact and remove safely, the format is typically verbose and human-friendly.

## See also
- [Example walkthroughs](docs.php?page=example_walkthroughs.md) — five of these examples explained in depth.
- [Templating](docs.php?page=templating.md) — how templates, template parameters, and template graders work.
- [Official CodeRunner Website](https://coderunner.org.nz) - Introductory usage including how quizzes look and are used
- [Deep-dive documentation](https://trampgeek.github.io/moodle-qtype_coderunner/) - official documentation discussing usage and installation
- [Author forum](https://coderunner.org.nz/mod/forum/view.php?id=51) - CodeRunner Author Forum for help and inquiries
- [Example questions](docs.php?page=example_questions.md) — downloadable question exports you can import into your question bank and experiment with.
- [Github Repo](https://github.com/trampgeek/moodle-qtype_coderunner#code-runner) - CodeRunner repository open for contribution


### Back to the [index](docs.php?page=index.md)
