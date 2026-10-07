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
 * File to mark posts as read.
 * LEARNWEB-TODO: this file is deprecated and should not longer be used, as it reloads the page. Use readtracking js.
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\readtracking;

require_once('../../config.php');

global $CFG, $DB, $PAGE, $USER, $SESSION, $OUTPUT;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

// Define the parameters.
$moodleoverflowid = required_param('m', PARAM_INT);         // The moodleoverflowinstance to mark.
$discussionid = optional_param('d', 0, PARAM_INT);      // The discussion to mark.
$returndiscussion = optional_param('return', 0, PARAM_INT); // The page to return to.

// Prepare the array that should be used to return to this page.
$url = new moodle_url('/mod/moodleoverflow/markposts.php', ['m' => $moodleoverflowid]);

// Check the optional params.
if ($discussionid !== 0) {
    $url->param('d', $discussionid);
}
if ($returndiscussion !== 0) {
    $url->param('returndiscussion', $returndiscussion);
}

// Set the url that should be used to return to this page.
$PAGE->set_url($url);

// Retrieve the connected moodleoverflow instance.
$moodleoverflow = moodleoverflow::from_id($moodleoverflowid);
$course = $moodleoverflow->get_course();

// From now on, the user must be logged in and enrolled.
require_login($course, false, $moodleoverflow->get_cm());

// Default relink address.
if ($returndiscussion === 0) {
    // If no parameter is set, relink to the view.
    $returnto = new moodle_url("/mod/moodleoverflow/view.php", ['m' => $moodleoverflow->id]);
} else {
    // Else relink back to the discussion we are coming from.
    $returnto = new moodle_url("/mod/moodleoverflow/discussion.php", ['d' => $returndiscussion]);
}

// Guests can't mark posts as read.
if (isguestuser()) {
    // Set Page-Parameter.
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);

    // Create the message.
    $message = get_string('noguesttracking', 'moodleoverflow') . '<br /><br />' . get_string('liketologin');

    // Display the page with a confirm-element.
    echo $OUTPUT->header();
    echo $OUTPUT->confirm($message, get_login_url(), $returnto);
    echo $OUTPUT->footer();
    exit;
}

permissions::ensure(permissions::can_track($moodleoverflow, $USER->id), 'markreadfailed');

// Delete a single discussion.
if (!empty($discussionid)) {
    // Mark all the discussions read.
    $discussion = discussion::from_id($discussionid);
    if ($discussion->get_moodleoverflowid() != $moodleoverflow->id) {
        throw new moodle_exception('invaliddiscussionid', 'moodleoverflow');
    }
    permissions::ensure(permissions::can_view_discussion($discussion, $USER->id), 'markreadfailed');
    readtracking::mark_discussion_read($discussion, $USER->id);
    $message = get_string('markdiscussionreadsuccessful', 'moodleoverflow');
} else {
    // Mark all message read in the current instance.
    readtracking::mark_moodleoverflow_read($moodleoverflow, $USER->id);
    $message = get_string('markmoodleoverflowreadsuccessful', 'moodleoverflow');
}
redirect(moodleoverflow_go_back_to($returnto), $message, null, notification::NOTIFY_SUCCESS);
