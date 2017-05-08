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
 * @package   mod_moodleoverflow
 * @copyright 2016 Your Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config and locallib.
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

$moodleoverflow = optional_param('moodleoverflow', 0, PARAM_INT);

// Set the URL that should be used to return to this page.
$PAGE->set_url('/mod/moodleoverflow/post.php', array(
        'moodleoverflow' => $moodleoverflow
));

// These params will be passed as hidden variables later in the form.
$page_params = array('moodleoverflow' => $moodleoverflow);

// Get the system context instance.
$systemcontext = context_system::instance();

// If not logged in, do so.
// TODO: Differenzieren. Und $OUTPUT->confirm() benutzen?
if (!isloggedin() OR isguestuser()) {
    require_login();
}

// First step: A general login is needed to post something.
require_login(0, false);

// First posibility: User is starting a new discussion in a moodleoverflow instance.
if (!empty($moodleoverflow)) {

    // Check the moodleoverflow instance is valid.
    if (! $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflow))) {
        print_error('invalidmoodleoverflowid', 'moodleoverflow');
    }

    // Get the related course.
    if (! $course = $DB->get_record('course', array('id' => $moodleoverflow->course))) {
        print_error('invalidcourseid');
    }

    // Get the related coursemodule.
    if (! $cm = get_course_and_cm_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Retrieve the contexts.
    $modulecontext = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    // TODO CONTINUE HERE.

}