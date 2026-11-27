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
 * API routines for qtype_coderunner
 *
 * @package qtype_coderunner
 * @author Dongsheng Cai <dongsheng@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Checks file access for CodeRunner questions.
 * Feedbackfiles are stored in the course context, because they are generated
 * by the grader, which does not have access to the questionattemptstepid.
 * Hence, the special case for these files needs to use the course context.
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function qtype_coderunner_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $COURSE;
    if ($filearea === 'feedbackfiles') {
        require_login($course, false, $cm);
        $fs = get_file_storage();
        $filename = array_pop($args);
        $itemid = intval(array_shift($args));
        $filepath = '/';
        $contextid = context_course::instance($COURSE->id)->id;
        $file = $fs->get_file($contextid, 'qtype_coderunner', $filearea, $itemid, $filepath, $filename);
        if (!$file) {
            send_file_not_found();
        }
        send_stored_file($file, 0, 0, $forcedownload, $options); // Adjust options can be added here if reqd.
    }
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_coderunner', $filearea, $args, $forcedownload, $options);
}

function qtype_coderunner_reload_cache_definitions_after_ttl_update(string $caller) {
    cache_helper::update_definitions();
}

/**
 * Add a link to the CodeRunner management dashboard on the site home page.
 *
 * The site home had no such link at all, so the only way to the site-scope
 * prototype library was to enter a course, follow the dashboard there, and
 * then strip the course id out of the URL -- which the author reported on
 * 27 November.
 */
function qtype_coderunner_extend_navigation_frontpage(
    navigation_node $navref,
    stdClass $course,
    context_course $context
): void {
    // The **system** context, not the front page course's. The dashboard asks
    // for the capability there when it is reached without a course id
    // (`management.php`, the `else` branch), so asking anywhere else would
    // offer a link to a page that then refuses it. In practice that means
    // administrators, which is what the site home needs: anybody may look at
    // it, and the site's CodeRunner settings are nobody else's business.
    if (!has_capability('qtype/coderunner:management', context_system::instance())) {
        return;
    }

    // No course id, which is what makes it the site's own dashboard rather
    // than the front page course's. The same distinction the prototype
    // library draws, and the reason the author could only reach the site view
    // by editing the URL by hand.
    $navref->add(
        get_string('mgmt_navlink', 'qtype_coderunner'),
        new moodle_url('/question/type/coderunner/management.php'),
        navigation_node::TYPE_SETTING,
        null,
        'coderunnermanagement',
        new pix_icon('i/settings', '')
    );
}

/**
 * Add a link to the CodeRunner management dashboard in the course administration navigation.
 * Visible to anyone with the qtype/coderunner:management capability in the course context.
 */
function qtype_coderunner_extend_navigation_course(
    navigation_node $navref,
    stdClass $course,
    context_course $context
): void {
    if (has_capability('qtype/coderunner:management', $context)) {
        $url = new moodle_url(
            '/question/type/coderunner/management.php',
            ['courseid' => $course->id]
        );
        // Keyed, so that other code can find it. The prototype manager's own
        // link is placed directly after this one and needs to be able to say
        // which node it means; a null key becomes the child count, which is a
        // number that moves whenever anything else is added.
        $navref->add(
            get_string('mgmt_navlink', 'qtype_coderunner'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'coderunnermanagement',
            new pix_icon('i/settings', '')
        );
    }
}
