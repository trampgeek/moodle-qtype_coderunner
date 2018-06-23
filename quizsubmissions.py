"""A set of classes to deal with the downloaded CSV file from the new downloadquizattempts
   script in CodeRunner.

   To use, just create a QuizSubmissions(csvfilename) object. Subscripting this
   with a username or an email gives a QuizAttempt object for the specified
   student. For example

   >>> submissions = QuizSubmissions('lab4download.csv')
   >>> submissions['zba25']   # Get the QuizAttempt for zba25@uclive.ac.nz

   A QuizAttempt object contains information about the user, when the
   quiz was started, when it was finished and the mark obtained. It also
   contains a list of the submissions made for each question in the form of
   a dictionary mapping from question number to a QuestionAttempt object.

   A QuestionAttempt object contains the name of the question, the quiz slot,
   the mark obtained and a list of all the submissions and actions of the
   student in obtaining that mark in the form of a list of QuestionAttemptStep
   objects. A QuestionAttempt object provides a get_answer() method to obtain
   the last answer submitted by the student (default), or any of the earlier ones
   (if a specific one is requested).

   A QuestionAttemptStep object contains a time stamp, the action of the
   student ('precheck', 'submit', 'finished') and the answer submitted by
   the student.


   @author Richard Lobb
   @version 24 June 2018
"""
import csv
from collections import defaultdict
from datetime import datetime

TOLERANCE = 0.01  # Floating point error in fraction tolerated for equality


class QuestionAttemptStep:
    """Wraps a single attempt step on a quiz"""
    def __init__(self, rows):  #time, action=None, fraction=0, answer=None):
        """Initialise given all the relevant attemptstepid rows from the database"""
        assert len(rows), "Empty row list passed to QuestionAttemptStep constructor"
        self.time = int(rows[0]['timestamp'])  # Unix timestamp
        self.state = rows[0]['state']
        self.rawfraction = None
        self.action = None
        self.answer = None
        self.attributes = {}  # Other attributes from the database we don't handle
        try:
            self.fraction = float(rows[0]['fraction'])
        except ValueError:
            self.fraction = 0

        for row in rows:
            name = row['qasdname']
            ignore_one_answer = False
            if name == '-_rawfraction':
                self.rawfraction = row['value']
            elif name.startswith('-'):
                action = name[1:]
                if self.action is not None:
                    if set([self.action, action]) == set(['precheck', 'submit']):
                        self.action = 'precheck'
                        ignore_one_answer = True
                        print("*** Warning: quiz attempt step had both precheck and submit actions")
                    else:
                        print('*** Warning: actions {} and {} occurred concurrently?!'.format(action, self.action))
                else:
                    self.action = action
            elif name == 'answer':
                if self.answer is None:
                    self.answer = row['value']
                elif not ignore_one_answer:
                    self.answer += ', ' + row['value']  # Concatenate answers
            else:
                self.attributes[name] = row['value']

    @staticmethod
    def format_time(timestamp):
        """Return a date and time in the form 2017/06/24 11:44 from a Unix
           timestamp
        """
        dt = datetime.fromtimestamp(timestamp)
        return dt.strftime('%Y/%m/%d %H:%M')


    def __repr__(self):
        return "QuestionAttemptStep({!r}, {!r}, {:.3f})".format(
            self.format_time(self.time), self.action, self.fraction)



class QuestionAttempt:
    """Wraps a student's attempt on a question in a quiz - all submissions,
       times, marks, etc.

    """
    def __init__(self, qnum, slot, rows):
        """Initialise given the the question number, its slot and the set
           of rows from the download pertaining to that question
        """
        assert len(rows), "Empty row list passed for QuestionAttempt for question {}".format(qnum)
        assert int(rows[0]['slot']) == slot, "Wrong slot number in row passed to QuestionAttempt"
        self.qnum = qnum
        self.slot = slot
        self.name = rows[0]['qname']
        self.mark_out_of = float(rows[0]['mark'])
        self.fraction = 0
        self.steps = []  # A time-ordered list of student actions/steps on this question

        # Sort rows into bins for each attemptstepid
        steps = defaultdict(list)
        for row in rows:
            steps[int(row['attemptstepid'])].append(row)

        # Build step from each set of pertinent rows
        for stepid, rows in sorted(steps.items()):
            self.steps.append(QuestionAttemptStep(rows))

        self.fraction = self.steps[-1].fraction


    @property
    def mark(self):
        assert self.fraction is not None and self.mark_out_of is not None
        return self.fraction * self.mark_out_of


    def get_answer(self, index=-1):
        """Return the final answer the student gave (if index not given) or
           the indexth answer otherwise
        """
        if index >= 0:
            return self.steps[index].answer
        else:  # Work backwards through the steps looking for an answer
            index = len(self.steps) - 1
            while index >= 0 and self.steps[index].answer is None:
                index -= 1
            return self.steps[index].answer if index >= 0 else None


    def get_first_right_answer(self, mark_threshold=1-TOLERANCE):
        """Return the first QuestionAttemptStep that earned the specified
           mark threshold (a fraction in the range [0, 1]) or None if no
           such attempt step occurred.
        """
        for step in self.steps:
            if step.fraction >= mark_threshold:
                return step
        return None



    def __repr__(self):
        return "QuestionAttempt({}, {!r}, {:.2f}/{:.2f}, {})".format(
            self.qnum, self.name, self.mark, self.mark_out_of, self.steps)



class QuizAttempt:
    """Wraps the information about a student's attempt on a quiz.
       email is just an email address, starttime and endtime are the Unix
       timestamps at which the student started and ended the quiz, totalmark
       is the sum of the individual question marks and submissions is a dictionary
       mapping from question number to QuestionAttempt objects.
    """
    def __init__(self, email, rows, slot2qnum_map):
        assert len(rows), "Empty rows for student {}?!".format(email)
        #print("Loading", email)
        self.email = email
        self.firstname = rows[0]['firstname']
        self.lastname = rows[0]['lastname']
        self.starttime = int(rows[0]['timestart'])
        self.endtime = int(rows[0]['timefinish'])
        self.totalmark = 0
        self.maxmark = 0
        self.submissions = {}

        # Sort rows by question number
        question_rows = defaultdict(list)
        for row in rows:
            slot = int(row['slot'])
            question_rows[slot].append(row)

        # Build question attempts
        for slot, rows in question_rows.items():
            qnum = slot2qnum_map[slot]
            qa = QuestionAttempt(qnum, slot, rows)
            self.submissions[qnum] = qa
            self.maxmark += qa.mark_out_of
            self.totalmark += qa.mark



    def get_question_attempt(self, qnum):
        """Return the QuestionAttempt object for the given qnum"""
        return self.submissions[qnum]


    def __repr__(self):
        return "QuizAttempt({!r} ({} {}), {!r}, {!r}, {:.2f}/{:.2f}, {})".format(
            self.email, self.firstname, self.lastname,
            QuestionAttemptStep.format_time(self.starttime),
            QuestionAttemptStep.format_time(self.endtime),
            self.totalmark, self.maxmark, self.submissions)



class QuizSubmissions:
    """A class that reads a download file of all quiz submission data.
       An object of this class behaves like a dictionary mapping from
       email to a QuizAttempt object.
    """
    def __init__(self, filename):
        """Read the .csv file given and record all vital information
           for querying.
        """
        self.quiz_attempts = {}  # Map from email to StudentQuizAttempt

        # First read all rows, sorting them by student email into a dictionary
        # Record all slot numbers in order to compute question numbers
        with open(filename) as infile:
            rdr = csv.DictReader(infile)
            submissions = defaultdict(list)
            slots = set()
            for row in rdr:
                email = row['email']
                try:
                    slot = int(row['slot'])
                    slots.add(slot)
                except ValueError:
                    pass
                except TypeError:
                    pass
                submissions[email].append(row)

            # We now have all slots, so can compute question numbers
            slotnums = sorted(slots)
            slot2qnum_map = { slot : qnum for qnum, slot in enumerate(slotnums, 1)}
            slot2qnum_map[0] = 0

            for email, rows in submissions.items():
                self.quiz_attempts[email] = QuizAttempt(email, rows, slot2qnum_map)


    def __getitem__(self, email):
        """Subscripting self with an email (or a username) returns the
           QuizAttempt object for the specified student
        """
        key = email if '@' in email else email + '@uclive.ac.nz'
        return self.quiz_attempts[key]


    def __iter__(self):
        """Iterates over the keys (emails) of the set of quiz attempts"""
        return self.quiz_attempts.__iter__()


    def __len__(self):
        return len(self.quiz_attempts)


    def items(self):
        """Returns the items of self.quiz_attempts"""
        return self.quiz_attempts.items()

    def keys(self):
        """Returns all the emails (keys) of users who submitted"""
        return self.quiz_attempts.keys()


    def __repr__(self):
        return "QuizSubmissions({})".format(self.quiz_attempts)


