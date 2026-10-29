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
 * File to display a moodleoverflow discussion.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\event\discussion_viewed;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service\readtracking;
use mod_moodleoverflow\local\service\subscription;
use mod_moodleoverflow\output\pages\discussion\discussion_page;

require_once('../../config.php');
global $PAGE, $USER, $SESSION, $OUTPUT;

// Declare parameters.
$d = required_param('d', PARAM_INT); // The ID of the discussion.
$unsubscribe = optional_param('unsubscribe', 0, PARAM_BOOL); // Unsubscribe link of a mail.

// Set the URL that should be used to return to this page.
$PAGE->set_url('/mod/moodleoverflow/discussion.php', ['d' => $d]);
$PAGE->add_body_class('limitedwidth');

// Check if the discussion is valid.
$discussion = discussion::from_id($d);
$modflow = $discussion->get_moodleoverflow();
$course = $modflow->get_course();

// A user must be logged in and enrolled to the course.
require_course_login($course, true, $modflow->get_cm());

// Has the user the capability to view the discussion?
if (!permissions::can_view_discussion($discussion, $USER->id)) {
    throw new moodle_exception('noviewdiscussionspermission', 'moodleoverflow', $modflow->get_link()->out());
}

// The user entered the discussion. Mark it as read and trigger the discussion viewed event.
readtracking::mark_discussion_read($discussion, $USER->id);
discussion_viewed::create(['context' => $modflow->get_context(), 'objectid' => $discussion->get_id()])->trigger();

// Unset where the user is coming from.
// Allows to calculate the correct return url later.
unset($SESSION->fromdiscussion);

// Append the discussion name to the navigation.
$forumnode = $PAGE->navigation->find($modflow->get_cm()->id, navigation_node::TYPE_ACTIVITY);
if (empty($forumnode)) {
    $forumnode = $PAGE->navbar;
} else {
    $forumnode->make_active();
}

$node = $forumnode->add(format_string($discussion->name), $discussion->get_link());
$node->display = false;

$PAGE->requires->js_call_amd('mod_moodleoverflow/reviewing', 'init');
$PAGE->requires->js_call_amd('mod_moodleoverflow/rating', 'init', [$USER->id, $modflow->allows_multiple_marks()]);
$PAGE->requires->js_call_amd('mod_moodleoverflow/deletepost', 'init');

// The unsubscribe link of a mail only opens a confirmation dialog, the GET request changes nothing.
if (
    $unsubscribe
    && permissions::can_change_discussion_subscription($discussion, $USER->id)
    && subscription::is_subscribed_to_discussion($discussion, $USER->id)
) {
    $names = (object) ['discussion' => format_string($discussion->name), 'moodleoverflow' => format_string($modflow->name)];
    $params = ['discussion', $discussion->get_id(), get_string('confirmunsubscribediscussion', 'moodleoverflow', $names)];
    $PAGE->requires->js_call_amd('mod_moodleoverflow/subscription', 'confirmUnsubscribe', $params);
}

// Initiate the page.
$PAGE->set_title($course->shortname . ': ' . format_string($discussion->name));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($discussion->name), 1, 'discussionname');
echo $OUTPUT->render(new discussion_page($discussion));
echo $OUTPUT->footer();
