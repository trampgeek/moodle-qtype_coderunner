<?php
// This file is part of CodeRunner - http://coderunner.org.nz
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
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script generates a csv or excel download of all attempt-step data
 * (or at least all that's likely to be useful) on a specific quiz, passed
 * as URL parameter quizid. The 'format' (csv or excel) is a required parameter
 * too.
 * The user must have grade:viewall permissions to run the script.
 *
 * @package   qtype_coderunner
 * @copyright 2017 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** In Moodle 4.0 through to at least 4.3, the core tablelib code has screwed up
 * the formatting of data by stripping out all the html tags and newlines.
 * See https://tracker.moodle.org/browse/MDL-78342
 * Pending a resolution (how many years to wait?) this override bypasses
 * the flawed function that adds data to the table.
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/tablelib.php');

class table_dataformat_export_format_fixed extends table_dataformat_export_format {
    /**
     * Add a row of data. This is where the Moodle version was screwed.
     *
     * @param array $row One record of data
     */
    public function add_data($row) {
        // Broken code was ....... ........ ........>  if (!$this->supports_html()) {
        // Broken code was ...... ....... ..... .....> $row = $this->format_data($row);
        // Broken code was ...... .. ...... ..... ....> };     .
        $this->dataformat->write_record($row, $this->rownum++);
        return true;
    }
}

class UnscrewedSqlTable extends table_sql {
    /**
     * Get, and optionally set, the export class.
     * @param $exportclass (optional) if passed, set the table to use this export class.
     * @return table_default_export_format_parent the export class in use (after any set).
     */
    /* Excluding the visibility error on this function as we need to keep it the same
       as the function that is being fixed/overidden.
    */
    // phpcs:disable Squiz.Scope.MethodScope.Missing
    function export_class_instance($exportclass = null) {
        if (!is_null($exportclass)) {
            $this->started_output = true;
            $this->exportclass = $exportclass;
            $this->exportclass->table = $this;
        } else if (is_null($this->exportclass) && !empty($this->download)) {
            $this->exportclass = new table_dataformat_export_format_fixed($this, $this->download);
            if (!$this->exportclass->document_started()) {
                $this->exportclass->start_document($this->filename, $this->sheettitle);
            }
        }
        return $this->exportclass;
    }
    //phpcs:enable
}

// Get the quiz-id and format parameters from the URL.
$quizid = required_param('quizid', PARAM_INT);
$format = required_param('format', PARAM_RAW);  // Csv or excel.
$anonymise = optional_param('anonymise', 0, PARAM_INT);

// Login and check permissions.
require_login();

if (class_exists('mod_quiz\access_manager')) {
    $quiz = mod_quiz\access_manager::load_quiz_and_settings($quizid);
} else { // Older versions of Moodle.
    require_once($CFG->libdir . '/../mod/quiz/accessmanager.php');
    $quiz = quiz_access_manager::load_quiz_and_settings($quizid);
}
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);

// I'm not sure if the next three lines are ever relevant but ... what's to lose?
$PAGE->set_url('/question/type/coderunner/getallattempts.php');
$PAGE->set_context($coursecontext);
$PAGE->set_title('Get all quiz attempts');  // TODO: use get_string.

if (!has_capability('moodle/grade:viewall', $coursecontext)) {
    echo '<p>' . get_string('unauthoriseddbaccess', 'qtype_coderunner') . '</p>';
} else {
    $table = new UnscrewedSqlTable(uniqid());
    $fields = "concat(quiza.uniqueid, qasd.attemptstepid, qasd.id) as uniquekey,
        quiza.uniqueid as quizattemptid,
        timestart,
        timefinish,";
    if (!$anonymise) {
        $fields .= "
        u.firstname,
        u.lastname,
        u.email,";
    } else {
        $fields .= "
        SHA2(u.email, 256) as hashed_email,
        ";
    }
    $fields .= "
        qatt.slot,
        qatt.questionid,
        qatt.questionsummary,
        quest.name as qname,
        slot.maxmark as mark,
        qattsteps.timecreated as timestamp,
        FROM_UNIXTIME(qattsteps.timecreated,'%Y/%m/%d %H:%i:%s') as datetime,
        qattsteps.fraction,
        qattsteps.state,
        qasd.attemptstepid,
        qasd.name as qasdname,
        qasd.value as value";

    $from = "{user} u
    JOIN {quiz_attempts} quiza ON quiza.userid = u.id AND quiza.quiz = :quizid
    JOIN {question_attempts} qatt ON qatt.questionusageid = quiza.uniqueid
    JOIN {question_attempt_steps} qattsteps ON qattsteps.questionattemptid = qatt.id
    JOIN {question_attempt_step_data} qasd on qasd.attemptstepid = qattsteps.id
    JOIN {question} quest ON quest.id = qatt.questionid
    JOIN {quiz_slots} slot ON qatt.slot = slot.slot AND slot.quizid = quiza.quiz";

    $where = "quiza.preview = 0
    AND (qasd.name NOT LIKE '-_%' OR qasd.name = '-_rawfraction')
    AND (qasd.name NOT LIKE '^_%' OR qasd.name = '_testoutcome')
    AND quest.length > 0
    ORDER BY quiza.uniqueid, timestamp";

    $params = ['quizid' => $quizid];
    $table->define_baseurl($PAGE->url);
    $table->set_sql($fields, $from, $where, $params);
    $table->is_downloading($format, "allattemptson$quizid", "All quiz attempts $quizid");
    $pagesize = 100; // Surely this is irrelevant for a download?
    raise_memory_limit(MEMORY_EXTRA);
    $table->out($pagesize, false); // And out it goes.
}
