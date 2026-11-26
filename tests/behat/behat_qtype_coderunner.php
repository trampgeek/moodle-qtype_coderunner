<?php
// This file is part of CodeRunner - http://coderunner.org.nz/
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat step definitions and page resolution for CodeRunner.
 *
 * Everything a feature needs lives here, including the steps that drive the
 * prototype manager's own pages: a feature is a robotic teacher, who neither
 * knows nor cares which plugin rendered the button being pressed. The class is
 * named for the component so that Moodle can find its page resolvers.
 *
 * @package    qtype_coderunner
 * @copyright  2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Facebook\WebDriver\Exception\NoSuchAlertException;
use Moodle\BehatExtension\Exception\SkippedException;
use qtype_coderunner\v6\field_registry;

class behat_qtype_coderunner extends behat_base {
    /**
     * Loads the default coderunner settings file for testing.
     * It seems silly that I have to do that. Why is there not
     * a global behat configuration option apply to all features?
     * @Given /^the CodeRunner test configuration file is loaded/
     */
    public function the_coderunner_test_configuration_file_is_loaded() {
        global $CFG;
        require($CFG->dirroot . '/question/type/coderunner/tests/fixtures/test-sandbox-config.php');
    }

    /**
     * Enables the CodeRunner webservice for testing purposes.
     *
     * @Given /^the CodeRunner webservice is enabled/
     */
    public function the_coderunner_webservice_is_enabled() {
        set_config('wsenabled', 1, 'qtype_coderunner');
    }


    /**
     * Sets the webserver webservice to disabled for testing purposes.
     *
     * @Given /^the CodeRunner webservice is disabled/
     */
    public function the_coderunner_webservice_is_disabled() {
        set_config('wsenabled', 0, 'qtype_coderunner');
    }


    /**
     * Disables the Jobe sandbox. Currently unused/untested.
     *
     * @Given /^the Jobe sandbox is disabled/
     */
    public function the_jobe_sandbox_is_disabled() {
        set_config('jobesandbox_enabled', 0, 'qtype_coderunner');
    }

    /**
     * Checks that a given string appears within answer textarea.
     * Intended for checking UI serialization
     * @Then /^I should see in answer field "(?P<expected>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $expected The string that we expect to find
     */
    public function i_should_see_in_answer($expected) {
        $xpath = '//textarea[contains(@class, "coderunner-answer")]';
        $driver = $this->getSession()->getDriver();
        if (!$driver->find($xpath)) {
            $error = "Answer box not found!";
            throw new ExpectationException($error, $this->getSession());
        }
        $page = $this->getSession()->getPage();
        $val = $page->find('xpath', $xpath)->getValue();
        if ($val !== $expected) {
            $error = "'$val' does not match '$expected'";
            throw new ExpectationException($error, $this->getSession());
        }
    }

     /**
      * Sets answer textarea (seen after presing ctrl+m) to a value
      * @Then /^I set answer field to "(?P<value>(?:[^"]|\\")*)"$/
      * @throws ExpectationException
      * @param string $expected The string that we expect to find
      */
    public function i_set_answer($value) {
        $xpath = '//textarea[contains(@class, "coderunner-answer")]';
        $driver = $this->getSession()->getDriver();
        if (!$driver->find($xpath)) {
            $error = "Answer box not found!";
            throw new ExpectationException($error, $this->getSession());
        }
        $page = $this->getSession()->getPage();
        $val = $page->find('xpath', $xpath)->setValue($value);
    }

    /**
     * Sets answer textarea (seen after presing ctrl+m) to a value
     * @Then /^I set answer field to:$/
     * @throws ExpectationException
     * @param string $expected The string that we expect to find
     */
    public function i_set_answer_pystring($pystring) {
        $this->i_set_answer($pystring->getRaw());
    }

     /**
      * Checks that a given string appears within answer textarea.
      * Intended for checking UI serialization
      * @Then /^I should see in answer field:$/
      */
    public function i_should_see_in_answer_pystring(Behat\Gherkin\Node\PyStringNode $pystring) {
        $this->i_should_see_in_answer($pystring->getRaw());
    }

    /**
     * Sets the ace editor content to provided string, using name of associated textarea.
     * NOTES:
     *  - Also works on partial matches, eg, value as _answer will work for Ace UI
     *  - But you will just get the first match so need to be using on pages with one question...
     *  - The field to look for is different between plain Ace and Scratchpad
     *     - In plain Ace look for a field containing "_answer" in the name
     *     - In Scratchpad look for
     *          - a field containing "answer_code" in the name
     *          - or a field containing "test_code" in the name
     *  - This assumes the existence of a text area next to a
     *    UI wrapper div containing the Ace div!

     *
     * Intended as a replacement for I set field to <value>, for ace fields.
     * @Then /^I set the ace field "(?P<elname>(?:[^"]|\\")*)" to "(?P<value>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $expected The string that we expect to find
     */
    public function i_set_ace_field($elname, $value) {
        $xpath = "//textarea[@name='$elname' or (contains(@name, '$elname') " .
             "and contains(@class, 'edit_code'))]/following-sibling::div[1]/div";
        $this->wait_for_ace_editor($xpath, $elname);

        // Behat's own syntax for this step cannot carry a bare double quote --
        // the pattern is (?:[^"]|\\")* -- so a feature has to write \" for one,
        // and that one escape is undone here. Everything else has to reach the
        // editor verbatim.
        $value = str_replace('\\"', '"', $value);

        // We inject JS into the browser to set the Ace editor contents...
        // (Gross) JS to take the x-path for the div managed by Ace,
        // open editor for that div, and set the editors value.
        //
        // Both strings are JSON-encoded rather than wrapped in backticks. A
        // template literal is not a safe quoting of arbitrary text: it reads
        // \n as a newline, and a backtick or ${ in the value would rewrite the
        // script. That cost one scenario -- the wrapper
        // exec("{| ANSWER_CODE |}\n{| SCRATCHPAD_CODE |}") reached Python with a
        // real newline inside a one-line string, and died as an unterminated
        // string literal rather than as anything to do with quoting.
        $javascript = "const editorNode = document.evaluate("
               . json_encode($xpath) . ","
               . "document,"
               . "null,"
               . "XPathResult.ANY_TYPE,null,"
               . ");"
               . "const editor = ace.edit(editorNode.iterateNext());"
               . "editor.setValue(" . json_encode($value) . ");";
        $this->getSession()->executeScript($javascript);
    }

    /**
     * Sets the ace editor content to a provided multiline string, using name of associated textarea.
     * Should probably change format to I set... to multiline:$ to match base Moodle equivalent.
     *    But this is big job :/
     * NOTES:
     *  - Also works on partial matches, eg, value as _answer will work for Ace UI
     *  - But you will just get the first match so need to be using on pages with one question...
     *  - The field to look for is different between plain Ace and Scratchpad
     *     - In plain Ace look for a field containing "_answer" in the name
     *     - In Scratchpad look for
     *          - a field containing "answer_code" in the name
     *          - or a field containing "test_code" in the name
     *  - This assumes the existence of a text area next to a
     *    UI wrapper div containing the Ace div!
     * Intended as a replacement for I set field to <value>, for ace fields.
     * @Then /^I set the ace field "(?P<elname>(?:[^"]|\\")*)" to:$/
     * @throws ExpectationException
     * @param string $expected The string that we expect to find
     */
    public function i_set_ace_field_pystring($elname, $pystring) {
        $this->i_set_ace_field($elname, $pystring->getRaw());
    }

    /**
     * Checks that an Ace-managed field contains the given text.
     *
     * The counterpart of *I set the ace field*, and the reason one is needed:
     * Ace hides the textarea it manages and renders the text in its own divs,
     * so core's *I should see "..." in the "id_template" "field"* finds the
     * text in the DOM and then rejects it as not visible. The value is read
     * from the editor rather than from the textarea, because Ace writes back
     * to the textarea only on change or submit, so a field the author has not
     * touched still holds whatever the page was rendered with.
     *
     * Substring, not equality, matching what the features ask of it.
     *
     * @Then /^I should see "(?P<expected>(?:[^"]|\\")*)" in the ace field "(?P<elname>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $expected the text the field should contain.
     * @param string $elname the name of the textarea Ace manages.
     */
    public function i_should_see_in_ace_field($expected, $elname) {
        $xpath = "//textarea[@name='$elname' or (contains(@name, '$elname') " .
             "and contains(@class, 'edit_code'))]/following-sibling::div[1]/div";
        $this->wait_for_ace_editor($xpath, $elname);
        $javascript = "const editorNode = document.evaluate("
               . "`$xpath`,"
               . "document,"
               . "null,"
               . "XPathResult.ANY_TYPE,null,"
               . ");"
               . "return ace.edit(editorNode.iterateNext()).getValue();";
        $value = $this->getSession()->evaluateScript("(function() { $javascript })()");

        if (!str_contains((string) $value, $expected)) {
            throw new ExpectationException(
                "The ace field '{$elname}' does not contain '{$expected}'. It holds: '{$value}'",
                $this->getSession()
            );
        }
    }

    /**
     * Click at the centre of the given element via a synthetic mouse event,
     * dispatched to whichever element is actually topmost there.
     *
     * Needed for clicking into an Ace gapfiller gap: its marker divs are
     * valid click targets as far as Mink/WebDriver locators are concerned,
     * but Ace's own text layer sits visually above them, so a plain "I click
     * on ... css_element" is rejected by the browser with
     * ElementClickInterceptedException even though the point is really
     * clickable (Ace itself handles the click via that text layer, computing
     * the row/column from where the click actually landed).
     *
     * @When /^I click at the centre of "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)"$/
     * @param string $element Element we look for
     * @param string $selectortype The type of what we look for
     */
    public function i_click_at_the_centre_of($element, $selectortype) {
        $node = $this->get_selected_node($selectortype, $element);
        $xpath = $node->getXpath();
        $javascript = "const el = document.evaluate("
            . "`$xpath`,"
            . "document,"
            . "null,"
            . "XPathResult.ANY_TYPE,null,"
            . ").iterateNext();"
            . "const rect = el.getBoundingClientRect();"
            . "const x = rect.left + rect.width / 2;"
            . "const y = rect.top + rect.height / 2;"
            . "const target = document.elementFromPoint(x, y) || el;"
            . "for (const type of ['mousedown', 'mouseup', 'click']) {"
            . "    target.dispatchEvent(new MouseEvent(type, "
            . "        {bubbles: true, cancelable: true, view: window, clientX: x, clientY: y, button: 0}));"
            . "}";
        $this->getSession()->executeScript($javascript);
    }


    /**
     * Checks that a given string appears within a visible ins or del element
     * that has a background-color attribute that is not 'inherit'.
     * Intended for use only when checking the behaviour of the
     * 'Show differences' button.
     *
     * @Then /^I should see highlighted "(?P<expected>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $expected The string that we expect to find
     */
    public function i_should_see_highlighted($expected) {
        $delxpath = "//div[contains(@class, 'coderunner-test-results')]//del[contains(text(), '{$expected}')]";
        $msg = "'{$expected}' not found within a highlighted del element";
        $driver = $this->getSession()->getDriver();
        if (! $driver->find($delxpath)) {
            throw new ExpectationException($msg, $this->getSession());
        }
    }

    /**
     * Checks that a given string does not appear within a visible ins or del element
     * that has a background-color attribute that is not 'inherit'.
     * Intended for use only when checking the behaviour of the
     * 'Show differences' button.
     *
     * @Then /^I should not see highlighted "(?P<nonexpected_string>(?:[^"]|\\")*)"$/
     */
    public function i_should_not_see_highlighted($nonexpected) {
        try {
            $this->i_should_see_highlighted($nonexpected);
        } catch (ExpectationException $ex) {
            return;
        }
        $msg = "'{$nonexpected}' found within a highlighted del or ins element";
        throw new ExpectationException($msg, $this->getSession());
    }

    /**
     * Step to set a global variable 'behattesting' to true to prevent
     * textarea autoindent, which messes up behat's setting of the textarea
     * value. [See module.js]
     * Also used to prevent alerts, since Chrome doesn't currently seem
     * to process "And I dismiss alert" correctly.
     *
     * @When /^I set CodeRunner behat testing flag/
     */
    public function i_set_behat_testing() {
        $javascript = "window.behattesting = true;";
        $this->getSession()->executeScript($javascript);
    }



    /**
     * Sets the contents of a field with multi-line input.
     *
     * @Given /^I set the field "(?P<field_string>(?:[^"]|\\")*)" to:$/
     *
     * From https://moodle.org/mod/forum/discuss.php?d=283216
     */
    public function i_set_the_field_to_pystring($fieldlocator, Behat\Gherkin\Node\PyStringNode $value) {
        $this->execute('behat_forms::i_set_the_field_to', [$fieldlocator, $this->escape($value)]);
    }


    /**
     * Sets the specified value to the field with xpath.
     * This doesn't seem to be in the base Moodle behat code as of v5.0
     * Need this as the above  i_set_the_field_to_pystring definition only
     * matches the field name exactly.
     *
     * @Given /^I set the field with xpath "(?P<fieldxpath_string>(?:[^"]|\\")*)" to multiline:$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $fieldxpath
     * @param string $value
     * @return void
     */
    public function i_set_the_field_with_xpath_to_pystring($fieldxpath, Behat\Gherkin\Node\PyStringNode $value) {
        #$value = (string)$value;
        $rawvalue = $value->getRaw();
        $this->execute('behat_forms::i_set_the_field_with_xpath_to', [$fieldxpath, $rawvalue]);
    }


    /**
     * @Then /^I should see a canvas/
     */
    public function i_see_a_canvas() {
        $xpath = "//canvas";
        $driver = $this->getSession()->getDriver();
        if (! $driver->find($xpath)) {
            throw new ExpectationException("Couldn't find canvas", $this->getSession());
        }
    }

    /**
     * @Then /^I should not see a canvas/
     */
    public function i_should_not_see_a_canvas() {
        $xpath = "//canvas";
        $driver = $this->getSession()->getDriver();
        if ($driver->find($xpath)) {
            throw new ExpectationException("Found a canvas", $this->getSession());
        }
    }

     /**
      * @Given /^I fill in my template/
      */
    public function i_fill_in_my_template() {
        $dfatemplate = file_get_contents("dfa_template.txt", FILE_USE_INCLUDE_PATH);
        $this->getSession()->getPage()->fillField('id_template', $dfatemplate);
    }


    /**
     * Checks that the page text matches the given PCRE regular expression
     * (including its delimiters, e.g. "/foo.*bar/"). Useful where the exact
     * wording of a message can legitimately vary, e.g. compiler error text
     * that differs between toolchain versions, and a literal "I should see"
     * substring match would be too brittle.
     *
     * @Then /^I should see text matching "(?P<pattern>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $pattern A PCRE regular expression, including delimiters
     */
    public function i_should_see_text_matching($pattern) {
        $this->assertSession()->pageTextMatches($pattern);
    }

    /**
     * Checks that the page text does not match the given PCRE regular
     * expression (including its delimiters, e.g. "/foo.*bar/").
     *
     * @Then /^I should not see text matching "(?P<pattern>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $pattern A PCRE regular expression, including delimiters
     */
    public function i_should_not_see_text_matching($pattern) {
        $this->assertSession()->pageTextNotMatches($pattern);
    }

    /**
     * Skips scenario if the given language is not installed on the Jobe server
     *
     * @Given /^the Jobe server supports "(?P<lang>[^"]+)"$/
     */
    public function jobe_supports_language(string $lang): void {
        if (qtype_coderunner_sandbox::get_best_sandbox($lang, true) === null) {
            $msg = "$lang is not installed on your server. Scenario skipped.";
            throw new SkippedException($msg);
        }
    }

    /**
     * Presses a named button. Checks if there is a specified error text displayed.
     *
     * @Then I should see the alert :error when I press :button
     * @param string $errortext The expected error message when alerted
     * @param string $button The name of the alert button.
     */
    public function there_is_an_alert_when_i_click($errortext, $button) {
        // Gets the item of the button.
        $xpath = "//button[@type='button' and contains(text(), '$button')]";
        $session = $this->getSession();
        $item = $session->getSelectorsHandler()->selectorToXpath('xpath', $xpath);
        $element = $session->getPage()->find('xpath', $item);

        // Makes sure there is an element before continuing.
        if ($element) {
            $element->click();
        } else {
            throw new ExpectationException("No button '{$button}'", $this->getSession());
        }
        try {
            // Gets you to wait for the pending JS alert by sleeping.
            sleep(1);
            // Gets the alert and its text.
            $alert = $this->getSession()->getDriver()->getWebDriver()->switchTo()->alert();
            $alerttext = $alert->getText();
        } catch (NoSuchAlertException $ex) {
            throw new ExpectationException("No alert was triggered appropriately", $this->getSession());
        }

        // Throws an error if expected error text doesn't match alert.
        if (!str_contains($alerttext, $errortext)) {
            throw new ExpectationException("Wrong alert; alert given: {$alerttext}", $this->getSession());
        } else {
            // To stop the Behat tests from throwing their own errors.
            $alert->accept();
        }
    }

    /**
     * Clears the *Inherit* box on one or more CodeRunner settings, so that the
     * fields can then be given values.
     *
     * CodeRunner 6 has no *Customise* checkbox. Every setting is visible, and
     * each inheritable one carries its own *Inherit* checkbox
     * (`inherit_<field>`); the field's own control is rendered readonly while
     * that box is ticked, and `inheritedcode.js` unlocks it when the box is
     * cleared. So a scenario that means to supply a value has to clear the box
     * first, and must be tagged @javascript for the unlocking to happen.
     *
     * Takes a list so that a scenario overriding several settings needs one
     * line rather than one line per field.
     *
     * @Given /^I override the CodeRunner fields? "(?P<fields_string>(?:[^"]|\\")*)"$/
     * @param string $fields comma-separated field names, e.g. "template, iscombinatortemplate".
     */
    public function i_override_the_coderunner_fields($fields) {
        // CodeRunner 6's settings sections are rendered collapsed, and a
        // checkbox inside a collapsed fieldset is not interactable. The table
        // form of "I set the following fields" expands them itself, but the
        // singular form does not and neither does this step, so it has to.
        $this->execute('behat_forms::i_expand_all_fieldsets');

        foreach (explode(',', $fields) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $node = $this->find('field', 'id_inherit_' . $name);
            $field = behat_field_manager::get_form_field($node, $this->getSession());
            $field->set_value('0');
        }
    }

    /**
     * Chooses a prototype in the question type picker, by the name the menu
     * shows, and waits for the form it rebuilds.
     *
     * The picker is a `selectgroups` whose option labels are indented with
     * non-breaking spaces to show the prototype tree, so an exact match on the
     * option text fails; the indent is stripped before comparing.
     *
     * Changing it makes `prototypereload.js` press a hidden no-submit button,
     * which rebuilds the whole form from the new prototype. So this step cannot
     * be folded into a form-filling table the way `id_coderunnertype` could:
     * anything set before it is discarded by the reload, which is why it has to
     * come first.
     *
     * @Given /^I choose the CodeRunner prototype "(?P<name_string>(?:[^"]|\\")*)"$/
     * @param string $name the prototype's name, as the menu shows it.
     */
    public function i_choose_the_coderunner_prototype($name) {
        $this->execute('behat_forms::i_expand_all_fieldsets');

        $select = $this->find('field', 'id_' . \qtype_coderunner\v6\prototype_picker::FIELD);
        $wanted = null;
        foreach ($select->findAll('xpath', './/option') as $option) {
            // The indent is a run of non-breaking spaces, four per tree level.
            $text = trim(str_replace("\xc2\xa0", '', $option->getText()));
            if ($text === $name) {
                $wanted = $option->getAttribute('value');
                break;
            }
        }
        if ($wanted === null) {
            throw new ExpectationException(
                "No prototype named '{$name}' is offered by the picker",
                $this->getSession()
            );
        }

        $this->select_and_await_rebuild($select, $wanted, "the prototype '{$name}'");
    }

    /**
     * Chooses a prototype's parent in the prototype editor, and waits for the
     * form it rebuilds.
     *
     * *Inherits from* is the same pair of menus as the question form's
     * question type, so it cannot be set from a form-filling table: the label
     * belongs to the row and the menus carry none, and a change of parent
     * rebuilds the whole form from the new chain, discarding anything typed
     * into a field that the new parent does not offer.
     *
     * A parent in another course needs *I choose the parent prototype source*
     * first, for the same reason the question form does.
     *
     * @Given /^I choose the parent prototype "(?P<name_string>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $name the prototype's name, as the menu shows it.
     */
    public function i_choose_the_parent_prototype($name) {
        $this->execute('behat_forms::i_expand_all_fieldsets');

        $select = $this->find('field', 'id_' . \local_coderunner_manager\form\prototype_form::PARENT_FIELD);
        $wanted = null;
        foreach ($select->findAll('xpath', './/option') as $option) {
            // The indent is a run of non-breaking spaces, four per tree level.
            $text = trim(str_replace("\xc2\xa0", '', $option->getText()));
            if ($text === $name) {
                $wanted = $option->getAttribute('value');
                break;
            }
        }
        if ($wanted === null) {
            throw new ExpectationException(
                "No prototype named '{$name}' is offered as a parent",
                $this->getSession()
            );
        }

        $this->select_and_await_rebuild($select, $wanted, "the parent '{$name}'");
    }

    /**
     * Chooses which course's library the parent menu offers, and waits for the
     * form it rebuilds.
     *
     * The editor's half of *I choose the CodeRunner prototype source*: a
     * prototype may inherit from another course's prototype exactly as a
     * question may be built on one. The menu is drawn only for an author who
     * has somewhere to borrow from, and the course the prototype belongs to is
     * named *This course* rather than by its shortname.
     *
     * @Given /^I choose the parent prototype source "(?P<name_string>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $name the course's short name, as the menu shows it.
     */
    public function i_choose_the_parent_prototype_source($name) {
        $this->execute('behat_forms::i_expand_all_fieldsets');

        $select = $this->find('field', 'id_' . \local_coderunner_manager\form\prototype_form::PARENT_COURSE_FIELD);
        $wanted = null;
        foreach ($select->findAll('xpath', './/option') as $option) {
            if (trim($option->getText()) === $name) {
                $wanted = $option->getAttribute('value');
                break;
            }
        }
        if ($wanted === null) {
            throw new ExpectationException(
                "No course named '{$name}' is offered as a parent source",
                $this->getSession()
            );
        }

        $this->select_and_await_rebuild($select, $wanted, "the parent source '{$name}'");
    }

    /**
     * Chooses which course's library the prototype menu offers, and waits for
     * the form it rebuilds.
     *
     * The picker is two menus: this one says whose prototypes the other holds.
     * It is drawn only for an author who has somewhere to borrow from, so a
     * feature that never leaves its own course never needs this step — and one
     * that does needs it before *I choose the CodeRunner prototype*, because
     * until the scope has changed the prototype it wants is not in the menu.
     *
     * The question's own course is named *This course* rather than by its
     * shortname, as the menu names it.
     *
     * @Given /^I choose the CodeRunner prototype source "(?P<name_string>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $name the course's short name, as the menu shows it.
     */
    public function i_choose_the_coderunner_prototype_source($name) {
        $this->execute('behat_forms::i_expand_all_fieldsets');

        $select = $this->find('field', 'id_' . \qtype_coderunner\v6\prototype_picker::COURSE_FIELD);
        $wanted = null;
        foreach ($select->findAll('xpath', './/option') as $option) {
            if (trim($option->getText()) === $name) {
                $wanted = $option->getAttribute('value');
                break;
            }
        }
        if ($wanted === null) {
            throw new ExpectationException(
                "No course named '{$name}' is offered as a prototype source",
                $this->getSession()
            );
        }

        $this->select_and_await_rebuild($select, $wanted, "the prototype source '{$name}'");
    }

    /**
     * Chooses a value in the UI plugin menu and waits for the form it rebuilds.
     *
     * The *Student answer* menu reloads the form through a hidden no-submit
     * button, exactly as the prototype picker does, because which UI the
     * answer box runs is the server's decision. So a plain
     * `I set the field "id_uiplugin" to ...` races the rebuild and the next
     * step acts on a page being torn down.
     *
     * The wanted UI is matched on the option's label or its value, so a
     * feature may say either *Scratchpad* or `scratchpad`.
     *
     * @Given /^I choose the CodeRunner UI "(?P<name_string>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $name the UI's label or value.
     */
    public function i_choose_the_coderunner_ui($name) {
        $this->execute('behat_forms::i_expand_all_fieldsets');

        $select = $this->find('field', 'id_' . \qtype_coderunner\v6\settings_form::UIPLUGIN_FIELD);
        $wanted = null;
        foreach ($select->findAll('xpath', './/option') as $option) {
            $value = $option->getAttribute('value');
            if (trim($option->getText()) === $name || $value === $name) {
                $wanted = $value;
                break;
            }
        }
        if ($wanted === null) {
            throw new ExpectationException(
                "No UI named '{$name}' is offered by the Student answer menu",
                $this->getSession()
            );
        }

        $this->select_and_await_rebuild($select, $wanted, "the UI '{$name}'");
    }

    /**
     * Selects a value in a menu that rebuilds the form, and waits for it.
     *
     * Shared by the two menus that do this, so the waiting is written once.
     *
     * The page is marked before the change, so that the rebuild can be told
     * from the page it replaces. Mink's NodeElement holds an xpath and
     * re-resolves it on every call, so there is no stale reference to catch;
     * an attribute on the document element is destroyed by a real page load
     * and by nothing else. `wait_for_pending_js()` alone can return while the
     * rebuild is in flight, which surfaces as "element not interactable" in
     * whichever step runs next rather than as anything to do with the menu.
     *
     * @param \Behat\Mink\Element\NodeElement $select the menu.
     * @param string $wanted the option value to choose.
     * @param string $what what was chosen, for the error message.
     */
    protected function select_and_await_rebuild($select, string $wanted, string $what): void {
        // Nothing to wait for if it is already the choice: prototypereload.js
        // ignores a change to the value already loaded, so no reload happens.
        if ($select->getValue() === $wanted) {
            return;
        }

        $this->getSession()->executeScript(
            "document.documentElement.setAttribute('data-behat-awaiting-reload', '1');"
        );

        $select->selectOption($wanted);

        $this->spin(
            function($context) {
                return $context->getSession()->evaluateScript(
                    "document.documentElement.getAttribute('data-behat-awaiting-reload') === null"
                );
            },
            false,
            self::get_extended_timeout(),
            new ExpectationException(
                "The form was not rebuilt after choosing {$what}",
                $this->getSession()
            )
        );
        $this->wait_for_pending_js();
    }

    /**
     * The fields that carry an *Inherit* checkbox.
     *
     * Not every inheritable field has one: a collected field (template
     * parameters, UI parameters, support files) merges a layer's contribution
     * with its parent's instead of replacing it, so {@see settings_form} adds
     * it through a different path with no checkbox to clear. The predicate here
     * is the same one that form branches on.
     *
     * @return string[] field names.
     */
    protected function overridable_fields(): array {
        $names = [];
        foreach (\qtype_coderunner\v6\field_registry::inheritable() as $name => $field) {
            if ($field->resolves_to_one_value()) {
                $names[] = $name;
            }
        }
        return $names;
    }

    /**
     * Adds a CodeRunner question built on a named prototype, filling the rest
     * of the form from a table.
     *
     * Replaces `I add a "CodeRunner" question filling the form with:` and its
     * `id_coderunnertype` row, which CodeRunner 6 cannot express as one table.
     * Choosing a prototype rebuilds the form and discards anything already
     * filled in, so it has to happen first; and an inheritable field the table
     * sets has to have its *Inherit* box cleared or its control is readonly.
     * Both are done here, so a scenario still needs only one step.
     *
     * @Given /^I add a CodeRunner question on "(?P<prototype_string>(?:[^"]|\\")*)" filling the form with:$/
     * @param string $prototype the prototype's name, as the picker shows it.
     * @param \Behat\Gherkin\Node\TableNode $data the remaining fields.
     */
    public function i_add_a_coderunner_question_on_filling_the_form_with(
        $prototype,
        \Behat\Gherkin\Node\TableNode $data
    ) {
        // Open the chooser, then the CodeRunner form, as
        // behat_core_question::i_add_a_question_filling_the_form_with and
        // behat_question_base::finish_adding_question do between them. The
        // press was missing at first, so the step ran against the question
        // list and found no form at all.
        $this->execute('behat_forms::press_button', get_string('createnewquestion', 'question'));
        $this->execute('behat_forms::i_set_the_field_to', [$this->escape('CodeRunner'), 1]);
        $this->execute('behat_general::i_click_on', ['.submitbutton', 'css_element']);

        $this->i_choose_the_coderunner_prototype($prototype);

        $overridable = $this->overridable_fields();
        $wanted = [];
        foreach ($data->getRows() as $row) {
            $name = trim($row[0]);
            if (str_starts_with($name, 'id_')) {
                $name = substr($name, 3);
            }
            if (in_array($name, $overridable, true) && !in_array($name, $wanted, true)) {
                $wanted[] = $name;
            }
        }
        if ($wanted !== []) {
            $this->i_override_the_coderunner_fields(implode(',', $wanted));
        }

        // A code field is managed by Ace, which hides the textarea that core's
        // bulk setter types into, so those rows are set through the editor
        // instead -- the way an author sets them. Only this step's own table is
        // treated this way: core's shared "I set the following fields to these
        // values" is left alone, because teaching it about Ace once hijacked
        // id_questiontext from TinyMCE and took a run from 5 failures to 28.
        //
        // The registry is asked which fields are code rather than a list being
        // kept here, so a new code field needs no change to this step. Being a
        // code field is necessary but not sufficient, though: the seven in the
        // settings sections always get an editor, while "answer" and
        // "answerpreload" get the question's own UI plugin and so get none at
        // all when that is empty or "none" (see
        // edit_coderunner_form::require_answer_box_ui). So the test is whether
        // the textarea is actually hidden, which is the condition that stops
        // core's setter working, rather than a guess about which component
        // attached what. By this point the picker has rebuilt the form and
        // Behat has waited for its JavaScript, so the editors have attached.
        $plainrows = [];
        $codevalues = [];
        foreach ($data->getRows() as $row) {
            $name = trim($row[0]);
            $bare = str_starts_with($name, 'id_') ? substr($name, 3) : $name;
            $iscode = field_registry::exists($bare) && field_registry::get($bare)->iscode;
            if ($iscode && $this->is_hidden_by_ui($bare)) {
                $codevalues[$bare] = $row[1];
            } else {
                $plainrows[] = $row;
            }
        }

        // The plain fields go first: one of them may rebuild the form through a
        // no-submit button, which would discard anything already typed into an
        // editor.
        if ($plainrows !== []) {
            $this->execute(
                'behat_forms::i_set_the_following_fields_to_these_values',
                new TableNode($plainrows)
            );
        }
        foreach ($codevalues as $bare => $value) {
            if ($this->has_ace_editor($bare)) {
                $this->i_set_ace_field($bare, $value);
            } else {
                $this->set_serialised_ui_field($bare, $value);
            }
        }

        $this->execute('behat_forms::press_button', 'id_submitbutton');
    }

    /**
     * Has an Ace editor attached itself to the named field?
     *
     * @param string $name the field's name attribute.
     * @return bool
     */
    protected function has_ace_editor(string $name): bool {
        $xpath = "//textarea[@name='$name']/following-sibling::div[1]"
            . "/div[contains(@class, 'ace_editor')]";
        return (bool) $this->getSession()->getDriver()->find($xpath);
    }

    /**
     * Set a field whose UI plugin is not a text editor, such as the graph UI.
     *
     * There is no typing to imitate here: a graph is drawn with a mouse, and no
     * Behat step can draw one. What the UI plugins do have in common is that
     * they serialise their state into the textarea they manage, and that is the
     * value a feature supplies. So the serialisation is written there and the UI
     * is stopped and restarted, which makes its constructor read the textarea
     * back -- the same path the UI takes when the form first loads. Writing the
     * textarea alone would not survive: a live UI syncs its own state over it
     * when the form is submitted.
     *
     * @param string $name the field's name attribute.
     * @param string $value the UI's serialisation of the wanted state.
     */
    protected function set_serialised_ui_field(string $name, string $value) {
        $js = "(function() {"
            . "const ta = document.querySelector('textarea[name=' + "
            . json_encode(json_encode($name)) . " + ']');"
            . "ta.value = " . json_encode($value) . ";"
            . "const w = ta.current_ui_wrapper;"
            . "if (w) { w.stop(); w.restart(); }"
            . "})();";
        $this->getSession()->executeScript($js);
    }

    /**
     * Is the named field's textarea hidden by a UI plugin that has replaced it?
     *
     * A UI wrapper sets the textarea's display to none once its editor loads,
     * which is exactly what stops core's field steps reaching it. A field with
     * no UI, or one whose UI declined to load, stays visible and is set the
     * ordinary way.
     *
     * @param string $name the field's name attribute, e.g. "template".
     * @return bool true if the field must be set through its editor instead.
     */
    protected function is_hidden_by_ui(string $name): bool {
        $node = $this->getSession()->getDriver()->find("//textarea[@name='$name']");
        if (!$node) {
            // Not on the form at all: leave it to core, whose error message
            // about a missing field is the useful one.
            return false;
        }
        return !$node[0]->isVisible();
    }

    /**
     * Resolves the pages of a named thing, for `I am on the "X" "qtype_coderunner > Y" page`.
     *
     * The prototype library and the prototype editor are served by the
     * local_coderunner_manager plugin, but they are CodeRunner pages to the
     * teacher using them, so they are named and resolved here. Nothing about
     * the framework requires otherwise: the component in the page name is the
     * name a feature types, not a statement about where any code lives.
     *
     * Recognised types:
     *  - "prototype library"  identified by a course shortname
     *  - "prototype"          identified by a prototype name
     *  - "prototype usage"    identified by a prototype name
     *
     * @param string $type the kind of page.
     * @param string $identifier what names the thing the page is about.
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'prototype library':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return new moodle_url('/local/coderunner_manager/index.php', ['courseid' => $courseid]);

            case 'prototype':
                $prototype = $DB->get_record(
                    'local_coderunner_manager_prototypes',
                    ['name' => $identifier],
                    'id, courseid',
                    MUST_EXIST
                );
                return new moodle_url('/local/coderunner_manager/edit.php', [
                    'id' => $prototype->id,
                    'courseid' => $prototype->courseid,
                ]);

            case 'prototype usage':
                // The library links this page from a row's question count, and
                // only when the count is non-zero, so a feature that wants the
                // empty report has no link to follow and needs the URL.
                $prototype = $DB->get_record(
                    'local_coderunner_manager_prototypes',
                    ['name' => $identifier],
                    'id, courseid',
                    MUST_EXIST
                );
                return new moodle_url('/local/coderunner_manager/usage.php', [
                    'id' => $prototype->id,
                    'courseid' => $prototype->courseid,
                ]);

            default:
                throw new Exception(
                    "Unrecognised qtype_coderunner page type '{$type}'."
                );
        }
    }

    /**
     * Resolves the pages that are not about one named thing.
     *
     * @param string $page the page name.
     * @return moodle_url
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'prototype library':
                // The site-wide library: no course, so site prototypes only.
                return new moodle_url('/local/coderunner_manager/index.php');

            default:
                throw new Exception("Unrecognised qtype_coderunner page '{$page}'.");
        }
    }

    /**
     * Waits for the div Ace manages for a field to appear.
     *
     * The editor is attached by `userinterfacewrapper.newUiWrapper`, which
     * loads over AMD and so is not in place the instant the page is. Checking
     * once was enough while a form was only ever loaded outright; it is not
     * enough now that choosing a prototype rebuilds the form, after which the
     * editors are constructed again and a following step can arrive first.
     *
     * @param string $xpath the editor div for the field.
     * @param string $elname the field, for the error message.
     * @throws ExpectationException if no editor appears.
     */
    /**
     * Checks that an Ace field is highlighting the language it should be.
     *
     * Asserts the editor's *mode*, which is what syntax colouring is: Ace
     * picks a mode from the language it is given and colours the text from
     * that mode's grammar. A field handed no language falls back to plain
     * text, which renders perfectly well and is not coloured at all -- so a
     * test that only typed into the field and read the value back would pass
     * either way. That is how the language came to be dropped on the author
     * form without any scenario noticing.
     *
     * The expected name is matched loosely, because Ace's mode ids are its own
     * ("ace/mode/python" for every Python) and the question's language is
     * CodeRunner's ("python3"); ui_ace.js maps between them.
     *
     * @Then /^the ace field "(?P<elname>(?:[^"]|\\")*)" should be highlighting "(?P<language>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $elname the name of the textarea Ace manages.
     * @param string $language the language, or the stem of one, Ace should use.
     */
    public function the_ace_field_should_be_highlighting($elname, $language) {
        $xpath = "//textarea[@name='$elname' or (contains(@name, '$elname') " .
             "and contains(@class, 'edit_code'))]/following-sibling::div[1]/div";
        $this->wait_for_ace_editor($xpath, $elname);
        $javascript = "const editorNode = document.evaluate("
               . "`$xpath`,"
               . "document,"
               . "null,"
               . "XPathResult.ANY_TYPE,null,"
               . ");"
               . "return ace.edit(editorNode.iterateNext()).session.getMode().\$id;";
        $mode = (string) $this->getSession()->evaluateScript("(function() { $javascript })()");

        $wanted = strtolower(rtrim($language, '0123456789'));
        if (!str_contains(strtolower($mode), $wanted)) {
            throw new ExpectationException(
                "The ace field '{$elname}' is in mode '{$mode}', which is not " .
                "a mode for '{$language}'. A field with no language falls back " .
                "to 'ace/mode/text' and is not syntax coloured.",
                $this->getSession()
            );
        }
    }

    protected function wait_for_ace_editor(string $xpath, string $elname): void {
        // CodeRunner 6 renders the settings sections collapsed, and every code
        // field except the answer and the test cases is inside one. Ace does
        // attach to a textarea in a collapsed fieldset -- the markup is in the
        // DOM and merely hidden -- so this is not what makes the editor
        // findable; it is so that the step can act on the field afterwards, and
        // so that a scenario does not depend on some earlier step having opened
        // the form. That dependency was real: the three Ace steps were the only
        // ones here that did not expand, and one scenario reached its template
        // solely because a stray "I set the following fields to these values"
        // table expanded the form as a side effect, which made deleting the
        // stray rows break the scenario.
        $this->execute('behat_forms::i_expand_all_fieldsets');

        $driver = $this->getSession()->getDriver();
        $this->spin(
            function() use ($driver, $xpath) {
                return (bool) $driver->find($xpath);
            },
            false,
            self::get_extended_timeout(),
            new ExpectationException(
                "No Ace editor appeared for the field '{$elname}'",
                $this->getSession()
            )
        );
    }
}
