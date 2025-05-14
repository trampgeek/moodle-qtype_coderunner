import sys, io, subprocess, ast, traceback, json
MAX_OUTPUT_CHARS = 30000

student_code = """
{{ ANSWER_CODE }}
{{ SCRATCHPAD_CODE }}
"""

subproc_code = """
import sys
MAX_OUTPUT_CHARS = 30000
__saved_input__ = input

def input(prompt=''):
    try:
        line = __saved_input__()
    except EOFError:
        print(prompt, end = '')
        sys.stderr.flush()
        sys.stdout.flush()
        sys.exit(42)
    print(prompt, end='')
    print(line)
    return line

__saved_print__ = print
__output_chars__ = 0

def print(*params, **keyparams):
    global __output_chars__
    for param in params:
        try:
            __output_chars__ += len(str(param))
        except:
            pass
    if __output_chars__ > 2 * MAX_OUTPUT_CHARS:
        __saved_print__("\\\\n*** Excessive output. Job aborted ***", file=sys.stderr)
        sys.exit(1)
    else:
        __saved_print__(*params, **keyparams)
"""

subproc_code += student_code

def truncated(s):
    return s if len(s) < MAX_OUTPUT_CHARS else s[:MAX_OUTPUT_CHARS] + ' ... (truncated)'

def check_syntax():
    try:
        ast.parse(student_code)
        return ''
    except SyntaxError:
        catcher = io.StringIO()
        traceback.print_exc(limit=0, file=catcher)
        return catcher.getvalue()

stdout = ''
stderr = check_syntax()
if stderr == '':  # No syntax errors
    program_code = subproc_code
    with open('prog.py', 'w') as outfile:
        outfile.write(program_code)
    proc = subprocess.Popen([sys.executable, 'prog.py'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    try:
        stdout, stderr = proc.communicate(timeout=3)
        returncode = proc.returncode
    except subprocess.TimeoutExpired:
        proc.kill()
        stdout, stderr = proc.communicate()
        returncode = 13


else:
    returncode = 1 # Syntax errors

output = {
    'returncode': returncode,
    'stdout' : truncated(stdout),
    'stderr' : truncated(stderr),
    'files'  : [],
}

print(json.dumps(output))