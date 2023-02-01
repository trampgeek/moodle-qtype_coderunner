<?php
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
 * CodeRunner files class which implements question_response_files to
 * allow access to files.
 *
 * @package    qtype_coderunner
 * @copyright  Richard Lobb, 2021, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner;

/**
 * This class implements {@link question_response_files} interface which allows
 * bulk testing and previews to use sample answer files by retrieving files associated
 * with a particular question. Can also be constructed with a different filearea
 * for future use.
 */
class coderunner_files implements \question_response_files {
    /** @var type The contextid of the question. */
    protected $contextid;
    /** @var string The string name of the filearea */
    protected $filearea;
    /** @var int The question id number. */
    protected $questionid;

    /**
     * Constructor.
     *
     * @param type $contextid The contextid of the question.
     * @param string $filearea The name of the filearea.
     * @param int $questionid The id of the question.
     */
    public function __construct($contextid, $filearea, $questionid) {
        $this->contextid = $contextid;
        $this->filearea = $filearea;
        $this->questionid = $questionid;
    }

    /**
     * Returns all the CodeRunner files in the specified filearea, questionid and
     * contextid.
     *
     * @return array of files An array of all the files requested.
     */
    public function get_files() {
        $fs = get_file_storage();
        return $fs->get_area_files($this->contextid, 'qtype_coderunner', $this->filearea, $this->questionid);
    }

    /**
     * Returns a string for the object to satisfy other Moodle checks.
     *
     * @return string A string for the object.
     */
    public function __toString() {
        return "coderunnerquestionresponsefiles";
    }
}