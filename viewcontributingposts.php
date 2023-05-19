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
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config and locallib.
require_once(__DIR__.'/../../config.php');
global $PAGE, $OUTPUT;
// If invoked from a course show the discussions from the course.
$courseid = optional_param('courseid', 0, PARAM_INT);
$params = array();

if ($courseid) {
    $params['courseid'] = $courseid;
}

$PAGE->set_url('/mod/moodleoverflow/viewcontributingposts.php', $params);
require_login();

// Systemwide context as all post in moodleoverflows are displayed.
$PAGE->set_context(context_system::instance());

$PAGE->set_title(get_string('overviewposts', 'mod_moodleoverflow'));
$PAGE->set_heading(get_string('overviewposts', 'mod_moodleoverflow'));

echo $OUTPUT->header();
echo "hello world";
echo $OUTPUT->footer();
