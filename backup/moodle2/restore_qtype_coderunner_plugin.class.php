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
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  &copy; 2012 Richard Lobb
 * @author     Richard Lobb richard.lobb@canterbury.ac.nz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/coderunner/questiontype.php');

/**
 * Restore plugin class for coderunner questions.
 */
class restore_qtype_coderunner_plugin extends restore_qtype_plugin {
    /**
     * Returns the paths to be handled by the plugin at question level.
     */
    public function define_question_plugin_structure() {

        $paths = [];

        // Add options and testcases to the restore structure.
        $this->add_question_options($paths);
        $this->add_question_testcases($paths);

        return $paths; // And return the paths.
    }

    /*
     * Add the options to the restore structure.
     */
    private function add_question_options(&$paths) {
        // Check $paths is an array.
        if (!is_array($paths)) {
            throw new restore_step_exception('paths_must_be_array', $paths);
        }

        $elename = 'coderunner_options';
        $elepath = $this->get_pathfor('/coderunner_options/coderunner_option');  // We used get_recommended_name() so this works.
        $paths[] = new restore_path_element($elename, $elepath);
    }

    /*
     * Add the testcases to the restore structure.
     */
    private function add_question_testcases(&$paths) {
        // Check $paths is one array.
        if (!is_array($paths)) {
            throw new restore_step_exception('paths_must_be_array', $paths);
        }

        $elename = 'coderunner_testcases';
        $elepath = $this->get_pathfor('/coderunner_testcases/coderunner_testcase');
        $paths[] = new restore_path_element($elename, $elepath);
    }


    /*
     * Called during restore to process the testcases within the
     * backup element.
     */
    public function process_coderunner_testcases($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, insert the new testcase.
        if ($questioncreated) {
            $data->questionid = $newquestionid;
            // Insert record.
            $DB->insert_record("question_coderunner_tests", $data);
        }
        // Nothing to remap if the question already existed.
    }

    /*
     * Called during restore to process the options within the
     * backup element.
     */
    public function process_coderunner_options($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to insert a new options record.
        if ($questioncreated) {
            $data->questionid = $newquestionid;

            // Convert pre-version 3.1 fields to post 3.1.
            if (
                isset($data->pertesttemplate) &&
                    trim($data->pertesttemplate ?? '') != '' &&
                    empty($data->enablecombinator) &&
                    $data->grader != 'CombinatorTemplateGrader'
            ) {
                $data->template = $data->pertesttemplate;
                $data->iscombinatortemplate = 0;
            }
            if (
                isset($data->combinatortemplate) &&
                    trim($data->combinatortemplate ?? '') != '' &&
                    ((isset($data->enablecombinator) &&
                    $data->enablecombinator == 1 ) ||
                            $data->grader == 'CombinatorTemplateGrader')
            ) {
                $data->template = $data->combinatortemplate;
                $data->iscombinatortemplate = 1;
            }
            if ($data->grader == 'CombinatorTemplateGrader') {
                $data->grader = 'TemplateGrader';
            }

            self::set_v6_columns(
                $data,
                \context_course::instance($this->get_task()->get_courseid())
            );
            $this->request_prototype_sweep_if_unresolved($data);

            // Insert the record.
            $DB->insert_record("question_coderunner_options", $data);
        }
        // Nothing to remap if the question already existed.
    }

    /** @var bool Whether this restore has already asked for the prototype sweep. */
    protected bool $sweeprequested = false;

    /**
     * Ask for the prototype sweep when a restored question could not be
     * resolved.
     *
     * A pre-6.0 backup carries its course's own prototype *questions*, and
     * those become prototypes in the manager only when the install task next
     * runs. Until then there is nothing of their name to resolve against, so
     * every question in the restored course that is built on one arrives
     * broken. The task fixes both halves in the right order — it snapshots the
     * prototype questions, then sweeps the questions whose prototype id is
     * null — but nothing was asking it to run: it is queued by the manager's
     * install and upgrade steps alone, so on a site that is not being upgraded
     * the restored course stayed broken indefinitely.
     *
     * Asked only when a question actually failed to resolve, so a restore
     * whose prototypes are all present queues nothing, and at most once per
     * restore. The flag is an instance property rather than a static because
     * this object belongs to the restore: a static would be shared with every
     * other restore in the same request, and asking twice is harmless anyway
     * — `queue_adhoc_task()` is told to check for an existing copy, so the
     * flag saves a query rather than preventing a duplicate.
     *
     * @param \stdClass $data the options row, after its columns are set.
     */
    protected function request_prototype_sweep_if_unresolved(\stdClass $data): void {
        if ($data->prototypeid !== null || $this->sweeprequested) {
            return;
        }
        $this->sweeprequested = true;
        \local_coderunner_manager\prototype_installer::queue_migration();
    }

    /**
     * Set the CodeRunner 6 columns on an options row being restored.
     *
     * **The prototype id is re-resolved, never carried over.** It is a local
     * id and Moodle does not remap ids by itself, so the backed-up one would
     * point the question at whatever prototype holds that id here: right by
     * accident on the same site, silently wrong across sites. It is discarded
     * and the prototype found by name instead, which is the boundary-code name
     * lookup the design allows for restore and which works on the same site
     * and across sites alike whenever the target course can see a prototype of
     * that name. When it cannot, the question is left with a null prototype id
     * and reports itself broken, which is visible rather than silent.
     *
     * M5 adds matching by the backed-up id and by the prototype uuid, for the
     * cases a name cannot settle: an ambiguous name, or a prototype that
     * travelled in the backup itself.
     *
     * **A pre-6.0 backup has no overrides document, and must be given one
     * here.** Its settings are in the CodeRunner 5 columns beside it, where
     * null means inherited, so the migration's own converter turns them into
     * one — the same code, per question, that the migration task runs in bulk.
     *
     * Leaving it for the task to find was the obvious alternative and is not
     * safe, because of what the half-converted state does in the meantime. A
     * null overrides document means *this question overrides nothing*, so
     * `resolve_inherited_fields()` overwrites every inherited column from the
     * chain, and the sixteen fields CodeRunner 5 read straight from the column
     * — the penalty regime, the answer box size, the attachment settings and
     * the rest — are replaced by the prototype's. The question renders and
     * grades perfectly well with the wrong settings, until something runs the
     * migration; and nothing does, on a site that is not being upgraded.
     * Converting here closes the window instead of shortening it.
     *
     * **An empty element is not a document.** Moodle backup writes a null
     * column as an empty element and the restore carries it through as `''`,
     * which `json_decode()` reads as nothing at all — the same trap the Moodle
     * XML importer set for the question form, where it was solved with an
     * explicit marker. So emptiness is tested rather than presence.
     *
     * @param \stdClass $data the options row, modified in place.
     * @param \context $context the context whose course resolves a name.
     */
    public static function set_v6_columns(\stdClass $data, \context $context): void {
        if (($data->prototypetype ?? 0) != 0) {
            // A legacy prototype question is not a question with a prototype:
            // it has nothing to inherit and nothing to override, and the
            // prototype installer converts it into a prototype of its own when
            // it next runs. Same rule as set_prototype_reference().
            $data->prototypeid = null;
            $data->overrides = null;
            return;
        }

        $data->prototypeid = (new qtype_coderunner())->resolve_prototype_id(
            $data->coderunnertype,
            $context
        );

        if (($data->overrides ?? '') === '') {
            $data->overrides = json_encode(
                \qtype_coderunner\v6\legacy_converter::question_overrides((array) $data)->to_array()
            );
        }
    }

    // Add the extra methods required by MDL-83541.
    // #[Override]  // This breaks the restore at run time if used with older versions of moodle so commented out.
    public static function convert_backup_to_questiondata(array $backupdata): stdClass {
        $questiondata = parent::convert_backup_to_questiondata($backupdata);
        $qtype = $questiondata->qtype;

        if (isset($backupdata["plugin_qtype_{$qtype}_question"]['coderunner_testcases'])) {
            $questiondata->options->testcases = [];
            foreach ($backupdata["plugin_qtype_{$qtype}_question"]['coderunner_testcases']['coderunner_testcase'] as $record) {
                $testcase = new stdClass();
                $fields = [ 'testcode', 'testtype', 'expected', 'useasexample', 'display',
                    'hiderestiffail', 'mark', 'stdin', 'extra'];
                foreach ($fields as $field) {
                    if (isset($record[$field])) {   // Old backups don't have testtype.
                        $testcase->$field = $record[$field];
                    }
                }
                $questiondata->options->testcases[] = $testcase;
            }
        }

        if (isset($backupdata["plugin_qtype_{$qtype}_question"]['coderunner_options'])) {
            $questiondata->options = (object) array_merge(
                (array) $questiondata->options,
                $backupdata["plugin_qtype_{$qtype}_question"]['coderunner_options']['coderunner_option'][0]
            );
        }

        return $questiondata;
    }

    // #[Override]  // This breaks the restore at run time if used with older versions of moodle so commented out.
    protected function define_excluded_identity_hash_fields(): array {
        return [
            '/answers',
            '/hints',
            '/prototype',
            '/options/customise',
            '/options/prototypeid',
            // The overrides document is excluded for as long as the CodeRunner
            // 5 columns are still written, because those columns carry the same
            // content and this does not: a pre-6.0 backup has no overrides
            // element at all, so leaving it in the hash stops such a backup
            // matching anything and makes the restore copy every question in
            // it. That is the duplication bug that grew sites' question banks
            // exponentially before CodeRunner supplied a hash at all. It
            // returns, canonicalised, in phase 4, when the legacy columns go
            // and it becomes the only record of what a question overrides.
            '/options/overrides',
            '/options/templateparamsevald',
            '/options/testcases/id',
            '/options/testcases/questionid',
        ];
    }

    // #[Override]  // This breaks the restore at run time if used with older versions of moodle so commented out.
    public static function remove_excluded_question_data(stdClass $questiondata, array $excludefields = []): stdClass {
        if (isset($questiondata->options->customise)) {
            unset($questiondata->options->customise);
        }
        if (isset($questiondata->prototype)) {
            unset($questiondata->prototype);
        }
        if (isset($questiondata->answers)) {
            unset($questiondata->answers);
        }
        if (isset($questiondata->options->answers)) {
            unset($questiondata->options->answers);
        }
        if (isset($questiondata->hints)) {
            unset($questiondata->hints);
        }

        // Hack: convert all cases of allowmultiplestdins being false to null.
        if (isset($questiondata->options) && ! ($questiondata->options->allowmultiplestdins ?? null)) {
            $questiondata->options->allowmultiplestdins = null;
        }

        if (isset($questiondata->options->testcases)) {
            foreach ($questiondata->options->testcases as $testcase) {
                if (isset($testcase->id)) {
                    unset($testcase->id);
                }
                if (isset($testcase->questionid)) {
                    unset($testcase->questionid);
                }
            }
        }
        return parent::remove_excluded_question_data($questiondata, $excludefields);
    }
}
