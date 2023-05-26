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
 * Prints a particular instance of moodleoverflow
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config and locallib.
require_once(__DIR__.'/../../config.php');
global $CFG, $PAGE, $DB, $OUTPUT, $SESSION;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

use mod_moodleoverflow\tables\userstats_table;
// Declare optional parameters.
$cmid = required_param('id', PARAM_INT);             // Course Module ID.
$courseid = required_param('courseid', PARAM_INT);   // Course ID.
$mid = required_param('mid', PARAM_INT);             // Moodleoveflow ID, Moodleoverflow that started the statistics.

// Define important variables.
if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*');
}
if ($cmid) {
    $cm = get_coursemodule_from_id('moodleoverflow', $cmid, $course->id, false, MUST_EXIST);
}
if ($mid) {
    $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $mid), '*');
}
// Require a login.
require_login($course);

// Set the context.
$context = context_course::instance($course->id);
$PAGE->set_context($context);

// Do a capability check, in case a user iserts the userstats-url manually.
if (has_capability('mod/moodleoverflow:viewanyrating', $context)) {
    // Print the page header.
    $PAGE->set_url('/mod/moodleoverflow/userstats.php', array('id' => $cm->id,
    'courseid' => $course->id, 'mid' => $moodleoverflow->id));
    $PAGE->set_title(format_string('User statistics'));
    $PAGE->set_heading(format_string('User statistics of course: ' . $course->fullname));

    // Output starts here.
    echo $OUTPUT->header();
    $table = new userstats_table('statisticstable' , $course->id, $moodleoverflow->id, $PAGE->url);
    $table->out();
    echo $OUTPUT->footer();
}
