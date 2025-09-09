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
 * Quiz class to access details of the quiz in which a question is running.
 *
 * @package    qtype_coderunner
 * @copyright  2025 Richard Lobb
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_coderunner_quiz {
    /** @var string Quiz name. Empty string if not running in a quiz*/
    public $name = '';

    /** @var array Quiz tags if running in a quiz else empty array.*/
    public $tags;

    public function __construct() {
        global $PAGE, $DB;

        $this->tags = [];

        $context = $PAGE->context;  // Context may or may not be a quiz.
        if ($context && $context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if ($cm) {
                $quiz = $DB->get_record('quiz', ['id' => $cm->instance]);
                $this->name = $quiz->name;
                $tags = core_tag_tag::get_item_tags('core', 'course_modules', $cm->id);
                foreach ($tags as $tag) {
                    $this->tags[] = $tag->name;
                }
            }
        }
    }
}
