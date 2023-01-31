## Scratchpad UI
The **Scratchpad UI** is an extension of the **Ace UI**:
- The Scratchpad UI is designed to allow the execution of code in the CodeRunner question in a manner similar to an IDE.
- The Scratchpad UI contains two editor boxes, one on top of another, allowing users to enter and edit code in both.

By default, only the top editor is visible and the **Scratchpad Area**, which contains the bottom editor, is hidden. 
Clicking the **Scratchpad Button** shows the **Scratchpad Area**. 
This includes a second editor, a **Run button** and a **Prefix with Answer** checkbox and an **Output Display Area**. 
Additionally, there is a **Help Button** that provides information about how to use the Scratchpad. 

It's possible to run code 'in-browser' by clicking the **Run Button**, _without_ making a submission via the **Check Button**:
- If **Prefix with Answer** is not checked, only the code in the **Scratchpad Editor** is run -- allowing for a rough working spot to quickly check the result of code.
- Otherwise, when **Prefix with Answer** is checked, the code in the **Scratchpad Editor** is appended to the code in the first editor before being run.

The Run Button has some limitations when using its default configuration:
- Does not support programs that use STDIN (by default);
- Only supports textual STDOUT (by default).

Note: *These features can be supported, see wrappers...*

### Serialisation
The UI state serialises to JSON, with fields:
- `answer_code`: `[""]` A list containing a string with answer code from the first editor;
- `test_code`: `[""]` A list containing a string with containing answer code from the second editor;
- `show_hide`: `["1"]` when scratchpad is visible, otherwise `[""]`;
- `prefix_ans`: `["1"]` when **Prefix with Answer** is checked, otherwise `[""]`.

A special case is the default serialisation: `{"answer_code":[""],"test_code":[""],"show_hide":["1"],"prefix_ans":["1"]}` is converted to `""` (an empty string).

The UI will also accept (and convert) JSON with only the field `answer_code` and strings to a valid serialisation.
A valid serialisation is one with all four specified fields. All other serialisations will be rejected by the interface.

### UI Parameters

- `scratchpad_name`: display name of the scratchpad, used to hide/un-hide the scratchpad.
- `button_name`: run button text.
- `prefix_name`: prefix with answer check-box label text.
- `help_text`: help text to show.
- `run_lang`: language used to run code when the run button is clicked, this should be the language your wrapper is written in (if applicable).
- `wrapper_src`: location of wrapper code to be used by the run button, if applicable:
    - setting to `globalextra` will use text in global extra field,
    - `prototypeextra` will use the prototype extra field.
- `output_display_mode`: control how program output is displayed on runs, there are three modes:
  - `text`: display program output as text, html escaped;
  - `json`: display program output, when it is json, see next section...
  - `html`: display program output as raw html.
- `disable_scratchpad`:	disable the scratchpad, effectively revert back to Ace UI from student perspective.
- `invert_prefix`: inverts meaning of prefix_ans serialisation -- `'1'` means un-ticked, vice versa. This can be used to swap the default state.
- `params` : **THESE ARE NOT WELL DOCUMENTED**


### Advanced Customization: Wrappers
A wrapper is be used to wrap code before it is run using the sandbox.
A wrapper could be used to enclose the code in a function call, or to run the program as a subprocess after manipulation.
Some tasks that require this include: running languages installed on Jobe but not supported by coderunner; reading standard input during runs; or displaying Matplotlib graphs.


You can insert the answer code and scratchpad code into the wrapper using `{{ ANSWER_CODE }}` and `{{ SCRATCHPAD_CODE }}` respectively.
If the **Prefix with Answer** checkbox is unchecked `{{ ANSWER_CODE }}` will be replaced with an empty string `''`.
The default configuration uses the following wrapper:


```
{{ ANSWER_CODE }}
{{ SCRATCHPAD_CODE }}
```

Two UI parameters are of particular importance when writing wrappers:

- `run_lang` sets the language the Sandbox Webservice uses to run code when the **Run Button** is pressed.
- `output_display_mode` controls how run output is displayed, see below. 

There are three modes of displaying program run output, set by `output_display_mode`:
  - `text`: Display the output as text, html escaped. **(default)**
  - `json`: Display programs that output JSON, useful for capturing stdin and displaying images. **(recommended)**
    - Accepts JSON in run output with the fields:
      - `returncode`: Exit code from program run.
      - `stdout`: Stdout text from program run.
      - `stderr`: Error text from program run.
      - `files`: Images encoded in base64 text encoding. These will be displayed above any stdout text.
    - When the `returncode` is set to `42`, an HTML input field will be added after the last `stdout` received.
      When the enter key is pressed inside the input, the input's value is added to stdin and the program is run again with this updated stdin.
      This is repeated until `returncode` is not set to `42`.
  - `html`: Display program output as raw html inside the output area. **(advanced)**
    - This can be used to show images and insert other HTML.
    - Giving an `<input>` element the class `coderunner-run-input` will add an event: when the enter key is pressed inside the input, the input's value is added to stdin and the program is run again with this updated stdin.

Note: JSON is the preferred display mode; wrapper debugging is much simpler than HTML mode. 
HTML output is only recommended if you are trying to display HTML elements and for very advanced users, enter at your own risk...

## Wrapper tutorial (TBD)
#### Wrappers I: Running code in unsupported languages
Wrappers can be in a different language to their question; you can set the Run language, using `run_lang`. 
This changes the language the sandbox service uses to run the wrapper.
This would be invisible to the student answering the question. 
Below is an example of a C program being wrapped using Python (see the multi-language question for further inspiration):
 ```
 import subprocess
 
student_answer = """{{ ANSWER_CODE }}"""
test_code = """{{ SCRATCHPAD_CODE }}"""
all_code = student_answer + '\n' + test_code
 filename = '__tester__.c'
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
Note: When writing wrappers it is recommended to use a scripting language with strong string manipulation features.

#### Wrappers II: Displaying on-textual run output
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
#### Wrappers III: Reading stdin during runs

[//]: # (A module used for running code using the Coderunner webservice &#40;CRWS&#41; and displaying output. Originally)

[//]: # (developed for use in the Scratchpad UI. It has three modes of operation:)

[//]: # (- 'text': Just display the output as text, html escaped.)

[//]: # (- 'json': The recommended way to display programs that use stdin or output images &#40;or both&#41;.)

[//]: # (  - Accepts JSON in the CRWS response output with fields:)

[//]: # (    - `"returncode"`: Error/return code from running program.)

[//]: # (    - `"stdout"`: Stdout text from running program.)

[//]: # (    - `"stderr"`: Error text from running program.)

[//]: # (    - `"files"`: Images encoded in base64 text encoding. These will be displayed above any stdout text.)

[//]: # (  - When input from stdin is required the returncode 42 should be returned, raise this)

[//]: # (    any time the program asks for input. An &#40;html&#41; input will be added after the last stdout received.)

[//]: # (    When enter is pressed, runCode is called with value of the input added to the stdin string.)

[//]: # (    This repeats until returncode is no longer 42.)

[//]: # (- 'html': Display program output as raw html inside the output area.)

[//]: # (  - This can be used to show images and insert other HTML tags &#40;and beyond&#41;.)

[//]: # (  - Giving an `<input>` tag the class 'coderunner-run-input' will add an event that)

[//]: # (    on pressing enter will call the runCode method again with the value of that input field added to stdin.)

[//]: # (    This method of receiving stdin is harder to use but more flexible than JSON, enter at your own risk.)