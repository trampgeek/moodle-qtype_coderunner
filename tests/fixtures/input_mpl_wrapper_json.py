import ast, traceback, sys, io, subprocess, base64, os, ast, traceback, json
MAX_OUTPUT_CHARS = 30000

student_code = """
\( ANSWER_CODE \)
\( SCRATCHPAD_CODE \)
"""

uses_matplotlib = 'matplotlib' in student_code

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

if uses_matplotlib:
    subproc_code += """
import os, tempfile, sys
os.environ["MPLCONFIGDIR"] = tempfile.mkdtemp()
import matplotlib as _mpl
_mpl.use("Agg")
"""

subproc_code += student_code

if uses_matplotlib:
    subproc_code += """
figs = _mpl.pyplot.get_fignums()
for i, fig in enumerate(figs):
    _mpl.pyplot.figure(fig)
    filename = f'image{i}.png'
    _mpl.pyplot.savefig(filename, bbox_inches='tight')
"""





def b64encode(filename):
    """Return the contents of the given file in base64"""
    with open(filename, "br") as fin:
        contents = fin.read()
    contents_b64 = base64.b64encode(contents).decode("utf8")
    return contents_b64

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

# Pick up any .png or .jpg image files.
image_extensions = ['png', 'jpg', 'jpeg']
image_files = [fname for fname in os.listdir() if fname.lower().split('.')[-1] in image_extensions]
files = {fname: b64encode(fname) for fname in image_files}

output = {
    'returncode': returncode,
    'stdout' : truncated(stdout),
    'stderr' : truncated(stderr),
    'files'  : files
}

print(json.dumps(output))