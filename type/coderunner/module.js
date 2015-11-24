// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for the CodeRunner question type.
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  Richard Lobb, 2015, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Thanks to Ulrich Dangel for the initial implementation of Ace within
// CodeRunner.


M.qtype_coderunner = M.qtype_coderunner || {};

// Functions to allow the use of the Ace editor for code text areas.
M.qtype_coderunner.init_ace = function (Y, field, lang) {
    
    var HANDLE_SIZE = 5,
        MIN_WIDTH = 300,
        MIN_HEIGHT = 100,
    
        mode =  null,
        session = null,
        textarea = null;

    // try to find the correct ace language mode
    function find_mode(language) {
        var modelist = M.qtype_coderunner.modelist,
            candidates = []; // List of candidate modes

        if (language.toLowerCase() === 'octave') {
            language = 'matlab';
        }

        candidates = [language, language.replace(/\d*$/, "")];
        for (var i=0; i<candidates.length; i++) {
            var v = candidates[i];
            var filename = "input." + v;

            var result = modelist.modesByName[v] ||
                modelist.modesByName[v.toLowerCase()] ||
                modelist.getModeForPath(filename) ||
                modelist.getModeForPath(filename.toLowerCase());

            if (result && result.name !== 'text') {
                return result;
            }
        }
    }

    // create ace editor for a specific text area
    function create_editor_element(textarea, mode) {
        var id = textarea.get("id"),
            h = parseInt(textarea.getComputedStyle("height")),
            w = parseInt(textarea.getComputedStyle("width")),
            wrapper_node = Y.Node.create('<div></div>'),
            edit_node = Y.Node.create("<div></div>"),
            editor = null,
            parent = null, 
            contents_changed = false,
            hLast = h - HANDLE_SIZE,
            wLast = w - HANDLE_SIZE,
            do_resize = function (h, w) {
                // Resize the editor to fit within a resizable wrapper 
                // div of the given height and width, allowing a bit of
                // space to show at least some of the resize handle.
                // This is a bit hacky, but I haven't figured out any way
                // to incorporate the resize handle into the edit div itself.
                edit_node.set("offsetHeight", h - HANDLE_SIZE);
                edit_node.set("offsetWidth", w - HANDLE_SIZE);
                editor.resize();
                hLast = h;
                wLast = w;
            };
        
        wrapper_node.setStyles({
            resize: 'both',
            overflow: 'hidden',
            height: h,
            width: w,
            minWidth: MIN_WIDTH,  // GRRR YUI needs camelCase not hyphens
            minHeight: MIN_HEIGHT
        });
        

        edit_node.setStyles({
            resize: 'none', // Chrome wrongly inherits this
            height: h - HANDLE_SIZE,
            width: w - HANDLE_SIZE
        }); 
        
        textarea.insert(wrapper_node, "after");
        wrapper_node.insert(edit_node, "replace");
     
        editor = ace.edit(edit_node.getDOMNode());
        if (textarea.getAttribute('readonly')) {
            editor.setReadOnly(true);
        }
        editor.getSession().setValue(textarea.get('value'));
        editor.getSession().on('change', function(){
            textarea.set('value', editor.getSession().getValue());
            contents_changed = true
        });
        editor.on('blur', function() {
            if (contents_changed) {
                textarea.simulate('change');
            }
        });
         
        if (mode) {
            editor.getSession().setMode(mode.mode);
        }

        editor.setOptions({
            enableBasicAutocompletion: true,
            newLineMode: "unix",
        });
        
        textarea.hide();
        
        /* Because chrome doesn't generate mutation events when a user resizes
         * a resizable div, we have to poll the wrapper div size on mouse 
         * motion events. Furthermore, we need to observe the mouse events
         * on the parent window rather than the resizable div, as Chrome doesn't
         * generate the events on the child (unlike Firefox).
         */
        parent = wrapper_node.ancestor();
        parent.on('mousemove', function () {
            var h = wrapper_node.get('offsetHeight'),
                w = wrapper_node.get('offsetWidth');
            if (h != hLast || w != wLast) {
                do_resize(h, w);  
            }
        });
        
        /* The following is how the above should be done, if Chrome weren't buggy
        var observer = new MutationObserver(function(mutations) {
            var h = wrapper_node.get('offsetHeight'),
                w = wrapper_node.get('offsetWidth');
            do_resize(h, w);   
        });
        observer.observe(wrapper_node.getDOMNode(),
            { attributes: true, childList: false, characterData: false });
        */
       
        return editor;
    }
    
    // The main body of the init_ace function
    // ======================================
    
    // Load the required ace modules
    if (! M.qtype_coderunner.modelist) {
        M.qtype_coderunner.modelist = ace.require('ace/ext/modelist');
        ace.require("ace/ext/language_tools");
    }
    
    // Keep track of all active editors on this page (in module global)
    if (! M.qtype_coderunner.active_editors) {
        M.qtype_coderunner.active_editors = {};
    }

    textarea = Y.one('[id="' + field + '"]');
    if (textarea) {
        mode =  find_mode(lang);   
        if (M.qtype_coderunner.active_editors[field]) {
            if (mode) {  // If we already have an editor set up, reload code, change mode
                session = M.qtype_coderunner.active_editors[field].getSession();
                session.setValue(textarea.get('value'));
                session.setMode(mode.mode);
            }
        } else {  // Otherwise create a new editor
            M.qtype_coderunner.active_editors[field] = create_editor_element(textarea, mode);
        }
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


// Initialise a given "Show differences" button, which toggles the visibility
// of <del> elements in the result table. showValue and hideValue are the
// labels to display on the button.
M.qtype_coderunner.initDiffButton = function(Y, buttonId, showValue, hideValue) {
    var diffButton = Y.one('[id="' + buttonId + '"]');
    diffButton.on("click", function(e) {
        var showing = true;
        if (diffButton.get('value') === showValue) {
            diffButton.set('value', hideValue);
        } else {
            diffButton.set('value', showValue);
            showing = false;
        }
        Y.all(".coderunner-test-results del").each(function (divEl) {
            if (showing) {
                divEl.setStyle('background-color', '#E0E000');
            } else {
                divEl.setStyle('background-color', 'inherit');
            }
        });
    });
    
};


// Set up the JavaScript to handle a given text area (as a YUI node)
// By default I just do rudimentary autoindent on return and replace tabs with
// 4 spaces always.
// For info on key handling browser inconsistencies see http://unixpapa.com/js/key.html
// If Ace is enabled, it will take over the functionality of this text area.
M.qtype_coderunner.initTextArea = function (Y, yta) {
    var i = 0,
        ENTER = 13,
        TAB = 9,
        SPACE = 32;

    yta.on('keydown', function(e) {
        if (window.hasOwnProperty('behattesting') && window.behattesting) { return; }  // Don't autoindent when behat testing in progress
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
    var typeCombo = Y.one('#id_coderunnertype'),
        template = Y.one('#id_pertesttemplate'),
        enablecombinator = Y.one('#id_enablecombinator'),
        useace = Y.one('#id_useace'),
        combinatortemplate = Y.one('#id_combinatortemplate'),
        testsplitter = Y.one('#id_testsplitterre'),
        language = Y.one('#id_language'),
        templateBlock = Y.one('#fitem_id_pertesttemplate'),
        gradingBlock = Y.one('#fgroup_id_gradingcontrols'),
        columnDisplayBlock = Y.one('#fgroup_id_columncontrols'),
        sandboxBlock = Y.one('#fgroup_id_sandboxcontrols'),
        customise = Y.one('#id_customise'),
        cputime = Y.one('#id_cputimelimitsecs'),
        memlimit = Y.one('#id_memlimitmb'),
        sandbox = Y.one('#id_sandbox'),
        sandboxparams = Y.one('#id_sandboxparams'),
        customisationFieldSet = Y.one('#id_customisationheader'),
        advancedCustomisation = Y.one('#id_advancedcustomisationheader'),
        isCustomised = customise.get('checked'),
        prototypeType = Y.one("#id_prototypetype"),
        typeName = Y.one('#id_typename'),
        courseId = Y.one('input[name="courseid"]').get('value'),
        message = '',
        questiontypeHelpDiv = Y.one('#qtype-help'),
        alertIssued = false;

    function setCustomisationVisibility(isVisible) {
        var display = isVisible ? 'block' : 'none',
            lang = language.get('value').toLowerCase();
        customisationFieldSet.setStyle('display', display);
        advancedCustomisation.setStyle('display', display);
        if (isVisible && useace.get('checked')) {
            M.qtype_coderunner.init_ace(Y, 'id_pertesttemplate', lang);
            M.qtype_coderunner.init_ace(Y, 'id_combinatortemplate', lang);
        }
    }
    
    function detailsHtml(title, html) {
        // Local function to return the HTML to display in the
        // Question type details section of the form
        return '<p class="question-type-details-header">CodeRunner question type: ' + title + '</p>\n' + html;
    }

    function loadDefaultCustomisationFields(Y) {
        // Local function to load the various customisation fields on the
        // form with their default values for the current Coderunner question
        // type and course.
        var newType = typeCombo.get('value'),
            secs = '',
            mb = '',
            sb = '',
            splitter = '',
            sb_param_val = '';

        if (newType != '' && newType != 'Undefined') {
            Y.io(M.cfg.wwwroot + '/question/type/coderunner/ajax.php', {
                method: 'GET',
                data: 'qtype=' + newType + '&courseid=' + courseId + '&sesskey=' + M.cfg.sesskey,
                on: {
                    success: function (id, result) {
                        var outcome = JSON.parse(result.responseText);
                        if (outcome.success) {
                            template.set('value', outcome.pertesttemplate);
                            secs = outcome.cputimelimitsecs ? outcome.cputimelimitsecs : '';
                            cputime.set('value', secs);
                            mb = outcome.memlimitmb ? outcome.memlimitmb : '';
                            memlimit.set('value', mb);
                            sb = outcome.sandbox ? outcome.sandbox : 'DEFAULT';
                            sandbox.set('value', sb);
                            sb_param_val = outcome.sandboxparams ? outcome.sandboxparams : '';
                            sandboxparams.set('value', sb_param_val);
                            combinatortemplate.set('value', outcome.combinatortemplate);
                            enablecombinator.set('checked', outcome.enablecombinator == "1");
                            splitter = outcome.testsplitterre ? outcome.testsplitterre.replace('\n','\\n'): '';
                            testsplitter.set('value', splitter);
                            language.set('value', outcome.language);
                            typeName.set('value', newType);
                            customise.set('checked', false);
                            questiontypeHelpDiv.setHTML(
                                    detailsHtml(newType, outcome.questiontext));
                            setCustomisationVisibility(false);
                        }
                        else {
                            alert("Error loading prototype: " + outcome.error);
                            $error = "*** PROTOTYPE LOAD FAILURE. DON'T SAVE THIS! ***\n" + outcome.error + 
                                    "\nCourseId: " + courseId + ", qtype: " + newType;
                            template.set('value', $error);
                        }

                    },
                    failure: function (id, result) {
                        alert("Error loading prototype. Network problems or server down, perhaps?");
                        template.set('value', "*** AJAX ERROR. DON'T SAVE THIS! ***");
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
    } else {
        questiontypeHelpDiv.setHTML("<p>Question type information is not available for customised questions.</p>");
    }

    customise.on('change', function(e) {
       isCustomised = customise.get('checked');
       if (isCustomised) {
           // Customisation is being turned on.
           setCustomisationVisibility(true);
       } else { // Customisation being turned off
           message = "If you save this question with 'Customise' " +
               "unchecked, any customisation you've done will be lost.";
           if (confirm(message + " Proceed?")) {
                 setCustomisationVisibility(false);
           } else {
               customise.set('checked',true);
           }
       }
    });

    template.on('change', function(e) {
           // Per-test template has been changed. Check if combinator should
           // be disabled.
           var combinatornonblank = combinatortemplate.get('value').trim() !== '';
           if (combinatornonblank
                   && !alertIssued
                   && enablecombinator.get('checked')
                   && confirm("Per-test template changed - disable combinator? ['Cancel' leaves it enabled.]")
              ) {
               enablecombinator.set('checked', false);
           }
           alertIssued = true;
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
