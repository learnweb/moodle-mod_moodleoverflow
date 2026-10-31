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
 * Page for user statistics
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
global $CFG, $PAGE, $OUTPUT, $USER;

use mod_moodleoverflow\local\dto\userstats_dto;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service\userstats;
use mod_moodleoverflow\output\userstats_table;

// Declare optional parameters.
$cmid = required_param('id', PARAM_INT); // Course Module ID.

// Define important variables.
$moodleoverflow = moodleoverflow::from_cmid($cmid);
$cm = $moodleoverflow->get_cm();
$course = $moodleoverflow->get_course();

// Require a login.
require_login($course, true, $cm);

// Set the context.
$PAGE->set_context($moodleoverflow->get_context());

if (!permissions::can_view_userstats($moodleoverflow, $USER->id)) {
    redirect(new moodle_url('/'));
}

// Print the page header.
$PAGE->set_url('/mod/moodleoverflow/userstats.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('userstats', 'moodleoverflow'));
$PAGE->set_heading(get_string('userstatscourse', 'moodleoverflow', $course->fullname));
$statistics = userstats::get_statistics($moodleoverflow, $USER->id);

// Output starts here. Moodle versions without React get the old table.
echo $OUTPUT->header();
if ($CFG->branch >= 503) {
    $props = (object) ['users' => array_map(fn(userstats_dto $dto) => $dto->to_array(), $statistics)];
    echo $PAGE->get_renderer('mod_moodleoverflow')->render_react('UserStatsPage', $props);
} else {
    (new userstats_table('statisticstable', $statistics, $PAGE->url))->out();
}
echo $OUTPUT->footer();
