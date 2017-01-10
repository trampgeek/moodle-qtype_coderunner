# Authoring CodeRunner Questions

Author: Richard Lobb, University of Canterbury, New Zealand.
        9 January 2017.

This document describes how to write quiz questions using the [CodeRunner
plug-in](http://www.coderunner.org.nz) for Moodle. It begins with a QuickStart
guide for first-time users. The rest of the document is (or will be) a series of sections
building from writing simple questions using the built-in question types
through to authoring of your own question types to handle your own
languages and/or course-specific requirements. The document is targetted
primarily at teachers of programming, although CodeRunner can also be used
in other contexts in which a computer program has to be run in order to grade a student's
textual answer.

*This is a work-in-progress. Stay tuned for updates.*

# A quick-start guide to authoring coderunner questions.

This section shows a first-time user how to write a trivial Python3 function. It
assumes that the CodeRunner plugin has already been installed, that
the reader has a basic knowledge of Moodle and that they can log in as a teacher
in a course.

Carry out the following steps:

1. Log yourself into a course on Moodle.

1. Select *Question bank* from the Course administration menu.

1. In the main page, click the *Create a new question...* button.

1. Select the *CodeRunner* radiobutton

1. Click *Add*.

1. Fill in the following fields in the question authoring form, leaving all
   other fields in their initial state (empty, checked or whatever). Fields are
   specified in top-to-bottom order, skipping irrelevant ones.
    * In the *CodeRunner question type* panel, dropdown, select *python3* as the
    question type, set the Answer Box *Rows* field to 6 and *Columns* to 60.

    <img src="http://coderunner.org.nz/pluginfile.php/56/mod_page/content/26/Selection_139.png" width="700"/>

    * In the *General* Panel, set the *Question name* to *Python sqr function* and
      set the *Question text* to "Write a function *sqr(n)* that returns
      the square of its parameter *n*"

    <img src="http://coderunner.org.nz/pluginfile.php/56/mod_page/content/26/The%20General%20section%20of%20the%20question%20authoring%20form.png" width="700"/>

    * In the *Test cases* section, set the first test case to:

        - Test case 1: `print(sqr(-3))`
        - Expected output: `9`
        - Use as example: checked

    <img src="http://coderunner.org.nz/pluginfile.php/56/mod_page/content/26/Selection_142.png" width="700"/>

    * In the *Test cases* section, set the second test case to:

        - Test case 2: `print(sqr(11))`
        - Expected output: `121`
        - Use as example: checked

    * In the *Test cases* section, set the third test case to:

        - Test case 3: `print(sqr(-4))`
        - Expected output: `16`
        - Use as example: leave unchecked

    * In the *Test cases* section, set the fourth test case to:

        - Test case 4: `print(sqr(0))`
        - Expected output: `0`
        - Use as example: leave unchecked

1. Click *Save changes*. The new question should now be showing in your question
   bank highlighted in green.

1. Click the new question's *Preview* button

1. Fill in the answer box with

        def sqr(n):
            return n * n

1. Click *Check*

You should now be looking at a page like the following:
<img src="http://coderunner.org.nz/pluginfile.php/56/mod_page/content/26/256c94b89a769060b65955759c2a8a3ed518557e.png" width="700"/>

Congratulations. You just wrote your first CodeRunner question.

## Other things to try:

1. Try submitting various wrong answers and observe what happens.

1. Go back to the question editing form and set the last test case to *Hidden*.
   Save and resubmit your answer. Observe that the last test case is now shaded
   green (or red, if your answer is wrong), indicating that this is a hidden
   test case visible only to staff. Students will not see this line of the table.

1. Back in the question bank, click the question's *Edit* icon. 
   In the authoring form, insert a wrong answer into the *Answer* box.
   If "Validate on save" is unchecked, click it so it becomes checked.
   Click *Save* - observe that the question
   is *not* saved because the question doesn't validate. Scroll to inspect
   the error message just above the question answer. Fix the error in the answer,
   and click *Save* again. It should work this time.

1. Set the *Precheck* dropdown towards the top of the
   authoring form to *Examples*. Save the question. Then Preview it again,
   as before. Observe the
   appearance of a *Precheck* button. Enter a wrong answer and click *Precheck*.
   Note that the code is tested only with the tests for which 'Use as example'
   was checked. Fix your code, click *Precheck* again, then lastly *Check*.
   Note that no penalties were charged for prechecking.

# The rest of this document ...
... isn't written yet! Stay tuned.






