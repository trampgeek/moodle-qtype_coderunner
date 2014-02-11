// JavaScript functions for CodeRunner
// Thanks for Ulrich Dangel for the code that loads and configures ace.

M.qtype_coderunner = {};


// Function to load ace and insert an editor into the current page
M.qtype_coderunner.init_ace = function (Y, field, lang) {

    // Load the required ace modules
    if (! M.qtype_coderunner.modelist) {
        M.qtype_coderunner.modelist = ace.require('ace/ext/modelist');
        ace.require("ace/ext/language_tools");
    }

    ace_setup();

    // helper function to insert an editor after the specific selector
    function ace_setup() {
        var yta = Y.one('[id="' + field + '"]');
        if (yta)
            create_editor_element(yta);
    }

    // try to find the correct ace language mode
    function find_mode(language) {
        var modelist = M.qtype_coderunner.modelist;

        // possible candiates for the editor
        var candidates = [language, language.replace(/\d*$/, "")];

        for (var i=0; i<candidates.length; i++) {
            var v = candidates[i];
            var filename = "input." + v;

            var result = modelist.modesByName[v] ||
                modelist.modesByName[v.toLowerCase()] ||
                modelist.getModeForPath(filename) ||
                modelist.getModeForPath(filename.toLowerCase());

            if (result && result.name != 'text')
                return result;
        }
    }

    // create ace editor for a specific text area
    function create_editor_element(textarea) {
        var id = textarea.get("id")
        var edit_node = Y.Node.create("<div></div>");
        textarea.insert(edit_node, "after");

        // Mimic the existing textarea
        edit_node.set("offsetHeight", parseInt(textarea.getComputedStyle("height")));
        edit_node.set("offsetWidth", parseInt(textarea.getComputedStyle("width")));


        var editor = ace.edit(edit_node.getDOMNode());
        editor.getSession().setValue(textarea.get('value'));
        editor.getSession().on('change', function(){
            textarea.set('value', editor.getSession().getValue());
        });

        if (textarea.getAttribute('readonly'))
            editor.setReadOnly(true);

        textarea.hide();

        var mode = find_mode(lang);
        if (mode)
            editor.getSession().setMode(mode.mode);

        editor.setOptions({
            enableBasicAutocompletion: true,
            newLineMode: "unix"
        });


	if (! document.activeElement.parentNode.className.match("ace"))
            editor.focus();
    }


}


// Function to initialise all code-input text-areas in a page.
// Used by the form editor but can't be used for question text areas as
// renderer.php is called once for each question in a quiz, and there is
// no communication between the questions.
M.qtype_coderunner.setupAllTAs = function (Y) {
    Y.all('.edit_code').each(function (yta) {
        M.qtype_coderunner.initTextArea(Y, yta);
    });
};


// Initialise a particular text area (TA), given its ID.
// Can't use IDs with underscores in Y.one -- use DOM method instead. Grrrr.
M.qtype_coderunner.initQuestionTA = function (Y, taId) {
    var ta = Y.one('[id="' + taId + '"]');
    M.qtype_coderunner.initTextArea(Y, ta);
};


// Set up the JavaScript to handle a given text area (as a YUI node)
// Having given up on syntax colouring editors in the YUI context, I
// now just do rudimentary autoindent on return and replace tab with
// 4 spaces always.
// For info on key handling browser inconsistencies see http://unixpapa.com/js/key.html
M.qtype_coderunner.initTextArea = function (Y, yta) {
    var i = 0,
        ENTER = 13,
        TAB = 9,
        SPACE = 32;
    yta.on('keydown', function(e) {
        var ta = yta.getDOMNode()
        if(e.which == undefined || e.which != 0) { // 'Normal' keypress?
            if (e.keyCode == TAB) {
                // Ignore SHIFT/TAB. Insert 4 spaces on TAB.
                if (e.shiftKey || M.qtype_coderunner.insertString(Y, ta, "    ")) {
                    e.preventDefault();
                }
            }
            else if (e.keyCode == ENTER && ta.selectionStart != undefined) {
                // Handle autoindent only on non-IE
                var before = ta.value.substring(0, ta.selectionStart);
                var eol = before.lastIndexOf("\n");
                var line = before.substring(eol + 1);  // take from eol to end
                var indent = "";
                for (i=0; i < line.length && line.charAt(i) == ' '; i++) {
                    indent = indent + " ";
                }
                if (M.qtype_coderunner.insertString(Y, ta, "\n" + indent)) {
                    e.preventDefault();
                }
            }
        }
    });
};

M.qtype_coderunner.insertString = function(Y, ta, sToInsert) {
    if (ta.selectionStart != undefined) {  // firefox etc.
        var before = ta.value.substring(0, ta.selectionStart);
        var selSave = ta.selectionEnd;
        var after = ta.value.substring(ta.selectionEnd, ta.value.length);

        // update the text field
        var tmp = ta.scrollTop;  // inhibit annoying auto-scroll
        ta.value = before + sToInsert + after;
        var pos = selSave + sToInsert.length;
        ta.selectionStart = pos;
        ta.selectionEnd = pos;
        ta.scrollTop = tmp;
        return true;

    }
    else if (document.selection && document.selection.createRange) { // IE
        var r = document.selection.createRange();
        var dr = r.duplicate();
        dr.moveToElementText(ta);
        dr.setEndPoint("EndToEnd", r)
        var c = dr.text.length - r.text.length;
        var b = ta.value.substring(0, c);
        r.text = sToInsert;
        return true;
    }
    // Other browsers we can't handle
    else {
        return false;
    }
};

// Script for the edit_coderunner_form page.
M.qtype_coderunner.initEditForm = function(Y) {
    var typeCombo = Y.one('#id_coderunner_type'),
        template = Y.one('#id_per_test_template'),
        enable_combinator = Y.one('#id_enable_combinator'),
        combinator_template = Y.one('#id_combinator_template'),
        test_splitter = Y.one('#id_test_splitter_re'),
        language = Y.one('#id_language'),
        templateBlock = Y.one('#fitem_id_per_test_template'),
        gradingBlock = Y.one('#fgroup_id_gradingcontrols'),
        columnDisplayBlock = Y.one('#fgroup_id_columncontrols'),
        sandboxBlock = Y.one('#fgroup_id_sandboxcontrols'),
        customise = Y.one('#id_customise'),
        cputime = Y.one('#id_cputimelimitsecs'),
        memlimit = Y.one('#id_memlimitmb'),
        customisationFieldSet = Y.one('#id_customisationheader'),
        advancedCustomisation = Y.one('#id_advancedcustomisationheader'),
        isCustomised = customise.get('checked'),
        combinator_non_blank = true,
        prototypeType = Y.one("#id_prototype_type"),
        typeName = Y.one('#id_type_name'),
        message = '';

    function setCustomisationVisibility(isVisible) {
        var display = isVisible ? 'block' : 'none';
        customisationFieldSet.setStyle('display', display);
        advancedCustomisation.setStyle('display', display);
    }

    function loadDefaultCustomisationFields(Y) {
        // Local function to load the various customisation fields on the
        // form with their default values for the current Coderunner question
        // type.
        var newType = typeCombo.get('value'),
            secs = '',
            mb = '';

        if (newType != '' && newType != 'Undefined') {
            Y.io(M.cfg.wwwroot + '/question/type/coderunner/ajax.php', {
                method: 'GET',
                data: 'qtype=' + newType + '&sesskey=' + M.cfg.sesskey,
                on: {
                    success: function (id, result) {
                        var outcome = JSON.parse(result.responseText);
                        if (outcome.success) {
                            template.set('text', outcome.per_test_template);
                            secs = outcome.cputimelimitsecs ? outcome.cputimelimitsecs : '';
                            cputime.set('value', secs);
                            mb = outcome.memlimitmb ? outcome.memlimitmb : '';
                            memlimit.set('value', mb);
                            combinator_template.set('text', outcome.combinator_template);
                            enable_combinator.set('checked', outcome.enable_combinator);
                            test_splitter.set('value', outcome.test_splitter_re);
                            language.set('value', outcome.language);
                            if (outcome.prototype_type != 0) {
                                typeName.set('value', newType);
                            }
                        }
                        else {
                            template.set('text', "*** AJAX ERROR. DON'T SAVE THIS! ***\n" + outcome.error);
                        }

                    },
                    failure: function (id, result) {
                        template.set('text', "*** AJAX ERROR. DON'T SAVE THIS! ***");
                    }
                }
            });
        };
    };

    if (prototypeType.get('value') == 1) {
        alert('Editing a built-in question prototype?! Proceed at your own risk!');
        prototypeType.set('disabled', true);
        typeCombo.set('disabled', true);
        customise.set('disabled', true);
    }
    setCustomisationVisibility(isCustomised);
    if (!isCustomised) {
        loadDefaultCustomisationFields(Y);
    }

    customise.on('change', function(e) {
       isCustomised = customise.get('checked');
       if (isCustomised) {
           // Customisation is being turned on. Disable combinator.
           // [User must explicitly re-enable it if they wish to use it.]
           if (confirm("Enable customisation (disables use of the combinator template)?")) {
               enable_combinator.set('checked', false);
               setCustomisationVisibility(true);
           } else {
               customise.set('checked', false);
           }

       } else { // Customisation being turned off
           combinator_non_blank = combinator_template.get('text').trim() !== '';
           message = "If you save this question with 'Customise' " +
               "unchecked, any customisation you've done will be lost.";
           if (combinator_non_blank) {
               message += " Also, use of the combinator template will be re-enabled.";
           }
           if (confirm(message + " Proceed?")) {
                 setCustomisationVisibility(false);
                 if (combinator_non_blank) {
                    enable_combinator.set('checked', true);
                 }
           } else {
               customise.set('checked',true);
           }
       }
    });


    // TODO: disallow changing it back to Undefined
    typeCombo.on('change', function(e) {
        if (customise.get('checked')) {
            if (confirm("Changing question type. Click OK to reload customisation fields , Cancel to retain your customised ones.")) {
                loadDefaultCustomisationFields(Y);
            }
        } else {
            loadDefaultCustomisationFields(Y);
        }
    });
};

// Code to switch from the simple combo select box for question types
// to a YUI menu system. Disabled as it didn't look nice. Possibly
// re-enable if nasty flashing on load can be avoided and better CSS written.
M.qtype_coderunner.useYuiTypesMenu = function(Y) {
    var typesMenu = Y.one('#question_types').plug(Y.Plugin.NodeMenuNav),
        defaultComboGroup = Y.one('#fitem_id_coderunner_type'),
        combo = Y.one('#id_coderunner_type'),
        currentValue = combo.get('value'),
        topLevel = typesMenu.one('a');

    // Switch off default combo box and enable YUI menu system instead

    typesMenu.setStyle('display', 'inline-block');
    defaultComboGroup.setStyle('display', 'none');

    if (currentValue != 'Undefined') {
        topLevel.set('text', currentValue);
    }

    typesMenu.delegate('click', function(e) {
           var url = e.target.get('href'),
               targetPos = url.lastIndexOf('#'),
               target = '';
           if (targetPos > 0) {
               target = url.substr(targetPos + 1);
           }

           // Update hidden combo state to the newly selected value
           combo.all('option').each(function(opt) {
                    if (opt.get('text') == target) {
                        opt.set('selected', 'selected');
                        topLevel.set('text', target);  // Change top level text
                    } else {
                        opt.removeAttribute('selected');
                    }
                }
            );
           return false;
    }, 'a');
};
