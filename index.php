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
 * Moodleoverflow index.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_courseformat\activityoverviewbase;

require_once(__DIR__ . '/../../config.php');
global $CFG, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT); // Course id.

$PAGE->set_url('/mod/moodleoverflow/index.php', ['id' => $id]);
$course = get_course($id);
require_course_login($course);

// Redirect to the new overview page if Moodle version is at least 5.1.
if ($CFG->branch >= 501) {
    activityoverviewbase::redirect_to_overview_page($id, 'moodleoverflow');
}

// Older Moodle versions: The legacy index page is no longer available.
$strmoodleoverflows = get_string('modulenameplural', 'moodleoverflow');
$PAGE->set_pagelayout('incourse');
$PAGE->set_title($course->shortname . ': ' . $strmoodleoverflows);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strmoodleoverflows);

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('indexnotavailable', 'moodleoverflow'), \core\output\notification::NOTIFY_INFO);
echo $OUTPUT->continue_button(new moodle_url('/course/view.php', ['id' => $course->id]));
echo $OUTPUT->footer();
