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

namespace qtype_coderunner\task;

/**
 * An ad hoc task to set up CodeRunner question prototypes after installation.
 * Can't be done in the install.php script because the question type is installed
 * before the question bank module is installed.
 *
 * @package   qtype_coderunner
 * @copyright 2025 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/coderunner/db/upgradelib.php');

class qtype_coderunner_setup_question_prototypes extends \core\task\adhoc_task {
    /**
     * Execute the task
     */
    public function execute() {
        global $CFG;
        return update_question_types_internal();
    }
}
