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

use Behat\Mink\Exception\ExpectationException;
use Facebook\WebDriver\Exception\NoSuchAlertException;
use Moodle\BehatExtension\Exception\SkippedException;

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
        // We inject JS into the browser to set the Ace editor contents...
        // (Gross) JS to take the x-path for the div managed by Ace,
        // open editor for that div, and set the editors value.
        $javascript = "const editorNode = document.evaluate("
               . "`$xpath`,"
               . "document,"
               . "null,"
               . "XPathResult.ANY_TYPE,null,"
               . ");"
               . "const editor = ace.edit(editorNode.iterateNext());"
               . "editor.setValue(`$value`);";
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
     * Step to set an HTML5 session variable 'disableUis' to true to prevent
     * loading of the usual Ace (or Graph etc) UI plugin.
     *
     * @When /^I disable UI plugins in the CodeRunner question type/
     */
    public function i_disable_ui_plugins() {
        $javascript = "sessionStorage.setItem('disableUis', true);";
        $this->getSession()->executeScript($javascript);
    }

    /**
     * Step to remove the HTML5 session variable 'disableUis' (if present)
     * to re-enable loading of the usual Ace (or Graph etc) UI plugins.
     *
     * @When /^I enable UI plugins in the CodeRunner question type/
     */
    public function i_enable_u_plugins() {
        $javascript = "sessionStorage.removeItem('disableUis');";
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

        // Nothing to wait for if it is already the choice: prototypereload.js
        // ignores a change to the value already loaded, so no reload happens.
        if ($select->getValue() === $wanted) {
            return;
        }

        // Mark the page before the change, so that the rebuild can be told
        // from the page it replaces. Mink's NodeElement holds an xpath and
        // re-resolves it on every call, so there is no stale reference to
        // catch; and the chain beside the menu cannot serve either, because
        // breadcrumb() renders nothing for a chain of fewer than two layers.
        // An attribute on the document element is destroyed by a real page
        // load and by nothing else.
        $this->getSession()->executeScript(
            "document.documentElement.setAttribute('data-behat-awaiting-reload', '1');"
        );

        $select->selectOption($wanted);

        // Changing the menu makes prototypereload.js press a hidden no-submit
        // button and the server rebuilds the whole form from the new
        // prototype. wait_for_pending_js() can return while that is in flight,
        // leaving the next step to act on a page being torn down, which
        // surfaces as "element not interactable" rather than as anything to do
        // with the picker.
        $this->spin(
            function($context) {
                return $context->getSession()->evaluateScript(
                    "document.documentElement.getAttribute('data-behat-awaiting-reload') === null"
                );
            },
            false,
            self::get_extended_timeout(),
            new ExpectationException(
                "The form was not rebuilt after choosing the prototype '{$name}'",
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

        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $data);
        $this->execute('behat_forms::press_button', 'id_submitbutton');
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
