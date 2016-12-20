# Authoring CodeRunner Questions

Author: Richard Lobb, University of Canterbury, New Zealand.
        20 December 2016.

This document describes how to write quiz questions using the [CodeRunner
plug-in](http://www.coderunner.org.nz) for Moodle. It begins with a QuickStart
guide for first-time users. The rest of the document is a series of sections
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
1. In the main page, click the *Create a new question ...* button.
1. Select the *CodeRunner* radiobutton and click *Add*.
1. Fill in the following fields in the question authoring form, leaving all
   other fields in their initial state(empty, check or whatever). Fields are
   specified in top-to-bottom order, skipping irrelevant ones.
    * In the *Question type* dropdown, select *python3*
    * Set the Answer Box *Rows* field to 6, *Columns* to 60.
    * Set *Question name* (in the *General* section) to *Python sqr function*.
    * Set the *Question text* to "Write a function *sqr(n)* that returns
      the square of its parameter *n*"
    * In the *Test cases* section, set the first test case to:
        - Test case 1: `print(sqr(-3))`
        - Expected output: `9`
        - Use as example: checked
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
        - Display: Hidden
1. Click *Save changes*. The new question should now be showing in your question 
   bank highlighted in green.
1. Click the new question's *Preview* button
1. Fill in the answer box with

    def sqr(n):
        return n * n

1. Click *Check*

You should now be looking at a page very like that at [coderunner.org.nz](
http://www.coderunner.org.nz)], except that the penalty regime of 33.3% is
explicitly displayed and the last of the test cases is shaded green, indicating
that it is hidden from the students and visible only to staff.

Congratulations. You just wrote your first CodeRunner question.

## Other things to try:

1. Try submitting various wrong answer and observe what happens. In particular,
   try the answer `def sqr(n): return n * n if n >!= 0 else -1` and observe the
   messages underneath the answerbox (remembering that students cannot see
   the last hidden test case).
1. Back in the question bank, click the question's *Edit* icon.
   In the authoring form, copy the correct answer into the *Answer* box. Change
   one of the test cases to a wrong answer. Click *Save* - observe that the question
   is *not* saved because the question no longer validates. Scroll to inspect
   the error message just above the question answer.
1. Fix the wrong test case. Set the *Precheck* dropdown towards the top of the
   authoring form to *Examples*. Save the question. Then Preview it. Observe the
   appearance of a *Precheck* button. 
   





