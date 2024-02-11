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
 * Helper functions used by the install/upgrade code.
 *
 * @package   qtype_coderunner
 * @copyright 2012 Richard Lobb, The University of Canterbury.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

/**
 * Add/replace standard question types by deleting all questions in the
 * category CR_PROTOTYPES and reloading them from the file
 * questions-CR_PROTOTYPES.xml. Also loads any other prototype files
 * whose names end in _PROTOTYPES.xml (case sensitive).
 *
 * @return bool true if successful
 */
function update_question_types() {
    $systemcontext = context_system::instance();
    $systemcontextid = $systemcontext->id;

    delete_existing_prototypes($systemcontextid);
    if (function_exists('question_get_top_category')) { // Moodle version >= 3.5.
        $parentid = get_top_id($systemcontextid);
    } else {
        $parentid = 0;
    }
    $prototypescategory = find_or_make_prototype_category($systemcontextid, $parentid);
    load_new_prototypes($systemcontextid, $prototypescategory);
    return true;
}


// Delete all existing prototypes in the given (system) context and in the
// CR_PROTOTYPES category.
function delete_existing_prototypes($systemcontextid) {
    global $DB;

    $query = "SELECT q.id
              FROM {context} ctx
              JOIN {question_categories} qc ON qc.contextid = ctx.id
              JOIN {question_bank_entries} qbe ON qbe.questioncategoryid = qc.id
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
              JOIN {question} q ON q.id = qv.questionid
              WHERE ctx.id=?
              AND qc.name='CR_PROTOTYPES'
              AND q.name LIKE 'BUILT%IN_PROTOTYPE_%'";
    $prototypes = $DB->get_records_sql($query, [$systemcontextid]);
    foreach ($prototypes as $question) {
        $DB->delete_records('question_coderunner_options', ['questionid' => $question->id]);
        $DB->delete_records('question', ['id' => $question->id]);
    }
}


// Return the id of the top system context category for Moodle versions >= 3.5.
// This function needs to be able to deal with the possibility that an
// earlier CodeRunner installer has been run on Moodle 3.5 resulting in either
// the CR_PROTOTYPES category being a proxy 'top' or in multiple top
// categories, both of which require some repairs.
// Must only be called for Moodle 3.5 or later.
function get_top_id($systemcontextid) {
    global $DB;
    $topid = 0;
    $prototypecategoryid = 0;
    $tops = $DB->get_records(
        'question_categories',
        ['contextid' => $systemcontextid, 'parent' => 0]
    );

    foreach (array_values($tops) as $category) {
        if (strtolower($category->name) === 'top') {
            $topid = $category->id;
        } else if ($category->name === 'CR_PROTOTYPES') {
            $prototypecategoryid = $category->id;
            $prototypecat = $category;
        }
    }
    if ($topid === 0 && $prototypecategoryid === 0) {
        // No top and no CR_PROTOTYPES category. Make and return a new top.
        $topid = question_get_top_category($systemcontextid, true)->id;
    } else if ($topid === 0 && $prototypecategoryid !== 0) {
        // No top found but we do have an existing CR_PROTOTYPES category
        // which will have been treated as a 'top' proxy. Rename it to 'top'
        // and use that as the real top.
        $topid = $prototypecategoryid;
        make_cr_prototypes_top($prototypecat);
    } else if ($topid !== 0 && $prototypecategoryid !== 0) {
        // We have both top and CR_PROTOTYPES categories. This is broken,
        // and needs to be repaired.
        $prototypecat->parent = $topid;
        $DB->update_record('question_categories', $prototypecat);
    }

    return $topid;
}


// Return CR_PROTOTYPES category, creating it if it doesn't exist.
function find_or_make_prototype_category($systemcontextid, $parentid) {
    global $DB;
    $category = $DB->get_record(
        'question_categories',
        ['contextid' => $systemcontextid, 'name' => 'CR_PROTOTYPES']
    );
    if (!$category) {
        $category = [
            'name'       => 'CR_PROTOTYPES',
            'contextid'  => $systemcontextid,
            'info'       => 'Category for CodeRunner question built-in prototypes. FOR SYSTEM USE ONLY.',
            'parent'     => $parentid,
            'sortorder'  => 999,
            'stamp'      => make_unique_id_code(),
        ];
        $prototypecategoryid = $DB->insert_record('question_categories', $category);
        if (!$prototypecategoryid) {
            throw new coding_exception("Upgrade failed: couldn't create CR_PROTOTYPES category");
        }
        $category = $DB->get_record('question_categories', ['id' => $prototypecategoryid]);
    }
    return $category;
}


// Make the existing CR_PROTOTYPES category into the top category
// by renaming it and setting various other attributes appropriately.
// Update it in the database.
function make_cr_prototypes_top($category) {
    global $DB;
    $category->name = 'top'; // A non-real name for the top category. It will be localised at the display time.
    $category->info = '';
    $category->parent = 0;
    $category->sortorder = 999;
    $category->stamp = make_unique_id_code();
    $DB->update_record('question_categories', $category);
}


// Load any files in the db directory ending with _PROTOTYPES.xml.
function load_new_prototypes($systemcontextid, $prototypecategory) {
    $dbfiles = scandir(__DIR__);
    foreach ($dbfiles as $file) {
        if (strpos(strrev($file), strrev('_PROTOTYPES.xml')) === 0) {
            $filename = __DIR__ . '/' . $file;
            load_questions($prototypecategory, $filename, $systemcontextid);
        }
    }
}

/**
 * Load all the questions from the given import file into the given category.
 * The category from the import file will be ignored if present.
 *
 * @param stdClass $category row from the question_categories table.
 * @param string $importfilename full path of the file to import.
 * @param int $contextid id of the context to import into.
 */
function load_questions($category, $importfilename, $contextid) {
    global $COURSE;
    $qformat = new qformat_xml();
    $qformat->setCategory($category);
    $systemcontext = context::instance_by_id($contextid);
    $contexts = new core_question\local\bank\question_edit_contexts($systemcontext);
    $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
    $qformat->setCourse($COURSE);
    $qformat->setFilename($importfilename);
    $qformat->setRealfilename($importfilename);
    $qformat->setMatchgrades('error');
    $qformat->setCatfromfile(false);
    $qformat->setContextfromfile(false);
    $qformat->setStoponerror(true);

    ob_start(); // Discard import output during installs & upgrades.

    // Do anything before that we need to.
    if (!$qformat->importpreprocess()) {
        ob_end_clean();
        throw new coding_exception('Upgrade failed: error preprocessing prototype upload');
    }

    // Process the given file.
    if (!$qformat->importprocess($category)) {
        ob_end_clean();
        throw new coding_exception('Upgrade failed: error uploading prototype questions');
    }

    // In case anything needs to be done after.
    if (!$qformat->importpostprocess()) {
        ob_end_clean();
        throw new coding_exception('Upgrade failed: error postprocessing prototype upload');
    }
    ob_end_clean();
}
