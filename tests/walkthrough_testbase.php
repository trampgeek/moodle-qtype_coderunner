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
 * Walkthrough base for the coderunner question type.
 * @group qtype_coderunner
 *
 * @package    qtype
 * @subpackage coderunner
 * @copyright  2011, 2020 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/coderunner/tests/test.php');
require_once($CFG->dirroot . '/question/type/coderunner/db/upgradelib.php');

/**
 * Walkthrough base for the coderunner question type.
 *
 * @coversNothing
 * @copyright  2011, 2020 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class walkthrough_testbase extends \qbehaviour_walkthrough_test_base {
    protected static bool $prototypesinstalled = false;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        ob_start();
        if (!self::$prototypesinstalled) {
            if (\qtype_coderunner_util::using_mod_qbank()) {
                update_question_types_with_qbank();
            } else {
                update_question_types_legacy();
            }
        }
        ob_end_clean();
    }

    public function test_dummy_walkthrough_testbase(): void {
        /* Present to avoid a warning about no testcases. */
    }
}