#! /usr/bin/env python3

'''CodeRunner load tester.

    Tests the load handling capability of a server by submitting
    quiz attempts by a set of n users to a set of m questions in a
    pseudo-random manner, recording response time and correctness
    of response. It requires that a suitable set of quizzes be
    set up on the server.

    Modified by Richard Lobb from the original version written by Carl Cerecke.

    This version uses a different model: it sets up a process for each of
    the n students, who then attempt to cycle around the m quiz questions
    with a pseudo-random pause between submissions.

    Assumed the existence of 4 1-question quizzes, called LoadTesting1 ..
    LoadTesting4, together with logins for 10 students, student0 ..
    student9.

    This program is very specific to the University of Canterbury
    but is included here on the off-chance it's useful to anyone else
    (with lots of twiddling of parameters).

    Richard Lobb, 13 April 2015.
'''

import mechanize as mech
import ssl
import time
from multiprocessing import Process, Queue
from queue import Empty
from time import sleep

import sys
import shelve
import random

SERVER = 'https://quiz2024.csse.canterbury.ac.nz/login/index.php?theme=clean'
COURSE = 'LoadTestingByRichard'
LANGUAGE = 'PYTHON3'
NUM_QUESTIONS = 4
FIRST_QUESTION = 0
LAST_QUESTION = FIRST_QUESTION + NUM_QUESTIONS - 1
DEBUGGING = False
INTER_SUBMISSION_GAP_SECS = 0
SIMULATION_DURATION_SECS = 300
NUM_STUDENTS = 40
START_STUDENT = 0
PASSWORD = 'S-tudent0'

# Correct answers to the four one-question quizzes for each language.

quiz_answers = {

# ==== C ====
'C': ['''
float addMul(float a, float b, float c)
{
    return (a + b) * c;
}
''',

'''
float approx_const(float xxx)
{
    long long xScaled = xxx * 1000 + 0.5;
    return xScaled / 1000.0;
}
''',

'''
float c_to_f(float ccc)
{
    return 32.0 + ccc * 9.0 / 5.0;
}
''',

'''
#include <stdio.h>

int main()
{
    int n;
    int i;
    scanf("%d", &n);
    for (i = 1; i <= n; i++) {
        printf("%d", i);
    }
    puts("");
    return 0;
}
'''],

# ==== PYTHON3 ====

'PYTHON3': [
'''
def addMul(aaa, bbb, ccc):
    """Return (aaa + bbb) * ccc"""
    return (aaa + bbb) * ccc
''',

'''
def approx_const(xxx):
    """Return xxx rounded to 3 decimal places"""
    x_scaled = xxx * 1000 + 0.5
    return int(x_scaled) / 1000.0
''',

'''
def c_to_f(degs_c):
    """Degs_c converted to degs_f"""
    return 32.0 + degs_c * 9.0 / 5.0
''',

'''
"""Read n from stdin, print 1 to n without spaces"""
n = int(input())
for i in range(1, n+1):
   print(str(i) + ' ', end='')
print()
''',

'''
([[{},{},{1,4,8}],[2,{},{}],[{},3,{}],[3,3,{}],[4,{4,5},{}],[6,{},{}],[{},7,{}],[7,7,{}],[8,{8,9},{}],[10,{},{}],[{},{},{}]],[3,7,10])
'''],

# ====== MATLAB ======

'MATLAB': [
'''function result = addMul(a, b, c)
    result = (a + b) * c;
end
''',
'''function ac = approx_const(f)
    ac = double(int32(f * 1000))/1000;
end
''',
'''function result = c_to_f(c)
    result = 32.0 + c * 9.0 / 5.0;
end
''',
'''function crazy(n)
   s = '';
   for i = 1 : n
      s = [s  sprintf('%d ', i)];
   end
   disp(s);
end
'''
],

# ====== CLOJURE ======
"CLOJURE": """
(defn arg-max [f ls]
  (reduce (fn [x y] (if (> (f x) (f y)) x y)) (first ls) ls))
;
(defn compose [& fns]
  (fn [x] (reduce (fn [x f] (f x)) x fns)))
;
(defn conjoin [& ps]
  (fn [x] (reduce (fn [v p] (and v (p x))) true ps)))
;
(defn transpose [ls] (apply map list ls))
""".split(';')
}

def randomise(answer, language):
    """Randomise the answer for the given language by inserting a random comment"""
    comments = {
        'C': '// ',
        'MATLAB' : '% ',
        'PYTHON3' : '# '
    }
    comment_line = '\n' + comments[language] + str(random.random()) + '\n'
    return answer + comment_line


def debug(student_num, message):
    if DEBUGGING:
        print('Student{}: {}'.format(student_num, message))


def loop_doing_questions(browser, student_num, sim_gap, duration, result_q):
    '''Using the given browser and for the given student number,
       who at this stage must already be logged in, cycle through the quiz
       questions, pausing for sim_gap seconds on average, terminating
       after the given duration. After each question has been attempted,
       a result tuple (student, question, time, error) is written to result_q'''

    br = browser

    if sim_gap == 0:
        lamb = 1.e10
    else:
        lamb = 1.0 / sim_gap
    time_end = time.time() + duration
    err_outfile = open("loadtesterrors.txt", 'a')

    while time.time() < time_end:
        for question in range(FIRST_QUESTION, LAST_QUESTION + 1):
            try:
                br.follow_link(text_regex='Home')
            except mech.LinkNotFoundError:
                pass
            
            debug(student_num, 'Following link to ' + COURSE)
            try:
                br.follow_link(text_regex='Site home')
            except mech.LinkNotFoundError:
                pass
            br.follow_link(text_regex=COURSE)
            question_name = LANGUAGE + '_LoadTesting' + str(question + 1)
            debug(student_num, 'Following link to {}'.format(question_name))
            br.follow_link(text_regex=question_name)
            debug(student_num, "Follow link to 'Attempt quiz now' (or 'Re-attempt quiz')")

            br.select_form(nr=0)
            br.submit()
            forms = list(br.forms())
            main_form = forms[0]
            controls = main_form.controls
            textarea = controls[4]

            debug(student_num, 'Entering code into textarea')
            quiz_answer = randomise(quiz_answers[LANGUAGE][question], LANGUAGE)
            textarea._value = quiz_answer

            br.form = main_form

            debug(student_num, 'submit %s code' % LANGUAGE)
            start = time.time()

            # Mechanize wrongly identifies the new Ace window maximise/window-ise buttons as submit buttons,
            # so we have to filter them out to find the actual Check button.
            submit_buttons = [control for control in controls if control.type == 'submitbutton' and control.name.endswith('submit')]
            br.submit(name=submit_buttons[0].name)

            res = br.response()
            data = res.get_data().decode('utf-8')
            dt = time.time() - start
            if 'coderunner-test-results' in data:
                if 'coderunner-test-results good' in data:
                    debug(student_num, 'Success! Test results returned in %.3f secs' % dt)
                    err = ''

                else:
                    debug(student_num, '***Failed***! Test results returned in %.3f secs' % dt)
                    err = 'Wrong answer'

            else:
                err = '****Serious error****'

            if err:
                err_outfile.write(err + "\n" + data + "\n\n")

            result_q.put((student_num, question, dt, err))

            if sim_gap != 0:
                sleep_time = random.expovariate(lamb)
                max_sleep = time_end - time.time()
                time.sleep(min(max_sleep, sleep_time))

    err_outfile.close()


def quiz_runner(student_num, sim_gap, duration, result_q):
    '''For the given student number, login and cycle through the quiz
       questions, pausing for sim_gap seconds on average, terminating
       after the given duration. After each question has been attempted,
       a result tuple (student, question, time, error) is written to result_q'''
    try:
        print('Student{} logging in ...'.format(student_num))
        login = SERVER
        ssl._create_default_https_context = ssl._create_unverified_context
        br = mech.Browser()
        br.set_handle_robots(False)
        br.open(login)
        log_form = list(br.forms())[0]
        br.form = log_form
        br['username'] = 'student' + str(student_num)
        br['password'] = PASSWORD
        br.submit()

        print("Student{} logged in.".format(student_num))
        loop_doing_questions(br, student_num, sim_gap, duration, result_q)
    except ValueError as e:
        message = "OOPS - student{} run broke: {}".format(student_num, e)
        print(message);
        result_q.put((student_num, -1, 0, message), block=False)


def simulate(num_students, avg_gap, sim_length):
    '''Do a full simulation run of multiple students
       for a duration of sim_length, with an average inter-submission
       time of avg_gap'''

    result_q = Queue()
    processes = []
    errs = []
    results = []

    start = time.time()
    finish = start + sim_length

    for student in range(START_STUDENT, START_STUDENT + NUM_STUDENTS):
        proc = Process(target=quiz_runner, args = ((student, avg_gap, sim_length, result_q)))
        print('STARTING STUDENT {}'.format(student))
        proc.start()
        processes.append(proc)

    # Loop reading result queue and displaying status
    while time.time() < finish + 10:
        try:
            (student, question, delta_t, error) = result_q.get(timeout=1)
            print('Student{}, Q{}: dt = {:.2f} {}'.format(student, question, delta_t, 'FAIL' if error else 'OK'))
            if error:
                errs.append(error)
            else:
                results.append(delta_t)
        except Empty:
            pass

    for p in processes:
        p.join()

    print(len(errs), ' errors')
    #print("Success times:", results)
    return (results, errs)



if __name__ == '__main__':
    before = time.time()
    (results, errs) = simulate(NUM_STUDENTS, INTER_SUBMISSION_GAP_SECS, SIMULATION_DURATION_SECS)
    after = time.time()
    elapsed = after-before
    print("min: {:.2f}".format(min(results)))
    print("max: {:.2f}".format(max(results)))
    print("avg: {:.2f}".format(sum(results)/len(results)))
    print('total time: %d' % elapsed)
    print('total successful submissions: %d' % len(results))
    print('total failed submissions: %d' % len(errs))
    rate_per_sec = len(results) / elapsed
    rate_per_min = 60 * rate_per_sec
    print('submission rate: {:.2f} submissions/sec ({:.0f} submissions/min)'.format(rate_per_sec, rate_per_min))

