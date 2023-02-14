## Scratchpad UI
The **Scratchpad UI** is an extension to the **Ace UI**:
- It is designed to allow the execution of code in the CodeRunner question in a manner similar to an IDE.
- It contains two editor boxes, one on top of another, allowing users to enter and edit code in both.

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


### Switching a Question to Use the Scratchpad UI

To switch an Ace UI question to use the Scratchpad UI:
   1. edit the question;
   2. make sure the "Ace/Scratchpad compliant" tick-box is checked;
   3. tick customise (second option, in first section);
   4. in the "Customisation" section, change "Input UIs" from "Ace" to "Scratchpad";
   5. save the question.

### Serialisation

Pressing CTRL ALT M will disable the plugin, exposing the underlying serialisation.
For most UIs this serialisation is passed into the question template as STUDENT_ANSWER. 
When "Ace/Scratchpad compliant" is ticked STUDENT_ANSWER is set to the value of the first editor instead.

Note: The following information is not relevant unless you un-tick the "Ace/Scratchpad compliant" tick-box.

The serialisation for this plugin is JSON, with fields:
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
- `open_delimiter`: The opening delimiter to use when inserting answer or Scratchpad code. It will replace the default value `{|`.
- `close_delimiter`: The closing delimiter to use when inserting answer or Scratchpad code. It will replace the default value `|}`.
- `disable_scratchpad`:	disable the scratchpad, reverting to Ace UI from student perspective.
- `invert_prefix`: inverts meaning of prefix_ans serialisation -- `'1'` means un-ticked, vice versa. This can be used to swap the default state.
- `escape`: when `true` code will be JSON escaped (minus outer quotes `"`) before being inserted into the wrapper.
- `params` : parameters for the sandbox webservice.

### Wrappers
A wrapper is used to wrap code before it is run using the sandbox.
A wrapper could be used to enclose the code in a function call, or to run the program as a subprocess after manipulation.
Some tasks that require this are: running languages installed on Jobe but not supported by coderunner; reading standard input during runs; or displaying Matplotlib graphs.


You can insert the answer code and scratchpad code into the wrapper using `{| ANSWER_CODE |}` and `{| SCRATCHPAD_CODE |}` respectively.
If the **Prefix with Answer** checkbox is unchecked `{| ANSWER_CODE |}` will be replaced with an empty string `''`.
The default configuration uses the following wrapper:


```
{| ANSWER_CODE |}
{| SCRATCHPAD_CODE |}
```
Whitespace is ignored between the delimiters (`{|`,`|}`) and the variable name, e.g. `{|ANSWER_CODE   |}` will be replaced.
You can change the delimiters using the `open_delimiter` and `close_delimiter` UI Parameters. 


Four UI parameters are of particular importance when writing wrappers:

- `wrapper_src` sets the location of the wrapper code.
- `run_lang` sets the language the Sandbox Webservice uses to run code when the **Run Button** is pressed.
- `output_display_mode` controls how run output is displayed, see below. 
- `escape` will escape (JSON escape with `"` removed from start and end) `ANSWER_CODE` and `SCRATCHPAD_CODE` before insertion into wrapper. Useful when inserting code into a string. NOTE: _single quotes `'` are NOT escaped.

There are three modes of displaying program run output, set by `output_display_mode`:
  - `text`: Display the output as text, html escaped. **(default)**
  - `json`: Display programs that output JSON, useful for capturing stdin and displaying images. **(recommended)**
    - Accepts JSON in run output with the fields:
      - `returncode`: Exit code from program run.
      - `stdout`: Stdout text from program run.
      - `stderr`: Error text from program run.
      - `files`: An object containing filenames mapped to base64 encoded images. These will be displayed below any stdout text.
    - When the `returncode` is set to `42`, an HTML input field will be added after the last `stdout` received.
      When the enter key is pressed inside the input, the input's value is added to stdin and the program is run again with this updated stdin.
      This is repeated until `returncode` is not set to `42`.
  - `html`: Display program output as raw html inside the output area. **(advanced)**
    - This can be used to show images and insert other HTML.
    - Giving an `<input>` element the class `coderunner-run-input` will add an event: when the enter key is pressed inside the input, the input's value is added to stdin and the program is run again with this updated stdin.

Note: JSON is the preferred display mode; wrapper debugging is much simpler than HTML mode. 
HTML output is only recommended if you are trying to display HTML elements and for very advanced users, enter at your own risk...
