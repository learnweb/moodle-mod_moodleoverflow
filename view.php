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
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\event\course_module_viewed;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service\subscription;
use mod_moodleoverflow\output\pages\view\view_page;

require_once(__DIR__ . '/../../config.php');
global $PAGE, $OUTPUT, $SESSION, $USER;

// Declare optional parameters.
$id = optional_param('id', 0, PARAM_INT);       // Course Module ID.
$m = optional_param('m', 0, PARAM_INT);        // MoodleOverflow ID.
$page = optional_param('page', 0, PARAM_INT);     // Which page to show.
$unsubscribe = optional_param('unsubscribe', 0, PARAM_BOOL); // Unsubscribe link of a mail.

// Fetch the moodleoverflow instance.
$modflow = match (true) {
    $id > 0 => moodleoverflow::from_cmid($id),
    $m > 0 => moodleoverflow::from_id($m),
    default => throw new moodle_exception('missingparameter'),
};
$course = $modflow->get_course();

// System checks: require a login and the permission to see the page.
require_login($course, true, $modflow->get_cm());
permissions::ensure(permissions::can_view_moodleoverflow($modflow, $USER->id), 'noviewdiscussionspermission');

// Print the page header.
$PAGE->set_url(new moodle_url($modflow->get_link(), ['page' => $page]));
$PAGE->set_title(format_string($modflow->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('limitedwidth');

$PAGE->requires->js_call_amd('mod_moodleoverflow/topicmove', 'init');
$PAGE->requires->js_call_amd('mod_moodleoverflow/rating', 'init', [$USER->id, $modflow->allows_multiple_marks()]);

// The unsubscribe link of a mail only opens a confirmation dialog, the GET request changes nothing.
if (
    $unsubscribe
    && permissions::can_change_subscription($modflow, $USER->id)
    && subscription::is_subscribed($modflow, $USER->id)
) {
    $params = ['moodleoverflow', $modflow->id, get_string('confirmunsubscribe', 'moodleoverflow', format_string($modflow->name))];
    $PAGE->requires->js_call_amd('mod_moodleoverflow/subscription', 'confirmUnsubscribe', $params);
}

$SESSION->fromdiscussion = qualified_me();

$event = course_module_viewed::create(['objectid' => $modflow->id, 'context' => $modflow->get_context()]);
$event->trigger();

// Output starts here.
echo $OUTPUT->header();
echo $OUTPUT->render(new view_page($modflow, $page));
echo $OUTPUT->footer();
