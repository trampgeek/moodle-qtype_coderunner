// JavaScript functions for CodeRunner

M.qtype_coderunner = {};

// Script for the usual student-view question render
// Having given up on syntax colouring editors in the YUI context, I
// now just do rudimentary autoindent on return and replace tab with
// 4 spaces always.
// For info on key handling browser inconsistencies see http://unixpapa.com/js/key.html
M.qtype_coderunner.initQuestionRender = function(Y, textareaId, lang) {
    var yta = Y.one(Y.DOM.byId(textareaId)), // Work around problem of colons in Moodle IDs
        ta = yta.getDOMNode(),
        i = 0,
        ENTER = 13,
        TAB = 9,
        SPACE = 32;
    yta.on('keydown', function(e) {
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
        template = Y.one('#id_custom_template'),
        templateBlock = Y.one('#fitem_id_custom_template'),
        customise = Y.one('#id_customise');

    function loadTemplate(Y) {
        // Local function to load the template field by AJAX from the
        // current coderunner type.
        var newType = typeCombo.get('value'),
            newTemplate = '';

        if (newType != '' && newType != 'Undefined') {
            Y.io(M.cfg.wwwroot + '/question/type/coderunner/ajax.php', {
                method: 'GET',
                data: 'qtype=' + newType + '&sesskey=' + M.cfg.sesskey,
                on: {
                    success: function (id, result) {
                        outcome = JSON.parse(result.responseText);
                        if (outcome.success) {
                            newTemplate = outcome.per_test_template;
                        }
                        else {
                            newTemplate = "*** AJAX ERROR. DON'T SAVE THIS! ***\n" + outcome.error;
                        }
                        template.set('text', newTemplate);
                    },
                    failure: function (id, result) {
                        template.set('text', "*** AJAX ERROR. DON'T SAVE THIS! ***");
                    }
                }
            });
        };
    };

    if (customise.get('checked')) {
        templateBlock.setStyle('display', 'block');
    } else {
        loadTemplate(Y);
    }

    customise.on('change', function(e) {
        templateBlock.setStyle('display', customise.get('checked') ? 'block' : 'none');
    });


    typeCombo.on('change', function(e) {
        if (!customise.get('checked') ||
               confirm("Changing question type. Click OK to load new template, Cancel to retain your customised one.")) {
            loadTemplate(Y);
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







