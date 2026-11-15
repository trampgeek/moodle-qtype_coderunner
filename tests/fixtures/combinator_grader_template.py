import subprocess, json, sys
{{ STUDENT_ANSWER | e('py') }}
tests = json.loads('''{{ TESTCASES | json_encode | e('py') }}''')
test_results = [['Test', 'Expected', 'Got', 'iscorrect']]
num_right = 0
for test in tests:
  code = test['testcode']
  expected = int(test['expected'])
  got = eval(code)
  test_results.append([code, expected, got, expected == got])
  if expected == got:
    num_right += 1
print(json.dumps({
  'testresults': test_results,
  'fraction': num_right / len(tests),
  'prologuehtml': '<h1>I am a prologue</h1>',
  'epiloguehtml': '<h1>I am an epilogue</h1>'
}))
