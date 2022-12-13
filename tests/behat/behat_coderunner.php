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

use Behat\Mink\Exception\ExpectationException as ExpectationException;
use WebDriver\Exception\NoAlertOpenError;
use WebDriver\Exception\UnexpectedAlertOpen;

class behat_coderunner extends behat_base {
     /**
     * Sets the webserver sandbox to enabled for testing purposes.
     *
     * @Given /^the CodeRunner sandbox is enabled/
     */
    public function the_coderunner_sandbox_is_enabled() {
        set_config('wsenabled', 1, 'qtype_coderunner');
        set_config('jobesandbox_enabled', 1, 'qtype_coderunner');
        set_config('jobe_host', '172.17.0.1:4000', 'qtype_coderunner');
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
        $val = $page->find('xpath',$xpath)->getValue();
        if ($val !== $expected) {
            $error = "'$val' does not match '$expected'";
            throw new ExpectationException($error, $this->getSession());
        }
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
     * UI wrapper div containing the Ace div!
     * Intended as a replacement for I set field to <value>, for ace fields.
     * @Then /^I set the ace field "(?P<elname>(?:[^"]|\\")*)" to "(?P<value>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $expected The string that we expect to find
     */    
     public function i_set_ace_field($elname, $value) {
        $xpath = "//textarea[@name='$elname']/../div/div";
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
     * @When /^I disable UI plugins/
     */
    public function i_disable_ui_plugins() {
        $javascript = "sessionStorage.setItem('disableUis', true);";
        $this->getSession()->executeScript($javascript);
    }

    /**
     * Step to remove the HTML5 session variable 'disableUis' (if present)
     * to re-enable loading of the usual Ace (or Graph etc) UI plugins.
     *
     * @When /^I enable UI plugins/
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
        $this->execute('behat_forms::i_set_the_field_to', array($fieldlocator, $this->escape($value)));
    }

    /**
     * @Then /^I should see a canvas/
     */
    public function i_see_a_canvas() {
        $xpath = "//canvas";
        $driver = $this->getSession()->getDriver();
        if (! $driver->find($xpath)) {
            throw new ExpectationException("Couldn't find canvas",  $this->getSession());
        }
    }

    /**
     * @Then /^I should not see a canvas/
     */
    public function i_should_not_see_a_canvas() {
        $xpath = "//canvas";
        $driver = $this->getSession()->getDriver();
        if ($driver->find($xpath)) {
            throw new ExpectationException("Found a canvas",  $this->getSession());
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
     *
     * This is currently just a hack. I used to be able to catch UnexpectedAlertOpen
     * but that's not working any more. I can catch a general exception
     */
    public function i_set_the_field_and_dismiss_the_alert($field, $value) {
        try {
            $this->execute('behat_forms::i_set_the_field_to', array($field, $this->escape($value)));
            $this->getSession()->getDriver()->getWebDriver()->switchTo()->alert()->dismiss(); // This has started working again!
        } catch (Exception $e) {  // For some reason UnexpectedAlertOpen can't be caught.
            return;
        }
    }
}
