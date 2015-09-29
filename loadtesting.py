#! /usr/bin/env python2

'''CodeRunner load tester.

    Tests the load handling capability of a server by submitting
    quiz attempts by a set of n users to a set of m quizzes in a
    pseudo-random manner, recording response time and correctness
    of response. It requires that a suitable set of quizzes be
    set up on the server.

    Mostly code written by Carl Cerecke, modified by Richard Lobb.

    Further modified to change from ccode to coderunner, Jan 2013.

    Assumed the existence of 4 1-question quizzes, called LoadTesting1 ..
    LoadTesting4, together with logins for 10 students, student0 ..
    student9.

    This program is very specific to the University of Canterbury
    but is included here on the off-chance it's useful to anyone else
    (with lots of twiddling of parameters).

    Richard Lobb, 17 April 2013.
'''

import mechanize as mech
from base64 import b16decode as decode
import time
from multiprocessing import Pool, Process, Queue
import itertools
import sys
import shelve
import random
import traceback

LANGUAGE = 'PYTHON3'

shelf = shelve.open('coderunner_loadtesting_results')

# Correct answers to the four one-question quizzes for each language.

quiz_answers = {

# ==== C ====
'C': ['''
float addMul(float a, float b, float c) {
    return (a + b) * c;
}
''',

'''
float approx_const(float x) {
    long long xScaled = x * 1000 + 0.5;
    return xScaled / 1000.0;
}
''',

'''
float c_to_f(float c) {
   return 32.0 + c * 9.0 / 5.0;
}
''',

'''
#include <stdio.h>

int main() {
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
'''def addMul(a, b, c): return (a + b) * c
''',

'''
def approx_const(x):
    xScaled = x * 1000 + 0.5
    return int(xScaled) / 1000.0
''',

'''
def c_to_f(c):
   return 32.0 + c * 9.0 / 5.0
''',

'''
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

FIRST_STUDENT = 5
LAST_STUDENT = 9

course = 'LoadTesting'
quizzes = [LANGUAGE + '_LoadTesting' + str(x) for x in range(1,5)]

students = ['student' + str(i) for i in range(FIRST_STUDENT, LAST_STUDENT + 1)]

all_args = list(itertools.product(quizzes,students))

MIN_N, MAX_N = 30, 40  # Range of subrange-lengths of all_args (see run_all_n)


def quiz_runner(arg_tup): #quiz_name, student, [Queue]
    '''Run the given quiz for the given student'''

    if len(arg_tup) == 2:
        quiz_name, student = arg_tup
        queue = None
    elif len(arg_tup) == 3:
        quiz_name, student, queue = arg_tup

    err = 'Wrong answer'
    quiz_num = int(quiz_name[-1]) - 1
    quiz_answer = quiz_answers[LANGUAGE][quiz_num]
    try:
        login = 'https://quiz.cosc.canterbury.ac.nz/login/index.php'

        print 'opening quiz page'
        br = mech.Browser()
        br.set_handle_robots(False)
        br.open(login)

        print 'Logging in as ' + student
        #print br.response().get_data()
        log_form = list(br.forms())[0]
        br.form = log_form
        br['username'] = student
        br['password'] = 'S-tudent0'
        br.submit()

        print "Logged in. Following link to " + course
        br.follow_link(text_regex=course)

        print "Follow link to " + quiz_name
        br.follow_link(text_regex=quiz_name)

        print "follow link to 'Attempt quiz now' (or 'Re-attempt quiz')"
        #br.follow_link(text_regex='Preview')
        #br.follow_link(text_regex='Re-attempt quiz')
        br.select_form(nr=0)
        br.submit()

        forms = list(br.forms())
        main_form = forms[0]
        textarea = main_form.controls[4]

        qa = quiz_answer;

        print 'Entering code into textarea:'
        textarea._value = qa

        br.form = main_form

        print 'submit %s code' % LANGUAGE
        start = time.time()
        br.submit()
        res = br.response()
        data = res.get_data()
        dt = time.time() - start
        if 'coderunner-test-results' in data:
            if 'coderunner-test-results good' in data:
                print 'Success! Test results returned in %.3f secs' % dt
                err = ''
            else:
                print '***Failed***! Test results returned in %.3f secs' % dt
                #f = open('junk.html', 'w')
                #f.write(data)
                #f.close()
                #sys.exit(0)
        else:
            print 'Something went wrong'
            print data

        print 'Finishing attempt'
        br.follow_link(text_regex='Finish attempt')

        print 'Getting review'
        br.select_form(nr=0)
        br.submit()

        print 'All done'
        br.close()
    except Exception, e:
        print '*** EXCEPTION', e
        traceback.print_exc()
        print br.response()
        err = str(e)
        dt = -1
    if queue:
        queue.put((dt, err), block=False)
    return (dt, err)


def run_n(n):
    '''Run, in parallel, the first n (student, quiz) pairings from
       the global all_args list'''
    arg_list = all_args[:n]
    pool = Pool(processes=len(arg_list))
    times = pool.map(quiz_runner, arg_list)
    floats = [x for (x, y) in times if y == '']
    errors = [y for (x, y) in times if y != '']
    avg = sum(floats)/len(floats) if len(floats) > 0 else -1
    return (n, min(floats), max(floats), sum(floats)/len(floats), len(errors))


def run_all_n():
    '''For a range of subranges of the global all_args variable from
       MIN_N to MAX_N, do a simulation run'''
    results = []
    for n in range(MIN_N, MAX_N + 1):
        print n
        result = run_n(n)
        shelf[str(n)] = result
        print result
        results.append(result)
    shelf.close()


def sim(avg_gap, sim_length):
    '''Do a full simulation run of multiple different (student,quiz)
       pairings for a duration of sim_length, with an average inter-submission
       time of avg_gap'''

    name = 'sim_gap_%d_length_%d' % (avg_gap, sim_length)
    arg_gen = itertools.cycle(all_args)
    lamb = 1.0/avg_gap
    result_q = Queue(10000)
    processes = []

    start = time.time()
    finish = start+sim_length

    while time.time() < finish:
        quiz_name, student = arg_gen.next()
        p = Process(target = quiz_runner, args = ((quiz_name, student, result_q),))
        print 'START %s %s' % (quiz_name, student)
        p.start()
        processes.append(p)
        time.sleep(random.expovariate(lamb))

    for p in processes:
        p.join()

    results = []
    errs = []
    while not result_q.empty():
        (dt, err) = result_q.get(False)
        if err:
            errs.append(err)
        else:
            results.append(dt)
    shelf[name] = (results, errs)
    shelf.close()
    print len(errs), ' exceptions'
    print "Success times:", results
    return (results, errs)


if __name__ == '__main__':
    SIMULATION_GAP_SECS = 2
    SIMULATION_DURATION_SECS = 60

    before = time.time()
    (results, errs) = sim(SIMULATION_GAP_SECS, SIMULATION_DURATION_SECS)
    after = time.time()
    print "min:",min(results)
    print "max:",max(results)
    print "avg:",sum(results)/len(results)
    print 'total time: %d' % (after-before)
    print 'total successful submissions: %d' % len(results)
    print 'total failed submissions: %d' % len(errs)

