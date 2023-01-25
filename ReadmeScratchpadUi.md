## Scratchpad UI
The **Scratchpad UI** is like the **Ace UI**, but with two editors for code entry. By default, only one editor is visible and the Scratchpad is hidden -- clicking the **Scratchpad button** shows it. The Scratchpad area contains a second editor, a **Run button** and a **Prefix with Answer** checkbox. Additionally, there is a help button that provides information about how to use the Scratchpad. It's possible to run code 'in-browser' using the **Run Button**:
- When prefix with answer is not checked, only the code in the scratchpad is run -- allowing for a rough working spot to quickly check the result of code.
- When the prefix answer is checked, the code in the scratchpad is appended to the code in the first editor before being run.

Note: *This behavior can be modified, see wrappers...*

### Serialisation
The UI serialises to JSON, with fields:
- `answer_code`: A list containing a string with answer code from the first editor;
- `test_code`: A list containing a string with containing answer code from the second editor;
- `show_hide`: `["1"]` when scratchpad is visible, otherwise `[""]`;
- `prefix_ans`: `["1"]` when **Prefix with Answer** is checked, otherwise `[""]`.

A special case: *if all fields are empty but `prefix_ans` is `'[1]'`, the serialisation itself is the empty string.*

Sample serialisation: `{"answer_code":["print('hello world')"],"test_code":[""],"show_hide":["1"],"prefix_ans":["1"]}`

### UI Parameters

- `scratchpad_name`: the display name of the scratchpad, used to hide/un-hide the scratchpad.
- `button_name`: the run button text.
- `prefix_name`: the prefix with answer check-box label text.
- `help_text`: the help text to show.
- `run_lang`: the language used to run code when the run button is clicked, this should be the language your wrapper is written in (if applicable).
- `wrapper_src`: the location of wrapper to be used by the run button:
    - setting to `globalextra` will use text in global extra field,
    - `prototypeextra` will use the prototype extra field.
- `html_output`: when true, the output from run will be displayed as raw HTML instead of text.
- `disable_scratchpad`:	disable the scratchpad, effectively revert back to Ace UI from student perspective.
- `invert_prefix`: inverts meaning of prefix_ans serialisation -- `'1'` means un-ticked, vice versa. This can be used to swap the default state.
- `params` : **THESE ARE NOT WELL DOCUMENTED**


### Advanced Customization: Wrappers
Sometimes the default configuration will not be flexible enough. To run langues installed on jobe but not supported by coderunner, read standard input with run, or to display Matplotlib graphs with run all require the use of a wrapper.

A wrapper can be used to wrap code before it is run using the sandbox. You can insert the answer code and scratchpad code into the wrapper, using `{{ ANSWER_CODE }}` and `{{ SCRATCHPAD_CODE }}` respectively. If the **Prefix with Answer** checkbox is unchecked `{{ ANSWER_CODE }}` will be replaced with an empty string `''`.  The default configuration uses the following wrapper:
```
{{ ANSWER_CODE }}
{{ SCRATCHPAD_CODE }}
```


You can set the Run language, using `run_lang`. This changes the language the sandbox service uses to run the wrapper; wrappers can be a different language to the question. To the student answering the question this would be invisible. Below is an example of a C program being wrapped using Python (see the multi-language question for further inspiration):
 ```
 import subprocess
 
student_answer = """{{ ANSWER_CODE }}"""
test_code = """{{ SCRATCHPAD_CODE }}"""
all_code = student_answer + '\n' + test_code
 filename = '__tester__.c
 with open(filename, "w") as src:
    print(all_code, file=src)

cflags = "-std=c99 -Wall -Werror"
return_code = subprocess.call("gcc {0} -o __tester__ __tester__.c".format(cflags).split())
if return_code != 0:
    raise Exception("** Compilation failed. Testing aborted **")
exec_command = ["./__tester__"]
 
 output = subprocess.check_output(exec_command, universal_newlines=True)
print(output)
 ```
To expand, it is possible to wrap the scratchpad code inside a main function, or use a modified wrapper to run unsupported code.

The `html_output` parameter, in conjunction with a wrapper, can be used to display graphical/non-textual output in the output display area. Using HTML output, it is possible to insert images and input boxes.

Example of a wrapper to display `Matplotlib` graphs in the output display area:
```
import subprocess, base64, html, os, tempfile


def make_data_uri(filename):
    with open(filename, "br") as fin:
        contents = fin.read()
    contents_b64 = base64.b64encode(contents).decode("utf8")
    return "data:image/png;base64,{}".format(contents_b64)


code = r"""{{ ANSWER_CODE }}
{{ SCRATCHPAD_CODE }}
"""

prefix = """import os, tempfile
os.environ["MPLCONFIGDIR"] = tempfile.mkdtemp()
import matplotlib as _mpl
_mpl.use("Agg")
"""

suffix = """
figs = _mpl.pyplot.get_fignums()
for i, fig in enumerate(figs):
    _mpl.pyplot.figure(fig)
    filename = f'image{i}.png'
    _mpl.pyplot.savefig(filename, bbox_inches='tight')
"""

prog_to_exec = prefix + code + suffix

with open('prog.py', 'w') as outfile:
    outfile.write(prog_to_exec)

result = subprocess.run(['python3', 'prog.py'], capture_output=True, text=True)
print('<div>')
output = result.stdout + result.stderr
if output:
    output = html.escape(output).replace(' ', '&nbsp;').replace('\n', '<br>')
    print(f'<p style="font-family:monospace;font-size:11pt;padding:5px;">{output}</p>')

for fname in os.listdir():
    if fname.endswith('png'):
        print(f'<img src="{make_data_uri(fname)}">')
```
Note: `html_output` *must* be `true` for the image to be displayed.