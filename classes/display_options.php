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

namespace qtype_coderunner;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * An extension of question_display_options that includes the extra options used by the coderunner.
 *
 * @package   qtype_coderunner
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class display_options extends \question_display_options {

    /**
     * @var bool
     */
    public $suppressruntestslink = false;
}
