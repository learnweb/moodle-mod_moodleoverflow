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
 * File to display all posts from a user.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
global $CFG, $PAGE, $OUTPUT;
require_login();

// Declare optional parameters.
$userid = required_param('user', PARAM_INT);
$courseid = optional_param('course', 0, PARAM_INT);

$mustachedata = [
    'userid' => $userid,
    'course' => $courseid,
    'pageavailable' => $CFG->branch > 502
];

// Initiate the page.
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_url('/mod/moodleoverflow/user.php', ['user' => $userid, 'course' => $courseid]);
$PAGE->set_title(get_string('pluginname', 'mod_moodleoverflow'));
$PAGE->set_heading("A page header");

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_moodleoverflow/user', $mustachedata);
echo $OUTPUT->footer();
