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
 * Behat extensions for coderunner.
 *
 * @package    qtype_coderunner
 * @copyright  2015 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Mink\Exception\ExpectationException;
use Facebook\WebDriver\Exception\NoSuchAlertException;


class behat_coderunner extends behat_base {

    /**
     * Loads the default coderunner settings file for testing.
     * It seems silly that I have to do that. Why is there not
     * a global behat configuration option apply to all features?
     * @Given /^the CodeRunner test configuration file is loaded/
     */
    public function the_coderunner_test_configuration_file_is_loaded() {
        global $CFG;
        require($CFG->dirroot .'/question/type/coderunner/tests/fixtures/test-sandbox-config.php');
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
     * NOTE: this assumes the existence of a text area next to a
     * UI wrapper div containing the Ace div! Also works on partial matches,
     * i.e. value as _answer will work for Ace UI
     * Intended as a replacement for I set field to <value>, for ace fields.
     * @Then /^I set the ace field "(?P<elname>(?:[^"]|\\")*)" to "(?P<value>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $expected The string that we expect to find
     */
    public function i_set_ace_field($elname, $value) {
        $xpath = "//textarea[@name='$elname' or (contains(@name, '$elname') and contains(@class, 'edit_code'))]/../div/div ";
        $driver = $this->getSession()->getDriver();
        // Does the div managed by Ace exist?
        if (!$driver->find($xpath)) {
            $error = "Ace editor not found!";
            throw new ExpectationException($error, $this->getSession());
        }
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
     * Sets the ace editor content to provided string, using name of associated textarea.
     * NOTE: this assumes the existence of a text area next to a
     * UI wrapper div containing the Ace div!
     * Intended as a replacement for I set field to <value>, for ace fields.
     * @Then /^I set the ace field "(?P<elname>(?:[^"]|\\")*)" to:$/
     * @throws ExpectationException
     * @param string $expected The string that we expect to find
     */
    public function i_set_ace_field_pystring($elname, $pystring) {
        $this->i_set_ace_field($elname, $pystring->getRaw());
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
     * Sets the given field to a given value and dismisses the expected alert.
     * @When /^I set the field "(?P<field_string>(?:[^"]|\\")*)" to "(?P<field_value_string>(?:[^"]|\\")*)" and dismiss the alert$/
     */
    public function i_set_the_field_and_dismiss_the_alert($field, $value) {
        // Gets the field.
        $fielditem = behat_field_manager::get_form_field_from_label($field, $this);

        // Makes sure there is a field before continuing.
        if ($fielditem) {
            $fielditem->set_value($value);
        } else {
            throw new ExpectationException("No field '{$field}' found.", $this->getSession());
        }
        // Gets you to wait for the pending JS alert by sleeping.
        sleep(1);
        try {
            // Gets the alert and its text.
            $alert = $this->getSession()->getDriver()->getWebDriver()->switchTo()->alert();
            $alert->accept();
        } catch (NoSuchAlertException $ex) {
            throw new ExpectationException("No alert was triggered appropriately", $this->getSession());
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
}
