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
    if (!$systemcontextid) {
        $systemcontextid = 1; // HACK ALERT: occurs when phpunit initialising itself.
    }

    delete_existing_prototypes($systemcontextid);
    $topid = find_or_make_top($systemcontextid);
    $prototypes_category = find_or_make_prototype_category($systemcontextid, $topid);
    load_new_prototypes($systemcontextid, $prototypes_category);
    return true;
}


// Delete all existing prototypes in the given (system) context.
function delete_existing_prototypes($systemcontextid) {
    global $DB;
    $query = "SELECT q.id
              FROM {question} q JOIN {question_categories} cats
              ON q.category = cats.id
              WHERE cats.contextid=?
              AND q.name LIKE '%PROTOTYPE_%'";
    $prototypes = $DB->get_records_sql($query, array($systemcontextid));
    foreach ($prototypes as $question) {
        $DB->delete_records('question_coderunner_options', array('questionid' => $question->id));
        $DB->delete_records('question', array('id' => $question->id));
    }
}


// Find or create the top category for the system context
// This may involve renaming an existing CR_PROTOTYPES category to 'top'
// if there's one already there but no actual top.
// Return the topid.
function find_or_make_top($systemcontextid) {
    global $DB;
    $topid = -1;
    $prototypecategoryid = -1;
    $tops = $DB->get_records('question_categories',
            array('contextid' => $systemcontextid, 'parent' => 0));

    foreach ($tops as $id => $category) {
        if (strtolower($category->name) === 'top') {
            $topid = $category->id;
            break;
        } else if ($category->name == 'CR_PROTOTYPES') {
            $prototypecategoryid = $category->id;
            $prototypecat = $category;
        }
    }
    if ($topid === -1 && $prototypecategoryid !== -1) {
        // No top found, but we do have an existing CR_PROTOTYPES category
        // so use that. This is to handle the situation where an older
        // version of CodeRunner was installed on Moodle 3.5, and the
        // CR_PROTOTYPES category was then used as the 'top' category. Other
        // content may then have been added and we don't want to lose it.
        $topid = $prototypecategoryid;
        make_existing_category_top($prototypecat, $systemcontextid);
    }

    // If we couldn't find a top category or make one from an existing
    // CR_PROTOTYPES category, create one now.
    if ($topid === -1) {
        $topid = make_new_top($systemcontextid);
    }
    return $topid;
}


// Create CR_PROTOTYPES category if it doesn't exist or, if it already
// exists, ensure that it is a child of the top category
function find_or_make_prototype_category($systemcontextid, $topid) {
    global $DB;
    $category = $DB->get_record('question_categories',
                array('contextid' => $systemcontextid, 'name' => 'CR_PROTOTYPES'));
    if ($category) {
        $category->parent = $topid;
        $DB->update_record('question_categories', $category);
    } else { // CR_PROTOTYPES category not defined yet. Add it.
        $category = array(
            'name'       => 'CR_PROTOTYPES',
            'contextid'  => $systemcontextid,
            'info'       => 'Category for CodeRunner question built-in prototypes. FOR SYSTEM USE ONLY.',
            'parent'     => $topid,
            'sortorder'  => 999,
            'stamp'      => make_unique_id_code()
        );
        $prototypecategoryid = $DB->insert_record('question_categories', $category);
        if (!$prototypecategoryid) {
            throw new coding_exception("Upgrade failed: couldn't create CR_PROTOTYPES category");
        }
        $category = $DB->get_record('question_categories', array('id' => $prototypecategoryid));
    }
    return $category;
}


// Create a new top category and return its id
function make_new_top($systemcontextid) {
    global $DB;
    $category = new stdClass();
    $category->name = 'top'; // A non-real name for the top category. It will be localised at the display time.
    $category->info = '';
    $category->contextid = $systemcontextid;
    $category->parent = 0;
    $category->sortorder = 0;
    $category->stamp = make_unique_id_code();
    $topid = $DB->insert_record('question_categories', $category);
    return $topid;
}

// Given a category (which should always be CR_PROTOTYPES), rename it as
// 'top' and rewrite it to the database with various other attributes
// set appropriately.
function make_existing_category_top($category, $systemcontextid) {
    global $DB;
    $category->name = 'top'; // A non-real name for the top category. It will be localised at the display time.
    $category->info = '';
    $category->contextid = $systemcontextid;
    $category->parent = 0;
    $category->sortorder = 999;
    $category->stamp = make_unique_id_code();
    $DB->update_record('question_categories', $category);
}


// Load any files in the db directory ending with _PROTOTYPES.xml.
function load_new_prototypes($systemcontextid, $prototype_category) {
    $dbfiles = scandir(__DIR__);
    foreach ($dbfiles as $file) {
        if (strpos(strrev($file), strrev('_PROTOTYPES.xml')) === 0) {
            $filename = __DIR__ . '/' . $file;
            load_questions($prototype_category, $filename, $systemcontextid);
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
    $contexts = new question_edit_contexts($systemcontext);
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
